<?php

namespace App\Services;

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
     * Kredensial aplikasi: diprioritaskan dari tabel (di-manage via UI),
     * fallback ke config/env.
     */
    private function appCredentials(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $cred = \App\Models\GoogleAppCredential::current();

        return $cached = [
            'client_id' => $cred->client_id ?: config('services.google-calendar.client_id'),
            'client_secret' => $cred->client_secret ?: config('services.google-calendar.client_secret'),
            'api_key' => $cred->api_key ?: config('services.google-calendar.api_key'),
            'calendar_id' => $cred->calendar_id ?: config('services.google-calendar.calendar_id'),
        ];
    }

    /**
     * Ambil event mendatang dari kalender publik via API key (read-only).
     */
    public function upcomingEvents(int $maxResults = 20, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $app = $this->appCredentials();

        $params = [
            'key' => $app['api_key'],
            'singleEvents' => 'true',
            'maxResults' => $maxResults,
            'orderBy' => 'startTime',
        ];

        if ($from) {
            $params['timeMin'] = $from->toRfc3339String();
        }

        if ($to) {
            $params['timeMax'] = $to->toRfc3339String();
        }

        $url = 'https://www.googleapis.com/calendar/v3/calendars/'.urlencode($this->appCredentials()['calendar_id']).'/events';

        try {
            // Timeout + retry: jangan gantung worker bila Google lambat.
            $response = Http::timeout(10)->retry(1, 200)->get($url, $params);

            $response->throw();

            $items = $response->json('items', []);

            Log::debug('Google API response', [
                'status' => $response->status(),
                'total_items' => count($items),
            ]);

            return collect($items)
                ->map(fn (array $item) => [
                    'id' => $item['id'] ?? null,
                    'title' => $item['summary'] ?? '(Tanpa judul)',
                    'start' => $this->parseEventTime($item['start'] ?? []),
                    'end' => $this->parseEventTime($item['end'] ?? []),
                    'location' => $item['location'] ?? '',
                    'description' => $item['description'] ?? '',
                ])
                ->sortBy('start')
                ->values()
                ->take($maxResults)
                ->all();
        } catch (\Throwable $e) {
            Log::error('Gagal mengambil event Google Calendar', [
                'calendar_id' => $this->appCredentials()['calendar_id'],
                'message' => $e->getMessage(),
            ]);

            throw $e;
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

        $stats = ['created' => 0, 'updated' => 0, 'restored' => 0, 'skipped' => 0];
        $owner = User::query()
            ->whereHas('permissions', fn ($q) => $q->whereIn('name', ['manage-technician', 'manage-admin']))
            ->orderBy('id')
            ->first() ?? User::query()->orderBy('id')->first();

        if (! $owner) {
            Log::warning('Google sync: tidak ada user untuk pemilik jadwal, dilewati.');

            return $stats;
        }

        foreach ($events as $event) {
            $id = $event['id'];
            if (! $id) {
                $stats['skipped']++;

                continue;
            }

            $schedule = TechnicianSchedule::withTrashed()->where('google_event_id', $id)->first();

            if (! $event['start'] && ! $event['end']) {
                $stats['skipped']++;

                continue;
            }

            $data = [
                'user_id' => $owner->id,
                'title' => $event['title'],
                'description' => $event['description'] ?: null,
                'location' => $event['location'] ?: null,
                'start_at' => $event['start'],
                'end_at' => $event['end'] ?? $event['start'],
                'status' => 'scheduled',
                'google_event_id' => $id,
                'google_calendar_id' => $this->appCredentials()['calendar_id'],
                'google_sync_status' => 'synced',
                'google_sync_error' => null,
            ];

            if ($schedule) {
                $schedule->update($data);
                if ($schedule->trashed()) {
                    $schedule->restore();
                    $stats['restored']++;
                    Log::debug('Google sync: jadwal trash direstore', ['id' => $schedule->id, 'google_event_id' => $id]);
                }
                $stats['updated']++;
                Log::debug('Google sync: UPDATE jadwal', ['id' => $schedule->id, 'google_event_id' => $id]);
            } else {
                TechnicianSchedule::create($data);
                $stats['created']++;
                Log::debug('Google sync: INSERT jadwal baru', ['google_event_id' => $id]);
            }
        }

        Log::info('Google sync: selesai', $stats);

        return $stats;
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
        $client = $this->clientFor();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
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
        $schedule = new TechnicianSchedule(array_merge($data, [
            'user_id' => $user->id,
            'google_sync_status' => $this->isConnected($user) ? 'syncing' : 'not_connected',
        ]));
        $schedule->save();

        if ($this->isConnected($user)) {
            $this->pushEvent($schedule);
        }

        $this->notifyTechnician($schedule->technician, $user,
            new TechnicianScheduleAssignedNotification($schedule));

        return $schedule;
    }

    /**
     * Update database lalu sinkronkan perubahan ke Google Calendar
     * (pakai google_event_id, tidak membuat event baru).
     */
    public function updateSchedule(TechnicianSchedule $schedule, array $data): TechnicianSchedule
    {
        $editor = auth()->user();
        $oldTechnicianId = $schedule->technician_user_id;
        $change = ($data['status'] ?? $schedule->status) === 'cancelled' ? 'cancelled' : 'updated';
        // Waktu berubah → reminder perlu dikirim ulang.
        $startChanged = isset($data['start_at']) && ! $schedule->start_at->equalTo($data['start_at']);

        $schedule->update(array_merge($data, [
            'google_sync_status' => $schedule->google_sync_status === 'not_connected'
                ? 'not_connected'
                : 'syncing',
            'reminder_sent_at' => $startChanged ? null : $schedule->reminder_sent_at,
        ]));

        if ($schedule->google_sync_status !== 'not_connected') {
            $this->pushEvent($schedule);
        }

        $schedule->refresh();
        $notified = [];

        // Teknisi baru → notif assign; teknisi lama yang diganti → notif perubahan.
        if ($schedule->technician_user_id && $schedule->technician_user_id !== $oldTechnicianId) {
            $this->notifyTechnician($schedule->technician, $editor,
                new TechnicianScheduleAssignedNotification($schedule), $notified);
        }
        if ($oldTechnicianId && $oldTechnicianId !== $schedule->technician_user_id) {
            $this->notifyTechnician(User::find($oldTechnicianId), $editor,
                new TechnicianScheduleChangedNotification($schedule, $change), $notified);
        }

        // Teknisi saat ini + owner (selain editor & yang sudah dinotifikasi).
        $this->notifyTechnician($schedule->technician, $editor,
            new TechnicianScheduleChangedNotification($schedule, $change), $notified);
        $this->notifyTechnician($schedule->owner, $editor,
            new TechnicianScheduleChangedNotification($schedule, $change), $notified);

        return $schedule;
    }

    public function deleteSchedule(TechnicianSchedule $schedule): void
    {
        $deleter = auth()->user();
        $technician = $schedule->technician;
        $owner = $schedule->owner;
        // Notifikasi dikirim sebelum hapus agar relasi masih tersedia.
        $notified = [];
        $this->notifyTechnician($technician, $deleter,
            new TechnicianScheduleChangedNotification($schedule, 'deleted'), $notified);
        $this->notifyTechnician($owner, $deleter,
            new TechnicianScheduleChangedNotification($schedule, 'deleted'), $notified);

        if ($schedule->google_event_id && $schedule->google_sync_status !== 'not_connected') {
            try {
                $calendar = $this->calendarService($schedule->owner);
                $calendar->events->delete($schedule->google_calendar_id ?? 'primary', $schedule->google_event_id);
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
                    $created = $calendar->events->update(
                        $schedule->google_calendar_id ?? 'primary',
                        $schedule->google_event_id,
                        $event
                    );
                } catch (\Throwable $e) {
                    if ($e->getCode() !== 404) {
                        throw $e;
                    }
                    // event sudah dihapus di Google → buat ulang
                    $schedule->google_event_id = null;
                    $created = $calendar->events->insert('primary', $event);
                }
            } else {
                $created = $calendar->events->insert('primary', $event);
            }

            $schedule->forceFill([
                'google_event_id' => $created->getId(),
                'google_calendar_id' => 'primary',
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
        $lines = [
            'Project: '.($schedule->project?->project_name ?? '-'),
            'Customer: '.($schedule->project?->customer?->company_name ?? '-'),
            'Teknisi: '.($schedule->technician?->name ?? '-'),
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

    private function calendarService(User $user): Calendar
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

    private function restoreTokens(Client $client, User $user): void
    {
        $credential = GoogleCredential::where('user_id', $user->id)->first();

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
