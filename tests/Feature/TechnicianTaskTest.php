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

class TechnicianTaskTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeLead(User $sales, array $customerOverrides = []): Lead
    {
        $customer = Customer::create(array_merge([
            'name' => 'PT Tugas '.uniqid(),
            'company' => 'Tridaya Group',
            'address' => 'Jl. Customer No. 10',
            'contact_person' => 'Pak Andi',
            'phone' => '081298765432',
        ], $customerOverrides));

        return Lead::create([
            'customer_id' => $customer->id,
            'assigned_to' => $sales->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'hot',
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
            'status' => 'assigned',
        ], $overrides));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_assigned_technician_only_sees_own_tasks(): void
    {
        $sales = $this->userWithRole('sales');
        $techA = $this->userWithRole('technician');
        $techB = $this->userWithRole('technician');
        $lead = $this->makeLead($sales);

        $ownTask = $this->makeRequest($sales, $lead, [
            'title' => 'Survey Milik A',
            'assigned_technician_id' => $techA->id,
            'location' => 'Gudang Blok A',
        ]);
        $otherTask = $this->makeRequest($sales, $lead, [
            'title' => 'Survey Milik B',
            'assigned_technician_id' => $techB->id,
        ]);
        $estimate = $this->makeRequest($sales, $lead, [
            'title' => 'Estimasi Harga A',
            'request_type' => 'estimate',
            'assigned_technician_id' => $techA->id,
        ]);

        $html = $this->actingAs($techA)
            ->get(route('teknisi.tasks.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($ownTask->title, $html);
        $this->assertStringContainsString($lead->customer->name, $html);
        $this->assertStringContainsString('Gudang Blok A', $html);
        $this->assertStringContainsString(TechnicalRequest::statusLabel('assigned'), $html);
        $this->assertStringNotContainsString($otherTask->title, $html);
        $this->assertStringNotContainsString($estimate->title, $html);
    }

    public function test_other_technician_cannot_open_task_or_attachment(): void
    {
        Storage::fake('private');

        $sales = $this->userWithRole('sales');
        $techA = $this->userWithRole('technician');
        $techB = $this->userWithRole('technician');
        $lead = $this->makeLead($sales);
        $request = $this->makeRequest($sales, $lead, [
            'assigned_technician_id' => $techA->id,
            'attachment_path' => 'tech-requests/1/berkas.pdf',
        ]);

        $this->actingAs($techB)
            ->get(route('teknisi.tasks.show', $request))
            ->assertForbidden();

        $this->actingAs($techB)
            ->get(route('teknisi.technical-requests.show', $request))
            ->assertForbidden();

        $this->actingAs($techB)
            ->get(route('teknisi.technical-requests.attachment', $request))
            ->assertForbidden();
    }

    public function test_reassignment_revokes_old_technician_access(): void
    {
        $sales = $this->userWithRole('sales');
        $leadTech = $this->userWithRole('lead-technician');
        $techA = $this->userWithRole('technician');
        $techB = $this->userWithRole('technician');
        $lead = $this->makeLead($sales);
        $request = $this->makeRequest($sales, $lead, [
            'status' => 'accepted',
            'assigned_technician_id' => null,
        ]);

        $this->actingAs($leadTech)->post(route('sales.technical-requests.assign', $request), [
            'technician_id' => $techA->id,
        ])->assertRedirect();

        $this->actingAs($techA)->get(route('teknisi.tasks.show', $request))->assertOk();

        $this->actingAs($leadTech)->post(route('sales.technical-requests.assign', $request), [
            'technician_id' => $techB->id,
        ])->assertRedirect();

        $this->actingAs($techA)->get(route('teknisi.tasks.show', $request))->assertForbidden();
        $this->actingAs($techB)->get(route('teknisi.tasks.show', $request))->assertOk();

        $html = $this->actingAs($techB)->get(route('teknisi.tasks.index'))->assertOk()->getContent();
        $this->assertStringContainsString($request->title, $html);
    }

    public function test_task_detail_shows_full_customer_request_information_read_only(): void
    {
        $sales = $this->userWithRole('sales');
        $tech = $this->userWithRole('technician');
        $lead = $this->makeLead($sales, [
            'address' => 'Jl. Fallback Alamat 22',
            'contact_person' => 'Ibu Sari',
            'phone' => '081200001111',
        ]);
        $request = $this->makeRequest($sales, $lead, [
            'assigned_technician_id' => $tech->id,
            'request_type' => 'installation',
            'title' => 'Instalasi CCTV Gudang',
            'purpose' => 'Pasang 8 titik CCTV di gudang',
            'location' => null,
            'pic_contact_name' => null,
            'pic_contact_phone' => null,
            'notes' => 'Gerbang belakang agak sempit',
        ]);

        $html = $this->actingAs($tech)
            ->get(route('teknisi.tasks.show', $request))
            ->assertOk()
            ->getContent();

        // Informasi request Sales.
        $this->assertStringContainsString('Instalasi CCTV Gudang', $html);
        $this->assertStringContainsString(TechnicalRequest::typeLabel('installation'), $html);
        $this->assertStringContainsString(TechnicalRequest::statusLabel('assigned'), $html);
        $this->assertStringContainsString('Pasang 8 titik CCTV di gudang', $html);
        $this->assertStringContainsString('Gerbang belakang agak sempit', $html);

        // Customer + fallback alamat/PIC dari data customer.
        $this->assertStringContainsString($lead->customer->name, $html);
        $this->assertStringContainsString('Jl. Fallback Alamat 22', $html);
        $this->assertStringContainsString('Ibu Sari', $html);
        $this->assertStringContainsString('081200001111', $html);

        // Read-only: tidak ada aksi kerja/assign/review di halaman ini.
        $this->assertStringContainsString(__('Read-only task view'), $html);
        $this->assertStringNotContainsString(__('Request actions'), $html);
        $this->assertStringNotContainsString(route('sales.technical-requests.complete', $request), $html);
        $this->assertStringNotContainsString(route('sales.technical-requests.assign', $request), $html);
        $this->assertStringNotContainsString(route('sales.technical-requests.review', $request), $html);
    }

    public function test_estimate_task_is_not_accessible_from_my_tasks(): void
    {
        $sales = $this->userWithRole('sales');
        $tech = $this->userWithRole('technician');
        $lead = $this->makeLead($sales);
        $estimate = $this->makeRequest($sales, $lead, [
            'request_type' => 'estimate',
            'title' => 'Estimasi Harga',
            'assigned_technician_id' => $tech->id,
        ]);

        $this->actingAs($tech)
            ->get(route('teknisi.tasks.show', $estimate))
            ->assertNotFound();
    }

    public function test_plain_technician_cannot_review_assign_or_complete_other_requests(): void
    {
        $sales = $this->userWithRole('sales');
        $techA = $this->userWithRole('technician');
        $techB = $this->userWithRole('technician');
        $lead = $this->makeLead($sales);

        $waiting = $this->makeRequest($sales, $lead, [
            'title' => 'Menunggu Review',
            'status' => 'waiting_lead',
            'assigned_technician_id' => null,
        ]);
        $assigned = $this->makeRequest($sales, $lead, [
            'title' => 'Milik Teknisi Lain',
            'status' => 'assigned',
            'assigned_technician_id' => $techB->id,
            'technical_result' => 'Sudah selesai',
        ]);

        $this->actingAs($techA)
            ->post(route('sales.technical-requests.review', $waiting), ['decision' => 'accepted'])
            ->assertForbidden();
        $this->assertSame('waiting_lead', $waiting->refresh()->status);

        $this->actingAs($techA)
            ->post(route('sales.technical-requests.assign', $assigned), ['technician_id' => $techA->id])
            ->assertForbidden();
        $this->assertSame($techB->id, (int) $assigned->refresh()->assigned_technician_id);

        $this->actingAs($techA)
            ->post(route('sales.technical-requests.complete', $assigned))
            ->assertForbidden();
        $this->assertSame('assigned', $assigned->refresh()->status);
    }

    public function test_plain_technician_inbox_is_scoped_to_own_requests(): void
    {
        $sales = $this->userWithRole('sales');
        $leadTech = $this->userWithRole('lead-technician');
        $techA = $this->userWithRole('technician');
        $techB = $this->userWithRole('technician');
        $lead = $this->makeLead($sales);

        $own = $this->makeRequest($sales, $lead, [
            'title' => 'Request Untuk A',
            'assigned_technician_id' => $techA->id,
        ]);
        $other = $this->makeRequest($sales, $lead, [
            'title' => 'Request Untuk B',
            'assigned_technician_id' => $techB->id,
        ]);
        $unassigned = $this->makeRequest($sales, $lead, [
            'title' => 'Request Belum Di-assign',
            'status' => 'waiting_lead',
            'assigned_technician_id' => null,
        ]);

        $html = $this->actingAs($techA)
            ->get(route('teknisi.requests.index', ['tab' => 'engineer']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString($own->title, $html);
        $this->assertStringNotContainsString($other->title, $html);
        $this->assertStringNotContainsString($unassigned->title, $html);

        // Koordinator teknis tetap melihat inbox penuh (regression).
        $leadHtml = $this->actingAs($leadTech)
            ->get(route('teknisi.requests.index', ['tab' => 'engineer']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString($own->title, $leadHtml);
        $this->assertStringContainsString($other->title, $leadHtml);
        $this->assertStringContainsString($unassigned->title, $leadHtml);
    }

    public function test_sales_cannot_open_technician_tasks(): void
    {
        $sales = $this->userWithRole('sales');

        $this->actingAs($sales)
            ->get(route('teknisi.tasks.index'))
            ->assertForbidden();
    }
}
