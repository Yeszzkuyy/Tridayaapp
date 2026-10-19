<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\TechnicalRequest;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Form Request Engineering/Price: Purpose (pengganti Problem Description),
 * Location (alamat / link Maps), dan Target Time. Kolom lama tidak dipakai
 * lagi di form, tapi data lama tetap tampil di halaman detail.
 */
class TechnicalRequestFormTest extends TestCase
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

    private function makeLead(User $sales, string $address = 'Jl. Customer 1'): Lead
    {
        return Lead::create([
            'customer_id' => Customer::create(['name' => 'PT Form '.uniqid(), 'address' => $address])->id,
            'assigned_to' => $sales->id,
            'status' => 'warm',
        ]);
    }

    private function makeRequest(User $sales, Lead $lead, array $overrides = []): TechnicalRequest
    {
        return TechnicalRequest::create(array_merge([
            'lead_id' => $lead->id,
            'requested_by' => $sales->id,
            'request_type' => 'survey',
            'title' => 'Survey Lokasi',
            'priority' => 'normal',
            'status' => 'accepted',
        ], $overrides));
    }

    public function test_form_uses_purpose_location_and_time_and_drops_legacy_fields(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales);

        $html = $this->actingAs($sales)
            ->get(route('sales.technical-requests.create', ['lead_id' => $lead->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('name="purpose"', $html);
        $this->assertStringContainsString('name="location"', $html);
        $this->assertStringContainsString('name="target_time"', $html);
        $this->assertStringContainsString('id="tr-location"', $html);
        $this->assertStringContainsString('data-location-autocomplete', $html);

        $this->assertStringNotContainsString('name="requirement"', $html);
        $this->assertStringNotContainsString('name="problem_description"', $html);
        $this->assertStringNotContainsString('name="scope"', $html);
    }

    public function test_store_persists_purpose_location_and_time(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales);

        $this->actingAs($sales)->post(route('sales.technical-requests.store'), [
            'lead_id' => $lead->id,
            'request_type' => 'survey',
            'title' => 'Survey Lokasi',
            'priority' => 'normal',
            'purpose' => 'Customer minta survey CCTV',
            'location' => 'Jl. Merdeka No. 1',
            'target_date' => now()->addDay()->toDateString(),
            'target_time' => '14:30',
        ])->assertRedirect();

        $request = TechnicalRequest::where('title', 'Survey Lokasi')->firstOrFail();
        $this->assertSame('Customer minta survey CCTV', $request->purpose);
        $this->assertSame('Jl. Merdeka No. 1', $request->location);
        $this->assertSame('14:30', substr((string) $request->target_time, 0, 5));
    }

    public function test_survey_inherits_purpose_and_request_location(): void
    {
        Storage::fake('private');
        $sales = $this->userWithRole('sales');
        $leadTech = $this->userWithRole('lead-technician');
        $tech = $this->userWithRole('technician');
        $lead = $this->makeLead($sales);
        $request = $this->makeRequest($sales, $lead, [
            'purpose' => 'Butuh hitung titik CCTV',
            'location' => 'Gudang Blok C',
        ]);

        $this->actingAs($leadTech)->post(
            route('sales.technical-requests.assign', $request),
            ['technician_id' => $tech->id]
        )->assertRedirect();

        $survey = $request->fresh()->survey;
        $this->assertNotNull($survey);
        $this->assertSame('Butuh hitung titik CCTV', $survey->sales_request);
        $this->assertSame('Gudang Blok C', $survey->location);
    }

    public function test_survey_falls_back_to_customer_address_when_location_empty(): void
    {
        Storage::fake('private');
        $sales = $this->userWithRole('sales');
        $leadTech = $this->userWithRole('lead-technician');
        $tech = $this->userWithRole('technician');
        $lead = $this->makeLead($sales, 'Jl. Customer 1');
        $request = $this->makeRequest($sales, $lead, ['purpose' => 'Konsultasi jaringan']);

        $this->actingAs($leadTech)->post(
            route('sales.technical-requests.assign', $request),
            ['technician_id' => $tech->id]
        )->assertRedirect();

        $survey = $request->fresh()->survey;
        $this->assertSame('Konsultasi jaringan', $survey->sales_request);
        $this->assertSame('Jl. Customer 1', $survey->location);
    }

    public function test_legacy_request_fields_still_visible_on_detail(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales);
        $request = $this->makeRequest($sales, $lead, [
            'status' => 'waiting_lead',
            'requirement' => 'Butuh internet stabil',
            'problem_description' => 'Sering putus',
            'scope' => 'Lantai 1-2',
        ]);

        $html = $this->actingAs($sales)
            ->get(route('sales.technical-requests.show', $request))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Requirement', $html);
        $this->assertStringContainsString('Butuh internet stabil', $html);
        $this->assertStringContainsString('Sering putus', $html);
        $this->assertStringContainsString('Lantai 1-2', $html);
    }

    public function test_detail_shows_purpose_location_and_target(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales);
        $request = $this->makeRequest($sales, $lead, [
            'status' => 'waiting_lead',
            'purpose' => 'Pasang CCTV 8 titik',
            'location' => 'Gudang Blok C',
            'target_date' => '2026-11-02',
            'target_time' => '09:15',
        ]);

        $html = $this->actingAs($sales)
            ->get(route('sales.technical-requests.show', $request))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Pasang CCTV 8 titik', $html);
        $this->assertStringContainsString('Gudang Blok C', $html);
        $this->assertStringContainsString('09:15', $html);
    }
}
