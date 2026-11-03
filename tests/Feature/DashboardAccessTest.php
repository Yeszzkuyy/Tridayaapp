<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_marketing_can_access_general_dashboard(): void
    {
        $this->actingAs($this->userWithRole('marketing'))
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_technician_can_access_general_dashboard(): void
    {
        $this->actingAs($this->userWithRole('technician'))
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_sales_can_access_general_dashboard(): void
    {
        $this->actingAs($this->userWithRole('sales'))
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_ceo_can_access_general_dashboard(): void
    {
        $this->actingAs($this->userWithRole('ceo'))
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_management_can_access_general_dashboard(): void
    {
        $this->actingAs($this->userWithRole('management'))
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_super_admin_can_access_general_dashboard(): void
    {
        $this->actingAs($this->userWithRole('super-admin'))
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_general_dashboard_renders_town_hall_widgets_and_deduplicates_activities(): void
    {
        $user = $this->userWithRole('super-admin');
        $this->actingAs($user);

        $openStatus = \App\Models\ProjectStatus::create(['name' => 'Open', 'color' => 'blue', 'sort_order' => 1, 'is_default' => true]);
        $progressStatus = \App\Models\ProjectStatus::create(['name' => 'On Progress', 'color' => 'yellow', 'sort_order' => 2]);
        $doneStatus = \App\Models\ProjectStatus::create(['name' => 'Done', 'color' => 'green', 'sort_order' => 3]);

        $customer = \App\Models\Customer::create(['name' => 'PT Nusantara Digital']);
        $workType = \App\Models\WorkType::create(['name' => 'Installation']);

        // ProjectService::create() previously double-logged ProjectActivity with ProjectObserver
        $projectService = app(\App\Services\Project\ProjectService::class);
        $project = $projectService->create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'SD-WAN Rollout',
            'project_status_id' => $openStatus->id,
        ]);

        // Simulate legacy duplicate row ("Project Dibuat") for the same project
        \App\Models\ProjectActivity::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'activity_date' => now(),
            'title' => 'Project Dibuat',
            'description' => 'Project baru berhasil dibuat',
        ]);

        \App\Models\Product::create([
            'brand' => 'Synology',
            'name' => 'DiskStation',
            'model' => 'DS425+',
            'category' => 'barang',
            'status' => \App\Models\Product::STATUS_PUBLISHED,
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Total Customers')
            ->assertSee('Completed Projects')
            ->assertDontSee('Total / Completed Projects')
            ->assertSee('Active Projects')
            ->assertSee('Total Documents & KB')
            ->assertSee('My Agenda & Tasks Today')
            ->assertSee('Project Status Breakdown')
            ->assertSee('Company Board & Latest KB')
            ->assertSee('1 Recent Activities')
            ->assertSee('Created a project')
            ->assertDontSee('Project Dibuat')
            ->assertDontSee('Company Town Hall & Daily Productivity')
            ->assertSee('Synology DiskStation DS425+');
    }

    public function test_sales_and_inside_sales_with_leads_follow_ups_and_tasks_can_render_dashboard(): void
    {
        $user = $this->userWithRole('sales');
        $user->assignRole('inside-sales');
        $this->actingAs($user);

        $customer = \App\Models\Customer::create(['name' => 'PT Maju Bersama']);
        $lead = \App\Models\Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'status' => 'warm',
            'kebutuhan' => 'Pengadaan Firewall',
            'assigned_to' => $user->id,
        ]);

        \App\Models\FollowUp::create([
            'customer_id' => $customer->id,
            'lead_id' => $lead->id,
            'description' => 'Konfirmasi jadwal presentasi',
            'type' => 'call',
            'follow_up_date' => now()->toDateString(),
            'created_by' => $user->id,
        ]);

        \App\Models\SalesSchedule::create([
            'lead_id' => $lead->id,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'title' => 'Demo Solusi',
            'type' => 'presentation',
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(2),
            'status' => 'scheduled',
        ]);

        \App\Models\LeadTask::create([
            'lead_id' => $lead->id,
            'title' => 'Siapkan BOQ Firewall',
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'status' => 'in_progress',
            'priority' => 'high',
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Konfirmasi jadwal presentasi')
            ->assertSee('Demo Solusi')
            ->assertSee('Siapkan BOQ Firewall');
    }

    public function test_sparkline_builder_renders_flat_line_when_zero_and_bezier_when_data_varies(): void
    {
        $zeroSpark = \App\Support\SparklineBuilder::fromSeries([0, 0, 0, 0, 0, 0], 0);
        $this->assertTrue($zeroSpark['is_flat']);
        $this->assertSame('M0,43 L240,43', $zeroSpark['line']);

        $zeroMetricOverride = \App\Support\SparklineBuilder::fromSeries([1, 2, 3, 4, 5, 6], 0);
        $this->assertTrue($zeroMetricOverride['is_flat']);
        $this->assertSame('M0,43 L240,43', $zeroMetricOverride['line']);

        $activeSpark = \App\Support\SparklineBuilder::fromSeries([1, 3, 2, 5, 4, 8], 8);
        $this->assertFalse($activeSpark['is_flat']);
        $this->assertStringContainsString(' C', $activeSpark['line']);
    }
}
