<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LocksLeadLink;
use App\Http\Controllers\Concerns\RespondsAjax;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Poc;
use Illuminate\Http\Request;

class PocController extends Controller
{
    use RespondsAjax, LocksLeadLink;

    public function index(Request $request)
    {
        $query = Poc::with(['customer', 'creator', 'lead']);

        if ($request->filled('search')) {
            $query->whereHas('customer', fn ($q) => $q->whereLike('name', $request->search));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }

        $this->scopeToOwnLeads($query);

        $pocs = $query->latest('scheduled_date')->paginate(15)->withQueryString();
        $customers = Customer::orderBy('name')->get(['id', 'name']);

        return $this->ajaxPartial($request, 'sales.pocs._table', compact('pocs'), 'sales.pocs.index');
    }

    public function create(Request $request)
    {
        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $preselectedCustomerId = $request->query('customer_id');
        $preselectedLeadId = $request->query('lead_id');
        $leads = $this->leadOptions($preselectedCustomerId ? (int) $preselectedCustomerId : null);
        if ($preselectedLeadId && ($lead = Lead::with('customer')->find($preselectedLeadId))) {
            $preselectedCustomerId = $lead->customer_id;
        }

        return view('sales.pocs.create', compact('customers', 'preselectedCustomerId', 'leads', 'preselectedLeadId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'lead_id' => 'nullable|exists:leads,id',
            'type' => 'required|in:' . implode(',', Poc::TYPES),
            'scheduled_date' => 'nullable|date',
            'location' => 'nullable|string|max:500',
            'result_notes' => 'nullable|string',
        ]);

        $this->lockLeadLink($validated);

        if (!empty($validated['lead_id'])) {
            $validated['customer_id'] = Lead::find($validated['lead_id'])->customer_id;
        }

        $validated['status'] = 'scheduled';
        $validated['created_by'] = auth()->id();
        $poc = Poc::create($validated);

        if ($poc->lead_id) {
            LeadActivity::create([
                'lead_id' => $poc->lead_id,
                'user_id' => auth()->id(),
                'action' => 'poc_created',
            ]);
        }

        return $this->ajaxOrRedirect($request, 'sales.pocs.index',
            __('POC/Demo berhasil dijadwalkan.'), ['redirect' => route('sales.pocs.index')]);
    }

    public function show(Poc $poc)
    {
        $poc->load(['customer', 'creator', 'lead.customer']);

        return view('sales.pocs.show', compact('poc'));
    }

    public function edit(Poc $poc)
    {
        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $leads = $this->leadOptions($poc->customer_id);

        return view('sales.pocs.edit', compact('poc', 'customers', 'leads'));
    }

    public function update(Request $request, Poc $poc)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'lead_id' => 'nullable|exists:leads,id',
            'type' => 'required|in:' . implode(',', Poc::TYPES),
            'scheduled_date' => 'nullable|date',
            'location' => 'nullable|string|max:500',
            'status' => 'required|in:' . implode(',', Poc::STATUSES),
            'result_notes' => 'nullable|string',
        ]);

        $this->lockLeadLink($validated);

        if (!empty($validated['lead_id'])) {
            $validated['customer_id'] = Lead::find($validated['lead_id'])->customer_id;
        }

        $poc->update($validated);

        return $this->ajaxOrRedirect($request, 'sales.pocs.index',
            __('POC/Demo berhasil diupdate.'), ['redirect' => route('sales.pocs.index')]);
    }

    public function destroy(Request $request, Poc $poc)
    {
        $poc->delete();

        return $this->ajaxOrRedirect($request, 'sales.pocs.index', __('POC/Demo berhasil dihapus.'));
    }

    private function leadOptions(?int $customerId = null)
    {
        $query = Lead::with('customer')->latest()->limit(100);
        if (auth()->user()?->hasRole('sales') && !auth()->user()?->can('manage-marketing')) {
            $query->where('assigned_to', auth()->id());
        }
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        return $query->get(['id', 'customer_id', 'status', 'assigned_to']);
    }

    /**
     * Sales biasa hanya melihat POC/demo dari lead miliknya
     * (atau yang ia buat sendiri untuk data tanpa lead_id).
     */
    private function scopeToOwnLeads($query): void
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('sales')) {
            return;
        }
        if ($user->can('manage-marketing') || $user->can('manage-sales-leads')) {
            return;
        }

        $userId = $user->id;
        $query->where(function ($q) use ($userId) {
            $q->whereHas('lead', fn ($l) => $l->where('assigned_to', $userId))
                ->orWhere(fn ($w) => $w->whereNull('lead_id')->where('created_by', $userId));
        });
    }
}
