<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_lead_can_view_monitoring_summary(): void
    {
        $lead = $this->userWithRole('lead-marketing');
        $junior = User::factory()->create();
        $junior->assignRole('marketing');
        $sales = User::factory()->create(['name' => 'Adi Sales']);
        $sales->assignRole('sales');

        $customer = Customer::create(['name' => 'PT Rekap']);
        Lead::create(['customer_id' => $customer->id, 'pt_group' => 'NTI', 'segment' => 'vendor', 'status' => 'won', 'assigned_to' => $junior->id]);
        Lead::create(['customer_id' => $customer->id, 'pt_group' => 'NTI', 'segment' => 'vendor', 'status' => 'cool', 'assigned_to' => $junior->id]);

        $this->actingAs($lead)->get(route('leads.monitoring'))
            ->assertOk()
            ->assertSee($junior->name)
            ->assertSee('Marketing')
            ->assertSee('Sales')
            ->assertSee('Adi Sales')
            ->assertSee(route('leads.activities', ['user' => $junior->id]));
    }

    public function test_marketing_cannot_access_monitoring(): void
    {
        $this->actingAs($this->userWithRole('marketing'))
            ->get(route('leads.monitoring'))
            ->assertForbidden();
    }

    public function test_activities_can_be_filtered_by_user(): void
    {
        $junior = $this->userWithRole('marketing');
        $other = User::factory()->create();
        $other->assignRole('marketing');

        $customer = Customer::create(['name' => 'PT Filter']);
        $lead = Lead::create(['customer_id' => $customer->id, 'pt_group' => 'NTI', 'segment' => 'vendor']);

        LeadActivity::create(['lead_id' => $lead->id, 'user_id' => $junior->id, 'action' => 'created']);
        LeadActivity::create(['lead_id' => $lead->id, 'user_id' => $other->id, 'action' => 'created']);

        $response = $this->actingAs($junior)
            ->get(route('leads.activities', ['user' => $other->id]))
            ->assertOk();

        // Hanya aktivitas milik "other" yang tampil di daftar
        $this->assertSame(1, $response->viewData('activities')->count());
        $this->assertSame($other->id, $response->viewData('activities')->first()->user_id);
    }
}
