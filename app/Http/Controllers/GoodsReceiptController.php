<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\SupplierPurchaseOrder;
use App\Rules\SecureFile;
use App\Services\ProcurementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tahap 3 — Terima Barang dari Supplier.
 *
 * Draft receipt belum mengubah stok/kuantitas PO. Verifikasi menghitung
 * ulang quantity_received dari seluruh receipt verified (idempoten).
 */
class GoodsReceiptController extends Controller
{
    public function __construct(private ProcurementService $procurement) {}

    public function index(Request $request)
    {
        $receipts = GoodsReceipt::query()
            ->with(['purchaseOrder.supplier', 'project', 'receiver'])
            ->withCount('items')
            ->when($request->filled('q'), fn ($q) => $q->where(function ($w) use ($request) {
                $w->whereLike('receipt_number', "%{$request->q}%")
                    ->orWhereLike('supplier_delivery_note_no', "%{$request->q}%")
                    ->orWhereHas('purchaseOrder', fn ($po) => $po->whereLike('po_number', "%{$request->q}%"))
                    ->orWhereHas('purchaseOrder.supplier', fn ($s) => $s->whereLike('name', "%{$request->q}%"));
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('received_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.goods-receipts.index', [
            'receipts' => $receipts,
            'statuses' => GoodsReceipt::STATUSES,
        ]);
    }

    public function create(Request $request)
    {
        $orders = SupplierPurchaseOrder::query()
            ->with(['supplier', 'project', 'items.product'])
            ->whereIn('status', ['ordered', 'partially_received'])
            ->whereHas('items', fn ($q) => $q->whereColumn('quantity_received', '<', 'quantity_ordered'))
            ->latest('issue_date')
            ->get();

        $selectedId = $request->integer('supplier_purchase_order_id') ?: null;
        $selectedOrder = $selectedId ? $orders->firstWhere('id', $selectedId) : null;

        return view('admin.goods-receipts.create', compact('orders', 'selectedId', 'selectedOrder'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_purchase_order_id' => 'required|exists:supplier_purchase_orders,id',
            'received_date' => 'required|date',
            'supplier_delivery_note_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
            'attachment' => ['nullable', 'file', 'max:20480', SecureFile::documents()],
            'items' => 'required|array|min:1',
            'items.*.supplier_purchase_order_item_id' => 'required|integer|exists:supplier_purchase_order_items,id',
            'items.*.quantity_received' => 'nullable|numeric|min:0|max:9999999',
            'items.*.serial_numbers_text' => 'nullable|string|max:2000',
            'items.*.condition_notes' => 'nullable|string|max:255',
        ]);

        $order = SupplierPurchaseOrder::with(['items.product'])->findOrFail($validated['supplier_purchase_order_id']);
        abort_unless(in_array($order->status, ['ordered', 'partially_received', 'received'], true), 422,
            __('Goods can only be received against an issued purchase order.'));

        $items = $this->prepareItems($order, $validated['items']);

        $attachmentPath = $request->file('attachment')
            ? $request->file('attachment')->store('goods-receipts', 'private')
            : null;

        $receipt = DB::transaction(function () use ($validated, $order, $items, $attachmentPath) {
            $receipt = GoodsReceipt::create([
                'receipt_number' => $this->procurement->generateReceiptNumber(),
                'supplier_purchase_order_id' => $order->id,
                'supplier_id' => $order->supplier_id,
                'project_id' => $order->project_id,
                'received_date' => $validated['received_date'],
                'supplier_delivery_note_no' => $validated['supplier_delivery_note_no'] ?? null,
                'received_by' => auth()->id(),
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'attachment_path' => $attachmentPath,
            ]);

            foreach ($items as $item) {
                $receipt->items()->create([
                    'supplier_purchase_order_item_id' => $item['po_item']->id,
                    'product_id' => $item['po_item']->product_id,
                    'quantity_received' => $item['quantity'],
                    'serial_numbers' => $item['serials'] ?: null,
                    'condition_notes' => $item['condition_notes'],
                ]);
            }

            return $receipt;
        });

        return redirect()->route('admin.goods-receipts.show', $receipt)
            ->with('success', __('Goods receipt saved as draft. Verify it to update the purchase order.'));
    }

    public function show(GoodsReceipt $goodsReceipt)
    {
        $goodsReceipt->load([
            'purchaseOrder.supplier',
            'purchaseOrder.items.product',
            'project',
            'receiver',
            'items.purchaseOrderItem',
            'items.product',
            'supplierInvoices',
        ]);

        return view('admin.goods-receipts.show', compact('goodsReceipt'));
    }

    public function verify(GoodsReceipt $goodsReceipt)
    {
        abort_if($goodsReceipt->status !== 'draft', 422, __('This receipt has already been verified.'));

        DB::transaction(function () use ($goodsReceipt) {
            $goodsReceipt->load('items.purchaseOrderItem.product');

            foreach ($goodsReceipt->items as $item) {
                $remaining = $this->procurement->remainingQuantity($item->purchaseOrderItem);

                if ((float) $item->quantity_received > $remaining + 0.0001) {
                    throw ValidationException::withMessages([
                        'items' => __('Received quantity for :item exceeds the remaining quantity (:remaining).', [
                            'item' => $item->purchaseOrderItem->item_name,
                            'remaining' => number_format($remaining, 2),
                        ]),
                    ]);
                }

                $this->assertSerialsComplete($item->product, (float) $item->quantity_received, $item->serial_numbers ?? [], null, $goodsReceipt->id);
            }

            $goodsReceipt->update(['status' => 'verified']);
            $this->procurement->recalculatePurchaseOrderReceipts($goodsReceipt->purchaseOrder);
        });

        return redirect()->route('admin.goods-receipts.show', $goodsReceipt)
            ->with('success', __('Goods receipt verified. Purchase order quantities updated.'));
    }

    public function attachment(GoodsReceipt $goodsReceipt)
    {
        abort_unless($goodsReceipt->attachment_path, 404, __('Attachment not found.'));

        return SecureFile::fileResponse('private', $goodsReceipt->attachment_path, basename($goodsReceipt->attachment_path));
    }

    private function prepareItems(SupplierPurchaseOrder $order, array $rows): array
    {
        $orderItems = $order->items->keyBy('id');
        $prepared = [];
        $seenSerials = [];

        foreach ($rows as $index => $row) {
            $quantity = round((float) ($row['quantity_received'] ?? 0), 2);
            if ($quantity <= 0) {
                continue;
            }

            $poItem = $orderItems->get((int) $row['supplier_purchase_order_item_id']);
            abort_unless($poItem, 422, __('The selected item does not belong to this purchase order.'));

            $remaining = $this->procurement->remainingQuantity($poItem);
            if ($quantity > $remaining + 0.0001) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity_received" => __('Received quantity exceeds the remaining quantity (:remaining).', [
                        'remaining' => number_format($remaining, 2),
                    ]),
                ]);
            }

            $serials = $this->parseSerials($row['serial_numbers_text'] ?? null);

            if ($poItem->product?->has_serial_number) {
                $this->assertSerialsComplete($poItem->product, $quantity, $serials, $index);
            }

            foreach ($serials as $serial) {
                if (isset($seenSerials[$serial])) {
                    throw ValidationException::withMessages([
                        "items.{$index}.serial_numbers_text" => __('Duplicate serial number in this receipt: :serial', ['serial' => $serial]),
                    ]);
                }
                $seenSerials[$serial] = true;
            }

            $prepared[] = [
                'po_item' => $poItem,
                'quantity' => $quantity,
                'serials' => $serials,
                'condition_notes' => $row['condition_notes'] ?? null,
            ];
        }

        if ($prepared === []) {
            throw ValidationException::withMessages([
                'items' => __('Enter a received quantity for at least one item.'),
            ]);
        }

        return $prepared;
    }

    private function parseSerials(?string $text): array
    {
        if (! $text) {
            return [];
        }

        $parts = preg_split('/[\r\n,]+/', $text) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $parts), fn ($value) => $value !== '')));
    }

    private function assertSerialsComplete($product, float $quantity, array $serials, ?int $index = null, ?int $ignoreReceiptId = null): void
    {
        if (! $product?->has_serial_number) {
            return;
        }

        $key = $index === null ? 'items' : "items.{$index}.serial_numbers_text";

        if (floor($quantity) !== $quantity) {
            throw ValidationException::withMessages([
                $key => __('Serialized items must be received in whole units.'),
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

        // Cegah SN yang sama dipakai di receipt lain.
        $existing = GoodsReceiptItem::query()
            ->where('product_id', $product->id)
            ->whereNotNull('serial_numbers')
            ->when($ignoreReceiptId, fn ($q) => $q->where('goods_receipt_id', '!=', $ignoreReceiptId))
            ->get()
            ->flatMap(fn ($row) => $row->serial_numbers ?? [])
            ->map(fn ($serial) => mb_strtoupper(trim((string) $serial)))
            ->all();

        foreach ($serials as $serial) {
            if (in_array(mb_strtoupper($serial), $existing, true)) {
                throw ValidationException::withMessages([
                    $key => __('Serial number has already been received: :serial', ['serial' => $serial]),
                ]);
            }
        }
    }
}
