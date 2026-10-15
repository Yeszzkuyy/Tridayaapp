<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Poc;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesPocTest extends TestCase
{
    use RefreshDatabase;

    private function salesUser(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('sales');

        return $user;
    }

    private function payload(Customer $customer, array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $customer->id,
            'type' => 'demo',
            'scheduled_date' => today()->addDays(3)->toDateString(),
            'location' => 'Kantor customer',
        ], $overrides);
    }

    public function test_sales_can_schedule_poc(): void
    {
        $sales = $this->salesUser();
        $customer = Customer::create(['name' => 'PT Demo']);

        $this->actingAs($sales)
            ->post(route('sales.pocs.store'), $this->payload($customer))
            ->assertRedirect(route('sales.pocs.index'));

        $this->assertDatabaseHas('pocs', [
            'customer_id' => $customer->id,
            'type' => 'demo',
            'status' => 'scheduled',
            'created_by' => $sales->id,
        ]);
    }

    public function test_auto_links_single_active_own_lead_and_logs_activity(): void
    {
        $sales = $this->salesUser();
        $customer = Customer::create(['name' => 'PT Auto Lead']);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'hot',
            'incoming_date' => now()->toDateString(),
            'assigned_to' => $sales->id,
        ]);

        $this->actingAs($sales)
            ->post(route('sales.pocs.store'), $this->payload($customer))
            ->assertRedirect(route('sales.pocs.index'));

        $this->assertSame($lead->id, Poc::first()->lead_id);
        $this->assertTrue(
            LeadActivity::where('lead_id', $lead->id)->where('action', 'poc_created')->exists()
        );
    }

    public function test_forbids_other_sales_lead_and_rejects_type(): void
    {
        $sales = $this->salesUser();
        $other = User::factory()->create();
        $other->assignRole('sales');
        $customer = Customer::create(['name' => 'PT Asing']);
        $foreignLead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'incoming_date' => now()->toDateString(),
            'assigned_to' => $other->id,
        ]);

        $this->actingAs($sales)
            ->post(route('sales.pocs.store'), $this->payload($customer, ['lead_id' => $foreignLead->id]))
            ->assertForbidden();

        $this->actingAs($sales)
            ->post(route('sales.pocs.store'), $this->payload($customer, ['type' => 'webinar']))
            ->assertSessionHasErrors('type');
    }

    public function test_sales_only_sees_own_pocs(): void
    {
        $sales = $this->salesUser();
        $other = User::factory()->create();
        $other->assignRole('sales');
        $mine = Customer::create(['name' => 'PT Milikku']);
        $theirs = Customer::create(['name' => 'PT Orang']);

        Poc::create(array_merge($this->payload($mine), ['created_by' => $sales->id]));
        Poc::create(array_merge($this->payload($theirs), ['created_by' => $other->id]));

        $this->actingAs($sales)
            ->get(route('sales.pocs.index'))
            ->assertOk()
            ->assertSee('PT Milikku')
            ->assertDontSee('PT Orang');
    }
}
