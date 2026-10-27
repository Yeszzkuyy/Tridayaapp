<?php

namespace App\Http\Controllers;

use App\Models\GoodsDelivery;
use App\Models\Project;
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

        return view('admin.goods-deliveries.create', [
            'projects' => $projects,
            'selectedProjectId' => $request->integer('project_id') ?: null,
            'selectedProject' => $selectedProject,
            'products' => \App\Models\Product::orderBy('name')->get(['id', 'name', 'part_number', 'unit']),
            'poItems' => SupplierPurchaseOrderItem::with('purchaseOrder')
                ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', ['ordered', 'partially_received', 'received']))
                ->where('quantity_received', '>', 0)
                ->orderBy('item_name')
                ->get(),
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
            ->filter(fn ($item) => trim((string) ($item['item_name'] ?? '')) !== '' || (float) ($item['quantity'] ?? 0) > 0)
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => __('Add at least one delivery item.'),
            ]);
        }

        foreach ($rows as $index => $row) {
            if (trim((string) ($row['item_name'] ?? '')) === '' || (float) ($row['quantity'] ?? 0) <= 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => __('Each delivery item needs a name and a quantity greater than zero.'),
                ]);
            }
        }

        $project = Project::findOrFail($validated['project_id']);

        $delivery = DB::transaction(function () use ($validated, $rows, $project) {
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

            foreach ($rows as $item) {
                $delivery->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'supplier_purchase_order_item_id' => $item['supplier_purchase_order_item_id'] ?? null,
                    'item_name' => $item['item_name'],
                    'part_number' => $item['part_number'] ?? null,
                    'unit' => $item['unit'] ?? 'pcs',
                    'quantity' => round((float) $item['quantity'], 2),
                    'serial_numbers' => $this->parseSerials($item['serial_numbers_text'] ?? null) ?: null,
                ]);
            }

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

    private function parseSerials(?string $text): array
    {
        if (! $text) {
            return [];
        }

        $parts = preg_split('/[\r\n,]+/', $text) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $parts), fn ($value) => $value !== '')));
    }
}
