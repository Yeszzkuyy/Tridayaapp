<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DocumentCategory;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectStatus;
use App\Models\Proposal;
use App\Models\User;
use App\Models\WorkType;
use App\Notifications\ProjectConvertedNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PRD project workflow: convert proposal → project, project read-only untuk
 * sales, tab daftar project, dan pemisahan dokumen customer vs project.
 */
class ProjectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function userWithRoles(array $roles): User
    {
        $user = User::factory()->create();
        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function makeCustomer(?string $name = null): Customer
    {
        return Customer::create(['name' => $name ?? 'PT Proyek '.uniqid(), 'pt_group' => 'NTI']);
    }

    private function makeLead(?User $sales = null): Lead
    {
        return Lead::create([
            'customer_id' => $this->makeCustomer()->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'hot',
            'assigned_to' => $sales?->id,
        ]);
    }

    private function makeProposal(Lead $lead, User $creator, string $status = 'accepted'): Proposal
    {
        return Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => 'Q/2026/10/'.uniqid(),
            'created_by' => $creator->id,
            'proposal_date' => now()->toDateString(),
            'customer' => $lead->customer->name,
            'status' => $status,
        ]);
    }

    private function makeProject(User $sales, User $tech, array $overrides = []): Project
    {
        if (! auth()->check()) {
            $this->actingAs($sales);
        }

        $workType = WorkType::firstOrCreate(['name' => 'Instalasi']);

        return Project::create(array_merge([
            'customer_id' => $this->makeCustomer()->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project '.uniqid(),
            'sales_user_id' => $sales->id,
            'teknisi_user_id' => $tech->id,
        ], $overrides));
    }

    public function test_convert_proposal_creates_project_with_notes_status_and_notification(): void
    {
        Notification::fake();

        $sales = $this->userWithRoles(['sales']);
        $leadTech = $this->userWithRoles(['lead-technician']);
        $lead = $this->makeLead($sales);
        $proposal = $this->makeProposal($lead, $sales);
        $workType = WorkType::create(['name' => 'Instalasi CCTV']);
        $pendingId = ProjectStatus::where('name', 'Pending')->value('id');

        $this->actingAs($sales)->post(route('sales.proposals.convert', $proposal), [
            'work_type_id' => $workType->id,
            'start_date' => now()->addDay()->toDateString(),
            'notes' => 'Gunakan kabel outdoor.',
        ])->assertRedirect();

        $project = Project::firstOrFail();
        $this->assertSame($proposal->id, $project->proposal_id);
        $this->assertSame($sales->id, $project->sales_user_id);
        $this->assertSame($pendingId, $project->project_status_id);
        $this->assertStringContainsString('Catatan untuk Teknisi: Gunakan kabel outdoor.', $project->description);

        Notification::assertSentTo($leadTech, ProjectConvertedNotification::class);

        // Idempoten: convert kedua kali ditolak, tidak membuat project baru.
        $this->actingAs($sales)->post(route('sales.proposals.convert', $proposal), [
            'work_type_id' => $workType->id,
        ])->assertStatus(422);
        $this->assertSame(1, Project::count());
    }

    public function test_sales_cannot_convert_proposal_of_another_sales(): void
    {
        $owner = $this->userWithRoles(['sales']);
        $other = $this->userWithRoles(['sales']);
        $lead = $this->makeLead($owner);
        $proposal = $this->makeProposal($lead, $owner);
        $workType = WorkType::create(['name' => 'Instalasi']);

        $this->actingAs($other)->post(route('sales.proposals.convert', $proposal), [
            'work_type_id' => $workType->id,
        ])->assertForbidden();

        $this->assertSame(0, Project::count());
    }

    public function test_sales_projects_tab_only_shows_own_projects(): void
    {
        $salesA = $this->userWithRoles(['sales']);
        $salesB = $this->userWithRoles(['sales']);
        $tech = $this->userWithRoles(['technician']);

        $mine = $this->makeProject($salesA, $tech, ['project_name' => 'Project Punyaku']);
        $this->makeProject($salesB, $tech, ['project_name' => 'Project Oranglain']);

        $html = $this->actingAs($salesA)->get(route('projects.index', ['tab' => 'sales']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Project Punyaku', $html);
        $this->assertStringNotContainsString('Project Oranglain', $html);

        // Sales tidak bisa mengintip tab all.
        $html = $this->actingAs($salesA)->get(route('projects.index', ['tab' => 'all']))
            ->assertOk()->getContent();
        $this->assertStringNotContainsString('Project Oranglain', $html);
        $this->assertStringContainsString($mine->project_name, $html);
    }

    public function test_technician_assigned_tab_filters_by_pic(): void
    {
        $sales = $this->userWithRoles(['sales']);
        $techA = $this->userWithRoles(['technician']);
        $techB = $this->userWithRoles(['technician']);

        $this->makeProject($sales, $techA, ['project_name' => 'Project Teknisi A']);
        $this->makeProject($sales, $techB, ['project_name' => 'Project Teknisi B']);

        $html = $this->actingAs($techA)->get(route('projects.index', ['tab' => 'assigned']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Project Teknisi A', $html);
        $this->assertStringNotContainsString('Project Teknisi B', $html);
    }

    public function test_projects_status_filter_works(): void
    {
        $sales = $this->userWithRoles(['sales']);
        $tech = $this->userWithRoles(['technician']);
        $open = ProjectStatus::where('name', 'Open')->firstOrFail();
        $done = ProjectStatus::where('name', 'Done')->firstOrFail();

        $this->makeProject($sales, $tech, ['project_name' => 'Project Open', 'project_status_id' => $open->id]);
        $this->makeProject($sales, $tech, ['project_name' => 'Project Done', 'project_status_id' => $done->id]);

        $html = $this->actingAs($tech)->get(route('projects.index', ['tab' => 'all', 'status' => $open->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Project Open', $html);
        $this->assertStringNotContainsString('Project Done', $html);
    }

    public function test_sales_can_only_upload_survey_documents_on_own_project(): void
    {
        Storage::fake('private');

        $sales = $this->userWithRoles(['sales']);
        $otherSales = $this->userWithRoles(['sales-mgk']);
        $tech = $this->userWithRoles(['technician']);
        $project = $this->makeProject($sales, $tech);

        $survey = DocumentCategory::create(['name' => 'Report Survey']);
        $installation = DocumentCategory::create(['name' => 'Report Instalasi']);

        // Survey untuk project miliknya: boleh.
        $this->actingAs($sales)->post(route('project-documents.store', $project), [
            'file' => UploadedFile::fake()->create('survey.pdf', 20, 'application/pdf'),
            'document_category_id' => $survey->id,
        ])->assertRedirect();

        // Kategori lain: ditolak.
        $this->actingAs($sales)->post(route('project-documents.store', $project), [
            'file' => UploadedFile::fake()->create('instalasi.pdf', 20, 'application/pdf'),
            'document_category_id' => $installation->id,
        ])->assertForbidden();

        // Sales lain: ditolak walaupun Survey.
        $this->actingAs($otherSales)->post(route('project-documents.store', $project), [
            'file' => UploadedFile::fake()->create('survey.pdf', 20, 'application/pdf'),
            'document_category_id' => $survey->id,
        ])->assertForbidden();

        // Teknisi bebas kategori.
        $this->actingAs($tech)->post(route('project-documents.store', $project), [
            'file' => UploadedFile::fake()->create('instalasi.pdf', 20, 'application/pdf'),
            'document_category_id' => $installation->id,
        ])->assertRedirect();
    }

    public function test_customer_documents_show_project_documents_subtab(): void
    {
        $sales = $this->userWithRoles(['sales']);
        $tech = $this->userWithRoles(['technician']);
        $project = $this->makeProject($sales, $tech);
        $customer = $project->customer;

        ProjectDocument::create([
            'project_id' => $project->id,
            'customer_id' => $customer->id,
            'file_name' => 'survey.pdf',
            'file_path' => 'documents/'.$project->id.'/survey.pdf',
            'file_size' => 100,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $tech->id,
        ]);

        $html = $this->actingAs($sales)->get(route('customers.show', $customer))
            ->assertOk()->getContent();

        $this->assertStringContainsString(__('Project Documents'), $html);
        $this->assertStringContainsString(__('Customer Documents'), $html);
    }
}
