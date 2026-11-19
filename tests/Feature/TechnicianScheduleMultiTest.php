<?php

namespace Tests\Feature;

use App\Models\TechnicianSchedule;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TechnicianScheduleAssignedNotification;
use App\Notifications\TechnicianScheduleChangedNotification;
use Tests\TestCase;

/**
 * Jadwal dengan banyak teknisi (survey/instalasi berangkat tim).
 *
 * Menjaga: data lama (teknisi tunggal) tetap berfungsi, notifikasi
 * dikirim ke semua teknisi yang ditugaskan.
 */
class TechnicianScheduleMultiTest extends TestCase
{
    use RefreshDatabase;

    private bool $seeded = false;

    private function userWithPerms(): User
    {
        // Seeder role cukup sekali per test.
        if (! $this->seeded) {
            $this->seed(RoleAndPermissionSeeder::class);
            $this->seeded = true;
        }
        $user = User::factory()->create();
        $user->givePermissionTo('manage-technician');
        $user->givePermissionTo('view-technician');

        return $user;
    }

    /** User yang benar-benar memegang role teknisi. */
    private function technician(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole('technician');

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Instalasi CCTV Tim',
            'date' => '2026-08-12',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'location' => 'Klaten',
            'status' => 'scheduled',
        ], $overrides);
    }

    public function test_schedule_can_have_multiple_technicians(): void
    {
        $user = $this->userWithPerms();
        $budi = $this->technician('Budi');
        $ani = $this->technician('Ani');
        $dedy = $this->technician('Dedy');

        $this->actingAs($user)
            ->post(route('teknisi.schedules.store'), $this->payload([
                'technician_ids' => [$budi->id, $ani->id],
            ]))
            ->assertSessionHasNoErrors();

        $schedule = TechnicianSchedule::firstOrFail();
        $this->assertSame(
            [$budi->id, $ani->id],
            $schedule->technicians()->pluck('users.id')->map(fn ($id) => (int) $id)->sort()->values()->all() ?: [],
            'Dua teknisi tersimpan di pivot'
        );
        // Kolom lama terisi teknisi pertama (kompatibilitas).
        $this->assertNotNull($schedule->technician_user_id);

        // Tambah satu teknisi lagi lewat update.
        $this->actingAs($user)
            ->put(route('teknisi.schedules.update', $schedule), $this->payload([
                'technician_ids' => [$budi->id, $ani->id, $dedy->id],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertCount(3, $schedule->fresh()->technicians);
    }

    public function test_all_assigned_technicians_are_notified(): void
    {
        Notification::fake();
        $user = $this->userWithPerms();
        $budi = $this->technician('Budi');
        $ani = $this->technician('Ani');

        $this->actingAs($user)
            ->post(route('teknisi.schedules.store'), $this->payload([
                'technician_ids' => [$budi->id, $ani->id],
            ]))
            ->assertSessionHasNoErrors();

        // Semua teknisi yang ditugaskan menerima notifikasi assign.
        Notification::assertSentTo($budi, TechnicianScheduleAssignedNotification::class);
        Notification::assertSentTo($ani, TechnicianScheduleAssignedNotification::class);
    }

    public function test_removed_technician_gets_change_notification(): void
    {
        Notification::fake();
        $user = $this->userWithPerms();
        $budi = $this->technician('Budi');
        $ani = $this->technician('Ani');

        $this->actingAs($user)
            ->post(route('teknisi.schedules.store'), $this->payload([
                'technician_ids' => [$budi->id, $ani->id],
            ]))
            ->assertSessionHasNoErrors();

        $schedule = TechnicianSchedule::firstOrFail();
        Notification::fake();

        // Ani dihapus dari jadwal.
        $this->actingAs($user)
            ->put(route('teknisi.schedules.update', $schedule), $this->payload([
                'technician_ids' => [$budi->id],
            ]))
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($ani, TechnicianScheduleChangedNotification::class);
    }

    public function test_legacy_single_technician_schedule_still_works(): void
    {
        $user = $this->userWithPerms();
        $budi = $this->technician('Budi');

        // Data lama / pemanggil lama: technician_user_id tunggal.
        $this->actingAs($user)
            ->post(route('teknisi.schedules.store'), $this->payload([
                'technician_user_id' => $budi->id,
            ]))
            ->assertSessionHasNoErrors();

        $schedule = TechnicianSchedule::firstOrFail();
        $this->assertSame([(int) $budi->id],
            $schedule->technicians()->pluck('users.id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame($budi->name, $schedule->technicianList()->first()->name);

        // API kalender menampilkan namanya.
        $this->actingAs($user)
            ->get(route('teknisi.kalender.events', [
                'start' => '2026-08-01T00:00:00+07:00',
                'end' => '2026-09-01T00:00:00+07:00',
            ]))
            ->assertOk()
            ->assertJsonPath('0.technician', 'Budi');
    }

    public function test_calendar_events_join_multiple_technician_names(): void
    {
        $user = $this->userWithPerms();
        $budi = $this->technician('Budi');
        $ani = $this->technician('Ani');

        $this->actingAs($user)
            ->post(route('teknisi.schedules.store'), $this->payload([
                'technician_ids' => [$budi->id, $ani->id],
            ]))
            ->assertSessionHasNoErrors();

        $events = $this->actingAs($user)
            ->get(route('teknisi.kalender.events', [
                'start' => '2026-08-01T00:00:00+07:00',
                'end' => '2026-09-01T00:00:00+07:00',
            ]))
            ->assertOk()
            ->json();

        $names = collect($events[0]['technicians'])->pluck('name')->all();
        $this->assertContains('Budi', $names);
        $this->assertContains('Ani', $names);
    }

    public function test_non_technician_pivot_member_does_not_receive_change_notification(): void
    {
        Notification::fake();
        $user = $this->userWithPerms();
        $budi = $this->technician('Budi');

        $anggie = User::factory()->create(['name' => 'Anggie']);
        $anggie->assignRole('marketing');

        $this->actingAs($user)
            ->post(route('teknisi.schedules.store'), $this->payload([
                'technician_ids' => [$budi->id],
            ]))
            ->assertSessionHasNoErrors();

        $schedule = TechnicianSchedule::firstOrFail();

        // Simulasikan data lama yang salah: marketing masuk pivot.
        $schedule->technicians()->attach($anggie->id);
        Notification::fake();

        $this->actingAs($user)
            ->put(route('teknisi.schedules.update', $schedule), $this->payload([
                'technician_ids' => [$budi->id],
            ]))
            ->assertSessionHasNoErrors();

        Notification::assertNotSentTo($anggie, TechnicianScheduleChangedNotification::class);
        Notification::assertSentTo($budi, TechnicianScheduleChangedNotification::class);
    }

    public function test_non_technician_cannot_be_assigned_to_schedule(): void
    {
        $user = $this->userWithPerms();

        $anggie = User::factory()->create(['name' => 'Anggie']);
        $anggie->assignRole('marketing');

        $this->actingAs($user)
            ->post(route('teknisi.schedules.store'), $this->payload([
                'technician_ids' => [$anggie->id],
            ]))
            ->assertSessionHasErrors('technician_ids.0');

        $this->assertDatabaseCount('technician_schedules', 0);
    }

    public function test_schedule_notification_respects_system_preference(): void
    {
        $user = $this->userWithPerms();
        $budi = $this->technician('Budi');
        $budi->update(['preferences' => [
            'notify_system' => false,
            'notify_email' => false,
            'notify_push' => false,
        ]]);

        $schedule = new TechnicianSchedule;
        $schedule->title = 'Instalasi';
        $schedule->status = 'scheduled';

        $notification = new TechnicianScheduleChangedNotification($schedule);

        $this->assertSame([], $notification->via($budi->fresh()));
    }

    public function test_super_admin_owner_still_receives_change_notification(): void
    {
        Notification::fake();
        $user = $this->userWithPerms();
        $budi = $this->technician('Budi');

        $owner = User::factory()->create(['name' => 'Yeski']);
        $owner->assignRole('super-admin');

        $this->actingAs($user)
            ->post(route('teknisi.schedules.store'), $this->payload([
                'technician_ids' => [$budi->id],
            ]))
            ->assertSessionHasNoErrors();

        $schedule = TechnicianSchedule::firstOrFail();
        $schedule->update(['user_id' => $owner->id]);
        Notification::fake();

        $this->actingAs($user)
            ->put(route('teknisi.schedules.update', $schedule), $this->payload([
                'technician_ids' => [$budi->id],
            ]))
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($owner, TechnicianScheduleChangedNotification::class);
    }
}
