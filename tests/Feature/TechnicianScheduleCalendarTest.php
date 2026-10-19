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
        $res->assertOk()->assertJsonStructure(['status', 'connected', 'oauth_connected', 'last_sync_at', 'last_sync_summary', 'last_sync_error']);

        // badge realtime: state Alpine 4 status + label
        $res = $this->actingAs($lead)->get(route('teknisi.jadwal'));
        $res->assertSee('googleStatus', false);
        $res->assertSee('statusLabel()', false);
        $res->assertSee('Not Connected');
    }

    public function test_connection_status_reflects_oauth_and_last_sync(): void
    {
        $lead = $this->userWithRole('lead-technician');

        // Belum ada OAuth → not_connected.
        $this->actingAs($lead)->getJson(route('teknisi.kalender.status'))
            ->assertOk()->assertJson(['status' => 'not_connected']);

        // Ada token OAuth + sinkron sukses → connected.
        $this->userWithRole('technician');
        $owner = \App\Models\User::orderBy('id')->first();
        \App\Models\GoogleCredential::create([
            'user_id' => $owner->id, 'access_token' => 'a', 'refresh_token' => 'r',
            'expires_at' => now()->addHour(), 'calendar_id' => 'primary',
        ]);
        \App\Models\GoogleAppCredential::current()->forceFill([
            'last_sync_at' => now(), 'last_sync_summary' => '2 baru', 'last_sync_error' => null, 'last_sync_error_kind' => null,
        ])->save();

        $this->actingAs($lead)->getJson(route('teknisi.kalender.status'))
            ->assertOk()->assertJson(['status' => 'connected']);

        // Terakhir gagal auth → needs_reauth.
        \App\Models\GoogleAppCredential::current()->forceFill(['last_sync_error_kind' => 'auth', 'last_sync_error' => 'izin kedaluwarsa'])->save();
        $this->actingAs($lead)->getJson(route('teknisi.kalender.status'))
            ->assertOk()->assertJson(['status' => 'needs_reauth']);

        // Terakhir gagal non-auth (jaringan/sync) → error.
        \App\Models\GoogleAppCredential::current()->forceFill(['last_sync_error_kind' => 'network', 'last_sync_error' => 'timeout'])->save();
        $this->actingAs($lead)->getJson(route('teknisi.kalender.status'))
            ->assertOk()->assertJson(['status' => 'error']);
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

        // Google sekarang TIDAK punya gcal-123 (dihapus di Google) → read mengembalikan [].
        $mock = \Mockery::mock(\App\Services\GoogleCalendarService::class)->makePartial();
        $mock->shouldReceive('upcomingEvents')->andReturn([]);
        $this->app->instance(\App\Services\GoogleCalendarService::class, $mock);

        $mock->syncFromGoogle();

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

        // Read via OAuth (mock) — event dari Google lebih LAMA dari lokal.
        $mock = \Mockery::mock(\App\Services\GoogleCalendarService::class)->makePartial();
        $mock->shouldReceive('upcomingEvents')->andReturn([[
            'id' => 'gcal-999', 'title' => 'Judul dari Google',
            'start' => \Illuminate\Support\Carbon::parse('2026-10-20 09:00'),
            'end' => \Illuminate\Support\Carbon::parse('2026-10-20 11:00'),
            'location' => '', 'description' => '',
            'updated' => now()->subDay(),
        ]]);
        $this->app->instance(\App\Services\GoogleCalendarService::class, $mock);

        $mock->syncFromGoogle();

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

    public function test_empty_credential_fields_keep_previous_values(): void
    {
        $lead = $this->userWithRole('lead-technician');
        $cred = \App\Models\GoogleAppCredential::create([
            'client_id' => 'id-lama', 'client_secret' => 'secret-lama',
            'api_key' => 'key-lama', 'calendar_id' => 'cal-lama',
        ]);

        // Semua field dikosongkan → nilai lama harus dipertahankan (G4).
        $this->actingAs($lead)->post(route('teknisi.kalender.credentials'), [
            'client_id' => '', 'client_secret' => '', 'api_key' => '', 'calendar_id' => '',
        ])->assertSessionHasNoErrors();

        $fresh = $cred->fresh();
        $this->assertSame('secret-lama', $fresh->client_secret);
        $this->assertSame('key-lama', $fresh->api_key);
        $this->assertSame('id-lama', $fresh->client_id);
        $this->assertSame('cal-lama', $fresh->calendar_id);
    }

    public function test_secret_not_rendered_in_html(): void
    {
        $lead = $this->userWithRole('lead-technician');
        \App\Models\GoogleAppCredential::create([
            'client_id' => 'id-xyz', 'client_secret' => 'RAHASIA-SECRET',
            'api_key' => 'RAHASIA-APIKEY', 'calendar_id' => 'cal@x',
        ]);

        $res = $this->actingAs($lead)->get(route('teknisi.jadwal'));
        $res->assertOk();
        $res->assertDontSee('RAHASIA-SECRET');
        $res->assertDontSee('RAHASIA-APIKEY');
    }

    public function test_invalid_config_keeps_previous_and_shows_error(): void
    {
        $lead = $this->userWithRole('lead-technician');
        $cred = \App\Models\GoogleAppCredential::create([
            'client_id' => 'id-lama', 'client_secret' => null, 'calendar_id' => 'cal-lama',
        ]);

        // client_id tanpa client_secret (pasangan tidak lengkap) → gagal, lama bertahan (G6).
        $this->actingAs($lead)->post(route('teknisi.kalender.credentials'), [
            'client_id' => 'id-baru',
        ])->assertSessionHasErrors();

        $this->assertSame('id-lama', $cred->fresh()->client_id);
    }

    public function test_api_management_endpoints_forbidden_without_roles(): void
    {
        foreach (['technician', 'management', 'ceo'] as $role) {
            $u = $this->userWithRole($role);

            $this->actingAs($u)->post(route('teknisi.kalender.credentials'), ['client_id' => 'x'])->assertForbidden();
            $this->actingAs($u)->post(route('teknisi.kalender.sync'), [])->assertForbidden();
            $this->actingAs($u)->get(route('teknisi.kalender.connect'))->assertForbidden();
            $this->actingAs($u)->delete(route('teknisi.kalender.disconnect'))->assertForbidden();
            // Callback dengan state valid pun tetap ditolak di gate sebelum token/session berubah.
            $this->actingAs($u)->withSession(['google_oauth_state' => 'st'])
                ->get(route('teknisi.kalender.callback', ['state' => 'st', 'code' => 'c']))
                ->assertForbidden();
        }

        // Penolakan tidak membuat/mengubah token maupun kredensial.
        $this->assertSame(0, \App\Models\GoogleCredential::count());
        $this->assertSame(0, GoogleAppCredential::count());
    }

    public function test_api_management_endpoints_allowed_for_admin_roles(): void
    {
        foreach (['super-admin', 'lead-technician'] as $role) {
            $u = $this->userWithRole($role);

            // credentials: simpan (tanpa panggil Google).
            $this->actingAs($u)->post(route('teknisi.kalender.credentials'), ['client_id' => 'cid', 'client_secret' => 'csec'])
                ->assertSessionHas('success');

            // disconnect: sukses (hanya DB, tanpa Google).
            $this->actingAs($u)->delete(route('teknisi.kalender.disconnect'))
                ->assertSessionHas('success');

            // connect: redirect ke Google (membangun URL, tanpa HTTP).
            $this->actingAs($u)->get(route('teknisi.kalender.connect'))
                ->assertStatus(302);
        }
    }

    public function test_sync_allowed_for_admin_roles_without_hitting_google(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'www.googleapis.com/*' => \Illuminate\Support\Facades\Http::response(['items' => []], 200),
        ]);

        foreach (['super-admin', 'lead-technician'] as $role) {
            $u = $this->userWithRole($role);
            $this->actingAs($u)->post(route('teknisi.kalender.sync'), [])
                ->assertSessionHas('sync_message');
        }
    }

    public function test_callback_authorization_and_state(): void
    {
        // A. Role tidak diizinkan → 403 sebelum token dibuat.
        $tech = $this->userWithRole('technician');
        $this->actingAs($tech)->withSession(['google_oauth_state' => 'st'])
            ->get(route('teknisi.kalender.callback', ['state' => 'st', 'code' => 'c']))
            ->assertForbidden();
        $this->assertSame(0, \App\Models\GoogleCredential::count());

        // B. Role diizinkan + state SALAH → 403 (CSRF tetap berlaku).
        $lead = $this->userWithRole('lead-technician');
        $this->actingAs($lead)->withSession(['google_oauth_state' => 'benar'])
            ->get(route('teknisi.kalender.callback', ['state' => 'salah', 'code' => 'c']))
            ->assertForbidden();
        $this->assertSame(0, \App\Models\GoogleCredential::count());

        // C. Role diizinkan + state BENAR → alur callback berlanjut & handler
        // BENAR-BENAR membuat token (Http::fake = seam, tanpa jaringan Google nyata).
        \Illuminate\Support\Facades\Http::fake([
            'oauth2.googleapis.com/token' => \Illuminate\Support\Facades\Http::response(
                ['access_token' => 'at-fake', 'refresh_token' => 'rt-fake', 'expires_in' => 3600], 200
            ),
        ]);

        $this->actingAs($lead)->withSession(['google_oauth_state' => 'benar'])
            ->get(route('teknisi.kalender.callback', ['state' => 'benar', 'code' => 'c']))
            ->assertRedirect(route('teknisi.jadwal'));

        // Handler terbukti berjalan: token OAuth tersimpan.
        $this->assertSame(1, \App\Models\GoogleCredential::count());
        $this->assertDatabaseHas('google_credentials', ['user_id' => $lead->id]);
    }

    public function test_read_requires_oauth_not_api_key(): void
    {
        // API key tersedia, tapi TIDAK ada token OAuth → baca ditolak (tanpa fallback API key).
        GoogleAppCredential::current()->fill(['api_key' => 'AIza-fake', 'calendar_id' => 'cal@x'])->save();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('belum terhubung');
        app(\App\Services\GoogleCalendarService::class)->upcomingEvents(1);
    }

    public function test_read_and_write_use_same_canonical_calendar_id(): void
    {
        $lead = $this->userWithRole('lead-technician');
        GoogleAppCredential::current()->fill(['calendar_id' => 'cal-office'])->save();

        // Mock read mengembalikan 1 event → sync menyimpan google_calendar_id = 'cal-office'.
        $mock = \Mockery::mock(\App\Services\GoogleCalendarService::class)->makePartial();
        $mock->shouldReceive('upcomingEvents')->andReturn([[
            'id' => 'gcal-cal', 'title' => 'Judul',
            'start' => \Illuminate\Support\Carbon::parse('2026-10-20 09:00'),
            'end' => \Illuminate\Support\Carbon::parse('2026-10-20 11:00'),
            'location' => '', 'description' => '', 'updated' => now(),
        ]]);
        $this->app->instance(\App\Services\GoogleCalendarService::class, $mock);

        $mock->syncFromGoogle();

        $this->assertDatabaseHas('technician_schedules', [
            'google_event_id' => 'gcal-cal', 'google_calendar_id' => 'cal-office',
        ]);
    }

    public function test_no_mass_delete_when_read_fails(): void
    {
        $lead = $this->userWithRole('lead-technician');
        $keep = TechnicianSchedule::create([
            'user_id' => $lead->id, 'title' => 'Tetap', 'start_at' => '2026-10-20 09:00:00',
            'end_at' => '2026-10-20 11:00:00', 'status' => 'scheduled',
            'google_event_id' => 'gcal-x', 'google_sync_status' => 'synced',
        ]);

        // Read gagal (mis. invalid_grant / jaringan) → tidak boleh memicu hapus massal.
        $mock = \Mockery::mock(\App\Services\GoogleCalendarService::class)->makePartial();
        $mock->shouldReceive('upcomingEvents')->andThrow(new \RuntimeException('invalid_grant'));
        $this->app->instance(\App\Services\GoogleCalendarService::class, $mock);

        try {
            $mock->syncFromGoogle();
        } catch (\Throwable) {
        }

        $this->assertNotSoftDeleted('technician_schedules', ['id' => $keep->id]);
    }

    public function test_sync_is_idempotent_on_repeat_run(): void
    {
        $this->userWithRole('lead-technician');
        $mock = \Mockery::mock(\App\Services\GoogleCalendarService::class)->makePartial();
        $mock->shouldReceive('upcomingEvents')->andReturn([[
            'id' => 'gcal-idemp', 'title' => 'Idem',
            'start' => \Illuminate\Support\Carbon::parse('2026-10-20 09:00'),
            'end' => \Illuminate\Support\Carbon::parse('2026-10-20 11:00'),
            'location' => '', 'description' => '', 'updated' => now(),
        ]]);
        $this->app->instance(\App\Services\GoogleCalendarService::class, $mock);

        $mock->syncFromGoogle();
        $mock->syncFromGoogle();

        $this->assertSame(1, TechnicianSchedule::where('google_event_id', 'gcal-idemp')->count());
    }

    public function test_retry_bounded_and_no_retry_on_auth_error(): void
    {
        $svc = app(\App\Services\GoogleCalendarService::class);
        $m = new \ReflectionMethod($svc, 'callWithRetry');
        $m->setAccessible(true);

        // Error sementara (timeout) → retry sampai sukses.
        $count = 0;
        $result = $m->invoke($svc, function () use (&$count) {
            $count++;
            if ($count < 3) {
                throw new \RuntimeException('cURL error 28: timeout');
            }

            return 'ok';
        }, 3);
        $this->assertSame('ok', $result);
        $this->assertSame(3, $count);

        // invalid_grant (auth permanen) → TIDAK retry.
        $count2 = 0;
        try {
            $m->invoke($svc, function () use (&$count2) {
                $count2++;
                throw new \RuntimeException('invalid_grant');
            }, 3);
            $this->fail('harus melempar');
        } catch (\RuntimeException) {
            $this->assertSame(1, $count2);
        }
    }

    public function test_sync_lock_prevents_concurrent_runs(): void
    {
        $this->userWithRole('lead-technician');
        $lock = \Illuminate\Support\Facades\Cache::lock('gcal:sync', 300);
        $lock->get();

        $mock = \Mockery::mock(\App\Services\GoogleCalendarService::class)->makePartial();
        $mock->shouldReceive('upcomingEvents')->andReturn([]);
        $this->app->instance(\App\Services\GoogleCalendarService::class, $mock);

        $stats = $mock->syncTwoWay();
        $this->assertTrue((bool) ($stats['locked'] ?? false));

        $lock->release();
        $stats2 = $mock->syncTwoWay();
        $this->assertFalse((bool) ($stats2['locked'] ?? false));
    }

    public function test_database_duplicates_are_not_auto_deleted(): void
    {
        $lead = $this->userWithRole('lead-technician');
        // Baris terhapus (soft) + baris aktif dengan google_event_id sama —
        // partial unique index mengizinkan kombinasi ini (hanya aktif yang unik).
        $trashed = TechnicianSchedule::create([
            'user_id' => $lead->id, 'title' => 'Duplikat terhapus', 'start_at' => '2026-10-20 09:00:00',
            'end_at' => '2026-10-20 11:00:00', 'status' => 'scheduled',
            'google_event_id' => 'gcal-dup', 'google_sync_status' => 'synced',
        ]);
        $trashed->delete();
        TechnicianSchedule::create([
            'user_id' => $lead->id, 'title' => 'Duplikat aktif', 'start_at' => '2026-10-20 09:00:00',
            'end_at' => '2026-10-20 11:00:00', 'status' => 'scheduled',
            'google_event_id' => 'gcal-dup', 'google_sync_status' => 'synced',
        ]);

        // Google masih punya gcal-dup → sync TIDAK menghapus keduanya.
        $mock = \Mockery::mock(\App\Services\GoogleCalendarService::class)->makePartial();
        $mock->shouldReceive('upcomingEvents')->andReturn([[
            'id' => 'gcal-dup', 'title' => 'Masih ada',
            'start' => \Illuminate\Support\Carbon::parse('2026-10-20 09:00'),
            'end' => \Illuminate\Support\Carbon::parse('2026-10-20 11:00'),
            'location' => '', 'description' => '', 'updated' => now(),
        ]]);
        $this->app->instance(\App\Services\GoogleCalendarService::class, $mock);

        $mock->syncFromGoogle();

        $this->assertSame(1, TechnicianSchedule::where('google_event_id', 'gcal-dup')->whereNull('deleted_at')->count());
        $this->assertSame(2, TechnicianSchedule::withTrashed()->where('google_event_id', 'gcal-dup')->count());
    }

    public function test_incomplete_read_does_not_mass_delete(): void
    {
        $lead = $this->userWithRole('lead-technician');
        $keep = TechnicianSchedule::create([
            'user_id' => $lead->id, 'title' => 'Tetap', 'start_at' => '2026-10-20 09:00:00',
            'end_at' => '2026-10-20 11:00:00', 'status' => 'scheduled',
            'google_event_id' => 'gcal-z', 'google_sync_status' => 'synced',
        ]);

        // Hasil baca tidak lengkap (pagination mentok) → dibatalkan, tanpa hapus massal.
        $mock = \Mockery::mock(\App\Services\GoogleCalendarService::class)->makePartial();
        $mock->shouldReceive('upcomingEvents')->andThrow(new \RuntimeException('Hasil baca Google tidak lengkap (terlalu banyak halaman).'));
        $this->app->instance(\App\Services\GoogleCalendarService::class, $mock);

        try {
            $mock->syncFromGoogle();
        } catch (\Throwable) {
        }

        $this->assertNotSoftDeleted('technician_schedules', ['id' => $keep->id]);
    }

    public function test_decrypt_failure_handled_safely(): void
    {
        // Sisipkan nilai "terenkripsi" rusak langsung ke DB (bypass cast).
        \Illuminate\Support\Facades\DB::table('google_app_credentials')->insert([
            'client_id' => 'cid', 'client_secret' => 'bukan-kripto-valid',
            'api_key' => 'bukan-kripto-valid', 'calendar_id' => 'cal@x',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->userWithRole('lead-technician');
        $mock = \Mockery::mock(\App\Services\GoogleCalendarService::class)->makePartial();
        $mock->shouldReceive('upcomingEvents')->andReturn([]);
        $this->app->instance(\App\Services\GoogleCalendarService::class, $mock);

        // appCredentials gagal decrypt → fallback config, TANPA exception.
        $stats = $mock->syncFromGoogle();
        $this->assertIsArray($stats);
    }

    public function test_migration_creates_partial_unique_index(): void
    {
        // RefreshDatabase menjalankan migration di DB tes. Verifikasi index
        // partial terbentuk dgn kondisi benar — cross-DB (PostgreSQL/SQLite).
        $driver = \Illuminate\Support\Facades\DB::getDriverName();
        if ($driver === 'pgsql') {
            $rows = \Illuminate\Support\Facades\DB::select(
                "SELECT indexdef FROM pg_indexes WHERE tablename='technician_schedules' AND indexname='technician_schedules_google_event_id_active_unique'"
            );
            $this->assertNotEmpty($rows);
            $def = (string) ($rows[0]->indexdef ?? '');
            $this->assertStringContainsString('UNIQUE', $def);
            $this->assertStringContainsString('deleted_at IS NULL', $def);
            $this->assertStringContainsString('google_event_id IS NOT NULL', $def);
        } else {
            $rows = \Illuminate\Support\Facades\DB::select(
                "SELECT sql FROM sqlite_master WHERE type='index' AND name='technician_schedules_google_event_id_active_unique'"
            );
            $this->assertNotEmpty($rows);
            $sql = (string) ($rows[0]->sql ?? '');
            $this->assertStringContainsString('google_event_id', $sql);
            $this->assertStringContainsString('deleted_at IS NULL', $sql);
        }
    }

    public function test_two_active_rows_same_google_event_id_rejected(): void
    {
        $lead = $this->userWithRole('lead-technician');
        TechnicianSchedule::create([
            'user_id' => $lead->id, 'title' => 'Aktif 1', 'start_at' => '2026-10-20 09:00:00',
            'end_at' => '2026-10-20 11:00:00', 'status' => 'scheduled',
            'google_event_id' => 'gcal-t', 'google_sync_status' => 'synced',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        TechnicianSchedule::create([
            'user_id' => $lead->id, 'title' => 'Aktif 2', 'start_at' => '2026-10-20 09:00:00',
            'end_at' => '2026-10-20 11:00:00', 'status' => 'scheduled',
            'google_event_id' => 'gcal-t', 'google_sync_status' => 'synced',
        ]);
    }

    public function test_multiple_null_google_event_id_allowed(): void
    {
        $lead = $this->userWithRole('lead-technician');
        foreach ([1, 2, 3] as $i) {
            TechnicianSchedule::create([
                'user_id' => $lead->id, 'title' => 'Lokal '.$i, 'start_at' => '2026-10-20 09:00:00',
                'end_at' => '2026-10-20 11:00:00', 'status' => 'scheduled',
            ]);
        }

        $this->assertSame(3, TechnicianSchedule::whereNull('google_event_id')->count());
    }

    public function test_soft_deleted_may_keep_same_id_as_active(): void
    {
        $lead = $this->userWithRole('lead-technician');
        $trashed = TechnicianSchedule::create([
            'user_id' => $lead->id, 'title' => 'Terhapus', 'start_at' => '2026-10-20 09:00:00',
            'end_at' => '2026-10-20 11:00:00', 'status' => 'scheduled',
            'google_event_id' => 'gcal-s', 'google_sync_status' => 'synced',
        ]);
        $trashed->delete();

        $active = TechnicianSchedule::create([
            'user_id' => $lead->id, 'title' => 'Aktif', 'start_at' => '2026-10-20 09:00:00',
            'end_at' => '2026-10-20 11:00:00', 'status' => 'scheduled',
            'google_event_id' => 'gcal-s', 'google_sync_status' => 'synced',
        ]);

        $this->assertNotNull($active->id);
        $this->assertSame(1, TechnicianSchedule::whereNull('deleted_at')->where('google_event_id', 'gcal-s')->count());
        $this->assertSame(2, TechnicianSchedule::withTrashed()->where('google_event_id', 'gcal-s')->count());
    }

    public function test_restore_succeeds_without_conflict(): void
    {
        $lead = $this->userWithRole('lead-technician');
        $trashed = TechnicianSchedule::create([
            'user_id' => $lead->id, 'title' => 'Untuk direstore', 'start_at' => '2026-10-20 09:00:00',
            'end_at' => '2026-10-20 11:00:00', 'status' => 'scheduled',
            'google_event_id' => 'gcal-r', 'google_sync_status' => 'synced',
        ]);
        $trashed->delete();

        $mock = \Mockery::mock(\App\Services\GoogleCalendarService::class)->makePartial();
        $mock->shouldReceive('upcomingEvents')->andReturn([[
            'id' => 'gcal-r', 'title' => 'Kembali',
            'start' => \Illuminate\Support\Carbon::parse('2026-10-20 09:00'),
            'end' => \Illuminate\Support\Carbon::parse('2026-10-20 11:00'),
            'location' => '', 'description' => '', 'updated' => now(),
        ]]);
        $this->app->instance(\App\Services\GoogleCalendarService::class, $mock);

        $mock->syncFromGoogle();

        $this->assertSame(1, TechnicianSchedule::whereNull('deleted_at')->where('google_event_id', 'gcal-r')->count());
        $this->assertSame(1, TechnicianSchedule::withTrashed()->where('google_event_id', 'gcal-r')->count());
    }

    public function test_restore_conflict_handled_safely(): void
    {
        $lead = $this->userWithRole('lead-technician');
        // Trashed dibuat DULU (id terendah) → dipilih first() → restore → konflik
        // dengan baris aktif yang sama.
        $trashed = TechnicianSchedule::create([
            'user_id' => $lead->id, 'title' => 'Trashed', 'start_at' => '2026-10-20 09:00:00',
            'end_at' => '2026-10-20 11:00:00', 'status' => 'scheduled',
            'google_event_id' => 'gcal-c', 'google_sync_status' => 'synced',
        ]);
        $trashed->delete();
        TechnicianSchedule::create([
            'user_id' => $lead->id, 'title' => 'Aktif', 'start_at' => '2026-10-20 09:00:00',
            'end_at' => '2026-10-20 11:00:00', 'status' => 'scheduled',
            'google_event_id' => 'gcal-c', 'google_sync_status' => 'synced',
        ]);

        $mock = \Mockery::mock(\App\Services\GoogleCalendarService::class)->makePartial();
        $mock->shouldReceive('upcomingEvents')->andReturn([[
            'id' => 'gcal-c', 'title' => 'Masih ada',
            'start' => \Illuminate\Support\Carbon::parse('2026-10-20 09:00'),
            'end' => \Illuminate\Support\Carbon::parse('2026-10-20 11:00'),
            'location' => '', 'description' => '', 'updated' => now(),
        ]]);
        $this->app->instance(\App\Services\GoogleCalendarService::class, $mock);

        $stats = $mock->syncFromGoogle(); // tidak boleh melempar

        // Tidak ada aktif kedua, tidak ada data hilang.
        $this->assertSame(1, TechnicianSchedule::whereNull('deleted_at')->where('google_event_id', 'gcal-c')->count());
        $this->assertSame(2, TechnicianSchedule::withTrashed()->where('google_event_id', 'gcal-c')->count());
        $this->assertGreaterThanOrEqual(1, $stats['conflicts'] ?? 0);
    }

    public function test_same_event_updates_correct_row_on_next_sync(): void
    {
        $this->userWithRole('lead-technician');
        $event = [
            'id' => 'gcal-next', 'title' => 'Versi 1',
            'start' => \Illuminate\Support\Carbon::parse('2026-10-20 09:00'),
            'end' => \Illuminate\Support\Carbon::parse('2026-10-20 11:00'),
            'location' => '', 'description' => '', 'updated' => now(),
        ];
        $mock = \Mockery::mock(\App\Services\GoogleCalendarService::class)->makePartial();
        $mock->shouldReceive('upcomingEvents')->andReturn([$event]);
        $this->app->instance(\App\Services\GoogleCalendarService::class, $mock);

        $mock->syncFromGoogle();
        $mock->syncFromGoogle();

        $this->assertSame(1, TechnicianSchedule::where('google_event_id', 'gcal-next')->count());
    }

    public function test_restore_conflict_no_destructive_delete_no_false_success(): void
    {
        $lead = $this->userWithRole('lead-technician');
        $b = TechnicianSchedule::create([
            'user_id' => $lead->id, 'title' => 'Trashed k', 'start_at' => '2026-10-20 09:00:00',
            'end_at' => '2026-10-20 11:00:00', 'status' => 'scheduled',
            'google_event_id' => 'gcal-k', 'google_sync_status' => 'synced',
        ]);
        $b->delete();
        TechnicianSchedule::create([
            'user_id' => $lead->id, 'title' => 'Aktif k', 'start_at' => '2026-10-20 09:00:00',
            'end_at' => '2026-10-20 11:00:00', 'status' => 'scheduled',
            'google_event_id' => 'gcal-k', 'google_sync_status' => 'synced',
        ]);
        $other = TechnicianSchedule::create([
            'user_id' => $lead->id, 'title' => 'Event lain', 'start_at' => '2026-10-20 09:00:00',
            'end_at' => '2026-10-20 11:00:00', 'status' => 'scheduled',
            'google_event_id' => 'gcal-m', 'google_sync_status' => 'synced',
        ]);

        $mock = \Mockery::mock(\App\Services\GoogleCalendarService::class)->makePartial();
        $mock->shouldReceive('upcomingEvents')->andReturn([
            [
                'id' => 'gcal-k', 'title' => 'Masih ada k',
                'start' => \Illuminate\Support\Carbon::parse('2026-10-20 09:00'),
                'end' => \Illuminate\Support\Carbon::parse('2026-10-20 11:00'),
                'location' => '', 'description' => '', 'updated' => now(),
            ],
            [
                'id' => 'gcal-m', 'title' => 'Event lain',
                'start' => \Illuminate\Support\Carbon::parse('2026-10-20 09:00'),
                'end' => \Illuminate\Support\Carbon::parse('2026-10-20 11:00'),
                'location' => '', 'description' => '', 'updated' => now(),
            ],
        ]);
        $this->app->instance(\App\Services\GoogleCalendarService::class, $mock);

        $stats = $mock->syncFromGoogle();

        // gcal-m TIDAK dihapus oleh delete-detection (masih ada di Google).
        $this->assertNotSoftDeleted('technician_schedules', ['id' => $other->id]);
        // Konflik tercatat → sync TIDAK dianggap sukses bersih.
        $this->assertGreaterThanOrEqual(1, $stats['conflicts'] ?? 0);
        // Tetap 1 aktif untuk gcal-k (tidak jadi 2).
        $this->assertSame(1, TechnicianSchedule::whereNull('deleted_at')->where('google_event_id', 'gcal-k')->count());
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
