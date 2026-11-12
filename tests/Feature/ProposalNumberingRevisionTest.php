<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Proposal;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalNumberingRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function salesUser(): User
    {
        return User::factory()->create()->assignRole('sales');
    }

    private function managementUser(): User
    {
        return User::factory()->create()->assignRole('management');
    }

    private function makeLead(User $sales): Lead
    {
        $customer = Customer::create(['name' => 'PT Number', 'pt_group' => 'MGK']);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'MGK',
            'segment' => 'vendor',
            'status' => 'hot',
            'assigned_to' => $sales->id,
        ]);

        \App\Models\FollowUp::create([
            'customer_id' => $customer->id,
            'lead_id' => $lead->id,
            'description' => 'Follow up awal.',
            'type' => 'note',
            'created_by' => $sales->id,
        ]);

        return $lead;
    }

    private function storePayload(Lead $lead): array
    {
        return [
            'lead_id' => $lead->id,
            'proposal_date' => now()->toDateString(),
            'items' => [[
                'description' => 'CCTV 8 channel',
                'quantity' => 1,
                'unit' => 'unit',
                'unit_price' => 5000000,
                'discount' => 0,
            ]],
        ];
    }

    public function test_proposal_number_follows_pt_sequence_initials_year_format(): void
    {
        $sales = $this->salesUser();
        $sales->update(['name' => 'Adi Santosa', 'initials' => 'AS']);
        $lead = $this->makeLead($sales);

        $this->actingAs($this->managementUser())->post(route('sales.proposals.store'), $this->storePayload($lead))
            ->assertRedirect();

        $proposal = Proposal::firstOrFail();
        $this->assertSame('MGK/001/AS/'.now()->year, $proposal->proposal_number);
        $this->assertSame('MGK', $proposal->letterhead_pt);
    }

    public function test_sequence_increments_per_pt(): void
    {
        $sales = $this->salesUser();
        $sales->update(['initials' => 'AS']);
        $management = $this->managementUser();

        $lead1 = $this->makeLead($sales);
        $this->actingAs($management)->post(route('sales.proposals.store'), $this->storePayload($lead1));

        $lead2 = $this->makeLead($sales);
        $this->actingAs($management)->post(route('sales.proposals.store'), $this->storePayload($lead2));

        $numbers = Proposal::orderBy('id')->pluck('proposal_number')->all();
        $this->assertStringContainsString('/001/', $numbers[0]);
        $this->assertStringContainsString('/002/', $numbers[1]);
    }

    public function test_revision_creates_version_history(): void
    {
        $sales = $this->salesUser();
        $sales->update(['initials' => 'AS']);
        $lead = $this->makeLead($sales);

        $this->actingAs($this->managementUser())->post(route('sales.proposals.store'), $this->storePayload($lead))
            ->assertRedirect();

        $proposal = Proposal::firstOrFail();
        $this->assertSame(1, $proposal->versions()->count());

        // Edit item → versi baru dengan total_before terisi.
        $this->actingAs($sales)->put(route('sales.proposals.update', $proposal), [
            'proposal_date' => now()->toDateString(),
            'items' => [[
                'description' => 'CCTV 8 channel',
                'quantity' => 2,
                'unit' => 'unit',
                'unit_price' => 5000000,
                'discount' => 0,
            ]],
        ])->assertRedirect();

        $this->assertSame(2, $proposal->fresh()->versions()->count());
        $version = $proposal->fresh()->versions->last();
        $this->assertNotNull($version->total_before);
        $this->assertSame(10000000.0, (float) $version->total_after);
    }

    public function test_second_quotation_for_same_lead_is_blocked(): void
    {
        $sales = $this->salesUser();
        $sales->update(['initials' => 'AS']);
        $lead = $this->makeLead($sales);
        $management = $this->managementUser();

        $this->actingAs($management)->post(route('sales.proposals.store'), $this->storePayload($lead))
            ->assertRedirect();

        // Attempt kedua harus ditolak.
        $this->actingAs($management)->post(route('sales.proposals.store'), $this->storePayload($lead))
            ->assertSessionHasErrors('lead_id');

        $this->assertSame(1, Proposal::count());
    }

    public function test_sales_cannot_create_quotation_directly(): void
    {
        $sales = $this->salesUser();
        $lead = $this->makeLead($sales);

        $this->actingAs($sales)
            ->get(route('sales.proposals.create', ['lead_id' => $lead->id]))
            ->assertForbidden();

        $this->actingAs($sales)
            ->post(route('sales.proposals.store'), $this->storePayload($lead))
            ->assertForbidden();

        $this->assertSame(0, Proposal::count());
    }
}
