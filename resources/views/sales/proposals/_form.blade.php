<div class="space-y-4">
    <input type="hidden" name="lead_id" value="{{ $lead->id ?? $proposal->lead_id }}">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Tanggal Proposal') }} <span class="text-red-500">*</span></label>
            <input type="date" name="proposal_date" required value="{{ old('proposal_date', isset($proposal) ? $proposal->proposal_date?->format('Y-m-d') : now()->format('Y-m-d')) }}"
                    class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Berlaku Sampai') }}</label>
            <input type="date" name="valid_until" value="{{ old('valid_until', isset($proposal) ? $proposal->valid_until?->format('Y-m-d') : '') }}"
                    class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
            @error('valid_until') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <div class="flex items-center justify-between mb-2">
            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Item') }} <span class="text-red-500">*</span></label>
            <x-icon-button icon="add" label="{{ __('+ Item') }}" title="{{ __('+ Item') }}" type="button" onclick="proposalAddRow()" />
        </div>
        <div class="relative mb-2">
            <input type="text" id="proposal-catalog-search" autocomplete="off" placeholder="{{ __('Cari di katalog...') }}"
                    class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
            <div id="proposal-catalog-results" class="absolute z-10 w-full mt-1 hidden max-h-56 overflow-auto rounded-xl border border-slate-200 bg-white shadow-lg dark:bg-slate-800 dark:border-slate-600"></div>
        </div>
        <div id="proposal-items" class="space-y-2">
            @php $rows = old('items', isset($proposal) ? $proposal->items->map(fn ($i) => ['product_id' => $i->product_id, 'description' => $i->description, 'quantity' => $i->quantity, 'unit' => $i->unit, 'unit_price' => $i->unit_price, 'discount' => $i->discount])->all() : [['product_id' => null, 'description' => '', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => 0, 'discount' => 0]]); @endphp
            @foreach($rows as $i => $row)
                <div class="proposal-row grid grid-cols-12 gap-2 p-3 rounded-xl bg-slate-50 dark:bg-slate-700/50">
                    <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $row['product_id'] }}">
                    <input type="text" name="items[{{ $i }}][description]" required value="{{ $row['description'] }}" placeholder="{{ __('Deskripsi') }}"
                            class="col-span-12 md:col-span-4 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                    <input type="number" name="items[{{ $i }}][quantity]" required min="0.01" step="0.01" value="{{ $row['quantity'] }}" placeholder="{{ __('Qty') }}"
                            class="col-span-4 md:col-span-2 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                    <input type="text" name="items[{{ $i }}][unit]" value="{{ $row['unit'] }}" placeholder="{{ __('Satuan') }}"
                            class="col-span-4 md:col-span-1 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                    <input type="number" name="items[{{ $i }}][unit_price]" required min="0" step="0.01" value="{{ $row['unit_price'] }}" placeholder="{{ __('Harga') }}"
                            class="col-span-4 md:col-span-2 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                    <input type="number" name="items[{{ $i }}][discount]" min="0" step="0.01" value="{{ $row['discount'] }}" placeholder="{{ __('Diskon') }}"
                            class="col-span-7 md:col-span-1 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                    <span class="proposal-row-subtotal col-span-3 md:col-span-1 self-center text-right text-xs font-semibold text-slate-600 dark:text-slate-300">0</span>
                    <div class="col-span-2 md:col-span-1">
                        <x-icon-button icon="delete" type="button" title="{{ __('Hapus') }}" onclick="this.closest('.proposal-row').remove(); proposalRecalc()" />
                    </div>
                </div>
            @endforeach
        </div>
        @error('items') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Diskon Total (Rp)') }}</label>
            <input type="number" id="proposal-discount" name="discount" min="0" step="0.01" value="{{ old('discount', $proposal->discount ?? 0) }}"
                    class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Pajak (Rp)') }}</label>
            <input type="number" id="proposal-tax" name="tax" min="0" step="0.01" value="{{ old('tax', $proposal->tax ?? 0) }}"
                    class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
        </div>
    </div>

    <div class="flex justify-end">
        <div class="w-full md:w-64 text-sm space-y-1 rounded-xl bg-slate-50 dark:bg-slate-700/50 p-3">
            <p class="flex justify-between text-slate-500 dark:text-slate-300"><span>{{ __('Subtotal') }}</span><span id="proposal-prev-subtotal">0</span></p>
            <p class="flex justify-between font-bold text-slate-800 dark:text-slate-100"><span>{{ __('Grand Total') }}</span><span id="proposal-prev-total">0</span></p>
            <p class="text-[11px] text-slate-400">{{ __('Estimasi otomatis, dihitung ulang di server.') }}</p>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Catatan') }}</label>
        <textarea name="notes" rows="2" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">{{ old('notes', $proposal->notes ?? $prefillNotes ?? '') }}</textarea>
    </div>
</div>

{{-- Tombol hapus baris item — dipakai ulang oleh skrip untuk baris dinamis --}}
<template id="proposal-row-delete-btn">
    <div class="col-span-2 md:col-span-1">
        <x-icon-button icon="delete" type="button" title="{{ __('Hapus') }}" onclick="this.closest('.proposal-row').remove(); proposalRecalc()" />
    </div>
</template>

<script>
let proposalRowIdx = {{ count($rows) }};
const proposalFmt = new Intl.NumberFormat('id-ID');

function proposalRowHtml(i, prefill) {
    prefill = prefill || {};
    const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    return '<input type="hidden" name="items[' + i + '][product_id]" value="' + (prefill.product_id || '') + '">' +
        '<input type="text" name="items[' + i + '][description]" required value="' + esc(prefill.description) + '" placeholder="{{ __('Deskripsi') }}" class="col-span-12 md:col-span-4 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">' +
        '<input type="number" name="items[' + i + '][quantity]" required min="0.01" step="0.01" value="' + (prefill.quantity || 1) + '" class="col-span-4 md:col-span-2 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">' +
        '<input type="text" name="items[' + i + '][unit]" value="' + esc(prefill.unit || 'pcs') + '" class="col-span-4 md:col-span-1 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">' +
        '<input type="number" name="items[' + i + '][unit_price]" required min="0" step="0.01" value="' + (prefill.unit_price || 0) + '" class="col-span-4 md:col-span-2 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">' +
        '<input type="number" name="items[' + i + '][discount]" min="0" step="0.01" value="0" class="col-span-7 md:col-span-1 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">' +
        '<span class="proposal-row-subtotal col-span-3 md:col-span-1 self-center text-right text-xs font-semibold text-slate-600 dark:text-slate-300">0</span>';
}

function proposalAddRow(prefill) {
    const wrap = document.getElementById('proposal-items');
    const div = document.createElement('div');
    div.className = 'proposal-row grid grid-cols-12 gap-2 p-3 rounded-xl bg-slate-50 dark:bg-slate-700/50';
    div.innerHTML = proposalRowHtml(proposalRowIdx++, prefill);
    div.appendChild(document.getElementById('proposal-row-delete-btn').content.cloneNode(true));
    wrap.appendChild(div);
    proposalRecalc();
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
    });
    const disc = parseFloat(document.getElementById('proposal-discount')?.value || 0) || 0;
    const tax = parseFloat(document.getElementById('proposal-tax')?.value || 0) || 0;
    document.getElementById('proposal-prev-subtotal').textContent = 'Rp ' + proposalFmt.format(Math.round(subtotal));
    document.getElementById('proposal-prev-total').textContent = 'Rp ' + proposalFmt.format(Math.round(Math.max(0, subtotal - Math.min(disc, subtotal) + Math.max(0, tax))));
}

document.getElementById('proposal-items').addEventListener('input', proposalRecalc);
document.getElementById('proposal-discount').addEventListener('input', proposalRecalc);
document.getElementById('proposal-tax').addEventListener('input', proposalRecalc);

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
                        box.innerHTML = '<p class="p-3 text-sm text-slate-400">{{ __('Tidak ketemu di katalog, ketik manual.') }}</p>';
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
        proposalAddRow({ product_id: p.id, description: p.name, quantity: 1, unit: p.unit, unit_price: p.default_price });
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
