<?php

namespace Tests\Feature;

use App\Models\TechnicianSchedule;
use App\Models\User;
use App\Notifications\TechnicianScheduleReminderNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TechnicianScheduleReminderTest extends TestCase
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

    private function makeSchedule(User $owner, array $overrides = []): TechnicianSchedule
    {
        return TechnicianSchedule::create(array_merge([
            'user_id' => $owner->id,
            'title' => 'Instalasi server',
            'start_at' => '2026-11-12 09:00:00',
            'end_at' => '2026-11-12 11:00:00',
            'status' => 'scheduled',
        ], $overrides));
    }

    private function reminderPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Survey lokasi',
            'date' => '2026-11-12',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'status' => 'scheduled',
            'reminder_minutes' => '30',
        ], $overrides);
    }

    public function test_numeric_reminder_targets_minutes_before_start(): void
    {
        $schedule = new TechnicianSchedule([
            'start_at' => '2026-11-12 09:00:00',
            'reminder_minutes' => 60,
        ]);

        $this->assertTrue(
            $schedule->reminderAt()->equalTo(Carbon::parse('2026-11-12 08:00:00'))
        );
    }

    public function test_morning_reminder_targets_0810_on_event_day(): void
    {
        $schedule = new TechnicianSchedule([
            'start_at' => '2026-11-12 14:00:00',
            'reminder_minutes' => TechnicianSchedule::REMINDER_MORNING_OF_EVENT,
        ]);

        $this->assertTrue(
            $schedule->reminderAt()->equalTo(Carbon::parse('2026-11-12 08:10:00'))
        );
        $this->assertSame('morning_of_event', $schedule->reminder_value);
    }

    public function test_command_sends_numeric_reminder_at_the_right_time(): void
    {
        Notification::fake();

        $owner = $this->userWithRole('management');
        $tech = $this->userWithRole('technician');
        $this->makeSchedule($owner, [
            'technician_user_id' => $tech->id,
            'reminder_minutes' => 60,
        ]);

        $this->travelTo(Carbon::parse('2026-11-12 07:59:00'));
        $this->artisan('schedules:remind')->assertSuccessful();
        Notification::assertNotSentTo($tech, TechnicianScheduleReminderNotification::class);

        $this->travelTo(Carbon::parse('2026-11-12 08:00:00'));
        $this->artisan('schedules:remind')->assertSuccessful();
        Notification::assertSentToTimes($tech, TechnicianScheduleReminderNotification::class, 1);

        // Tidak terkirim dua kali.
        $this->travelTo(Carbon::parse('2026-11-12 08:01:00'));
        $this->artisan('schedules:remind')->assertSuccessful();
        Notification::assertSentToTimes($tech, TechnicianScheduleReminderNotification::class, 1);
    }

    public function test_command_sends_morning_reminder_at_0810(): void
    {
        Notification::fake();

        $owner = $this->userWithRole('management');
        $tech = $this->userWithRole('technician');
        $this->makeSchedule($owner, [
            'technician_user_id' => $tech->id,
            'start_at' => '2026-11-12 14:00:00',
            'end_at' => '2026-11-12 16:00:00',
            'reminder_minutes' => TechnicianSchedule::REMINDER_MORNING_OF_EVENT,
        ]);

        $this->travelTo(Carbon::parse('2026-11-12 08:09:00'));
        $this->artisan('schedules:remind')->assertSuccessful();
        Notification::assertNotSentTo($tech, TechnicianScheduleReminderNotification::class);

        $this->travelTo(Carbon::parse('2026-11-12 08:10:00'));
        $this->artisan('schedules:remind')->assertSuccessful();
        Notification::assertSentToTimes($tech, TechnicianScheduleReminderNotification::class, 1);
    }

    public function test_form_accepts_all_reminder_options_and_stores_morning_sentinel(): void
    {
        $lead = $this->userWithRole('lead-technician');

        $this->actingAs($lead)
            ->post(route('teknisi.schedules.store'), $this->reminderPayload([
                'reminder_minutes' => 'morning_of_event',
                'technician_user_id' => $lead->id,
            ]))
            ->assertSessionHasNoErrors();

        $schedule = TechnicianSchedule::firstOrFail();
        $this->assertSame(TechnicianSchedule::REMINDER_MORNING_OF_EVENT, $schedule->reminder_minutes);
        $this->assertSame('morning_of_event', $schedule->reminder_value);

        $this->actingAs($lead)
            ->put(route('teknisi.schedules.update', $schedule), $this->reminderPayload([
                'reminder_minutes' => '1440',
                'technician_user_id' => $lead->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1440, $schedule->fresh()->reminder_minutes);
    }

    public function test_form_rejects_unknown_reminder_value(): void
    {
        $lead = $this->userWithRole('lead-technician');

        $this->actingAs($lead)
            ->post(route('teknisi.schedules.store'), $this->reminderPayload([
                'reminder_minutes' => '15',
                'technician_user_id' => $lead->id,
            ]))
            ->assertSessionHasErrors('reminder_minutes');

        $this->assertDatabaseCount('technician_schedules', 0);
    }

    public function test_api_returns_reminder_value_for_edit_form(): void
    {
        $tech = $this->userWithRole('technician');
        $this->makeSchedule($tech, ['title' => 'Pagi Hari H', 'reminder_minutes' => 0]);
        $this->makeSchedule($tech, [
            'title' => 'Satu jam sebelumnya',
            'start_at' => '2026-11-12 10:00:00',
            'end_at' => '2026-11-12 12:00:00',
            'reminder_minutes' => 60,
        ]);

        $res = $this->actingAs($tech)
            ->getJson(route('teknisi.schedules.api', ['date' => '2026-11-12']))
            ->assertOk();

        $byTitle = collect($res->json())->keyBy('title');
        $this->assertSame('morning_of_event', $byTitle['Pagi Hari H']['reminder_value']);
        $this->assertSame(0, $byTitle['Pagi Hari H']['reminder_minutes']);
        $this->assertSame('60', $byTitle['Satu jam sebelumnya']['reminder_value']);
    }

    public function test_changing_reminder_option_resets_sent_marker(): void
    {
        $lead = $this->userWithRole('lead-technician');
        $schedule = $this->makeSchedule($lead, [
            'reminder_minutes' => 30,
            'reminder_sent_at' => now(),
        ]);

        $this->actingAs($lead)
            ->put(route('teknisi.schedules.update', $schedule), $this->reminderPayload([
                'reminder_minutes' => '60',
                'technician_user_id' => $lead->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull($schedule->fresh()->reminder_sent_at);
    }

    public function test_form_can_remove_reminder(): void
    {
        $lead = $this->userWithRole('lead-technician');
        $schedule = $this->makeSchedule($lead, ['reminder_minutes' => 30]);

        $this->actingAs($lead)
            ->put(route('teknisi.schedules.update', $schedule), $this->reminderPayload([
                'reminder_minutes' => '',
                'technician_user_id' => $lead->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull($schedule->fresh()->reminder_minutes);
    }

    public function test_unchanged_morning_reminder_keeps_sent_marker(): void
    {
        $lead = $this->userWithRole('lead-technician');
        $schedule = $this->makeSchedule($lead, [
            'reminder_minutes' => TechnicianSchedule::REMINDER_MORNING_OF_EVENT,
            'reminder_sent_at' => now(),
        ]);

        $this->actingAs($lead)
            ->put(route('teknisi.schedules.update', $schedule), $this->reminderPayload([
                'reminder_minutes' => 'morning_of_event',
                'technician_user_id' => $lead->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($schedule->fresh()->reminder_sent_at);
    }
}
