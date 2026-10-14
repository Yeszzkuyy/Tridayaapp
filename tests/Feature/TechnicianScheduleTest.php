<?php

namespace Tests\Feature;

use App\Models\TechnicianSchedule;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnicianScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function teknisiUser(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo('manage-technician');
        $user->givePermissionTo('view-technician');

        return $user;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Instalasi CCTV PT ABC',
            'project_id' => null,
            'technician_user_id' => null,
            'date' => '2026-08-12',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'location' => 'Klaten',
            'description' => 'Instalasi 8 kamera CCTV',
            'status' => 'scheduled',
            'reminder_minutes' => '30',
        ], $overrides);
    }

    public function test_manage_user_can_create_update_and_delete_schedule(): void
    {
        $user = $this->teknisiUser();

        $this->actingAs($user)
            ->post(route('teknisi.schedules.store'), $this->validPayload())
            ->assertSessionHasNoErrors();

        $schedule = TechnicianSchedule::firstOrFail();
        $this->assertDatabaseHas('technician_schedules', [
            'id' => $schedule->id,
            'title' => 'Instalasi CCTV PT ABC',
            'status' => 'scheduled',
            'google_sync_status' => 'not_connected',
            'google_event_id' => null,
        ]);
        $this->assertSame('09:00', $schedule->start_at->setTimezone('Asia/Jakarta')->format('H:i'));

        $this->actingAs($user)
            ->put(route('teknisi.schedules.update', $schedule), $this->validPayload([
                'title' => 'Instalasi CCTV PT XYZ',
                'start_time' => '10:00',
                'end_time' => '13:00',
            ]))
            ->assertSessionHasNoErrors();

        $schedule->refresh();
        $this->assertSame('Instalasi CCTV PT XYZ', $schedule->title);
        $this->assertSame('10:00', $schedule->start_at->setTimezone('Asia/Jakarta')->format('H:i'));

        $this->actingAs($user)
            ->delete(route('teknisi.schedules.destroy', $schedule))
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('technician_schedules', ['id' => $schedule->id]);
    }

    public function test_validation_rejects_end_time_before_start_time(): void
    {
        $user = $this->teknisiUser();

        $this->actingAs($user)
            ->post(route('teknisi.schedules.store'), $this->validPayload([
                'start_time' => '12:00',
                'end_time' => '09:00',
            ]))
            ->assertSessionHasErrors('end_time');

        $this->assertDatabaseCount('technician_schedules', 0);
    }

    public function test_calendar_and_schedule_pages_render(): void
    {
        $user = $this->teknisiUser();
        $this->actingAs($user)->post(route('teknisi.schedules.store'), $this->validPayload());

        $this->actingAs($user)
            ->get(route('teknisi.jadwal'))
            ->assertOk()
            ->assertSee(__('Technician Schedule'))
            ->assertSee('Google Calendar')
            ->assertDontSee('filter-teknisi');
    }

    public function test_calendar_events_endpoint_returns_all_technicians_with_customer_info(): void
    {
        $user = $this->teknisiUser();

        $customer = \App\Models\Customer::create(['name' => 'PT ABC']);
        $workType = \App\Models\WorkType::create(['name' => 'Instalasi']);
        $this->actingAs($user);
        $project = \App\Models\Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'pic_engineer' => 'Engineer A',
            'project_name' => 'Instalasi CCTV',
        ]);

        $naufal = User::factory()->create(['name' => 'Naufal']);
        $budi = User::factory()->create(['name' => 'Budi']);

        $this->actingAs($user)
            ->post(route('teknisi.schedules.store'), $this->validPayload([
                'project_id' => $project->id,
                'technician_user_id' => $naufal->id,
                'title' => 'Instalasi CCTV PT ABC',
            ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('teknisi.schedules.store'), $this->validPayload([
                'project_id' => $project->id,
                'technician_user_id' => $budi->id,
                'title' => 'Maintenance Server PT XYZ',
                'date' => '2026-08-13',
            ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('teknisi.kalender.events', [
                'start' => '2026-08-01T00:00:00+07:00',
                'end' => '2026-09-01T00:00:00+07:00',
            ]))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.customer', 'PT ABC')
            ->assertJsonPath('0.project', 'Instalasi CCTV')
            ->assertJsonPath('0.technician', 'Naufal')
            ->assertJsonPath('0.status', 'scheduled')
            ->assertJsonPath('1.technician', 'Budi');

        // Technician filter is ignored: all technicians always returned.
        $this->actingAs($user)
            ->get(route('teknisi.kalender.events', [
                'start' => '2026-08-01T00:00:00+07:00',
                'end' => '2026-09-01T00:00:00+07:00',
                'technician_id' => $naufal->id,
            ]))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('1.technician', 'Budi');
    }

    public function test_user_without_permission_cannot_manage_schedule(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('teknisi.schedules.store'), $this->validPayload())
            ->assertForbidden();
    }
}
