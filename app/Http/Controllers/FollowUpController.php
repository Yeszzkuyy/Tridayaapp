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

        return view('sales.follow-ups.create', [
            'customerOptions' => $this->customerOptions(auth()->user()),
            'leadItems' => $this->leadItems(auth()->user()),
            'customerId' => $customerId,
            'leadId' => $leadId,
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
                ->with('success', __('Log follow up berhasil dicatat.'));
        }

        return $this->ajaxOrRedirect($request, 'sales.follow-ups.index',
            __('Follow up berhasil dicatat.'), ['redirect' => route('sales.follow-ups.index')]);
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
            __('Follow up berhasil diupdate.'), ['redirect' => route('sales.follow-ups.index')]);
    }

    public function destroy(Request $request, FollowUp $followUp)
    {
        $this->salesService->deleteFollowUp($followUp);

        return $this->ajaxOrRedirect($request, 'sales.follow-ups.index', __('Follow up berhasil dihapus.'));
    }

    public function complete(Request $request, FollowUp $followUp)
    {
        $this->salesService->completeFollowUp($followUp);

        return $this->ajaxOrRedirect($request, 'sales.follow-ups.index',
            __('Follow up selesai.'), ['redirect' => route('sales.follow-ups.index')]);
    }

    public function reopen(Request $request, FollowUp $followUp)
    {
        $this->salesService->reopenFollowUp($followUp);

        return $this->ajaxOrRedirect($request, 'sales.follow-ups.index',
            __('Follow up dibuka lagi.'), ['redirect' => route('sales.follow-ups.index')]);
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
            ->with('success', __('Follow up ditunda.'));
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
     * Lead untuk dropdown: label unik "Lead #id — status — tgl masuk".
     * Sales-like hanya melihat lead miliknya sendiri.
     */
    private function leadItems($user): array
    {
        $query = Lead::with('customer')->latest()->limit(200);
        if (PtAccess::isSalesLike($user) && !$user->can('manage-marketing')) {
            $query->where('assigned_to', $user->id);
        }

        return $query->get(['id', 'customer_id', 'status', 'incoming_date', 'assigned_to'])
            ->map(fn ($lead) => [
                'id' => (string) $lead->id,
                'customer_id' => (string) $lead->customer_id,
                'label' => "Lead #{$lead->id} — ".Lead::salesStatusLabel($lead->status)
                    .($lead->incoming_date ? ' — '.$lead->incoming_date->format('d M Y') : ''),
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
                422, __('Lead yang dipilih bukan milik customer ini.'));
        }
    }
}
