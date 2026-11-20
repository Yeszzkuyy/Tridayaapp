<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardLatestCustomersTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_dashboard_shows_newest_customers_with_active_project_count(): void
    {
        $admin = $this->userWithRole('admin');

        $openStatus = ProjectStatus::create(['name' => 'Open', 'color' => 'blue', 'sort_order' => 1, 'is_default' => true]);
        ProjectStatus::create(['name' => 'On Progress', 'color' => 'yellow', 'sort_order' => 2]);

        $customerWithProject = Customer::create(['name' => 'PT Sinar Jaya', 'pt_group' => 'NTI', 'status' => 'deal']);
        $customerWithoutProject = Customer::create(['name' => 'PT Maju Bersama', 'pt_group' => 'MGK', 'status' => 'deal']);

        app(\App\Services\Project\ProjectService::class)->create([
            'customer_id' => $customerWithProject->id,
            'work_type_id' => WorkType::create(['name' => 'Installation'])->id,
            'project_name' => 'Instalasi Gudang Baru',
            'project_status_id' => $openStatus->id,
        ]);

        // Prospek (status lead) tidak boleh tampil di kartu ini.
        Customer::create(['name' => 'PT Belum Deal', 'pt_group' => 'TPS', 'status' => 'lead']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Newest Customers')
            ->assertSee('PT Sinar Jaya')
            ->assertSee('PT Maju Bersama')
            ->assertSee('1 active project')
            ->assertSee('No active project')
            ->assertDontSee('PT Belum Deal');
    }

    public function test_sales_only_sees_own_customers_in_the_card(): void
    {
        $sales = $this->userWithRole('sales');
        $otherSales = User::factory()->create();
        $otherSales->assignRole('sales');

        Customer::create([
            'name' => 'PT Customer Sales Ini',
            'pt_group' => 'NTI',
            'status' => 'deal',
            'created_by' => $sales->id,
        ]);

        Customer::create([
            'name' => 'PT Customer Sales Lain',
            'pt_group' => 'NTI',
            'status' => 'deal',
            'created_by' => $otherSales->id,
        ]);

        $this->actingAs($sales)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Newest Customers')
            ->assertSee('PT Customer Sales Ini')
            ->assertDontSee('PT Customer Sales Lain');
    }

    public function test_dashboard_renders_empty_state_when_there_is_no_customer(): void
    {
        $marketing = $this->userWithRole('marketing');

        $this->actingAs($marketing)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Newest Customers')
            ->assertSee('No customers yet');
    }
}
