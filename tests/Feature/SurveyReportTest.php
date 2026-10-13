<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
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
        $this->assertStringContainsString('No surveys yet. Create a survey first.', $html);
        // Tombol header "Create Report" & "Upload PDF" selalu tampil.
        $this->assertStringContainsString(config('services.survey-report-template'), $html);
        $this->assertStringContainsString('report-upload-open', $html);
    }

    public function test_index_shows_correct_report_actions(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');

        $without = $this->makeSurvey(['location' => 'Bandung']);
        $with = $this->makeSurvey(['location' => 'Surabaya']);
        Storage::disk('private')->put('surveys/2/reports/y.pdf', 'pdf');
        $with->update(['report_file' => 'surveys/2/reports/y.pdf', 'status' => 'completed']);

        $html = $this->actingAs($technician)->get(route('teknisi.surveys.index'))->assertOk()->getContent();

        // Survey tanpa laporan: tombol upload (trigger modal), tanpa tombol view report.
        $this->assertStringContainsString('report-upload', $html);
        $this->assertStringNotContainsString(route('teknisi.surveys.report.view', $without), $html);
        $this->assertStringNotContainsString(route('teknisi.surveys.report.download', $without), $html);

        // Survey dengan laporan: tombol view & download.
        $this->assertStringContainsString(route('teknisi.surveys.report.view', $with), $html);
        $this->assertStringContainsString(route('teknisi.surveys.report.download', $with), $html);

        // Tombol header Upload PDF + dropdown pilihan survey di modal.
        $this->assertStringContainsString('report-upload-open', $html);
        $this->assertStringContainsString('Select Survey', $html);
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
}
