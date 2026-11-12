<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PriceRequest;
use App\Models\PriceRequestComment;
use App\Models\PriceRequestReply;
use App\Models\Product;
use App\Models\ProductPriceLog;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\PriceRequestCompletedNotification;
use App\Notifications\PriceRequestCreatedNotification;
use App\Notifications\PriceRequestReviewedNotification;
use App\Rules\SecureFile;
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

/**
 * Request Harga: sales/teknisi mengirim quotation, lalu Amir menangani
 * perhitungan, diskusi, dan revisi. Terpisah dari TechnicalRequest.
 */
class PriceRequestController extends Controller
{
    /** Jenis file yang boleh dilampirkan (quotation / balasan hitungan). */
    private const FILE_MIMES = 'pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png,zip';

    public function create(Request $request)
    {
        $user = $request->user();
        // Form sama persis untuk sales & teknisi — hanya tombol kembali yang
        // menyesuaikan asal (inbox teknisi vs hub sales) agar terhubung.
        $isTechOnly = $user->can('manage-technician')
            && ! $user->can('manage-sales')
            && ! $user->can('manage-inside-sales');
        $backUrl = $isTechOnly
            ? route('teknisi.requests.index', ['tab' => 'price'])
            : ($user->can('manage-price-requests') && ! $user->can('manage-sales')
                ? route('sales.price-requests.index')
                : route('sales.requests.index', ['tab' => 'price']));

        return view('sales.price-requests.create', [
            'customers' => $this->customerOptions($user),
            'projects' => Project::orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']),
            'presetProjectId' => $request->query('project_id'),
            'backUrl' => $backUrl,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'project_id' => 'nullable|exists:projects,id',
            'notes' => 'required_without:quotation|nullable|string|max:2000',
            'quotation' => 'nullable|file|max:10240|mimes:'.self::FILE_MIMES,
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);
        abort_unless(PtAccess::canWritePt($request->user(), $customer->pt_group), 403);

        $projectId = $validated['project_id'] ?? null;
        if ($projectId) {
            $project = Project::findOrFail($projectId);
            abort_unless((int) $project->customer_id === (int) $customer->id, 422, __('The selected project does not belong to this customer.'));
        }

        $pricingEngineer = $this->configuredPricingEngineer();
        $priceRequest = PriceRequest::create([
            'customer_id' => $customer->id,
            'project_id' => $projectId,
            'requested_by' => $request->user()->id,
            'notes' => $validated['notes'] ?? null,
            'assigned_to' => $pricingEngineer?->id,
            'status' => $pricingEngineer ? 'in_progress' : 'waiting_lead',
        ]);

        $priceRequest->update([
            'quotation_path' => $request->hasFile('quotation')
                ? $this->storeFile($request->file('quotation'), 'price-requests/'.$priceRequest->id)
                : null,
        ]);

        $this->addSystemComment(
            $priceRequest,
            $pricingEngineer
                ? __('Price request assigned to :name.', ['name' => $pricingEngineer->name])
                : __('Price request is waiting for a lead technician.'),
            ['event' => 'created', 'assigned_to' => $pricingEngineer?->id],
        );

        // Route directly to the configured pricing engineer. If that account
        // is unavailable, notify the lead technician group as a safe fallback.
        $recipients = $pricingEngineer
            ? collect([$pricingEngineer])
            : User::role('lead-technician')->get();
        $recipients = $recipients->reject(fn (User $recipient) => (int) $recipient->id === (int) $request->user()->id);
        Notification::send($recipients, new PriceRequestCreatedNotification($priceRequest));

        return redirect()->route('sales.price-requests.show', $priceRequest)
            ->with('success', __('Price request dikirim ke tim Engineer.'))
            ->with('success_card', true);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|in:open,'.implode(',', PriceRequest::STATUSES),
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $query = PriceRequest::query()
            ->with([
                'customer:id,name,pt_group',
                'requester:id,name',
                'assignee:id,name',
                'handledBy:id,name',
                'latestPriceLog',
            ])
            ->withCount('priceLogs')
            ->withMax('priceLogs', 'created_at')
            ->withMax('replies', 'created_at')
            ->when($validated['q'] ?? null, function ($query, string $term): void {
                $query->where(function ($search) use ($term): void {
                    $search->whereHas('customer', fn ($customer) => $customer->whereLike('name', "%{$term}%"));

                    if (ctype_digit($term)) {
                        $search->orWhere('id', (int) $term);
                    }
                });
            })
            ->when(($validated['status'] ?? null) === 'open', fn ($query) => $query
                ->whereNotIn('status', ['completed', 'rejected', 'cancelled']))
            ->when(($validated['status'] ?? null) && $validated['status'] !== 'open', fn ($query, string $status) => $query
                ->where('status', $status))
            ->when($validated['date_from'] ?? null, fn ($query, string $date) => $query
                ->whereDate('created_at', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($query, string $date) => $query
                ->whereDate('created_at', '<=', $date));

        if (! ($user->can('manage-price-requests')
            || $user->can('manage-sales-leads')
            || $user->hasRole('lead-technician'))) {
            $query->where('requested_by', $user->id);
        }

        $summary = [
            'waiting' => (clone $query)->whereIn('status', ['waiting_lead', 'accepted', 'assigned', 'in_progress'])->count(),
            'received' => (clone $query)->where('status', 'waiting_sales_review')->count(),
            'revision' => (clone $query)->where('status', 'revision')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
        ];

        $priceRequests = $query
            ->orderByRaw("CASE WHEN status IN ('completed', 'rejected', 'cancelled') THEN 1 ELSE 0 END")
            ->orderBy('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('sales.price-requests.index', [
            'priceRequests' => $priceRequests,
            'statuses' => PriceRequest::STATUSES,
            'summary' => $summary,
        ]);
    }

    public function show(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('view', $priceRequest);
        $priceRequest->load([
            'customer', 'proposal', 'requester.roles', 'assignee', 'handledBy',
            'replies.uploader', 'replies.acceptedBy',
            'priceLogs.product', 'priceLogs.supplier',
        ]);

        $technicians = ($request->user()->hasRole('lead-technician') || $request->user()->hasRole('super-admin'))
            ? User::role('technician')->orderBy('name')->get(['id', 'name'])
            : collect();

        // Katalog + supplier untuk mencatat harga saat Estimator membalas.
        $products = $request->user()->can('reply', $priceRequest)
            ? Product::saleable()->orderBy('name')->get(['id', 'sku', 'name'])
            : collect();
        $suppliers = $request->user()->can('reply', $priceRequest)
            ? Supplier::orderBy('name')->get(['id', 'name'])
            : collect();

        return view('sales.price-requests.show', compact('priceRequest', 'technicians', 'products', 'suppliers'));
    }

    /** Sales pemilik (atau management) membatalkan request yang belum selesai. */
    public function cancel(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('cancel', $priceRequest);
        abort_if($priceRequest->isClosed(), 422);

        $priceRequest->update(['status' => 'cancelled']);
        $this->addSystemComment($priceRequest, __('Price request cancelled.'), ['event' => 'cancelled']);
        $priceRequest->loadMissing(['customer', 'assignee']);
        $this->notifyAssignedEngineer($priceRequest, new PriceRequestReviewedNotification($priceRequest, 'cancelled'));

        return back()->with('success', __('Price request canceled.'))->with('success_card', true);
    }

    /** Fallback only: a lead technician may claim a request if Amir is unavailable. */
    public function take(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('take', $priceRequest);

        $priceRequest->update([
            'status' => 'in_progress',
            'assigned_to' => $request->user()->id,
            'handled_by' => $request->user()->id,
        ]);
        $this->addSystemComment(
            $priceRequest,
            __(':name took responsibility for this price request.', ['name' => $request->user()->name]),
            ['event' => 'assigned', 'assigned_to' => $request->user()->id],
        );

        $this->notifyRequester($priceRequest, new PriceRequestReviewedNotification($priceRequest, 'in_progress'));

        return back()->with('success', __('Price request diambil untuk dikerjakan.'))->with('success_card', true);
    }

    /** Fallback only: a lead technician may reject an unassigned request. */
    public function reject(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('reject', $priceRequest);

        $priceRequest->update(['status' => 'rejected']);
        $this->addSystemComment($priceRequest, __('The price request was rejected.'), ['event' => 'rejected']);

        $this->notifyRequester($priceRequest, new PriceRequestReviewedNotification($priceRequest, 'rejected'));

        return back()->with('success', __('Price request ditolak.'))->with('success_card', true);
    }

    /** Fallback only: review requests when the configured pricing engineer is unavailable. */
    public function review(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('view', $priceRequest);
        abort_unless($priceRequest->status === 'waiting_lead', 422);
        $this->authorize('review', $priceRequest);

        $validated = $request->validate(['decision' => 'required|in:accepted,rejected']);

        $priceRequest->update(['status' => $validated['decision']]);
        $this->addSystemComment(
            $priceRequest,
            __('Lead technician :decision the price request.', ['decision' => $validated['decision']]),
            ['event' => $validated['decision']],
        );
        $this->notifyRequester($priceRequest, new PriceRequestReviewedNotification($priceRequest, $validated['decision']));

        return back()->with('success', __('Price request telah di-'.$validated['decision'].'.'))->with('success_card', true);
    }

    /** Fallback only: assign a technician when the configured pricing engineer is unavailable. */
    public function assign(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('assign', $priceRequest);
        abort_unless(in_array($priceRequest->status, ['accepted', 'assigned'], true), 422);

        $validated = $request->validate(['technician_id' => 'required|exists:users,id']);

        $technician = User::findOrFail($validated['technician_id']);
        abort_unless($technician->hasRole('technician'), 422, __('Only users with the Technician role can be assigned.'));

        $priceRequest->update([
            'assigned_to' => $technician->id,
            'handled_by' => $technician->id,
            'status' => 'assigned',
        ]);
        $this->addSystemComment(
            $priceRequest,
            __('Price request assigned to :name.', ['name' => $technician->name]),
            ['event' => 'assigned', 'assigned_to' => $technician->id],
        );

        $this->notifyRequester($priceRequest, new PriceRequestReviewedNotification($priceRequest, 'assigned'));

        return back()->with('success', __('Technician assigned successfully.'))->with('success_card', true);
    }

    /** Upload a new immutable calculation version and return the request to Sales review. */
    public function reply(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('reply', $priceRequest);

        // Baris harga kosong dari form dinamis tidak perlu divalidasi.
        $priceItems = collect($request->input('price_items', []))
            ->filter(fn ($row) => is_array($row) && (
                filled($row['product_id'] ?? null)
                || filled($row['price'] ?? null)
                || filled($row['stock_status'] ?? null)
                || filled($row['warranty_info'] ?? null)
            ))
            ->values()
            ->all();
        $request->merge(['price_items' => $priceItems]);

        $validated = $request->validate([
            'reply' => 'nullable|file|max:10240|mimes:'.self::FILE_MIMES,
            'reply_notes' => 'nullable|string|max:2000',
            'price_items' => 'required_without:reply|array|max:50',
            'price_items.*.product_id' => 'required|exists:products,id',
            'price_items.*.supplier_id' => 'required|exists:suppliers,id',
            'price_items.*.price' => 'required|numeric|min:0.01|max:999999999999',
            'price_items.*.stock_status' => 'required|in:ready,indent',
            'price_items.*.stock_eta' => 'nullable|string|max:100',
            'price_items.*.warranty_info' => 'required|string|max:255',
            'price_items.*.notes' => 'nullable|string|max:255',
        ]);

        foreach ($validated['price_items'] ?? [] as $index => $item) {
            if ($item['stock_status'] === 'indent' && blank($item['stock_eta'] ?? null)) {
                throw ValidationException::withMessages([
                    "price_items.{$index}.stock_eta" => __('Stock estimate is required for indent items.'),
                ]);
            }
        }

        $storedPath = null;

        try {
            DB::transaction(function () use ($request, $priceRequest, $validated, &$storedPath): void {
                $lockedRequest = PriceRequest::query()->lockForUpdate()->findOrFail($priceRequest->id);
                $this->authorize('reply', $lockedRequest);
                abort_unless(in_array($lockedRequest->status, ['in_progress', 'revision', 'accepted', 'assigned'], true), 422);

                $version = ((int) $lockedRequest->replies()->max('version')) + 1;
                $storedPath = $request->hasFile('reply')
                    ? $this->storeFile(
                        $request->file('reply'),
                        'price-requests/'.$lockedRequest->id.'/replies',
                        $version,
                    )
                    : null;

                $reply = $lockedRequest->replies()->create([
                    'uploaded_by' => $request->user()->id,
                    'version' => $version,
                    'file_path' => $storedPath,
                    'notes' => $validated['reply_notes'] ?? null,
                ]);

                $lockedRequest->update([
                    'reply_path' => $reply->file_path,
                    'reply_notes' => $reply->notes,
                    'status' => 'waiting_sales_review',
                    'completed_at' => null,
                    'handled_by' => $request->user()->id,
                ]);

                // Harga terstruktur masuk histori harga modal. Harga jual
                // default produk tidak disentuh.
                foreach ($validated['price_items'] ?? [] as $item) {
                    ProductPriceLog::create([
                        'product_id' => $item['product_id'],
                        'supplier_id' => $item['supplier_id'],
                        'price' => $item['price'],
                        'price_type' => 'cost',
                        'currency' => 'IDR',
                        'stock_status' => $item['stock_status'],
                        'stock_eta' => $item['stock_eta'] ?? null,
                        'warranty_info' => $item['warranty_info'],
                        'price_request_id' => $lockedRequest->id,
                        'updated_by' => $request->user()->id,
                        'notes' => $item['notes'] ?? null,
                    ]);

                    Product::whereKey($item['product_id'])->update([
                        'cost_price' => $item['price'],
                    ]);
                }

                $this->addSystemComment(
                    $lockedRequest,
                    __('Price calculation version :version uploaded.', ['version' => $version]),
                    ['event' => 'reply_uploaded', 'reply_id' => $reply->id, 'version' => $version],
                );
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('private')->delete($storedPath);
            }

            throw $exception;
        }

        $priceRequest->refresh()->load(['customer', 'requester']);

        $this->notifyRequester($priceRequest, new PriceRequestCompletedNotification($priceRequest));

        return back()->with('success', __('Price calculation sent to Sales for review.'))->with('success_card', true);
    }

    /** Sales accepts the latest calculation as the final price. */
    public function acceptReply(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('decide', $priceRequest);

        DB::transaction(function () use ($request, $priceRequest): void {
            $lockedRequest = PriceRequest::query()->lockForUpdate()->findOrFail($priceRequest->id);
            $this->authorize('decide', $lockedRequest);
            abort_unless($lockedRequest->status === 'waiting_sales_review', 422);

            $reply = $lockedRequest->replies()
                ->reorder()
                ->lockForUpdate()
                ->orderByDesc('version')
                ->firstOrFail();
            abort_if($reply->accepted_at !== null, 422);

            $acceptedAt = now();
            $reply->update([
                'accepted_by' => $request->user()->id,
                'accepted_at' => $acceptedAt,
            ]);
            $lockedRequest->update([
                'status' => 'completed',
                'completed_at' => $acceptedAt,
            ]);

            $this->addSystemComment(
                $lockedRequest,
                __('Sales accepted pricing version :version.', ['version' => $reply->version]),
                ['event' => 'accepted', 'reply_id' => $reply->id, 'version' => $reply->version],
            );
        });

        $priceRequest->refresh()->load(['customer', 'assignee']);
        $this->notifyAssignedEngineer($priceRequest, new PriceRequestReviewedNotification($priceRequest, 'accepted'));

        return back()->with('success', __('Price accepted as final.'))->with('success_card', true);
    }

    /** Sales asks the assigned engineer to revise/renegotiate the latest calculation. */
    public function requestRevision(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('decide', $priceRequest);

        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        DB::transaction(function () use ($request, $priceRequest, $validated): void {
            $lockedRequest = PriceRequest::query()->lockForUpdate()->findOrFail($priceRequest->id);
            $this->authorize('decide', $lockedRequest);
            abort_unless($lockedRequest->status === 'waiting_sales_review', 422);

            $reply = $lockedRequest->replies()
                ->reorder()
                ->lockForUpdate()
                ->orderByDesc('version')
                ->firstOrFail();
            abort_if($reply->accepted_at !== null, 422);

            $lockedRequest->update([
                'status' => 'revision',
                'completed_at' => null,
            ]);
            $lockedRequest->comments()->create([
                'user_id' => $request->user()->id,
                'body' => $validated['reason'],
                'type' => PriceRequestComment::TYPE_MESSAGE,
                'meta' => ['event' => 'revision_request', 'reply_id' => $reply->id, 'version' => $reply->version],
            ]);
            $this->addSystemComment(
                $lockedRequest,
                __('Revision requested for pricing version :version.', ['version' => $reply->version]),
                ['event' => 'revision_requested', 'reply_id' => $reply->id, 'version' => $reply->version],
            );

        });

        $priceRequest->refresh()->load(['customer', 'assignee']);
        $this->notifyAssignedEngineer($priceRequest, new PriceRequestReviewedNotification($priceRequest, 'revision'));

        return back()->with('success', __('Revision request sent to the Pricing Engineer.'))->with('success_card', true);
    }

    /**
     * Laporan mingguan Estimator (file .xlsx): siapa meminta harga, untuk
     * customer mana, item apa, siapa estimator-nya, dan status akhirnya.
     */
    public function exportExcel(Request $request)
    {
        $user = $request->user();
        abort_unless(
            $user->can('manage-price-requests')
                || $user->can('manage-sales-leads'),
            403
        );

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'requested_by' => 'nullable|exists:users,id',
            'customer_id' => 'nullable|exists:customers,id',
            'status' => 'nullable|in:'.implode(',', PriceRequest::STATUSES),
        ]);

        $from = $validated['date_from'] ?? now()->startOfWeek()->toDateString();
        $to = $validated['date_to'] ?? now()->endOfWeek()->toDateString();

        $requests = PriceRequest::with([
            'customer', 'requester', 'assignee', 'handledBy',
            'priceLogs.product', 'priceLogs.supplier',
        ])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->when($validated['requested_by'] ?? null, fn ($q, $id) => $q->where('requested_by', $id))
            ->when($validated['customer_id'] ?? null, fn ($q, $id) => $q->where('customer_id', $id))
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderBy('created_at')
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Estimator Report'));

        $headers = [
            __('No'),
            __('Request Date'),
            __('Requested by'),
            __('Customer'),
            __('Project / Requirement'),
            __('Item & Estimated Price'),
            __('Estimator'),
            __('Completed Date'),
            __('Status'),
        ];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        $row = 2;

        foreach ($requests as $index => $priceRequest) {
            $items = $priceRequest->priceLogs->map(function (ProductPriceLog $log) {
                $name = $log->product?->name ?? __('Product');
                $supplier = $log->supplier?->name ? ' ('.$log->supplier->name.')' : '';
                $stock = $log->stock_status
                    ? ' ['.($log->stock_status === 'indent' ? __('Indent') : __('Ready Stock')).($log->stock_status === 'indent' && $log->stock_eta ? ' '.$log->stock_eta : '').']'
                    : '';
                $warranty = $log->warranty_info ? ' ['.__('Warranty').': '.$log->warranty_info.']' : '';

                return $name.': Rp '.number_format((float) $log->price, 0, ',', '.').$supplier.$stock.$warranty;
            })->implode("\n");

            $sheet->fromArray([
                $index + 1,
                $priceRequest->created_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
                $priceRequest->requester?->name ?? '-',
                trim(($priceRequest->customer?->name ?? '-').($priceRequest->customer?->pt_group ? ' / '.$priceRequest->customer->pt_group : '')),
                Str::limit((string) $priceRequest->notes, 180),
                $items !== '' ? $items : ($priceRequest->reply_notes ?? '-'),
                $priceRequest->handledBy?->name ?? $priceRequest->assignee?->name ?? '-',
                $priceRequest->completed_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '-',
                PriceRequest::statusLabel($priceRequest->status),
            ], null, 'A'.$row);

            $sheet->getRowDimension($row)->setRowHeight($items !== '' ? max(15, min(90, 15 * (substr_count($items, "\n") + 1))) : 15);
            $row++;
        }

        $sheet->getStyle('A2:I'.max(2, $row - 1))->getAlignment()->setVertical('top');
        $sheet->getStyle('F2:F'.max(2, $row - 1))->getAlignment()->setWrapText(true);

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'estimator-report-'.$from.'-'.$to.'.xlsx';

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function downloadQuotation(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('view', $priceRequest);
        abort_unless($priceRequest->quotation_path && Storage::disk('private')->exists($priceRequest->quotation_path), 404);

        return Storage::disk('private')->download($priceRequest->quotation_path);
    }

    public function downloadReply(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('view', $priceRequest);
        abort_unless($priceRequest->reply_path && Storage::disk('private')->exists($priceRequest->reply_path), 404);

        return Storage::disk('private')->download($priceRequest->reply_path);
    }

    public function previewQuotation(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('view', $priceRequest);
        abort_unless($priceRequest->quotation_path && Storage::disk('private')->exists($priceRequest->quotation_path), 404);

        return SecureFile::fileResponse(
            'private',
            $priceRequest->quotation_path,
            basename($priceRequest->quotation_path)
        );
    }

    public function previewReply(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('view', $priceRequest);
        abort_unless($priceRequest->reply_path && Storage::disk('private')->exists($priceRequest->reply_path), 404);

        return SecureFile::fileResponse(
            'private',
            $priceRequest->reply_path,
            basename($priceRequest->reply_path)
        );
    }

    public function downloadReplyVersion(Request $request, PriceRequest $priceRequest, PriceRequestReply $reply)
    {
        $this->authorize('view', $priceRequest);
        abort_unless((int) $reply->price_request_id === (int) $priceRequest->id, 404);
        abort_unless($reply->file_path && Storage::disk('private')->exists($reply->file_path), 404);

        return Storage::disk('private')->download($reply->file_path);
    }

    public function previewReplyVersion(Request $request, PriceRequest $priceRequest, PriceRequestReply $reply)
    {
        $this->authorize('view', $priceRequest);
        abort_unless((int) $reply->price_request_id === (int) $priceRequest->id, 404);
        abort_unless($reply->file_path && Storage::disk('private')->exists($reply->file_path), 404);

        return SecureFile::fileResponse(
            'private',
            $reply->file_path,
            basename($reply->file_path)
        );
    }

    private function configuredPricingEngineer(): ?User
    {
        $email = strtolower(trim((string) config('services.price_request.pricing_engineer_email')));
        if ($email === '') {
            return null;
        }

        return User::role('lead-technician')->whereRaw('LOWER(email) = ?', [$email])->first();
    }

    private function addSystemComment(PriceRequest $priceRequest, string $body, array $meta = []): PriceRequestComment
    {
        return $priceRequest->comments()->create([
            'user_id' => null,
            'body' => $body,
            'type' => PriceRequestComment::TYPE_SYSTEM,
            'meta' => $meta,
        ]);
    }

    private function notifyAssignedEngineer(PriceRequest $priceRequest, \Illuminate\Notifications\Notification $notification): void
    {
        $engineer = $priceRequest->assignee ?? $this->configuredPricingEngineer();
        if ($engineer && (int) $engineer->id !== (int) auth()->id()) {
            $engineer->notify($notification);
        }
    }

    /** Kirim notifikasi ke sales peminta — lewati bila dia sendiri yang beraksi. */
    private function notifyRequester(PriceRequest $priceRequest, \Illuminate\Notifications\Notification $notification): void
    {
        $requester = $priceRequest->requester;
        if ($requester && (int) $requester->id !== (int) auth()->id()) {
            $requester->notify($notification);
        }
    }

    private function storeFile($file, string $dir, ?int $version = null): string
    {
        $prefix = $version !== null ? 'v'.$version.'_' : '';

        return $file->storeAs(
            $dir,
            $prefix.time().'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName()),
            'private'
        );
    }

    /**
     * Customer yang boleh dipilih user ini: PT dari role sales-* plus
     * customer tanpa PT (general). Tanpa role PT = semua.
     */
    private function customerOptions(User $user): array
    {
        $pts = PtAccess::userPts($user);

        return Customer::query()
            ->when($pts !== [], fn ($q) => $q->where(
                fn ($w) => $w->whereIn('pt_group', $pts)->orWhereNull('pt_group')
            ))
            ->orderBy('name')
            ->get(['id', 'name', 'pt_group'])
            ->mapWithKeys(fn ($c) => [$c->id => $c->name.($c->pt_group ? " ({$c->pt_group})" : '')])
            ->all();
    }
}
