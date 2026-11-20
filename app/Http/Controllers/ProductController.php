<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Product;
use App\Models\ProductPriceLog;
use App\Models\Proposal;
use App\Models\Supplier;
use App\Services\BulkProductPriceUpdateService;
use App\Services\ProductPriceService;
use App\Support\Pricing;
use App\Support\Rupiah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $canManageCost = Gate::allows('manageCost', Product::class);

        // Satu pintu untuk update harga massal: tautan lama ?bulk=1
        // diarahkan ke halaman Bulk Price yang sudah memakai PPN + review.
        if ($canManageCost && $request->boolean('bulk')) {
            return redirect()->route('products.bulk-price', array_filter([
                'search' => $request->input('search'),
                'category' => $request->input('category'),
                'quick' => $request->input('quick'),
            ], fn ($value) => $value !== null && $value !== ''));
        }

        $quick = $request->input('quick');
        if ($quick === null && $request->boolean('price_review')) {
            $quick = 'needs_recheck';
        }
        $quick = in_array($quick, ['active', 'needs_recheck', 'negative_margin', 'expiring_soon'], true)
            ? $quick
            : 'all';

        $base = Product::query()
            ->when($request->filled('search'), fn ($q) => $q->where(
                fn ($w) => $w->whereLike('name', "%{$request->search}%")
                    ->orWhereLike('sku', "%{$request->search}%")
                    ->orWhereLike('part_number', "%{$request->search}%")
            ))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category));

        $counts = [
            'all' => (clone $base)->count(),
            'active' => (clone $base)->priceActive()->count(),
            'needs_recheck' => (clone $base)->needsRecheck()->count(),
            'negative_margin' => (clone $base)->negativeMargin()->count(),
            'expiring_soon' => (clone $base)->expiringSoon()->count(),
        ];

        $products = (clone $base)
            ->with(['latestCostLog.supplier', 'costLogs'])
            ->when($quick === 'active', fn ($q) => $q->priceActive())
            ->when($quick === 'needs_recheck', fn ($q) => $q->needsRecheck())
            ->when($quick === 'negative_margin', fn ($q) => $q->negativeMargin())
            ->when($quick === 'expiring_soon', fn ($q) => $q->expiringSoon())
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('sales.products.index', [
            'products' => $products,
            'counts' => $counts,
            'quick' => $quick,
            'canManageCost' => $canManageCost,
        ]);
    }

    /** JSON untuk picker BOQ di form proposal (hanya produk aktif). */
    public function picker(Request $request)
    {
        $canViewCost = Gate::allows('viewCost', Proposal::class);
        $items = Product::saleable()
            ->with(['costLogs' => fn ($q) => $q->with('supplier')->limit(6)])
            ->when($request->filled('q'), fn ($q) => $q->where(
                fn ($w) => $w->whereLike('name', "%{$request->q}%")
                    ->orWhereLike('sku', "%{$request->q}%")
            ))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'sku', 'name', 'category', 'unit', 'default_price']);

        return response()->json($items->map(fn (Product $product) => [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'category' => $product->category,
            'unit' => $product->unit,
            'default_price' => (float) $product->default_price,
            'price_history' => $canViewCost ? $product->costLogs->map(fn (ProductPriceLog $log) => [
                'id' => $log->id,
                'price' => (float) $log->price,
                'price_entered' => $log->price_entered !== null ? (float) $log->price_entered : null,
                'price_includes_tax' => (bool) $log->price_includes_tax,
                'tax_rate' => $log->tax_rate !== null ? (float) $log->tax_rate : null,
                'supplier' => $log->supplier?->name,
                'stock_status' => $log->stock_status,
                'stock_eta' => $log->stock_eta,
                'warranty_info' => $log->warranty_info,
                'date' => $log->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i'),
                'valid_until' => $log->valid_until?->format('Y-m-d'),
                'notes' => $log->notes,
            ])->values() : [],
        ])->values());
    }

    public function create()
    {
        return view('sales.products.create', $this->formData());
    }

    /** Riwayat harga modal untuk dropdown dependen di form item quotation. */
    public function costLogs(Product $product)
    {
        Gate::authorize('viewCost', Proposal::class);

        $logs = $product->costLogs()
            ->with('supplier')
            ->latest('created_at')
            ->latest('id')
            ->limit(50)
            ->get();

        return response()->json($logs->map(fn (ProductPriceLog $log) => [
            'id' => $log->id,
            'price' => (float) $log->price,
            'price_entered' => $log->price_entered !== null ? (float) $log->price_entered : null,
            'price_includes_tax' => (bool) $log->price_includes_tax,
            'tax_rate' => $log->tax_rate !== null ? (float) $log->tax_rate : null,
            'supplier' => $log->supplier?->name,
            'date' => $log->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i'),
            'valid_until' => $log->valid_until?->format('d M Y'),
            'stock_status' => $log->stock_status,
            'stock_eta' => $log->stock_eta,
            'warranty_info' => $log->warranty_info,
        ])->values());
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

    /**
     * Bersihkan pemisah ribuan dari input uang sebelum validasi.
     *
     * @param  array<int, string>  $fields
     */
    private function normalizeMoneyInput(Request $request, array $fields): void
    {
        $normalized = [];

        foreach ($fields as $field) {
            $value = $request->input($field);

            // Biarkan kosong tetap null (nullable): jangan diubah jadi string
            // kosong, karena kolom numeric PostgreSQL menolak nilai ''.
            if ($value === null || $value === '') {
                continue;
            }

            $normalized[$field] = Rupiah::normalize($value);
        }

        if ($normalized !== []) {
            $request->merge($normalized);
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

        if ($product) {
            $product->loadMissing(['latestCostLog.supplier', 'latestCostLog.updater']);
        }

        return [
            'suppliers' => $canManageSuppliers
                ? Supplier::active()->orderBy('name')->get()
                : collect(),
            'selectedSupplierIds' => $product
                ? $product->suppliers()->pluck('suppliers.id')->all()
                : [],
            'primarySupplierId' => $product?->primarySupplier()?->id,
            'latestPriceLog' => $product?->latestCostLog,
        ];
    }

    public function store(Request $request)
    {
        $canManageCost = Gate::allows('manageCost', Product::class);
        $canManageSellingPrice = Gate::allows('manageSellingPrice', Product::class);
        $this->assertCostTampering($request, $canManageCost);
        $this->assertSupplierTampering($request);
        $this->normalizeMoneyInput($request, ['default_price', 'cost_price']);

        abort_if(
            $request->has('default_price') && ! $canManageSellingPrice,
            403,
            __('Only Admin/Purchasing can change the selling price.')
        );

        $rules = [
            'sku' => 'nullable|string|max:255|unique:products,sku',
            'name' => 'required|string|max:255',
            'part_number' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:2000',
            'category' => 'required|in:'.implode(',', Product::SALE_CATEGORIES),
            'unit' => 'nullable|string|max:16',
            'has_serial_number' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ];
        if ($canManageSellingPrice) {
            $rules['default_price'] = 'nullable|numeric|min:0|max:999999999999';
        }
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
        $this->logPriceChange($product, (float) ($validated['default_price'] ?? 0), 'selling');
        if ($canManageCost && array_key_exists('cost_price', $validated)) {
            $this->logPriceChange($product, (float) ($validated['cost_price'] ?? 0), 'cost');
        }

        return redirect()->route('products.index')->with('success', __('Product added successfully.'));
    }

    public function edit(Product $product)
    {
        return view('sales.products.edit', array_merge(['product' => $product], $this->formData($product)));
    }

    public function update(Request $request, Product $product)
    {
        $canManageCost = Gate::allows('manageCost', $product);
        $canManageSellingPrice = Gate::allows('manageSellingPrice', $product);
        $this->assertCostTampering($request, $canManageCost);
        $this->assertSupplierTampering($request);
        $this->normalizeMoneyInput($request, ['default_price', 'cost_price']);

        abort_if(
            $request->has('default_price') && ! $canManageSellingPrice,
            403,
            __('Only Admin/Purchasing can change the selling price.')
        );

        $rules = [
            'sku' => 'nullable|string|max:255|unique:products,sku,'.$product->id,
            'name' => 'required|string|max:255',
            'part_number' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:2000',
            'category' => 'required|in:'.implode(',', Product::SALE_CATEGORIES),
            'unit' => 'nullable|string|max:16',
            'has_serial_number' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ];
        if ($canManageSellingPrice) {
            $rules['default_price'] = 'nullable|numeric|min:0|max:999999999999';
        }
        if ($canManageCost) {
            $rules['cost_price'] = 'nullable|numeric|min:0|max:999999999999';
        }
        $validated = $request->validate($rules);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['has_serial_number'] = (bool) ($validated['has_serial_number'] ?? false);

        $beforeSelling = (float) $product->default_price;
        $beforeCost = $product->cost_price === null ? null : (float) $product->cost_price;
        $product->update($validated);
        $this->syncSuppliers($request, $product);

        if ($canManageSellingPrice) {
            $newSelling = (float) ($validated['default_price'] ?? $product->default_price);
            if (abs($newSelling - $beforeSelling) > 0.0001) {
                $this->logPriceChange($product, $newSelling, 'selling');
            }
        }

        $newCost = $product->cost_price === null ? null : (float) $product->cost_price;
        if ($canManageCost && $newCost !== null && ($beforeCost === null || abs($newCost - $beforeCost) > 0.0001)) {
            $this->logPriceChange($product, $newCost, 'cost');
        }

        return redirect()->route('products.index')->with('success', __('Product updated successfully.'));
    }

    public function destroy(Product $product)
    {
        // Nonaktifkan, jangan hapus: riwayat BOQ proposal lama tetap terbaca.
        $product->update(['is_active' => false]);

        return redirect()->route('products.index')->with('success', __('Product deactivated.'));
    }

    /**
     * Form update harga modal dari Pricelist. Estimator mencatat supplier,
     * stok, garansi, dan masa berlaku tanpa menyentuh harga jual default.
     */
    public function editPrice(Product $product)
    {
        Gate::authorize('manageCost', $product);

        return view('sales.products.price', [
            'product' => $product->loadMissing('latestCostLog.supplier', 'latestCostLog.updater'),
            'suppliers' => Supplier::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updatePrice(Request $request, Product $product)
    {
        Gate::authorize('manageCost', $product);

        $request->merge(['price' => Rupiah::normalize($request->input('price'))]);

        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'price' => 'required|numeric|min:0.01|max:999999999999',
            'price_includes_tax' => 'nullable|boolean',
            'stock_status' => 'required|in:ready,indent',
            'stock_eta' => 'required_if:stock_status,indent|nullable|string|max:100',
            'warranty_info' => 'required|string|max:255',
            'valid_until' => 'nullable|date',
            'notes' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request, $product, $validated): void {
            $vatRate = AppSetting::vatRate();
            $includesTax = (bool) ($validated['price_includes_tax'] ?? false);
            $netPrice = Pricing::netCost((float) $validated['price'], $includesTax, $vatRate);

            app(ProductPriceService::class)->recordCost($product, [
                'supplier_id' => (int) $validated['supplier_id'],
                'price' => $netPrice,
                'price_entered' => (float) $validated['price'],
                'price_includes_tax' => $includesTax,
                'tax_rate' => $vatRate,
                'stock_status' => $validated['stock_status'],
                'stock_eta' => $validated['stock_eta'] ?? null,
                'warranty_info' => $validated['warranty_info'],
                'valid_until' => $validated['valid_until'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ], $request->user()->id);
        });

        return redirect()->route('products.index')
            ->with('success', __('Cost price updated.'));
    }

    public function bulkPriceEdit()
    {
        Gate::authorize('manageCost', Product::class);

        $products = Product::query()
            ->whereHas('latestCostLog')
            ->with('latestCostLog.supplier')
            ->orderBy('name')
            ->paginate(100)
            ->withQueryString();

        return view('sales.products.bulk-price', [
            'products' => $products,
            'suppliers' => Supplier::active()->orderBy('name')->get(['id', 'name']),
            'vatRate' => Pricing::vatRate(),
        ]);
    }

    public function bulkPriceUpdate(Request $request, BulkProductPriceUpdateService $bulkProductPriceUpdateService)
    {
        Gate::authorize('manageCost', Product::class);

        $items = $request->input('items');
        if (is_array($items)) {
            foreach ($items as $key => $item) {
                if (! is_array($item) || ! array_key_exists('price', $item)) {
                    continue;
                }

                $items[$key]['price'] = Rupiah::normalize($item['price']);
            }
            $request->merge(['items' => $items]);
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => 'required|integer|distinct|exists:products,id',
            'items.*.supplier_id' => 'required|integer|exists:suppliers,id',
            'items.*.price' => 'required|numeric|min:0.01|max:999999999999',
            'items.*.price_includes_tax' => 'nullable|boolean',
            'items.*.valid_until' => 'nullable|date',
        ]);

        $products = Product::query()
            ->whereIn('id', collect($validated['items'])->pluck('product_id'))
            ->get()
            ->keyBy('id');

        foreach ($validated['items'] as $item) {
            $product = $products->get((int) $item['product_id']);
            if (! $product) {
                abort(404);
            }
            Gate::authorize('manageCost', $product);
        }

        $updatedCount = $bulkProductPriceUpdateService->update(
            array_values($validated['items']),
            $request->user(),
        );

        if ($updatedCount === 0) {
            return redirect()->route('products.index')
                ->with('warning', __('No price changes were saved.'));
        }

        return redirect()->route('products.index')->with(
            'success',
            __(':count product prices updated.', ['count' => $updatedCount]),
        );
    }

    /**
     * Catat harga baru ke riwayat. Harga 0 tidak dicatat supaya tidak
     * membanjiri log dengan baris kosong.
     */
    private function logPriceChange(Product $product, float $price, string $type = 'selling', ?int $priceRequestId = null, ?string $notes = null): void
    {
        $userId = auth()->id();

        if ($price <= 0 || ! $userId) {
            return;
        }

        ProductPriceLog::create([
            'product_id' => $product->id,
            'supplier_id' => $product->primarySupplier()?->id,
            'price' => $price,
            'price_type' => $type,
            'currency' => 'IDR',
            'price_request_id' => $priceRequestId,
            'updated_by' => $userId,
            'notes' => $notes,
        ]);
    }
}
