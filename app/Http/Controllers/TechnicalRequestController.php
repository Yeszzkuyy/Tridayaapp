<?php

namespace App\Http\Controllers;

use App\Models\Instalasi;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Project;
use App\Models\Survey;
use App\Models\TechnicalRequest;
use App\Models\TechnicianSchedule;
use App\Models\User;
use App\Notifications\TechnicalRequestCreatedNotification;
use App\Notifications\TechnicalRequestReviewedNotification;
use App\Notifications\TechnicalWorkCompletedNotification;
use App\Notifications\TechnicianAssignedNotification;
use App\Rules\SecureFile;
use App\Services\GoogleCalendarService;
use App\Services\Project\FieldJobWorkflowService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TechnicalRequestController extends Controller
{
    /**
     * Buat (atau ambil) Survey untuk Technical Request tipe survey.
     *
     * IDEMPOTEN: memakai firstOrCreate dengan kunci technical_request_id
     * (juga dijamin UNIQUE di database) — memproses request yang sama
     * berapa kali pun tetap menghasilkan tepat satu Survey. Survey yang
     * pernah di-soft-delete dipulihkan, bukan digandakan.
     */
    private function ensureSurvey(TechnicalRequest $technicalRequest): ?Survey
    {
        if ($technicalRequest->request_type !== 'survey') {
            return null;
        }

        $technician = $technicalRequest->technician;
        $lead = $technicalRequest->lead;

        $survey = Survey::withTrashed()->firstOrCreate(
            ['technical_request_id' => $technicalRequest->id],
            [
                'title' => $technicalRequest->title,
                'project_id' => $technicalRequest->project_id,
                'customer_id' => $lead?->customer_id,
                'sales_user_id' => $lead?->assigned_to
                    ?? $technicalRequest->requested_by,
                'technician_user_id' => $technician?->id,
                'sales_request' => $technicalRequest->purpose
                    ?: $technicalRequest->requirement
                    ?: $technicalRequest->problem_description,
                'survey_date' => $technicalRequest->target_date,
                'location' => $technicalRequest->location ?: $lead?->customer?->address,
                'pic' => $technician?->name,
                'status' => 'scheduled',
            ]
        );

        if ($survey->trashed()) {
            // Jangan digandakan (kunci unik melarang) dan jangan dibiarkan
            // terhapus sementara request-nya masih diproses — pulihkan saja.
            $survey->restore();
        }

        // Lengkapi tautan yang belum terisi tanpa menimpa data yang sudah ada.
        $survey->fill([
            'project_id' => $survey->project_id ?: $technicalRequest->project_id,
            'customer_id' => $survey->customer_id ?: $lead?->customer_id,
            'sales_user_id' => $survey->sales_user_id ?: ($lead?->assigned_to ?? $technicalRequest->requested_by),
            'technician_user_id' => $technician?->id ?: $survey->technician_user_id,
            'pic' => $technician?->name ?: $survey->pic,
            'survey_date' => $survey->survey_date ?: $technicalRequest->target_date,
        ])->save();

        return $survey;
    }

    /**
     * Buat (atau ambil) Instalasi untuk Technical Request tipe installation.
     * Idempoten lewat kunci unik technical_request_id.
     */
    private function ensureInstallation(TechnicalRequest $technicalRequest): ?Instalasi
    {
        if ($technicalRequest->request_type !== 'installation') {
            return null;
        }

        $technician = $technicalRequest->technician;
        $lead = $technicalRequest->lead;

        $installation = Instalasi::withTrashed()->firstOrCreate(
            ['technical_request_id' => $technicalRequest->id],
            [
                'project_id' => $technicalRequest->project_id,
                'customer_id' => $lead?->customer_id,
                'sales_user_id' => $lead?->assigned_to ?? $technicalRequest->requested_by,
                'location' => $technicalRequest->location ?: $lead?->customer?->address,
                'technician_pic' => $technician?->name,
                'technician_user_id' => $technician?->id,
                'schedule_date' => $technicalRequest->target_date,
                'status' => 'scheduled',
            ]
        );

        if ($installation->trashed()) {
            $installation->restore();
        }

        $installation->fill([
            'project_id' => $installation->project_id ?: $technicalRequest->project_id,
            'customer_id' => $installation->customer_id ?: $lead?->customer_id,
            'sales_user_id' => $installation->sales_user_id ?: ($lead?->assigned_to ?? $technicalRequest->requested_by),
            'location' => $installation->location ?: ($technicalRequest->location ?: $lead?->customer?->address),
            'technician_pic' => $technician?->name ?: $installation->technician_pic,
            'technician_user_id' => $technician?->id ?: $installation->technician_user_id,
            'schedule_date' => $installation->schedule_date ?: $technicalRequest->target_date,
        ])->save();

        return $installation;
    }

    /**
     * Tautkan request ke project bila lead hanya punya satu project.
     */
    private function resolveProjectId(TechnicalRequest $technicalRequest): ?int
    {
        if ($technicalRequest->project_id) {
            return (int) $technicalRequest->project_id;
        }

        $lead = $technicalRequest->lead;
        if (! $lead) {
            return null;
        }

        $projectIds = Project::query()
            ->whereHas('proposal', fn ($q) => $q->where('lead_id', $lead->id))
            ->pluck('id')
            ->unique()
            ->values();

        return $projectIds->count() === 1 ? (int) $projectIds->first() : null;
    }

    /** Kirim notifikasi ke sales peminta — lewati bila dia sendiri yang beraksi. */
    private function notifyRequester(TechnicalRequest $technicalRequest, $notification): void
    {
        $requester = $technicalRequest->requester;
        if ($requester && (int) $requester->id !== (int) auth()->id()) {
            $requester->notify($notification);
        }
    }

    private function log(Lead $lead, string $action, array $changes = []): void
    {
        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'changes' => $changes,
        ]);
    }

    /**
     * Koordinator teknis: Lead Technician dan Super Admin. Sengaja TIDAK
     * memakai permission manage-technician karena role technician juga
     * memegang permission itu — kalau dipakai, semua teknisi bisa melihat
     * dan mengubah request milik teknisi lain.
     */
    private function isTechnicalCoordinator(): bool
    {
        return auth()->user()?->hasAnyRole(['lead-technician', 'super-admin']) ?? false;
    }

    private function authorizeView(TechnicalRequest $request): void
    {
        $user = auth()->user();

        // Koordinator teknis boleh memonitor semua request. Engineer utama
        // dan support hanya boleh membuka request yang ditugaskan kepadanya
        // atau yang ia buat sendiri.
        if ($this->isTechnicalCoordinator()
            || $request->isAssignedTo($user)
            || (int) $request->requested_by === (int) $user->id) {
            return;
        }

        $this->authorize('view', $request->lead);

        $involved = (int) $request->lead->assigned_to === (int) $user->id;

        abort_unless($involved || $user->can('manage-sales-leads'), 403);
    }

    public function create(Request $request)
    {
        $lead = $request->filled('lead_id') ? Lead::findOrFail($request->get('lead_id')) : null;
        if ($lead) {
            $this->authorize('view', $lead);
        }

        // Tanpa lead bawaan: tampilkan pemilih lead (sales biasa hanya miliknya).
        $leads = null;
        if (! $lead) {
            $leadsQuery = Lead::with('customer')->latest()->limit(100);
            $user = $request->user();
            if ($user && $user->hasRole('sales') && ! $user->can('manage-marketing') && ! $user->can('manage-sales-leads')) {
                $leadsQuery->where('assigned_to', $user->id);
            }
            $leads = $leadsQuery->get();
        }

        $backUrl = $lead
            ? route('leads.show', $lead)
            : route('sales.requests.index', ['tab' => 'engineer']);

        return view('sales.technical-requests.create', compact('lead', 'leads', 'backUrl'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'request_type' => 'required|in:'.implode(',', TechnicalRequest::TYPES),
            'title' => 'required|string|max:255',
            'purpose' => 'nullable|string|max:2000',
            'location' => 'nullable|string|max:255',
            'pic_contact_name' => 'nullable|string|max:150',
            'pic_contact_phone' => 'nullable|string|max:40',
            'target_date' => 'nullable|date|after_or_equal:today',
            'target_time' => 'nullable|date_format:H:i',
            'attachment' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,txt,csv,doc,docx,xls,xlsx',
            'notes' => 'nullable|string',
        ]);

        $lead = Lead::findOrFail($validated['lead_id']);
        $this->authorize('view', $lead);

        // Priority tidak lagi diisi sales; default normal. PIC hanya untuk Instalasi.
        $isInstallation = $validated['request_type'] === 'installation';

        $technicalRequest = TechnicalRequest::create([
            ...collect($validated)->except('attachment')->all(),
            'priority' => 'normal',
            'project_id' => $this->resolveProjectId(new TechnicalRequest(['lead_id' => $lead->id])),
            'pic_contact_name' => $isInstallation ? ($validated['pic_contact_name'] ?? null) : null,
            'pic_contact_phone' => $isInstallation ? ($validated['pic_contact_phone'] ?? null) : null,
            'requested_by' => auth()->id(),
            'status' => 'waiting_lead',
        ]);

        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->storeAs(
                'tech-requests/'.$technicalRequest->id,
                time().'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $request->file('attachment')->getClientOriginalName()),
                'private'
            );
            $technicalRequest->update(['attachment_path' => $path]);
        }

        $this->log($lead, 'tech_request_created', ['title' => $technicalRequest->title]);
        $this->log($lead, 'tech_request_sent_to_lead', ['title' => $technicalRequest->title]);

        // Jangan notifikasi pembuat request sendiri (mis. super-admin / multi-role
        // yang juga memegang role lead-technician).
        Notification::send(
            User::role('lead-technician')->where('id', '!=', $technicalRequest->requested_by)->get(),
            new TechnicalRequestCreatedNotification($technicalRequest)
        );

        return redirect()->route('sales.technical-requests.show', $technicalRequest)
            ->with('success', __('Technical request sent to the Lead Technician.'));
    }

    /**
     * Buat jadwal teknisi dari request (idempoten) dan tolak bila bentrok.
     */
    private function ensureScheduleFromRequest(TechnicalRequest $technicalRequest, User $technician, ?array $engineerIds = null): ?TechnicianSchedule
    {
        if (! $technicalRequest->target_date) {
            return null;
        }

        // Engineer utama + semua support ikut masuk jadwal. Assign mengirim
        // daftar eksplisit supaya validasi bentrok berjalan SEBELUM data
        // request diubah (tidak ada penugasan setengah jadi).
        $engineerIds ??= $technicalRequest->assignedEngineerIds();
        $existing = TechnicianSchedule::where('technical_request_id', $technicalRequest->id)->first();

        if ($existing) {
            // Assign ulang menghasilkan jadwal yang sama, tetapi daftar
            // engineer-nya harus mengikuti pilihan terbaru.
            $current = $existing->technicianList()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $desired = collect($engineerIds)->sort()->values()->all();

            if ($current !== $desired) {
                $this->assertNoScheduleConflict(
                    $engineerIds,
                    (int) $technician->id,
                    $existing->id,
                    $existing->start_at,
                    $existing->end_at,
                );

                app(GoogleCalendarService::class)->updateSchedule($existing, [
                    'technician_ids' => $engineerIds,
                ]);
            }

            return $existing;
        }

        $start = $technicalRequest->target_time
            ? Carbon::parse($technicalRequest->target_date->format('Y-m-d').' '.$technicalRequest->target_time)
            : $technicalRequest->target_date->copy()->setTime(9, 0);
        $end = $start->copy()->addHours(2);

        $this->assertNoScheduleConflict(
            $engineerIds,
            (int) $technician->id,
            null,
            $start,
            $end,
        );

        return app(GoogleCalendarService::class)->createSchedule(Auth::user(), [
            'technical_request_id' => $technicalRequest->id,
            'title' => $technicalRequest->title,
            'description' => $technicalRequest->purpose ?: $technicalRequest->problem_description,
            'location' => $technicalRequest->location,
            'start_at' => $start,
            'end_at' => $end,
            'status' => 'scheduled',
            'technician_ids' => $engineerIds,
        ]);
    }

    /**
     * Tolak assign bila salah satu engineer (utama/support) sudah punya
     * jadwal yang bertabrakan.
     *
     * @param  array<int, int>  $engineerIds
     */
    private function assertNoScheduleConflict(
        array $engineerIds,
        int $mainEngineerId,
        ?int $ignoreScheduleId,
        Carbon $start,
        Carbon $end,
    ): void {
        foreach (array_values(array_unique($engineerIds)) as $id) {
            $conflict = TechnicianSchedule::query()
                ->when($ignoreScheduleId, fn ($query) => $query->whereKeyNot($ignoreScheduleId))
                ->where('status', '!=', 'cancelled')
                ->where('start_at', '<', $end)
                ->where('end_at', '>', $start)
                ->where(function ($query) use ($id) {
                    $query->where('technician_user_id', $id)
                        ->orWhereHas('technicians', fn ($technician) => $technician->where('users.id', $id));
                })
                ->orderBy('start_at')
                ->first();

            if (! $conflict) {
                continue;
            }

            $name = User::find($id)?->name ?? __('Engineer');

            throw ValidationException::withMessages([
                $id === $mainEngineerId ? 'technician_id' : 'support_ids' => __(':name already has :title at :time. Choose another technician or date.', [
                    'name' => $name,
                    'title' => $conflict->title,
                    'time' => $conflict->start_at->format('d M Y H:i'),
                ]),
            ]);
        }
    }

    public function show(TechnicalRequest $technicalRequest)
    {
        $technicalRequest->load(['lead.customer', 'requester', 'technician', 'supportUsers', 'project', 'survey', 'installation', 'schedules']);
        $this->authorizeView($technicalRequest);

        $technicians = $this->isTechnicalCoordinator()
            ? User::role('technician')->orderBy('name')->get(['id', 'name'])
            : collect();

        $workflow = app(FieldJobWorkflowService::class)->evaluate($technicalRequest);

        return view('sales.technical-requests.show', compact('technicalRequest', 'technicians', 'workflow'));
    }

    public function review(Request $request, TechnicalRequest $technicalRequest)
    {
        abort_unless($this->isTechnicalCoordinator(), 403);
        $this->authorizeView($technicalRequest);
        abort_unless($technicalRequest->status === 'waiting_lead', 422);

        $validated = $request->validate(['decision' => 'required|in:accepted,rejected']);

        $technicalRequest->update(['status' => $validated['decision']]);
        $this->log($technicalRequest->lead, 'tech_request_'.$validated['decision'], ['title' => $technicalRequest->title]);
        $this->notifyRequester($technicalRequest, new TechnicalRequestReviewedNotification($technicalRequest, $validated['decision']));

        return back()->with('success', __('Request telah di-'.$validated['decision'].'.'));
    }

    public function assign(Request $request, TechnicalRequest $technicalRequest)
    {
        abort_unless($this->isTechnicalCoordinator(), 403);
        $this->authorizeView($technicalRequest);
        abort_unless(in_array($technicalRequest->status, ['accepted', 'assigned']), 422);

        $validated = $request->validate([
            'technician_id' => 'required|exists:users,id',
            'support_ids' => 'nullable|array',
            'support_ids.*' => [
                'integer',
                'exists:users,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! User::find($value)?->isTechnician()) {
                        $fail(__('The selected support engineer is not a technician.'));
                    }
                },
            ],
        ]);

        $technician = User::findOrFail($validated['technician_id']);
        abort_unless($technician->isTechnician(), 422, __('Only users with the Technician role can be assigned.'));

        // Support engineer boleh banyak (5/6 orang), tetapi engineer utama
        // tidak boleh muncul dua kali di daftar support.
        $supportIds = collect($validated['support_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === (int) $technician->id)
            ->unique()
            ->values();

        // Form mengirim penanda agar daftar support kosong pun bisa
        // disimpan (menghapus semua support). Pemanggil lama tanpa field
        // ini tidak menghapus support yang sudah ada.
        $shouldSyncSupport = $request->has('support_ids') || $request->boolean('support_present');

        $engineerIds = array_values(array_unique(array_merge([(int) $technician->id], $supportIds->all())));

        // Validasi bentrok jadwal dilakukan SEBELUM data request diubah,
        // supaya assign yang gagal tidak meninggalkan penugasan setengah jadi.
        $this->ensureScheduleFromRequest($technicalRequest, $technician, $engineerIds);

        if ($shouldSyncSupport) {
            $technicalRequest->supportUsers()->sync($supportIds->all());
        }

        $technicalRequest->update([
            'assigned_technician_id' => $technician->id,
            'project_id' => $this->resolveProjectId($technicalRequest),
            'status' => 'assigned',
        ]);

        $technicalRequest->refresh();

        // Request tipe survey/installation: siapkan data lapangan untuk
        // dikerjakan teknisi. Idempoten — request yang sama tetap satu data.
        $this->ensureSurvey($technicalRequest->fresh());
        $this->ensureInstallation($technicalRequest->fresh());

        $this->log($technicalRequest->lead, 'technician_assigned', [
            'title' => $technicalRequest->title,
            'technician' => $technician->name,
            'support_engineers' => $technicalRequest->supportUsers->pluck('name')->implode(', '),
        ]);

        // Engineer utama DAN support selalu diberi tahu detail task yang
        // di-assign, supaya tidak bingung saat klik notifikasi.
        foreach ($technicalRequest->supportUsers->push($technician)->unique('id') as $engineer) {
            if ((int) $engineer->id !== (int) auth()->id()) {
                $engineer->notify(new TechnicianAssignedNotification($technicalRequest->fresh()));
            }
        }

        $this->notifyRequester($technicalRequest, new TechnicalRequestReviewedNotification($technicalRequest, 'assigned'));

        return back()->with('success', __('Technician assigned successfully.'));
    }

    public function progress(Request $request, TechnicalRequest $technicalRequest)
    {
        $this->authorizeWork($technicalRequest);
        abort_unless(in_array($technicalRequest->status, ['assigned', 'in_progress']), 422);

        $validated = $request->validate(['notes' => 'nullable|string|max:2000']);

        $technicalRequest->update([
            'status' => 'in_progress',
            'notes' => $validated['notes'] ?? $technicalRequest->notes,
        ]);

        return back()->with('success', __('Progress updated.'));
    }

    public function result(Request $request, TechnicalRequest $technicalRequest)
    {
        $this->authorizeWork($technicalRequest);
        abort_unless(in_array($technicalRequest->status, ['assigned', 'in_progress']), 422);

        // Hasil teknis hanya bisa diisi pelaksana (teknisi yang di-assign) — Sales dilarang.
        $validated = $request->validate(['technical_result' => 'required|string']);

        $technicalRequest->update(['technical_result' => $validated['technical_result']]);
        $this->log($technicalRequest->lead, 'technical_result_submitted', ['title' => $technicalRequest->title]);

        return back()->with('success', __('Technical results saved.'));
    }

    public function complete(TechnicalRequest $technicalRequest)
    {
        $user = auth()->user();
        abort_unless(
            $technicalRequest->isAssignedTo($user) || $this->isTechnicalCoordinator(),
            403
        );
        $this->authorizeView($technicalRequest);
        abort_unless(in_array($technicalRequest->status, ['assigned', 'in_progress']), 422);
        abort_unless(filled($technicalRequest->technical_result), 422, __('Fill in the technical results before completing.'));

        // Data lama / request yang di-assign sebelum integrasi ini mungkin
        // belum punya record lapangan. Siapkan idempoten sebelum validasi.
        $this->ensureSurvey($technicalRequest);
        $this->ensureInstallation($technicalRequest);
        $technicalRequest->refresh();

        // Konsistensi status: request tipe survey TIDAK boleh selesai sebelum
        // Survey-nya punya laporan — jangan sampai request menyatakan selesai
        // padahal laporan belum ada.
        $survey = null;
        if ($technicalRequest->request_type === 'survey') {
            $survey = $technicalRequest->survey;
            abort_unless(
                $survey?->hasReport(),
                422,
                __('The survey has no report yet. Upload the survey report before completing this request.')
            );
        }

        // Instalasi baru boleh selesai setelah laporan teknis dan BAST
        // bertanda tangan kedua pihak tersedia.
        $installation = null;
        if ($technicalRequest->request_type === 'installation') {
            $installation = $technicalRequest->installation;
            abort_unless(
                $installation?->hasReport(),
                422,
                __('The installation has no report yet. Upload the installation report before completing this request.')
            );

            // BAST wajib hanya untuk instalasi non-project. Instalasi di dalam
            // project memakai BAST project yang dikelola Admin.
            if (blank($technicalRequest->project_id)) {
                abort_unless(
                    $installation?->isBastReady(),
                    422,
                    __('The installation BAST is not signed yet. Upload the signed BAST before completing this request.')
                );
            }
        }

        $technicalRequest->update(['status' => 'completed', 'completed_at' => now()]);

        // Baru setelah request selesai tersimpan, status data lapangan ikut
        // diperbarui agar konsisten.
        $survey?->update(['status' => Survey::STATUS_COMPLETED]);
        $installation?->update([
            'status' => 'completed',
            'completed_at' => $installation->completed_at ?? now(),
            'completed_by' => $installation->completed_by ?? auth()->id(),
        ]);

        $this->log($technicalRequest->lead, 'tech_request_completed', ['title' => $technicalRequest->title]);
        $this->notifyRequester($technicalRequest, new TechnicalWorkCompletedNotification($technicalRequest));

        return back()->with('success', __('Technical request completed.'));
    }

    public function cancel(TechnicalRequest $technicalRequest)
    {
        $user = auth()->user();
        abort_unless(
            (int) $technicalRequest->requested_by === (int) $user->id
                || $user->can('manage-sales-leads'),
            403
        );
        $this->authorizeView($technicalRequest);
        abort_unless(! in_array($technicalRequest->status, ['completed', 'cancelled']), 422);

        $technicalRequest->update(['status' => 'cancelled']);
        $this->log($technicalRequest->lead, 'tech_request_cancelled', ['title' => $technicalRequest->title]);

        return back()->with('success', __('Technical request canceled.'));
    }

    public function downloadAttachment(TechnicalRequest $technicalRequest)
    {
        $this->authorizeView($technicalRequest);
        abort_unless($technicalRequest->attachment_path && Storage::disk('private')->exists($technicalRequest->attachment_path), 404);

        return Storage::disk('private')->download($technicalRequest->attachment_path);
    }

    /**
     * Pratinjau lampiran request teknis: PDF/gambar/teks tampil di browser,
     * format lain tetap diunduh.
     */
    public function previewAttachment(TechnicalRequest $technicalRequest)
    {
        $this->authorizeView($technicalRequest);
        abort_unless($technicalRequest->attachment_path && Storage::disk('private')->exists($technicalRequest->attachment_path), 404);

        return SecureFile::fileResponse(
            'private',
            $technicalRequest->attachment_path,
            basename($technicalRequest->attachment_path)
        );
    }

    private function authorizeWork(TechnicalRequest $technicalRequest): void
    {
        $this->authorizeView($technicalRequest);
        // Sales (termasuk requester) dilarang menyentuh progres/hasil teknis.
        // Engineer utama, support, dan super-admin boleh bekerja.
        abort_unless(
            $technicalRequest->isAssignedTo(auth()->user())
                || (auth()->user()?->hasRole('super-admin') ?? false),
            403
        );
    }
}
