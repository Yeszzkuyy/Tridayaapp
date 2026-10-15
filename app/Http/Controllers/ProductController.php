<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
            ->when($request->filled('search'), fn ($q) => $q->where(
                fn ($w) => $w->whereLike('name', "%{$request->search}%")
                    ->orWhereLike('sku', "%{$request->search}%")
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
        return view('sales.products.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'nullable|string|max:255|unique:products,sku',
            'name' => 'required|string|max:255',
            'category' => 'required|in:' . implode(',', Product::SALE_CATEGORIES),
            'unit' => 'nullable|string|max:16',
            'default_price' => 'nullable|numeric|min:0|max:999999999999',
            'is_active' => 'nullable|boolean',
        ]);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        // Produk katalog sales: langsung approved agar tidak masuk antrean review KB.
        $validated['status'] = Product::STATUS_APPROVED;

        Product::create($validated);

        return redirect()->route('products.index')->with('success', __('Produk berhasil ditambahkan.'));
    }

    public function edit(Product $product)
    {
        return view('sales.products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'sku' => 'nullable|string|max:255|unique:products,sku,' . $product->id,
            'name' => 'required|string|max:255',
            'category' => 'required|in:' . implode(',', Product::SALE_CATEGORIES),
            'unit' => 'nullable|string|max:16',
            'default_price' => 'nullable|numeric|min:0|max:999999999999',
            'is_active' => 'nullable|boolean',
        ]);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        $product->update($validated);

        return redirect()->route('products.index')->with('success', __('Produk berhasil diperbarui.'));
    }

    public function destroy(Product $product)
    {
        // Nonaktifkan, jangan hapus: riwayat BOQ proposal lama tetap terbaca.
        $product->update(['is_active' => false]);

        return redirect()->route('products.index')->with('success', __('Produk dinonaktifkan.'));
    }
}
