<div class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Product Name') }} <span class="text-red-500">*</span></label>
            <input type="text" name="name" required value="{{ old('name', $product->name ?? '') }}"
                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('SKU') }}</label>
            <input type="text" name="sku" value="{{ old('sku', $product->sku ?? '') }}" placeholder="{{ __('e.g. CCTV-8CH') }}"
                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
            @error('sku') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Part Number') }}</label>
            <input type="text" name="part_number" value="{{ old('part_number', $product->part_number ?? '') }}" placeholder="{{ __('Manufacturer / supplier part number') }}"
                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
            @error('part_number') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Category') }} <span class="text-red-500">*</span></label>
            <select name="category" required class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                @foreach(\App\Models\Product::SALE_CATEGORIES as $cat)
                    <option value="{{ $cat }}" @selected(old('category', $product->category ?? 'barang') === $cat)>{{ \App\Models\Product::saleCategoryLabel($cat) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Unit') }}</label>
            <input type="text" name="unit" value="{{ old('unit', $product->unit ?? 'pcs') }}"
                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Base Price (Rp)') }}</label>
            <input type="number" name="default_price" min="0" step="0.01" value="{{ old('default_price', $product->default_price ?? 0) }}"
                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
        </div>
        @can('manageCost', \App\Models\Product::class)
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Cost Price (Rp)') }}</label>
            <input type="number" name="cost_price" min="0" step="0.01" value="{{ old('cost_price', $product->cost_price ?? '') }}"
                   class="w-full rounded-xl border-amber-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-amber-700">
        </div>
        @endcan
        <div class="flex items-end">
            <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="has_serial_number" value="1" @checked(old('has_serial_number', $product->has_serial_number ?? false)) class="rounded border-slate-300">
                {{ __('Requires serial number') }}
            </label>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Description') }}</label>
        <textarea name="description" rows="3"
                  class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">{{ old('description', $product->description ?? '') }}</textarea>
        @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    @if(($suppliers ?? collect())->isNotEmpty())
        @can('manage-admin')
            <div class="rounded-xl border border-slate-200 dark:border-slate-600 p-4 space-y-3">
                <div>
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('Suppliers') }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">{{ __('A product may be purchased from multiple suppliers. Primary supplier is only a preference — Purchasing can still choose another supplier on a purchase order.') }}</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Available Suppliers') }}</label>
                        @php
                            $oldSupplierIds = old('supplier_ids', $selectedSupplierIds ?? []);
                            $oldSupplierIds = array_map('intval', (array) $oldSupplierIds);
                        @endphp
                        <select name="supplier_ids[]" multiple size="6"
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected(in_array($supplier->id, $oldSupplierIds, true))>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        @error('supplier_ids') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Primary Supplier') }}</label>
                        @php $oldPrimary = old('primary_supplier_id', $primarySupplierId ?? null); @endphp
                        <select name="primary_supplier_id"
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                            <option value="">{{ __('No primary supplier') }}</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((int) $oldPrimary === $supplier->id)>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        @endcan
    @endif

    @isset($product)
        <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active)) class="rounded border-slate-300">
            {{ __('Active (selectable in BOQ)') }}
        </label>
    @endisset
</div>
