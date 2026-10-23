<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsAjax;
use App\Http\Controllers\Concerns\LocksLeadLink;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Services\SalesService;
use App\Support\PtAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FollowUpController extends Controller
{
    use RespondsAjax, LocksLeadLink;

    public function __construct(private SalesService $salesService) {}

    public function index(Request $request)
    {
        $followUps = $this->salesService->getFollowUps($request->only(['search', 'customer_id', 'lead_id', 'overdue']));

        return $this->ajaxPartial($request, 'sales.follow-ups._table',
            compact('followUps'),
            'sales.follow-ups.index');
    }

    public function create(Request $request)
    {
        $customerId = $request->integer('customer_id') ?: null;
        $leadId = $request->integer('lead_id') ?: null;
        if ($leadId && ($lead = Lead::find($leadId))) {
            $customerId = $lead->customer_id;
        }

        // Mode form: form simpel hanya saat datang dari lead yang belum pernah
        // di-follow-up. Tanpa konteks lead (tombol + halaman Follow Up) atau
        // lead yang sudah punya follow-up = form lengkap.
        $isFirstTime = $leadId !== null && ! FollowUp::where('lead_id', $leadId)->exists();

        return view('sales.follow-ups.create', [
            'customerOptions' => $this->customerOptions(auth()->user()),
            'leadItems' => $this->leadItems(auth()->user()),
            'customerId' => $customerId,
            'leadId' => $leadId,
            'isFirstTime' => $isFirstTime,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'lead_id' => 'nullable|exists:leads,id',
            'description' => 'required|string',
            'type' => 'nullable|in:'.implode(',', array_merge(FollowUp::CONTACT_TYPES, FollowUp::LEGACY_TYPES, FollowUp::LOG_TYPES)),
            'follow_up_date' => 'nullable|date',
            'activity_time' => 'nullable|date_format:H:i',
            'location' => 'nullable|string|max:150',
            'next_follow_up_date' => 'nullable|date',
        ]);

        $this->assertConsistentRelations($validated);
        $this->lockLeadLink($validated);

        $followUp = $this->salesService->createFollowUp($validated);

        // Form Log Follow Up di detail lead minta balik ke lead-nya.
        if ($request->input('return') === 'lead' && $followUp->lead_id) {
            return redirect()->route('leads.show', $followUp->lead_id)
                ->with('success', __('Follow up logged successfully.'));
        }

        // Modal tambah jadwal di Weekly Strip minta balik ke kalendernya.
        if ($request->input('return') === 'schedule') {
            return redirect()->route('sales.schedules.index', $request->filled('date') ? ['date' => substr($request->input('date'), 0, 10)] : [])
                ->with('success', __('Visit schedule added successfully.'));
        }

        return $this->ajaxOrRedirect($request, 'sales.follow-ups.index',
            __('Follow up recorded successfully.'), ['redirect' => route('sales.follow-ups.index')]);
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
        if ($followUp->customer && !isset($options[$followUp->customer_id])) {
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
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'lead_id' => 'nullable|exists:leads,id',
            'description' => 'required|string',
            'type' => 'nullable|in:'.implode(',', array_merge(FollowUp::CONTACT_TYPES, FollowUp::LEGACY_TYPES, FollowUp::LOG_TYPES)),
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
        $this->salesService->deleteFollowUp($followUp);

        return $this->ajaxOrRedirect($request, 'sales.follow-ups.index', __('Follow up deleted successfully.'));
    }

    /**
     * Legacy complete — for non-meeting types, mark as done directly.
     * For meeting types, redirect to meeting report form.
     */
    public function complete(Request $request, FollowUp $followUp)
    {
        // Meeting type: redirect to meeting report form (wajib isi foto + MOM)
        if ($followUp->type === 'meeting' && !$followUp->hasMeetingReport()) {
            $params = [];
            if ($request->input('return') === 'schedule' && $request->filled('date')) {
                $params['return'] = 'schedule';
                $params['date'] = substr($request->input('date'), 0, 10);
            }

            return redirect()->route('sales.follow-ups.meeting-report', array_merge([$followUp], $params));
        }

        $this->salesService->completeFollowUp($followUp);

        // Drawer detail di jadwal sales minta balik ke kalendernya.
        if ($request->input('return') === 'schedule') {
            return redirect()->route('sales.schedules.index', $request->filled('date') ? ['date' => substr($request->input('date'), 0, 10)] : [])
                ->with('success', __('Visit schedule completed.'));
        }

        return $this->ajaxOrRedirect($request, 'sales.follow-ups.index',
            __('Follow up completed.'), ['redirect' => route('sales.follow-ups.index')]);
    }

    /**
     * Show the meeting report form — sales must fill this before completing a meeting.
     */
    public function meetingReport(Request $request, FollowUp $followUp)
    {
        abort_if($followUp->type !== 'meeting', 422, __('Only meeting type can have a meeting report.'));
        abort_if($followUp->completed_at !== null, 422, __('This meeting has already been completed.'));

        $followUp->load(['customer', 'lead.customer', 'creator']);

        return view('sales.follow-ups.meeting-report', [
            'followUp' => $followUp,
            'returnTo' => $request->query('return'),
            'returnDate' => $request->query('date'),
        ]);
    }

    /**
     * Store meeting report (photos + MOM + outcome) and mark as completed.
     */
    public function storeMeetingReport(Request $request, FollowUp $followUp)
    {
        abort_if($followUp->type !== 'meeting', 422, __('Only meeting type can have a meeting report.'));
        abort_if($followUp->completed_at !== null, 422, __('This meeting has already been completed.'));

        $validated = $request->validate([
            'meeting_photos' => 'required|array|min:1|max:3',
            'meeting_photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            'meeting_notes' => 'required|string|min:10',
            'meeting_outcome' => 'required|in:'.implode(',', FollowUp::MEETING_OUTCOMES),
            'meeting_next_action_date' => 'nullable|date|after_or_equal:today',
        ], [
            'meeting_photos.required' => __('Please upload at least 1 photo as meeting documentation.'),
            'meeting_photos.min' => __('Please upload at least 1 photo as meeting documentation.'),
            'meeting_photos.max' => __('Maximum 3 photos allowed.'),
            'meeting_photos.*.max' => __('Each photo must be under 5 MB.'),
            'meeting_notes.required' => __('Meeting notes are required.'),
            'meeting_notes.min' => __('Meeting notes must be at least 10 characters.'),
            'meeting_outcome.required' => __('Please select the meeting outcome.'),
        ]);

        $this->salesService->completeMeetingReport($followUp, $validated);

        // Return to schedule calendar if requested
        if ($request->input('return') === 'schedule') {
            return redirect()->route('sales.schedules.index', $request->filled('date') ? ['date' => substr($request->input('date'), 0, 10)] : [])
                ->with('success', __('Meeting report submitted and marked as completed.'));
        }

        return redirect()->route('sales.follow-ups.index')
            ->with('success', __('Meeting report submitted and marked as completed.'));
    }

    public function reopen(Request $request, FollowUp $followUp)
    {
        $this->salesService->reopenFollowUp($followUp);

        return $this->ajaxOrRedirect($request, 'sales.follow-ups.index',
            __('Follow up reopened.'), ['redirect' => route('sales.follow-ups.index')]);
    }

    public function snooze(Request $request, FollowUp $followUp)
    {
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
        if (PtAccess::isSalesLike(auth()->user()) && !auth()->user()->can('manage-marketing')) {
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
        $pts = PtAccess::userPts($user);

        return Customer::query()
            ->when($pts !== [], fn ($q) => $q->where(
                fn ($w) => $w->whereIn('pt_group', $pts)->orWhereNull('pt_group')
            ))
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
     * Lead untuk dropdown: label unik "Lead #id — status — kebutuhan — tgl masuk".
     * Sales-like hanya melihat lead miliknya sendiri.
     */
    private function leadItems($user): array
    {
        $query = Lead::with('customer')->latest()->limit(200);
        if (PtAccess::isSalesLike($user) && !$user->can('manage-marketing')) {
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
        $customer = Customer::findOrFail($validated['customer_id']);
        abort_unless(PtAccess::canWritePt(auth()->user(), $customer->pt_group), 403);

        if (!empty($validated['lead_id'])) {
            $lead = Lead::find($validated['lead_id']);
            abort_if(!$lead || (int) $lead->customer_id !== (int) $validated['customer_id'],
                422, __('The selected lead does not belong to this customer.'));
        }
    }
}
