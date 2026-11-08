<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Proposal;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesPrivacyDrilldownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function userWithRole(string $role, ?string $name = null): User
    {
        return User::factory()->create($name ? ['name' => $name] : [])->assignRole($role);
    }

    private function leadFor(User $sales, string $customerName = 'PT Privasi'): Lead
    {
        $customer = Customer::create([
            'name' => $customerName,
            'pt_group' => 'NTI',
            'contact_person' => 'Budi',
        ]);

        return Lead::create([
            'customer_id' => $customer->id,
            'assigned_to' => $sales->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'hot',
        ]);
    }

    public function test_sales_dashboard_hides_other_sales_names_and_leaderboard(): void
    {
        $sales = $this->userWithRole('sales', 'Irfan Sales');
        $other = $this->userWithRole('sales', 'Sales Rahasia');

        $this->leadFor($other, 'PT Rahasia');

        $response = $this->actingAs($sales)->get(route('sales.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Sales Rahasia');
        $response->assertDontSee(__('Peringkat & Target Bulanan Sales'));
        $response->assertDontSee('PT Rahasia');
    }

    public function test_sales_cannot_open_management_drilldown(): void
    {
        $sales = $this->userWithRole('sales');
        $other = $this->userWithRole('sales');

        $this->actingAs($sales)
            ->get(route('manage-sales.sales-rep', $other))
            ->assertForbidden();
    }

    public function test_management_can_open_sales_drilldown_and_see_customers(): void
    {
        $management = $this->userWithRole('management');
        $sales = $this->userWithRole('sales', 'Irfan Sales');
        $this->leadFor($sales, 'PT Milik Irfan');

        $response = $this->actingAs($management)
            ->get(route('manage-sales.sales-rep', $sales));

        $response->assertOk();
        $response->assertSee('Irfan Sales');
        $response->assertSee('PT Milik Irfan');
        $response->assertSee(__('Customer Hub'));
    }

    public function test_proposal_creation_is_blocked_without_follow_up_note(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->leadFor($sales);

        $response = $this->actingAs($sales)->post(route('sales.proposals.store'), [
            'lead_id' => $lead->id,
            'proposal_date' => now()->toDateString(),
            'items' => [[
                'description' => 'Kamera CCTV',
                'quantity' => 1,
                'unit' => 'unit',
                'unit_price' => 1000000,
                'discount' => 0,
            ]],
        ]);

        $response->assertSessionHasErrors('lead_id');
        $this->assertSame(0, Proposal::where('lead_id', $lead->id)->count());
    }
}
