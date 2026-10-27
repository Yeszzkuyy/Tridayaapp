<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_sales_can_view_meeting_report_form(): void
    {
        $sales = $this->userWithRole('sales');
        $fu = $this->makeMeeting($sales);

        $res = $this->actingAs($sales)->get(route('sales.meetings.report', $fu));

        $res->assertOk();
        $res->assertSee('Meeting Report &amp; MOM', false);
        $res->assertSee('Photo Documentation');
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

    public function test_submit_meeting_report_validates_required_fields(): void
    {
        $sales = $this->userWithRole('sales');
        $fu = $this->makeMeeting($sales);

        $res = $this->actingAs($sales)->post(route('sales.meetings.report.store', $fu), []);

        $res->assertSessionHasErrors(['meeting_photos', 'meeting_notes', 'meeting_outcome']);
    }

    public function test_submit_meeting_report_success_and_saves_data(): void
    {
        Storage::fake('public');

        $sales = $this->userWithRole('sales');
        $customer = Customer::create(['name' => 'PT Target Customer '.uniqid()]);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'title' => 'Project Pengadaan CCTV',
            'status' => 'follow_up',
            'assigned_to' => $sales->id,
        ]);
        $fu = $this->makeMeeting($sales, ['lead_id' => $lead->id, 'customer_id' => $customer->id]);

        $photo1 = UploadedFile::fake()->image('meeting_1.jpg', 600, 400);
        $photo2 = UploadedFile::fake()->image('meeting_2.png', 800, 600);

        $res = $this->actingAs($sales)->post(route('sales.meetings.report.store', $fu), [
            'meeting_photos' => [$photo1, $photo2],
            'meeting_notes' => 'Diskusi berjalan lancar. Klien meminta quotation 16 unit kamera beserta switch PoE. Target follow-up minggu depan.',
            'meeting_outcome' => 'need_quotation',
            'meeting_next_action_date' => '2026-10-30',
            'return' => 'schedule',
            'date' => '2026-10-25',
        ]);

        $res->assertRedirect(route('sales.schedules.index', ['date' => '2026-10-25']));
        $res->assertSessionHas('success');

        $fu->refresh();
        $this->assertNotNull($fu->completed_at);
        $this->assertSame('need_quotation', $fu->meeting_outcome);
        $this->assertStringContainsString('Diskusi berjalan lancar', $fu->meeting_notes);
        $this->assertSame('2026-10-30', $fu->meeting_next_action_date->toDateString());
        $this->assertCount(2, $fu->meeting_photos);
        $this->assertTrue($fu->hasMeetingReport());

        // Pastikan foto tersimpan di public disk
        foreach ($fu->meeting_photos as $path) {
            Storage::disk('public')->assertExists($path);
        }

        // Pastikan aktivitas lead tercatat
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'action' => 'meeting_report_submitted',
        ]);
    }
}
