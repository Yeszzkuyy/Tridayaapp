<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DocumentCategory;
use App\Models\Instalasi;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use App\Models\WorkType;
use App\Notifications\BastReadyNotification;
use App\Notifications\InstallationCompletedNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BastWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        // Role finance disiapkan oleh migrasi panel; tes ini membuatnya
        // lebih dulu agar alur BAST → Finance bisa diuji terpisah.
        if (! Role::where('name', 'finance')->exists()) {
            $role = Role::create(['name' => 'finance', 'guard_name' => 'web']);
            $role->givePermissionTo(Permission::where('name', 'view-admin')->get());
        }
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeProject(): Project
    {
        $customer = Customer::create([
            'name' => 'PT BAST Workflow '.uniqid(),
            'pt_group' => 'NTI',
        ]);

        $workType = WorkType::firstOrCreate(['name' => 'Instalasi & Aktivasi']);

        return Project::withoutEvents(fn () => Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project BAST '.uniqid(),
            'project_code' => 'PRJ-'.uniqid(),
            'progress' => 30,
            'requires_installation' => true,
        ]));
    }

    private function makeInstallation(Project $project, string $status = 'on_progress'): Instalasi
    {
        return Instalasi::create([
            'project_id' => $project->id,
            'location' => 'Jakarta',
            'technician_pic' => 'Gilar',
            'status' => $status,
        ]);
    }

    public function test_completing_installation_requires_a_report_file(): void
    {
        $technician = $this->userWithRole('technician');
        $project = $this->makeProject();
        $instalasi = $this->makeInstallation($project);

        $this->actingAs($technician)
            ->put(route('teknisi.instalasis.update', $instalasi), [
                'project_id' => $project->id,
                'status' => 'completed',
            ])
            ->assertSessionHasErrors('report_file');

        $this->assertSame('on_progress', $instalasi->fresh()->status);
        $this->assertNull($instalasi->fresh()->completed_at);
    }

    public function test_completing_installation_sets_technical_completion_and_notifies_admin_finance(): void
    {
        Storage::fake('private');
        Notification::fake();

        $technician = $this->userWithRole('technician');
        $admin = $this->userWithRole('admin');
        $finance = $this->userWithRole('finance');
        $project = $this->makeProject();
        $instalasi = $this->makeInstallation($project);

        $this->actingAs($technician)
            ->put(route('teknisi.instalasis.update', $instalasi), [
                'project_id' => $project->id,
                'status' => 'completed',
                'report_file' => UploadedFile::fake()->create('laporan-instalasi.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('teknisi.instalasis.show', $instalasi));

        $instalasi->refresh();
        $this->assertNotNull($instalasi->completed_at);
        $this->assertSame($technician->id, $instalasi->completed_by);
        $this->assertTrue($instalasi->isTechnicallyComplete());

        Notification::assertSentTo($admin, InstallationCompletedNotification::class);
        Notification::assertSentTo($finance, InstallationCompletedNotification::class);
    }

    public function test_admin_uploads_signed_bast_and_marks_it_ready_for_finance(): void
    {
        Storage::fake('private');
        Notification::fake();

        $admin = $this->userWithRole('admin');
        $finance = $this->userWithRole('finance');
        $project = $this->makeProject();

        $this->actingAs($admin)
            ->put(route('admin.bast.update', $project), [
                'bast_number' => 'BAST-'.uniqid(),
                'bast_date' => now()->toDateString(),
                'bast_file' => UploadedFile::fake()->create('bast-signed.pdf', 100, 'application/pdf'),
                'bast_signed_3dy_name' => 'Perwakilan 3DY',
                'bast_signed_3dy_at' => now()->toDateString(),
                'bast_signed_customer_name' => 'Perwakilan Customer',
                'bast_signed_customer_at' => now()->toDateString(),
                'requires_installation' => 1,
            ])
            ->assertRedirect(route('admin.bast.index'));

        $project->refresh();

        $this->assertNotNull($project->bast_document_id);
        $this->assertNotNull($project->bast_ready_at);
        $this->assertTrue($project->isBastReady());

        $document = $project->bastDocument;
        $this->assertSame('bast-signed.pdf', $document->file_name);
        $this->assertSame('bast', $document->doc_type);
        $this->assertSame('BAST', DocumentCategory::find($document->document_category_id)?->name);
        Storage::disk('private')->assertExists($document->file_path);

        Notification::assertSentTo($finance, BastReadyNotification::class);
    }

    public function test_bast_is_not_ready_until_both_signatures_are_filled(): void
    {
        Storage::fake('private');

        $admin = $this->userWithRole('admin');
        $project = $this->makeProject();

        $this->actingAs($admin)
            ->put(route('admin.bast.update', $project), [
                'bast_number' => 'BAST-'.uniqid(),
                'bast_date' => now()->toDateString(),
                'bast_file' => UploadedFile::fake()->create('bast-signed.pdf', 100, 'application/pdf'),
                'bast_signed_3dy_name' => 'Perwakilan 3DY',
                'bast_signed_3dy_at' => now()->toDateString(),
                'requires_installation' => 1,
            ])
            ->assertRedirect(route('admin.bast.index'));

        $project->refresh();

        $this->assertNotNull($project->bast_document_id);
        $this->assertNull($project->bast_ready_at);
        $this->assertFalse($project->isBastReady());
    }

    public function test_replacing_bast_file_updates_the_same_document(): void
    {
        Storage::fake('private');

        $admin = $this->userWithRole('admin');
        $project = $this->makeProject();

        $this->actingAs($admin)->put(route('admin.bast.update', $project), [
            'bast_number' => 'BAST-'.uniqid(),
            'bast_file' => UploadedFile::fake()->create('bast-v1.pdf', 100, 'application/pdf'),
            'requires_installation' => 1,
        ])->assertRedirect();

        $firstId = $project->fresh()->bast_document_id;

        $this->actingAs($admin)->put(route('admin.bast.update', $project), [
            'bast_number' => 'BAST-'.uniqid(),
            'bast_file' => UploadedFile::fake()->create('bast-v2.pdf', 100, 'application/pdf'),
            'requires_installation' => 1,
        ])->assertRedirect();

        $project->refresh();

        $this->assertSame($firstId, $project->bast_document_id);
        $this->assertSame('bast-v2.pdf', $project->bastDocument->file_name);
        $this->assertSame(1, ProjectDocument::where('doc_type', 'bast')->count());
    }

    public function test_finance_can_view_bast_list_and_document_but_cannot_edit(): void
    {
        Storage::fake('private');

        $finance = $this->userWithRole('finance');
        $admin = $this->userWithRole('admin');
        $project = $this->makeProject();

        $this->actingAs($admin)->put(route('admin.bast.update', $project), [
            'bast_number' => 'BAST-'.uniqid(),
            'bast_file' => UploadedFile::fake()->create('bast-signed.pdf', 100, 'application/pdf'),
            'bast_signed_3dy_at' => now()->toDateString(),
            'bast_signed_customer_at' => now()->toDateString(),
            'requires_installation' => 1,
        ])->assertRedirect();

        $this->actingAs($finance)->get(route('admin.bast.index'))->assertOk();
        $this->actingAs($finance)->get(route('admin.bast.document', $project))->assertOk();
        $this->actingAs($finance)->get(route('admin.bast.edit', $project))->assertForbidden();
    }
}
