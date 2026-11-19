@php
    $itemsRequired = $itemsRequired ?? true;
    $defaultMargin = isset($proposal)
        ? ($proposal->default_margin_percent ?? \App\Models\AppSetting::defaultMarginPercent())
        : \App\Models\AppSetting::defaultMarginPercent();
    $taxRateValue = isset($proposal)
        ? ($proposal->tax_rate ?? ($proposal->netSales() > 0
            ? round(((float) $proposal->tax / $proposal->netSales()) * 100, 2)
            : \App\Models\AppSetting::vatRate()))
        : \App\Models\AppSetting::vatRate();
@endphp
<div class="space-y-4">
    <input type="hidden" name="lead_id" value="{{ $lead->id ?? $proposal->lead_id }}">

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Quotation Date') }} <span class="text-red-500">*</span></label>
            <input type="date" name="proposal_date" required value="{{ old('proposal_date', isset($proposal) ? $proposal->proposal_date?->format('Y-m-d') : now()->format('Y-m-d')) }}"
                    class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Valid Until') }}</label>
            <input type="date" name="valid_until" value="{{ old('valid_until', isset($proposal) ? $proposal->valid_until?->format('Y-m-d') : '') }}"
                    class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
            @error('valid_until') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Default Margin (%)') }}</label>
            <input id="proposal-default-margin" type="number" name="default_margin_percent" min="0" max="99.99" step="0.01" value="{{ old('default_margin_percent', $defaultMargin) }}"
                    class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
            @error('default_margin_percent') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            <p class="mt-1 text-[11px] text-slate-400">{{ __('Inherited by new items. Existing items keep their own margin.') }}</p>
        </div>
    </div>

    <div x-data="{ scheme: '{{ old('payment_scheme', $proposal->payment_scheme ?? '') }}' }" class="space-y-4 rounded-xl border border-slate-200 dark:border-slate-600 p-4">
        <div>
            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('Payment Terms') }}</h3>
            <p class="text-xs text-slate-500">{{ __('Admin Finance uses this to decide whether to invoice a DP or the full amount.') }}</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Payment Scheme') }}</label>
                <select name="payment_scheme" x-model="scheme"
                        class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                    <option value="">{{ __('Not set yet') }}</option>
                    <option value="full">{{ __('Full Payment 100%') }}</option>
                    <option value="dp">{{ __('Down Payment + Settlement') }}</option>
                    <option value="tempo">{{ __('Tempo / Net Days') }}</option>
                </select>
                @error('payment_scheme') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div x-show="scheme === 'dp'" x-cloak>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('DP Percentage') }} <span class="text-red-500">*</span></label>
                <input type="number" name="dp_percent" min="1" max="99" step="0.01" value="{{ old('dp_percent', $proposal->dp_percent ?? '') }}"
                        class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                @error('dp_percent') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div x-show="scheme === 'tempo'" x-cloak>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Term Days') }} <span class="text-red-500">*</span></label>
                <input type="number" name="term_days" min="1" max="365" step="1" value="{{ old('term_days', $proposal->term_days ?? '') }}"
                        class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                @error('term_days') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Payment Terms Note') }}</label>
                <input type="text" name="payment_terms" maxlength="255" value="{{ old('payment_terms', $proposal->payment_terms ?? '') }}"
                        placeholder="{{ __('e.g. DP 30% after PO, 70% after BAST') }}"
                        class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                @error('payment_terms') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Delivery Terms') }}</label>
                <input type="text" name="delivery_terms" maxlength="255" value="{{ old('delivery_terms', $proposal->delivery_terms ?? '') }}"
                        placeholder="{{ __('e.g. Ready stock 3-5 working days') }}"
                        class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                @error('delivery_terms') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Warranty Terms') }}</label>
                <input type="text" name="warranty_terms" maxlength="255" value="{{ old('warranty_terms', $proposal->warranty_terms ?? '') }}"
                        placeholder="{{ __('e.g. 1 year official distributor warranty') }}"
                        class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                @error('warranty_terms') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div>
        <div class="flex items-center justify-between mb-2">
            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Item') }} @if($itemsRequired)<span class="text-red-500">*</span>@endif</label>
            <x-icon-button icon="add" label="{{ __('Item') }}" title="{{ __('Item') }}" type="button" onclick="proposalAddRow()" />
        </div>
        <div class="relative mb-2">
            <input type="text" id="proposal-catalog-search" autocomplete="off" placeholder="{{ __('Search catalog...') }}"
                    class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
            <div id="proposal-catalog-results" class="absolute z-10 w-full mt-1 hidden max-h-56 overflow-auto rounded-xl border border-slate-200 bg-white shadow-lg dark:bg-slate-800 dark:border-slate-600"></div>
        </div>
        <div id="proposal-items" class="space-y-2">
            @php
                $rows = old('items', isset($proposal) ? $proposal->items->map(fn ($i) => [
                    'product_id' => $i->product_id,
                    'product_price_log_id' => $i->product_price_log_id,
                    'description' => $i->description,
                    'scope_of_supply' => $i->scope_of_supply,
                    'lead_time' => $i->lead_time,
                    'section' => $i->section,
                    'quantity' => $i->quantity,
                    'unit' => $i->unit,
                    'unit_price' => $i->unit_price,
                    'unit_cost' => $i->unit_cost,
                    'target_margin_percent' => $i->target_margin_percent ?? $defaultMargin,
                    'stock_status' => $i->stock_status,
                    'stock_eta' => $i->stock_eta,
                    'warranty_info' => $i->warranty_info,
                    'discount' => $i->discount,
                ])->all() : []);
                if (empty($rows)) {
                    $rows = [[
                        'product_id' => null, 'product_price_log_id' => null, 'description' => '', 'scope_of_supply' => null,
                        'lead_time' => null, 'section' => 'MATERIAL', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => 0,
                        'unit_cost' => null, 'target_margin_percent' => $defaultMargin, 'stock_status' => null,
                        'stock_eta' => null, 'warranty_info' => null, 'discount' => 0,
                    ]];
                }
                $historyByProduct = \App\Models\ProductPriceLog::with('supplier')
                    ->where('price_type', 'cost')
                    ->whereIn('product_id', collect($rows)->pluck('product_id')->filter()->unique())
                    ->orderByDesc('created_at')
                    ->get()
                    ->groupBy('product_id');
            @endphp
            @foreach($rows as $i => $row)
                <div class="proposal-row grid grid-cols-12 gap-2 p-3 rounded-xl bg-slate-50 dark:bg-slate-700/50" data-cost="{{ $row['unit_cost'] ?? '' }}">
                    <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $row['product_id'] }}">
                    <input type="hidden" name="items[{{ $i }}][product_price_log_id]" value="{{ $row['product_price_log_id'] ?? '' }}">
                    <input type="hidden" name="items[{{ $i }}][stock_status]" value="{{ $row['stock_status'] ?? '' }}">
                    <input type="hidden" name="items[{{ $i }}][stock_eta]" value="{{ $row['stock_eta'] ?? '' }}">
                    <input type="hidden" name="items[{{ $i }}][warranty_info]" value="{{ $row['warranty_info'] ?? '' }}">
                    <input type="hidden" name="items[{{ $i }}][scope_of_supply]" value="{{ $row['scope_of_supply'] ?? '' }}">
                    <input type="hidden" name="items[{{ $i }}][lead_time]" value="{{ $row['lead_time'] ?? '' }}">
                    <input type="hidden" name="items[{{ $i }}][section]" value="{{ $row['section'] ?? 'MATERIAL' }}">
                    <input type="text" name="items[{{ $i }}][description]" @if($itemsRequired) required @endif value="{{ $row['description'] }}" placeholder="{{ __('Description') }}"
                            class="col-span-12 md:col-span-4 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                    <input type="number" name="items[{{ $i }}][quantity]" required min="0.01" step="0.01" value="{{ $row['quantity'] }}" placeholder="{{ __('Qty') }}"
                            class="col-span-4 md:col-span-1 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                    <input type="text" name="items[{{ $i }}][unit]" value="{{ $row['unit'] }}" placeholder="{{ __('Unit') }}"
                            class="col-span-4 md:col-span-1 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                    <input type="number" name="items[{{ $i }}][unit_price]" @if($itemsRequired) required @endif min="0" step="0.01" value="{{ $row['unit_price'] }}" placeholder="{{ __('Price') }}"
                            class="col-span-4 md:col-span-2 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                    @can('updateCost', \App\Models\Proposal::class)
                        <input type="number" name="items[{{ $i }}][unit_cost]" min="0" step="0.01" value="{{ old('items.'.$i.'.unit_cost', $row['unit_cost']) }}" placeholder="{{ __('Cost Price (Rp)') }}"
                                class="col-span-6 md:col-span-2 rounded-xl border-amber-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-amber-700">
                    @else
                        <span class="col-span-6 md:col-span-2 self-center rounded-xl bg-slate-100 px-3 py-2 text-xs text-slate-500 dark:bg-slate-700 dark:text-slate-300">
                            {{ __('Cost') }}: {{ $row['unit_cost'] !== null && $row['unit_cost'] !== '' ? 'Rp '.number_format((float) $row['unit_cost'], 0, ',', '.') : '-' }}
                        </span>
                    @endcan
                    <input type="number" name="items[{{ $i }}][discount]" min="0" step="0.01" value="{{ $row['discount'] }}" placeholder="{{ __('Discount') }}"
                            class="col-span-3 md:col-span-1 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                    <span class="proposal-row-subtotal col-span-3 md:col-span-1 self-center text-right text-xs font-semibold text-slate-600 dark:text-slate-300">0</span>

                    <input type="number" name="items[{{ $i }}][target_margin_percent]" min="0" max="99.99" step="0.01"
                           value="{{ old('items.'.$i.'.target_margin_percent', $row['target_margin_percent'] ?? $defaultMargin) }}" placeholder="{{ __('Margin %') }}"
                           title="{{ __('Target Margin (%)') }}" aria-label="{{ __('Target Margin (%)') }}"
                           class="col-span-6 md:col-span-2 rounded-xl border-emerald-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-emerald-700">
                    <span class="proposal-row-margin col-span-12 md:col-span-4 self-center text-xs font-semibold text-emerald-600 dark:text-emerald-400"></span>

                    @php($priceHistory = $historyByProduct[$row['product_id']] ?? collect())
                    @if($priceHistory->isNotEmpty())
                        <div class="col-span-12 md:col-span-4">
                            <select class="proposal-price-pick w-full rounded-xl border-slate-200 bg-white text-xs text-slate-600 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                <option value="">{{ __('Price history: pick an older price (optional)') }}</option>
                                @foreach($priceHistory as $log)
                                    <option value="{{ $log->id }}"
                                            data-price="{{ (float) $log->price }}"
                                            data-price-entered="{{ $log->price_entered }}"
                                            data-includes-tax="{{ $log->price_includes_tax ? 1 : 0 }}"
                                            data-stock="{{ $log->stock_status }}"
                                            data-eta="{{ $log->stock_eta }}"
                                            data-warranty="{{ $log->warranty_info }}">
                                        {{ $log->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }}
                                        - Rp {{ number_format((float) $log->price, 0, ',', '.') }}
                                        @if($log->supplier) ({{ $log->supplier->name }}) @endif
                                        @if($log->price_includes_tax) ({{ __('incl. VAT') }}) @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-span-12 md:col-span-2 flex items-center justify-end">
                        <x-icon-button icon="delete" type="button" title="{{ __('Delete') }}" onclick="this.closest('.proposal-row').remove(); proposalRecalc()" />
                    </div>
                </div>
            @endforeach
        </div>
        @error('items') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Total Discount (Rp)') }}</label>
            <input type="number" id="proposal-discount" name="discount" min="0" step="0.01" value="{{ old('discount', $proposal->discount ?? 0) }}"
                    class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('VAT Rate (%)') }}</label>
            <input type="number" id="proposal-tax-rate" name="tax_rate" min="0" max="100" step="0.01" value="{{ old('tax_rate', $taxRateValue) }}"
                    class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
            @error('tax_rate') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            <p class="mt-1 text-[11px] text-slate-400">{{ __('Calculated from net sales before VAT.') }}</p>
        </div>
    </div>

    <div class="flex justify-end">
        <div class="w-full md:w-72 text-sm space-y-1 rounded-xl bg-slate-50 dark:bg-slate-700/50 p-3">
            <p class="flex justify-between text-slate-500 dark:text-slate-300"><span>{{ __('Subtotal') }}</span><span id="proposal-prev-subtotal">0</span></p>
            <p class="flex justify-between text-slate-500 dark:text-slate-300"><span>{{ __('VAT') }}</span><span id="proposal-prev-tax">0</span></p>
            <p class="flex justify-between font-bold text-slate-800 dark:text-slate-100"><span>{{ __('Grand Total') }}</span><span id="proposal-prev-total">0</span></p>
            <p class="text-[11px] text-slate-400">{{ __('Auto estimate, recalculated on server.') }}</p>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Notes') }}</label>
        <textarea name="notes" rows="2" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">{{ old('notes', $proposal->notes ?? $prefillNotes ?? '') }}</textarea>
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Internal Notes') }} <span class="text-xs text-slate-400">({{ __('not shown on customer preview') }})</span></label>
        <textarea name="internal_notes" rows="2" class="w-full rounded-xl border-amber-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-amber-700">{{ old('internal_notes', $proposal->internal_notes ?? '') }}</textarea>
    </div>
</div>

{{-- Tombol hapus baris item — dipakai ulang oleh skrip untuk baris dinamis --}}
<template id="proposal-row-delete-btn">
    <div class="col-span-2 md:col-span-1">
        <x-icon-button icon="delete" type="button" title="{{ __('Delete') }}" onclick="this.closest('.proposal-row').remove(); proposalRecalc()" />
    </div>
</template>

<script>
let proposalRowIdx = {{ count($rows) }};
const proposalFmt = new Intl.NumberFormat('id-ID');
const proposalItemsRequired = {{ $itemsRequired ? 'true' : 'false' }};
let proposalDefaultMargin = parseFloat(document.getElementById('proposal-default-margin')?.value || 0) || 0;

function proposalRowHtml(i, prefill) {
    prefill = prefill || {};
    const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    const history = prefill.price_history || [];
    const historyHtml = history.length ? '<div class="col-span-12 md:col-span-4"><select class="proposal-price-pick w-full rounded-xl border-slate-200 bg-white text-xs text-slate-600 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">' +
        '<option value="">{{ __('Price history: pick an older price (optional)') }}</option>' +
        history.map(function (h) {
            return '<option value="' + (h.id || '') + '" data-price="' + (h.price || 0) + '" data-price-entered="' + (h.price_entered || '') + '" data-includes-tax="' + (h.price_includes_tax ? 1 : 0) + '" data-stock="' + esc(h.stock_status || '') + '" data-eta="' + esc(h.stock_eta || '') + '" data-warranty="' + esc(h.warranty_info || '') + '">' + esc(h.date || '') + ' - Rp ' + proposalFmt.format(Math.round(h.price || 0)) + (h.supplier ? ' (' + esc(h.supplier) + ')' : '') + (h.price_includes_tax ? ' ({{ __('incl. VAT') }})' : '') + '</option>';
        }).join('') +
        '</select></div>' : '';
    const marginValue = prefill.target_margin_percent !== undefined && prefill.target_margin_percent !== null
        ? prefill.target_margin_percent
        : proposalDefaultMargin;

    return '<input type="hidden" name="items[' + i + '][product_id]" value="' + (prefill.product_id || '') + '">' +
        '<input type="hidden" name="items[' + i + '][product_price_log_id]" value="' + esc(prefill.product_price_log_id || '') + '">' +
        '<input type="hidden" name="items[' + i + '][stock_status]" value="' + esc(prefill.stock_status || '') + '">' +
        '<input type="hidden" name="items[' + i + '][stock_eta]" value="' + esc(prefill.stock_eta || '') + '">' +
        '<input type="hidden" name="items[' + i + '][warranty_info]" value="' + esc(prefill.warranty_info || '') + '">' +
        '<input type="hidden" name="items[' + i + '][scope_of_supply]" value="' + esc(prefill.scope_of_supply || '') + '">' +
        '<input type="hidden" name="items[' + i + '][lead_time]" value="' + esc(prefill.lead_time || '') + '">' +
        '<input type="hidden" name="items[' + i + '][section]" value="' + esc(prefill.section || 'MATERIAL') + '">' +
        '<input type="text" name="items[' + i + '][description]"' + (proposalItemsRequired ? ' required' : '') + ' value="' + esc(prefill.description) + '" placeholder="{{ __('Description') }}" class="col-span-12 md:col-span-4 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">' +
        '<input type="number" name="items[' + i + '][quantity]" required min="0.01" step="0.01" value="' + (prefill.quantity || 1) + '" class="col-span-4 md:col-span-1 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">' +
        '<input type="text" name="items[' + i + '][unit]" value="' + esc(prefill.unit || 'pcs') + '" class="col-span-4 md:col-span-1 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">' +
        '<input type="number" name="items[' + i + '][unit_price]"' + (proposalItemsRequired ? ' required' : '') + ' min="0" step="0.01" value="' + (prefill.unit_price || 0) + '" class="col-span-4 md:col-span-2 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">' +
        @can('updateCost', \App\Models\Proposal::class)
        '<input type="number" name="items[' + i + '][unit_cost]" min="0" step="0.01" value="' + (prefill.unit_cost ?? '') + '" placeholder="{{ __('Cost Price (Rp)') }}" class="col-span-6 md:col-span-2 rounded-xl border-amber-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-amber-700">' +
        @else
        '<span class="col-span-6 md:col-span-2 self-center rounded-xl bg-slate-100 px-3 py-2 text-xs text-slate-500 dark:bg-slate-700 dark:text-slate-300">{{ __('Cost') }}: ' + (prefill.unit_cost !== null && prefill.unit_cost !== undefined && prefill.unit_cost !== '' ? 'Rp ' + proposalFmt.format(Math.round(prefill.unit_cost)) : '-') + '</span>' +
        @endcan
        '<input type="number" name="items[' + i + '][discount]" min="0" step="0.01" value="' + (prefill.discount || 0) + '" placeholder="{{ __('Discount') }}" class="col-span-3 md:col-span-1 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">' +
        '<span class="proposal-row-subtotal col-span-3 md:col-span-1 self-center text-right text-xs font-semibold text-slate-600 dark:text-slate-300">0</span>' +
        '<input type="number" name="items[' + i + '][target_margin_percent]" min="0" max="99.99" step="0.01" value="' + marginValue + '" placeholder="{{ __('Margin %') }}" title="{{ __('Target Margin (%)') }}" aria-label="{{ __('Target Margin (%)') }}" class="col-span-6 md:col-span-2 rounded-xl border-emerald-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-emerald-700">' +
        '<span class="proposal-row-margin col-span-12 md:col-span-4 self-center text-xs font-semibold text-emerald-600 dark:text-emerald-400"></span>' +
        historyHtml;
}

function proposalAddRow(prefill) {
    prefill = prefill || {};
    const wrap = document.getElementById('proposal-items');
    const div = document.createElement('div');
    div.className = 'proposal-row grid grid-cols-12 gap-2 p-3 rounded-xl bg-slate-50 dark:bg-slate-700/50';
    div.dataset.cost = prefill.unit_cost ?? '';
    div.innerHTML = proposalRowHtml(proposalRowIdx++, prefill);
    div.appendChild(document.getElementById('proposal-row-delete-btn').content.cloneNode(true));
    wrap.appendChild(div);
    proposalRecalc();
    return div;
}

function proposalRowCost(row) {
    const input = row.querySelector('input[name$="[unit_cost]"]');
    if (input) return parseFloat(input.value || 0) || 0;
    return parseFloat(row.dataset.cost || 0) || 0;
}

function proposalApplyMargin(row) {
    if (!row) return;
    const margin = parseFloat(row.querySelector('input[name$="[target_margin_percent]"]')?.value || 0) || 0;
    const cost = proposalRowCost(row);
    const qty = parseFloat(row.querySelector('input[name$="[quantity]"]')?.value || 0) || 0;
    const discount = parseFloat(row.querySelector('input[name$="[discount]"]')?.value || 0) || 0;
    const priceInput = row.querySelector('input[name$="[unit_price]"]');
    if (!priceInput || cost <= 0 || qty <= 0 || margin <= 0 || margin >= 100) return;

    const price = Math.ceil(((cost / (1 - margin / 100)) + (discount / qty)) * 100) / 100;
    priceInput.value = price;
}

function proposalRowMargin(row) {
    const label = row.querySelector('.proposal-row-margin');
    if (!label) return;

    const cost = proposalRowCost(row);
    const price = parseFloat(row.querySelector('input[name$="[unit_price]"]')?.value || 0) || 0;
    const qty = parseFloat(row.querySelector('input[name$="[quantity]"]')?.value || 0) || 0;
    const discount = parseFloat(row.querySelector('input[name$="[discount]"]')?.value || 0) || 0;
    const net = (price * qty) - discount;

    if (cost <= 0 || qty <= 0 || net <= 0) {
        label.textContent = @js(__('Margin')) + ': -';
        return;
    }

    const amount = net - (cost * qty);
    const pct = (amount / net) * 100;
    label.textContent = @js(__('Margin')) + ': Rp ' + proposalFmt.format(Math.round(amount)) + ' (' + pct.toFixed(2) + '%)';
}

function proposalRowSubtotal(row) {
    const num = (sel) => parseFloat(row.querySelector(sel)?.value || 0) || 0;
    return Math.max(0, num('input[name$="[quantity]"]') * num('input[name$="[unit_price]"]') - num('input[name$="[discount]"]'));
}

function proposalRecalc() {
    let subtotal = 0;
    document.querySelectorAll('#proposal-items .proposal-row').forEach(function (row) {
        const st = proposalRowSubtotal(row);
        subtotal += st;
        const label = row.querySelector('.proposal-row-subtotal');
        if (label) label.textContent = proposalFmt.format(Math.round(st));
        proposalRowMargin(row);
    });

    const disc = parseFloat(document.getElementById('proposal-discount')?.value || 0) || 0;
    const rate = parseFloat(document.getElementById('proposal-tax-rate')?.value || 0) || 0;
    const net = Math.max(0, subtotal - Math.min(disc, subtotal));
    const tax = Math.max(0, net * (rate / 100));

    document.getElementById('proposal-prev-subtotal').textContent = 'Rp ' + proposalFmt.format(Math.round(subtotal));
    document.getElementById('proposal-prev-tax').textContent = 'Rp ' + proposalFmt.format(Math.round(tax));
    document.getElementById('proposal-prev-total').textContent = 'Rp ' + proposalFmt.format(Math.round(net + tax));
}

document.getElementById('proposal-items').addEventListener('input', function (event) {
    const row = event.target.closest('.proposal-row');
    if (row && event.target.matches('input[name$="[target_margin_percent]"]')) {
        proposalApplyMargin(row);
    }
    proposalRecalc();
});
document.getElementById('proposal-items').addEventListener('change', function (event) {
    if (!event.target.classList.contains('proposal-price-pick')) return;
    const row = event.target.closest('.proposal-row');
    const option = event.target.selectedOptions[0];
    const price = parseFloat(option?.dataset.price || 0) || 0;
    if (!row || price <= 0) return;
    const cost = row.querySelector('input[name$="[unit_cost]"]');
    const sell = row.querySelector('input[name$="[unit_price]"]');
    if (cost) cost.value = price;
    row.dataset.cost = price;
    const targetMargin = parseFloat(row.querySelector('input[name$="[target_margin_percent]"]')?.value || 0) || 0;
    if (sell && (!sell.value || parseFloat(sell.value) === 0)) {
        if (targetMargin > 0) {
            proposalApplyMargin(row);
        } else {
            sell.value = price;
        }
    }
    const log = row.querySelector('input[name$="[product_price_log_id]"]');
    const stock = row.querySelector('input[name$="[stock_status]"]');
    const eta = row.querySelector('input[name$="[stock_eta]"]');
    const warranty = row.querySelector('input[name$="[warranty_info]"]');
    if (log) log.value = event.target.value;
    if (stock) stock.value = option?.dataset.stock || '';
    if (eta) eta.value = option?.dataset.eta || '';
    if (warranty) warranty.value = option?.dataset.warranty || '';
    proposalRecalc();
});
document.getElementById('proposal-discount').addEventListener('input', proposalRecalc);
document.getElementById('proposal-tax-rate').addEventListener('input', proposalRecalc);
document.getElementById('proposal-default-margin')?.addEventListener('input', function () {
    proposalDefaultMargin = parseFloat(this.value || 0) || 0;
});

// Picker katalog: ketik → pilih → tambah baris terisi.
(function () {
    const input = document.getElementById('proposal-catalog-search');
    const box = document.getElementById('proposal-catalog-results');
    let timer = null;
    input.addEventListener('input', function () {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) { box.classList.add('hidden'); box.innerHTML = ''; return; }
        timer = setTimeout(function () {
            fetch('{{ route('products.picker') }}?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then((r) => r.json())
                .then(function (items) {
                    if (!items.length) {
                        box.innerHTML = '<p class="p-3 text-sm text-slate-400">{{ __('Not in catalog, type manually.') }}</p>';
                    } else {
                        box.innerHTML = items.map(function (p) {
                            return '<button type="button" data-p=\'' + JSON.stringify(p).replace(/'/g, '&#39;') + '\' class="proposal-pick block w-full text-left p-3 hover:bg-slate-50 dark:hover:bg-slate-700/50 border-b border-slate-100 dark:border-slate-700 last:border-0">' +
                                '<span class="block text-sm font-medium text-slate-800 dark:text-slate-100">' + p.name + '</span>' +
                                '<span class="block text-xs text-slate-500">' + (p.sku || '-') + ' • Rp ' + proposalFmt.format(Math.round(p.default_price)) + ' /' + p.unit + '</span></button>';
                        }).join('');
                    }
                    box.classList.remove('hidden');
                });
        }, 250);
    });
    box.addEventListener('click', function (e) {
        const btn = e.target.closest('.proposal-pick');
        if (!btn) return;
        const p = JSON.parse(btn.dataset.p);
        const latest = (p.price_history || [])[0] || null;
        const row = proposalAddRow({
            product_id: p.id,
            description: p.name,
            quantity: 1,
            unit: p.unit,
            unit_price: p.default_price,
            unit_cost: latest ? latest.price : null,
            target_margin_percent: proposalDefaultMargin,
            product_price_log_id: latest ? latest.id : '',
            stock_status: latest ? latest.stock_status : '',
            stock_eta: latest ? latest.stock_eta : '',
            warranty_info: latest ? latest.warranty_info : '',
            price_history: p.price_history || []
        });
        if (row && (!p.default_price || parseFloat(p.default_price) <= 0)) {
            proposalApplyMargin(row);
            proposalRecalc();
        }
        input.value = '';
        box.classList.add('hidden');
        box.innerHTML = '';
        input.focus();
    });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#proposal-catalog-search') && !e.target.closest('#proposal-catalog-results')) box.classList.add('hidden');
    });
})();

proposalRecalc();
</script>
