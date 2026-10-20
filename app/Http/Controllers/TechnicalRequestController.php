<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Survey;
use App\Models\TechnicalRequest;
use App\Models\User;
use App\Notifications\TechnicalRequestCreatedNotification;
use App\Notifications\TechnicalRequestReviewedNotification;
use App\Notifications\TechnicalWorkCompletedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

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

        $survey = Survey::withTrashed()->firstOrCreate(
            ['technical_request_id' => $technicalRequest->id],
            [
                'title' => $technicalRequest->title,
                'customer_id' => $technicalRequest->lead?->customer_id,
                'sales_user_id' => $technicalRequest->lead?->assigned_to
                    ?? $technicalRequest->requested_by,
                'sales_request' => $technicalRequest->purpose
                    ?: $technicalRequest->requirement
                    ?: $technicalRequest->problem_description,
                'survey_date' => $technicalRequest->target_date,
                'location' => $technicalRequest->location ?: $technicalRequest->lead?->customer?->address,
                'status' => 'scheduled',
            ]
        );

        if ($survey->trashed()) {
            // Jangan digandakan (kunci unik melarang) dan jangan dibiarkan
            // terhapus sementara request-nya masih diproses — pulihkan saja.
            $survey->restore();
        }

        return $survey;
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

    private function authorizeView(TechnicalRequest $request): void
    {
        $user = auth()->user();

        // Alur teknisi + requester sendiri: tanpa LeadPolicy view agar
        // Lead Teknisi bisa memonitor dan teknisi bisa mengerjakan.
        if ($user->can('manage-technician')
            || (int) $request->assigned_technician_id === (int) $user->id
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
        if (!$lead) {
            $leadsQuery = Lead::with('customer')->latest()->limit(100);
            $user = $request->user();
            if ($user && $user->hasRole('sales') && !$user->can('manage-marketing') && !$user->can('manage-sales-leads')) {
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
            'request_type' => 'required|in:' . implode(',', TechnicalRequest::TYPES),
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
            'pic_contact_name' => $isInstallation ? ($validated['pic_contact_name'] ?? null) : null,
            'pic_contact_phone' => $isInstallation ? ($validated['pic_contact_phone'] ?? null) : null,
            'requested_by' => auth()->id(),
            'status' => 'waiting_lead',
        ]);

        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->storeAs(
                'tech-requests/' . $technicalRequest->id,
                time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $request->file('attachment')->getClientOriginalName()),
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
            ->with('success', __('Technical request sent to the Lead Technician.'))
            ->with('success_card', true);
    }

    public function show(TechnicalRequest $technicalRequest)
    {
        $technicalRequest->load(['lead.customer', 'requester', 'technician']);
        $this->authorizeView($technicalRequest);

        $technicians = auth()->user()->can('manage-technician')
            ? User::role('technician')->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('sales.technical-requests.show', compact('technicalRequest', 'technicians'));
    }

    public function review(Request $request, TechnicalRequest $technicalRequest)
    {
        abort_unless(auth()->user()->can('manage-technician'), 403);
        $this->authorizeView($technicalRequest);
        abort_unless($technicalRequest->status === 'waiting_lead', 422);

        $validated = $request->validate(['decision' => 'required|in:accepted,rejected']);

        $technicalRequest->update(['status' => $validated['decision']]);
        $this->log($technicalRequest->lead, 'tech_request_' . $validated['decision'], ['title' => $technicalRequest->title]);
        $this->notifyRequester($technicalRequest, new TechnicalRequestReviewedNotification($technicalRequest, $validated['decision']));

        return back()->with('success', __('Request telah di-' . $validated['decision'] . '.'))->with('success_card', true);
    }

    public function assign(Request $request, TechnicalRequest $technicalRequest)
    {
        abort_unless(auth()->user()->can('manage-technician'), 403);
        $this->authorizeView($technicalRequest);
        abort_unless(in_array($technicalRequest->status, ['accepted', 'assigned']), 422);

        $validated = $request->validate(['technician_id' => 'required|exists:users,id']);

        $technician = User::findOrFail($validated['technician_id']);
        abort_unless($technician->hasRole('technician'), 422, __('Only users with the Technician role can be assigned.'));

        $technicalRequest->update([
            'assigned_technician_id' => $technician->id,
            'status' => 'assigned',
        ]);

        // Request tipe survey: buatkan Survey untuk dikerjakan teknisi.
        // Idempoten — request yang sama diproses ulang tetap satu survey.
        // Notifikasi ke sales cukup satu (di bawah, 'assigned'); survey adalah
        // artefak internal tim teknisi, jadi tidak dibuatkan notifikasi sendiri.
        $this->ensureSurvey($technicalRequest);

        $this->log($technicalRequest->lead, 'technician_assigned', [
            'title' => $technicalRequest->title,
            'technician' => $technician->name,
        ]);
        $this->notifyRequester($technicalRequest, new TechnicalRequestReviewedNotification($technicalRequest, 'assigned'));

        return back()->with('success', __('Technician assigned successfully.'))->with('success_card', true);
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

        return back()->with('success', __('Progress updated.'))->with('success_card', true);
    }

    public function result(Request $request, TechnicalRequest $technicalRequest)
    {
        $this->authorizeWork($technicalRequest);
        abort_unless(in_array($technicalRequest->status, ['assigned', 'in_progress']), 422);

        // Hasil teknis hanya bisa diisi pelaksana (teknisi yang di-assign) — Sales dilarang.
        $validated = $request->validate(['technical_result' => 'required|string']);

        $technicalRequest->update(['technical_result' => $validated['technical_result']]);
        $this->log($technicalRequest->lead, 'technical_result_submitted', ['title' => $technicalRequest->title]);

        return back()->with('success', __('Technical results saved.'))->with('success_card', true);
    }

    public function complete(TechnicalRequest $technicalRequest)
    {
        $user = auth()->user();
        abort_unless(
            (int) $technicalRequest->assigned_technician_id === (int) $user->id
                || $user->can('manage-technician'),
            403
        );
        $this->authorizeView($technicalRequest);
        abort_unless(in_array($technicalRequest->status, ['assigned', 'in_progress']), 422);
        abort_unless(filled($technicalRequest->technical_result), 422, __('Fill in the technical results before completing.'));

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

        $technicalRequest->update(['status' => 'completed', 'completed_at' => now()]);

        // Baru setelah request selesai tersimpan, status survey ikut
        // diperbarui agar konsisten.
        $survey?->update(['status' => Survey::STATUS_COMPLETED]);

        $this->log($technicalRequest->lead, 'tech_request_completed', ['title' => $technicalRequest->title]);
        $this->notifyRequester($technicalRequest, new TechnicalWorkCompletedNotification($technicalRequest));

        return back()->with('success', __('Technical request completed.'))->with('success_card', true);
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

        return back()->with('success', __('Technical request canceled.'))->with('success_card', true);
    }

    public function downloadAttachment(TechnicalRequest $technicalRequest)
    {
        $this->authorizeView($technicalRequest);
        abort_unless($technicalRequest->attachment_path && Storage::disk('private')->exists($technicalRequest->attachment_path), 404);

        return Storage::disk('private')->download($technicalRequest->attachment_path);
    }

    private function authorizeWork(TechnicalRequest $technicalRequest): void
    {
        $this->authorizeView($technicalRequest);
        // Sales (termasuk requester) dilarang menyentuh progres/hasil teknis.
        abort_unless(
            (int) $technicalRequest->assigned_technician_id === (int) auth()->id(),
            403
        );
    }
}
