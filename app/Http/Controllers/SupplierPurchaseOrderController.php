<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Proposal;
use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Supplier Purchase Order — pesanan 3DY kepada PEMASOK.
 *
 * BUKAN Customer PO (`purchase_orders`). Tidak ada penulisan ke tabel itu.
 * Item quotation hanya DIBACA sebagai sumber; quotation tidak diubah.
 */
class SupplierPurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = SupplierPurchaseOrder::query()
            ->with(['supplier', 'project', 'proposal'])
            ->withCount('items')
            ->when($request->filled('q'), fn ($q) => $q->where(function ($w) use ($request) {
                $w->whereLike('po_number', "%{$request->q}%")
                    ->orWhereHas('supplier', fn ($s) => $s->whereLike('name', "%{$request->q}%"));
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('issue_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.supplier-pos.index', [
            'orders' => $orders,
            'statuses' => SupplierPurchaseOrder::STATUSES,
        ]);
    }

    public function create(Request $request)
    {
        $suppliers = Supplier::active()->orderBy('name')->get();
        $proposals = $this->eligibleProposals();

        return view('admin.supplier-pos.create', [
            'suppliers' => $suppliers,
            'proposals' => $proposals,
            'quotationData' => $proposals->mapWithKeys(fn ($proposal) => [
                $proposal->id => [
                    'number' => $proposal->proposal_number,
                    'customer' => $proposal->customer,
                    'project_id' => $proposal->project?->id,
                    'items' => $proposal->items->map(fn ($item) => [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'name' => $item->description,
                        'part_number' => $item->product?->part_number,
                        'unit' => $item->unit ?? 'pcs',
                        'quantity' => (float) $item->quantity,
                        'unit_price' => (float) ($item->product?->cost_price ?? 0),
                    ])->values(),
                ],
            ]),
            'selectedProposalId' => $request->integer('proposal_id') ?: null,
            'selectedSupplierId' => $request->integer('supplier_id') ?: null,
            // Kunci idempotensi per render form: submit berulang tidak
            // membuat Supplier PO kedua.
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'idempotency_key' => 'nullable|string|max:64',
            'supplier_id' => 'required|exists:suppliers,id',
            'proposal_id' => 'required|exists:proposals,id',
            'issue_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:issue_date',
            'discount' => 'nullable|numeric|min:0|max:999999999999',
            'tax' => 'nullable|numeric|min:0|max:999999999999',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.proposal_item_id' => 'nullable|integer',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.item_name' => 'nullable|string|max:255',
            'items.*.part_number' => 'nullable|string|max:120',
            'items.*.description' => 'nullable|string|max:2000',
            'items.*.unit' => 'nullable|string|max:16',
            'items.*.quantity_ordered' => 'required|numeric|min:0.01|max:9999999',
            'items.*.unit_price' => 'required|numeric|min:0|max:999999999999',
        ]);

        $proposal = Proposal::with(['items.product', 'project'])->find($validated['proposal_id']);

        // Hanya quotation yang sudah disetujui DAN lolos syarat handoff
        // (customer PO terunggah) yang boleh diproses Purchasing.
        abort_unless($proposal && $proposal->isReadyForPurchasing(), 422, __('This quotation is not ready for Purchasing yet.'));

        foreach ($validated['items'] as $index => $item) {
            $proposalItemId = $item['proposal_item_id'] ?? null;

            if ($proposalItemId !== null) {
                $belongs = $proposal->items->contains(fn ($row) => (int) $row->id === (int) $proposalItemId);
                abort_unless($belongs, 422, __('The selected quotation item does not belong to this quotation.'));
            }
        }

        // Idempotensi: request ulang dengan key sama tidak membuat PO kedua.
        $key = $validated['idempotency_key'] ?? null;
        if ($key && ($existing = SupplierPurchaseOrder::where('idempotency_key', $key)->first())) {
            return redirect()->route('admin.supplier-pos.show', $existing)
                ->with('success', __('This purchase order has already been created.'));
        }

        try {
            $order = DB::transaction(function () use ($validated, $proposal, $key) {
                $order = SupplierPurchaseOrder::create([
                    'po_number' => $this->generateNumber(),
                    'supplier_id' => $validated['supplier_id'],
                    'proposal_id' => $proposal->id,
                    'project_id' => $proposal->project?->id,
                    'issue_date' => $validated['issue_date'],
                    'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                    'status' => 'draft',
                    'notes' => $validated['notes'] ?? null,
                    'discount' => $validated['discount'] ?? 0,
                    'tax' => $validated['tax'] ?? 0,
                    'idempotency_key' => $key,
                    'created_by' => auth()->id(),
                ]);

                $proposalItems = $proposal->items->keyBy('id');

                foreach ($validated['items'] as $item) {
                    $proposalItem = ! empty($item['proposal_item_id'])
                        ? $proposalItems->get((int) $item['proposal_item_id'])
                        : null;
                    $product = ! empty($item['product_id'])
                        ? ($proposalItem?->product ?: Product::find($item['product_id']))
                        : $proposalItem?->product;

                    $quantity = round((float) $item['quantity_ordered'], 2);
                    $price = round((float) $item['unit_price'], 2);

                    // Semua nilai bisnis disimpan sebagai snapshot.
                    $order->items()->create([
                        'proposal_item_id' => $proposalItem?->id,
                        'product_id' => $product?->id ?? ($item['product_id'] ?? null),
                        'item_name' => $this->firstFilled(
                            $item['item_name'] ?? null,
                            $product?->name,
                            $proposalItem?->description,
                            __('Procurement item')
                        ),
                        'part_number' => $this->firstFilled($item['part_number'] ?? null, $product?->part_number),
                        'description' => $this->firstFilled($item['description'] ?? null, $proposalItem?->description),
                        'unit' => $this->firstFilled($item['unit'] ?? null, $product?->unit, $proposalItem?->unit, 'pcs'),
                        'quantity_ordered' => $quantity,
                        'quantity_received' => 0,
                        'unit_price' => $price,
                        'subtotal' => round($quantity * $price, 2),
                    ]);
                }

                $order->recalculate();

                return $order;
            });
        } catch (QueryException $e) {
            // Balapan request paralel dengan idempotency key yang sama.
            if ($key && ($existing = SupplierPurchaseOrder::where('idempotency_key', $key)->first())) {
                return redirect()->route('admin.supplier-pos.show', $existing)
                    ->with('success', __('This purchase order has already been created.'));
            }

            throw $e;
        }

        return redirect()->route('admin.supplier-pos.show', $order)
            ->with('success', __('Supplier purchase order created successfully.'));
    }

    public function show(SupplierPurchaseOrder $supplierPurchaseOrder)
    {
        $supplierPurchaseOrder->load(['supplier', 'proposal', 'project', 'items.proposalItem', 'items.product', 'creator']);

        return view('admin.supplier-pos.show', compact('supplierPurchaseOrder'));
    }

    /** Tandai PO sudah dikirim ke pemasok. */
    public function issue(SupplierPurchaseOrder $supplierPurchaseOrder)
    {
        abort_unless($supplierPurchaseOrder->status === 'draft', 422);

        $supplierPurchaseOrder->update(['status' => 'ordered']);

        return back()->with('success', __('Purchase order marked as ordered.'));
    }

    /** Batalkan PO tanpa menghapus riwayat. */
    public function cancel(SupplierPurchaseOrder $supplierPurchaseOrder)
    {
        abort_unless(in_array($supplierPurchaseOrder->status, ['draft', 'ordered']), 422);

        $supplierPurchaseOrder->update(['status' => 'cancelled']);

        return back()->with('success', __('Purchase order cancelled.'));
    }

    /** Quotation accepted + customer PO terunggah (syarat handoff). */
    private function eligibleProposals()
    {
        return Proposal::query()
            ->with(['items.product', 'project'])
            ->where('status', 'accepted')
            ->whereNotNull('po_document_path')
            ->latest('id')
            ->get();
    }

    private function firstFilled(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    private function generateNumber(): string
    {
        $prefix = 'SPO/' . now()->format('Y/m') . '/';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $seq = SupplierPurchaseOrder::withTrashed()
                ->where('po_number', 'like', $prefix . '%')
                ->count() + 1 + $attempt;
            $number = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

            if (! SupplierPurchaseOrder::withTrashed()->where('po_number', $number)->exists()) {
                return $number;
            }
        }

        return $prefix . str_pad((string) Str::random(6), 6, '0', STR_PAD_LEFT);
    }
}
