<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use App\Services\SalesReportStatusService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesDailyReportTest extends TestCase
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

    private function makeMeeting(User $creator, array $overrides = []): FollowUp
    {
        $customer = Customer::create(['name' => 'PT Daily '.uniqid()]);

        return FollowUp::create(array_merge([
            'customer_id' => $customer->id,
            'description' => 'Meeting harian',
            'type' => 'meeting',
            'follow_up_date' => today()->toDateString(),
            'activity_time' => '09:00',
            'meeting_mode' => 'offline',
            'created_by' => $creator->id,
        ], $overrides));
    }

    public function test_daily_status_marks_done_partial_and_missing(): void
    {
        $done = $this->userWithRole('sales');
        $partial = $this->userWithRole('sales');
        $missing = $this->userWithRole('sales');

        $this->makeMeeting($done, [
            'completed_at' => now(),
            'meeting_notes' => 'Semua poin meeting sudah dicatat.',
            'meeting_outcome' => 'prospect',
            'daily_report_submitted_at' => now(),
        ]);

        $this->makeMeeting($partial, [
            'completed_at' => now(),
            'meeting_notes' => 'Meeting pertama selesai.',
            'meeting_outcome' => 'follow_up',
            'daily_report_submitted_at' => now(),
        ]);
        $this->makeMeeting($partial);

        $this->makeMeeting($missing);

        $rows = app(SalesReportStatusService::class)
            ->daily(today())
            ->keyBy(fn (array $row) => $row['user']->id);

        $this->assertSame('done', $rows[$done->id]['status']);
        $this->assertSame(1, $rows[$done->id]['total']);
        $this->assertSame(1, $rows[$done->id]['sent']);

        $this->assertSame('partial', $rows[$partial->id]['status']);
        $this->assertSame(2, $rows[$partial->id]['total']);
        $this->assertSame(1, $rows[$partial->id]['sent']);
        $this->assertSame(1, $rows[$partial->id]['pending']);

        $this->assertSame('missing', $rows[$missing->id]['status']);
        $this->assertSame(1, $rows[$missing->id]['total']);
        $this->assertSame(0, $rows[$missing->id]['sent']);
    }

    public function test_sales_dashboard_shows_daily_report_card(): void
    {
        $sales = $this->userWithRole('sales');
        $this->makeMeeting($sales);

        $res = $this->actingAs($sales)->get(route('sales.dashboard'));

        $res->assertOk();
        $res->assertSee('Daily Report');
        $res->assertSee('Send report');
    }

    public function test_management_team_activity_shows_meeting_report_status(): void
    {
        $management = $this->userWithRole('management');
        $done = $this->userWithRole('sales');
        $missing = $this->userWithRole('sales');

        $this->makeMeeting($done, [
            'completed_at' => now(),
            'meeting_notes' => 'Laporan lengkap.',
            'meeting_outcome' => 'prospect',
            'daily_report_submitted_at' => now(),
        ]);
        $this->makeMeeting($missing);

        $this->actingAs($management)
            ->get(route('manage-sales.team-activity'))
            ->assertOk()
            ->assertSee(__('Meeting Reports'))
            ->assertSee(__('Report Sent'))
            ->assertSee(__('Not reported'))
            ->assertSee($done->name)
            ->assertSee($missing->name);
    }

    public function test_team_activity_date_filter_uses_selected_date(): void
    {
        $management = $this->userWithRole('management');
        $sales = $this->userWithRole('sales');
        $yesterday = now()->subDay()->toDateString();

        $this->makeMeeting($sales, ['follow_up_date' => $yesterday]);
        $this->makeMeeting($sales, [
            'completed_at' => now(),
            'meeting_notes' => 'Laporan hari ini.',
            'daily_report_submitted_at' => now(),
        ]);

        $response = $this->actingAs($management)
            ->get(route('manage-sales.team-activity', ['date' => $yesterday]))
            ->assertOk();

        $rows = collect($response->viewData('reportRows'))->keyBy(fn ($row) => $row['user']->id);

        $this->assertSame(1, $rows[$sales->id]['total']);
        $this->assertSame(0, $rows[$sales->id]['sent']);
        $this->assertSame('missing', $rows[$sales->id]['status']);
    }

    public function test_team_activity_pt_filter_uses_lead_pt_group(): void
    {
        $management = $this->userWithRole('management');
        $sales = $this->userWithRole('sales');

        $ntiCustomer = Customer::create(['name' => 'PT NTI Filter']);
        $ntiLead = Lead::create([
            'customer_id' => $ntiCustomer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'hot',
            'assigned_to' => $sales->id,
            'incoming_date' => today()->toDateString(),
            'kebutuhan' => 'Meeting NTI',
        ]);

        $mgkCustomer = Customer::create(['name' => 'PT MGK Filter']);
        $mgkLead = Lead::create([
            'customer_id' => $mgkCustomer->id,
            'pt_group' => 'MGK',
            'segment' => 'vendor',
            'status' => 'hot',
            'assigned_to' => $sales->id,
            'incoming_date' => today()->toDateString(),
            'kebutuhan' => 'Meeting MGK',
        ]);

        $this->makeMeeting($sales, ['customer_id' => $ntiCustomer->id, 'lead_id' => $ntiLead->id]);
        $this->makeMeeting($sales, ['customer_id' => $mgkCustomer->id, 'lead_id' => $mgkLead->id]);

        $response = $this->actingAs($management)
            ->get(route('manage-sales.team-activity', ['pt_group' => 'NTI']))
            ->assertOk();

        $rows = collect($response->viewData('reportRows'))->keyBy(fn ($row) => $row['user']->id);

        $this->assertSame(1, $rows[$sales->id]['total']);
    }
}
