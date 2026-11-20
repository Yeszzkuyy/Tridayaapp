<x-app-layout>
    @php
        $supplierOptions = $suppliers->map(fn ($supplier) => [
            'value' => (string) $supplier->id,
            'label' => $supplier->name,
        ])->values()->all();
        $bulkLabels = [
            'product' => __('Product'),
            'cost' => __('Cost'),
            'supplier' => __('Supplier'),
            'validUntil' => __('Valid Until'),
            'vat' => __('VAT'),
            'included' => __('Included'),
            'excluded' => __('Excluded'),
            'noPrice' => __('Enter a price'),
            'noChange' => __('No change'),
            'noExpiry' => __('No expiry date'),
            'notSelected' => __('Not selected'),
            'confirm' => __('Review price changes for :count products.'),
            'unsavedTitle' => __('Leave bulk price update?'),
            'unsavedMessage' => __('You have unsaved price changes. Leaving this page will discard them.'),
            'leave' => __('Leave page'),
            'stay' => __('Stay on this page'),
            'changed' => __('Changed products'),
        ];
    @endphp

    <div class="space-y-5 pb-6"
         x-data="bulkPriceUpdate({ vatRate: @js($vatRate), labels: @js($bulkLabels) })"
         x-init="init()">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                    <a href="{{ route('products.index') }}" class="text-accent-700 hover:underline dark:text-accent-300">{{ __('Pricelist') }}</a>
                    <span aria-hidden="true">/</span>
                    {{ __('Bulk Price Update') }}
                </p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('Bulk Price Update') }}</h1>
            </div>
            <x-back-button :href="route('products.index')" />
        </div>

        <div class="flex items-start gap-3 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-800 dark:bg-sky-950/35 dark:text-sky-200"
             role="note">
            <x-icon name="file-text" class="mt-0.5 h-5 w-5 shrink-0" />
            <div class="space-y-1">
                <p class="font-semibold">{{ __('Only products with existing cost price history are listed.') }}</p>
                <p>{{ __('Each saved change creates a new price history record. Previous costs remain available for comparison.') }}</p>
                <p>{{ __('Current stock and warranty details are copied from the latest price record.') }}</p>
                <p>{{ __('VAT-included prices are recalculated using the current VAT rate (:rate%).', ['rate' => rtrim(rtrim(number_format($vatRate, 2, '.', ''), '0'), '.')]) }}</p>
                <p>{{ __('VAT selection is used only to calculate net cost; history stores net cost.') }}</p>
            </div>
        </div>

        <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800 sm:flex-row sm:items-end">
            <label class="min-w-0 flex-1">
                <span class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Search') }}</span>
                <input type="search" x-model="search" @input="recount()"
                       placeholder="{{ __('Search product name or SKU...') }}"
                       class="h-10 w-full rounded-xl border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-100 dark:placeholder:text-slate-500">
            </label>
            <p class="text-xs text-slate-500 dark:text-slate-400" aria-live="polite">
                {{ __('Showing') }} <span x-text="visibleCount()"></span> {{ __('Products on this page') }}
            </p>
        </div>

        <form id="bulk-price-update-form"
              action="{{ route('products.bulk-price.update') }}"
              method="POST"
              data-confirm="{{ __('Review price changes before saving.') }}"
              data-confirm-title="{{ __('Review price changes?') }}"
              data-confirm-tone="warning"
              data-confirm-label="{{ __('Confirm & Save Changes') }}"
              data-confirm-details="[]"
              x-bind:data-confirm="confirmMessage()"
              x-bind:data-confirm-details="JSON.stringify(reviewDetails())"
              @input="recount()"
              @change="recount()"
              @formdata="stripUnchanged($event.formData)">
            @csrf

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800"
                     aria-label="{{ __('Bulk price comparison') }}">
                <div class="overflow-x-auto">
                    <table class="min-w-[1120px] w-full text-left text-sm">
                        <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-900/50 dark:text-slate-400">
                            <tr>
                                <th scope="col" class="px-4 py-3 sm:px-5">{{ __('Product') }}</th>
                                <th scope="col" class="px-4 py-3 text-right sm:px-5">{{ __('Previous Cost') }}</th>
                                <th scope="col" class="px-4 py-3 text-right sm:px-5">{{ __('New Cost') }}</th>
                                <th scope="col" class="px-4 py-3">{{ __('Supplier') }}</th>
                                <th scope="col" class="px-4 py-3">{{ __('Valid Until') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            @forelse($products as $product)
                                @php
                                    $log = $product->latestCostLog;
                                    $enteredPrice = $log->price;
                                    $priceInputKey = 'items.'.$product->id.'.price';
                                    $supplierInputKey = 'items.'.$product->id.'.supplier_id';
                                    $validUntilInputKey = 'items.'.$product->id.'.valid_until';
                                    $includesTaxInputKey = 'items.'.$product->id.'.price_includes_tax';
                                    $hasValidationErrors = $errors->any();
                                    $priceInputValue = $hasValidationErrors
                                        ? old($priceInputKey, $enteredPrice)
                                        : $enteredPrice;
                                    $priceInputValue = is_scalar($priceInputValue) ? (string) $priceInputValue : '';
                                    $priceDisplay = is_numeric($priceInputValue)
                                        ? number_format((float) $priceInputValue, 0, ',', '.')
                                        : $priceInputValue;
                                    $supplierInputValue = $hasValidationErrors
                                        ? old($supplierInputKey, $log->supplier_id)
                                        : $log->supplier_id;
                                    $supplierInputValue = is_scalar($supplierInputValue) ? (string) $supplierInputValue : '';
                                    $validUntilInputValue = $hasValidationErrors
                                        ? old($validUntilInputKey, $log->valid_until?->format('Y-m-d') ?? '')
                                        : ($log->valid_until?->format('Y-m-d') ?? '');
                                    $validUntilInputValue = is_scalar($validUntilInputValue) ? (string) $validUntilInputValue : '';
                                    $includesTaxInputValue = $hasValidationErrors
                                        ? old($includesTaxInputKey, false)
                                        : false;
                                    $includesTaxInputValue = is_scalar($includesTaxInputValue) ? (bool) $includesTaxInputValue : false;
                                    $originalValidUntil = $log->valid_until?->format('Y-m-d') ?? '';
                                    $rowSupplierOptions = $supplierOptions;
                                    if ($log->supplier && ! $suppliers->contains('id', $log->supplier_id)) {
                                        $rowSupplierOptions = collect([[
                                            'value' => (string) $log->supplier_id,
                                            'label' => $log->supplier->name.' ('.__('Inactive Supplier').')',
                                        ]])->concat($supplierOptions)->all();
                                    }
                                    $searchText = strtolower(collect([
                                        $product->name,
                                        $product->sku,
                                        $product->part_number,
                                        \App\Models\Product::saleCategoryLabel($product->category),
                                    ])->filter()->implode(' '));
                                @endphp
                                <tr data-bulk-row
                                    data-product-name="{{ $product->name }}{{ $product->sku ? ' · '.$product->sku : '' }}"
                                    data-search="{{ $searchText }}"
                                    data-previous-net-cost="{{ (float) $log->price }}"
                                    data-original-supplier="{{ $log->supplier_id ?? '' }}"
                                    data-original-supplier-name="{{ $log->supplier?->name ?? '' }}"
                                    data-original-valid-until="{{ $originalValidUntil }}"
                                    x-show="matchesSearch($el.dataset.search)"
                                    class="align-top transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-700/30">
                                    <td class="min-w-[240px] px-4 py-4 sm:px-5">
                                        <input type="hidden" data-bulk-field name="items[{{ $product->id }}][product_id]" value="{{ $product->id }}">
                                        <p class="font-semibold text-slate-900 dark:text-slate-100">{{ $product->name }}</p>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                            {{ $product->sku ?: '-' }} <span aria-hidden="true">·</span>
                                            {{ \App\Models\Product::saleCategoryLabel($product->category) }} <span aria-hidden="true">·</span>
                                            {{ $product->unit ?: '-' }}
                                        </p>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-right tabular-nums sm:px-5">
                                        <span class="font-semibold text-slate-700 dark:text-slate-200">Rp {{ number_format((float) $log->price, 0, ',', '.') }}</span>
                                    </td>
                                    <td class="min-w-[220px] px-4 py-3 text-right sm:px-5">
                                        <div class="relative">
                                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-500 dark:text-slate-300" aria-hidden="true">Rp</span>
                                            <input type="text"
                                                   inputmode="numeric"
                                                   data-bulk-field
                                                   data-bulk-price
                                                   name="items[{{ $product->id }}][price]"
                                                   value="{{ $priceDisplay }}"
                                                   aria-label="{{ __('New Cost for :product', ['product' => $product->name]) }}"
                                                   @input="formatPrice($event)"
                                                   class="h-10 w-full min-w-[180px] rounded-xl border-slate-300 pl-9 pr-3 text-right text-sm font-semibold tabular-nums text-slate-900 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-100">
                                        </div>
                                        <div class="mt-1 flex items-center justify-end gap-2">
                                            <input type="hidden" data-bulk-field name="items[{{ $product->id }}][price_includes_tax]" value="0">
                                            <label class="inline-flex items-center gap-1.5 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                                                <input type="checkbox"
                                                       data-bulk-field
                                                       data-bulk-includes-tax
                                                       name="items[{{ $product->id }}][price_includes_tax]"
                                                       value="1"
                                                       @checked($includesTaxInputValue)
                                                       class="rounded border-slate-300 text-accent-600 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                                                {{ __('Price includes VAT') }}
                                            </label>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                            <span class="font-medium">{{ __('Price Difference') }}:</span>
                                            <span class="font-semibold" aria-live="polite"
                                                  x-text="difference($el.closest('[data-bulk-row]'))"></span>
                                        </p>
                                    </td>
                                    <td class="min-w-[220px] px-4 py-3 sm:px-5" data-bulk-supplier>
                                        <x-glide-select :name="'items['.$product->id.'][supplier_id]'"
                                                        :options="$rowSupplierOptions"
                                                        :value="$supplierInputValue"
                                                        :label="__('Supplier')"
                                                        :placeholder="__('Select supplier')"
                                                        size="field" :menu-width="260" searchable
                                                        :search-placeholder="__('Search supplier...')" required />
                                    </td>
                                    <td class="min-w-[170px] px-4 py-3 sm:px-5">
                                        <input type="date"
                                               data-bulk-field
                                               data-bulk-valid-until
                                               name="items[{{ $product->id }}][valid_until]"
                                               value="{{ $validUntilInputValue }}"
                                               aria-label="{{ __('Valid Until for :product', ['product' => $product->name]) }}"
                                               class="h-10 w-full rounded-xl border-slate-300 text-sm text-slate-900 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-100">
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-14 text-center">
                                        <x-empty-state label="{{ __('products with price history') }}"
                                                       description="{{ __('No products with existing cost history are available for bulk update.') }}" />
                                    </td>
                                </tr>
                            @endforelse
                            @if($products->count() > 0)
                                <tr x-show="visibleCount() === 0" x-cloak>
                                    <td colspan="5" class="px-5 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                                        {{ __('No products match this search. Try another product name or SKU.') }}
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </section>

            @if($products->hasPages())
                <div class="mt-4"
                     @click.capture="guardPagination($event)">
                    {{ $products->links() }}
                </div>
            @endif

            <div class="sticky bottom-0 z-20 mt-4 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-[var(--card-bg)] p-4 shadow-lg shadow-slate-900/10 dark:border-slate-700 dark:shadow-black/30 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                        <span x-text="changedCount"></span> {{ __('products changed') }}
                    </p>
                    <p x-show="changedCount > 0" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Review your changes before saving.') }}</p>
                    <p x-show="changedCount === 0" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('No price changes detected.') }}</p>
                </div>
                <x-primary-button type="submit"
                                  x-bind:disabled="changedCount === 0"
                                  x-bind:aria-label="'{{ __('Review Changes') }} (' + changedCount + ')'"
                                  x-bind:class="{ 'cursor-not-allowed opacity-50': changedCount === 0 }">
                    {{ __('Review Changes') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
