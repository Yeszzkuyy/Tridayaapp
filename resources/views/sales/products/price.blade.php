<x-app-layout>
    @php
        $keepDates = [
            '3d' => now()->timezone('Asia/Jakarta')->addDays(3)->format('Y-m-d'),
            '7d' => now()->timezone('Asia/Jakarta')->addDays(7)->format('Y-m-d'),
            '14d' => now()->timezone('Asia/Jakarta')->addDays(14)->format('Y-m-d'),
            '1m' => now()->timezone('Asia/Jakarta')->addMonth()->format('Y-m-d'),
        ];
        $supplierOptions = $suppliers->map(fn ($supplier) => [
            'value' => (string) $supplier->id,
            'label' => $supplier->name,
        ])->values()->all();
        $stockOptions = [
            ['value' => 'ready', 'label' => __('Ready')],
            ['value' => 'indent', 'label' => __('Indent')],
        ];
    @endphp
    <div>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('Update Cost Price') }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $product->name }}{{ $product->sku ? ' · '.$product->sku : '' }}</p>
            </div>
            <x-back-button :href="route('products.index')" />
        </div>

        @if($product->latestCostLog)
            <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                {{ __('Latest cost: Rp :price', ['price' => number_format((float) $product->latestCostLog->price, 0, ',', '.')]) }}
                @if($product->latestCostLog->supplier)
                    &middot; {{ $product->latestCostLog->supplier->name }}
                @endif
                &middot; {{ $product->latestCostLog->created_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') }}
            </div>
        @endif

        <form action="{{ route('products.price.update', $product) }}" method="POST"
              x-data="{
                  validUntil: @js(old('valid_until', $product->latestCostLog?->valid_until?->format('Y-m-d') ?? '')),
                  setKeepDate(dateStr) { this.validUntil = dateStr; }
              }"
              class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            @csrf

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="price-supplier" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Supplier') }} <span class="text-rose-500">*</span></label>
                    <x-glide-select id="price-supplier" name="supplier_id" :options="$supplierOptions"
                                    :value="old('supplier_id', $product->latestCostLog?->supplier_id)"
                                    :label="__('Supplier')" :placeholder="__('Select supplier')"
                                    :error="$errors->first('supplier_id')" size="field" :menu-width="260" required />
                </div>

                <div>
                    <label for="price-cost" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Cost Price (Rp)') }} <span class="text-rose-500">*</span></label>
                    <input id="price-cost" type="number" name="price" min="0.01" step="0.01" required value="{{ old('price', $product->latestCostLog?->price) }}"
                           class="h-10 w-full rounded-xl border-slate-300 text-sm tabular-nums focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                    @error('price') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="price-valid" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Valid Until') }}</label>
                    <input id="price-valid" type="date" name="valid_until" x-model="validUntil"
                           class="h-10 w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        <button type="button" @click="setKeepDate('{{ $keepDates['3d'] }}')" class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600 transition hover:border-accent-500 hover:bg-accent-50 hover:text-accent-700 active:scale-95 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">{{ __('+3 Days') }}</button>
                        <button type="button" @click="setKeepDate('{{ $keepDates['7d'] }}')" class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600 transition hover:border-accent-500 hover:bg-accent-50 hover:text-accent-700 active:scale-95 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">{{ __('+1 Week') }}</button>
                        <button type="button" @click="setKeepDate('{{ $keepDates['14d'] }}')" class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600 transition hover:border-accent-500 hover:bg-accent-50 hover:text-accent-700 active:scale-95 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">{{ __('+2 Weeks') }}</button>
                        <button type="button" @click="setKeepDate('{{ $keepDates['1m'] }}')" class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600 transition hover:border-accent-500 hover:bg-accent-50 hover:text-accent-700 active:scale-95 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">{{ __('+1 Month') }}</button>
                    </div>
                    @error('valid_until') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="price-stock" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Stock') }} <span class="text-rose-500">*</span></label>
                    <x-glide-select id="price-stock" name="stock_status" :options="$stockOptions"
                                    :value="old('stock_status', $product->latestCostLog?->stock_status ?: 'ready')"
                                    :label="__('Stock')" :placeholder="__('Stock')"
                                    :error="$errors->first('stock_status')" size="field" required />
                </div>

                <div>
                    <label for="price-eta" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Stock Estimate') }}</label>
                    <input id="price-eta" type="text" name="stock_eta" maxlength="100" value="{{ old('stock_eta', $product->latestCostLog?->stock_eta) }}" placeholder="{{ __('e.g. 2-3 weeks') }}"
                           class="h-10 w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                    @error('stock_eta') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="price-warranty" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Warranty') }} <span class="text-rose-500">*</span></label>
                    <input id="price-warranty" type="text" name="warranty_info" required maxlength="255" value="{{ old('warranty_info', $product->latestCostLog?->warranty_info) }}" placeholder="{{ __('e.g. 1 year official distributor warranty') }}"
                           class="h-10 w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                    @error('warranty_info') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="price-notes" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Note') }}</label>
                    <input id="price-notes" type="text" name="notes" maxlength="255" value="{{ old('notes') }}" placeholder="{{ __('Vendor / MOQ note') }}"
                           class="h-10 w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                    @error('notes') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-700/60">
                <x-secondary-button as="a" :href="route('products.index')">{{ __('Cancel') }}</x-secondary-button>
                <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
