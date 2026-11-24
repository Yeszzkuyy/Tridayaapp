<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalIndexFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function makeProposal(string $status, float $amount, array $attributes = []): Proposal
    {
        $customer = Customer::create(['name' => 'PT Index ' . uniqid()]);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'hot',
        ]);

        $proposal = Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => $attributes['proposal_number'] ?? 'Q/2026/11/' . uniqid(),
            'created_by' => $attributes['created_by'] ?? $lead->created_by ?? User::factory()->create()->id,
            'proposal_date' => now()->toDateString(),
            'customer' => $customer->name,
            'status' => $status,
            'valid_until' => $attributes['valid_until'] ?? null,
            'grand_total' => $amount,
        ]);

        ProposalItem::create([
            'proposal_id' => $proposal->id,
            'description' => 'IP Camera',
            'quantity' => 1,
            'unit' => 'unit',
            'unit_price' => $amount,
            'discount' => 0,
            'subtotal' => $amount,
        ]);

        return $proposal;
    }

    private function management(): User
    {
        $user = User::factory()->create();
        $user->assignRole('management');

        return $user;
    }

    public function test_status_filter_only_shows_matching_quotations(): void
    {
        $management = $this->management();
        $draft = $this->makeProposal('draft', 1000000, ['created_by' => $management->id]);
        $won = $this->makeProposal('accepted', 2000000, ['created_by' => $management->id]);

        $this->actingAs($management)
            ->get(route('sales.proposals.index', ['status' => 'draft']))
            ->assertOk()
            ->assertSee($draft->proposal_number)
            ->assertDontSee($won->proposal_number);
    }

    public function test_expiring_filter_uses_the_same_window_as_the_kpi(): void
    {
        $management = $this->management();
        $expiring = $this->makeProposal('ready', 1000000, [
            'created_by' => $management->id,
            'valid_until' => now()->addDays(3)->toDateString(),
        ]);
        $farAway = $this->makeProposal('sent', 2000000, [
            'created_by' => $management->id,
            'valid_until' => now()->addDays(30)->toDateString(),
        ]);

        $this->actingAs($management)
            ->get(route('sales.proposals.index', ['filter' => 'expiring']))
            ->assertOk()
            ->assertSee($expiring->proposal_number)
            ->assertDontSee($farAway->proposal_number);
    }

    public function test_won_this_month_filter_only_shows_accepted_this_month(): void
    {
        $management = $this->management();
        $won = $this->makeProposal('accepted', 1000000, ['created_by' => $management->id]);
        $draft = $this->makeProposal('draft', 2000000, ['created_by' => $management->id]);

        $this->actingAs($management)
            ->get(route('sales.proposals.index', ['filter' => 'won_month']))
            ->assertOk()
            ->assertSee($won->proposal_number)
            ->assertDontSee($draft->proposal_number);
    }

    public function test_amount_sort_uses_numeric_values(): void
    {
        $management = $this->management();
        $small = $this->makeProposal('draft', 2000000, [
            'created_by' => $management->id,
            'proposal_number' => 'Q/SORT/SMALL',
        ]);
        $large = $this->makeProposal('draft', 33000000, [
            'created_by' => $management->id,
            'proposal_number' => 'Q/SORT/LARGE',
        ]);

        $this->actingAs($management)
            ->get(route('sales.proposals.index', ['sort' => 'amount', 'direction' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder([$large->proposal_number, $small->proposal_number]);
    }

    public function test_per_page_is_limited_to_supported_values(): void
    {
        $management = $this->management();
        $this->makeProposal('draft', 1000000, ['created_by' => $management->id]);

        $this->actingAs($management)
            ->get(route('sales.proposals.index', ['per_page' => 999]))
            ->assertOk();

        $this->actingAs($management)
            ->get(route('sales.proposals.index', ['per_page' => 25]))
            ->assertOk()
            ->assertSee('Rows per page');
    }
}
