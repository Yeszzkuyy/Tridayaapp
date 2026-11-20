@php
    $isTemplate = $index === '__INDEX__';
    $itemId = $item?->id;
    $productId = $item?->product_id;
    $unitCost = $item?->unit_cost;
    $targetMargin = $item?->target_margin_percent ?? $defaultMargin;
    $subtotal = (float) ($item?->subtotal ?? 0);
    $marginAmount = $item && $item->unit_cost !== null
        ? \App\Support\Pricing::marginAmount((float) $item->unit_price, (float) $item->unit_cost, (float) $item->quantity, (float) $item->discount)
        : null;
    $marginPercent = $item && $item->unit_cost !== null
        ? \App\Support\Pricing::marginPercent((float) $item->unit_price, (float) $item->unit_cost, (float) $item->quantity, (float) $item->discount)
        : null;
    $marginTone = $marginAmount === null
        ? 'text-slate-500 dark:text-slate-400'
        : ($marginAmount < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400');
@endphp

<div data-item-card data-index="{{ $index }}"
     class="quotation-item-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-colors duration-200 dark:border-slate-700/60 dark:bg-slate-900/50">
    @if($itemId)
        <input type="hidden" name="items[{{ $index }}][id]" value="{{ $itemId }}">
    @endif
    <input type="hidden" name="items[{{ $index }}][product_price_log_id]" value="{{ $item?->product_price_log_id }}">
    <input type="hidden" name="items[{{ $index }}][unit_cost]" value="{{ $unitCost !== null ? (int) round((float) $unitCost) : '' }}">

    <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
        <div class="space-y-3 md:col-span-5">
            <div>
                <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Product') }}</label>
                <x-glide-select id="item-product-{{ $index }}" name="items[{{ $index }}][product_id]"
                                :options="$productOptions" :value="$productId"
                                :label="__('Product')" :placeholder="__('Select product (optional)')"
                                size="field" searchable :search-placeholder="__('Search product...')" />
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Description') }} <span class="text-rose-500">*</span></label>
                <input type="text" name="items[{{ $index }}][description]" required maxlength="255"
                       value="{{ $item?->description }}"
                       placeholder="{{ __('Item / product description') }}"
                       class="h-10 w-full rounded-xl border-slate-300 text-sm text-slate-900 transition-colors duration-200 placeholder:text-slate-400 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-100 dark:placeholder:text-slate-500">
            </div>
        </div>
        <div class="md:col-span-2">
            <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Scope of Supply') }}</label>
            <input type="text" name="items[{{ $index }}][scope_of_supply]" maxlength="100" value="{{ $item?->scope_of_supply }}"
                   placeholder="WAN-RS-..."
                   class="h-10 w-full rounded-xl border-slate-300 text-sm text-slate-900 transition-colors duration-200 placeholder:text-slate-400 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-100 dark:placeholder:text-slate-500">
        </div>
        <div class="md:col-span-2">
            <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Estimated Lead Time') }}</label>
            <input type="text" name="items[{{ $index }}][lead_time]" maxlength="100" value="{{ $item?->lead_time ?? 'Ready Stock' }}"
                   class="h-10 w-full rounded-xl border-slate-300 text-sm text-slate-900 transition-colors duration-200 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-100">
        </div>
        <div class="md:col-span-2">
            <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Section') }}</label>
            <input type="text" name="items[{{ $index }}][section]" maxlength="100" value="{{ $item?->section ?? 'MATERIAL' }}"
                   class="h-10 w-full rounded-xl border-slate-300 text-sm text-slate-900 transition-colors duration-200 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-100">
        </div>
        <div class="flex items-end justify-end md:col-span-1">
            <x-icon-button icon="delete" type="button" data-remove-item title="{{ __('Delete') }}" />
        </div>
    </div>

    <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-12">
        <div class="md:col-span-2">
            <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Qty') }} <span class="text-rose-500">*</span></label>
            <div class="flex gap-2">
                <input type="number" name="items[{{ $index }}][quantity]" required min="0.01" step="0.01" value="{{ $item?->quantity ?? 1 }}"
                       class="h-10 w-full min-w-0 rounded-xl border-slate-300 text-sm tabular-nums text-slate-900 transition-colors duration-200 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-100">
                <input type="text" name="items[{{ $index }}][unit]" maxlength="16" value="{{ $item?->unit ?? 'pcs' }}"
                       class="h-10 w-16 shrink-0 rounded-xl border-slate-300 text-center text-sm text-slate-900 transition-colors duration-200 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-100">
            </div>
        </div>
        <div class="md:col-span-3">
            <div class="flex items-center justify-between gap-2">
                <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Cost Price (Estimator)') }} <span class="text-[10px] font-normal text-slate-400">({{ __('Auto') }})</span></label>
                <button type="button" data-history-trigger title="{{ __('View Price History') }}" aria-label="{{ __('View Price History') }}"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-xl text-slate-400 transition-colors duration-200 hover:bg-slate-100 hover:text-accent-600 dark:hover:bg-slate-700 dark:hover:text-accent-300">
                    <x-icon name="clock" class="h-3.5 w-3.5" />
                </button>
            </div>
            <div class="relative">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-500 dark:text-slate-300" aria-hidden="true">Rp</span>
                <input type="text" readonly value="{{ $unitCost !== null ? number_format((float) $unitCost, 0, ',', '.') : '' }}"
                       data-cost-display
                       placeholder="{{ __('From estimator') }}"
                       class="h-10 w-full cursor-not-allowed rounded-xl border-slate-300 bg-slate-100 pl-9 pr-3 text-right text-sm font-semibold tabular-nums text-slate-600 placeholder:text-slate-400 dark:border-slate-700/60 dark:bg-slate-950/60 dark:text-slate-400">
            </div>
        </div>
        <div class="md:col-span-2">
            <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Target Margin (%)') }}</label>
            <input type="number" name="items[{{ $index }}][target_margin_percent]" min="0" max="99.99" step="0.01"
                   value="{{ $targetMargin }}"
                   data-target-margin
                   class="h-10 w-full rounded-xl border-slate-300 text-sm tabular-nums text-slate-900 transition-colors duration-200 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-100">
        </div>
        <div class="md:col-span-3">
            <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Selling Price') }} <span class="text-rose-500">*</span></label>
            <x-rupiah-input name="items[{{ $index }}][unit_price]" :value="$item?->unit_price ?? 0"
                            :aria-label="__('Selling Price')" required />
        </div>
        <div class="md:col-span-2">
            <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Disc. (Rp)') }}</label>
            <x-rupiah-input name="items[{{ $index }}][discount]" :value="$item?->discount ?? 0"
                            :aria-label="__('Discount')" />
        </div>
    </div>

    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
        <span class="text-slate-500 dark:text-slate-400">{{ __('Subtotal') }}: <span class="font-semibold tabular-nums text-slate-800 dark:text-slate-100" data-item-subtotal>Rp {{ number_format($subtotal, 0, ',', '.') }}</span></span>
        <span class="{{ $marginTone }}" data-item-margin>
            @if($marginAmount !== null)
                {{ __('Margin') }}: Rp {{ number_format($marginAmount, 0, ',', '.') }}
                ({{ $marginPercent === null ? '-' : number_format($marginPercent, 2).'%' }})
            @else
                {{ __('Margin') }}: -
            @endif
        </span>
    </div>
</div>
