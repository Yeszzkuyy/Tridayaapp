<div class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Nama Produk') }} <span class="text-red-500">*</span></label>
            <input type="text" name="name" required value="{{ old('name', $product->name ?? '') }}"
                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('SKU') }}</label>
            <input type="text" name="sku" value="{{ old('sku', $product->sku ?? '') }}" placeholder="{{ __('cth: CCTV-8CH') }}"
                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
            @error('sku') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Kategori') }} <span class="text-red-500">*</span></label>
            <select name="category" required class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                @foreach(\App\Models\Product::SALE_CATEGORIES as $cat)
                    <option value="{{ $cat }}" @selected(old('category', $product->category ?? 'barang') === $cat)>{{ \App\Models\Product::saleCategoryLabel($cat) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Satuan') }}</label>
            <input type="text" name="unit" value="{{ old('unit', $product->unit ?? 'pcs') }}"
                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Harga Dasar (Rp)') }}</label>
            <input type="number" name="default_price" min="0" step="0.01" value="{{ old('default_price', $product->default_price ?? 0) }}"
                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
        </div>
    </div>
    @isset($product)
        <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active)) class="rounded border-slate-300">
            {{ __('Aktif (bisa dipilih di BOQ)') }}
        </label>
    @endisset
</div>
