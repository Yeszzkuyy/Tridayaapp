<?php

namespace App\Services;

use App\Models\GoogleAppCredential;
use App\Models\GoogleCredential;
use App\Models\TechnicianSchedule;
use App\Models\User;
use App\Notifications\TechnicianScheduleAssignedNotification;
use App\Notifications\TechnicianScheduleChangedNotification;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\EventReminder;
use Google\Service\Calendar\EventReminders;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
    public const TIMEZONE = 'Asia/Jakarta';

    /** Batas aman halaman saat baca event (pagination) — lebih dari ini = baca dianggap tidak lengkap. */
    private const MAX_SYNC_PAGES = 20;

    /** Nama partial unique index (google_event_id unik untuk baris aktif). */
    private const UNIQUE_INDEX_NAME = 'technician_schedules_google_event_id_active_unique';

    /** Timeout transport Google (detik): koneksi & baca/tulis. */
    public const HTTP_CONNECT_TIMEOUT = 5;
    public const HTTP_TIMEOUT = 30;

/**
     * Cek apakah Google Calendar API dapat diakses (berdasarkan hasil request, bukan DB).
     */
    public function isApiReachable(): bool
    {
        // Cache hasil: sukses 2 menit, gagal hanya 15 detik (recovery cepat).
        // Kegagalan transient (timeout) tidak boleh membuat badge "Disconnected"
        // selama 5 menit penuh.
        $cached = Cache::get('gcal:reachable');
        if (is_bool($cached)) {
            return $cached;
        }

        try {
            $this->upcomingEvents(1);
            Cache::put('gcal:reachable', true, 120);

            return true;
        } catch (\Throwable) {
            Cache::put('gcal:reachable', false, 15);

            return false;
        }
    }

    /**
     * Buang cache status koneksi — dipanggil setelah kredensial berubah
     * atau sync, supaya badge langsung akurat tanpa tunggu TTL.
     */
    public function forgetReachableCache(): void
    {
        Cache::forget('gcal:reachable');
    }

    /**
     * Validasi struktur konfigurasi kredensial (tanpa menyimpan).
     * Mengembalikan pesan kesalahan yang aman, atau null bila valid.
     * Uji koneksi nyata ke Google menyusul di tahap OAuth.
     */
    public function validateCredentials(array $creds): ?string
    {
        $clientId = trim((string) ($creds['client_id'] ?? ''));
        $clientSecret = trim((string) ($creds['client_secret'] ?? ''));

        // Client ID & Client Secret harus satu paket.
        if (($clientId === '') !== ($clientSecret === '')) {
            return __('Client ID dan Client Secret harus diisi bersama.');
        }

        $calendarId = trim((string) ($creds['calendar_id'] ?? ''));
        if ($calendarId !== '' && (str_contains($calendarId, ' ') || strlen($calendarId) > 255)) {
            return __('Calendar ID tidak valid.');
        }

        return null;
    }

    /**
     * Kredensial aplikasi: diprioritaskan dari tabel (di-manage via UI),
     * fallback ke config/env.
     */
    private function appCredentials(): array
    {
        // Tanpa cache statis: nilai harus selalu segar (perubahan kredensial
        // langsung terbaca, termasuk calendar_id kanonis untuk read/write).
        try {
            $cred = \App\Models\GoogleAppCredential::current();

            return [
                'client_id' => $cred->client_id ?: config('services.google-calendar.client_id'),
                'client_secret' => $cred->client_secret ?: config('services.google-calendar.client_secret'),
                'calendar_id' => $cred->calendar_id ?: config('services.google-calendar.calendar_id'),
            ];
        } catch (\Throwable $e) {
            // Kredensial tersimpan gagal didekripsi (mis. APP_KEY berubah) →
            // pakai fallback config + pesan aman (tanpa plaintext/stack trace).
            try {
                Log::warning('Kredensial Google gagal didekripsi, memakai nilai config.');
            } catch (\Throwable) {
            }

            return [
                'client_id' => config('services.google-calendar.client_id'),
                'client_secret' => config('services.google-calendar.client_secret'),
                'calendar_id' => config('services.google-calendar.calendar_id'),
            ];
        }
    }

    /**
     * Ambil event mendatang dari kalender publik via API key (read-only).
     */
    public function upcomingEvents(int $maxResults = 20, ?Carbon $from = null, ?Carbon $to = null): array
    {
        // Read via OAuth (bukan API key). Jika belum terhubung, hentikan dengan
        // status jelas — jangan diam-diam kembali memakai API key.
        if (! $this->hasOAuthConnection()) {
            throw new \RuntimeException('Google Calendar belum terhubung (OAuth).');
        }

        $calendarId = $this->canonicalCalendarId();
        $calendar = $this->calendarService(auth()->user());

        $params = [
            'singleEvents' => true,
            'maxResults' => min($maxResults, 250),
            'orderBy' => 'startTime',
        ];

        if ($from) {
            $params['timeMin'] = $from->toRfc3339String();
        }

        if ($to) {
            $params['timeMax'] = $to->toRfc3339String();
        }

        // Baca SEMUA halaman (pageToken) sebelum daftar dianggap lengkap.
        // Bila melebihi batas aman → lempar "tidak lengkap" agar proses hapus
        // massal dibatalkan (lihat syncFromGoogle).
        $items = [];
        $pageToken = null;
        $pages = 0;

        do {
            $params['pageToken'] = $pageToken;
            $result = $this->callWithRetry(fn () => $calendar->events->listEvents($calendarId, $params));
            $items = array_merge($items, $result->getItems() ?? []);
            $pageToken = $result->getNextPageToken();
            $pages++;

            if ($pages > self::MAX_SYNC_PAGES) {
                throw new \RuntimeException('Hasil baca Google tidak lengkap (terlalu banyak halaman).');
            }
        } while ($pageToken);

        return collect($items)
            ->map(function ($item) {
                $s = $item->getStart();
                $e = $item->getEnd();
                $updated = $item->getUpdated();

                return [
                    'id' => $item->getId(),
                    'title' => $item->getSummary() ?: '(Tanpa judul)',
                    'start' => $this->parseEventTime(['dateTime' => $s?->getDateTime(), 'date' => $s?->getDate()]),
                    'end' => $this->parseEventTime(['dateTime' => $e?->getDateTime(), 'date' => $e?->getDate()]),
                    'location' => $item->getLocation() ?: '',
                    'description' => $item->getDescription() ?: '',
                    'updated' => $updated ? Carbon::parse($updated) : null,
                ];
            })
            ->sortBy('start')
            ->values()
            ->all();
    }

    /**
     * Panggil aksi Google dengan retry terbatas + exponential backoff untuk
     * kegagalan sementara (timeout, rate limit, server error). TIDAK retry
     * untuk kegagalan autentikasi permanen (invalid_grant/401) atau 4xx.
     */
    private function callWithRetry(callable $action, int $attempts = 3)
    {
        $delayMs = 150;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                return $action();
            } catch (\Throwable $e) {
                $code = (int) $e->getCode();
                $msg = strtolower($e->getMessage());

                $auth = $code === 401
                    || str_contains($msg, 'invalid_grant')
                    || str_contains($msg, 'invalid_client')
                    || str_contains($msg, 'invalid_token')
                    || str_contains($msg, 'unauthorized')
                    || str_contains($msg, 'mac is invalid');

                $transient = ! $auth && (
                    $code === 429 || $code === 408 || $code >= 500
                    || str_contains($msg, 'timeout') || str_contains($msg, 'timed out')
                    || str_contains($msg, 'curl error') || str_contains($msg, 'could not resolve')
                    || str_contains($msg, 'connection') || str_contains($msg, 'network')
                );

                if (! $transient || $i === $attempts - 1) {
                    throw $e;
                }

                usleep($delayMs * 1000);
                $delayMs *= 2; // exponential backoff
            }
        }
    }

    /**
     * Sinkronkan event Google Calendar ke tabel technician_schedules.
     * UPDATE jika google_event_id sudah ada, INSERT jika belum, tidak pernah duplikat.
     */
    public function syncFromGoogle(int $maxResults = 250): array
    {
        $events = $this->upcomingEvents(
            $maxResults,
            now()->subDays(30),
            now()->addMonths(2),
        );

        $stats = ['created' => 0, 'updated' => 0, 'restored' => 0, 'skipped' => 0, 'deleted' => 0, 'conflicts' => 0];
        $calendarId = $this->canonicalCalendarId();
        $owner = User::query()
            ->whereHas('permissions', fn ($q) => $q->whereIn('name', ['manage-technician', 'manage-admin']))
            ->orderBy('id')
            ->first() ?? User::query()->orderBy('id')->first();

        if (! $owner) {
            Log::warning('Google sync: tidak ada user untuk pemilik jadwal, dilewati.');

            return $stats;
        }

        $seenIds = [];

        foreach ($events as $event) {
            $id = $event['id'];
            if (! $id) {
                $stats['skipped']++;

                continue;
            }

            $seenIds[] = $id;

            if (! $event['start'] && ! $event['end']) {
                $stats['skipped']++;

                continue;
            }

            $schedule = TechnicianSchedule::withTrashed()->orderBy('id')->where('google_event_id', $id)->first();

            // Field milik aplikasi (project, teknisi, status, reminder) TIDAK pernah
            // ditimpa Google — hanya judul/deskripsi/lokasi/jam yang diperebutkan.
            $shared = [
                'title' => $event['title'],
                'description' => $event['description'] ?: null,
                'location' => $event['location'] ?: null,
                'start_at' => $event['start'],
                'end_at' => $event['end'] ?? $event['start'],
            ];

            if ($schedule) {
                // "Terbaru menang": bila Google tidak lebih baru dari lokal, biarkan lokal.
                if ($event['updated'] && $schedule->updated_at && $event['updated']->lte($schedule->updated_at)) {
                    $stats['skipped']++;

                    continue;
                }

                $schedule->update($shared + [
                    'google_calendar_id' => $calendarId,
                    'google_sync_status' => 'synced',
                    'google_sync_error' => null,
                ]);

                if ($schedule->trashed()) {
                    try {
                        // Savepoint: bila restore ditolak unique constraint,
                        // rollback hanya savepoint ini — transaksi luar tetap
                        // valid (tidak diracuni) sehingga query berikutnya jalan.
                        \Illuminate\Support\Facades\DB::transaction(function () use ($schedule) {
                            $schedule->restore();
                        });
                        $stats['restored']++;
                        Log::debug('Google sync: jadwal trash direstore', ['id' => $schedule->id, 'google_event_id' => $id]);
                    } catch (\Illuminate\Database\QueryException $e) {
                        if (! $this->isRestoreUniqueConflict($e)) {
                            throw $e; // QueryException lain → jangan ditelan
                        }

                        // Konflik: sudah ada baris AKTIF dengan google_event_id sama.
                        // Jangan buat aktif kedua / jangan hapus apa pun → biarkan
                        // baris ini tetap terhapus (soft-delete) dan catat konflik.
                        $stats['conflicts']++;
                        try {
                            Log::warning('Restore jadwal ditolak: sudah ada baris aktif dengan google_event_id sama.', [
                                'google_event_id' => $id,
                            ]);
                        } catch (\Throwable) {
                        }
                    }
                }
                $stats['updated']++;
                Log::debug('Google sync: UPDATE jadwal', ['id' => $schedule->id, 'google_event_id' => $id]);
            } else {
                TechnicianSchedule::create($shared + [
                    'user_id' => $owner->id,
                    'status' => 'scheduled',
                    'google_event_id' => $id,
                    'google_calendar_id' => $calendarId,
                    'google_sync_status' => 'synced',
                    'google_sync_error' => null,
                ]);
                $stats['created']++;
                Log::debug('Google sync: INSERT jadwal baru', ['google_event_id' => $id]);
            }
        }

        // Deteksi hapus: event yang dihapus di Google → soft-delete lokal.
        // Hanya yang BER-google_event_id & dalam rentang sync; jadwal lokal
        // murni (tanpa google_event_id) tidak pernah disentuh. `upcomingEvents`
        // sudah membaca SEMUA halaman (melempar bila tidak lengkap), sehingga
        // perbandingan $seenIds valid — tidak ada penghapusan dari baca parsial.
        TechnicianSchedule::query()
            ->whereNotNull('google_event_id')
            ->whereBetween('start_at', [now()->subDays(30), now()->addMonths(2)])
            ->whereNotIn('google_event_id', $seenIds)
            ->get()
            ->each(function (TechnicianSchedule $schedule) use (&$stats) {
                $schedule->delete();
                $stats['deleted']++;
                Log::debug('Google sync: jadwal dihapus di Google → soft-delete', ['id' => $schedule->id]);
            });

        Log::info('Google sync: selesai', $stats);

        return $stats;
    }

    /**
     * Dorong perubahan lokal ke Google (2-arah). Target: jadwal yang belum
     * tersinkron (syncing/error) — hasil create/edit di aplikasi. Lewati bila
     * OAuth belum terhubung.
     */
    public function pushLocalChanges(): array
    {
        $out = ['pushed' => 0, 'errors' => 0, 'skipped_no_oauth' => false, 'auth_error' => false];

        if (! $this->hasOAuthConnection()) {
            $out['skipped_no_oauth'] = true;

            return $out;
        }

        $pending = TechnicianSchedule::with(['project.customer', 'technician', 'owner'])
            ->whereIn('google_sync_status', ['syncing', 'error'])
            ->limit(100)
            ->get();

        foreach ($pending as $schedule) {
            $this->pushEvent($schedule);
            $schedule->refresh();

            if ($schedule->google_sync_status === 'synced') {
                $out['pushed']++;
            } else {
                $out['errors']++;
                if ($this->looksLikeAuthError((string) $schedule->google_sync_error)) {
                    $out['auth_error'] = true;
                }
            }
        }

        return $out;
    }

    /**
     * Sinkron dua arah: tarik dari Google (terbaru menang + deteksi hapus),
     * lalu dorong perubahan lokal ke Google.
     */
    public function syncTwoWay(): array
    {
        // Lock tunggal untuk SEMUA jalur sync (command, job, tombol manual) —
        // cegah sinkron berjalan bersamaan yang bisa menghasilkan event duplikat.
        $lock = Cache::lock('gcal:sync', 300);

        if (! $lock->get()) {
            return ['created' => 0, 'updated' => 0, 'deleted' => 0, 'pushed' => 0, 'skipped' => 0, 'locked' => true];
        }

        try {
            try {
                $stats = $this->syncFromGoogle();
            } catch (\Throwable $e) {
                $c = $this->classifySyncError($e);
                $this->recordSyncFailure($c['kind'], $c['message']);

                throw $e;
            }

            $push = $this->pushLocalChanges();
            $stats['pushed'] = $push['pushed'];
            $stats['push_errors'] = $push['errors'];
            $stats['push_no_oauth'] = $push['skipped_no_oauth'];

            if (! empty($push['auth_error'])) {
                $this->recordSyncFailure('auth', __('Izin Google kedaluwarsa, hubungkan kembali.'));
            } elseif (! empty($push['errors'])) {
                $this->recordSyncFailure('sync', __('Sebagian jadwal gagal dikirim ke Google.'));
            } elseif (! empty($stats['conflicts'])) {
                // Ada konflik unik saat restore → jangan klaim sinkron sempurna.
                $this->recordSyncFailure('sync', __('Sebagian jadwal tidak dapat dipulihkan (konflik google_event_id).'));
            } else {
                $this->recordSyncSuccess(__(
                    ':created baru, :updated diperbarui, :deleted dihapus, :pushed dikirim',
                    [
                        'created' => $stats['created'] ?? 0,
                        'updated' => $stats['updated'] ?? 0,
                        'deleted' => $stats['deleted'] ?? 0,
                        'pushed' => $stats['pushed'] ?? 0,
                    ]
                ));
            }

            return $stats;
        } finally {
            // Lock selalu dilepas, termasuk saat proses gagal.
            try {
                $lock->release();
            } catch (\Throwable) {
            }
        }
    }

    /**
     * Status koneksi untuk badge (4 state) + info sinkron terakhir.
     * Berbasis kondisi nyata: adanya token OAuth & hasil sinkron terakhir.
     */
    public function connectionStatus(?User $user = null): array
    {
        $cred = GoogleAppCredential::current();
        $oauth = $this->hasOAuthConnection($user);
        $kind = $cred->last_sync_error_kind;

        if (! $oauth) {
            $state = 'not_connected';
        } elseif ($kind === 'auth') {
            $state = 'needs_reauth';
        } elseif ($kind !== null) {
            $state = 'error';
        } else {
            $state = 'connected';
        }

        return [
            'status' => $state,
            'connected' => $state === 'connected',
            'oauth_connected' => $oauth,
            'last_sync_at' => $cred->last_sync_at
                ? \Illuminate\Support\Carbon::parse($cred->last_sync_at)->toIso8601String()
                : null,
            'last_sync_summary' => $cred->last_sync_summary,
            'last_sync_error' => $cred->last_sync_error,
        ];
    }

    /**
     * Klasifikasi kegagalan: auth (perlu otorisasi ulang) | network | sync.
     */
    private function classifySyncError(\Throwable $e): array
    {
        $msg = $e->getMessage();
        $lower = strtolower($msg);
        $code = (int) $e->getCode();

        if (
            str_contains($lower, 'invalid_grant')
            || str_contains($lower, 'invalid_client')
            || str_contains($lower, 'invalid_token')
            || str_contains($lower, 'unauthorized')
            || str_contains($lower, 'mac is invalid')
            || str_contains($lower, 'revoked')
            || $code === 401
        ) {
            return ['kind' => 'auth', 'message' => $this->sanitizeError($msg)];
        }

        if (
            str_contains($lower, 'curl error')
            || str_contains($lower, 'timed out')
            || str_contains($lower, 'timeout')
            || str_contains($lower, 'could not resolve')
            || str_contains($lower, 'network')
            || str_contains($lower, 'failed to connect')
            || str_contains($lower, 'connection')
        ) {
            return ['kind' => 'network', 'message' => $this->sanitizeError($msg)];
        }

        return ['kind' => 'sync', 'message' => $this->sanitizeError($msg)];
    }

    /**
     * Bersihkan pesan error dari nilai yang mirip rahasia (key/token/secret).
     */
    private function sanitizeError(string $msg): string
    {
        $msg = preg_replace('/\b(key|token|access_token|refresh_token|client_secret|api_key|secret|authorization|bearer)\b[=:\s]*[^\s&"\']*/i', '$1=[REDACTED]', (string) $msg);
        $msg = preg_replace('/\b[A-Za-z0-9_\-\.]{25,}\b/', '[REDACTED]', (string) $msg);

        return \Illuminate\Support\Str::limit(trim((string) $msg), 160);
    }

    /**
     * Catat sinkronisasi berhasil (tanpa rahasia).
     */
    private function recordSyncSuccess(string $summary): void
    {
        try {
            GoogleAppCredential::current()->forceFill([
                'last_sync_at' => now(),
                'last_sync_summary' => \Illuminate\Support\Str::limit($summary, 255),
                'last_sync_error' => null,
                'last_sync_error_kind' => null,
            ])->save();
        } catch (\Throwable) {
        }
    }

    /**
     * Catat sinkronisasi gagal — $kind: auth|network|sync. Pesan disanitasi.
     */
    private function recordSyncFailure(string $kind, string $message): void
    {
        try {
            GoogleAppCredential::current()->forceFill([
                'last_sync_at' => now(),
                'last_sync_summary' => null,
                'last_sync_error' => $this->sanitizeError($message),
                'last_sync_error_kind' => $kind,
            ])->save();
        } catch (\Throwable) {
        }
    }

    private function looksLikeAuthError(string $msg): bool
    {
        $m = strtolower($msg);

        return str_contains($m, 'invalid_grant')
            || str_contains($m, 'kedaluwarsa')
            || str_contains($m, 'revoked')
            || str_contains($m, 'hubungkan kembali')
            || str_contains($m, 'unauthorized');
    }

    /**
     * Deteksi spesifik pelanggaran unique pada index google_event_id aktif
     * (PostgreSQL SQLSTATE 23505 + nama index). TIDAK menelan QueryException lain.
     */
    private function isRestoreUniqueConflict(\Illuminate\Database\QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $msg = (string) $e->getMessage();

        // Hanya pelanggaran UNIQUE pada google_event_id — BUKAN semua QueryException.
        $isUnique = $sqlState === '23505'
            || stripos($msg, 'unique') !== false;
        $isOurs = str_contains($msg, self::UNIQUE_INDEX_NAME)
            || str_contains($msg, 'google_event_id');

        return $isUnique && $isOurs;
    }

    private function parseEventTime(array $time): ?Carbon
    {
        if (isset($time['dateTime'])) {
            return Carbon::parse($time['dateTime'])->setTimezone(self::TIMEZONE);
        }

        if (isset($time['date'])) {
            return Carbon::parse($time['date'])->setTimezone(self::TIMEZONE);
        }

        return null;
    }

    private const STATUS_COLOR = [
        'scheduled' => 1,
        'on_progress' => 5,
        'completed' => 10,
        'cancelled' => 11,
    ];

    public function clientFor(?User $user = null): Client
    {
        $client = new Client;
        // Timeout transport nyata pada Guzzle yang dipakai Google SDK —
        // `Http::timeout()` TIDAK berlaku di sini. Connect + read/write timeout
        // diterapkan pada http client agar listEvents/insert/update/delete
        // tidak menggantung tanpa batas.
        $client->setHttpClient(new \GuzzleHttp\Client([
            'timeout' => self::HTTP_TIMEOUT,
            'connect_timeout' => self::HTTP_CONNECT_TIMEOUT,
        ]));
        $client->setApplicationName(config('app.name'));
        $client->setClientId($this->appCredentials()['client_id']);
        $client->setClientSecret($this->appCredentials()['client_secret']);
        $client->setRedirectUri(route('teknisi.kalender.callback'));
        $client->setScopes([Calendar::CALENDAR_EVENTS]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        if ($user) {
            $this->restoreTokens($client, $user);
        }

        return $client;
    }

    public function isConnected(User $user): bool
    {
        return GoogleCredential::where('user_id', $user->id)->exists();
    }

    /**
     * Apakah ada koneksi OAuth (tulis/sync 2-arah) — token tersimpan untuk
     * user ini, atau user lain mana pun (token global dipakai sync).
     */
    public function hasOAuthConnection(?User $user = null): bool
    {
        if ($user && GoogleCredential::where('user_id', $user->id)->exists()) {
            return true;
        }

        return GoogleCredential::query()->exists();
    }

    public function getAuthUrl(User $user, string $state): string
    {
        $client = $this->clientFor();
        $client->setState($state);

        return $client->createAuthUrl();
    }

    public function handleCallback(User $user, string $code): void
    {
        // Tukar authorization code → token via HTTP (memakai Http facade agar
        // bisa di-fake di tes tanpa jaringan Google nyata). Semantik sama
        // dengan fetchAccessTokenWithAuthCode, hanya transport yang berbeda.
        // Connect + read timeout diterapkan pada request ini.
        $response = Http::asForm()
            ->connectTimeout(self::HTTP_CONNECT_TIMEOUT)
            ->timeout(self::HTTP_TIMEOUT)
            ->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->appCredentials()['client_id'],
            'client_secret' => $this->appCredentials()['client_secret'],
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => route('teknisi.kalender.callback'),
        ]);

        $token = $response->json() ?? [];

        if (isset($token['error']) || empty($token['access_token'])) {
            throw new \RuntimeException($token['error_description'] ?? 'Google OAuth gagal.');
        }

        GoogleCredential::updateOrCreate(['user_id' => $user->id], [
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
            'expires_at' => isset($token['expires_in'])
                ? now()->addSeconds($token['expires_in'])
                : null,
            'calendar_id' => 'primary',
        ]);
    }

    public function disconnect(User $user): void
    {
        GoogleCredential::where('user_id', $user->id)->delete();

        TechnicianSchedule::where('user_id', $user->id)
            ->where('google_sync_status', '!=', 'not_connected')
            ->update([
                'google_sync_status' => 'not_connected',
                'google_sync_error' => null,
            ]);
    }

    /**
     * Simpan jadwal ke database lalu buat event di Google Calendar.
     */
    public function createSchedule(User $user, array $data): TechnicianSchedule
    {
        // technician_ids bukan kolom tabel — disimpan ke relasi pivot.
        $technicianIds = array_values(array_unique(array_map('intval', $data['technician_ids'] ?? [])));
        unset($data['technician_ids']);

        $schedule = new TechnicianSchedule(array_merge($data, [
            'user_id' => $user->id,
            'google_sync_status' => $this->isConnected($user) ? 'syncing' : 'not_connected',
        ]));
        $schedule->save();
        $schedule->technicians()->sync($technicianIds);

        if ($this->isConnected($user)) {
            $this->pushEvent($schedule);
        }

        // Semua teknisi yang ditugaskan menerima notifikasi.
        foreach ($schedule->technicians as $technician) {
            $this->notifyTechnician($technician, $user,
                new TechnicianScheduleAssignedNotification($schedule));
        }

        return $schedule;
    }

    /**
     * Update database lalu sinkronkan perubahan ke Google Calendar
     * (pakai google_event_id, tidak membuat event baru).
     */
    public function updateSchedule(TechnicianSchedule $schedule, array $data): TechnicianSchedule
    {
        $editor = auth()->user();

        // Teknisi lama (pivot) untuk deteksi siapa yang ditambah/dihapus.
        $oldTechnicianIds = $schedule->technicians()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        $newTechnicianIds = array_values(array_unique(array_map('intval', $data['technician_ids'] ?? $oldTechnicianIds)));
        unset($data['technician_ids']);

        $change = ($data['status'] ?? $schedule->status) === 'cancelled' ? 'cancelled' : 'updated';
        // Waktu berubah → reminder perlu dikirim ulang.
        $startChanged = isset($data['start_at']) && ! $schedule->start_at->equalTo($data['start_at']);

        $schedule->update(array_merge($data, [
            'google_sync_status' => $schedule->google_sync_status === 'not_connected'
                ? 'not_connected'
                : 'syncing',
            'reminder_sent_at' => $startChanged ? null : $schedule->reminder_sent_at,
        ]));

        $schedule->technicians()->sync($newTechnicianIds);

        if ($schedule->google_sync_status !== 'not_connected') {
            $this->pushEvent($schedule);
        }

        $schedule->refresh();
        $notified = [];

        // Teknisi baru → notif assign; teknisi lama yang diganti/dihapus → notif perubahan.
        foreach ($newTechnicianIds as $id) {
            if (! in_array($id, $oldTechnicianIds, true)) {
                $this->notifyTechnician(User::find($id), $editor,
                    new TechnicianScheduleAssignedNotification($schedule), $notified);
            }
        }
        foreach ($oldTechnicianIds as $id) {
            if (! in_array($id, $newTechnicianIds, true)) {
                $this->notifyTechnician(User::find($id), $editor,
                    new TechnicianScheduleChangedNotification($schedule, $change), $notified);
            }
        }

        // Teknisi saat ini + owner (selain editor & yang sudah dinotifikasi).
        foreach ($schedule->technicians as $technician) {
            $this->notifyTechnician($technician, $editor,
                new TechnicianScheduleChangedNotification($schedule, $change), $notified);
        }
        $this->notifyTechnician($schedule->owner, $editor,
            new TechnicianScheduleChangedNotification($schedule, $change), $notified);

        return $schedule;
    }

    public function deleteSchedule(TechnicianSchedule $schedule): void
    {
        $deleter = auth()->user();
        $technicians = $schedule->technicianList();
        $owner = $schedule->owner;
        // Notifikasi dikirim sebelum hapus agar relasi masih tersedia.
        $notified = [];
        foreach ($technicians as $technician) {
            $this->notifyTechnician($technician, $deleter,
                new TechnicianScheduleChangedNotification($schedule, 'deleted'), $notified);
        }
        $this->notifyTechnician($owner, $deleter,
            new TechnicianScheduleChangedNotification($schedule, 'deleted'), $notified);

        if ($schedule->google_event_id && $schedule->google_sync_status !== 'not_connected') {
            try {
                $calendar = $this->calendarService($schedule->owner);
                $calendar->events->delete($schedule->google_calendar_id ?: $this->canonicalCalendarId(), $schedule->google_event_id);
            } catch (\Throwable $e) {
                // jangan gagalkan hapus lokal karena error google
            }
        }

        $schedule->delete();
    }

    /**
     * Kirim notifikasi ke teknisi/owner, lewati bila null, sama dengan
     * pengirim aksi, atau sudah dinotifikasi. Kegagalan push tidak
     * boleh menggagalkan simpan jadwal.
     */
    private function notifyTechnician(?User $target, ?User $actor, object $notification, array &$notified = []): void
    {
        if (! $target || ($actor && $target->is($actor)) || in_array($target->id, $notified, true)) {
            return;
        }

        try {
            $target->notify($notification);
            $notified[] = $target->id;
        } catch (\Throwable $e) {
            Log::warning('Notifikasi jadwal teknisi gagal: '.$e->getMessage());
        }
    }

    /**
     * Buat atau update event Google Calendar. Error tidak di-throw ke caller,
     * status sinkronisasi disimpan di baris jadwal.
     */
    private function pushEvent(TechnicianSchedule $schedule): void
    {
        try {
            $calendar = $this->calendarService($schedule->owner);
            $event = new Event($this->buildPayload($schedule));

            if ($schedule->google_event_id) {
                try {
                    $created = $this->callWithRetry(fn () => $calendar->events->update(
                        $schedule->google_calendar_id ?: $this->canonicalCalendarId(),
                        $schedule->google_event_id,
                        $event
                    ));
                } catch (\Throwable $e) {
                    if ($e->getCode() !== 404) {
                        throw $e;
                    }
                    // event sudah dihapus di Google → buat ulang
                    $schedule->google_event_id = null;
                    $created = $this->callWithRetry(fn () => $calendar->events->insert($this->canonicalCalendarId(), $event));
                }
            } else {
                $created = $this->callWithRetry(fn () => $calendar->events->insert($this->canonicalCalendarId(), $event));
            }

            $schedule->forceFill([
                'google_event_id' => $created->getId(),
                'google_calendar_id' => $this->canonicalCalendarId(),
                'google_sync_status' => 'synced',
                'google_sync_error' => null,
            ])->save();
        } catch (\Throwable $e) {
            $schedule->forceFill([
                'google_sync_status' => 'error',
                'google_sync_error' => $this->friendlyError($e),
            ])->save();
        }
    }

    private function buildPayload(TechnicianSchedule $schedule): array
    {
        $payload = [
            'summary' => '[TEKNISI] '.$schedule->title,
            'description' => $this->buildDescription($schedule),
            'location' => $schedule->location,
            'colorId' => self::STATUS_COLOR[$schedule->status] ?? null,
            'start' => $this->eventDateTime($schedule->start_at),
            'end' => $this->eventDateTime($schedule->end_at),
            'reminders' => $this->buildReminders($schedule),
        ];

        if ($schedule->technician?->email) {
            $payload['attendees'] = [
                ['email' => $schedule->technician->email, 'displayName' => $schedule->technician->name],
            ];
        }

        return $payload;
    }

    private function buildDescription(TechnicianSchedule $schedule): string
    {
        $technicianNames = $schedule->technicianList()->pluck('name')->filter()->implode(', ');

        $lines = [
            'Project: '.($schedule->project?->project_name ?? '-'),
            'Customer: '.($schedule->project?->customer?->company_name ?? '-'),
            'Teknisi: '.($technicianNames !== '' ? $technicianNames : '-'),
            'Status: '.(TechnicianSchedule::STATUSES[$schedule->status] ?? $schedule->status),
        ];

        if ($schedule->description) {
            $lines[] = '';
            $lines[] = $schedule->description;
        }

        return implode("\n", $lines);
    }

    private function eventDateTime(Carbon $carbon): EventDateTime
    {
        $dt = new EventDateTime;
        $dt->setDateTime($carbon->copy()->setTimezone(self::TIMEZONE)->format('Y-m-d\TH:i:s'));
        $dt->setTimeZone(self::TIMEZONE);

        return $dt;
    }

    private function buildReminders(TechnicianSchedule $schedule): EventReminders
    {
        $reminders = new EventReminders;
        $reminders->setUseDefault(false);

        $popup = $schedule->reminder_minutes ?: 30;
        $reminders->setOverrides([
            (new EventReminder)->setMethod('email')->setMinutes(1440),
            (new EventReminder)->setMethod('popup')->setMinutes(min($popup, 1440)),
        ]);

        return $reminders;
    }

    private function calendarService(?User $user = null): Calendar
    {
        $client = $this->clientFor($user);

        if (! $client->getAccessToken()) {
            throw new \RuntimeException('Google Calendar belum terhubung.');
        }

        try {
            return new Calendar($client);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Koneksi Google Calendar gagal, coba hubungkan ulang.');
        }
    }

    /**
     * Satu calendar_id kanonis untuk baca maupun tulis (kalender kantor).
     * Fallback 'primary' hanya bila belum dikonfigurasi.
     */
    private function canonicalCalendarId(): string
    {
        return $this->appCredentials()['calendar_id'] ?: 'primary';
    }

    private function restoreTokens(Client $client, ?User $user): void
    {
        // Fallback: bila user ini belum Connect (atau dipanggil dari job/command
        // tanpa auth), pakai token global yang ada (satu akun Google bersama).
        $credential = ($user ? GoogleCredential::where('user_id', $user->id)->first() : null)
            ?? GoogleCredential::query()->latest('id')->first();

        if (! $credential) {
            return;
        }

        $client->setAccessToken([
            'access_token' => $credential->access_token,
            'refresh_token' => $credential->refresh_token,
            'created' => ($credential->expires_at?->getTimestamp() ?? now()->timestamp) - 3600,
            'expires_in' => 3600,
        ]);

        // simpan otomatis token hasil refresh agar tidak kedaluwarsa
        $client->setTokenCallback(function ($cacheKey, $token) use ($credential) {
            $credential->update([
                'access_token' => $token['access_token'] ?? $credential->access_token,
                'refresh_token' => $token['refresh_token'] ?? $credential->refresh_token,
                'expires_at' => isset($token['expires_in'])
                    ? now()->addSeconds($token['expires_in'])
                    : $credential->expires_at,
            ]);
        });
    }

    private function friendlyError(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'invalid_grant') || str_contains($message, 'Invalid Credentials')) {
            return 'Izin Google Calendar kedaluwarsa, hubungkan kembali.';
        }

        if ($e->getCode() === 401) {
            return 'Token Google kedaluwarsa, coba hubungkan kembali.';
        }

        return 'Gagal sinkron ke Google Calendar: '.$message;
    }
}
