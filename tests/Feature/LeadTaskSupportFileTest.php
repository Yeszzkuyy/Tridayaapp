<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Lampiran pendukung dari Sales untuk request Inside Sales. */
class LeadTaskSupportFileTest extends TestCase
{
    use RefreshDatabase;

    private bool $seeded = false;

    private function userWithRole(string $role): User
    {
        if (! $this->seeded) {
            $this->seed(RoleAndPermissionSeeder::class);
            $this->seeded = true;
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeLead(User $sales): Lead
    {
        $customer = Customer::create(['name' => 'PT Lampiran '.uniqid()]);

        return Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'cool',
            'assigned_to' => $sales->id,
            'incoming_date' => now()->toDateString(),
        ]);
    }

    private function makeTask(User $sales, User $inside, ?Lead $lead = null): LeadTask
    {
        $lead ??= $this->makeLead($sales);

        return LeadTask::create([
            'lead_id' => $lead->id,
            'title' => 'Buatkan penawaran',
            'description' => 'Butuh penawaran lengkap.',
            'assigned_to' => $inside->id,
            'created_by' => $sales->id,
            'status' => 'todo',
        ]);
    }

    public function test_sales_can_attach_supporting_files_when_creating_request(): void
    {
        Storage::fake('private');
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $lead = $this->makeLead($sales);

        $this->actingAs($sales)->post(route('lead-tasks.store'), [
            'lead_id' => $lead->id,
            'title' => 'Buatkan penawaran CCTV',
            'description' => 'Lampirkan brief customer.',
            'assigned_to' => $inside->id,
            'request_files' => [
                UploadedFile::fake()->create('brief-customer.pdf', 100, 'application/pdf'),
            ],
        ])->assertRedirect();

        $task = LeadTask::where('lead_id', $lead->id)->firstOrFail();

        $this->assertCount(1, $task->requestFiles);
        Storage::disk('private')->assertExists($task->requestFiles->first()->file_path);
    }

    public function test_sales_can_add_and_delete_own_supporting_file_on_task_page(): void
    {
        Storage::fake('private');
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside);

        $this->actingAs($sales)->post(route('lead-tasks.request-files.store', $task), [
            'request_files' => [
                UploadedFile::fake()->create('requirement.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ],
        ])->assertRedirect();

        $file = $task->requestFiles()->firstOrFail();
        Storage::disk('private')->assertExists($file->file_path);

        $this->actingAs($sales)
            ->delete(route('lead-tasks.request-files.destroy', [$task, $file]))
            ->assertRedirect();

        $this->assertDatabaseMissing('lead_task_request_files', ['id' => $file->id]);
        Storage::disk('private')->assertMissing($file->file_path);
    }

    public function test_inside_sales_can_download_but_not_upload_supporting_files(): void
    {
        Storage::fake('private');
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside);

        $this->actingAs($sales)->post(route('lead-tasks.request-files.store', $task), [
            'request_files' => [
                UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf'),
            ],
        ])->assertRedirect();

        $file = $task->requestFiles()->firstOrFail();

        $this->actingAs($inside)
            ->get(route('lead-tasks.request-files.download', [$task, $file]))
            ->assertOk();

        $this->actingAs($inside)->post(route('lead-tasks.request-files.store', $task), [
            'request_files' => [
                UploadedFile::fake()->create('balasan.pdf', 100, 'application/pdf'),
            ],
        ])->assertForbidden();
    }

    public function test_other_sales_cannot_upload_or_delete_supporting_files(): void
    {
        Storage::fake('private');
        $sales = $this->userWithRole('sales');
        $other = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside);

        $this->actingAs($sales)->post(route('lead-tasks.request-files.store', $task), [
            'request_files' => [
                UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf'),
            ],
        ])->assertRedirect();

        $file = $task->requestFiles()->firstOrFail();

        $this->actingAs($other)->post(route('lead-tasks.request-files.store', $task), [
            'request_files' => [
                UploadedFile::fake()->create('nyasar.pdf', 100, 'application/pdf'),
            ],
        ])->assertForbidden();

        $this->actingAs($other)
            ->delete(route('lead-tasks.request-files.destroy', [$task, $file]))
            ->assertForbidden();

        $this->assertDatabaseHas('lead_task_request_files', ['id' => $file->id]);
    }
}
