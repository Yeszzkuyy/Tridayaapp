<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementSalesTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_management_sidebar_has_one_manage_sales_entry(): void
    {
        $management = User::factory()->create();
        $management->assignRole('management');

        $html = $this->actingAs($management)->get(route('dashboard'))->assertOk()->getContent();
        $start = strpos($html, 'id="sidebar-management-menu"');
        $this->assertNotFalse($start);
        $sidebar = substr($html, $start, (strpos($html, '</div>', $start) ?: strlen($html)) - $start);

        $this->assertStringContainsString(route('manage-sales.index'), $sidebar);
        $this->assertStringNotContainsString(route('manage-sales.kpi'), $sidebar);
        $this->assertStringNotContainsString(route('manage-sales.team-activity'), $sidebar);
    }

    public function test_three_management_sales_pages_share_the_tabs(): void
    {
        $management = User::factory()->create();
        $management->assignRole('management');

        foreach ([
            route('manage-sales.index') => 'Lead Assignment',
            route('manage-sales.kpi') => 'Sales KPI &amp; Target',
            route('manage-sales.team-activity') => 'Team Activity',
        ] as $url => $activeTab) {
            $response = $this->actingAs($management)->get($url)->assertOk();
            $response->assertSee('Lead Assignment');
            $response->assertSee('Sales KPI &amp; Target', false);
            $response->assertSee('Team Activity');
            $response->assertSee($activeTab, false);
        }
    }
}
