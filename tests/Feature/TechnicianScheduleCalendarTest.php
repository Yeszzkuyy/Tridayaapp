<?php

namespace Tests\Feature;

use App\Models\GoogleAppCredential;
use App\Models\Project;
use App\Models\TechnicianSchedule;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnicianScheduleCalendarTest extends TestCase
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
            'start_at' => '2026-10-20 09:00:00',
            'end_at' => '2026-10-20 11:00:00',
            'status' => 'scheduled',
        ], $overrides));
    }

    public function test_api_returns_jobs_for_day_and_range(): void
    {
        $tech = $this->userWithRole('technician');
        $this->makeSchedule($tech);
        $this->makeSchedule($tech, ['start_at' => '2026-10-21 09:00:00', 'end_at' => '2026-10-21 11:00:00', 'title' => 'Hari lain']);

        $res = $this->actingAs($tech)->getJson(route('teknisi.schedules.api', ['date' => '2026-10-20']));
        $res->assertOk();
        $this->assertSame(['Instalasi server'], collect($res->json())->pluck('title')->all());
        $this->assertSame('09:00', $res->json()[0]['start_time']);

        $res = $this->actingAs($tech)->getJson(route('teknisi.schedules.api', ['start' => '2026-10-19', 'end' => '2026-10-22']));
        $res->assertOk();
        $this->assertCount(2, $res->json());

        $this->actingAs($tech)->getJson(route('teknisi.schedules.api', ['date' => 'bukan']))
            ->assertStatus(422);
    }

    public function test_credentials_saved_and_gated_to_super_admin_and_lead_technician(): void
    {
        $lead = $this->userWithRole('lead-technician');
        $super = $this->userWithRole('super-admin');
        $tech = $this->userWithRole('technician');

        $payload = [
            'client_id' => 'cid', 'client_secret' => 'secret',
            'api_key' => 'key', 'calendar_id' => 'cal@group',
        ];

        // lead-technician & super-admin boleh simpan
        $this->actingAs($lead)->post(route('teknisi.kalender.credentials'), $payload)
            ->assertRedirect();
        $this->assertDatabaseHas('google_app_credentials', ['client_id' => 'cid', 'calendar_id' => 'cal@group']);

        $this->actingAs($super)->post(route('teknisi.kalender.credentials'), ['client_id' => 'cid2'])
            ->assertRedirect();

        // teknisi biasa ditolak
        $this->actingAs($tech)->post(route('teknisi.kalender.credentials'), ['client_id' => 'x'])
            ->assertForbidden();
    }

    public function test_sync_gated_and_buttons_hidden_for_non_admin(): void
    {
        $lead = $this->userWithRole('lead-technician');
        $tech = $this->userWithRole('technician');

        $this->actingAs($tech)->post(route('teknisi.kalender.sync'))->assertForbidden();

        // tombol API hanya render untuk yang berhak
        $res = $this->actingAs($lead)->get(route('teknisi.jadwal'));
        $res->assertOk();
        $res->assertSee('Google Calendar API');

        $res = $this->actingAs($tech)->get(route('teknisi.jadwal'));
        $res->assertOk();
        $res->assertDontSee('Google Calendar API');
    }

    public function test_sync_failure_returns_message_not_500(): void
    {
        $lead = $this->userWithRole('lead-technician');

        // API key kosong → upcomingEvents() melempar, harus ditangkap controller.
        $res = $this->actingAs($lead)->post(route('teknisi.kalender.sync'), [
            'api_key' => 'salah', 'calendar_id' => 'bukan@kalender.ada',
        ]);

        $res->assertRedirect();
        $res->assertSessionHas('sync_message');
    }

    public function test_google_status_endpoint_and_badge(): void
    {
        $lead = $this->userWithRole('lead-technician');

        $res = $this->actingAs($lead)->getJson(route('teknisi.kalender.status'));
        $res->assertOk()->assertJsonStructure(['connected']);

        // badge realtime: punya state Alpine + teks Connected/Disconnected
        $res = $this->actingAs($lead)->get(route('teknisi.jadwal'));
        $res->assertSee('googleConnected', false);
        $res->assertSee('Disconnected');
    }

    public function test_store_update_delete_return_json_for_ajax(): void
    {
        $lead = $this->userWithRole('lead-technician');
        $schedule = $this->makeSchedule($lead);

        $res = $this->actingAs($lead)->postJson(route('teknisi.schedules.store'), [
            'title' => 'Ajax create', 'date' => '2026-10-25',
            'start_time' => '09:00', 'end_time' => '11:00', 'status' => 'scheduled',
        ]);
        $res->assertOk()->assertJson(['ok' => true]);

        $res = $this->actingAs($lead)->putJson(route('teknisi.schedules.update', $schedule), [
            'title' => 'Ajax update', 'date' => '2026-10-20',
            'start_time' => '09:00', 'end_time' => '11:00', 'status' => 'scheduled',
        ]);
        $res->assertOk()->assertJson(['ok' => true]);

        $res = $this->actingAs($lead)->deleteJson(route('teknisi.schedules.destroy', $schedule));
        $res->assertOk()->assertJson(['ok' => true]);

        // validasi gagal → 422 JSON, bukan redirect
        $this->actingAs($lead)->postJson(route('teknisi.schedules.store'), ['title' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_gcal_sync_command_runs_and_is_safe(): void
    {
        // Command harus exit sukses walau Google gagal (tidak boleh melempar).
        $this->artisan('gcal:sync')->assertExitCode(0);
    }

    public function test_fetch_dispatches_auto_sync_job_throttled(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $tech = $this->userWithRole('technician');

        $this->actingAs($tech)->getJson(route('teknisi.schedules.api', ['date' => '2026-10-20']));
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SyncGoogleCalendarJob::class);

        // Fetch berikutnya dalam throttle window tidak dispatch ulang.
        \Illuminate\Support\Facades\Queue::fake();
        $this->actingAs($tech)->getJson(route('teknisi.schedules.api', ['date' => '2026-10-21']));
        \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\SyncGoogleCalendarJob::class);
    }

    public function test_index_renders_calendar_views(): void
    {
        $tech = $this->userWithRole('technician');

        $res = $this->actingAs($tech)->get(route('teknisi.jadwal'));
        $res->assertOk();
        $res->assertSee('id="teknisi-month-grid"', false);
        $res->assertSee('id="teknisi-view-switcher"', false);
        $res->assertSee('id="teknisi-week-list"', false);
        $res->assertSee('openMore(cell.date', false);
        $res->assertSee('Weekly Schedule');
    }
}
