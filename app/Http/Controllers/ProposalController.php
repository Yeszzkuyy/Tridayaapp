<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\WorkType;
use App\Notifications\ProjectConvertedNotification;
use App\Notifications\ProposalStatusNotification;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        // Selama pending_technical, BOQ dikunci dari sales; hanya tim teknis/admin yang boleh ubah.
        if ($proposal->status === 'pending_technical') {
            abort_unless(auth()->user()->can('manage-technician') || auth()->user()->can('manage-admin'), 422);
            return;
        }

        abort_unless(in_array($proposal->status, ['draft', 'revision']), 422);
    }

    /** Aksi tim teknis/admin: owner tidak wajib, cukup permission teknis. */
    private function authorizeTechnical(Proposal $proposal): void
    {
        $user = auth()->user();

        // Tim teknis/admin kerja lintas divisi by design: cukup viewAny.
        // (LeadPolicy::view menjebak admin di cabang sales karena ia juga
        // pegang view-sales, jadi authorize penuh tidak dipakai di sini.)
        if ($user->can('manage-technician') || $user->can('manage-admin')) {
            $this->authorize('viewAny', Lead::class);
            return;
        }

        $this->authorizeProposal($proposal);
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
        // Tanpa lead: tampilkan pemilih lead dulu (mis. dari tombol + di
        // daftar proposal), bukan 404.
        if (! $request->filled('lead_id')) {
            $user = $request->user();
            $query = Lead::with('customer')->latest();

            if (! $user->can('manage-sales-leads') && ! $user->can('manage-marketing')) {
                $query->where('assigned_to', $user->id);
            }

            $q = trim((string) $request->get('q'));
            if ($q !== '') {
                $query->where(function ($w) use ($q) {
                    if (ctype_digit($q)) {
                        $w->orWhere('id', (int) $q);
                    }
                    $w->orWhereHas('customer', fn ($c) => $c->whereLike('name', $q));
                });
            }

            $leads = $query->limit(100)->get();

            return view('sales.proposals.select-lead', compact('leads', 'q'));
        }

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
            return back()->withInput()->withErrors(['proposal_number' => __('Failed to generate the proposal number, please try again.')]);
        }

        $this->log($lead, 'proposal_created', ['number' => $proposal->proposal_number]);

        return redirect()->route('sales.proposals.show', $proposal)
            ->with('success', __('Proposal draft created successfully.'));
    }

    public function show(Proposal $proposal)
    {
        $proposal->load(['lead.customer', 'items', 'creator', 'project']);
        $this->authorizeProposal($proposal);

        $workTypes = $proposal->status === 'accepted' && ! $proposal->project
            ? WorkType::orderBy('name')->get(['id', 'name'])
            : collect();

        return view('sales.proposals.show', compact('proposal', 'workTypes'));
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
            ->with('success', __('Proposal updated successfully.'));
    }

    public function markReady(Proposal $proposal)
    {
        $this->authorizeEdit($proposal);
        abort_unless($proposal->status === 'draft', 422);

        $proposal->update(['status' => 'ready']);

        return back()->with('success', __('Proposal is ready to be sent.'));
    }

    /** Sales menyerahkan draft ke tim teknis untuk penyesuaian BOQ/harga. */
    public function submitTechnical(Proposal $proposal)
    {
        $this->authorizeProposal($proposal);
        abort_unless($proposal->status === 'draft', 422);

        $proposal->update(['status' => 'pending_technical']);
        $this->log($proposal->lead, 'proposal_pending_technical', ['number' => $proposal->proposal_number]);

        \App\Models\User::role(['admin', 'lead-technician'])->get()
            ->each(fn ($u) => $u->notify(new ProposalStatusNotification($proposal, 'pending_technical')));

        return back()->with('success', __('Proposal submitted to the technical team.'));
    }

    /** Tim teknis/admin mengembalikan ke draft (butuh koreksi sales). */
    public function returnTechnical(Proposal $proposal)
    {
        $this->authorizeTechnical($proposal);
        abort_unless($proposal->status === 'pending_technical', 422);

        $proposal->update(['status' => 'draft']);
        $this->log($proposal->lead, 'proposal_revised', ['number' => $proposal->proposal_number]);

        $assignee = $proposal->lead->assignee;
        if ($assignee) {
            $assignee->notify(new ProposalStatusNotification($proposal, 'returned_to_draft'));
        }

        return back()->with('success', __('Proposal returned to draft.'));
    }

    /** Tim teknis/admin selesai menyesuaikan → siap dikirim sales. */
    public function completeTechnical(Proposal $proposal)
    {
        $this->authorizeTechnical($proposal);
        abort_unless($proposal->status === 'pending_technical', 422);

        $proposal->update(['status' => 'ready']);
        $this->log($proposal->lead, 'proposal_ready', ['number' => $proposal->proposal_number]);

        $assignee = $proposal->lead->assignee;
        if ($assignee && (int) $assignee->id !== (int) auth()->id()) {
            $assignee->notify(new ProposalStatusNotification($proposal, 'ready'));
        }

        return back()->with('success', __('Technical adjustment done, proposal ready to send.'));
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

        return back()->with('success', __('Proposal marked as sent.'));
    }

    public function markViewed(Proposal $proposal)
    {
        $this->authorizeProposal($proposal);
        abort_unless($proposal->status === 'sent', 422);

        $proposal->update(['status' => 'viewed']);

        return back()->with('success', __('Proposal marked as viewed by the customer.'));
    }

    public function revise(Proposal $proposal)
    {
        $this->authorizeProposal($proposal);
        abort_unless(in_array($proposal->status, ['sent', 'viewed']), 422);

        $proposal->update(['status' => 'revision']);
        $this->log($proposal->lead, 'proposal_revised', ['number' => $proposal->proposal_number]);

        return back()->with('success', __('Proposal returned for revision.'));
    }

    public function respond(Request $request, Proposal $proposal)
    {
        $this->authorizeProposal($proposal);
        abort_unless(in_array($proposal->status, ['sent', 'viewed', 'revision', 'ready']), 422);

        $validated = $request->validate(['decision' => 'required|in:accepted,rejected']);

        DB::transaction(function () use ($proposal, $validated) {
            $proposal->update(['status' => $validated['decision']]);
            $this->log($proposal->lead, 'proposal_' . $validated['decision'], ['number' => $proposal->proposal_number]);

            // Proposal diterima = lead WON (ikuti konvensi pipeline: closed_at + acknowledged).
            if ($validated['decision'] === 'accepted' && $proposal->lead->status !== 'won') {
                $leadPayload = ['status' => 'won', 'closed_at' => now()];
                if ($proposal->lead->acknowledged_at === null) {
                    $leadPayload['acknowledged_at'] = now();
                }
                $proposal->lead->update($leadPayload);
                $this->log($proposal->lead, 'status_changed', ['status' => 'won', 'via' => 'proposal_accepted']);
            }
        });

        $assignee = $proposal->lead->assignee;
        if ($assignee && (int) $assignee->id !== (int) auth()->id()) {
            $assignee->notify(new ProposalStatusNotification($proposal, $validated['decision']));
        }

        return back()->with('success', __('Proposal status recorded.'));
    }

    public function cancel(Proposal $proposal)
    {
        $this->authorizeProposal($proposal);
        abort_unless(in_array($proposal->status, ['draft', 'ready']), 422);

        $proposal->update(['status' => 'cancelled']);

        return back()->with('success', __('Proposal canceled.'));
    }

    /** Upload dokumen PO klien (hanya saat proposal accepted/WON). */
    public function uploadPo(Request $request, Proposal $proposal)
    {
        $this->authorizeProposal($proposal);
        abort_unless($proposal->status === 'accepted', 422);

        $validated = $request->validate([
            'po_file' => ['required', 'file', 'max:10240', \App\Rules\SecureFile::documents()],
        ]);

        $file = $validated['po_file'];
        $path = $file->storeAs(
            "proposals/{$proposal->id}/po",
            Str::uuid() . '.' . strtolower($file->getClientOriginalExtension()),
            'private'
        );

        if ($proposal->po_document_path) {
            Storage::disk('private')->delete($proposal->po_document_path);
        }
        $proposal->update(['po_document_path' => $path]);

        return back()->with('success', __('PO document uploaded successfully.'));
    }

    public function downloadPo(Proposal $proposal)
    {
        $this->authorizeProposal($proposal);
        abort_if(! $proposal->po_document_path, 404);

        return \App\Rules\SecureFile::fileResponse(
            'private',
            $proposal->po_document_path,
            'PO-' . $proposal->proposal_number . '.' . pathinfo($proposal->po_document_path, PATHINFO_EXTENSION)
        );
    }

    /** Convert proposal WON menjadi Project untuk divisi Teknisi (sekali saja). */
    public function convertToProject(Request $request, Proposal $proposal)
    {
        $this->authorizeProposal($proposal);
        abort_unless($proposal->status === 'accepted', 422);
        abort_if($proposal->project()->exists(), 422, __('This proposal has already been converted.'));

        $validated = $request->validate([
            'work_type_id' => 'required|exists:work_types,id',
            'start_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $lead = $proposal->lead;
        $customerName = $lead->customer?->name ?? $proposal->customer;

        $description = trim(
            ($lead->kebutuhan ? "Kebutuhan: {$lead->kebutuhan}\n" : '')
            . ($proposal->notes ?? '')
            . (! empty($validated['notes']) ? "\nCatatan untuk Teknisi: ".$validated['notes'] : '')
        );

        $project = DB::transaction(function () use ($proposal, $lead, $customerName, $validated, $description) {
            $project = Project::create([
                'customer_id' => $lead->customer_id,
                'sales_user_id' => $lead->assigned_to ?? $proposal->created_by,
                'work_type_id' => $validated['work_type_id'],
                'project_status_id' => ProjectStatus::default()->value('id'),
                'project_name' => $customerName . ' — ' . $proposal->proposal_number,
                'quotation_number' => $proposal->proposal_number,
                'proposal_id' => $proposal->id,
                'start_date' => $validated['start_date'] ?? null,
                'description' => $description,
                'progress' => 0,
            ]);

            $this->log($lead, 'proposal_converted', [
                'number' => $proposal->proposal_number,
                'project_id' => $project->id,
            ]);

            return $project;
        });

        // Beri tahu tim teknisi (jangan notifikasi diri sendiri bila yang
        // convert juga memegang role lead-technician).
        Notification::send(
            User::role('lead-technician')->where('id', '!=', auth()->id())->get(),
            new ProjectConvertedNotification($project)
        );

        return redirect()->route('projects.show', $project)
            ->with('success', __('Project created from proposal successfully.'));
    }
}
