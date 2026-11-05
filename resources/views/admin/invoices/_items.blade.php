@php
    $invoiceRows = old('items', $items ?? []);
    if (empty($invoiceRows)) {
        $invoiceRows = [['description' => '', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => 0, 'discount' => 0]];
    }
@endphp

<div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('Invoice Items') }}</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Optional. If items are filled, the invoice total is calculated automatically.') }}</p>
        </div>
        <x-icon-button icon="add" type="button" label="{{ __('Item') }}" title="{{ __('Add Item') }}"
                       onclick="invoiceAddRow()" />
    </div>

    <div class="space-y-2">
        <div id="invoice-items" class="space-y-2">
            @foreach($invoiceRows as $i => $row)
                <div class="invoice-row grid grid-cols-12 gap-2 rounded-xl bg-slate-50 p-3 dark:bg-slate-700/50">
                    <input type="text" name="items[{{ $i }}][description]" value="{{ $row['description'] ?? '' }}"
                           placeholder="{{ __('Description') }}"
                           class="col-span-12 md:col-span-4 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                    <input type="number" name="items[{{ $i }}][quantity]" min="0.01" step="0.01" value="{{ $row['quantity'] ?? 1 }}"
                           placeholder="{{ __('Qty') }}"
                           class="col-span-4 md:col-span-2 rounded-lg border-slate-300 text-sm text-right focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                    <input type="text" name="items[{{ $i }}][unit]" value="{{ $row['unit'] ?? 'pcs' }}"
                           placeholder="{{ __('Unit') }}"
                           class="col-span-4 md:col-span-1 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                    <input type="number" name="items[{{ $i }}][unit_price]" min="0" step="0.01" value="{{ $row['unit_price'] ?? 0 }}"
                           placeholder="{{ __('Price') }}"
                           class="col-span-4 md:col-span-2 rounded-lg border-slate-300 text-sm text-right focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                    <input type="number" name="items[{{ $i }}][discount]" min="0" step="0.01" value="{{ $row['discount'] ?? 0 }}"
                           placeholder="{{ __('Discount') }}"
                           class="col-span-7 md:col-span-1 rounded-lg border-slate-300 text-sm text-right focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                    <span class="invoice-row-subtotal col-span-3 md:col-span-1 self-center text-right text-xs font-semibold text-slate-600 dark:text-slate-300">0</span>
                    <div class="col-span-2 md:col-span-1">
                        <x-icon-button icon="delete" type="button" title="{{ __('Delete') }}"
                                       onclick="this.closest('.invoice-row').remove(); invoiceRecalc()" />
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @error('items') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
    @error('items.*.description') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
</div>

<template id="invoice-row-template">
    <div class="invoice-row grid grid-cols-12 gap-2 rounded-xl bg-slate-50 p-3 dark:bg-slate-700/50">
        <input type="text" data-field="description" placeholder="{{ __('Description') }}"
               class="col-span-12 md:col-span-4 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
        <input type="number" data-field="quantity" min="0.01" step="0.01" value="1" placeholder="{{ __('Qty') }}"
               class="col-span-4 md:col-span-2 rounded-lg border-slate-300 text-sm text-right focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
        <input type="text" data-field="unit" value="pcs" placeholder="{{ __('Unit') }}"
               class="col-span-4 md:col-span-1 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
        <input type="number" data-field="unit_price" min="0" step="0.01" value="0" placeholder="{{ __('Price') }}"
               class="col-span-4 md:col-span-2 rounded-lg border-slate-300 text-sm text-right focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
        <input type="number" data-field="discount" min="0" step="0.01" value="0" placeholder="{{ __('Discount') }}"
               class="col-span-7 md:col-span-1 rounded-lg border-slate-300 text-sm text-right focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
        <span class="invoice-row-subtotal col-span-3 md:col-span-1 self-center text-right text-xs font-semibold text-slate-600 dark:text-slate-300">0</span>
        <div class="col-span-2 md:col-span-1">
            <x-icon-button icon="delete" type="button" title="{{ __('Delete') }}"
                           onclick="this.closest('.invoice-row').remove(); invoiceRecalc()" />
        </div>
    </div>
</template>

<script>
let invoiceRowIndex = {{ count($invoiceRows) }};
const invoiceNumberFormat = new Intl.NumberFormat('id-ID');

function invoiceAddRow() {
    const template = document.getElementById('invoice-row-template');
    const row = template.content.firstElementChild.cloneNode(true);
    row.querySelectorAll('[data-field]').forEach(function (input) {
        input.name = 'items[' + invoiceRowIndex + '][' + input.dataset.field + ']';
    });
    invoiceRowIndex++;
    document.getElementById('invoice-items').appendChild(row);
    invoiceRecalc();
}

function invoiceRowSubtotal(row) {
    const value = (name) => parseFloat(row.querySelector('[name$="[' + name + ']"]')?.value || 0) || 0;
    return Math.max(0, value('quantity') * value('unit_price') - value('discount'));
}

function invoiceRecalc() {
    let subtotal = 0;
    let hasItems = false;
    document.querySelectorAll('#invoice-items .invoice-row').forEach(function (row) {
        const description = (row.querySelector('[name$="[description]"]')?.value || '').trim();
        const unitPrice = parseFloat(row.querySelector('[name$="[unit_price]"]')?.value || 0) || 0;
        if (description !== '' || unitPrice > 0) hasItems = true;

        const amount = invoiceRowSubtotal(row);
        subtotal += amount;
        const label = row.querySelector('.invoice-row-subtotal');
        if (label) label.textContent = invoiceNumberFormat.format(Math.round(amount));
    });

    const discount = parseFloat(document.getElementById('invoice-discount')?.value || 0) || 0;
    const tax = parseFloat(document.getElementById('invoice-tax')?.value || 0) || 0;
    const total = Math.max(0, subtotal - Math.min(discount, subtotal) + Math.max(0, tax));

    const subtotalLabel = document.getElementById('invoice-subtotal-preview');
    if (subtotalLabel) subtotalLabel.textContent = 'Rp ' + invoiceNumberFormat.format(Math.round(subtotal));

    const amountInput = document.getElementById('invoice-amount');
    if (amountInput && hasItems) amountInput.value = Math.round(total * 100) / 100;
}

document.getElementById('invoice-items')?.addEventListener('input', invoiceRecalc);
document.getElementById('invoice-discount')?.addEventListener('input', invoiceRecalc);
document.getElementById('invoice-tax')?.addEventListener('input', invoiceRecalc);
invoiceRecalc();
</script>
