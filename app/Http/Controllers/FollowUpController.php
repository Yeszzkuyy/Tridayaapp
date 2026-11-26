<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LocksLeadLink;
use App\Http\Controllers\Concerns\RespondsAjax;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Rules\ActiveCustomer;
use App\Services\SalesService;
use App\Support\CustomerAccess;
use App\Support\PtAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FollowUpController extends Controller
{
    use LocksLeadLink, RespondsAjax;

    public function __construct(private SalesService $salesService) {}

    public function index(Request $request)
    {
        $filters = $request->only([
            'search', 'customer', 'customer_id', 'lead_id', 'stage',
            'status', 'activity_type', 'date_from', 'date_to', 'tab', 'overdue',
        ]);

        $followUps = $this->salesService->getFollowUps($filters);
        $counts = $this->salesService->followUpCounts($filters);
        $activeTab = $request->input('tab') ?: match ((string) $request->input('overdue')) {
            'upcoming' => 'upcoming',
            'completed' => 'completed',
            'today', '1', 'due', 'overdue' => 'due_today',
            default => 'all',
        };

        $stages = LeadController::STATUSES;
        $types = FollowUp::ACTIVITY_TYPES;

        return $this->ajaxPartial($request, 'sales.follow-ups._table',
            compact('followUps', 'counts', 'activeTab', 'stages', 'types'),
            'sales.follow-ups.index');
    }

    public function create(Request $request)
    {
        $customerId = $request->integer('customer_id') ?: null;
        $leadId = $request->integer('lead_id') ?: null;
        $returnTo = $request->input('return');
        $lead = null;

        if ($leadId && ($lead = Lead::with(['customer.contacts', 'activeFollowUp', 'latestActivityFollowUp'])->find($leadId))) {
            $customerId = $lead->customer_id;
        }

        // Mode form: form simpel hanya saat datang dari lead yang belum pernah
        // di-follow-up. Tanpa konteks lead (tombol + halaman Follow Up) atau
        // lead yang sudah punya follow-up = form lengkap.
        $isFirstTime = $leadId !== null && ! FollowUp::where('lead_id', $leadId)->exists();

        // Dari My Leads atau tombol per baris Follow Up: customer & lead yang
        // diklik sudah pasti, jadi form dikunci ke keduanya dan sisa isian
        // diambil dari data lead.
        $leadContext = in_array($returnTo, ['my-leads', 'follow-ups'], true) ? $lead : null;

        $customerOptions = $this->customerOptions(auth()->user());
        if ($leadContext?->customer && ! isset($customerOptions[$leadContext->customer_id])) {
            // Customer lead selalu bisa tampil walau di luar scope PT user;
            // form terkunci, jadi tidak ada pilihan lain yang ditawarkan.
            $customerOptions[$leadContext->customer_id] = $this->customerLabel($leadContext->customer);
        }

        return view('sales.follow-ups.create', [
            'customerOptions' => $customerOptions,
            'leadItems' => $this->leadItems(auth()->user()),
            'customerId' => $customerId,
            'leadId' => $leadId,
            'isFirstTime' => $isFirstTime,
            'returnTo' => $returnTo,
            'leadContext' => $leadContext,
            'leadSummary' => $leadContext ? $this->leadSummary($leadContext) : null,
            'prefill' => $leadContext ? $this->leadPrefill($leadContext) : [],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['required', new ActiveCustomer],
            'lead_id' => 'nullable|exists:leads,id',
            'description' => 'required|string',
            'type' => 'nullable|in:'.implode(',', FollowUp::ALLOWED_TYPES),
            'follow_up_date' => 'nullable|date',
            'activity_time' => 'nullable|date_format:H:i',
            'location' => 'nullable|string|max:150',
            'next_follow_up_date' => 'nullable|date',
            'next_follow_up_time' => 'nullable|date_format:H:i',
        ]);

        $this->assertConsistentRelations($validated);
        $this->lockLeadLink($validated);

        // "Add Follow Up" mencatat aktivitas yang sudah terjadi. Hanya modal
        // Add Schedule di kalender (return=schedule) atau tanggal aktivitas di
        // masa depan yang dianggap jadwal yang masih menunggu dikerjakan.
        // Form Log Follow Up di detail lead selalu catatan aktivitas karena
        // tidak punya kolom jadwal berikutnya.
        $returnTo = (string) $request->input('return');
        $activityDate = $validated['follow_up_date'] ?? null;
        $isPlanned = $returnTo !== 'lead'
            && ($returnTo === 'schedule' || ($activityDate !== null && Carbon::parse($activityDate)->isFuture()));

        if (! $isPlanned) {
            $validated['status'] = 'completed';
        }

        $followUp = $this->salesService->createFollowUp($validated);

        // Tanpa jadwal berikutnya, jadwal lama lead ini yang sudah jatuh tempo
        // ikut ditutup supaya tidak tertinggal sebagai Overdue. Detail lead
        // tidak menawarkan jadwal berikutnya, jadi jadwalnya dibiarkan.
        if (! $isPlanned && $returnTo !== 'lead'
            && empty($validated['next_follow_up_date']) && $followUp->lead_id) {
            $this->salesService->closeDueSchedulesForLead(
                (int) $followUp->lead_id,
                (int) $followUp->created_by,
                $followUp->id
            );
        }

        // Form Log Follow Up di detail lead minta balik ke lead-nya.
        if ($request->input('return') === 'lead' && $followUp->lead_id) {
            return redirect()->route('leads.show', $followUp->lead_id)
                ->with('success', __('Follow up logged successfully.'));
        }

        // Form follow up yang dibuka dari My Leads minta balik ke tabel itu.
        if ($request->input('return') === 'my-leads') {
            $message = __('Follow up recorded successfully.');

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => true,
                    'message' => $message,
                    'redirect' => route('sales.my-leads'),
                ]);
            }

            return redirect()->route('sales.my-leads')->with('success', $message);
        }

        // DAR cepat dari pipeline minta balik ke pipeline.
        if ($request->input('return') === 'pipeline') {
            return redirect()->route('leads.pipeline')
                ->with('success', __('Follow up logged successfully.'));
        }

        // Modal tambah jadwal di Weekly Strip minta balik ke kalendernya.
        if ($request->input('return') === 'schedule') {
            return redirect()->route('sales.schedules.index', $request->filled('date') ? ['date' => substr($request->input('date'), 0, 10)] : [])
                ->with('success', __('Visit schedule added successfully.'));
        }

        $message = __('Follow up recorded successfully.');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'redirect' => route('sales.follow-ups.index'),
            ]);
        }

        return redirect()->route('sales.follow-ups.index')
            ->with('success', $message);
    }

    public function show(FollowUp $followUp)
    {
        $followUp->load(['customer', 'creator', 'lead.customer']);

        return view('sales.follow-ups.show', compact('followUp'));
    }

    public function edit(FollowUp $followUp)
    {
        $followUp->load('customer');
        $options = $this->customerOptions(auth()->user());
        if ($followUp->customer && ! isset($options[$followUp->customer_id])) {
            $options[$followUp->customer_id] = $this->customerLabel($followUp->customer);
        }

        return view('sales.follow-ups.edit', [
            'followUp' => $followUp,
            'customerOptions' => $options,
            'leadItems' => $this->leadItems(auth()->user()),
            'customerId' => $followUp->customer_id,
            'leadId' => $followUp->lead_id,
        ]);
    }

    public function update(Request $request, FollowUp $followUp)
    {
        // Ownership check: hanya pembuat record yang boleh mengubah. Super Admin bypass.
        if (! auth()->user()->hasRole('super-admin')) {
            abort_unless($followUp->created_by !== null && (int) $followUp->created_by === (int) auth()->id(), 403);
        }

        $validated = $request->validate([
            'customer_id' => ['required', new ActiveCustomer($followUp->customer_id)],
            'lead_id' => 'nullable|exists:leads,id',
            'description' => 'required|string',
            'type' => 'nullable|in:'.implode(',', FollowUp::ALLOWED_TYPES),
            'follow_up_date' => 'nullable|date',
            'activity_time' => 'nullable|date_format:H:i',
            'location' => 'nullable|string|max:150',
            'next_follow_up_date' => 'nullable|date',
        ]);

        $this->assertConsistentRelations($validated);
        $this->lockLeadLink($validated);

        $this->salesService->updateFollowUp($followUp, $validated);

        return $this->ajaxOrRedirect($request, 'sales.follow-ups.index',
            __('Follow up updated successfully.'), ['redirect' => route('sales.follow-ups.index')]);
    }

    public function destroy(Request $request, FollowUp $followUp)
    {
        // Ownership check: hanya pembuat record yang boleh menghapus. Super Admin bypass.
        if (! auth()->user()->hasRole('super-admin')) {
            abort_unless($followUp->created_by !== null && (int) $followUp->created_by === (int) auth()->id(), 403);
        }

        $this->salesService->deleteFollowUp($followUp);

        return $this->ajaxOrRedirect($request, 'sales.follow-ups.index', __('Follow up deleted successfully.'));
    }

    /**
     * Legacy complete — for non-meeting types, mark as done directly.
     * For meeting types, redirect to meeting report form.
     */
    public function complete(Request $request, FollowUp $followUp)
    {
        // Ownership check: hanya pembuat record yang boleh menyelesaikan. Super Admin bypass.
        if (! auth()->user()->hasRole('super-admin')) {
            abort_unless($followUp->created_by !== null && (int) $followUp->created_by === (int) auth()->id(), 403);
        }

        // Meeting type: redirect to meeting report form (wajib isi foto + MOM)
        if ($followUp->type === 'meeting' && ! $followUp->hasMeetingReport()) {
            $params = [];
            if ($request->input('return') === 'schedule' && $request->filled('date')) {
                $params['return'] = 'schedule';
                $params['date'] = substr($request->input('date'), 0, 10);
            }

            return redirect()->route('sales.meetings.report', array_merge([$followUp], $params));
        }

        $validated = $request->validate([
            'outcome' => 'nullable|string|max:2000',
            'next_follow_up_date' => 'nullable|date|after_or_equal:today',
            'next_follow_up_time' => 'nullable|date_format:H:i',
        ]);

        $this->salesService->completeFollowUp($followUp, $validated);

        // Drawer detail di jadwal sales minta balik ke kalendernya.
        if ($request->input('return') === 'schedule') {
            return redirect()->route('sales.schedules.index', $request->filled('date') ? ['date' => substr($request->input('date'), 0, 10)] : [])
                ->with('success', __('Visit schedule completed.'));
        }

        return $this->ajaxOrRedirect($request, 'sales.follow-ups.index',
            __('Follow up completed.'), [
                'redirect' => route('sales.follow-ups.index', $request->only('tab')),
            ]);
    }

    public function reopen(Request $request, FollowUp $followUp)
    {
        // Ownership check: hanya pembuat record yang boleh reopen. Super Admin bypass.
        if (! auth()->user()->hasRole('super-admin')) {
            abort_unless($followUp->created_by !== null && (int) $followUp->created_by === (int) auth()->id(), 403);
        }

        $this->salesService->reopenFollowUp($followUp);

        return $this->ajaxOrRedirect($request, 'sales.follow-ups.index',
            __('Follow up reopened.'), ['redirect' => route('sales.follow-ups.index')]);
    }

    public function snooze(Request $request, FollowUp $followUp)
    {
        // Ownership check: hanya pembuat record yang boleh menunda. Super Admin bypass.
        if (! auth()->user()->hasRole('super-admin')) {
            abort_unless($followUp->created_by !== null && (int) $followUp->created_by === (int) auth()->id(), 403);
        }

        $validated = $request->validate([
            'days' => 'required_without:date|integer|min:1|max:60',
            'date' => 'required_without:days|date|after_or_equal:today',
        ]);

        if (isset($validated['date'])) {
            $this->salesService->rescheduleFollowUp($followUp, Carbon::parse($validated['date']));
        } else {
            $this->salesService->snoozeFollowUp($followUp, (int) $validated['days']);
        }

        return redirect()->route('sales.follow-ups.index')
            ->with('success', __('Follow up postponed.'));
    }

    /**
     * Opsi lead untuk form follow-up.
     */
    private function leadOptions()
    {
        $query = Lead::with('customer')->latest()->limit(100);
        if (PtAccess::isSalesLike(auth()->user()) && ! auth()->user()->can('manage-marketing')) {
            $query->where('assigned_to', auth()->id());
        }

        return $query->get(['id', 'customer_id', 'status', 'assigned_to']);
    }

    /**
     * Customer yang boleh dipilih user ini: PT dari role sales-* plus
     * customer tanpa PT (general). Tanpa role PT = semua (grandfather).
     */
    private function customerOptions($user, bool $withPtSuffix = true): array
    {
        return CustomerAccess::activeQueryFor($user)
            ->orderBy('name')
            ->get(['id', 'name', 'pt_group'])
            ->mapWithKeys(fn ($c) => [$c->id => $this->customerLabel($c, $withPtSuffix)])
            ->all();
    }

    private function customerLabel(Customer $customer, bool $withPtSuffix = true): string
    {
        return $customer->name.($withPtSuffix && $customer->pt_group ? " ({$customer->pt_group})" : '');
    }

    /**
     * Ringkasan lead untuk form follow-up dari My Leads. Nilai kosong
     * dibuang supaya baris ringkasan tidak menampilkan tanda "-".
     */
    private function leadSummary(Lead $lead): array
    {
        $customer = $lead->customer;

        return array_filter([
            'id' => $lead->id,
            'customer' => $customer?->name,
            'pt' => $customer?->pt_group,
            'pt_classes' => $customer?->pt_group ? (Lead::PT_COLORS[$customer->pt_group] ?? null) : null,
            'pic' => $customer?->picName(),
            'pic_phone' => $customer?->picPhone(),
            'project' => $lead->project_name,
            'need' => Lead::kebutuhanLabel($lead->kebutuhan, 80),
            'stage' => Lead::salesStatusLabel($lead->status),
            'stage_classes' => Lead::workspaceStageClasses($lead->status),
            'url' => route('leads.show', $lead),
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Isian awal dari data lead: tipe follow up terakhir (atau kanal kontak
     * yang tersedia). Jadwal berikutnya sengaja tidak diisi supaya kolom
     * "Next Follow-up (optional)" benar-benar opsional.
     */
    private function leadPrefill(Lead $lead): array
    {
        $last = $lead->latestActivityFollowUp;
        $type = in_array($last?->type, FollowUp::LOG_TYPES, true) ? $last->type : null;

        if ($type === null) {
            $customer = $lead->customer;
            $type = match (true) {
                (bool) $customer?->picWhatsapp() => 'whatsapp',
                (bool) $customer?->picPhone() => 'call',
                (bool) $customer?->picEmail() => 'email',
                default => null,
            };
        }

        return ['type' => $type];
    }

    /**
     * Lead untuk dropdown: label unik "Lead #id — status — kebutuhan — tgl masuk".
     * Sales-like hanya melihat lead miliknya sendiri.
     */
    private function leadItems($user): array
    {
        $query = Lead::with('customer')->latest()->limit(200);
        if (PtAccess::isSalesLike($user) && ! $user->can('manage-marketing')) {
            $query->where('assigned_to', $user->id);
        }

        return $query->get(['id', 'customer_id', 'status', 'incoming_date', 'kebutuhan', 'assigned_to'])
            ->map(fn ($lead) => [
                'id' => (string) $lead->id,
                'customer_id' => (string) $lead->customer_id,
                'label' => Lead::salesDropdownLabel($lead),
            ])->all();
    }

    /**
     * Pastikan customer/lead saling terkait dan customer
     * memang boleh ditulis user ini. Gagalkan, jangan diam-diam timpa.
     */
    private function assertConsistentRelations(array $validated): void
    {
        $user = auth()->user();
        $customer = Customer::findOrFail($validated['customer_id']);

        $lead = null;
        if (! empty($validated['lead_id'])) {
            $lead = Lead::find($validated['lead_id']);
            abort_if(! $lead || (int) $lead->customer_id !== (int) $validated['customer_id'],
                422, __('The selected lead does not belong to this customer.'));
        }

        // Customer/lead luar cakupan user = data bukan miliknya (403, bukan 422).
        // Aturan "PT-nya sendiri" hanya berlaku untuk customer yang bukan lead
        // milik user: Sales boleh mencatat follow-up untuk lead yang sudah
        // di-assign kepadanya walau customer-nya PT lain (penugasan lintas PT
        // oleh Management). Customer tanpa lead tetap dibatasi ke PT yang
        // boleh ditulis user atau pemilik customer.
        $assignedToUser = $lead !== null && $lead->assigned_to !== null
            && (int) $lead->assigned_to === (int) $user->id;
        abort_unless(
            $assignedToUser
            || $user->hasAnyPermission(['manage-sales-leads', 'manage-marketing'])
            || (PtAccess::canWritePt($user, $customer->pt_group)
                && CustomerAccess::canView($user, $customer)),
            403
        );

        // Lead bukan milik user (dan bukan pengelola) tetap ditolak.
        if ($lead !== null && ! $assignedToUser
            && ! $user->can('manage-sales-leads') && ! $user->can('manage-marketing')) {
            abort(403);
        }
    }
}
