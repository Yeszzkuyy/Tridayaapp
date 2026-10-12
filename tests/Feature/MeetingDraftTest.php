<?php

namespace Tests\Feature;

use App\Ai\Agents\MeetingUpdateDrafter;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\MeetingDraft;
use App\Models\User;
use App\Notifications\DailyMeetingReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MeetingDraftTest extends TestCase
{
    use RefreshDatabase;

    private function loginAs(string $role): User
    {
        Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function aiDraftJson(): string
    {
        return json_encode([
            'participants' => 'Budi (IT Manager)',
            'user_needs' => 'CCTV 8 channel untuk gudang',
            'user_complaints' => '',
            'existing_system' => 'DVR lama rusak',
            'notes' => 'Minta revisi penawaran minggu depan',
        ]);
    }

    private function makeCustomer(string $name = 'PT Draft Uji'): Customer
    {
        return Customer::create(['name' => $name]);
    }

    public function test_generate_creates_pending_draft_from_one_sentence(): void
    {
        MeetingUpdateDrafter::fake([$this->aiDraftJson()]);
        $sales = $this->loginAs('sales');
        $customer = $this->makeCustomer();

        $this->actingAs($sales)
            ->post(route('sales.meeting-drafts.generate'), [
                'customer_id' => $customer->id,
                'source_sentence' => 'Kunjungan PT Draft Uji, demo CCTV, minta revisi penawaran',
            ])
            ->assertRedirect(route('sales.follow-ups.index'));

        $draft = MeetingDraft::first();
        $this->assertNotNull($draft);
        $this->assertSame('pending', $draft->status);
        $this->assertSame($sales->id, $draft->created_by);
        $this->assertSame('Budi (IT Manager)', $draft->participants);
        $this->assertSame('CCTV 8 channel untuk gudang', $draft->user_needs);
        $this->assertSame(today()->toDateString(), $draft->meeting_date->toDateString());
        $this->assertSame(0, Meeting::count());
    }

    public function test_generate_fails_gracefully_on_invalid_ai_output(): void
    {
        MeetingUpdateDrafter::fake(['bukan json sama sekali {{{']);
        $sales = $this->loginAs('sales');

        $this->actingAs($sales)
            ->post(route('sales.meeting-drafts.generate'), [
                'customer_id' => $this->makeCustomer()->id,
                'source_sentence' => 'Kunjungan singkat',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, MeetingDraft::count());
    }

    public function test_approve_moves_draft_to_meetings(): void
    {
        MeetingUpdateDrafter::fake([$this->aiDraftJson()]);
        $sales = $this->loginAs('sales');
        $customer = $this->makeCustomer();

        $this->actingAs($sales)->post(route('sales.meeting-drafts.generate'), [
            'customer_id' => $customer->id,
            'source_sentence' => 'Kunjungan dan demo',
        ]);
        $draft = MeetingDraft::first();

        $this->actingAs($sales)
            ->post(route('sales.meeting-drafts.approve', $draft), [
                'participants' => 'Budi (IT Manager)',
                'user_needs' => 'CCTV 8 channel (revisi sales)',
                'user_complaints' => null,
                'existing_system' => null,
                'notes' => null,
            ])
            ->assertRedirect(route('sales.follow-ups.index'));

        $this->assertSame('approved', $draft->fresh()->status);
        $meeting = Meeting::first();
        $this->assertNotNull($meeting);
        $this->assertSame($customer->id, $meeting->customer_id);
        $this->assertSame('CCTV 8 channel (revisi sales)', $meeting->user_needs);
    }

    public function test_discard_marks_draft_discarded(): void
    {
        $sales = $this->loginAs('sales');
        $draft = MeetingDraft::create([
            'created_by' => $sales->id,
            'customer_id' => $this->makeCustomer()->id,
            'meeting_date' => today()->toDateString(),
            'source_sentence' => 'Salah customer',
            'status' => 'pending',
        ]);

        $this->actingAs($sales)
            ->post(route('sales.meeting-drafts.discard', $draft))
            ->assertRedirect(route('sales.follow-ups.index'));

        $this->assertSame('discarded', $draft->fresh()->status);
        $this->assertSame(0, Meeting::count());
    }

    public function test_other_sales_cannot_approve_foreign_draft(): void
    {
        $salesA = $this->loginAs('sales');
        $salesB = User::factory()->create();
        $salesB->assignRole('sales');
        $draft = MeetingDraft::create([
            'created_by' => $salesA->id,
            'customer_id' => $this->makeCustomer()->id,
            'meeting_date' => today()->toDateString(),
            'source_sentence' => 'Milik A',
            'status' => 'pending',
        ]);

        $this->actingAs($salesB)
            ->post(route('sales.meeting-drafts.approve', $draft), [])
            ->assertForbidden();

        $this->assertSame(0, Meeting::count());
    }

    public function test_reminder_notifies_active_sales_without_meeting(): void
    {
        $this->loginAs('sales');
        $busy = User::factory()->create();
        $busy->assignRole('sales');
        $idle = User::factory()->create();
        $idle->assignRole('sales');
        $done = User::factory()->create();
        $done->assignRole('sales');

        $customer = $this->makeCustomer();
        FollowUp::create([
            'customer_id' => $customer->id,
            'description' => 'Follow up pagi',
            'follow_up_date' => today()->toDateString(),
            'created_by' => $busy->id,
        ]);
        FollowUp::create([
            'customer_id' => $customer->id,
            'description' => 'Follow up pagi',
            'follow_up_date' => today()->toDateString(),
            'created_by' => $done->id,
        ]);
        Meeting::create([
            'customer_id' => $customer->id,
            'meeting_date' => today()->toDateString(),
            'created_by' => $done->id,
        ]);

        $this->artisan('meetings:remind-daily')->assertSuccessful();

        $this->assertTrue($busy->notifications()->where('type', DailyMeetingReminderNotification::class)->exists());
        $this->assertFalse($idle->notifications()->where('type', DailyMeetingReminderNotification::class)->exists());
        $this->assertFalse($done->notifications()->where('type', DailyMeetingReminderNotification::class)->exists());
    }

    public function test_old_meetings_menu_redirects_to_follow_ups(): void
    {
        $sales = $this->loginAs('sales');

        $this->actingAs($sales)
            ->get(route('sales.meetings.index'))
            ->assertRedirect(route('sales.follow-ups.index'));
    }

    public function test_follow_ups_page_is_follow_up_only(): void
    {
        $sales = $this->loginAs('sales');

        $this->actingAs($sales)
            ->get(route('sales.follow-ups.index'))
            ->assertOk()
            ->assertSee('Follow Up', false)
            ->assertDontSee('Daily Update', false);
    }
}
