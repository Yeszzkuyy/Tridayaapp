<?php

namespace Tests\Feature;

use App\Models\SalesTarget;
use App\Models\User;
use App\Services\SalesKpiService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesKpiTargetTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_sales_dashboard_renders_with_personal_kpi_and_leaderboard(): void
    {
        $salesUser = $this->userWithRole('sales');

        // Create a custom target for this month
        SalesTarget::create([
            'user_id' => $salesUser->id,
            'period_month' => now()->month,
            'period_year' => now()->year,
            'target_revenue' => 100000000,
            'target_deals' => 5,
        ]);

        $response = $this->actingAs($salesUser)->get(route('sales.dashboard'));

        $response->assertOk();
        $response->assertViewHas('myKpi');
        $response->assertViewHas('salesLeaderboard');
        $response->assertSee(__('KPI Personal Saya'));
        $response->assertSee(__('Peringkat & Target Bulanan Sales'));
    }

    public function test_management_can_access_sales_kpi_page(): void
    {
        $mgmtUser = $this->userWithRole('management');

        $response = $this->actingAs($mgmtUser)->get(route('manage-sales.kpi'));

        $response->assertOk();
        $response->assertViewHas('teamSummary');
        $response->assertViewHas('leaderboard');
        $response->assertSee(__('Sales KPI & Target Performance'));
    }

    public function test_regular_sales_user_cannot_access_management_kpi(): void
    {
        $salesUser = $this->userWithRole('sales');

        $response = $this->actingAs($salesUser)->get(route('manage-sales.kpi'));

        $response->assertForbidden();
    }

    public function test_management_can_set_and_update_sales_target(): void
    {
        $mgmtUser = $this->userWithRole('management');
        $salesUser = $this->userWithRole('sales');

        $payload = [
            'user_id' => $salesUser->id,
            'period_month' => 11,
            'period_year' => 2026,
            'target_revenue' => 75000000,
            'target_deals' => 4,
            'notes' => 'Fokus closing corporate clients',
        ];

        $response = $this->actingAs($mgmtUser)->post(route('manage-sales.targets.update'), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('sales_targets', [
            'user_id' => $salesUser->id,
            'period_month' => 11,
            'period_year' => 2026,
            'target_revenue' => 75000000,
            'target_deals' => 4,
            'notes' => 'Fokus closing corporate clients',
        ]);
    }

    public function test_sales_kpi_service_computes_accuracy_and_celebration(): void
    {
        $salesUser = $this->userWithRole('sales');

        SalesTarget::create([
            'user_id' => $salesUser->id,
            'period_month' => now()->month,
            'period_year' => now()->year,
            'target_revenue' => 50000000,
            'target_deals' => 2,
        ]);

        $service = app(SalesKpiService::class);
        $kpi = $service->getUserKpi($salesUser, now()->month, now()->year);

        $this->assertEquals(50000000, $kpi['target_revenue']);
        $this->assertEquals(2, $kpi['target_deals']);
        $this->assertArrayHasKey('celebration', $kpi);
        $this->assertArrayHasKey('title', $kpi['celebration']);
        $this->assertArrayHasKey('badge', $kpi['celebration']);
    }
}
