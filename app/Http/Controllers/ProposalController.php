<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Notifications\ProposalStatusNotification;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProposalController extends Controller
{
    private function log(Lead $lead, string $action, array $changes = []): void
    {
        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'changes' => $changes,
        ]);
    }

    private function authorizeProposal(Proposal $proposal): void
    {
        $this->authorize('view', $proposal->lead);

        $user = auth()->user();
        $owner = in_array($user->id, [$proposal->created_by, $proposal->lead->assigned_to]);

        abort_unless($owner || $user->can('manage-sales-leads'), 403);
    }

    private function authorizeEdit(Proposal $proposal): void
    {
        $this->authorizeProposal($proposal);
        abort_unless(in_array($proposal->status, ['draft', 'revision']), 422);
    }

    private function generateNumber(): string
    {
        $prefix = 'Q/' . now()->format('Y/m') . '/';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $seq = Proposal::where('proposal_number', 'like', $prefix . '%')->count() + 1 + $attempt;
            $number = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

            if (! Proposal::where('proposal_number', $number)->exists()) {
                return $number;
            }
        }

        return $prefix . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function itemRules(): array
    {
        return [
            'items' => 'required|array|min:1|max:50',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01|max:999999',
            'items.*.unit' => 'nullable|string|max:16',
            'items.*.unit_price' => 'required|numeric|min:0|max:999999999999',
            'items.*.discount' => 'nullable|numeric|min:0|max:999999999999',
        ];
    }

    private function persistItems(Proposal $proposal, array $items): void
    {
        $proposal->items()->delete();

        foreach ($items as $item) {
            $qty = (float) $item['quantity'];
            $price = (float) $item['unit_price'];
            $disc = (float) ($item['discount'] ?? 0);

            $proposal->items()->create([
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $qty,
                'unit' => $item['unit'] ?? 'pcs',
                'unit_price' => $price,
                'discount' => $disc,
                'subtotal' => ProposalItem::subtotalFor($qty, $price, $disc),
            ]);
        }

        $proposal->recalculate();
    }

    public function index(Request $request)
    {
        $base = $this->scopedQuery();

        $kpi = [
            'draft' => (clone $base)->where('status', 'draft')->count(),
            'pending_technical' => (clone $base)->where('status', 'pending_technical')->count(),
            'sent' => (clone $base)->where('status', 'sent')->count(),
            'viewed' => (clone $base)->where('status', 'viewed')->count(),
            'expiring' => (clone $base)->whereIn('status', ['ready', 'sent', 'viewed'])
                ->whereNotNull('valid_until')
                ->whereDate('valid_until', '>=', today())
                ->whereDate('valid_until', '<=', today()->addDays(7))->count(),
            'won_month' => (clone $base)->where('status', 'accepted')
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)->count(),
        ];

        $query = (clone $base)->with(['lead.customer', 'creator'])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(fn ($q) => $q->whereLike('proposal_number', "%{$search}%")
                ->orWhereLike('customer', "%{$search}%"));
        }

        $proposals = $query->paginate(15)->withQueryString();
        $statuses = Proposal::STATUSES;

        return view('sales.proposals.index', compact('proposals', 'kpi', 'statuses'));
    }

    /** Scope proposal milik user (sales biasa hanya buatannya / lead-nya). */
    private function scopedQuery()
    {
        $user = auth()->user();

        $query = Proposal::query();

        if (! $user->can('manage-sales-leads')) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhereHas('lead', fn ($l) => $l->where('assigned_to', $user->id));
            });
        }

        return $query;
    }

    public function create(Request $request)
    {
        $lead = Lead::with('customer')->findOrFail($request->get('lead_id'));
        $this->authorize('view', $lead);

        $completedTech = $lead->technicalRequests()->where('status', 'completed')->latest()->first();
        $prefillNotes = $lead->kebutuhan;

        return view('sales.proposals.create', compact('lead', 'completedTech', 'prefillNotes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate(array_merge([
            'lead_id' => 'required|exists:leads,id',
            'proposal_date' => 'required|date',
            'valid_until' => 'nullable|date|after_or_equal:proposal_date',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ], $this->itemRules()));

        $lead = Lead::with('customer')->findOrFail($validated['lead_id']);
        $this->authorize('view', $lead);

        try {
            $proposal = DB::transaction(function () use ($validated, $lead) {
                $proposal = Proposal::create([
                    'lead_id' => $lead->id,
                    'proposal_number' => $this->generateNumber(),
                    'created_by' => auth()->id(),
                    'proposal_date' => $validated['proposal_date'],
                    'valid_until' => $validated['valid_until'] ?? null,
                    'customer' => $lead->customer?->name ?? '-',
                    'discount' => $validated['discount'] ?? 0,
                    'tax' => $validated['tax'] ?? 0,
                    'notes' => $validated['notes'] ?? null,
                    'status' => 'draft',
                ]);

                $this->persistItems($proposal, $validated['items']);

                return $proposal;
            });
        } catch (QueryException $e) {
            return back()->withInput()->withErrors(['proposal_number' => __('Gagal membuat nomor proposal, coba lagi.')]);
        }

        $this->log($lead, 'proposal_created', ['number' => $proposal->proposal_number]);

        return redirect()->route('sales.proposals.show', $proposal)
            ->with('success', __('Proposal draft berhasil dibuat.'));
    }

    public function show(Proposal $proposal)
    {
        $proposal->load(['lead.customer', 'items', 'creator']);
        $this->authorizeProposal($proposal);

        return view('sales.proposals.show', compact('proposal'));
    }

    public function preview(Proposal $proposal)
    {
        $proposal->load(['lead.customer', 'items', 'creator']);
        $this->authorizeProposal($proposal);

        return view('sales.proposals.preview', compact('proposal'));
    }

    public function edit(Proposal $proposal)
    {
        $proposal->load('items');
        $this->authorizeEdit($proposal);

        return view('sales.proposals.edit', compact('proposal'));
    }

    public function update(Request $request, Proposal $proposal)
    {
        $this->authorizeEdit($proposal);

        $validated = $request->validate(array_merge([
            'proposal_date' => 'required|date',
            'valid_until' => 'nullable|date|after_or_equal:proposal_date',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ], $this->itemRules()));

        DB::transaction(function () use ($proposal, $validated) {
            $proposal->update([
                'proposal_date' => $validated['proposal_date'],
                'valid_until' => $validated['valid_until'] ?? null,
                'discount' => $validated['discount'] ?? 0,
                'tax' => $validated['tax'] ?? 0,
                'notes' => $validated['notes'] ?? null,
            ]);
            $this->persistItems($proposal, $validated['items']);
        });

        return redirect()->route('sales.proposals.show', $proposal)
            ->with('success', __('Proposal berhasil diperbarui.'));
    }

    public function markReady(Proposal $proposal)
    {
        $this->authorizeEdit($proposal);
        abort_unless($proposal->status === 'draft', 422);

        $proposal->update(['status' => 'ready']);

        return back()->with('success', __('Proposal siap dikirim.'));
    }

    public function send(Proposal $proposal)
    {
        $this->authorizeProposal($proposal);
        abort_unless($proposal->status === 'ready', 422);

        $proposal->update([
            'status' => 'sent',
            'sent_at' => now(),
            'sent_by' => auth()->id(),
        ]);

        $this->log($proposal->lead, 'proposal_sent', ['number' => $proposal->proposal_number]);

        $assignee = $proposal->lead->assignee;
        if ($assignee && (int) $assignee->id !== (int) auth()->id()) {
            $assignee->notify(new ProposalStatusNotification($proposal, 'sent'));
        }

        return back()->with('success', __('Proposal ditandai terkirim.'));
    }

    public function markViewed(Proposal $proposal)
    {
        $this->authorizeProposal($proposal);
        abort_unless($proposal->status === 'sent', 422);

        $proposal->update(['status' => 'viewed']);

        return back()->with('success', __('Proposal ditandai sudah dilihat customer.'));
    }

    public function revise(Proposal $proposal)
    {
        $this->authorizeProposal($proposal);
        abort_unless(in_array($proposal->status, ['sent', 'viewed']), 422);

        $proposal->update(['status' => 'revision']);
        $this->log($proposal->lead, 'proposal_revised', ['number' => $proposal->proposal_number]);

        return back()->with('success', __('Proposal dikembalikan ke revisi.'));
    }

    public function respond(Request $request, Proposal $proposal)
    {
        $this->authorizeProposal($proposal);
        abort_unless(in_array($proposal->status, ['sent', 'viewed', 'revision', 'ready']), 422);

        $validated = $request->validate(['decision' => 'required|in:accepted,rejected']);

        $proposal->update(['status' => $validated['decision']]);
        $this->log($proposal->lead, 'proposal_' . $validated['decision'], ['number' => $proposal->proposal_number]);

        $assignee = $proposal->lead->assignee;
        if ($assignee && (int) $assignee->id !== (int) auth()->id()) {
            $assignee->notify(new ProposalStatusNotification($proposal, $validated['decision']));
        }

        return back()->with('success', __('Status proposal dicatat.'));
    }

    public function cancel(Proposal $proposal)
    {
        $this->authorizeProposal($proposal);
        abort_unless(in_array($proposal->status, ['draft', 'ready']), 422);

        $proposal->update(['status' => 'cancelled']);

        return back()->with('success', __('Proposal dibatalkan.'));
    }
}
