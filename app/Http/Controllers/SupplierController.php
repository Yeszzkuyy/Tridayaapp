<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

/**
 * Master pemasok pengadaan. Terpisah dari `partners` (relasi marketing).
 * Tulis: manage-admin (Admin/Purchasing). Baca: view-admin|manage-admin.
 */
class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $suppliers = Supplier::query()
            ->when($request->filled('q'), fn ($q) => $q->where(function ($w) use ($request) {
                $w->whereLike('name', "%{$request->q}%")
                    ->orWhereLike('code', "%{$request->q}%")
                    ->orWhereLike('contact_person', "%{$request->q}%");
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('admin.suppliers.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validateSupplier($request);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        Supplier::create($validated);

        return redirect()->route('admin.suppliers.index')->with('success', __('Supplier added successfully.'));
    }

    public function edit(Supplier $supplier)
    {
        return view('admin.suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $this->validateSupplier($request, $supplier);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        $supplier->update($validated);

        return redirect()->route('admin.suppliers.index')->with('success', __('Supplier updated successfully.'));
    }

    public function destroy(Supplier $supplier)
    {
        // Nonaktifkan, jangan hapus permanen: PO historis tetap terbaca.
        $supplier->update(['is_active' => false]);

        return redirect()->route('admin.suppliers.index')->with('success', __('Supplier deactivated.'));
    }

    private function validateSupplier(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'code' => 'nullable|string|max:50|unique:suppliers,code' . ($supplier ? ',' . $supplier->id : ''),
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);
    }
}
