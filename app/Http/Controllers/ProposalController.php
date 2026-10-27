<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Project;
use App\Models\Product;
use App\Models\ProjectStatus;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\SupplierInvoice;
use App\Models\SupplierPurchaseOrder;
use App\Models\WorkType;
use App\Notifications\ProjectConvertedNotification;
use App\Notifications\ProposalStatusNotification;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

    private function isOwner(Proposal $proposal): bool
    {
        return in_array(auth()->id(), [$proposal->created_by, $proposal->lead->assigned_to]);
    }

    /**
     * Baca quotation:
     * - pemilik (Sales Owner / Quotation Maker),
     * - Management (manage-sales-leads) — read-only hub,
     * - Admin & Purchasing (manage-admin) — keperluan handoff,
     * - CEO — read-only sesuai matriks (role aktual, bukan view-admin agar
     *   tidak membuka quotation ke semua role yang punya view-admin).
     * Marketing, teknisi, inside sales/sales non-pemilik: 403.
     */
    private function authorizeView(Proposal $proposal): void
    {
        $user = auth()->user();

        abort_unless(
            $this->isOwner($proposal)
            || $user->can('manage-sales-leads')
            || $user->can('manage-admin')
            || $user->hasRole('ceo'),
            403
        );
    }

    /**
     * Tulis quotation (ready/send/respond/PO/convert): HANYA pemilik.
     * Management & CEO hanya baca — tidak memperoleh hak ubah karena
     * punya akses baca (matriks Role & Permission).
     */
    private function authorizeManage(Proposal $proposal): void
    {
        $this->authorizeView($proposal);

        abort_unless($this->isOwner($proposal), 403);
    }

    private function authorizeEdit(Proposal $proposal): void
    {
        $this->authorizeManage($proposal);

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

        $this->authorizeManage($proposal);
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
            'items.*.unit_cost' => 'nullable|numeric|min:0|max:999999999999',
            'items.*.discount' => 'nullable|numeric|min:0|max:999999999999',
        ];
    }

    /** Aturan syarat bayar & estimasi pengiriman. */
    private function paymentRules(bool $required = false): array
    {
        return [
            'payment_scheme' => ($required ? 'required' : 'nullable').'|in:'.implode(',', Proposal::PAYMENT_SCHEMES),
            'dp_percent' => 'nullable|required_if:payment_scheme,dp|numeric|min:1|max:99',
            'term_days' => 'nullable|required_if:payment_scheme,tempo|integer|min:1|max:365',
            'payment_terms' => 'nullable|string|max:255',
            'delivery_terms' => 'nullable|string|max:255',
        ];
    }

    /**
     * Tolak perubahan harga modal dari pengguna tanpa kewenangan (anti-tamper).
     */
    private function assertNoCostTampering(array $items, bool $canManageCost): void
    {
        if ($canManageCost) {
            return;
        }

        foreach ($items as $item) {
            if (array_key_exists('unit_cost', $item)
                && $item['unit_cost'] !== null && $item['unit_cost'] !== '') {
                abort(403, __('You are not allowed to modify cost prices.'));
            }
        }
    }

    /**
     * Simpan item + snapshot harga modal.
     *
     * - Pengguna berwenang (manage-admin): nilai eksplisit dipakai; kolom
     *   kosong = pertahankan snapshot lama / default produk.
     * - Pengguna lain: unit_cost tidak boleh dikirim; nilai lama DIPERTAHANKAN
     *   (snapshot historis tidak berubah), item baru = snapshot produk saat
     *   dibuat. Data historis tidak pernah diisi angka perkiraan.
     */
    private function persistItems(Proposal $proposal, array $items, bool $canManageCost): void
    {
        $existing = [];
        foreach ($proposal->items()->get() as $old) {
            $key = $old->product_id ? 'p' . $old->product_id : 'd' . $old->description;
            $existing[$key] = $old->unit_cost;
        }

        $proposal->items()->delete();

        foreach ($items as $item) {
            $qty = (float) $item['quantity'];
            $price = (float) $item['unit_price'];
            $disc = (float) ($item['discount'] ?? 0);
            $key = ! empty($item['product_id']) ? 'p' . $item['product_id'] : 'd' . $item['description'];

            if ($canManageCost
                && array_key_exists('unit_cost', $item)
                && $item['unit_cost'] !== null && $item['unit_cost'] !== '') {
                // Pembaruan eksplisit oleh yang berwenang.
                $cost = round((float) $item['unit_cost'], 2);
            } elseif (array_key_exists($key, $existing)) {
                // Item lama: pertahankan snapshot apa adanya (termasuk null).
                $cost = $existing[$key] === null ? null : round((float) $existing[$key], 2);
            } else {
                // Item baru: snapshot harga modal produk saat ini.
                $cost = ! empty($item['product_id'])
                    ? (Product::find($item['product_id'])?->cost_price === null
                        ? null
                        : round((float) Product::find($item['product_id'])->cost_price, 2))
                    : null;
            }

            $proposal->items()->create([
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $qty,
                'unit' => $item['unit'] ?? 'pcs',
                'unit_price' => $price,
                'unit_cost' => $cost,
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
            'internal_notes' => 'nullable|string',
        ], $this->paymentRules(), $this->itemRules()));

        $canManageCost = Gate::allows('updateCost', Proposal::class);
        $this->assertNoCostTampering($validated['items'], $canManageCost);

        $lead = Lead::with('customer')->findOrFail($validated['lead_id']);
        $this->authorize('view', $lead);

        try {
            $proposal = DB::transaction(function () use ($validated, $lead, $canManageCost) {
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
                    'internal_notes' => $validated['internal_notes'] ?? null,
                    'payment_scheme' => $validated['payment_scheme'] ?? null,
                    'dp_percent' => $validated['dp_percent'] ?? null,
                    'term_days' => $validated['term_days'] ?? null,
                    'payment_terms' => $validated['payment_terms'] ?? null,
                    'delivery_terms' => $validated['delivery_terms'] ?? null,
                    'status' => 'draft',
                ]);

                $this->persistItems($proposal, $validated['items'], $canManageCost);

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
        $proposal->load(['lead.customer', 'lead.assignee', 'items', 'creator', 'project']);
        $this->authorizeView($proposal);

        $workTypes = $proposal->status === 'accepted' && ! $proposal->project
            ? WorkType::orderBy('name')->get(['id', 'name'])
            : collect();

        return view('sales.proposals.show', compact('proposal', 'workTypes'));
    }

    public function preview(Proposal $proposal)
    {
        $proposal->load(['lead.customer', 'lead.assignee', 'items', 'creator']);
        $this->authorizeView($proposal);

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
            'internal_notes' => 'nullable|string',
        ], $this->paymentRules(), $this->itemRules()));

        $canManageCost = Gate::allows('updateCost', Proposal::class);
        $this->assertNoCostTampering($validated['items'], $canManageCost);

        DB::transaction(function () use ($proposal, $validated, $canManageCost) {
            $proposal->update([
                'proposal_date' => $validated['proposal_date'],
                'valid_until' => $validated['valid_until'] ?? null,
                'discount' => $validated['discount'] ?? 0,
                'tax' => $validated['tax'] ?? 0,
                'notes' => $validated['notes'] ?? null,
                'internal_notes' => $validated['internal_notes'] ?? null,
                'payment_scheme' => $validated['payment_scheme'] ?? null,
                'dp_percent' => $validated['dp_percent'] ?? null,
                'term_days' => $validated['term_days'] ?? null,
                'payment_terms' => $validated['payment_terms'] ?? null,
                'delivery_terms' => $validated['delivery_terms'] ?? null,
            ]);
            $this->persistItems($proposal, $validated['items'], $canManageCost);
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
        $this->authorizeManage($proposal);
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
        $this->authorizeManage($proposal);
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
        $this->authorizeManage($proposal);
        abort_unless($proposal->status === 'sent', 422);

        $proposal->update(['status' => 'viewed']);

        return back()->with('success', __('Proposal marked as viewed by the customer.'));
    }

    public function revise(Proposal $proposal)
    {
        $this->authorizeManage($proposal);
        abort_unless(in_array($proposal->status, ['sent', 'viewed']), 422);

        $proposal->update(['status' => 'revision']);
        $this->log($proposal->lead, 'proposal_revised', ['number' => $proposal->proposal_number]);

        return back()->with('success', __('Proposal returned for revision.'));
    }

    public function respond(Request $request, Proposal $proposal)
    {
        $this->authorizeManage($proposal);
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
        $this->authorizeManage($proposal);
        abort_unless(in_array($proposal->status, ['draft', 'ready']), 422);

        $proposal->update(['status' => 'cancelled']);

        return back()->with('success', __('Proposal canceled.'));
    }

    /** Upload dokumen PO klien (hanya saat proposal accepted/WON). */
    public function uploadPo(Request $request, Proposal $proposal)
    {
        $this->authorizeManage($proposal);
        abort_unless($proposal->status === 'accepted', 422);

        $validated = $request->validate(array_merge([
            // File wajib bila belum ada PO tersimpan; bila sudah ada, boleh
            // hanya memperbarui nomor/tanggal PO & info pengiriman.
            'po_file' => [
                $proposal->po_document_path ? 'nullable' : 'required',
                'file',
                'max:10240',
                \App\Rules\SecureFile::documents(),
            ],
            'po_number' => 'nullable|string|max:100',
            'po_date' => 'nullable|date',
            'delivery_deadline' => 'nullable|date',
            'delivery_address' => 'nullable|string|max:255',
        ], $this->paymentRules()));

        $payload = [
            'po_number' => $validated['po_number'] ?? $proposal->po_number,
            'po_date' => $validated['po_date'] ?? $proposal->po_date,
            'delivery_deadline' => $validated['delivery_deadline'] ?? $proposal->delivery_deadline,
            'delivery_address' => $validated['delivery_address'] ?? $proposal->delivery_address,
            'payment_scheme' => $validated['payment_scheme'] ?? $proposal->payment_scheme,
            'dp_percent' => $validated['dp_percent'] ?? $proposal->dp_percent,
            'term_days' => $validated['term_days'] ?? $proposal->term_days,
            'payment_terms' => $validated['payment_terms'] ?? $proposal->payment_terms,
            'delivery_terms' => $validated['delivery_terms'] ?? $proposal->delivery_terms,
        ];

        if ($request->hasFile('po_file')) {
            $file = $validated['po_file'];
            $path = $file->storeAs(
                "proposals/{$proposal->id}/po",
                Str::uuid() . '.' . strtolower($file->getClientOriginalExtension()),
                'private'
            );

            if ($proposal->po_document_path) {
                Storage::disk('private')->delete($proposal->po_document_path);
            }
            $payload['po_document_path'] = $path;
        }

        $proposal->update($payload);

        return back()->with('success', __('PO document uploaded successfully.'));
    }

    public function downloadPo(Proposal $proposal)
    {
        $this->authorizeView($proposal);
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
        $this->authorizeManage($proposal);
        abort_unless($proposal->status === 'accepted', 422);
        abort_if($proposal->project()->exists(), 422, __('This proposal has already been converted.'));

        // Handoff wajib lengkap: tanpa PO & cara bayar, Admin akan mengetik ulang.
        abort_unless(filled($proposal->po_document_path), 422, __('Upload the customer PO before converting to a project.'));
        abort_unless(filled($proposal->po_number), 422, __('Fill in the customer PO number before converting to a project.'));
        abort_unless(filled($proposal->payment_scheme), 422, __('Fill in the payment terms before converting to a project.'));

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
                'delivery_deadline' => $proposal->delivery_deadline,
                'description' => $description,
                'progress' => 0,
            ]);

            // Purchasing boleh membuat Supplier PO sebelum project lahir.
            // Saat project dibuat, tautkan dokumen procurement yang masih
            // yatim agar filter project/PT dan laporan tidak kehilangan data.
            SupplierPurchaseOrder::where('proposal_id', $proposal->id)
                ->whereNull('project_id')
                ->update(['project_id' => $project->id]);

            GoodsReceipt::whereNull('project_id')
                ->whereHas('purchaseOrder', fn ($q) => $q->where('proposal_id', $proposal->id))
                ->update(['project_id' => $project->id]);

            SupplierInvoice::whereNull('project_id')
                ->whereHas('purchaseOrder', fn ($q) => $q->where('proposal_id', $proposal->id))
                ->update(['project_id' => $project->id]);

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

        $redirect = redirect()->route('projects.show', $project)
            ->with('success', __('Project created from proposal successfully.'));

        if (blank($proposal->delivery_deadline)) {
            $redirect->with(
                'warning',
                __('Project created, but the delivery deadline is still empty. Please complete it in the quotation.')
            );
        }

        return $redirect;
    }
}
