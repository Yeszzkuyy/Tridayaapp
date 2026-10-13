<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Survey;
use App\Models\TechnicalRequest;
use App\Models\User;
use App\Models\WorkType;
use App\Models\Project;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Integrasi Technical Request (tipe survey) ↔ Survey.
 *
 * Tiga aturan yang dijaga:
 * 1. Idempotensi — request yang sama diproses ulang tetap satu Survey.
 * 2. Konsistensi status — request tidak boleh selesai sebelum Survey
 *    memiliki laporan.
 * 3. Data lama — Survey lama tetap bisa dibuka & digunakan.
 */
class TechnicalRequestSurveyTest extends TestCase
{
    use RefreshDatabase;

    private bool $seeded = false;

    private function userWithRole(string $role): User
    {
        // Seeder role cukup dijalankan sekali per test — seed berulang
        // merusak assignment role.
        if (! $this->seeded) {
            $this->seed(RoleAndPermissionSeeder::class);
            $this->seeded = true;
        }
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeRequest(User $requester, array $overrides = []): TechnicalRequest
    {
        $customer = Customer::create(['name' => 'PT Request Uji', 'address' => 'Jakarta']);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'assigned_to' => $requester->id,
            'status' => 'warm',
        ]);

        // Status accepted = sudah lolos review lead teknisi, siap di-assign.
        return TechnicalRequest::create(array_merge([
            'lead_id' => $lead->id,
            'requested_by' => $requester->id,
            'request_type' => 'survey',
            'title' => 'Survey Kebutuhan CCTV',
            'requirement' => 'Butuh survei lokasi',
            'target_date' => '2026-10-20',
            'status' => 'accepted',
        ], $overrides));
    }

    public function test_assign_creates_exactly_one_survey_even_when_repeated(): void
    {
        Storage::fake('private');
        $leadTech = $this->userWithRole('lead-technician');
        $tech = $this->userWithRole('technician');
        $sales = $this->userWithRole('sales');
        $request = $this->makeRequest($sales);

        // Assign berulang kali — idempotensi: tetap satu survey.
        foreach (range(1, 3) as $i) {
            $this->actingAs($leadTech)->post(
                route('sales.technical-requests.assign', $request),
                ['technician_id' => $tech->id]
            );
        }

        $this->assertCount(1, Survey::where('technical_request_id', $request->id)->get());
        $this->assertSame('Survey Kebutuhan CCTV', $request->survey->title);
        $this->assertSame('scheduled', $request->survey->status);
    }

    public function test_complete_request_rejected_before_survey_has_report(): void
    {
        Storage::fake('private');
        $leadTech = $this->userWithRole('lead-technician');
        $tech = $this->userWithRole('technician');
        $sales = $this->userWithRole('sales');
        $request = $this->makeRequest($sales);

        $this->actingAs($leadTech)->post(
            route('sales.technical-requests.assign', $request),
            ['technician_id' => $tech->id]
        );
        $this->actingAs($tech)->post(route('sales.technical-requests.result', $request), [
            'technical_result' => 'Survei selesai, butuh 4 kamera.',
        ]);

        // Konsistensi status: survey belum punya laporan → request TIDAK
        // boleh dinyatakan selesai.
        $this->actingAs($tech)
            ->post(route('sales.technical-requests.complete', $request))
            ->assertStatus(422);

        $this->assertNotSame('completed', $request->fresh()->status);

        // Setelah laporan diunggah, request boleh selesai.
        $survey = $request->survey;
        $this->actingAs($tech)->post(route('teknisi.surveys.report.upload', $survey), [
            'report' => UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->actingAs($tech)
            ->post(route('sales.technical-requests.complete', $request))
            ->assertRedirect();

        $this->assertSame('completed', $request->fresh()->status);
        $this->assertSame(Survey::STATUS_COMPLETED, $survey->fresh()->status);
    }

    public function test_non_survey_request_does_not_create_survey(): void
    {
        Storage::fake('private');
        $leadTech = $this->userWithRole('lead-technician');
        $tech = $this->userWithRole('technician');
        $sales = $this->userWithRole('sales');
        $request = $this->makeRequest($sales, ['request_type' => 'consultation']);

        $this->actingAs($leadTech)->post(
            route('sales.technical-requests.assign', $request),
            ['technician_id' => $tech->id]
        );

        $this->assertNull($request->fresh()->survey);
        $this->assertDatabaseCount('surveys', 0);
    }

    public function test_survey_from_request_inherits_lead_data(): void
    {
        Storage::fake('private');
        $leadTech = $this->userWithRole('lead-technician');
        $tech = $this->userWithRole('technician');
        $sales = $this->userWithRole('sales');
        $request = $this->makeRequest($sales);

        $this->actingAs($leadTech)->post(
            route('sales.technical-requests.assign', $request),
            ['technician_id' => $tech->id]
        );

        $survey = $request->fresh()->survey;
        $this->assertSame($request->lead->customer_id, $survey->customer_id);
        $this->assertNotNull($survey->sales_user_id);
    }

    public function test_legacy_survey_without_request_still_works(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $this->actingAs($technician); // ProjectObserver butuh aktor untuk activity log

        // Data lama: survey biasa tanpa technical_request_id tetap bisa
        // dibuka, diedit, dan laporannya dilihat.
        $customer = Customer::create(['name' => 'PT Lama']);
        $workType = WorkType::create(['name' => 'Instalasi']);
        $project = Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Lama',
        ]);
        $survey = Survey::create([
            'project_id' => $project->id,
            'title' => 'Survey Lama',
            'report_file' => 'surveys/1/reports/lama.pdf',
            'status' => 'completed',
        ]);
        Storage::disk('private')->put('surveys/1/reports/lama.pdf', 'pdf');

        $this->assertNull($survey->technical_request_id);

        $this->actingAs($technician)->get(route('teknisi.surveys.show', $survey))->assertOk();
        $this->actingAs($technician)->get(route('teknisi.surveys.report.view', $survey))->assertOk();
        $this->actingAs($technician)->get(route('teknisi.surveys.report.download', $survey))->assertOk();
        $this->actingAs($technician)->get(route('teknisi.surveys.edit', $survey))->assertOk();
    }
}
