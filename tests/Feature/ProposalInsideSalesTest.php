<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\Proposal;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Paket 1 — Tahap 1: buktikan Inside Sales boleh menyusun quotation
 * untuk lead yang ditangani Sales TANPA mengambil alih kepemilikan lead.
 */
class ProposalInsideSalesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeLeadOwnedBy(User $sales): Lead
    {
        $customer = Customer::create(['name' => 'PT Inside '.uniqid()]);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'hot',
        ]);
        $lead->update(['assigned_to' => $sales->id]);

        return $lead;
    }

    /** Inside Sales hanya boleh lihat lead yang punya task untuknya. */
    private function giveTaskTo(Lead $lead, User $inside): void
    {
        LeadTask::create([
            'lead_id' => $lead->id,
            'title' => 'Buatkan draft quotation',
            'description' => 'Follow-up kebutuhan customer.',
            'assigned_to' => $inside->id,
            'due_date' => now()->addDays(3)->toDateString(),
            'priority' => 'high',
            'status' => 'todo',
        ]);
    }

    private function proposalPayload(Lead $lead): array
    {
        return [
            'lead_id' => $lead->id,
            'proposal_date' => now()->toDateString(),
            'items' => [[
                'description' => 'IP Camera 4MP',
                'quantity' => 2,
                'unit' => 'unit',
                'unit_price' => 1500000,
                'discount' => 0,
            ]],
        ];
    }

    public function test_inside_sales_can_create_draft_quotation_for_sales_owned_lead(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $lead = $this->makeLeadOwnedBy($sales);
        $this->giveTaskTo($lead, $inside);

        $this->actingAs($inside)
            ->post(route('sales.proposals.store'), $this->proposalPayload($lead))
            ->assertRedirect();

        $proposal = Proposal::where('lead_id', $lead->id)->firstOrFail();
        $this->assertSame('draft', $proposal->status);
    }

    public function test_sales_owner_and_quotation_maker_recorded_separately(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $lead = $this->makeLeadOwnedBy($sales);
        $this->giveTaskTo($lead, $inside);

        $this->actingAs($inside)
            ->post(route('sales.proposals.store'), $this->proposalPayload($lead))
            ->assertRedirect();

        $proposal = Proposal::where('lead_id', $lead->id)->firstOrFail();

        // Quotation Maker = pembuat quotation (Inside Sales).
        $this->assertSame($inside->id, (int) $proposal->created_by);
        // Sales Owner = Sales yang ditugaskan pada lead — TIDAK berubah.
        $this->assertSame($sales->id, (int) $lead->fresh()->assigned_to);
    }

    public function test_editing_quotation_does_not_change_lead_owner(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $lead = $this->makeLeadOwnedBy($sales);
        $this->giveTaskTo($lead, $inside);

        $this->actingAs($inside)
            ->post(route('sales.proposals.store'), $this->proposalPayload($lead))
            ->assertRedirect();
        $proposal = Proposal::where('lead_id', $lead->id)->firstOrFail();

        $payload = $this->proposalPayload($lead);
        $payload['notes'] = 'Revisi harga untuk negosiasi.';
        $this->actingAs($inside)
            ->put(route('sales.proposals.update', $proposal), $payload)
            ->assertRedirect();

        $this->assertSame('Revisi harga untuk negosiasi.', $proposal->fresh()->notes);
        $this->assertSame($sales->id, (int) $lead->fresh()->assigned_to);
        $this->assertSame($inside->id, (int) $proposal->fresh()->created_by);
    }

    public function test_inside_sales_without_task_cannot_create_quotation(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $lead = $this->makeLeadOwnedBy($sales);
        // Tidak ada task untuk inside sales = tidak berwenang atas lead ini.

        $this->actingAs($inside)
            ->get(route('sales.proposals.create', ['lead_id' => $lead->id]))
            ->assertForbidden();

        $this->actingAs($inside)
            ->post(route('sales.proposals.store'), $this->proposalPayload($lead))
            ->assertForbidden();

        $this->assertSame(0, Proposal::count());
        $this->assertSame($sales->id, (int) $lead->fresh()->assigned_to);
    }

    public function test_unauthorized_roles_are_rejected(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLeadOwnedBy($sales);

        // Marketing tidak punya akses route proposal.
        $marketing = $this->userWithRole('marketing');
        $this->actingAs($marketing)
            ->post(route('sales.proposals.store'), $this->proposalPayload($lead))
            ->assertForbidden();

        // Prakerin juga tidak.
        $prakerin = $this->userWithRole('prakerin-admin');
        $this->actingAs($prakerin)
            ->post(route('sales.proposals.store'), $this->proposalPayload($lead))
            ->assertForbidden();

        $this->assertSame(0, Proposal::count());
    }
}
