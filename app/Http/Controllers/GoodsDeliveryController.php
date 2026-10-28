<?php

namespace App\Http\Controllers;

use App\Models\GoodsDelivery;
use App\Models\Project;
use App\Models\SupplierPurchaseOrder;
use App\Models\SupplierPurchaseOrderItem;
use App\Services\ProcurementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tahap 3 — Pengiriman barang keluar ke pelanggan (Surat Jalan).
 * Status `delivered` memicu evaluasi closing komersial proyek (Tahap 5).
 */
class GoodsDeliveryController extends Controller
{
    public function __construct(private ProcurementService $procurement) {}

    public function index(Request $request)
    {
        $deliveries = GoodsDelivery::query()
            ->with(['project', 'customer', 'creator'])
            ->withCount('items')
            ->when($request->filled('q'), fn ($q) => $q->where(function ($w) use ($request) {
                $w->whereLike('delivery_number', "%{$request->q}%")
                    ->orWhereLike('recipient_name', "%{$request->q}%")
                    ->orWhereHas('customer', fn ($c) => $c->whereLike('name', "%{$request->q}%"))
                    ->orWhereHas('project', fn ($p) => $p->whereLike('project_name', "%{$request->q}%"));
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('delivery_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.goods-deliveries.index', [
            'deliveries' => $deliveries,
            'statuses' => GoodsDelivery::STATUSES,
        ]);
    }

    public function create(Request $request)
    {
        $projects = Project::with('customer')
            ->orderBy('project_name')
            ->get(['id', 'project_name', 'customer_id']);

        $selectedProject = $request->integer('project_id')
            ? $projects->firstWhere('id', $request->integer('project_id'))
            : null;

        $hasProcurementItems = $selectedProject
            ? SupplierPurchaseOrderItem::query()
                ->whereHas('purchaseOrder', fn ($q) => $q
                    ->where('project_id', $selectedProject->id)
                    ->where('status', '!=', 'cancelled'))
                ->exists()
            : false;

        $poItems = $selectedProject
            ? SupplierPurchaseOrderItem::with(['purchaseOrder', 'product'])
                ->whereHas('purchaseOrder', fn ($q) => $q
                    ->where('project_id', $selectedProject->id)
                    ->whereIn('status', ['ordered', 'partially_received', 'received']))
                ->whereColumn('quantity_received', '>', 'quantity_delivered')
                ->orderBy('item_name')
                ->get()
            : collect();

        return view('admin.goods-deliveries.create', [
            'projects' => $projects,
            'selectedProjectId' => $request->integer('project_id') ?: null,
            'selectedProject' => $selectedProject,
            'products' => \App\Models\Product::orderBy('name')->get(['id', 'name', 'part_number', 'unit']),
            'poItems' => $poItems,
            'hasProcurementItems' => $hasProcurementItems,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'delivery_date' => 'required|date',
            'recipient_name' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.supplier_purchase_order_item_id' => 'nullable|integer|exists:supplier_purchase_order_items,id',
            'items.*.item_name' => 'nullable|string|max:255',
            'items.*.part_number' => 'nullable|string|max:120',
            'items.*.unit' => 'nullable|string|max:16',
            'items.*.quantity' => 'nullable|numeric|min:0|max:9999999',
            'items.*.serial_numbers_text' => 'nullable|string|max:2000',
        ]);

        $rows = collect($validated['items'])
            ->filter(fn ($item) => trim((string) ($item['item_name'] ?? '')) !== ''
                || trim((string) ($item['supplier_purchase_order_item_id'] ?? '')) !== ''
                || (float) ($item['quantity'] ?? 0) > 0)
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => __('Add at least one delivery item.'),
            ]);
        }

        $project = Project::findOrFail($validated['project_id']);
        $projectHasProcurement = SupplierPurchaseOrderItem::query()
            ->whereHas('purchaseOrder', fn ($q) => $q
                ->where('project_id', $project->id)
                ->where('status', '!=', 'cancelled'))
            ->exists();

        $delivery = DB::transaction(function () use ($validated, $rows, $project, $projectHasProcurement) {
            $prepared = $this->prepareDeliveryRows($project, $rows, $projectHasProcurement);

            $delivery = GoodsDelivery::create([
                'delivery_number' => $this->procurement->generateDeliveryNumber(),
                'project_id' => $project->id,
                'customer_id' => $project->customer_id,
                'delivery_date' => $validated['delivery_date'],
                'recipient_name' => $validated['recipient_name'] ?? null,
                'status' => 'prepared',
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($prepared as $item) {
                $delivery->items()->create([
                    'product_id' => $item['product_id'],
                    'supplier_purchase_order_item_id' => $item['supplier_purchase_order_item_id'],
                    'item_name' => $item['item_name'],
                    'part_number' => $item['part_number'],
                    'unit' => $item['unit'],
                    'quantity' => $item['quantity'],
                    'serial_numbers' => $item['serials'] ?: null,
                ]);
            }

            $orderIds = collect($prepared)
                ->pluck('supplier_purchase_order_id')
                ->filter()
                ->unique()
                ->values();

            SupplierPurchaseOrder::whereIn('id', $orderIds)->get()
                ->each(fn (SupplierPurchaseOrder $order) => $this->procurement->recalculatePurchaseOrderDeliveries($order));

            return $delivery;
        });

        return redirect()->route('admin.goods-deliveries.show', $delivery)
            ->with('success', __('Delivery note created.'));
    }

    public function show(GoodsDelivery $goodsDelivery)
    {
        $goodsDelivery->load(['project.customer', 'customer', 'creator', 'items.product', 'items.purchaseOrderItem']);

        return view('admin.goods-deliveries.show', compact('goodsDelivery'));
    }

    public function updateStatus(Request $request, GoodsDelivery $goodsDelivery)
    {
        $validated = $request->validate([
            'status' => 'required|in:prepared,shipped,delivered',
        ]);

        if ($goodsDelivery->status === 'delivered' && $validated['status'] !== 'delivered') {
            throw ValidationException::withMessages([
                'status' => __('A delivered note cannot be moved back to an earlier status.'),
            ]);
        }

        $goodsDelivery->update(['status' => $validated['status']]);

        if ($validated['status'] === 'delivered') {
            $goodsDelivery->project?->evaluateCommercialClosing();
        }

        return back()->with('success', __('Delivery status updated.'));
    }

    /**
     * Siapkan baris delivery. Untuk project yang memiliki Supplier PO,
     * semua baris wajib terhubung ke item PO dan tidak boleh melebihi
     * kuantitas yang sudah diterima dan belum dikirim. Project legacy
     * tanpa Supplier PO tetap memakai input manual agar kompatibel.
     */
    private function prepareDeliveryRows(Project $project, $rows, bool $projectHasProcurement): array
    {
        $poItems = collect();

        if ($projectHasProcurement) {
            $ids = $rows->pluck('supplier_purchase_order_item_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $poItems = SupplierPurchaseOrderItem::with(['purchaseOrder', 'product'])
                ->whereIn('id', $ids)
                ->whereHas('purchaseOrder', fn ($q) => $q
                    ->where('project_id', $project->id)
                    ->where('status', '!=', 'cancelled'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
        }

        $requested = [];
        $seenSerials = [];
        $prepared = [];

        foreach ($rows as $index => $row) {
            $quantity = round((float) ($row['quantity'] ?? 0), 2);

            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => __('Each delivery item needs a quantity greater than zero.'),
                ]);
            }

            $poItemId = (int) ($row['supplier_purchase_order_item_id'] ?? 0);

            if ($projectHasProcurement) {
                $poItem = $poItems->get($poItemId);

                if (! $poItem) {
                    throw ValidationException::withMessages([
                        "items.{$index}.supplier_purchase_order_item_id" => __('Choose goods received from this project Supplier PO.'),
                    ]);
                }

                if (! in_array($poItem->purchaseOrder->status, ['ordered', 'partially_received', 'received'], true)) {
                    throw ValidationException::withMessages([
                        "items.{$index}.supplier_purchase_order_item_id" => __('The selected Supplier PO is not active.'),
                    ]);
                }

                $alreadyRequested = (float) ($requested[$poItem->id] ?? 0);
                $available = max(0, (float) $poItem->quantity_received - (float) $poItem->quantity_delivered);

                if ($alreadyRequested + $quantity > $available + 0.0001) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => __('Delivery quantity exceeds available stock (:available) for :item.', [
                            'available' => number_format(max(0, $available - $alreadyRequested), 2),
                            'item' => $poItem->item_name,
                        ]),
                    ]);
                }

                $serials = $this->parseSerials($row['serial_numbers_text'] ?? null);
                $this->assertDeliverySerials($poItem, $quantity, $serials, $index, $seenSerials);

                $requested[$poItem->id] = $alreadyRequested + $quantity;

                $prepared[] = [
                    'supplier_purchase_order_id' => $poItem->supplier_purchase_order_id,
                    'supplier_purchase_order_item_id' => $poItem->id,
                    'product_id' => $poItem->product_id,
                    'item_name' => $poItem->item_name,
                    'part_number' => $poItem->part_number,
                    'unit' => $poItem->unit ?: 'pcs',
                    'quantity' => $quantity,
                    'serials' => $serials,
                ];

                continue;
            }

            $itemName = trim((string) ($row['item_name'] ?? ''));

            if ($itemName === '') {
                throw ValidationException::withMessages([
                    "items.{$index}.item_name" => __('Each delivery item needs a name.'),
                ]);
            }

            $prepared[] = [
                'supplier_purchase_order_id' => null,
                'supplier_purchase_order_item_id' => null,
                'product_id' => $row['product_id'] ?? null,
                'item_name' => $itemName,
                'part_number' => $row['part_number'] ?? null,
                'unit' => $row['unit'] ?? 'pcs',
                'quantity' => $quantity,
                'serials' => $this->parseSerials($row['serial_numbers_text'] ?? null),
            ];
        }

        if ($prepared === []) {
            throw ValidationException::withMessages([
                'items' => __('Add at least one delivery item.'),
            ]);
        }

        return $prepared;
    }

    private function assertDeliverySerials(
        SupplierPurchaseOrderItem $poItem,
        float $quantity,
        array $serials,
        int $index,
        array &$seenSerials
    ): void {
        $product = $poItem->product;

        if (! $product?->has_serial_number) {
            return;
        }

        $key = "items.{$index}.serial_numbers_text";

        if (floor($quantity) !== $quantity) {
            throw ValidationException::withMessages([
                $key => __('Serialized items must be delivered in whole units.'),
            ]);
        }

        if (count($serials) !== (int) $quantity) {
            throw ValidationException::withMessages([
                $key => __('Enter exactly :count serial numbers for :item.', [
                    'count' => (int) $quantity,
                    'item' => $product->name,
                ]),
            ]);
        }

        $received = $this->procurement->receivedSerialNumbers($poItem);
        $delivered = $this->procurement->deliveredSerialNumbers($poItem);

        foreach ($serials as $serial) {
            $normalized = mb_strtoupper(trim((string) $serial));

            if (! in_array($normalized, $received, true)) {
                throw ValidationException::withMessages([
                    $key => __('Serial number has not been received for this item: :serial', ['serial' => $serial]),
                ]);
            }

            if (in_array($normalized, $delivered, true)) {
                throw ValidationException::withMessages([
                    $key => __('Serial number has already been delivered: :serial', ['serial' => $serial]),
                ]);
            }

            if (isset($seenSerials[$poItem->id][$normalized])) {
                throw ValidationException::withMessages([
                    $key => __('Duplicate serial number in this delivery: :serial', ['serial' => $serial]),
                ]);
            }

            $seenSerials[$poItem->id][$normalized] = true;
        }
    }

    private function parseSerials(?string $text): array
    {
        if (! $text) {
            return [];
        }

        $parts = preg_split('/[\r\n,]+/', $text) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $parts), fn ($value) => $value !== '')));
    }
}
