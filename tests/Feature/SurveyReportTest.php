<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Survey;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SurveyReportTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeSurvey(array $overrides = []): Survey
    {
        // ProjectObserver menulis ProjectActivity dengan auth()->id(); sediakan
        // aktor bila belum ada agar fixture tidak melanggar NOT NULL.
        if (! auth()->check()) {
            $this->actingAs(User::factory()->create());
        }

        $customer = Customer::create(['name' => 'PT Survey Uji']);
        $workType = WorkType::create(['name' => 'Instalasi']);
        $project = Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Survey Uji',
        ]);

        return Survey::create(array_merge([
            'project_id' => $project->id,
            'location' => 'Jakarta',
            'status' => 'scheduled',
        ], $overrides));
    }

    public function test_technician_can_upload_pdf_and_status_becomes_completed(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $survey = $this->makeSurvey();

        $response = $this->actingAs($technician)->post(
            route('teknisi.surveys.report.upload', $survey),
            ['report' => UploadedFile::fake()->create('laporan.pdf', 200, 'application/pdf')]
        );

        $response->assertRedirect(route('teknisi.surveys.index'))->assertSessionHasNoErrors();

        $survey->refresh();
        $this->assertNotNull($survey->report_file);
        $this->assertTrue($survey->hasReport());
        $this->assertSame(Survey::STATUS_COMPLETED, $survey->status);
        $this->assertStringEndsWith('.pdf', $survey->report_file);
        Storage::disk('private')->assertExists($survey->report_file);
    }

    public function test_non_pdf_file_is_rejected_and_status_unchanged(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $survey = $this->makeSurvey();

        $this->actingAs($technician)->post(
            route('teknisi.surveys.report.upload', $survey),
            ['report' => UploadedFile::fake()->create('catatan.txt', 5, 'text/plain')]
        )->assertSessionHasErrors('report');

        $survey->refresh();
        $this->assertNull($survey->report_file);
        $this->assertSame('scheduled', $survey->status);
        $this->assertCount(0, Storage::disk('private')->allFiles());
    }

    public function test_oversized_pdf_is_rejected(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $survey = $this->makeSurvey();

        // 6000 KB > batas 5120 KB (5 MB).
        $this->actingAs($technician)->post(
            route('teknisi.surveys.report.upload', $survey),
            ['report' => UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf')]
        )->assertSessionHasErrors('report');

        $this->assertNull($survey->fresh()->report_file);
    }

    public function test_missing_file_is_rejected(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $survey = $this->makeSurvey();

        $this->actingAs($technician)
            ->post(route('teknisi.surveys.report.upload', $survey), [])
            ->assertSessionHasErrors('report');

        $this->assertNull($survey->fresh()->report_file);
    }

    public function test_user_without_manage_permission_cannot_upload(): void
    {
        Storage::fake('private');
        $viewer = $this->userWithRole('prakerin-technician'); // view-technician saja
        $survey = $this->makeSurvey();

        $this->actingAs($viewer)->post(
            route('teknisi.surveys.report.upload', $survey),
            ['report' => UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf')]
        )->assertForbidden();

        $this->assertNull($survey->fresh()->report_file);
        $this->assertCount(0, Storage::disk('private')->allFiles());
    }

    public function test_user_without_view_permission_cannot_view_or_download(): void
    {
        Storage::fake('private');
        $outsider = $this->userWithRole('marketing'); // tanpa view-technician
        $survey = $this->makeSurvey(['report_file' => 'surveys/1/reports/x.pdf', 'status' => 'completed']);
        Storage::disk('private')->put('surveys/1/reports/x.pdf', 'pdf');

        $this->actingAs($outsider)
            ->get(route('teknisi.surveys.report.view', $survey))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->get(route('teknisi.surveys.report.download', $survey))
            ->assertForbidden();
    }

    public function test_view_and_download_report_when_authorized(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $survey = $this->makeSurvey();
        Storage::disk('private')->put('surveys/1/reports/x.pdf', 'pdf-content');
        $survey->update(['report_file' => 'surveys/1/reports/x.pdf', 'status' => 'completed']);

        $this->actingAs($technician)
            ->get(route('teknisi.surveys.report.view', $survey))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($technician)
            ->get(route('teknisi.surveys.report.download', $survey))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_view_report_returns_404_when_no_report(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $survey = $this->makeSurvey();

        $this->actingAs($technician)
            ->get(route('teknisi.surveys.report.view', $survey))
            ->assertNotFound();
    }

    public function test_replace_failure_keeps_old_report(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $survey = $this->makeSurvey();

        // Upload pertama (valid).
        $this->actingAs($technician)->post(
            route('teknisi.surveys.report.upload', $survey),
            ['report' => UploadedFile::fake()->create('awal.pdf', 100, 'application/pdf')]
        )->assertSessionHasNoErrors();

        $oldPath = $survey->fresh()->report_file;
        $this->assertNotNull($oldPath);
        Storage::disk('private')->assertExists($oldPath);

        // Ganti dengan file tidak valid.
        $this->actingAs($technician)->post(
            route('teknisi.surveys.report.upload', $survey),
            ['report' => UploadedFile::fake()->create('salah.txt', 5, 'text/plain')]
        )->assertSessionHasErrors('report');

        $this->assertSame($oldPath, $survey->fresh()->report_file);
        Storage::disk('private')->assertExists($oldPath);
    }

    public function test_index_empty_state_guides_to_upload(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');

        $html = $this->actingAs($technician)->get(route('teknisi.surveys.index'))->assertOk()->getContent();

        $this->assertStringContainsString('No survey data yet.', $html);
        $this->assertStringContainsString('Create a survey first, then upload the PDF report from its row.', $html);
        // Upload dipicu dari baris/detail agar Survey selalu sudah diketahui.
        $this->assertStringNotContainsString('report-upload-open', $html);
        // Template report tidak lagi di header daftar (hanya di detail survey).
        $this->assertStringNotContainsString(config('services.survey-report-template'), $html);
    }

    public function test_index_has_no_report_actions_in_rows(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');

        $without = $this->makeSurvey(['location' => 'Bandung']);
        $with = $this->makeSurvey(['location' => 'Surabaya']);
        Storage::disk('private')->put('surveys/2/reports/y.pdf', 'pdf');
        $with->update(['report_file' => 'surveys/2/reports/y.pdf', 'status' => 'completed']);

        $html = $this->actingAs($technician)->get(route('teknisi.surveys.index'))->assertOk()->getContent();

        // Aksi laporan hanya di halaman detail, bukan di baris daftar.
        $this->assertStringNotContainsString(route('teknisi.surveys.report.view', $with), $html);
        $this->assertStringNotContainsString(route('teknisi.surveys.report.download', $with), $html);
        $this->assertStringNotContainsString(route('teknisi.surveys.report.view', $without), $html);
        $this->assertStringNotContainsString("new CustomEvent('report-upload'", $html);
        // Menu baris tetap ada (view/edit/delete) dan modal tetap dirender.
        $this->assertStringContainsString('View details', $html);
        $this->assertStringContainsString('data-report-upload-form', $html);
    }

    public function test_show_lists_report_actions(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');

        // Belum ada laporan → Create + Upload.
        $noReport = $this->makeSurvey(['location' => 'Bandung']);
        $html = $this->actingAs($technician)->get(route('teknisi.surveys.show', $noReport))->assertOk()->getContent();
        $this->assertStringContainsString(config('services.survey-report-template'), $html);
        $this->assertStringContainsString("new CustomEvent('report-upload'", $html);

        // Sudah ada laporan → View + Download + Replace.
        $with = $this->makeSurvey(['location' => 'Surabaya']);
        $path = 'surveys/'.$with->id.'/reports/y.pdf';
        Storage::disk('private')->put($path, 'pdf');
        $with->update(['report_file' => $path, 'report_file_name' => 'laporan.pdf', 'status' => 'completed']);

        $html2 = $this->actingAs($technician)->get(route('teknisi.surveys.show', $with))->assertOk()->getContent();
        $this->assertStringContainsString(route('teknisi.surveys.report.view', $with), $html2);
        $this->assertStringContainsString(route('teknisi.surveys.report.download', $with), $html2);
    }

    public function test_view_and_download_use_original_filename(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $survey = $this->makeSurvey();
        $path = 'surveys/'.$survey->id.'/reports/x.pdf';
        Storage::disk('private')->put($path, 'pdf-content');
        $survey->update(['report_file' => $path, 'report_file_name' => 'laporan-akhir-survey.pdf', 'status' => 'completed']);

        $view = $this->actingAs($technician)->get(route('teknisi.surveys.report.view', $survey))->assertOk();
        $this->assertStringContainsString('laporan-akhir-survey.pdf', $view->headers->get('content-disposition') ?? '');

        $download = $this->actingAs($technician)->get(route('teknisi.surveys.report.download', $survey))->assertOk();
        $this->assertStringContainsString('laporan-akhir-survey.pdf', $download->headers->get('content-disposition') ?? '');
    }

    public function test_survey_regression_pages_still_work(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $survey = $this->makeSurvey();

        $this->actingAs($technician)->get(route('teknisi.surveys.index'))->assertOk();
        $this->actingAs($technician)->get(route('teknisi.surveys.show', $survey))->assertOk();
        $this->actingAs($technician)->get(route('teknisi.surveys.create'))->assertOk();
        $this->actingAs($technician)->get(route('teknisi.surveys.edit', $survey))->assertOk();
    }

    private function makeProject(string $name): Project
    {
        $customer = Customer::create(['name' => 'PT '.$name]);
        $workType = WorkType::create(['name' => 'Instalasi']);

        return Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project '.$name,
        ]);
    }

    public function test_create_survey_requires_report_and_saves_completed(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $this->actingAs($technician);
        $project = $this->makeProject('Baru');

        $this->post(route('teknisi.surveys.store'), [
            'project_id' => $project->id,
            'survey_date' => '2026-10-13',
            'location' => 'Jakarta',
            'report' => UploadedFile::fake()->create('laporan.pdf', 200, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $survey = Survey::where('project_id', $project->id)->firstOrFail();
        $this->assertSame(Survey::STATUS_COMPLETED, $survey->status);
        $this->assertNotNull($survey->report_file);
        Storage::disk('private')->assertExists($survey->report_file);
    }

    public function test_create_survey_rejects_missing_report(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $this->actingAs($technician);
        $project = $this->makeProject('TanpaFile');

        $this->post(route('teknisi.surveys.store'), [
            'project_id' => $project->id,
        ])->assertSessionHasErrors('report');

        $this->assertDatabaseMissing('surveys', ['project_id' => $project->id]);
    }

    public function test_create_accepts_common_document(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $this->actingAs($technician);
        $project = $this->makeProject('Dokumen');

        $this->post(route('teknisi.surveys.store'), [
            'project_id' => $project->id,
            'report' => UploadedFile::fake()->create('boq.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('surveys', ['project_id' => $project->id]);
    }

    public function test_create_rejects_oversized_report(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $this->actingAs($technician);
        $project = $this->makeProject('Besar');

        $this->post(route('teknisi.surveys.store'), [
            'project_id' => $project->id,
            'report' => UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf'),
        ])->assertSessionHasErrors('report');

        $this->assertDatabaseMissing('surveys', ['project_id' => $project->id]);
    }

    public function test_sales_sees_only_owned_surveys(): void
    {
        Storage::fake('private');
        $this->seed(RoleAndPermissionSeeder::class);
        $owner = User::factory()->create();
        $owner->assignRole('sales');
        $other = User::factory()->create();
        $other->assignRole('sales');
        $this->actingAs($owner);

        $mine = $this->makeProject('Milik');
        $mine->update(['sales_user_id' => $owner->id]);
        $s1 = Survey::create(['project_id' => $mine->id, 'status' => 'completed']);
        $theirs = $this->makeProject('Lain');
        $theirs->update(['sales_user_id' => $other->id]);
        $s2 = Survey::create(['project_id' => $theirs->id, 'status' => 'completed']);

        $html = $this->get(route('teknisi.surveys.index'))->assertOk()->getContent();
        $this->assertStringContainsString(route('teknisi.surveys.show', $s1), $html);
        $this->assertStringNotContainsString(route('teknisi.surveys.show', $s2), $html);
    }

    public function test_sales_cannot_access_survey_of_other_owner(): void
    {
        Storage::fake('private');
        $this->seed(RoleAndPermissionSeeder::class);
        $owner = User::factory()->create();
        $owner->assignRole('sales');
        $other = User::factory()->create();
        $other->assignRole('sales');
        $this->actingAs($other);

        $project = $this->makeProject('Milik Orang Lain');
        $project->update(['sales_user_id' => $owner->id]);
        $survey = Survey::create(['project_id' => $project->id, 'status' => 'completed']);
        $path = 'surveys/'.$survey->id.'/reports/x.pdf';
        Storage::disk('private')->put($path, 'pdf');
        $survey->update(['report_file' => $path, 'status' => 'completed']);

        $this->get(route('teknisi.surveys.show', $survey))->assertForbidden();
        $this->get(route('teknisi.surveys.report.view', $survey))->assertForbidden();
        $this->get(route('teknisi.surveys.report.download', $survey))->assertForbidden();
    }

    public function test_sales_owner_can_view_and_download_report(): void
    {
        Storage::fake('private');
        $this->seed(RoleAndPermissionSeeder::class);
        $sales = User::factory()->create();
        $sales->assignRole('sales');
        $this->actingAs($sales);

        $project = $this->makeProject('Milik Saya');
        $project->update(['sales_user_id' => $sales->id]);
        $survey = Survey::create(['project_id' => $project->id]);
        $path = 'surveys/'.$survey->id.'/reports/x.pdf';
        Storage::disk('private')->put($path, 'pdf');
        $survey->update(['report_file' => $path, 'report_file_name' => 'lap.pdf', 'status' => 'completed']);

        $this->get(route('teknisi.surveys.show', $survey))->assertOk();
        $this->get(route('teknisi.surveys.report.view', $survey))->assertOk();
        $this->get(route('teknisi.surveys.report.download', $survey))->assertOk();
    }

    public function test_sales_cannot_upload_or_edit_survey(): void
    {
        Storage::fake('private');
        $this->seed(RoleAndPermissionSeeder::class);
        $sales = User::factory()->create();
        $sales->assignRole('sales');
        $this->actingAs($sales);

        $project = $this->makeProject('Milik');
        $project->update(['sales_user_id' => $sales->id]);
        $survey = Survey::create(['project_id' => $project->id]);

        $this->post(route('teknisi.surveys.report.upload', $survey), [
            'report' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
        ])->assertForbidden();
        $this->get(route('teknisi.surveys.edit', $survey))->assertForbidden();
        $this->get(route('teknisi.surveys.create'))->assertForbidden();
    }

    public function test_admin_with_view_sales_not_unlocked(): void
    {
        Storage::fake('private');
        $this->seed(RoleAndPermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin'); // punya view-sales tapi bukan role sales
        $this->actingAs($admin);

        $project = $this->makeProject('Admin');
        $project->update(['sales_user_id' => $admin->id]);
        Survey::create(['project_id' => $project->id]);

        $this->get(route('teknisi.surveys.index'))->assertForbidden();
    }

    public function test_convert_lead_sets_sales_owner(): void
    {
        Storage::fake('private');
        $this->seed(RoleAndPermissionSeeder::class);
        $sales = User::factory()->create();
        $sales->assignRole('sales');
        $customer = Customer::create(['name' => 'PT Convert']);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'assigned_to' => $sales->id,
        ]);
        $status = ProjectStatus::create(['name' => 'Open']);
        $workType = WorkType::create(['name' => 'Instalasi']);

        $this->actingAs($sales)->patch(route('leads.convert', $lead), [
            'project_name' => 'Project Convert',
            'project_status_id' => $status->id,
            'work_type_id' => $workType->id,
        ])->assertRedirect();

        $project = Project::where('project_name', 'Project Convert')->firstOrFail();
        $this->assertSame((int) $sales->id, (int) $project->sales_user_id);
    }
}
