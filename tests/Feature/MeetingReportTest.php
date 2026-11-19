<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingReportTest extends TestCase
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
        $customer = Customer::create(['name' => 'PT Klien Maju '.uniqid()]);

        return FollowUp::create(array_merge([
            'customer_id' => $customer->id,
            'description' => 'Meeting presentasi solusi',
            'type' => 'meeting',
            'follow_up_date' => '2026-10-25',
            'activity_time' => '14:00',
            'location' => 'Kantor klien',
            'created_by' => $creator->id,
        ], $overrides));
    }

    public function test_store_meeting_marks_daily_report_sent(): void
    {
        $sales = $this->userWithRole('sales');
        $customer = Customer::create([
            'name' => 'PT Meeting Baru '.uniqid(),
            'created_by' => $sales->id,
        ]);
        $nextMeeting = today()->addDays(3)->toDateString();

        $res = $this->actingAs($sales)->post(route('sales.meetings.store'), [
            'customer_id' => $customer->id,
            'follow_up_date' => today()->toDateString(),
            'activity_time' => '10:00',
            'meeting_mode' => 'offline',
            'description' => 'Meeting perdana dengan calon customer.',
            'next_follow_up_date' => $nextMeeting,
        ]);

        $meeting = FollowUp::query()->latest('id')->firstOrFail();

        $res->assertRedirect(route('sales.meetings.show', $meeting));
        $this->assertSame('meeting', $meeting->type);
        $this->assertNotNull($meeting->completed_at);
        $this->assertNotNull($meeting->daily_report_submitted_at);
        $this->assertSame($nextMeeting, $meeting->meeting_next_action_date->toDateString());
    }

    public function test_sales_can_view_meeting_report_form(): void
    {
        $sales = $this->userWithRole('sales');
        $fu = $this->makeMeeting($sales);

        $res = $this->actingAs($sales)->get(route('sales.meetings.report', $fu));

        $res->assertOk();
        $res->assertSee('Send Daily Report');
        $res->assertSee('Summary Meeting');
        $res->assertDontSee('name="meeting_outcome"', false);
        $res->assertDontSee('name="meeting_photos"', false);
        $res->assertDontSee('name="meeting_notes"', false);
    }

    public function test_non_meeting_type_aborts_when_opening_meeting_report(): void
    {
        $sales = $this->userWithRole('sales');
        $fu = $this->makeMeeting($sales, ['type' => 'call']);

        $res = $this->actingAs($sales)->get(route('sales.meetings.report', $fu));

        $res->assertStatus(422);
    }

    public function test_already_completed_meeting_aborts_when_opening_meeting_report(): void
    {
        $sales = $this->userWithRole('sales');
        $fu = $this->makeMeeting($sales, ['completed_at' => now()]);

        $res = $this->actingAs($sales)->get(route('sales.meetings.report', $fu));

        $res->assertStatus(422);
    }

    public function test_submit_meeting_report_marks_daily_report_sent_without_fields(): void
    {
        $sales = $this->userWithRole('sales');
        $fu = $this->makeMeeting($sales);

        $res = $this->actingAs($sales)->post(route('sales.meetings.report.store', $fu), []);

        $res->assertRedirect(route('sales.meetings.show', $fu));

        $fu->refresh();
        $this->assertNotNull($fu->completed_at);
        $this->assertNotNull($fu->daily_report_submitted_at);
        $this->assertTrue($fu->hasMeetingReport());
    }

    public function test_submit_meeting_report_success_and_saves_data(): void
    {
        $sales = $this->userWithRole('sales');
        $customer = Customer::create(['name' => 'PT Target Customer '.uniqid()]);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'title' => 'Project Pengadaan CCTV',
            'status' => 'follow_up',
            'assigned_to' => $sales->id,
        ]);
        $nextMeeting = now()->addDays(5)->toDateString();
        $fu = $this->makeMeeting($sales, [
            'lead_id' => $lead->id,
            'customer_id' => $customer->id,
            'next_follow_up_date' => $nextMeeting,
        ]);

        $returnDate = now()->toDateString();

        $res = $this->actingAs($sales)->post(route('sales.meetings.report.store', $fu), [
            'return' => 'schedule',
            'date' => $returnDate,
        ]);

        $res->assertRedirect(route('sales.schedules.index', ['date' => $returnDate]));
        $res->assertSessionHas('success');

        $fu->refresh();
        $this->assertNotNull($fu->completed_at);
        $this->assertNotNull($fu->daily_report_submitted_at);
        $this->assertSame($nextMeeting, $fu->meeting_next_action_date->toDateString());
        $this->assertTrue($fu->hasMeetingReport());

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'action' => 'daily_report_submitted',
        ]);
    }
}
