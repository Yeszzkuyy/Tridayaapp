<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\PriceRequest;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Proposal;
use App\Models\ProposalAttachment;
use App\Models\ProposalItem;
use App\Models\SupplierInvoice;
use App\Models\SupplierPurchaseOrder;
use App\Models\User;
use App\Models\WorkType;
use App\Notifications\ProjectConvertedNotification;
use App\Notifications\ProposalStatusNotification;
use App\Notifications\PurchasingHandoffNotification;
use App\Rules\SecureFile;
use App\Services\CustomerLifecycleService;
use App\Services\Project\ProjectWorkflowService;
use App\Services\QuotationNumberService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProposalController extends Controller
{
    public function __construct(private QuotationNumberService $numbers) {}

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
     * Quotation tanpa item/nilai tidak boleh masuk tahap siap kirim atau
     * diterima customer. Ini menjaga agar Admin tidak menerima deal Rp 0.
     */
    private function assertQuotationHasBillableContent(Proposal $proposal): void
    {
        $proposal->loadMissing('items');

        if ($proposal->items->isEmpty() || (float) $proposal->grand_total <= 0) {
            throw ValidationException::withMessages([
                'quotation' => __('Quotation must have at least one item and a total greater than 0 before it can be sent or accepted.'),
            ]);
        }
    }

    /**
     * Buang baris item kosong dari form. Baris kosong tetap dikirim browser
     * saat Sales hanya melampirkan file, jadi jangan sampai dianggap item.
     */
    private function normalizedItems(mixed $items): array
    {
        return collect(is_array($items) ? $items : [])
            ->filter(function ($item) {
                if (! is_array($item)) {
                    return false;
                }

                return filled($item['description'] ?? null)
                    || (float) ($item['unit_price'] ?? 0) > 0;
            })
            ->values()
            ->all();
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
    /**
     * Unduh file quotation yang di-accept dari Inside Sales (lampiran proposal).
     */
    /** Simpan file quotation yang diunggah ke lampiran proposal. */
    private function storeQuotationFile(Request $request, Proposal $proposal): void
    {
        if (! $request->hasFile('quotation_file')) {
            return;
        }

        $file = $request->file('quotation_file');
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
        $path = $file->storeAs(
            'quotations/'.$proposal->id,
            Str::uuid().'_'.$safeName,
            'private'
        );

        $proposal->attachments()->create([
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'uploaded_by' => auth()->id(),
        ]);
    }

    public function downloadAttachment(Proposal $proposal, ProposalAttachment $attachment)
    {
        $this->authorizeView($proposal);

        abort_unless((int) $attachment->proposal_id === (int) $proposal->id, 404);
        abort_unless(Storage::disk('private')->exists($attachment->file_path), 404, __('File not found.'));

        return Storage::disk('private')
            ->download($attachment->file_path, $attachment->original_name);
    }

    /**
     * Sajikan lampiran quotation inline (PDF/gambar/teks tampil di browser);
     * format lain dipaksa unduh oleh SecureFile.
     */
    public function previewAttachment(Proposal $proposal, ProposalAttachment $attachment)
    {
        $this->authorizeView($proposal);

        abort_unless((int) $attachment->proposal_id === (int) $proposal->id, 404);

        return SecureFile::fileResponse(
            'private',
            $attachment->file_path,
            $attachment->original_name
        );
    }

    private function authorizeView(Proposal $proposal): void
    {
        $user = auth()->user();

        abort_unless(
            $this->isOwner($proposal)
            || $user->can('manage-sales-leads')
            || $user->can('manage-admin')
            || $user->can('manage-technician')
            || $user->hasRole('ceo'),
            403
        );
    }

    /**
     * Lead/customer dianggap sudah "hidup" bila minimal ada satu catatan
     * follow-up (DAR). Dipakai sebagai gerbang anti-ghosting.
     */
    private function hasFollowUpNotes(?Lead $lead): bool
    {
        if (! $lead) {
            return false;
        }

        return $lead->followUps()->exists()
            || ($lead->customer?->followUps()->exists() ?? false);
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

        $canFixZeroReady = $proposal->status === 'ready' && (float) $proposal->grand_total <= 0;

        abort_unless(in_array($proposal->status, ['draft', 'revision'], true) || $canFixZeroReady, 422);
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

    private function generateNumber(Lead $lead): string
    {
        $pt = $lead->pt_group ?: $lead->customer?->pt_group ?: 'WANI';

        // Inisial pada nomor quotation milik Sales pemilik lead, bukan
        // pembuat draf (Inside Sales / Management).
        return $this->numbers->generate($pt, $lead->assignee ?? auth()->user());
    }

    /**
     * Sales lapangan tidak lagi membuat quotation langsung. Draf disusun
     * Inside Sales lewat task; Management tetap punya jalur darurat.
     */
    private function authorizeQuotationDraft(Request $request): void
    {
        abort_unless(
            $request->user()?->can('manage-inside-sales')
            || $request->user()?->can('manage-sales-leads'),
            403
        );
    }

    /**
     * Simpan snapshot versi quotation agar riwayat revisi bisa dibaca.
     * `total_before` = total versi sebelumnya (null untuk versi pertama).
     */
    private function recordVersion(Proposal $proposal, ?float $totalBefore = null, ?string $summary = null): void
    {
        $items = $proposal->items()->get();
        $next = ((int) $proposal->versions()->max('version')) + 1;

        $proposal->versions()->create([
            'version' => $next,
            'label' => 'v'.$next,
            'snapshot' => $items->map(fn (ProposalItem $item) => [
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit' => $item->unit,
                'unit_price' => (float) $item->unit_price,
                'unit_cost' => $item->unit_cost !== null ? (float) $item->unit_cost : null,
                'subtotal' => (float) $item->subtotal,
            ])->values()->all(),
            'total_before' => $totalBefore,
            'total_after' => (float) $proposal->grand_total,
            'change_summary' => $summary,
            'changed_by' => auth()->id(),
        ]);
    }

    private function itemRules(): array
    {
        return [
            'items' => 'nullable|array|max:50',
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
            $key = $old->product_id ? 'p'.$old->product_id : 'd'.$old->description;
            $existing[$key] = $old->unit_cost;
        }

        $proposal->items()->delete();

        foreach ($items as $item) {
            $qty = (float) $item['quantity'];
            $price = (float) $item['unit_price'];
            $disc = (float) ($item['discount'] ?? 0);
            $key = ! empty($item['product_id']) ? 'p'.$item['product_id'] : 'd'.$item['description'];

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

        if (! $user->can('manage-sales-leads') && ! $user->can('manage-admin')) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhereHas('lead', fn ($l) => $l->where('assigned_to', $user->id));
            });
        }

        return $query;
    }

    public function create(Request $request)
    {
        $this->authorizeQuotationDraft($request);

        $priceRequest = null;
        if ($request->filled('price_request_id')) {
            $priceRequest = PriceRequest::with('customer')->findOrFail($request->integer('price_request_id'));
            $this->authorize('view', $priceRequest);
            abort_if($priceRequest->proposal_id, 422, __('This price request already has a quotation.'));
        }

        // Tanpa lead: tampilkan pemilih lead dulu (mis. dari tombol + di
        // daftar proposal), bukan 404.
        if (! $request->filled('lead_id')) {
            $user = $request->user();
            $query = Lead::with('customer')->latest();

            if ($priceRequest) {
                $query->where('customer_id', $priceRequest->customer_id);
            }

            if ($user->can('manage-inside-sales')) {
                // Inside Sales memilih lead dari task yang di-assign kepadanya.
                $query->whereHas('tasks', fn ($task) => $task->where('assigned_to', $user->id));
            } elseif (! $user->can('manage-sales-leads') && ! $user->can('manage-marketing')) {
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

            return view('sales.proposals.select-lead', compact('leads', 'q', 'priceRequest'));
        }

        $lead = Lead::with('customer')->findOrFail($request->get('lead_id'));
        $this->authorize('view', $lead);

        if ($priceRequest) {
            abort_unless((int) $priceRequest->customer_id === (int) $lead->customer_id, 422, __('The selected lead does not match the price request customer.'));
        }

        $completedTech = $lead->technicalRequests()->where('status', 'completed')->latest()->first();
        $prefillNotes = $lead->kebutuhan;

        return view('sales.proposals.create', compact('lead', 'completedTech', 'prefillNotes', 'priceRequest'));
    }

    public function store(Request $request)
    {
        $this->authorizeQuotationDraft($request);

        $request->merge(['items' => $this->normalizedItems($request->input('items'))]);

        $validated = $request->validate(array_merge([
            'lead_id' => 'required|exists:leads,id',
            'proposal_date' => 'required|date',
            'valid_until' => 'nullable|date|after_or_equal:proposal_date',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'price_request_id' => 'nullable|exists:price_requests,id',
            'quotation_file' => ['nullable', 'file', 'max:10240', SecureFile::documents()],
        ], $this->paymentRules(), $this->itemRules()));

        $items = $validated['items'] ?? [];

        abort_if(empty($items) && ! $request->hasFile('quotation_file'), 422,
            __('Please upload the quotation file or fill at least one item.'));

        $canManageCost = Gate::allows('updateCost', Proposal::class);
        $this->assertNoCostTampering($items, $canManageCost);

        $lead = Lead::with('customer')->findOrFail($validated['lead_id']);
        $this->authorize('view', $lead);

        // Anti-ghosting: jangan buat penawaran untuk lead yang belum pernah
        // dicatat update/follow-up-nya.
        if (! $this->hasFollowUpNotes($lead)) {
            return back()->withInput()->withErrors([
                'lead_id' => __('Record at least one follow-up update before creating a quotation.'),
            ]);
        }

        // Satu lead = satu quotation. Revisi memakai nomor yang sama.
        if ($lead->hasActiveProposal()) {
            return back()->withInput()->withErrors([
                'lead_id' => __('This lead already has a quotation. Revise the existing quotation instead of creating a new one.'),
            ]);
        }

        $priceRequest = null;
        if (! empty($validated['price_request_id'])) {
            $priceRequest = PriceRequest::findOrFail($validated['price_request_id']);
            $this->authorize('view', $priceRequest);
            abort_if($priceRequest->proposal_id, 422, __('This price request already has a quotation.'));
            abort_unless(
                (int) $priceRequest->customer_id === (int) $lead->customer_id,
                422,
                __('The selected lead does not match the price request customer.')
            );
        }

        try {
            $proposal = DB::transaction(function () use ($validated, $lead, $canManageCost, $priceRequest, $items) {
                $proposal = Proposal::create([
                    'lead_id' => $lead->id,
                    'proposal_number' => $this->generateNumber($lead),
                    'created_by' => auth()->id(),
                    'proposal_date' => $validated['proposal_date'],
                    'valid_until' => $validated['valid_until'] ?? null,
                    'customer' => $lead->customer?->name ?? '-',
                    'letterhead_pt' => $lead->pt_group ?: $lead->customer?->pt_group,
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

                if ($items !== []) {
                    $this->persistItems($proposal, $items, $canManageCost);
                }

                if ($priceRequest) {
                    $priceRequest->update(['proposal_id' => $proposal->id]);
                }

                return $proposal;
            });
        } catch (QueryException $e) {
            return back()->withInput()->withErrors(['proposal_number' => __('Failed to generate the proposal number, please try again.')]);
        }

        $this->storeQuotationFile($request, $proposal);

        $this->recordVersion($proposal);

        $this->log($lead, 'proposal_created', ['number' => $proposal->proposal_number]);

        return redirect()->route('sales.proposals.show', $proposal)
            ->with('success', __('Proposal draft created successfully.'));
    }

    public function show(Proposal $proposal)
    {
        $proposal->load(['lead.customer', 'lead.assignee', 'items', 'creator', 'project', 'priceRequest', 'attachments']);
        $this->authorizeView($proposal);

        $workTypes = $proposal->status === 'accepted' && ! $proposal->project
            ? WorkType::orderBy('name')->get(['id', 'name'])
            : collect();

        $technicians = $proposal->status === 'accepted' && ! $proposal->project
            ? User::whereHas('roles', fn ($q) => $q->whereIn('name', ['technician', 'lead-technician', 'prakerin-technician']))
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        return view('sales.proposals.show', compact('proposal', 'workTypes', 'technicians'));
    }

    public function preview(Proposal $proposal)
    {
        $proposal->load(['lead.customer', 'lead.assignee', 'items', 'creator']);
        $this->authorizeView($proposal);

        return view('sales.proposals.preview', compact('proposal'));
    }

    public function downloadPdf(Proposal $proposal)
    {
        $this->authorizeView($proposal);

        abort_unless(
            $proposal->pdf_path && Storage::disk('private')->exists($proposal->pdf_path),
            404,
            __('Quotation PDF not found.')
        );

        return Storage::disk('private')->download(
            $proposal->pdf_path,
            preg_replace('/[^A-Za-z0-9._-]/', '_', $proposal->proposal_number).'.pdf'
        );
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
        $request->merge(['items' => $this->normalizedItems($request->input('items'))]);

        $validated = $request->validate(array_merge([
            'proposal_date' => 'required|date',
            'valid_until' => 'nullable|date|after_or_equal:proposal_date',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'quotation_file' => ['nullable', 'file', 'max:10240', SecureFile::documents()],
        ], $this->paymentRules(), $this->itemRules()));

        $canManageCost = Gate::allows('updateCost', Proposal::class);
        $items = $validated['items'] ?? [];
        $this->assertNoCostTampering($items, $canManageCost);

        $totalBefore = (float) $proposal->grand_total;

        DB::transaction(function () use ($proposal, $validated, $canManageCost, $items) {
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
            if ($items !== []) {
                $this->persistItems($proposal, $items, $canManageCost);
            }
        });

        $this->storeQuotationFile($request, $proposal);

        $proposal->refresh();
        $this->recordVersion($proposal, $totalBefore, __('Quotation revised.'));

        return redirect()->route('sales.proposals.show', $proposal)
            ->with('success', __('Proposal updated successfully.'));
    }

    public function markReady(Proposal $proposal)
    {
        $this->authorizeEdit($proposal);
        abort_unless($proposal->status === 'draft', 422);
        $this->assertQuotationHasBillableContent($proposal);

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

        User::role(['admin', 'lead-technician'])->get()
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
        $this->assertQuotationHasBillableContent($proposal);

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
        $this->assertQuotationHasBillableContent($proposal);

        $proposal->update([
            'status' => 'sent',
            'sent_at' => now(),
            'sent_by' => auth()->id(),
            'sent_channel' => 'manual',
        ]);

        $this->log($proposal->lead, 'proposal_sent', ['number' => $proposal->proposal_number]);

        $assignee = $proposal->lead->assignee;
        if ($assignee && (int) $assignee->id !== (int) auth()->id()) {
            $assignee->notify(new ProposalStatusNotification($proposal, 'sent'));
        }

        return back()->with('success', __('Proposal marked as sent.'));
    }

    /**
     * Tandai quotation terkirim lewat kanal tertentu (WhatsApp/email).
     * Dipanggil lewat fetch dari tombol kanal, jadi pemilik quotation tidak
     * perlu klik "Mark as Sent" lagi setelah benar-benar mengirim.
     */
    public function markSentVia(Request $request, Proposal $proposal, string $channel)
    {
        $this->authorizeManage($proposal);

        abort_unless(in_array($channel, ['whatsapp', 'email'], true), 404);

        if ($proposal->status === 'ready') {
            $proposal->update([
                'status' => 'sent',
                'sent_at' => now(),
                'sent_by' => auth()->id(),
                'sent_channel' => $channel,
            ]);

            $this->log($proposal->lead, 'proposal_sent', [
                'number' => $proposal->proposal_number,
                'channel' => $channel,
            ]);

            $assignee = $proposal->lead?->assignee;
            if ($assignee && (int) $assignee->id !== (int) auth()->id()) {
                $assignee->notify(new ProposalStatusNotification($proposal->fresh(), 'sent'));
            }
        } elseif ($proposal->status === 'sent' && $proposal->sent_channel === null) {
            // Sudah ditandai manual sebelumnya: lengkapi kanalnya saja.
            $proposal->update(['sent_channel' => $channel]);
        }

        return response()->json([
            'ok' => true,
            'status' => $proposal->status,
            'channel' => $proposal->fresh()->sent_channel,
            'sent_at' => $proposal->fresh()->sent_at?->toIso8601String(),
        ]);
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
        $this->recordVersion($proposal, (float) $proposal->grand_total, __('Requested revision from customer.'));

        return back()->with('success', __('Proposal returned for revision.'));
    }

    public function respond(Request $request, Proposal $proposal)
    {
        $this->authorizeManage($proposal);
        abort_unless(in_array($proposal->status, ['sent', 'viewed', 'revision', 'ready']), 422);

        $validated = $request->validate(['decision' => 'required|in:accepted,rejected']);

        if ($validated['decision'] === 'accepted') {
            $this->assertQuotationHasBillableContent($proposal);
        }

        DB::transaction(function () use ($proposal, $validated) {
            $proposal->update(['status' => $validated['decision']]);
            $this->log($proposal->lead, 'proposal_'.$validated['decision'], ['number' => $proposal->proposal_number]);

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

        if ($validated['decision'] === 'accepted') {
            app(CustomerLifecycleService::class)->promoteFromLead($proposal->lead);
        }

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

        // Anti-ghosting: PO hanya boleh diunggah bila lead sudah punya
        // catatan follow-up/DAR.
        if (! $this->hasFollowUpNotes($proposal->lead)) {
            return back()->withErrors([
                'po_file' => __('Record at least one follow-up update before uploading the customer PO.'),
            ]);
        }

        $validated = $request->validate(array_merge([
            // File wajib bila belum ada PO tersimpan; bila sudah ada, boleh
            // hanya memperbarui nomor/tanggal PO & info pengiriman.
            'po_file' => [
                $proposal->po_document_path ? 'nullable' : 'required',
                'file',
                'max:10240',
                SecureFile::documents(),
            ],
            'po_number' => 'nullable|string|max:100',
            'po_date' => 'nullable|date',
            'delivery_deadline' => 'nullable|date',
            'delivery_address' => 'nullable|string|max:255',
        ], $this->paymentRules()));

        // Alamat pengiriman wajib sebelum PO masuk handoff (dipisah dari
        // alamat tagihan kantor, sesuai wawancara Sales).
        $effectiveAddress = trim((string) ($validated['delivery_address'] ?? $proposal->delivery_address ?? ''));
        if ($effectiveAddress === '') {
            return back()->withInput()->withErrors([
                'delivery_address' => __('Fill in the delivery address before uploading the customer PO.'),
            ]);
        }

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
                Str::uuid().'.'.strtolower($file->getClientOriginalExtension()),
                'private'
            );

            if ($proposal->po_document_path) {
                Storage::disk('private')->delete($proposal->po_document_path);
            }
            $payload['po_document_path'] = $path;
        }

        if ($proposal->po_returned_at !== null) {
            $payload['po_returned_at'] = null;
            $payload['po_returned_by'] = null;
            $payload['po_return_reason'] = null;
        }

        $proposal->update($payload);

        app(CustomerLifecycleService::class)->promoteFromLead($proposal->lead);

        $this->notifyAdminHandoff($proposal->fresh(), PurchasingHandoffNotification::STAGE_PO_RECEIVED);

        return back()->with('success', __('PO document uploaded successfully.'));
    }

    /** Beri tahu Admin/Purchasing bahwa quotation sudah masuk alur handoff. */
    private function notifyAdminHandoff(Proposal $proposal, string $stage): void
    {
        $recipients = User::permission('view-admin-hub')
            ->where('id', '!=', auth()->id())
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new PurchasingHandoffNotification($proposal, $stage));
    }

    public function downloadPo(Proposal $proposal)
    {
        $this->authorizeView($proposal);
        abort_if(! $proposal->po_document_path, 404);

        return SecureFile::fileResponse(
            'private',
            $proposal->po_document_path,
            'PO-'.$proposal->proposal_number.'.'.pathinfo($proposal->po_document_path, PATHINFO_EXTENSION)
        );
    }

    /** Convert proposal WON menjadi Project untuk divisi Teknisi (sekali saja). */
    public function convertToProject(Request $request, Proposal $proposal)
    {
        $user = auth()->user();
        abort_unless(
            $this->isOwner($proposal)
            || $user->can('manage-admin')
            || $user->can('manage-sales-leads')
            || $user->hasRole('ceo'),
            403
        );
        abort_unless($proposal->status === 'accepted', 422);
        abort_if($proposal->project()->exists(), 422, __('This proposal has already been converted.'));
        $this->assertQuotationHasBillableContent($proposal);

        // Handoff wajib lengkap: tanpa PO & cara bayar, Admin akan mengetik ulang.
        abort_unless(filled($proposal->po_document_path), 422, __('Upload the customer PO before converting to a project.'));
        abort_unless(filled($proposal->po_number), 422, __('Fill in the customer PO number before converting to a project.'));
        abort_unless(filled($proposal->payment_scheme), 422, __('Fill in the payment terms before converting to a project.'));
        abort_if($proposal->isPoReturned(), 422, __('This customer PO was returned to Sales. Wait for the revised PO before converting to a project.'));
        abort_unless($proposal->isPoVerified(), 422, __('Verify the customer PO before converting to a project.'));

        $validated = $request->validate([
            'work_type_id' => 'required|exists:work_types,id',
            'start_date' => 'nullable|date',
            'requires_delivery' => 'nullable|boolean',
            'requires_installation' => 'nullable|boolean',
            'teknisi_user_id' => 'nullable|exists:users,id',
            'support_user_ids' => 'nullable|array',
            'support_user_ids.*' => 'exists:users,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        $picEngineer = filled($validated['teknisi_user_id'] ?? null)
            ? User::find($validated['teknisi_user_id'])
            : null;
        abort_unless(
            $picEngineer === null || $picEngineer->hasAnyRole(['technician', 'lead-technician', 'prakerin-technician']),
            422,
            __('The selected PIC is not an engineer.')
        );

        $supportUsers = collect($validated['support_user_ids'] ?? [])
            ->filter()
            ->unique()
            ->map(fn ($id) => User::find($id))
            ->filter(fn (?User $user) => $user?->hasAnyRole(['technician', 'lead-technician', 'prakerin-technician']))
            ->values();

        $lead = $proposal->lead;
        $customerName = $lead->customer?->name ?? $proposal->customer;

        $description = trim(
            ($lead->kebutuhan ? "Kebutuhan: {$lead->kebutuhan}\n" : '')
            .($proposal->notes ?? '')
            .(! empty($validated['notes']) ? "\nCatatan untuk Engineer: ".$validated['notes'] : '')
        );

        $project = DB::transaction(function () use ($proposal, $lead, $customerName, $validated, $description, $request, $picEngineer, $supportUsers) {
            $project = Project::create([
                'customer_id' => $lead->customer_id,
                'sales_user_id' => $lead->assigned_to ?? $proposal->created_by,
                'teknisi_user_id' => $picEngineer?->id,
                'work_type_id' => $validated['work_type_id'],
                'project_status_id' => ProjectStatus::default()->value('id'),
                'project_name' => $customerName.' — '.$proposal->proposal_number,
                'quotation_number' => $proposal->proposal_number,
                'proposal_id' => $proposal->id,
                'pic_engineer' => $picEngineer?->name,
                'support_technicians' => $supportUsers->pluck('name')->implode(', '),
                'start_date' => $validated['start_date'] ?? null,
                'delivery_deadline' => $proposal->delivery_deadline,
                'requires_delivery' => $request->boolean('requires_delivery', true),
                'requires_installation' => $request->boolean('requires_installation', true),
                'description' => $description,
                'workflow_state' => ['request'],
                'progress' => 17,
            ]);

            if ($supportUsers->isNotEmpty()) {
                $project->supportUsers()->sync($supportUsers->pluck('id')->all());
            }

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
        app(CustomerLifecycleService::class)->promoteFromLead($lead);

        // Samakan progress awal dengan langkah yang sudah selesai dari quotation.
        app(ProjectWorkflowService::class)->sync($project, false);

        Notification::send(
            User::role('lead-technician')->where('id', '!=', auth()->id())->get(),
            new ProjectConvertedNotification($project)
        );

        $this->notifyAdminHandoff(
            $proposal->fresh(),
            PurchasingHandoffNotification::STAGE_PROJECT_CREATED
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
