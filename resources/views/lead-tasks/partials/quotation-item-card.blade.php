@php
    $isTemplate = $index === '__INDEX__';
    $itemId = $item?->id;
    $productId = $item?->product_id;
    $unitCost = $item?->unit_cost;
    $targetMargin = $item?->target_margin_percent ?? $defaultMargin;
    $targetMarginValue = rtrim(rtrim(number_format((float) $targetMargin, 2, '.', ''), '0'), '.');
    $quantityValue = $item ? rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') : 1;
    $subtotal = (float) ($item?->subtotal ?? 0);
    $marginAmount = $item && $item->unit_cost !== null
        ? \App\Support\Pricing::marginAmount((float) $item->unit_price, (float) $item->unit_cost, (float) $item->quantity, (float) $item->discount)
        : null;
    $marginPercent = $item && $item->unit_cost !== null
        ? \App\Support\Pricing::marginPercent((float) $item->unit_price, (float) $item->unit_cost, (float) $item->quantity, (float) $item->discount)
        : null;
    $marginTone = $marginAmount === null
        ? 'text-slate-500 dark:text-slate-400'
        : ($marginAmount < 0 ? 'text-rose-700 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400');

    $costInfo = $productId && isset($productData[$productId]) ? $productData[$productId] : null;
    $costTitle = __('Cost from Estimator. This value cannot be edited here.');
    if ($costInfo && ! empty($costInfo['supplier'])) {
        $costTitle = __('Cost from Estimator') . ': ' . $costInfo['supplier'];
        if (! empty($costInfo['date'])) {
            $costTitle .= ' · ' . $costInfo['date'];
        }
    }

    $descriptionError = ! $isTemplate && $errors->has("items.$index.description") ? $errors->first("items.$index.description") : null;
    $quantityError = ! $isTemplate && $errors->has("items.$index.quantity") ? $errors->first("items.$index.quantity") : null;
    $priceError = ! $isTemplate && $errors->has("items.$index.unit_price") ? $errors->first("items.$index.unit_price") : null;
    $hasDetailError = $descriptionError !== null;
    $detailOpen = $hasDetailError;
@endphp

<div data-item-card data-index="{{ $index }}" data-estimator-row-id="{{ $estimatorRowId }}"
     class="quotation-item border-b border-slate-100 transition-colors duration-150 last:border-b-0 hover:bg-slate-50/70 dark:border-slate-700/50 dark:hover:bg-slate-800/40">
    <div class="grid grid-cols-12 items-start gap-x-2 gap-y-2 px-3 py-2 lg:grid-cols-[28px_28px_minmax(180px,1fr)_110px_142px_80px_136px_108px_176px_56px] lg:items-center lg:gap-y-0 lg:py-1">
        {{-- Expand --}}
        <div class="col-span-1 flex items-center lg:col-span-1">
            <button type="button" data-item-toggle aria-expanded="{{ $detailOpen ? 'true' : 'false' }}"
                    aria-controls="item-detail-{{ $index }}"
                    aria-label="{{ __('Show item details') }}" title="{{ __('Show item details') }}"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition-colors duration-200 hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 dark:hover:bg-slate-700 dark:hover:text-slate-200">
                <x-icon name="chevron-right" class="h-4 w-4 transition-transform duration-200 {{ $detailOpen ? 'rotate-90' : '' }}" data-item-chevron />
            </button>
        </div>

        {{-- Row number --}}
        <div class="hidden text-xs font-semibold tabular-nums text-slate-400 lg:col-span-1 lg:block" data-item-number>{{ $isTemplate ? 1 : $rowNumber }}</div>

        {{-- Product --}}
        <div class="col-span-11 min-w-0 lg:col-span-1">
            <label class="mb-1 block text-[12px] font-medium text-slate-500 dark:text-slate-400 lg:hidden">{{ __('Product') }}</label>
            <x-glide-select id="item-product-{{ $index }}" name="items[{{ $index }}][product_id]"
                            :options="$productOptions" :value="$productId"
                            :label="__('Product')" :placeholder="__('Select product (optional)')"
                            size="field" searchable :search-placeholder="__('Search product...')" />
        </div>

        {{-- Quantity --}}
        <div class="col-span-6 lg:col-span-1">
            <label class="mb-1 block text-[12px] font-medium text-slate-500 dark:text-slate-400 lg:hidden">{{ __('Qty') }} <span class="text-rose-500">*</span></label>
            <div class="flex gap-1.5">
                <input type="number" name="items[{{ $index }}][quantity]" required min="0.01" step="0.01"
                       value="{{ $quantityValue }}" aria-label="{{ __('Qty') }}"
                       class="h-10 w-full min-w-0 rounded-xl border-slate-300 px-2 text-right text-sm tabular-nums text-slate-900 transition-colors duration-200 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-100">
                <input type="text" name="items[{{ $index }}][unit]" maxlength="16" value="{{ $item?->unit ?? 'pcs' }}"
                       aria-label="{{ __('Unit') }}"
                       class="h-10 w-12 shrink-0 rounded-xl border-slate-300 px-1 text-center text-xs text-slate-600 transition-colors duration-200 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-300">
            </div>
            @if($quantityError)
                <p class="mt-1 text-[12px] font-medium text-rose-700 dark:text-rose-400">{{ $quantityError }}</p>
            @endif
        </div>

        {{-- Cost (read-only) --}}
        <div class="col-span-6 lg:col-span-1">
            <label class="mb-1 block text-[12px] font-medium text-slate-500 dark:text-slate-400 lg:hidden">{{ __('Cost') }}</label>
            <div class="flex h-10 items-center justify-end gap-1 rounded-xl bg-slate-100 px-2 dark:bg-slate-950/50" title="{{ $costTitle }}">
                <span data-cost-display class="min-w-0 truncate text-sm font-semibold tabular-nums text-slate-600 dark:text-slate-300">
                    {{ $unitCost !== null ? 'Rp '.number_format((float) $unitCost, 0, ',', '.') : '' }}
                </span>
                <button type="button" data-history-trigger title="{{ __('View Price History') }}" aria-label="{{ __('View Price History') }}"
                        class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors duration-200 hover:bg-white hover:text-accent-600 dark:hover:bg-slate-800 dark:hover:text-accent-300">
                    <x-icon name="clock" class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>

        {{-- Target margin --}}
        <div class="col-span-6 lg:col-span-1">
            <label class="mb-1 block text-[12px] font-medium text-slate-500 dark:text-slate-400 lg:hidden">{{ __('Margin %') }}</label>
            <div class="relative">
                <input type="number" name="items[{{ $index }}][target_margin_percent]" min="0" max="99.99" step="0.01"
                       value="{{ $targetMarginValue }}" data-target-margin aria-label="{{ __('Target Margin (%)') }}"
                       class="h-10 w-full rounded-xl border-slate-300 pl-2 pr-6 text-right text-sm tabular-nums text-slate-900 transition-colors duration-200 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-100">
                <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-xs text-slate-400" aria-hidden="true">%</span>
            </div>
        </div>

        {{-- Selling price --}}
        <div class="col-span-6 lg:col-span-1">
            <label class="mb-1 block text-[12px] font-medium text-slate-500 dark:text-slate-400 lg:hidden">{{ __('Selling Price') }} <span class="text-rose-500">*</span></label>
            <x-rupiah-input name="items[{{ $index }}][unit_price]" :value="$item?->unit_price ?? 0"
                            :aria-label="__('Selling Price')" :input-class="$priceError ? 'border-rose-400' : ''" required />
            @if($priceError)
                <p class="mt-1 text-[12px] font-medium text-rose-700 dark:text-rose-400">{{ $priceError }}</p>
            @endif
        </div>

        {{-- Discount --}}
        <div class="col-span-6 lg:col-span-1">
            <label class="mb-1 block text-[12px] font-medium text-slate-500 dark:text-slate-400 lg:hidden">{{ __('Disc. (Rp)') }}</label>
            <x-rupiah-input name="items[{{ $index }}][discount]" :value="$item?->discount ?? 0"
                            :aria-label="__('Discount')" />
        </div>

        {{-- Subtotal + margin --}}
        <div class="col-span-6 lg:col-span-1">
            <label class="mb-1 block text-[12px] font-medium text-slate-500 dark:text-slate-400 lg:hidden">{{ __('Subtotal') }}</label>
            <div class="lg:text-right">
                <span data-item-subtotal class="block text-sm font-bold tabular-nums text-slate-800 dark:text-slate-100">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                <span data-item-margin class="block text-[12px] font-medium tabular-nums {{ $marginTone }}">
                    @if($marginAmount !== null)
                        {{ __('Margin') }}: Rp {{ number_format($marginAmount, 0, ',', '.') }}
                        ({{ $marginPercent === null ? '-' : rtrim(rtrim(number_format($marginPercent, 1, ',', '.'), '0'), ',') . '%' }})
                    @else
                        {{ __('Margin') }}: -
                    @endif
                </span>
            </div>
        </div>

        {{-- Actions --}}
        <div class="col-span-12 flex items-center justify-end lg:col-span-1">
            <x-icon-button icon="delete" type="button" data-remove-item title="{{ __('Delete') }}" />
        </div>
    </div>

    {{-- Detail row --}}
    <div id="item-detail-{{ $index }}" data-item-detail class="{{ $detailOpen ? '' : 'hidden' }} border-t border-slate-100 bg-slate-50/70 px-4 py-3 dark:border-slate-700/50 dark:bg-slate-900/40">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[2fr_1fr_1fr_1fr]">
            <div class="sm:col-span-2 xl:col-span-1">
                <label class="mb-1.5 block text-[12px] font-medium text-slate-500 dark:text-slate-400">{{ __('Description') }} <span class="text-rose-500">*</span></label>
                <textarea name="items[{{ $index }}][description]" rows="2" maxlength="255" data-item-description
                          aria-label="{{ __('Description') }}"
                          placeholder="{{ __('Item / product description') }}"
                          class="w-full rounded-xl border-slate-300 px-3 py-2 text-sm text-slate-900 transition-colors duration-200 placeholder:text-slate-400 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-100 dark:placeholder:text-slate-500">{{ $item?->description }}</textarea>
                @if($descriptionError)
                    <p class="mt-1 text-[12px] font-medium text-rose-700 dark:text-rose-400">{{ $descriptionError }}</p>
                @endif
            </div>
            <div>
                <label class="mb-1.5 block text-[12px] font-medium text-slate-500 dark:text-slate-400">{{ __('Scope of Supply') }}</label>
                <input type="text" name="items[{{ $index }}][scope_of_supply]" maxlength="100" value="{{ $item?->scope_of_supply }}"
                       aria-label="{{ __('Scope of Supply') }}" placeholder="WAN-RS-..."
                       class="h-10 w-full rounded-xl border-slate-300 px-3 text-sm text-slate-900 transition-colors duration-200 placeholder:text-slate-400 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-100 dark:placeholder:text-slate-500">
            </div>
            <div>
                <label class="mb-1.5 block text-[12px] font-medium text-slate-500 dark:text-slate-400">{{ __('Estimated Lead Time') }}</label>
                <input type="text" name="items[{{ $index }}][lead_time]" maxlength="100" value="{{ $item?->lead_time ?? 'Ready Stock' }}"
                       aria-label="{{ __('Estimated Lead Time') }}"
                       class="h-10 w-full rounded-xl border-slate-300 px-3 text-sm text-slate-900 transition-colors duration-200 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-100">
            </div>
            <div>
                <label class="mb-1.5 block text-[12px] font-medium text-slate-500 dark:text-slate-400">{{ __('Section') }}</label>
                <input type="text" name="items[{{ $index }}][section]" maxlength="100" value="{{ $item?->section ?? 'MATERIAL' }}"
                       aria-label="{{ __('Section') }}"
                       class="h-10 w-full rounded-xl border-slate-300 px-3 text-sm text-slate-900 transition-colors duration-200 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-100">
            </div>
        </div>
    </div>

    @if($itemId)
        <input type="hidden" name="items[{{ $index }}][id]" value="{{ $itemId }}">
    @endif
    <input type="hidden" name="items[{{ $index }}][product_price_log_id]" value="{{ $item?->product_price_log_id }}">
    <input type="hidden" name="items[{{ $index }}][unit_cost]" value="{{ $unitCost !== null ? (int) round((float) $unitCost) : '' }}">
</div>
