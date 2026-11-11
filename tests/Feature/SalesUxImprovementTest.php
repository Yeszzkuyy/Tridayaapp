<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\SalesSchedule;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Perubahan UX Nov 2026: form lead (tanpa Location, requirement auto-grow),
 * tabel requirement seragam, Team Activity untuk management, dan
 * tombol Follow Up berlabel + tour di My Leads.
 */
class SalesUxImprovementTest extends TestCase
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

    private function makeLead(?User $assignee = null): Lead
    {
        $customer = Customer::create(['name' => 'PT UX '.uniqid(), 'pt_group' => 'NTI']);
        LeadActivity::query()->delete();

        return Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'hot',
            'assigned_to' => $assignee?->id,
            'incoming_date' => '2026-11-01',
            'kebutuhan' => 'Butuh 10 unit CCTV beserta instalasi.',
        ]);
    }

    public function test_lead_form_drops_location_and_uses_auto_grow_requirement(): void
    {
        $marketing = $this->userWithRole('marketing');

        $html = $this->actingAs($marketing)->get(route('leads.create'))->assertOk()->getContent();

        $this->assertStringNotContainsString('name="location"', $html);
        $this->assertStringContainsString('data-auto-grow', $html);
        $this->assertStringContainsString('rows="4"', $html);
    }

    public function test_management_can_open_team_activity_and_sales_cannot(): void
    {
        $management = $this->userWithRole('management');
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales);

        SalesSchedule::create([
            'lead_id' => $lead->id,
            'assigned_to' => $sales->id,
            'created_by' => $management->id,
            'title' => 'Visit kantor',
            'type' => 'visit',
            'start_at' => now()->subHour(),
            'status' => 'completed',
        ]);

        $this->actingAs($management)->get(route('manage-sales.team-activity'))
            ->assertOk()
            ->assertSee(__('Team Activity'))
            ->assertSee($sales->name)
            ->assertSee(__('Schedules Today'));

        $this->actingAs($sales)->get(route('manage-sales.team-activity'))->assertForbidden();
    }

    public function test_my_leads_marks_new_lead_with_labeled_follow_up_and_tour(): void
    {
        $sales = $this->userWithRole('sales');
        $this->makeLead($sales);

        $html = $this->actingAs($sales)->get(route('sales.my-leads'))->assertOk()->getContent();

        $this->assertStringContainsString('data-tour="followup"', $html);
        $this->assertStringContainsString(__('Quick guide: 3 steps to follow up'), $html);
        $this->assertStringContainsString('rounded-[100px]', $html);
        $this->assertStringContainsString(__('Add Follow Up'), $html);
    }

    public function test_requirement_cell_is_left_aligned_with_line_clamp(): void
    {
        $marketing = $this->userWithRole('marketing');
        $this->makeLead();

        $html = $this->actingAs($marketing)->get(route('leads.index'))->assertOk()->getContent();

        $this->assertStringContainsString('line-clamp-2', $html);
        $this->assertStringContainsString('View full requirement', $html);
    }
}
