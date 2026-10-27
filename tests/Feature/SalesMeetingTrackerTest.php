<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\User;
use App\Notifications\MeetingReportReminderNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Menu Meeting dipisah dari Follow Up: tracker per sales, laporan MOM + foto,
 * reminder, dan Follow Up tidak lagi memuat meeting.
 */
class SalesMeetingTrackerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function userWithRoles(array $roles): User
    {
        $user = User::factory()->create();
        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function makeCustomer(?string $name = null): Customer
    {
        return Customer::create(['name' => $name ?? 'PT Meeting '.uniqid(), 'pt_group' => 'NTI']);
    }

    private function makeMeeting(User $creator, array $overrides = []): FollowUp
    {
        return FollowUp::create(array_merge([
            'customer_id' => $this->makeCustomer()->id,
            'description' => 'Meeting bahas penawaran',
            'type' => 'meeting',
            'follow_up_date' => today()->toDateString(),
            'activity_time' => '10:00',
            'meeting_mode' => 'office',
            'created_by' => $creator->id,
        ], $overrides));
    }

    public function test_sales_only_sees_own_meetings(): void
    {
        $salesA = $this->userWithRoles(['sales']);
        $salesB = $this->userWithRoles(['sales']);
        $manager = $this->userWithRoles(['management']);

        $mine = $this->makeMeeting($salesA, ['customer_id' => $this->makeCustomer('PT Milik A')->id]);
        $foreign = $this->makeMeeting($salesB, ['customer_id' => $this->makeCustomer('PT Milik B')->id]);

        $html = $this->actingAs($salesA)->get(route('sales.meetings.index', ['range' => 'all']))
            ->assertOk()->getContent();
        $this->assertStringContainsString('PT Milik A', $html);
        $this->assertStringNotContainsString('PT Milik B', $html);

        $html = $this->actingAs($manager)->get(route('sales.meetings.index', ['range' => 'all', 'tab' => 'team']))
            ->assertOk()->getContent();
        $this->assertStringContainsString('PT Milik A', $html);
        $this->assertStringContainsString('PT Milik B', $html);
    }

    public function test_sales_can_create_meeting_with_mode(): void
    {
        $sales = $this->userWithRoles(['sales']);
        $customer = $this->makeCustomer();

        $this->actingAs($sales)->post(route('sales.meetings.store'), [
            'customer_id' => $customer->id,
            'follow_up_date' => today()->toDateString(),
            'activity_time' => '14:30',
            'meeting_mode' => 'field',
            'location' => 'Kantor customer',
            'description' => 'Presentasi solusi baru',
        ])->assertRedirect();

        $meeting = FollowUp::where('type', 'meeting')->firstOrFail();
        $this->assertSame($customer->id, $meeting->customer_id);
        $this->assertSame('field', $meeting->meeting_mode);
        $this->assertSame($sales->id, $meeting->created_by);
        $this->assertNull($meeting->completed_at);
    }

    public function test_meeting_report_saves_photos_and_completes(): void
    {
        Storage::fake('public');

        $sales = $this->userWithRoles(['sales']);
        $meeting = $this->makeMeeting($sales);

        $this->actingAs($sales)->post(route('sales.meetings.report.store', $meeting), [
            'meeting_photos' => [UploadedFile::fake()->image('meeting.jpg', 600, 400)],
            'meeting_notes' => 'Diskusi berjalan lancar, customer minta penawaran.',
            'meeting_outcome' => 'need_quotation',
            'meeting_next_action_date' => today()->addDays(3)->toDateString(),
        ])->assertRedirect(route('sales.meetings.show', $meeting));

        $meeting->refresh();
        $this->assertNotNull($meeting->completed_at);
        $this->assertSame('need_quotation', $meeting->meeting_outcome);
        $this->assertCount(1, $meeting->meeting_photos);
        $this->assertTrue($meeting->hasMeetingReport());
        Storage::disk('public')->assertExists($meeting->meeting_photos[0]);
    }

    public function test_meeting_reminder_command_notifies_creator_once(): void
    {
        Notification::fake();

        $sales = $this->userWithRoles(['sales']);
        $meeting = $this->makeMeeting($sales, [
            'follow_up_date' => today()->subDay()->toDateString(),
        ]);

        $this->artisan('meetings:remind')->assertSuccessful();

        Notification::assertSentTo($sales, MeetingReportReminderNotification::class);
        $this->assertNotNull($meeting->fresh()->reminder_sent_at);

        // Jalankan ulang: tidak dobel.
        $this->artisan('meetings:remind')->assertSuccessful();
        Notification::assertSentToTimes($sales, MeetingReportReminderNotification::class, 1);
    }

    public function test_follow_up_index_excludes_meetings(): void
    {
        $sales = $this->userWithRoles(['sales']);
        $customer = $this->makeCustomer();
        $lead = \App\Models\Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'warm',
            'assigned_to' => $sales->id,
        ]);

        FollowUp::create([
            'customer_id' => $customer->id,
            'lead_id' => $lead->id,
            'description' => 'Telepon customer soal harga',
            'type' => 'call',
            'follow_up_date' => today()->toDateString(),
            'created_by' => $sales->id,
        ]);
        FollowUp::create([
            'customer_id' => $customer->id,
            'lead_id' => $lead->id,
            'description' => 'Meeting bahas kontrak',
            'type' => 'meeting',
            'follow_up_date' => today()->toDateString(),
            'created_by' => $sales->id,
        ]);

        $html = $this->actingAs($sales)->get(route('sales.follow-ups.index'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Telepon customer soal harga', $html);
        $this->assertStringNotContainsString('Meeting bahas kontrak', $html);
    }
}
