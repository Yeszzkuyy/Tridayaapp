<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
            ->when($request->filled('search'), fn ($q) => $q->where(
                fn ($w) => $w->whereLike('name', "%{$request->search}%")
                    ->orWhereLike('sku', "%{$request->search}%")
                    ->orWhereLike('part_number', "%{$request->search}%")
            ))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('sales.products.index', compact('products'));
    }

    /** JSON untuk picker BOQ di form proposal (hanya produk aktif). */
    public function picker(Request $request)
    {
        $items = Product::saleable()
            ->when($request->filled('q'), fn ($q) => $q->where(
                fn ($w) => $w->whereLike('name', "%{$request->q}%")
                    ->orWhereLike('sku', "%{$request->q}%")
            ))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'sku', 'name', 'category', 'unit', 'default_price']);

        return response()->json($items);
    }

    public function create()
    {
        return view('sales.products.create', $this->formData());
    }

    /**
     * Anti-tamper harga modal: pengguna tanpa kewenangan tidak boleh
     * mengirim/mengubah cost_price (backend menolak, bukan sekadar menyembunyikan).
     */
    private function assertCostTampering(Request $request, bool $canManageCost): void
    {
        if (! $canManageCost && $request->filled('cost_price')) {
            abort(403, __('You are not allowed to modify cost prices.'));
        }
    }

    /**
     * Relasi pemasok = data pengadaan. Hanya Admin/Purchasing (manage-admin)
     * yang boleh mengubahnya; request dari role lain ditolak di backend.
     */
    private function assertSupplierTampering(Request $request): void
    {
        $touchesSuppliers = $request->has('supplier_ids') || $request->has('primary_supplier_id');

        if ($touchesSuppliers && ! $request->user()?->can('manage-admin')) {
            abort(403, __('You are not allowed to modify product suppliers.'));
        }
    }

    private function syncSuppliers(Request $request, Product $product): void
    {
        if (! $request->user()?->can('manage-admin')) {
            return;
        }

        // Jangan sentuh relasi bila form memang tidak mengirim data pemasok
        // (mis. update dari jalur lain) — supaya relasi lama tidak terhapus.
        if (! $request->has('supplier_ids') && ! $request->has('primary_supplier_id')) {
            return;
        }

        $validated = $request->validate([
            'supplier_ids' => 'nullable|array',
            'supplier_ids.*' => 'integer|exists:suppliers,id',
            'primary_supplier_id' => 'nullable|integer|exists:suppliers,id',
        ]);

        $ids = collect($validated['supplier_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $primaryId = ! empty($validated['primary_supplier_id'])
            ? (int) $validated['primary_supplier_id']
            : null;

        if ($primaryId && ! $ids->contains($primaryId)) {
            $ids->push($primaryId);
        }

        // Pertahankan data pivot yang sudah ada (mis. harga modal terakhir).
        $existing = $product->suppliers()->get()->keyBy('id');

        $sync = $ids->mapWithKeys(function ($id) use ($existing, $primaryId) {
            $pivot = $existing->get($id)?->pivot;

            return [$id => [
                'is_primary' => $id === $primaryId,
                'supplier_part_number' => $pivot?->supplier_part_number,
                'last_cost_price' => $pivot?->last_cost_price,
            ]];
        })->all();

        $product->suppliers()->sync($sync);
    }

    /** Data tambahan form produk (pemasok hanya untuk Admin/Purchasing). */
    private function formData(?Product $product = null): array
    {
        $canManageSuppliers = auth()->user()?->can('manage-admin') ?? false;

        return [
            'suppliers' => $canManageSuppliers
                ? Supplier::active()->orderBy('name')->get()
                : collect(),
            'selectedSupplierIds' => $product
                ? $product->suppliers()->pluck('suppliers.id')->all()
                : [],
            'primarySupplierId' => $product?->primarySupplier()?->id,
        ];
    }

    public function store(Request $request)
    {
        $canManageCost = Gate::allows('manageCost', Product::class);
        $this->assertCostTampering($request, $canManageCost);
        $this->assertSupplierTampering($request);

        $rules = [
            'sku' => 'nullable|string|max:255|unique:products,sku',
            'name' => 'required|string|max:255',
            'part_number' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:2000',
            'category' => 'required|in:' . implode(',', Product::SALE_CATEGORIES),
            'unit' => 'nullable|string|max:16',
            'default_price' => 'nullable|numeric|min:0|max:999999999999',
            'has_serial_number' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ];
        if ($canManageCost) {
            $rules['cost_price'] = 'nullable|numeric|min:0|max:999999999999';
        }
        $validated = $request->validate($rules);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        $validated['has_serial_number'] = (bool) ($validated['has_serial_number'] ?? false);
        // Produk katalog sales: langsung approved agar tidak masuk antrean review KB.
        $validated['status'] = Product::STATUS_APPROVED;

        $product = Product::create($validated);
        $this->syncSuppliers($request, $product);

        return redirect()->route('products.index')->with('success', __('Product added successfully.'));
    }

    public function edit(Product $product)
    {
        return view('sales.products.edit', array_merge(['product' => $product], $this->formData($product)));
    }

    public function update(Request $request, Product $product)
    {
        $canManageCost = Gate::allows('manageCost', $product);
        $this->assertCostTampering($request, $canManageCost);
        $this->assertSupplierTampering($request);

        $rules = [
            'sku' => 'nullable|string|max:255|unique:products,sku,' . $product->id,
            'name' => 'required|string|max:255',
            'part_number' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:2000',
            'category' => 'required|in:' . implode(',', Product::SALE_CATEGORIES),
            'unit' => 'nullable|string|max:16',
            'default_price' => 'nullable|numeric|min:0|max:999999999999',
            'has_serial_number' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ];
        if ($canManageCost) {
            $rules['cost_price'] = 'nullable|numeric|min:0|max:999999999999';
        }
        $validated = $request->validate($rules);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['has_serial_number'] = (bool) ($validated['has_serial_number'] ?? false);

        $product->update($validated);
        $this->syncSuppliers($request, $product);

        return redirect()->route('products.index')->with('success', __('Product updated successfully.'));
    }

    public function destroy(Product $product)
    {
        // Nonaktifkan, jangan hapus: riwayat BOQ proposal lama tetap terbaca.
        $product->update(['is_active' => false]);

        return redirect()->route('products.index')->with('success', __('Product deactivated.'));
    }
}
