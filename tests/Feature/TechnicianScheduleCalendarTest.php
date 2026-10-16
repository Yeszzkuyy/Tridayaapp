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

    public function test_sync_deletes_local_when_removed_from_google(): void
    {
        $lead = $this->userWithRole('lead-technician');

        // Jadwal lokal yang BER-google_event_id (akan dihapus oleh Google) + yang lokal murni.
        $fromGoogle = TechnicianSchedule::create([
            'user_id' => $lead->id, 'title' => 'From Google', 'start_at' => '2026-10-20 09:00:00',
            'end_at' => '2026-10-20 11:00:00', 'status' => 'scheduled',
            'google_event_id' => 'gcal-123', 'google_sync_status' => 'synced',
        ]);
        $localOnly = TechnicianSchedule::create([
            'user_id' => $lead->id, 'title' => 'Local only', 'start_at' => '2026-10-20 09:00:00',
            'end_at' => '2026-10-20 11:00:00', 'status' => 'scheduled',
        ]);

        // Google sekarang TIDAK punya gcal-123 (sudah dihapus user di Google).
        \Illuminate\Support\Facades\Http::fake([
            'www.googleapis.com/*' => \Illuminate\Support\Facades\Http::response(['items' => []], 200),
        ]);

        app(\App\Services\GoogleCalendarService::class)->syncFromGoogle();

        // Yang dari Google → soft-delete; yang lokal murni → tetap aman.
        $this->assertSoftDeleted('technician_schedules', ['id' => $fromGoogle->id]);
        $this->assertDatabaseHas('technician_schedules', ['id' => $localOnly->id, 'deleted_at' => null]);
    }

    public function test_sync_newest_wins_and_preserves_app_fields(): void
    {
        $lead = $this->userWithRole('lead-technician');

        $schedule = TechnicianSchedule::create([
            'user_id' => $lead->id, 'title' => 'Judul lama', 'start_at' => '2026-10-20 09:00:00',
            'end_at' => '2026-10-20 11:00:00', 'status' => 'completed',
            'technician_user_id' => $lead->id, 'google_event_id' => 'gcal-999', 'google_sync_status' => 'synced',
        ]);
        // Buat updated_at lokal JAUH di masa depan → lokal lebih baru dari Google.
        \Illuminate\Support\Facades\DB::table('technician_schedules')->where('id', $schedule->id)
            ->update(['updated_at' => now()->addDay()]);

        \Illuminate\Support\Facades\Http::fake([
            'www.googleapis.com/*' => \Illuminate\Support\Facades\Http::response([
                'items' => [[
                    'id' => 'gcal-999', 'summary' => 'Judul dari Google',
                    'start' => ['dateTime' => '2026-10-20T09:00:00'],
                    'end' => ['dateTime' => '2026-10-20T11:00:00'],
                    'updated' => now()->subDay()->toIso8601String(),
                ]],
            ], 200),
        ]);

        app(\App\Services\GoogleCalendarService::class)->syncFromGoogle();

        // Google lebih lama → lokal TIDAK ditimpa; status/teknisi tetap.
        $fresh = $schedule->fresh();
        $this->assertSame('Judul lama', $fresh->title);
        $this->assertSame('completed', $fresh->status);
        $this->assertSame($lead->id, $fresh->technician_user_id);
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

    public function test_create_schedule_with_status_key_succeeds(): void
    {
        $lead = $this->userWithRole('lead-technician');

        // Form mengirim KEY status (scheduled/dst), bukan label — harus lolos validasi.
        foreach (array_keys(\App\Models\TechnicianSchedule::STATUSES) as $status) {
            $this->actingAs($lead)->post(route('teknisi.schedules.store'), [
                'title' => 'Status '.$status, 'date' => '2026-10-25',
                'start_time' => '09:00', 'end_time' => '11:00', 'status' => $status,
            ])->assertSessionHasNoErrors();
        }

        // Label (Scheduled) harus DITOLAK — ini penyebab bug lama.
        $this->actingAs($lead)->post(route('teknisi.schedules.store'), [
            'title' => 'Salah', 'date' => '2026-10-25',
            'start_time' => '09:00', 'end_time' => '11:00', 'status' => 'Scheduled',
        ])->assertSessionHasErrors('status');
    }

    public function test_end_time_optional_and_create_succeeds(): void
    {
        $lead = $this->userWithRole('lead-technician');

        $this->actingAs($lead)->post(route('teknisi.schedules.store'), [
            'title' => 'Tanpa jam selesai', 'date' => '2026-10-25',
            'start_time' => '09:00', 'status' => 'scheduled',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('technician_schedules', ['title' => 'Tanpa jam selesai']);
    }

    public function test_mark_as_done_is_removed(): void
    {
        $lead = $this->userWithRole('lead-technician');
        $schedule = $this->makeSchedule($lead);

        // Tombol "Mark as done" sudah dihapus dari jadwal teknisi (tidak
        // dipakai) — route-nya juga tidak ada lagi.
        $html = $this->actingAs($lead)->get(route('teknisi.jadwal'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Mark as done', $html);
        $this->assertStringNotContainsString('completeSchedule', $html);

        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('teknisi.schedules.complete'),
            'Route tandai selesai tidak boleh terdaftar lagi.'
        );

        // Status completed tetap ada (data lama aman) dan bisa diubah
        // lewat form edit — yang dihapus cuma tombol pintasannya.
        $this->assertArrayHasKey('completed', TechnicianSchedule::STATUSES);
    }

    public function test_lead_technician_can_edit_teammate_schedule(): void
    {
        $lead = $this->userWithRole('lead-technician');
        $tech = $this->userWithRole('technician');
        $schedule = $this->makeSchedule($tech); // dibuat teknisi, bukan lead

        // Lead teknisi boleh merevisi jadwal tim.
        $this->actingAs($lead)
            ->put(route('teknisi.schedules.update', $schedule), [
                'title' => 'Instalasi server (revisi)',
                'date' => '2026-10-20',
                'start_time' => '10:00',
                'status' => 'scheduled',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Instalasi server (revisi)', $schedule->fresh()->title);
    }

    public function test_technician_can_edit_teammate_schedule(): void
    {
        $owner = $this->userWithRole('technician');
        $teammate = $this->userWithRole('technician');
        $schedule = $this->makeSchedule($owner); // dibuat teknisi lain

        // Sesama jajaran teknisi boleh merevisi jadwal tim (tombol Edit
        // tampil untuk semua teknisi — backend harus konsisten).
        $this->actingAs($teammate)
            ->put(route('teknisi.schedules.update', $schedule), [
                'title' => 'Instalasi server (revisi)',
                'date' => '2026-10-20',
                'start_time' => '10:00',
                'status' => 'scheduled',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Instalasi server (revisi)', $schedule->fresh()->title);
    }

    public function test_user_without_technician_permission_gets_clear_message(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('technician');
        $outsider = User::factory()->create();
        $outsider->assignRole('marketing'); // tanpa manage-technician
        $schedule = $this->makeSchedule($owner);

        // Non-teknisi ditolak dengan pesan yang jelas, bukan error generik.
        $this->actingAs($outsider)
            ->putJson(route('teknisi.schedules.update', $schedule), [
                'title' => 'Dibajak',
                'date' => '2026-10-20',
                'start_time' => '10:00',
                'status' => 'scheduled',
            ])
            ->assertForbidden();

        $this->assertSame('Instalasi server', $schedule->fresh()->title);
    }

    public function test_index_renders_calendar_views(): void
    {
        $tech = $this->userWithRole('technician');

        $res = $this->actingAs($tech)->get(route('teknisi.jadwal'));
        $res->assertOk();
        $res->assertSee('id="teknisi-month-grid"', false);
        $res->assertSee('id="teknisi-view-segment"', false);
        $res->assertSee('id="teknisi-week-list"', false);
        $res->assertSee('openMore(cell.date', false);
        $res->assertSee('Weekly Schedule');
    }
}
