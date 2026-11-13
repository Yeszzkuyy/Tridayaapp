<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\EstimatorRequest;
use App\Models\EstimatorRequestItem;
use App\Models\Lead;
use App\Models\Product;
use App\Models\ProductPriceLog;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\EstimatorRequestAnsweredNotification;
use App\Notifications\EstimatorRequestCreatedNotification;
use App\Notifications\EstimatorRequestRevisionNotification;
use App\Support\PtAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class EstimatorRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless(
            $user->can('manage-price-requests')
            || $user->can('manage-sales')
            || $user->can('manage-sales-leads')
            || $user->can('manage-admin')
            || $user->hasRole('ceo'),
            403
        );

        // Sales memakai tab Product Price di menu Request; halaman Queue
        // berdiri sendiri hanya untuk Estimator/Management.
        if (! ($user->can('manage-price-requests')
            || $user->can('manage-sales-leads')
            || $user->can('manage-admin')
            || $user->hasRole('ceo'))) {
            return redirect()->route('sales.requests.index', ['tab' => 'product-price']);
        }

        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|in:open,'.implode(',', EstimatorRequest::STATUSES),
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $query = EstimatorRequest::query()
            ->with([
                'customer:id,name,pt_group',
                'requester:id,name',
                'handler:id,name',
                'items',
            ])
            ->withCount([
                'items',
                'items as answered_items_count' => fn ($items) => $items->whereIn('status', ['answered', 'accepted']),
            ])
            ->when($validated['q'] ?? null, function ($query, string $term): void {
                $query->where(function ($search) use ($term): void {
                    $search->whereHas('customer', fn ($customer) => $customer->whereLike('name', "%{$term}%"));

                    if (ctype_digit($term)) {
                        $search->orWhere('id', (int) $term);
                    }
                });
            })
            ->when(($validated['status'] ?? null) === 'open', fn ($query) => $query
                ->whereIn('status', EstimatorRequest::OPEN_STATUSES))
            ->when(($validated['status'] ?? null) && $validated['status'] !== 'open', fn ($query, string $status) => $query
                ->where('status', $status))
            ->when($validated['date_from'] ?? null, fn ($query, string $date) => $query
                ->whereDate('created_at', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($query, string $date) => $query
                ->whereDate('created_at', '<=', $date));

        if (! ($user->can('manage-price-requests')
            || $user->can('manage-sales-leads')
            || $user->can('manage-admin')
            || $user->hasRole('ceo'))) {
            $query->where('requested_by', $user->id);
        }

        $summary = [
            'waiting' => (clone $query)->where('status', 'submitted')->count(),
            'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
            'answered' => (clone $query)->where('status', 'answered')->count(),
            'revision' => (clone $query)->where('status', 'revision')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
        ];

        $isEstimator = $user->can('manage-price-requests');

        // Antrean baru dipisah ke kartu sendiri supaya langsung terlihat
        // oleh Estimator; tabel di bawah khusus request yang sudah dikerjakan.
        $newRequests = $isEstimator
            ? (clone $query)->where('status', 'submitted')
                ->with(['customer:id,name,pt_group', 'requester:id,name'])
                ->withCount('items')
                ->orderBy('created_at')
                ->limit(12)
                ->get()
            : collect();

        if ($isEstimator) {
            $query->where('status', '!=', 'submitted');
        }

        $requests = $query
            ->orderByRaw("CASE WHEN status IN ('completed', 'rejected', 'cancelled') THEN 1 ELSE 0 END")
            ->orderBy('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('estimator.requests.index', [
            'requests' => $requests,
            'newRequests' => $newRequests,
            'isEstimator' => $isEstimator,
            'statuses' => EstimatorRequest::STATUSES,
            'summary' => $summary,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $this->authorize('create', EstimatorRequest::class);

        $backUrl = $request->filled('proposal_id')
            ? route('sales.proposals.show', (int) $request->query('proposal_id'))
            : ($request->filled('lead_id')
                ? route('leads.show', (int) $request->query('lead_id'))
                : ($request->filled('project_id')
                    ? route('projects.show', (int) $request->query('project_id'))
                    : route('estimator.requests.index')));

        return view('estimator.requests.create', [
            'customers' => $this->customerOptions($user),
            'products' => Product::saleable()->orderBy('name')->limit(500)->get(['id', 'sku', 'name', 'unit']),
            'presetCustomerId' => $request->query('customer_id'),
            'presetLeadId' => $request->query('lead_id'),
            'presetProjectId' => $request->query('project_id'),
            'presetProposalId' => $request->query('proposal_id'),
            'backUrl' => $backUrl,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', EstimatorRequest::class);

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'lead_id' => 'nullable|exists:leads,id',
            'project_id' => 'nullable|exists:projects,id',
            'proposal_id' => 'nullable|exists:proposals,id',
            'notes' => 'nullable|string|max:2000',
            'attachment' => 'nullable|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png,zip',
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01|max:999999',
            'items.*.unit' => 'nullable|string|max:16',
            'items.*.notes' => 'nullable|string|max:255',
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);
        abort_unless(PtAccess::canWritePt($request->user(), $customer->pt_group), 403);

        $leadId = $validated['lead_id'] ?? null;
        if ($leadId) {
            $lead = Lead::findOrFail($leadId);
            abort_unless((int) $lead->customer_id === (int) $customer->id, 422, __('The selected lead does not belong to this customer.'));
        }

        $projectId = $validated['project_id'] ?? null;
        if ($projectId) {
            $project = Project::findOrFail($projectId);
            abort_unless((int) $project->customer_id === (int) $customer->id, 422, __('The selected project does not belong to this customer.'));
        }

        $proposalId = $validated['proposal_id'] ?? null;
        if ($proposalId) {
            $proposal = Proposal::findOrFail($proposalId);
            abort_unless((int) $proposal->lead?->customer_id === (int) $customer->id, 422, __('The selected quotation does not belong to this customer.'));
        }

        $storedPath = null;

        try {
            $estimatorRequest = DB::transaction(function () use ($request, $validated, $customer, $leadId, $projectId, $proposalId, &$storedPath) {
                if ($request->hasFile('attachment')) {
                    $file = $request->file('attachment');
                    $storedPath = $file->store('estimator-requests', 'private');
                    $attachmentName = $file->getClientOriginalName();
                    $attachmentSize = $file->getSize();
                }

                $estimatorRequest = EstimatorRequest::create([
                    'customer_id' => $customer->id,
                    'lead_id' => $leadId,
                    'project_id' => $projectId,
                    'proposal_id' => $proposalId,
                    'requested_by' => $request->user()->id,
                    'status' => 'submitted',
                    'notes' => $validated['notes'] ?? null,
                    'attachment_path' => $storedPath,
                    'attachment_name' => $attachmentName ?? null,
                    'attachment_size' => $attachmentSize ?? null,
                ]);

                foreach ($validated['items'] as $item) {
                    $estimatorRequest->items()->create([
                        'product_id' => $item['product_id'] ?? null,
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit' => $item['unit'] ?? 'pcs',
                        'notes' => $item['notes'] ?? null,
                        'status' => 'pending',
                    ]);
                }

                return $estimatorRequest;
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('private')->delete($storedPath);
            }

            throw $exception;
        }

        $estimatorRequest->load(['customer', 'requester']);

        $recipients = User::role('estimator')->get();
        if ($recipients->isEmpty()) {
            $recipients = User::role('lead-technician')->get();
        }
        $recipients = $recipients->reject(fn (User $user) => (int) $user->id === (int) $request->user()->id);
        Notification::send($recipients, new EstimatorRequestCreatedNotification($estimatorRequest));

        return redirect()->route('estimator.requests.show', $estimatorRequest)
            ->with('success', __('Product price request sent to Estimator.'));
    }

    public function show(Request $request, EstimatorRequest $estimatorRequest)
    {
        $this->authorize('view', $estimatorRequest);

        $estimatorRequest->load([
            'customer', 'lead', 'project', 'proposal',
            'requester', 'handler',
            'items.product.latestCostLog.supplier', 'items.supplier', 'items.answeredBy',
        ]);

        $user = $request->user();
        $suppliers = $user->can('answer', $estimatorRequest)
            ? Supplier::active()->orderBy('name')->get(['id', 'name', 'contact_person', 'phone', 'email'])
            : collect();

        $backUrl = $user->can('manage-price-requests')
            || $user->can('manage-sales-leads')
            || $user->can('manage-admin')
            || $user->hasRole('ceo')
            ? route('estimator.requests.index')
            : route('sales.requests.index', ['tab' => 'product-price']);
        $backUrl = $estimatorRequest->proposal_id
            ? route('sales.proposals.show', $estimatorRequest->proposal_id)
            : ($estimatorRequest->lead_id
                ? route('leads.show', $estimatorRequest->lead_id)
                : ($estimatorRequest->project_id
                    ? route('projects.show', $estimatorRequest->project_id)
                    : $backUrl));

        return view('estimator.requests.show', compact('estimatorRequest', 'suppliers', 'backUrl'));
    }

    /**
     * Direktori supplier untuk Estimator: kontak lengkap supaya bisa
     * langsung menghubungi vendor/distributor saat mengecek harga.
     */
    public function suppliers(Request $request)
    {
        $user = $request->user();
        abort_unless(
            $user->can('manage-price-requests')
            || $user->can('manage-admin')
            || $user->can('manage-sales-leads'),
            403
        );

        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
        ]);

        $suppliers = Supplier::query()
            ->withCount('products')
            ->when($validated['q'] ?? null, fn ($query, string $term) => $query->where(
                fn ($search) => $search->whereLike('name', "%{$term}%")
                    ->orWhereLike('contact_person', "%{$term}%")
                    ->orWhereLike('phone', "%{$term}%")
                    ->orWhereLike('email', "%{$term}%")
            ))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('estimator.suppliers.index', compact('suppliers'));
    }

    public function answer(Request $request, EstimatorRequest $estimatorRequest)
    {
        $this->authorize('answer', $estimatorRequest);

        // Harga dikirim dari form berformat ribuan (mis. 444.444.444).
        // Bersihkan pemisah sebelum validasi agar tersimpan sebagai angka.
        $request->merge([
            'items' => collect($request->input('items', []))->map(function ($item) {
                if (! is_array($item)) {
                    return $item;
                }

                if (array_key_exists('price', $item)) {
                    $item['price'] = is_scalar($item['price'])
                        ? str_replace(['.', ' ', "\u{00A0}"], '', (string) $item['price'])
                        : '';
                }

                if (($item['stock_status'] ?? null) === 'ready') {
                    $item['stock_eta'] = null;
                }

                return $item;
            })->all(),
        ]);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:estimator_request_items,id',
            'items.*.supplier_id' => 'required|exists:suppliers,id',
            'items.*.price' => 'required|numeric|min:0.01|max:999999999999',
            'items.*.stock_status' => 'required|in:ready,indent',
            'items.*.stock_eta' => 'nullable|string|max:100',
            'items.*.warranty_info' => 'required|string|max:255',
            'items.*.valid_until' => 'nullable|date',
            'items.*.notes' => 'nullable|string|max:255',
        ]);

        foreach ($validated['items'] as $index => $item) {
            if ($item['stock_status'] === 'indent' && blank($item['stock_eta'] ?? null)) {
                throw ValidationException::withMessages([
                    "items.{$index}.stock_eta" => __('Stock estimate is required for indent items.'),
                ]);
            }
        }

        DB::transaction(function () use ($request, $estimatorRequest, $validated): void {
            $locked = EstimatorRequest::query()->lockForUpdate()->findOrFail($estimatorRequest->id);
            $this->authorize('answer', $locked);

            $itemIds = collect($validated['items'])->pluck('id')->map(fn ($id) => (int) $id)->all();
            $items = $locked->items()->whereIn('id', $itemIds)->get()->keyBy('id');
            abort_if($items->isEmpty(), 422, __('No valid items selected.'));

            foreach ($validated['items'] as $data) {
                $item = $items->get((int) $data['id']);
                if (! $item) {
                    continue;
                }

                if ($item->product_id) {
                    ProductPriceLog::create([
                        'product_id' => $item->product_id,
                        'supplier_id' => $data['supplier_id'],
                        'price' => $data['price'],
                        'price_type' => 'cost',
                        'currency' => 'IDR',
                        'stock_status' => $data['stock_status'],
                        'stock_eta' => $data['stock_eta'] ?? null,
                        'warranty_info' => $data['warranty_info'],
                        'valid_until' => $data['valid_until'] ?? null,
                        'estimator_request_item_id' => $item->id,
                        'updated_by' => $request->user()->id,
                        'notes' => $data['notes'] ?? null,
                    ]);

                    Product::whereKey($item->product_id)->update(['cost_price' => $data['price']]);
                }

                $item->update([
                    'supplier_id' => $data['supplier_id'],
                    'price' => $data['price'],
                    'stock_status' => $data['stock_status'],
                    'stock_eta' => $data['stock_eta'] ?? null,
                    'warranty_info' => $data['warranty_info'],
                    'valid_until' => $data['valid_until'] ?? null,
                    'notes' => $data['notes'] ?? $item->notes,
                    'status' => 'answered',
                    'answered_by' => $request->user()->id,
                    'answered_at' => now(),
                ]);
            }

            $allAnswered = ! $locked->items()
                ->whereNotIn('status', ['answered', 'accepted'])
                ->exists();
            $locked->update([
                'handled_by' => $request->user()->id,
                'status' => $allAnswered ? 'answered' : 'in_progress',
                'completed_at' => null,
                'revision_reason' => $allAnswered ? null : $locked->revision_reason,
                'revision_requested_at' => $allAnswered ? null : $locked->revision_requested_at,
            ]);
        });

        $estimatorRequest->refresh()->load(['customer', 'requester']);

        if ($estimatorRequest->requester) {
            $estimatorRequest->requester->notify(new EstimatorRequestAnsweredNotification($estimatorRequest));
        }

        return back()->with('success', __('Prices sent to Sales for review.'));
    }

    public function accept(Request $request, EstimatorRequest $estimatorRequest)
    {
        $this->authorize('decide', $estimatorRequest);
        abort_unless($estimatorRequest->status === 'answered', 422);

        DB::transaction(function () use ($estimatorRequest): void {
            $estimatorRequest->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            $estimatorRequest->items()->update(['status' => 'accepted']);
        });

        return back()->with('success', __('Product prices accepted.'));
    }

    public function revision(Request $request, EstimatorRequest $estimatorRequest)
    {
        $this->authorize('decide', $estimatorRequest);
        abort_unless($estimatorRequest->status === 'answered', 422);

        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        $estimatorRequest->update([
            'status' => 'revision',
            'revision_reason' => $validated['reason'],
            'revision_requested_at' => now(),
        ]);
        $estimatorRequest->items()->where('status', 'answered')->update(['status' => 'revision']);

        $estimatorRequest->refresh()->load(['customer', 'requester']);
        $recipients = User::permission('manage-price-requests')
            ->where('id', '!=', $request->user()->id)
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new EstimatorRequestRevisionNotification($estimatorRequest));
        }

        return back()->with('success', __('Revision request sent to Estimator.'));
    }

    public function cancel(EstimatorRequest $estimatorRequest)
    {
        $this->authorize('cancel', $estimatorRequest);

        $estimatorRequest->update(['status' => 'cancelled']);

        return back()->with('success', __('Product price request cancelled.'));
    }

    public function downloadAttachment(Request $request, EstimatorRequest $estimatorRequest)
    {
        $this->authorize('view', $estimatorRequest);
        abort_unless(
            $estimatorRequest->attachment_path && Storage::disk('private')->exists($estimatorRequest->attachment_path),
            404
        );

        return Storage::disk('private')->download(
            $estimatorRequest->attachment_path,
            $estimatorRequest->attachment_name ?: basename($estimatorRequest->attachment_path)
        );
    }

    public function priceHistory(Request $request)
    {
        $user = $request->user();
        abort_unless(
            $user->can('manage-price-requests')
            || $user->can('manage-sales-leads')
            || $user->can('manage-admin')
            || $user->hasRole('ceo'),
            403
        );

        $validated = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $logs = ProductPriceLog::query()
            ->where('price_type', 'cost')
            ->whereNotNull('estimator_request_item_id')
            ->with([
                'product:id,name,sku',
                'supplier:id,name',
                'updater:id,name',
                'estimatorRequestItem.estimatorRequest.customer:id,name',
            ])
            ->when($validated['product_id'] ?? null, fn ($query, $id) => $query->where('product_id', $id))
            ->when($validated['supplier_id'] ?? null, fn ($query, $id) => $query->where('supplier_id', $id))
            ->when($validated['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('estimator.price-history.index', [
            'logs' => $logs,
            'products' => Product::saleable()->orderBy('name')->limit(500)->get(['id', 'name', 'sku']),
            'suppliers' => Supplier::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function exportExcel(Request $request)
    {
        $this->authorize('export', EstimatorRequest::class);

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'requested_by' => 'nullable|exists:users,id',
            'customer_id' => 'nullable|exists:customers,id',
            'status' => 'nullable|in:'.implode(',', EstimatorRequest::STATUSES),
        ]);

        $from = $validated['date_from'] ?? now()->startOfWeek()->toDateString();
        $to = $validated['date_to'] ?? now()->endOfWeek()->toDateString();

        $requests = EstimatorRequest::with([
            'customer', 'requester', 'handler',
            'items.supplier',
        ])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->when($validated['requested_by'] ?? null, fn ($query, $id) => $query->where('requested_by', $id))
            ->when($validated['customer_id'] ?? null, fn ($query, $id) => $query->where('customer_id', $id))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('created_at')
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Estimator Report'));

        $headers = [
            __('No'), __('Request Date'), __('Requested by'), __('Customer'),
            __('Project / Requirement'), __('Item & Estimated Price'), __('Estimator'),
            __('Completed Date'), __('Status'),
        ];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        $row = 2;
        foreach ($requests as $index => $estimatorRequest) {
            $items = $estimatorRequest->items->map(function (EstimatorRequestItem $item) {
                if ($item->price === null) {
                    return $item->description.' - '.__('Waiting for price');
                }

                $details = $item->description.': Rp '.number_format((float) $item->price, 0, ',', '.');
                if ($item->supplier) {
                    $details .= ' ('.$item->supplier->name.')';
                }
                if ($item->stock_status) {
                    $details .= ' ['.($item->stock_status === 'indent' ? __('Indent') : __('Ready Stock')).($item->stock_eta ? ' '.$item->stock_eta : '').']';
                }
                if ($item->warranty_info) {
                    $details .= ' ['.__('Warranty').': '.$item->warranty_info.']';
                }
                if ($item->valid_until) {
                    $details .= ' ['.__('Valid Until').': '.$item->valid_until->format('d/m/Y').']';
                }

                return $details;
            })->implode("\n");

            $sheet->fromArray([
                $index + 1,
                $estimatorRequest->created_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
                $estimatorRequest->requester?->name ?? '-',
                trim(($estimatorRequest->customer?->name ?? '-').($estimatorRequest->customer?->pt_group ? ' / '.$estimatorRequest->customer->pt_group : '')),
                Str::limit((string) $estimatorRequest->notes, 180),
                $items !== '' ? $items : '-',
                $estimatorRequest->handler?->name ?? '-',
                $estimatorRequest->completed_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '-',
                EstimatorRequest::statusLabel($estimatorRequest->status),
            ], null, 'A'.$row);

            $sheet->getRowDimension($row)->setRowHeight($items !== '' ? max(15, min(120, 15 * (substr_count($items, "\n") + 1))) : 15);
            $row++;
        }

        $sheet->getStyle('A2:I'.max(2, $row - 1))->getAlignment()->setVertical('top');
        $sheet->getStyle('F2:F'.max(2, $row - 1))->getAlignment()->setWrapText(true);
        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'estimator-report-'.now()->format('Ymd-His').'.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'estimator-report-');
        (new Xlsx($spreadsheet))->save($temp);

        return response()->download($temp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function customerOptions(User $user): array
    {
        $pts = PtAccess::userPts($user);

        return Customer::query()
            ->when($pts !== [], fn ($query) => $query->where(
                fn ($query) => $query->whereIn('pt_group', $pts)->orWhereNull('pt_group')
            ))
            ->orderBy('name')
            ->get(['id', 'name', 'pt_group'])
            ->mapWithKeys(fn (Customer $customer) => [
                $customer->id => $customer->name.($customer->pt_group ? " ({$customer->pt_group})" : ''),
            ])
            ->all();
    }
}
