<x-app-layout>
    <div class="max-w-6xl">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Create Supplier PO') }}</h1>
                <p class="text-slate-500 mt-1">{{ __('Pick a quotation, split its items into procurement components, then order them from one supplier.') }}</p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('admin.supplier-pos.index') }}" title="{{ __('Back') }}" />
        </div>

        @if($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-700 dark:text-red-300">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('admin.supplier-pos.store') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Supplier') }} <span class="text-red-500">*</span></label>
                        <select name="supplier_id" required
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                            <option value="">{{ __('Select supplier') }}</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((int) old('supplier_id', $selectedSupplierId) === $supplier->id)>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        @error('supplier_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Quotation') }} <span class="text-red-500">*</span></label>
                        <select name="proposal_id" id="proposal_id" required
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                            <option value="">{{ __('Select quotation') }}</option>
                            @foreach($proposals as $proposal)
                                <option value="{{ $proposal->id }}" @selected((int) old('proposal_id', $selectedProposalId) === $proposal->id)>
                                    {{ $proposal->proposal_number }} — {{ $proposal->customer }}
                                </option>
                            @endforeach
                        </select>
                        @error('proposal_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        @if($proposals->isEmpty())
                            <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">{{ __('No quotations are ready for Purchasing yet (accepted + customer PO uploaded).') }}</p>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Issue Date') }} <span class="text-red-500">*</span></label>
                        <input type="date" name="issue_date" required value="{{ old('issue_date', now()->toDateString()) }}"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                        @error('issue_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Expected Delivery') }}</label>
                        <input type="date" name="expected_delivery_date" value="{{ old('expected_delivery_date') }}"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                        @error('expected_delivery_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Notes') }}</label>
                    <textarea name="notes" rows="2"
                              class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="font-semibold text-slate-700 dark:text-slate-200">{{ __('Procurement Items') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Items are copied from the quotation as a starting point. Split one quotation item into several components and order them from different suppliers using separate POs. The quotation itself is never modified.') }}</p>
                    </div>
                    <button type="button" id="add-component"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 transition hover:bg-green-600 hover:text-white hover:border-green-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        {{ __('Add Component') }}
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-slate-500 border-b border-slate-200 dark:border-slate-700">
                                <th class="py-2 pr-2">{{ __('Item') }}</th>
                                <th class="py-2 pr-2">{{ __('Part Number') }}</th>
                                <th class="py-2 pr-2 w-20">{{ __('Unit') }}</th>
                                <th class="py-2 pr-2 w-24 text-right">{{ __('Qty') }}</th>
                                <th class="py-2 pr-2 w-32 text-right">{{ __('Unit Price') }}</th>
                                <th class="py-2 pr-2 w-32 text-right">{{ __('Subtotal') }}</th>
                                <th class="py-2 w-20"></th>
                            </tr>
                        </thead>
                        <tbody id="spo-items" class="divide-y divide-slate-100 dark:divide-slate-700/60"></tbody>
                    </table>
                </div>
                <p id="empty-items" class="text-sm text-slate-400 text-center py-4">{{ __('Select a quotation to load its items, or add a component manually.') }}</p>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
                <div class="flex justify-end">
                    <div class="w-full md:w-80 space-y-2 text-sm">
                        <p class="flex items-center justify-between gap-3 text-slate-500">
                            <span>{{ __('Subtotal') }}</span>
                            <span id="spo-subtotal" class="font-mono text-slate-800 dark:text-slate-100">0</span>
                        </p>
                        <div class="flex items-center justify-between gap-3 text-slate-500">
                            <label for="discount">{{ __('Discount') }}</label>
                            <input id="discount" type="number" name="discount" min="0" step="0.01" value="{{ old('discount', 0) }}"
                                   class="w-40 rounded-xl border-slate-300 text-right text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                        </div>
                        <div class="flex items-center justify-between gap-3 text-slate-500">
                            <label for="tax">{{ __('Tax') }}</label>
                            <input id="tax" type="number" name="tax" min="0" step="0.01" value="{{ old('tax', 0) }}"
                                   class="w-40 rounded-xl border-slate-300 text-right text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                        </div>
                        <p class="flex items-center justify-between gap-3 border-t border-slate-200 dark:border-slate-700 pt-2 font-bold text-slate-800 dark:text-slate-100">
                            <span>{{ __('Grand Total') }}</span>
                            <span id="spo-grand-total" class="font-mono">0</span>
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit"
                        class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                    {{ __('Save') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </button>
                <a href="{{ route('admin.supplier-pos.index') }}"
                   class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                    {{ __('Cancel') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </a>
            </div>
        </form>
    </div>

    <template id="spo-row-template">
        <tr class="align-top">
            <td class="py-2 pr-2">
                <input type="hidden" data-field="proposal_item_id">
                <input type="hidden" data-field="product_id">
                <input type="text" data-field="item_name" maxlength="255" placeholder="{{ __('Item name') }}"
                       class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                <input type="text" data-field="description" maxlength="2000" placeholder="{{ __('Description') }}"
                       class="mt-1 w-full rounded-lg border-slate-300 text-xs focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                <p class="quotation-ref hidden mt-1 text-xs text-slate-400"></p>
            </td>
            <td class="py-2 pr-2">
                <input type="text" data-field="part_number" maxlength="120"
                       class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
            </td>
            <td class="py-2 pr-2">
                <input type="text" data-field="unit" maxlength="16" value="pcs"
                       class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
            </td>
            <td class="py-2 pr-2">
                <input type="number" data-field="quantity_ordered" min="0.01" step="0.01" value="1"
                       class="w-full rounded-lg border-slate-300 text-right text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
            </td>
            <td class="py-2 pr-2">
                <input type="number" data-field="unit_price" min="0" step="0.01" value="0"
                       class="w-full rounded-lg border-slate-300 text-right text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
            </td>
            <td class="py-2 pr-2 text-right font-mono text-slate-700 dark:text-slate-200">
                <span class="row-subtotal">0</span>
            </td>
            <td class="py-2">
                <div class="flex items-center justify-end gap-1">
                    <button type="button" class="split-row rounded-lg border border-slate-300 dark:border-slate-600 p-1.5 text-slate-500 transition hover:border-violet-600 hover:bg-violet-600 hover:text-white" title="{{ __('Split into another component') }}">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </button>
                    <button type="button" class="remove-row rounded-lg border border-slate-300 dark:border-slate-600 p-1.5 text-red-600 transition hover:border-red-600 hover:bg-red-600 hover:text-white" title="{{ __('Remove') }}">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </td>
        </tr>
    </template>

    <script>
    (function () {
        const quotationData = @json($quotationData);
        const template = document.getElementById('spo-row-template');
        const tbody = document.getElementById('spo-items');
        const emptyHint = document.getElementById('empty-items');
        const proposalSelect = document.getElementById('proposal_id');
        const discountInput = document.getElementById('discount');
        const taxInput = document.getElementById('tax');
        let rowSeq = 0;

        const numberFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

        function money(value) {
            return numberFormat.format(Number(value) || 0);
        }

        function updateTotals() {
            let subtotal = 0;

            tbody.querySelectorAll('tr').forEach(function (row) {
                const qty = Number(row.querySelector('[data-field="quantity_ordered"]').value) || 0;
                const price = Number(row.querySelector('[data-field="unit_price"]').value) || 0;
                const lineTotal = Math.round(qty * price * 100) / 100;
                row.querySelector('.row-subtotal').textContent = money(lineTotal);
                subtotal += lineTotal;
            });

            const discount = Math.min(Number(discountInput.value) || 0, subtotal);
            const tax = Math.max(Number(taxInput.value) || 0, 0);

            document.getElementById('spo-subtotal').textContent = money(subtotal);
            document.getElementById('spo-grand-total').textContent = money(subtotal - discount + tax);
            emptyHint.classList.toggle('hidden', tbody.querySelectorAll('tr').length > 0);
        }

        function addRow(data) {
            data = data || {};

            const idx = rowSeq++;
            const fragment = template.content.cloneNode(true);
            const row = fragment.querySelector('tr');

            row.querySelectorAll('[data-field]').forEach(function (input) {
                input.name = 'items[' + idx + '][' + input.dataset.field + ']';
                const value = data[input.dataset.field];
                if (value !== undefined && value !== null) {
                    input.value = value;
                }
                input.addEventListener('input', updateTotals);
            });

            if (data.quotation_ref) {
                const ref = row.querySelector('.quotation-ref');
                ref.textContent = data.quotation_ref;
                ref.classList.remove('hidden');
            }

            row.querySelector('.remove-row').addEventListener('click', function () {
                row.remove();
                updateTotals();
            });

            // "Split": salin baris (termasuk referensi item quotation) supaya
            // satu item quotation bisa jadi beberapa komponen pengadaan.
            row.querySelector('.split-row').addEventListener('click', function () {
                const copy = { quotation_ref: data.quotation_ref || '' };
                row.querySelectorAll('[data-field]').forEach(function (input) {
                    copy[input.dataset.field] = input.value;
                });
                addRow(copy);
                updateTotals();
            });

            tbody.appendChild(row);
            updateTotals();
        }

        function loadQuotation(proposalId) {
            tbody.innerHTML = '';
            const data = quotationData[proposalId];

            if (!data) {
                updateTotals();
                return;
            }

            data.items.forEach(function (item) {
                addRow({
                    proposal_item_id: item.id,
                    product_id: item.product_id || '',
                    item_name: item.name || '',
                    description: item.name || '',
                    part_number: item.part_number || '',
                    unit: item.unit || 'pcs',
                    quantity_ordered: item.quantity || 1,
                    unit_price: item.unit_price || 0,
                    quotation_ref: @json(__('From quotation')) + ': ' + (item.name || ''),
                });
            });

            updateTotals();
        }

        proposalSelect.addEventListener('change', function () {
            loadQuotation(this.value);
        });

        document.getElementById('add-component').addEventListener('click', function () {
            addRow({ unit: 'pcs', quantity_ordered: 1, unit_price: 0 });
        });

        [discountInput, taxInput].forEach(function (input) {
            input.addEventListener('input', updateTotals);
        });

        const initialProposal = @json($selectedProposalId);
        if (initialProposal) {
            loadQuotation(String(initialProposal));
        } else {
            updateTotals();
        }
    })();
    </script>
</x-app-layout>
