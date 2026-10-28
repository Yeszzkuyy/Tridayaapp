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
            'meeting_mode' => 'offline',
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
            'meeting_mode' => 'offline',
            'location' => 'Kantor customer',
            'description' => 'Presentasi solusi baru',
        ])->assertRedirect();

        $meeting = FollowUp::where('type', 'meeting')->firstOrFail();
        $this->assertSame($customer->id, $meeting->customer_id);
        $this->assertSame('offline', $meeting->meeting_mode);
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

    public function test_create_meeting_accepts_five_photos(): void
    {
        Storage::fake('public');

        $sales = $this->userWithRoles(['sales']);
        $customer = $this->makeCustomer();

        $photos = collect(range(1, FollowUp::MAX_MEETING_PHOTOS))
            ->map(fn (int $i) => UploadedFile::fake()->image("bukti-{$i}.jpg", 400, 300))
            ->all();

        $this->actingAs($sales)->post(route('sales.meetings.store'), [
            'customer_id' => $customer->id,
            'follow_up_date' => today()->toDateString(),
            'activity_time' => '14:30',
            'meeting_mode' => 'offline',
            'location' => 'Kantor customer',
            'description' => 'Meeting dengan 5 foto bukti',
            'meeting_photos' => $photos,
        ])->assertSessionHasNoErrors();

        $meeting = FollowUp::where('type', 'meeting')->firstOrFail();
        $this->assertCount(FollowUp::MAX_MEETING_PHOTOS, $meeting->meeting_photos);
    }

    public function test_create_meeting_rejects_more_than_five_photos(): void
    {
        Storage::fake('public');

        $sales = $this->userWithRoles(['sales']);
        $customer = $this->makeCustomer();

        $photos = collect(range(1, FollowUp::MAX_MEETING_PHOTOS + 1))
            ->map(fn (int $i) => UploadedFile::fake()->image("bukti-{$i}.jpg", 400, 300))
            ->all();

        $this->actingAs($sales)->post(route('sales.meetings.store'), [
            'customer_id' => $customer->id,
            'follow_up_date' => today()->toDateString(),
            'meeting_mode' => 'offline',
            'description' => 'Meeting dengan foto kebanyakan',
            'meeting_photos' => $photos,
        ])->assertSessionHasErrors('meeting_photos');

        $this->assertSame(0, FollowUp::where('type', 'meeting')->count());
    }

    public function test_report_adds_photos_until_limit(): void
    {
        Storage::fake('public');

        $sales = $this->userWithRoles(['sales']);
        $meeting = $this->makeMeeting($sales, [
            'meeting_photos' => ['meeting-reports/foto-lama-1.jpg', 'meeting-reports/foto-lama-2.jpg'],
        ]);

        $newPhotos = collect(range(1, 3))
            ->map(fn (int $i) => UploadedFile::fake()->image("tambahan-{$i}.jpg", 400, 300))
            ->all();

        $this->actingAs($sales)->post(route('sales.meetings.report.store', $meeting), [
            'meeting_photos' => $newPhotos,
            'meeting_notes' => 'Diskusi lanjutan, foto bukti ditambahkan setelah meeting.',
            'meeting_outcome' => 'follow_up',
        ])->assertSessionHasNoErrors();

        $meeting->refresh();
        $this->assertCount(FollowUp::MAX_MEETING_PHOTOS, $meeting->meeting_photos);
        $this->assertSame('meeting-reports/foto-lama-1.jpg', $meeting->meeting_photos[0]);
    }

    public function test_detail_page_shows_photos_before_report_submitted(): void
    {
        Storage::fake('public');

        $sales = $this->userWithRoles(['sales']);
        $meeting = $this->makeMeeting($sales, [
            'meeting_photos' => ['meeting-reports/bukti-1.jpg', 'meeting-reports/bukti-2.jpg'],
        ]);

        $html = $this->actingAs($sales)->get(route('sales.meetings.show', $meeting))
            ->assertOk()->getContent();

        $this->assertStringContainsString(__('Meeting Proof Photos'), $html);
        $this->assertStringContainsString('meeting-reports/bukti-1.jpg', $html);
        $this->assertStringContainsString('meeting-reports/bukti-2.jpg', $html);
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

    public function test_create_form_filters_related_lead_by_customer(): void
    {
        $sales = $this->userWithRoles(['sales']);
        $customerA = $this->makeCustomer('PT Filter A');
        $customerB = $this->makeCustomer('PT Filter B');

        foreach ([$customerA, $customerB] as $customer) {
            \App\Models\Lead::create([
                'customer_id' => $customer->id,
                'pt_group' => 'NTI',
                'segment' => 'vendor',
                'status' => 'warm',
                'assigned_to' => $sales->id,
            ]);
        }

        $html = $this->actingAs($sales)->get(route('sales.meetings.create'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('id="mtg-lead-glider"', $html);
        $this->assertStringContainsString('&quot;customer_id&quot;:&quot;'.$customerA->id.'&quot;', $html);
        $this->assertStringContainsString('&quot;customer_id&quot;:&quot;'.$customerB->id.'&quot;', $html);
    }

    public function test_create_meeting_with_proof_photos(): void
    {
        Storage::fake('public');

        $sales = $this->userWithRoles(['sales']);
        $customer = $this->makeCustomer();

        $this->actingAs($sales)->post(route('sales.meetings.store'), [
            'customer_id' => $customer->id,
            'follow_up_date' => today()->toDateString(),
            'meeting_mode' => 'online',
            'description' => 'Meeting online dengan customer',
            'meeting_photos' => [UploadedFile::fake()->image('bukti.jpg', 600, 400)],
        ])->assertRedirect();

        $meeting = FollowUp::where('type', 'meeting')->firstOrFail();
        $this->assertCount(1, $meeting->meeting_photos);
        Storage::disk('public')->assertExists($meeting->meeting_photos[0]);
    }

    public function test_report_keeps_photos_uploaded_at_creation(): void
    {
        Storage::fake('public');

        $sales = $this->userWithRoles(['sales']);
        $meeting = $this->makeMeeting($sales, [
            'meeting_photos' => ['meeting-reports/existing.jpg'],
        ]);

        $this->actingAs($sales)->post(route('sales.meetings.report.store', $meeting), [
            'meeting_notes' => 'Hasil meeting sudah dicatat.',
            'meeting_outcome' => 'prospect',
        ])->assertRedirect();

        $meeting->refresh();
        $this->assertNotNull($meeting->completed_at);
        $this->assertSame(['meeting-reports/existing.jpg'], $meeting->meeting_photos);
    }

    public function test_follow_up_index_excludes_meetings(): void    {
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
