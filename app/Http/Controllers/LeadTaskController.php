<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\EstimatorRequest;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadTask;
use App\Models\LeadTaskItem;
use App\Models\LeadTaskRequestFile;
use App\Models\Product;
use App\Models\ProductPriceLog;
use App\Models\User;
use App\Rules\SecureFile;
use App\Services\QuotationNumberService;
use App\Services\QuotationVersionService;
use App\Support\Rupiah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LeadTaskController extends Controller
{
    public function __construct(
        private QuotationNumberService $numbers,
        private QuotationVersionService $versions,
    ) {}

    /** Boleh request/kelola task: management, marketing, atau sales pemilik lead. */
    private function authorizeTaskRequest(Lead $lead): void
    {
        $user = request()->user();
        if ($user && ($user->can('manage-sales-leads') || $user->can('manage-marketing'))) {
            return;
        }
        if ($user && $user->can('manage-sales') && (int) $lead->assigned_to === (int) $user->id) {
            return;
        }
        abort(403);
    }

    /** Boleh melihat task: mengikuti policy view lead parent, atau assignee task. */
    private function authorizeTaskView(LeadTask $task): void
    {
        $user = request()->user();
        if ($user && (int) $task->assigned_to === (int) $user->id) {
            return;
        }
        $this->authorize('view', $task->lead);
    }

    /** Boleh mengerjakan/mengubah task: assignee, pembuat, atau management. */
    private function authorizeTaskManage(LeadTask $task): void
    {
        $user = request()->user();
        if ($user && ((int) $task->assigned_to === (int) $user->id
            || (int) $task->created_by === (int) $user->id
            || $user->can('manage-sales-leads'))) {
            return;
        }
        abort(403);
    }

    /**
     * Item & lampiran task adalah pekerjaan Inside Sales.
     * Sales pembuat request hanya membaca, bukan mengubah item.
     */
    private function authorizeTaskItems(LeadTask $task): void
    {
        $user = request()->user();
        if ($user && ((int) $task->assigned_to === (int) $user->id
            || $user->can('manage-sales-leads'))) {
            return;
        }
        abort(403);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = LeadTask::with(['lead.customer', 'assignee', 'creator'])->withCount('attachments')->latest();

        // Management melihat semua. Selain itu scope digabung dengan OR:
        // multi-role (sales + inside-sales) harus melihat task yang di-assign
        // kepadanya ATAU lead miliknya — bukan irisan keduanya (AND), yang
        // membuat task hilang saat assignee dan pemilik lead berbeda orang.
        if ($user && ! $user->can('manage-sales-leads')) {
            $query->where(function ($q) use ($user) {
                if ($user->can('manage-inside-sales')) {
                    $q->orWhere('assigned_to', $user->id);
                }
                if (($user->can('view-sales') || $user->can('manage-sales')) && ! $user->can('manage-marketing')) {
                    $q->orWhereHas('lead', fn ($l) => $l->where('assigned_to', $user->id));
                }
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tasks = $query->paginate(15)->withQueryString();

        return view('lead-tasks.index', compact('tasks'));
    }

    public function create(Request $request)
    {
        $lead = null;
        if ($request->filled('lead_id')) {
            $lead = Lead::with('customer')->findOrFail($request->lead_id);
            $this->authorizeTaskRequest($lead);
        }
        $insideSales = User::role('inside-sales')->orderBy('name')->get(['id', 'name']);

        $leads = null;
        if (!$lead) {
            $leadsQuery = Lead::with('customer')->latest()->limit(100);
            $user = $request->user();
            if ($user && $user->hasRole('sales') && !$user->can('manage-marketing') && !$user->can('manage-sales-leads')) {
                $leadsQuery->where('assigned_to', $user->id);
            }
            $leads = $leadsQuery->get();
        }

        return view('lead-tasks.create', compact('lead', 'insideSales', 'leads'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => 'required|exists:users,id',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|in:'.implode(',', LeadTask::PRIORITIES),
            'request_files' => 'nullable|array|max:5',
            'request_files.*' => ['file', 'max:10240', SecureFile::documents()],
        ], [
            'request_files.max' => __('Maximum 5 supporting files.'),
            'request_files.*.max' => __('Each file must be under 10 MB.'),
        ]);

        $lead = Lead::findOrFail($validated['lead_id']);
        $this->authorizeTaskRequest($lead);

        $assignee = User::find($validated['assigned_to']);
        if (!$assignee?->hasRole('inside-sales')) {
            abort(422, __('The task target must be a user with the Inside Sales role.'));
        }

        $validated['created_by'] = auth()->id();
        unset($validated['request_files']);
        $task = LeadTask::create($validated);

        $this->storeRequestFiles($request, $task);

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'action' => 'task_created',
            'changes' => ['task' => $task->title],
        ]);

        // Beri tahu inside sales yang di-assign (kecuali pembuatnya sendiri).
        if ($task->assigned_to && (int) $task->assigned_to !== (int) auth()->id()) {
            $task->assignee?->notify(new \App\Notifications\LeadTaskNotification($task, 'created'));
        }

        return redirect()
            ->route('lead-tasks.show', $task)
            ->with('success', __('Inside sales task created successfully.'));
    }

    public function show(LeadTask $leadTask)
    {
        $leadTask->load(['lead.customer', 'assignee', 'creator', 'comments.user', 'attachments.uploader', 'requestFiles.uploader', 'items.product', 'proposal']);
        $this->authorizeTaskView($leadTask);

        $products = Product::saleable()
            ->with('latestPriceLog.supplier')
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'unit', 'default_price']);

        $productOptions = $products->map(fn (Product $product) => [
            'value' => (string) $product->id,
            'label' => $product->name.($product->sku ? ' · '.$product->sku : ''),
        ])->values()->all();

        $productData = $products->mapWithKeys(fn (Product $product) => [
            $product->id => [
                'description' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit ?: 'pcs',
                'price' => (float) $product->default_price,
                'cost' => $product->latestPriceLog ? (float) $product->latestPriceLog->price : null,
                'supplier' => $product->latestPriceLog?->supplier?->name,
                'date' => $product->latestPriceLog?->created_at?->format('d M Y'),
            ],
        ])->all();

        $estimatorRequest = EstimatorRequest::query()
            ->where('lead_id', $leadTask->lead_id)
            ->whereIn('status', ['answered', 'completed'])
            ->with(['items' => fn ($query) => $query
                ->whereIn('status', ['answered', 'accepted'])
                ->with(['product', 'supplier', 'priceLog.supplier'])
                ->orderBy('id')])
            ->latest('id')
            ->first();

        $estimatorRowByItem = [];
        if ($estimatorRequest) {
            foreach ($estimatorRequest->items as $estimatorItem) {
                $priceLogId = $estimatorItem->priceLog?->id;

                foreach ($leadTask->items as $taskItem) {
                    $matches = ($priceLogId && (int) $taskItem->product_price_log_id === (int) $priceLogId)
                        || ($estimatorItem->product_id
                            && (int) $taskItem->product_id === (int) $estimatorItem->product_id
                            && trim((string) $taskItem->description) === trim((string) $estimatorItem->description));

                    if ($matches) {
                        $estimatorRowByItem[$taskItem->id] = $estimatorItem->id;
                        break;
                    }
                }
            }
        }

        return view('lead-tasks.show', [
            'task' => $leadTask,
            'products' => $products,
            'productOptions' => $productOptions,
            'productData' => $productData,
            'estimatorRequest' => $estimatorRequest,
            'estimatorRowByItem' => $estimatorRowByItem,
            'defaultMargin' => \App\Models\AppSetting::defaultMarginPercent(),
        ]);
    }

    public function edit(LeadTask $leadTask)
    {
        $leadTask->load(['lead.customer']);
        $this->authorizeTaskManage($leadTask);
        $insideSales = User::role('inside-sales')->orderBy('name')->get(['id', 'name']);

        return view('lead-tasks.edit', ['task' => $leadTask, 'insideSales' => $insideSales]);
    }

    public function update(Request $request, LeadTask $leadTask)
    {
        $this->authorizeTaskManage($leadTask);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => 'required|exists:users,id',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|in:'.implode(',', LeadTask::PRIORITIES),
            'status' => 'required|in:'.implode(',', LeadTask::STATUSES),
        ]);

        $assignee = User::find($validated['assigned_to']);
        if (!$assignee?->hasRole('inside-sales')) {
            abort(422, __('The task target must be a user with the Inside Sales role.'));
        }

        $oldStatus = $leadTask->status;
        $leadTask->update($validated);

        if ($oldStatus !== $leadTask->status) {
            LeadActivity::create([
                'lead_id' => $leadTask->lead_id,
                'user_id' => auth()->id(),
                'action' => 'task_status_changed',
                'changes' => ['task_status' => ['old' => $oldStatus, 'new' => $leadTask->status]],
            ]);

            // Beri tahu creator + assignee (kecuali aktor sendiri).
            foreach ([$leadTask->creator, $leadTask->assignee] as $recipient) {
                if ($recipient && (int) $recipient->id !== (int) auth()->id()) {
                    $recipient->notify(new \App\Notifications\LeadTaskNotification($leadTask, 'status'));
                }
            }
        }

        return redirect()
            ->route('lead-tasks.show', $leadTask)
            ->with('success', __('Task updated successfully.'));
    }

    public function destroy(LeadTask $leadTask)
    {
        if (!request()->user()?->can('manage-sales-leads')) {
            abort(403);
        }
        $leadTask->delete();

        return redirect()
            ->route('lead-tasks.index')
            ->with('success', __('Task deleted successfully.'));
    }

    public function storeComment(Request $request, LeadTask $leadTask)
    {
        $this->authorizeTaskView($leadTask);

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $leadTask->comments()->create([
            'user_id' => auth()->id(),
            'body' => $validated['body'],
        ]);

        return redirect()
            ->route('lead-tasks.show', $leadTask)
            ->with('success', __('Comment sent.'));
    }

    /** Tambah satu item quotation dari Inside Sales (sebelum file diterima). */
    public function storeItem(Request $request, LeadTask $leadTask)
    {
        $this->authorizeTaskItems($leadTask);

        // Input harga memakai pemisah ribuan (18.000.000). Normalisasi dulu
        // sebelum validasi agar tetap tersimpan sebagai angka.
        $request->merge([
            'unit_price' => Rupiah::normalize($request->input('unit_price')),
            'unit_cost' => $request->filled('unit_cost') ? Rupiah::normalize($request->input('unit_cost')) : null,
            'discount' => $request->filled('discount') ? Rupiah::normalize($request->input('discount')) : null,
        ]);

        $validated = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'product_price_log_id' => 'nullable|integer|exists:product_price_logs,id',
            'description' => 'required|string|max:255',
            'scope_of_supply' => 'nullable|string|max:100',
            'lead_time' => 'nullable|string|max:100',
            'section' => 'nullable|string|max:100',
            'quantity' => 'required|numeric|min:0.01',
            'unit' => 'nullable|string|max:16',
            'unit_price' => 'required|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'target_margin_percent' => 'nullable|numeric|min:0|max:99.99',
            'discount' => 'nullable|numeric|min:0',
        ]);

        $qty = (float) $validated['quantity'];
        $price = (float) $validated['unit_price'];
        $discount = (float) ($validated['discount'] ?? 0);

        abort_if(
            ! empty($validated['product_price_log_id']) && empty($validated['product_id']),
            422,
            __('Select a product before choosing a price history.')
        );

        // Harga modal selalu diambil dari riwayat resmi (bukan input bebas)
        // agar snapshot harga dan margin tidak bisa dimanipulasi dari form.
        $priceLog = null;
        if (! empty($validated['product_price_log_id'])) {
            $priceLog = ProductPriceLog::query()
                ->whereKey($validated['product_price_log_id'])
                ->where('price_type', 'cost')
                ->when(! empty($validated['product_id']), fn ($query) => $query
                    ->where('product_id', $validated['product_id']))
                ->first();

            abort_if(! $priceLog, 422, __('The selected price history does not match this product.'));
        }

        if (! $priceLog && ! empty($validated['product_id'])) {
            $priceLog = ProductPriceLog::query()
                ->where('product_id', $validated['product_id'])
                ->where('price_type', 'cost')
                ->latest('created_at')
                ->latest('id')
                ->first();
        }

        $unitCost = $priceLog?->price !== null
            ? (float) $priceLog->price
            : ($request->user()->can('manage-inside-sales')
                && isset($validated['unit_cost'])
                && $validated['unit_cost'] !== ''
                ? (float) $validated['unit_cost']
                : null);

        if ($unitCost !== null && (($price * $qty) - $discount) < ($unitCost * $qty)) {
            throw ValidationException::withMessages([
                'unit_price' => __('Selling price is below cost. Fix the selling price or target margin first.'),
            ]);
        }

        $leadTask->items()->create([
            'product_id' => $validated['product_id'] ?? null,
            'product_price_log_id' => $priceLog?->id,
            'description' => $validated['description'],
            'scope_of_supply' => $validated['scope_of_supply'] ?? null,
            'lead_time' => $validated['lead_time'] ?? null,
            'section' => $validated['section'] ?? 'MATERIAL',
            'quantity' => $qty,
            'unit' => $validated['unit'] ?? 'pcs',
            'unit_price' => $price,
            'unit_cost' => $unitCost,
            'target_margin_percent' => isset($validated['target_margin_percent'])
                ? (float) $validated['target_margin_percent']
                : null,
            'discount' => $discount,
            'subtotal' => LeadTaskItem::subtotalFor($qty, $price, $discount),
        ]);

        return redirect()
            ->route('lead-tasks.show', $leadTask)
            ->with('success', __('Quotation item added.'));
    }

    /** Hapus item quotation milik task ini. */
    public function syncItems(Request $request, LeadTask $leadTask)
    {
        $this->authorizeTaskItems($leadTask);

        // Normalisasi input Rupiah per baris sebelum validasi.
        $request->merge([
            'items' => collect($request->input('items', []))->map(function ($item) {
                if (! is_array($item)) {
                    return $item;
                }

                foreach (['unit_price', 'unit_cost', 'discount'] as $key) {
                    if (array_key_exists($key, $item)) {
                        $item[$key] = $item[$key] === null || $item[$key] === ''
                            ? null
                            : Rupiah::normalize($item[$key]);
                    }
                }

                return $item;
            })->all(),
        ]);

        $validated = $request->validate([
            'items' => 'nullable|array|max:100',
            'items.*.id' => 'nullable|integer',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.product_price_log_id' => 'nullable|integer|exists:product_price_logs,id',
            'items.*.description' => 'required|string|max:255',
            'items.*.scope_of_supply' => 'nullable|string|max:100',
            'items.*.lead_time' => 'nullable|string|max:100',
            'items.*.section' => 'nullable|string|max:100',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'nullable|string|max:16',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.target_margin_percent' => 'nullable|numeric|min:0|max:99.99',
            'items.*.discount' => 'nullable|numeric|min:0',
        ]);

        $items = $validated['items'] ?? [];
        $existing = $leadTask->items()->get()->keyBy('id');
        $keepIds = [];

        DB::transaction(function () use ($request, $leadTask, $items, $existing, &$keepIds) {
            foreach ($items as $index => $data) {
                $qty = (float) $data['quantity'];
                $price = (float) $data['unit_price'];
                $discount = (float) ($data['discount'] ?? 0);

                abort_if(
                    ! empty($data['product_price_log_id']) && empty($data['product_id']),
                    422,
                    __('Select a product before choosing a price history.')
                );

                $priceLog = null;
                if (! empty($data['product_price_log_id'])) {
                    $priceLog = ProductPriceLog::query()
                        ->whereKey($data['product_price_log_id'])
                        ->where('price_type', 'cost')
                        ->when(! empty($data['product_id']), fn ($query) => $query
                            ->where('product_id', $data['product_id']))
                        ->first();

                    abort_if(! $priceLog, 422, __('The selected price history does not match this product.'));
                }

                if (! $priceLog && ! empty($data['product_id'])) {
                    $priceLog = ProductPriceLog::query()
                        ->where('product_id', $data['product_id'])
                        ->where('price_type', 'cost')
                        ->latest('created_at')
                        ->latest('id')
                        ->first();
                }

                $unitCost = $priceLog?->price !== null
                    ? (float) $priceLog->price
                    : ($request->user()->can('manage-inside-sales')
                        && isset($data['unit_cost'])
                        && $data['unit_cost'] !== ''
                        ? (float) $data['unit_cost']
                        : null);

                if ($unitCost !== null && (($price * $qty) - $discount) < ($unitCost * $qty)) {
                    throw ValidationException::withMessages([
                        "items.{$index}.unit_price" => __('Selling price is below cost. Fix the selling price or target margin first.'),
                    ]);
                }

                $item = null;
                if (! empty($data['id'])) {
                    $item = $existing->get((int) $data['id']);
                    abort_unless($item, 404);
                }

                $payload = [
                    'product_id' => $data['product_id'] ?? null,
                    'product_price_log_id' => $priceLog?->id,
                    'description' => $data['description'],
                    'scope_of_supply' => $data['scope_of_supply'] ?? null,
                    'lead_time' => $data['lead_time'] ?? null,
                    'section' => ($data['section'] ?? null) ?: 'MATERIAL',
                    'quantity' => $qty,
                    'unit' => $data['unit'] ?? 'pcs',
                    'unit_price' => $price,
                    'unit_cost' => $unitCost,
                    'target_margin_percent' => $data['target_margin_percent'] ?? null,
                    'discount' => $discount,
                    'subtotal' => LeadTaskItem::subtotalFor($qty, $price, $discount),
                ];

                if ($item) {
                    $item->update($payload);
                } else {
                    $item = $leadTask->items()->create($payload);
                }

                $keepIds[] = $item->id;
            }

            $leadTask->items()->whereNotIn('id', $keepIds)->delete();
        });

        return redirect()->route('lead-tasks.show', $leadTask)
            ->with('success', __('Quotation items saved.'));
    }

    public function destroyItem(LeadTask $leadTask, LeadTaskItem $item)
    {
        $this->authorizeTaskItems($leadTask);

        abort_unless((int) $item->lead_task_id === (int) $leadTask->id, 404);

        $item->delete();

        return redirect()
            ->route('lead-tasks.show', $leadTask)
            ->with('success', __('Quotation item removed.'));
    }

    /**
     * Upload file quotation baru dari Inside Sales. File lama TIDAK ditimpa —
     * setiap upload jadi versi baru supaya revisi tetap bisa diunduh.
     */
    /**
     * Request mulai dikerjakan begitu Inside Sales mengunggah file penawaran:
     * status Todo naik otomatis ke On Progress (sekali saja, tidak menurunkan
     * status Review/Done yang sudah lebih jauh).
     */
    private function markTaskInProgress(LeadTask $task): void
    {
        if (! in_array($task->status, [null, '', 'todo'], true)) {
            return;
        }

        $oldStatus = $task->status;
        $task->update(['status' => 'in_progress']);

        LeadActivity::create([
            'lead_id' => $task->lead_id,
            'user_id' => auth()->id(),
            'action' => 'task_status_changed',
            'changes' => ['task_status' => ['old' => $oldStatus, 'new' => 'in_progress']],
        ]);
    }

    public function storeAttachment(Request $request, LeadTask $leadTask)
    {
        $this->authorizeTaskItems($leadTask);

        $request->validate([
            'attachment' => ['required', 'file', 'max:10240', \App\Rules\SecureFile::documents()],
        ], [
            'attachment.required' => __('Please choose a file to upload.'),
            'attachment.max' => __('The file must be under 10 MB.'),
        ]);

        $file = $request->file('attachment');
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
        $path = $file->storeAs(
            'lead-tasks/'.$leadTask->id,
            \Illuminate\Support\Str::uuid().'_'.$safeName,
            'private'
        );

        $leadTask->attachments()->create([
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'uploaded_by' => auth()->id(),
        ]);

        $this->markTaskInProgress($leadTask);

        // Beri tahu peminta (pembuat task) kalau yang upload bukan dia sendiri.
        $requester = $leadTask->creator;
        if ($requester && (int) $requester->id !== (int) auth()->id()) {
            $requester->notify(new \App\Notifications\LeadTaskNotification($leadTask, 'quotation'));
        }

        return redirect()
            ->route('lead-tasks.show', $leadTask)
            ->with('success', __('Quotation file uploaded.'));
    }

    /**
     * Tombol "Selesai": tutup request dari halaman detail maupun daftar.
     * Hanya assignee Inside Sales atau management, sama seperti item quotation.
     */
    public function complete(LeadTask $leadTask)
    {
        $this->authorizeTaskItems($leadTask);

        if ($leadTask->status === 'done') {
            return redirect()->back()->with('info', __('This request is already marked as done.'));
        }

        $oldStatus = $leadTask->status;
        $leadTask->update(['status' => 'done']);

        LeadActivity::create([
            'lead_id' => $leadTask->lead_id,
            'user_id' => auth()->id(),
            'action' => 'task_status_changed',
            'changes' => ['task_status' => ['old' => $oldStatus, 'new' => 'done']],
        ]);

        // Status dipakai bersama oleh menu Sales dan Inside Sales.
        foreach ([$leadTask->creator, $leadTask->assignee] as $recipient) {
            if ($recipient && (int) $recipient->id !== (int) auth()->id()) {
                $recipient->notify(new \App\Notifications\LeadTaskNotification($leadTask, 'status'));
            }
        }

        return redirect()->back()->with('success', __('Request marked as done.'));
    }

    /**
     * Terima file quotation Inside Sales → otomatis buat Quotation (proposal)
     * draft yang tertaut ke lead/customer task, lalu file dipasang sebagai
     * lampiran quotation tersebut. Akses menu Quotation mengikuti lead
     * (pemilik lead + management), sama seperti quotation lain.
     */
    public function acceptAttachment(Request $request, LeadTask $leadTask, \App\Models\LeadTaskAttachment $attachment)
    {
        abort_unless((int) $attachment->lead_task_id === (int) $leadTask->id, 404);

        $user = $request->user();
        $lead = $leadTask->lead;

        // Hanya peminta (pembuat task), pemilik lead, atau management —
        // bukan semua role sales.
        $canAccept = $user && (
            (int) $leadTask->created_by === (int) $user->id
            || (int) ($lead?->assigned_to ?? 0) === (int) $user->id
            || $user->can('manage-sales-leads')
        );

        abort_unless($canAccept, 403);

        // Sales per PT hanya boleh menerima quotation pada PT-nya sendiri.
        abort_unless(
            \App\Support\PtAccess::canWritePt($user, $lead?->customer?->pt_group),
            403
        );

        abort_if($attachment->accepted_at !== null, 422, __('This quotation file has already been accepted.'));

        $items = $leadTask->items()->get();
        abort_if($items->isEmpty(), 422, __('Add at least one quotation item before accepting the file.'));
        abort_if(
            \App\Models\Proposal::where('lead_task_id', $leadTask->id)->exists(),
            422,
            __('This task already has a quotation.')
        );
        abort_if(
            $lead?->hasActiveProposal(),
            422,
            __('This lead already has a quotation. Revise the existing quotation instead of creating a new one.')
        );

        $proposal = \Illuminate\Support\Facades\DB::transaction(function () use ($attachment, $leadTask, $lead, $items) {
            $defaultMargin = AppSetting::defaultMarginPercent();

            $proposal = \App\Models\Proposal::create([
                'lead_id' => $leadTask->lead_id,
                'lead_task_id' => $leadTask->id,
                'proposal_number' => $this->generateProposalNumber($lead),
                'created_by' => $lead?->assigned_to ?? auth()->id(),
                'proposal_date' => now()->toDateString(),
                'customer' => $lead?->customer?->name ?? '-',
                'letterhead_pt' => $lead?->pt_group ?: $lead?->customer?->pt_group,
                'tax_rate' => 0,
                'default_margin_percent' => $defaultMargin,
                'status' => 'draft',
                'internal_notes' => __('Created automatically from Inside Sales :count item(s) and file :file (task #:task).', [
                    'count' => $items->count(),
                    'file' => $attachment->original_name,
                    'task' => $leadTask->id,
                ]),
            ]);

            foreach ($items as $item) {
                $proposal->items()->create([
                    'product_id' => $item->product_id,
                    'product_price_log_id' => $item->product_price_log_id,
                    'description' => $item->description,
                    'scope_of_supply' => $item->scope_of_supply,
                    'lead_time' => $item->lead_time,
                    'stock_status' => $item->stock_status,
                    'stock_eta' => $item->stock_eta,
                    'warranty_info' => $item->warranty_info,
                    'section' => $item->section ?: 'MATERIAL',
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'unit_cost' => $item->unit_cost,
                    'target_margin_percent' => $item->target_margin_percent ?? $defaultMargin,
                    'discount' => $item->discount,
                    'subtotal' => $item->subtotal,
                ]);
            }

            $proposal->recalculate();

            // File ikut dipasang sebagai lampiran quotation.
            \App\Models\ProposalAttachment::create([
                'proposal_id' => $proposal->id,
                'file_path' => $attachment->file_path,
                'original_name' => $attachment->original_name,
                'mime' => $attachment->mime,
                'size' => $attachment->size,
                'uploaded_by' => $attachment->uploaded_by,
            ]);

            $attachment->update([
                'proposal_id' => $proposal->id,
                'accepted_by' => auth()->id(),
                'accepted_at' => now(),
            ]);

            return $proposal;
        });

        $this->versions->record($proposal, null, __('Initial quotation'), auth()->id());

        // Kabari Inside Sales bahwa file-nya sudah diterima.
        $assignee = $leadTask->assignee;
        if ($assignee && (int) $assignee->id !== (int) auth()->id()) {
            $assignee->notify(new \App\Notifications\LeadTaskNotification($leadTask, 'accepted'));
        }

        return redirect()
            ->route('sales.proposals.show', $proposal)
            ->with('success', __('Quotation created from the accepted file.'));
    }

    /** Nomor quotation baku: PT/urut/inisial/tahun. */
    private function generateProposalNumber(?Lead $lead): string
    {
        $pt = $lead?->pt_group ?: $lead?->customer?->pt_group ?: 'WANI';

        return $this->numbers->generate($pt, $lead?->assignee ?? auth()->user());
    }

    public function downloadAttachment(LeadTask $leadTask, \App\Models\LeadTaskAttachment $attachment)
    {
        $this->authorizeTaskView($leadTask);

        abort_unless((int) $attachment->lead_task_id === (int) $leadTask->id, 404);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('private')->exists($attachment->file_path), 404, __('File not found.'));

        return \Illuminate\Support\Facades\Storage::disk('private')
            ->download($attachment->file_path, $attachment->original_name);
    }

    /**
     * Pratinjau lampiran quotation task tanpa mengunduh (PDF/gambar inline).
     */
    public function previewAttachment(LeadTask $leadTask, \App\Models\LeadTaskAttachment $attachment)
    {
        $this->authorizeTaskView($leadTask);

        abort_unless((int) $attachment->lead_task_id === (int) $leadTask->id, 404);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('private')->exists($attachment->file_path), 404, __('File not found.'));

        return \App\Rules\SecureFile::fileResponse(
            'private',
            $attachment->file_path,
            $attachment->original_name
        );
    }

    public function destroyAttachment(LeadTask $leadTask, \App\Models\LeadTaskAttachment $attachment)
    {
        $this->authorizeTaskItems($leadTask);

        abort_unless((int) $attachment->lead_task_id === (int) $leadTask->id, 404);

        \Illuminate\Support\Facades\Storage::disk('private')->delete($attachment->file_path);
        $attachment->delete();

        return redirect()
            ->route('lead-tasks.show', $leadTask)
            ->with('success', __('Quotation file deleted.'));
    }

    /**
     * Upload lampiran pendukung dari Sales (brief/requirement), bisa menyusul
     * setelah request dibuat. File quotation tetap lewat storeAttachment.
     */
    public function storeRequestFile(Request $request, LeadTask $leadTask)
    {
        $this->authorizeRequestFileUpload($leadTask);

        $request->validate([
            'request_files' => 'required|array|max:5',
            'request_files.*' => ['file', 'max:10240', SecureFile::documents()],
        ], [
            'request_files.required' => __('Please choose a file to upload.'),
            'request_files.max' => __('Maximum 5 supporting files.'),
            'request_files.*.max' => __('Each file must be under 10 MB.'),
        ]);

        $this->storeRequestFiles($request, $leadTask);

        return redirect()
            ->route('lead-tasks.show', $leadTask)
            ->with('success', __('Supporting files uploaded.'));
    }

    public function downloadRequestFile(LeadTask $leadTask, LeadTaskRequestFile $requestFile)
    {
        $this->authorizeTaskView($leadTask);

        abort_unless((int) $requestFile->lead_task_id === (int) $leadTask->id, 404);
        abort_unless(Storage::disk('private')->exists($requestFile->file_path), 404, __('File not found.'));

        return SecureFile::fileResponse(
            'private',
            $requestFile->file_path,
            $requestFile->original_name
        );
    }

    public function destroyRequestFile(Request $request, LeadTask $leadTask, LeadTaskRequestFile $requestFile)
    {
        $this->authorizeTaskView($leadTask);

        abort_unless((int) $requestFile->lead_task_id === (int) $leadTask->id, 404);

        $user = $request->user();
        $canDelete = $user && (
            (int) $requestFile->uploaded_by === (int) $user->id
            || $user->can('manage-sales-leads')
        );

        abort_unless($canDelete, 403);

        Storage::disk('private')->delete($requestFile->file_path);
        $requestFile->delete();

        return redirect()
            ->route('lead-tasks.show', $leadTask)
            ->with('success', __('Supporting file deleted.'));
    }

    /**
     * Sales pembuat request, pemilik lead, atau management boleh menambah
     * lampiran pendukung. Inside Sales hanya melihat/mengunduh.
     */
    private function authorizeRequestFileUpload(LeadTask $leadTask): void
    {
        $user = request()->user();

        if ($user && (
            (int) $leadTask->created_by === (int) $user->id
            || (int) $leadTask->lead?->assigned_to === (int) $user->id
            || $user->can('manage-sales-leads')
        )) {
            return;
        }

        abort(403);
    }

    private function storeRequestFiles(Request $request, LeadTask $leadTask): void
    {
        foreach ($request->file('request_files', []) as $file) {
            if (! $file) {
                continue;
            }

            $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
            $path = $file->storeAs(
                'lead-tasks/'.$leadTask->id.'/request-files',
                \Illuminate\Support\Str::uuid().'_'.$safeName,
                'private'
            );

            $leadTask->requestFiles()->create([
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
                'uploaded_by' => auth()->id(),
            ]);
        }
    }
}
