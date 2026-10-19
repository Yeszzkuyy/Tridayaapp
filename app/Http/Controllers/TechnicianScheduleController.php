<?php

namespace App\Http\Controllers;

use App\Jobs\SyncGoogleCalendarJob;
use App\Models\GoogleAppCredential;
use App\Models\Project;
use App\Models\TechnicianSchedule;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TechnicianScheduleController extends Controller
{
    public function __construct(
        protected GoogleCalendarService $google
    ) {}

    public function events(Request $request)
    {
        $schedules = $this->schedulesForRange(
            $request->query('start'),
            $request->query('end'),
            $request->query('search'),
        );

        return response()->json($schedules->map(fn ($s) => [
            'id' => $s->id,
            'title' => $s->title,
            'description' => $s->description,
            'location' => $s->location,
            'project_id' => $s->project_id,
            'technician_user_id' => $s->technician_user_id,
            'technician_ids' => $s->technicianList()->pluck('id'),
            'technicians' => $s->technicianList()->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'reminder_minutes' => $s->reminder_minutes,
            'start' => $s->start_at->setTimezone(GoogleCalendarService::TIMEZONE)->format('Y-m-d\TH:i:s'),
            'end' => $s->end_at->setTimezone(GoogleCalendarService::TIMEZONE)->format('Y-m-d\TH:i:s'),
            'status' => $s->status,
            'sync_status' => $s->google_sync_status,
            'google_event_id' => $s->google_event_id,
            'technician' => $s->technicianList()->pluck('name')->implode(', '),
            'project' => $s->project?->project_name,
            'customer' => $s->project?->customer?->name,
        ]));
    }

    public function jadwal(Request $request)
    {
        $this->autoSyncSoon();
        $technicians = User::orderBy('name')->limit(200)->get(['id', 'name']);
        $projects = Project::whereNotNull('project_name')->orderBy('project_name')->limit(200)->get(['id', 'project_name']);
        $connection = $this->google->connectionStatus(auth()->user());
        $credential = GoogleAppCredential::current();

        return view('teknisi.jadwal', compact('technicians', 'projects', 'credential', 'connection'));
    }

    /**
     * JSON feed untuk kalender: jadwal per hari (`?date=`) atau rentang
     * (`?start=&end=`). Format mirip sales.schedules.api + info teknisi.
     */
    public function getSchedulesApi(Request $request)
    {
        $this->autoSyncSoon();
        $query = TechnicianSchedule::with(['project.customer', 'technician', 'technicians', 'owner']);

        if ($request->filled('start') && $request->filled('end')) {
            try {
                $start = Carbon::createFromFormat('Y-m-d', substr($request->query('start'), 0, 10))->toDateString();
                $end = Carbon::createFromFormat('Y-m-d', substr($request->query('end'), 0, 10))->toDateString();
            } catch (\Throwable) {
                return response()->json(['message' => __('Invalid date range.')], 422);
            }
            $query->whereDate('start_at', '<=', $end)
                ->whereDate('end_at', '>=', $start);
        } else {
            $date = $request->query('date', today()->toDateString());
            try {
                $parsed = Carbon::createFromFormat('Y-m-d', substr($date, 0, 10))->toDateString();
            } catch (\Throwable) {
                return response()->json(['message' => __('Invalid date.')], 422);
            }
            $query->whereDate('start_at', '<=', $parsed)
                ->whereDate('end_at', '>=', $parsed);
        }

        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->where(function ($q) use ($s) {
                $q->whereLike('title', $s)
                    ->orWhereLike('location', $s)
                    ->orWhereHas('project', fn ($p) => $p->whereLike('project_name', $s))
                    ->orWhereHas('technician', fn ($t) => $t->whereLike('name', $s))
                    ->orWhereHas('technicians', fn ($t) => $t->whereLike('name', $s));
            });
        }

        $schedules = $query->orderBy('start_at')->get()->map(fn (TechnicianSchedule $s) => [
            'id' => $s->id,
            'title' => $s->title,
            'description' => $s->description,
            'location' => $s->location,
            'date' => $s->start_at->toDateString(),
            'start_time' => $s->start_at->format('H:i'),
            'end_time' => ($s->end_at && !$s->end_at->eq($s->start_at)) ? $s->end_at->format('H:i') : null,
            'status' => $s->status,
            'sync_status' => $s->google_sync_status,
            'technician' => $s->technicianList()->pluck('name')->implode(', '),
            'technician_user_id' => $s->technician_user_id,
            'technician_ids' => $s->technicianList()->pluck('id'),
            'technicians' => $s->technicianList()->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'project' => $s->project?->project_name,
            'customer' => $s->project?->customer?->name,
            'creator' => $s->owner?->name,
            'creator_avatar' => $s->owner?->avatar ? asset('storage/'.$s->owner->avatar) : null,
            'url' => $s->project_id ? route('projects.show', $s->project_id) : route('teknisi.jadwal'),
        ]);

        return response()->json($schedules);
    }

    /**
     * Simpan kredensial aplikasi Google (client ID/secret + API key).
     * Hanya super-admin / lead-technician.
     */
    public function saveCredentials(Request $request)
    {
        abort_unless($this->canManageApi(), 403);

        $validated = $request->validate([
            'client_id' => 'nullable|string|max:255',
            'client_secret' => 'nullable|string|max:255',
            'calendar_id' => 'nullable|string|max:255',
        ]);

        $error = $this->applyCredentials($validated);
        if ($error !== null) {
            return back()->withErrors([$error]);
        }

        return back()->with('success', __('Google Calendar credentials saved.'));
    }

    /**
     * Simpan kredensial lalu tarik event dari Google ke jadwal lokal
     * (satu tombol "Sync Now"). Hanya super-admin / lead-technician.
     */
    public function syncNow(Request $request)
    {
        abort_unless($this->canManageApi(), 403);

        $validated = $request->validate([
            'client_id' => 'nullable|string|max:255',
            'client_secret' => 'nullable|string|max:255',
            'calendar_id' => 'nullable|string|max:255',
        ]);

        $configError = $this->applyCredentials($validated);
        if ($configError !== null) {
            return back()->with('sync_message', __('Konfigurasi tidak valid — konfigurasi lama dipertahankan. :error', [
                'error' => $configError,
            ]));
        }

        try {
            $stats = $this->google->syncTwoWay();
        } catch (\Throwable $e) {
            // Logging best-effort: kegagalan menulis log tidak boleh
            // mengubah pesan ramah ini jadi error 500.
            try {
                \Illuminate\Support\Facades\Log::warning('Google sync gagal: '.$e->getMessage());
            } catch (\Throwable) {
            }

            return back()->with('sync_message', __('Gagal sinkron ke Google Calendar: :error', [
                'error' => $e->getMessage(),
            ]));
        }

        return back()->with('sync_message', __(
            'Sinkron 2-arah: :created baru, :updated diperbarui, :deleted dihapus, :pushed dikirim ke Google, :skipped dilewati.',
            [
                'created' => $stats['created'] ?? 0,
                'updated' => $stats['updated'] ?? 0,
                'deleted' => $stats['deleted'] ?? 0,
                'pushed' => $stats['pushed'] ?? 0,
                'skipped' => $stats['skipped'] ?? 0,
            ]
        ));
    }

    /**
     * Status koneksi Google Calendar (JSON) untuk badge realtime.
     */
    public function googleStatus()
    {
        return response()->json($this->google->connectionStatus(auth()->user()));
    }

    private function canManageApi(): bool
    {
        return auth()->user()?->hasAnyRole(['super-admin', 'lead-technician']) ?? false;
    }

    /**
     * Simpan input kredensial dengan aturan:
     * - Field kosong = pertahankan nilai lama, bukan menghapus (G4).
     * - Validasi dulu sebelum mengaktifkan; gagal = pertahankan lama (G6).
     * Mengembalikan pesan kesalahan yang aman, atau null bila sukses.
     */
    private function applyCredentials(array $validated): ?string
    {
        $filtered = array_filter($validated, fn ($v) => $v !== null && $v !== '');
        if ($filtered === []) {
            return null; // tidak ada perubahan
        }

        $current = GoogleAppCredential::current();
        $candidate = [
            'client_id' => $filtered['client_id'] ?? $current->client_id,
            'client_secret' => $filtered['client_secret'] ?? $current->client_secret,
            'calendar_id' => $filtered['calendar_id'] ?? $current->calendar_id,
        ];

        $error = $this->google->validateCredentials($candidate);
        if ($error !== null) {
            return $error;
        }

        try {
            $current->fill($filtered)->save();
        } catch (\Throwable $e) {
            try {
                \Illuminate\Support\Facades\Log::warning('Simpan kredensial Google gagal: '.$e->getMessage());
            } catch (\Throwable) {
            }

            return __('Kredensial Google gagal disimpan.');
        }

        $this->google->forgetReachableCache();

        return null;
    }

    /**
     * Minta sync Google berjalan di background (queue) saat halaman dibuka,
     * di-throttle maksimal 1x per 3 menit supaya tidak memanggil Google
     * berlebihan saat banyak user / banyak fetch.
     */
    private function autoSyncSoon(): void
    {
        if (\Illuminate\Support\Facades\Cache::add('gcal:last_auto_sync', true, 180)) {
            SyncGoogleCalendarJob::dispatch();
        }
    }

    public function store(Request $request)
    {
        $data = $this->validateSchedule($request);

        $schedule = $this->google->createSchedule(auth()->user(), $data);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => __('Schedule created successfully.')]);
        }

        return back()->with('success', __('Schedule created successfully.'))
            ->with('sync_message', $this->syncMessage($schedule));
    }

    public function update(Request $request, TechnicianSchedule $schedule)
    {
        // Jajaran teknisi (manage-technician) boleh merevisi jadwal tim —
        // konsisten dengan tombol Edit yang tampil untuk semua teknisi dan
        // dengan store() yang tidak membatasi pembuat. Cek ini jaring
        // pengaman bila suatu saat akses route diperlonggar.
        if ($schedule->user_id !== auth()->id() && ! auth()->user()->can('manage-technician')) {
            Log::warning('Edit jadwal teknisi ditolak (tanpa izin).', [
                'schedule_id' => $schedule->id,
                'owner_id' => $schedule->user_id,
                'user_id' => auth()->id(),
            ]);

            return $this->denyResponse($request, __('You do not have permission to edit this schedule.'));
        }

        $data = $this->validateSchedule($request);
        $schedule = $this->google->updateSchedule($schedule, $data);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => __('Jadwal berhasil diupdate.')]);
        }

        return back()->with('success', __('Jadwal berhasil diupdate.'))
            ->with('sync_message', $this->syncMessage($schedule));
    }

    public function destroy(Request $request, TechnicianSchedule $schedule)
    {
        if ($schedule->user_id !== auth()->id() && ! auth()->user()->can('manage-admin')) {
            abort(403);
        }

        $this->google->deleteSchedule($schedule);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => __('Schedule deleted successfully.')]);
        }

        return back()->with('success', __('Schedule deleted successfully.'));
    }

    public function connect()
    {
        abort_unless($this->canManageApi(), 403);

        $state = Str::random(40);
        session(['google_oauth_state' => $state]);

        return redirect($this->google->getAuthUrl(auth()->user(), $state));
    }

    public function callback(Request $request)
    {
        abort_unless($this->canManageApi(), 403);

        if (! is_string($request->query('state'))
            || ! hash_equals((string) session('google_oauth_state'), $request->query('state'))) {
            abort(403, 'OAuth state tidak valid.');
        }

        $error = $request->query('error');
        if ($error) {
            return redirect()->route('teknisi.jadwal')->with('error', 'Koneksi Google dibatalkan: '.$error);
        }

        try {
            $this->google->handleCallback(auth()->user(), $request->query('code'));
        } catch (\Throwable $e) {
            return redirect()->route('teknisi.jadwal')
                ->with('error', $e->getMessage());
        }

        session()->forget('google_oauth_state');

        return redirect()->route('teknisi.jadwal')
            ->with('success', __('Google Calendar Connected.'));
    }

    public function disconnect()
    {
        abort_unless($this->canManageApi(), 403);

        $this->google->disconnect(auth()->user());

        return back()->with('success', __('Google Calendar connection disconnected.'));
    }

    private function schedulesForRange(?string $start, ?string $end, $search = null)
    {
        return TechnicianSchedule::with(['project.customer', 'technician', 'technicians'])
            ->when($start && $end, function ($q) use ($start, $end) {
                $q->where('start_at', '<', $end)->where('end_at', '>', $start);
            })
            ->when($search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->whereLike('title', $s)
                    ->orWhereLike('location', $s)
                    ->orWhereHas('project', fn ($q) => $q->whereLike('project_name', $s))
                    ->orWhereHas('technician', fn ($q) => $q->whereLike('name', $s))
                    ->orWhereHas('technicians', fn ($q) => $q->whereLike('name', $s));
            }))
            ->orderBy('start_at')
            ->get();
    }

    private function validateSchedule(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'project_id' => 'nullable|exists:projects,id',
            'technician_ids' => 'nullable|array',
            'technician_ids.*' => 'exists:users,id',
            // Kolom lama tetap diterima sebagai fallback (kompatibilitas
            // mundur — data/pemanggil lama tidak boleh rusak).
            'technician_user_id' => 'nullable|exists:users,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'status' => 'required|in:'.implode(',', array_keys(TechnicianSchedule::STATUSES)),
            'reminder_minutes' => 'nullable|integer|min:5|max:1440',
        ], [
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
        ]);

        // Teknisi bisa lebih dari satu (survey/instalasi berangkat tim).
        // Teknisi pertama juga disalin ke kolom lama technician_user_id
        // sebagai jaring pengaman kompatibilitas (data lama tetap terbaca).
        $technicianIds = array_values(array_unique(array_map('intval', $validated['technician_ids'] ?? [])));
        if ($technicianIds === [] && filled($validated['technician_user_id'] ?? null)) {
            $technicianIds = [(int) $validated['technician_user_id']];
        }
        $validated['technician_ids'] = $technicianIds;
        $validated['technician_user_id'] = $technicianIds[0] ?? null;

        $tz = GoogleCalendarService::TIMEZONE;
        $validated['start_at'] = Carbon::parse(
            $validated['date'].' '.$validated['start_time'],
            $tz
        )->setTimezone(config('app.timezone'));

        // End time opsional: bila kosong, samakan dengan jam mulai (kolom
        // end_at not-null) dan disembunyikan di tampilan.
        $endTime = $validated['end_time'] ?? null;
        $validated['end_at'] = $endTime
            ? Carbon::parse($validated['date'].' '.$endTime, $tz)->setTimezone(config('app.timezone'))
            : $validated['start_at'];

        unset($validated['date'], $validated['start_time'], $validated['end_time']);

        return $validated;
    }

    /**
     * Respons penolakan yang jelas: JSON untuk AJAX (frontend menampilkan
     * pesannya apa adanya), redirect + error untuk request biasa.
     */
    private function denyResponse(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'message' => $message], 403);
        }

        return back()->withErrors([$message]);
    }

    private function syncMessage(TechnicianSchedule $schedule): string
    {
        return match ($schedule->google_sync_status) {
            'synced' => 'Jadwal tersinkron ke Google Calendar.',
            'syncing' => 'Jadwal disimpan, sinkronisasi Google sedang diproses.',
            'error' => 'Jadwal disimpan, tetapi sinkron Google gagal: '.$schedule->google_sync_error,
            default => 'Jadwal disimpan. Hubungkan Google Calendar agar tersinkron.',
        };
    }
}
