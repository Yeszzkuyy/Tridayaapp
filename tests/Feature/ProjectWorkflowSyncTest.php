<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DocumentCategory;
use App\Models\Instalasi;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectStatus;
use App\Models\Proposal;
use App\Models\Survey;
use App\Models\TechnicalRequest;
use App\Models\User;
use App\Models\WorkType;
use App\Services\Project\ProjectWorkflowService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectWorkflowSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->actingAs($this->userWithRole('technician'));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeProject(array $overrides = []): Project
    {
        $customer = Customer::firstOrCreate(['name' => 'PT Workflow Uji']);
        $workType = WorkType::firstOrCreate(['name' => 'Instalasi & Aktivasi']);
        $status = ProjectStatus::where('name', 'Pending')->firstOrFail();

        return Project::create(array_merge([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_status_id' => $status->id,
            'project_name' => 'Project Workflow Uji',
        ], $overrides));
    }

    private function makeLead(): Lead
    {
        $customer = Customer::firstOrCreate(['name' => 'PT Request Uji']);

        return Lead::create([
            'name' => 'Prospek Uji',
            'customer_id' => $customer->id,
            'assigned_to' => $this->userWithRole('sales')->id,
            'status' => 'warm',
            'pt_group' => 'NTI',
        ]);
    }

    public function test_survey_report_upload_checks_workflow_and_starts_project(): void
    {
        $project = $this->makeProject();
        $sales = $this->userWithRole('sales');
        $tech = User::where('name', auth()->user()->name)->first();

        Survey::create([
            'title' => 'Survey Awal',
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'sales_user_id' => $sales->id,
            'technician_user_id' => $tech?->id,
            'status' => Survey::STATUS_COMPLETED,
            'report_file' => 'surveys/1/reports/test.pdf',
            'report_file_name' => 'test.pdf',
        ]);

        $project->refresh();

        $this->assertContains('survey', $project->workflow_state ?? []);
        $this->assertSame(33, $project->progress); // request + survey of 6 steps
        $this->assertSame('On Progress', $project->status?->name);
    }

    public function test_completing_sizing_without_attachment_is_rejected(): void
    {
        $project = $this->makeProject();

        $this->post(route('teknisi.sizing-projects.store'), [
            'project_id' => $project->id,
            'status' => 'completed',
        ])->assertSessionHasErrors('attachments');

        $this->assertDatabaseCount('sizing_projects', 0);
    }

    public function test_installation_step_requires_all_active_installations_complete(): void
    {
        $project = $this->makeProject();

        Instalasi::create([
            'project_id' => $project->id,
            'status' => 'completed',
            'report_file' => 'instalasis/reports/a.pdf',
            'report_file_name' => 'a.pdf',
        ]);
        Instalasi::create([
            'project_id' => $project->id,
            'status' => 'scheduled',
        ]);

        $workflow = app(ProjectWorkflowService::class)->evaluate($project->fresh());
        $step = collect($workflow['steps'])->firstWhere('key', 'installation');

        $this->assertFalse($step['done']);
    }

    public function test_signed_bast_is_detected_and_does_not_close_without_invoice(): void
    {
        $project = $this->makeProject();
        $category = DocumentCategory::firstOrCreate(['name' => 'BAST']);
        $document = ProjectDocument::create([
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'doc_type' => 'bast',
            'document_category_id' => $category->id,
            'file_name' => 'bast.pdf',
            'file_path' => 'bast/test.pdf',
            'uploaded_by' => auth()->id(),
        ]);

        $project->forceFill([
            'bast_number' => 'BAST-001',
            'bast_document_id' => $document->id,
            'bast_signed_3dy_at' => now(),
            'bast_signed_customer_at' => now(),
        ])->save();

        $workflow = app(ProjectWorkflowService::class)->evaluate($project->fresh());
        $bastStep = collect($workflow['steps'])->firstWhere('key', 'bast');

        $this->assertTrue($bastStep['done']);
        // Tanpa invoice, project belum boleh Done.
        $this->assertNotSame('Done', $project->fresh()->status?->name);
    }

    public function test_convert_can_assign_pic_and_support_engineers(): void
    {
        $sales = $this->userWithRole('sales');
        $pic = $this->userWithRole('lead-technician');
        $support = $this->userWithRole('technician');
        $customer = Customer::create(['name' => 'PT Convert Engineer', 'pt_group' => 'NTI']);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'status' => 'hot',
            'assigned_to' => $sales->id,
        ]);

        $proposal = Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => 'Q/2026/11/'.uniqid(),
            'created_by' => $sales->id,
            'proposal_date' => now()->toDateString(),
            'customer' => $customer->name,
            'status' => 'accepted',
            'po_document_path' => 'proposals/test/po/test.pdf',
            'po_number' => 'PO-ENG-1',
            'po_date' => now()->toDateString(),
            'payment_scheme' => 'full',
            'po_verified_at' => now(),
            'po_verified_by' => $sales->id,
        ]);
        $proposal->items()->create([
            'description' => 'Paket instalasi',
            'quantity' => 1,
            'unit' => 'paket',
            'unit_price' => 1000000,
            'discount' => 0,
            'subtotal' => 1000000,
        ]);
        $proposal->recalculate();

        $workType = WorkType::firstOrCreate(['name' => 'Instalasi Engineer']);

        $this->actingAs($sales)->post(route('sales.proposals.convert', $proposal), [
            'work_type_id' => $workType->id,
            'teknisi_user_id' => $pic->id,
            'support_user_ids' => [$support->id],
        ])->assertRedirect();

        $project = Project::firstOrFail();

        $this->assertSame($pic->id, $project->teknisi_user_id);
        $this->assertSame($pic->name, $project->pic_engineer);
        $this->assertDatabaseHas('project_supports', [
            'project_id' => $project->id,
            'user_id' => $support->id,
        ]);
    }

    public function test_workflow_sync_runs_without_authenticated_user(): void
    {
        $project = $this->makeProject();
        $openStatus = ProjectStatus::where('name', 'Open')->firstOrFail();

        // CLI tidak punya sesi login; jangan sampai activity not-null gagal.
        $this->app['auth']->forgetGuards();

        $project->update(['project_status_id' => $openStatus->id]);

        $this->artisan('projects:sync-workflow')->assertExitCode(0);
    }

    public function test_admin_can_manage_non_project_installation_bast(): void
    {
        Storage::fake('private');

        $admin = $this->userWithRole('admin');
        $customer = Customer::create(['name' => 'PT Instalasi Lepas', 'pt_group' => 'NTI']);

        $installation = Instalasi::create([
            'customer_id' => $customer->id,
            'location' => 'Bandung',
            'status' => 'completed',
            'report_file' => 'instalasis/reports/test.pdf',
            'report_file_name' => 'test.pdf',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.bast.index'))
            ->assertOk()
            ->assertSee(__('Non-project Installation BAST'));

        $this->actingAs($admin)
            ->get(route('admin.instalasi-bast.show', $installation))
            ->assertOk();

        $this->actingAs($admin)->put(route('teknisi.instalasis.bast', $installation), [
            'bast_number' => 'BAST-LEPAS-1',
            'bast_date' => now()->toDateString(),
            'bast_signed_3dy_name' => 'Admin 3DY',
            'bast_signed_3dy_at' => now()->toDateString(),
            'bast_signed_customer_name' => 'Pak Customer',
            'bast_signed_customer_at' => now()->toDateString(),
            'bast_file' => UploadedFile::fake()->create('bast.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertTrue($installation->fresh()->isBastReady());
    }

    public function test_installation_request_creates_field_installation_and_requires_bast_to_complete(): void
    {
        $leadTech = $this->userWithRole('lead-technician');
        $worker = $this->userWithRole('technician');
        $lead = $this->makeLead();
        $sales = User::find($lead->assigned_to);

        $request = TechnicalRequest::create([
            'lead_id' => $lead->id,
            'requested_by' => $sales->id,
            'request_type' => 'installation',
            'title' => 'Instalasi Non Project',
            'status' => 'accepted',
        ]);

        $this->actingAs($leadTech)->post(route('sales.technical-requests.assign', $request), [
            'technician_id' => $worker->id,
        ])->assertRedirect();

        $request->refresh();
        $installation = Instalasi::where('technical_request_id', $request->id)->first();

        $this->assertNotNull($installation);
        $this->assertNull($installation->project_id);
        $this->assertSame($worker->id, $installation->technician_user_id);

        $request->update(['technical_result' => 'Selesai dipasang.']);

        // Belum ada hasil, tidak boleh selesai.
        $this->actingAs($leadTech)->post(route('sales.technical-requests.complete', $request), [
            'technical_result' => 'Selesai dipasang.',
        ])->assertStatus(422);

        $installation->update([
            'status' => 'completed',
            'report_file' => 'instalasis/reports/test.pdf',
            'report_file_name' => 'test.pdf',
        ]);

        // Sudah ada laporan tetapi BAST belum ditandatangani, tetap ditolak.
        $this->actingAs($leadTech)->post(route('sales.technical-requests.complete', $request), [
            'technical_result' => 'Selesai dipasang.',
        ])->assertStatus(422);

        $category = DocumentCategory::firstOrCreate(['name' => 'BAST']);
        $document = ProjectDocument::create([
            'customer_id' => $lead->customer_id,
            'doc_type' => 'bast',
            'document_category_id' => $category->id,
            'file_name' => 'bast.pdf',
            'file_path' => 'bast/test.pdf',
            'uploaded_by' => $leadTech->id,
        ]);

        $installation->update([
            'bast_document_id' => $document->id,
            'bast_signed_3dy_at' => now(),
            'bast_signed_customer_at' => now(),
        ]);

        $this->actingAs($leadTech)->post(route('sales.technical-requests.complete', $request), [
            'technical_result' => 'Selesai dipasang.',
        ])->assertRedirect();

        $this->assertSame('completed', $request->fresh()->status);
    }
}
