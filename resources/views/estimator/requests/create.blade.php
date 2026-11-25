<x-app-layout>
    @php
        $productOptions = $products->map(fn ($p) => [
            'id' => (string) $p->id,
            'name' => $p->name,
            'sku' => $p->sku ?? '',
            'unit' => $p->unit ?: 'pcs',
            'label' => $p->name.($p->sku ? ' · '.$p->sku : ''),
        ])->values()->all();
        $rowsSource = old('items');
        if (! is_array($rowsSource)) {
            $rowsSource = ! empty($prefillItems)
                ? $prefillItems
                : [['product_id' => '', 'description' => '', 'quantity' => 1, 'unit' => 'pcs', 'notes' => '']];
        }

        $initialRows = collect($rowsSource)
            ->map(function ($row) use ($productOptions) {
                $pid = isset($row['product_id']) && $row['product_id'] !== '' ? (string) $row['product_id'] : '';
                $matched = $pid !== '' ? collect($productOptions)->firstWhere('id', $pid) : null;

                return [
                    'product_id' => $pid,
                    'product_label' => $matched['name'] ?? '',
                    'product_sku' => $matched['sku'] ?? '',
                    'open' => false,
                    'browse' => false,
                    'active' => 0,
                    'description' => $row['description'] ?? '',
                    'quantity' => $row['quantity'] ?? 1,
                    'unit' => $row['unit'] ?? 'pcs',
                    'notes' => $row['notes'] ?? '',
                ];
            })
            ->values()
            ->all();
    @endphp

    <div>
        <div class="mb-6 flex flex-col items-start gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('New Product Price Request') }}</h1>
            </div>
            <div class="flex w-full justify-end sm:w-auto">
                <x-back-button :href="$backUrl" />
            </div>
        </div>

        @if($errors->any())
            <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300">
                {{ __('Please check the request data below.') }}
            </div>
        @endif

        <form action="{{ route('estimator.requests.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            <input type="hidden" name="lead_id" value="{{ old('lead_id', $presetLeadId) }}">
            <input type="hidden" name="project_id" value="{{ old('project_id', $presetProjectId) }}">
            <input type="hidden" name="proposal_id" value="{{ old('proposal_id', $presetProposalId) }}">

            <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-600 dark:text-slate-300">{{ __('Customer (PT)') }} <span class="text-rose-500">*</span></label>
                        @include('sales._customer-picker', [
                            'name' => 'customer_id',
                            'options' => $customers,
                            'selected' => old('customer_id', $presetCustomerId),
                            'placeholder' => __('Search customer...'),
                            'required' => true,
                            'hint' => __('Choose the PT this product price request is for.'),
                        ])
                        @error('customer_id') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="estimator-attachment" class="mb-1 block text-sm font-medium text-slate-600 dark:text-slate-300">{{ __('Quotation / BOQ File (optional)') }}</label>
                        <input id="estimator-attachment" type="file" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.zip"
                               class="h-11 w-full rounded-xl border-slate-300 text-base text-slate-900 placeholder:text-slate-400 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-100 dark:placeholder:text-slate-500">
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Attach the quotation/BOQ if available. Max 10 MB.') }}</p>
                        @error('attachment') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="estimator-notes" class="mb-1 block text-sm font-medium text-slate-600 dark:text-slate-300">{{ __('Notes') }}</label>
                        <textarea id="estimator-notes" name="notes" rows="3" maxlength="2000" placeholder="{{ __('Project context, target price, or anything Estimator should know.') }}"
                                  class="w-full rounded-xl border-slate-300 text-base text-slate-900 placeholder:text-slate-400 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-100 dark:placeholder:text-slate-500">{{ old('notes') }}</textarea>
                        @error('notes') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90"
                 x-data="{
                     products: @js($productOptions),
                     rows: @js($initialRows),
                     filteredProducts(row) {
                         const q = row.browse ? '' : (row.description || '').toLowerCase().trim();
                         return this.products.filter(p => !q || p.label.toLowerCase().includes(q)).slice(0, 30);
                     },
                     pickProduct(row, product) {
                         row.product_id = String(product.id);
                         row.product_label = product.name;
                         row.product_sku = product.sku || '';
                         row.description = product.name;
                         row.unit = product.unit || 'pcs';
                         row.open = false;
                         row.browse = false;
                         row.active = 0;
                     },
                     moveActive(index, row, step) {
                         const list = this.filteredProducts(row);
                         if (!list.length) return;
                         row.open = true;
                         row.active = Math.max(0, Math.min((row.active ?? 0) + step, list.length - 1));
                         this.$nextTick(() => {
                             const menu = document.querySelector(`[data-product-menu='${index}']`);
                             menu?.querySelector(`[data-option-index='${row.active}']`)?.scrollIntoView({ block: 'nearest' });
                         });
                     },
                     pickActive(index, row) {
                         if (!row.open) return;
                         const list = this.filteredProducts(row);
                         if (!list.length) return;
                         this.pickProduct(row, list[Math.max(0, Math.min(row.active ?? 0, list.length - 1))]);
                     },
                     unlinkProduct(row) {
                         row.product_id = '';
                         row.product_label = '';
                         row.product_sku = '';
                         row.open = false;
                     },
                     addRow() {
                         this.rows.push({ product_id: '', product_label: '', product_sku: '', open: false, browse: false, active: 0, description: '', quantity: 1, unit: 'pcs', notes: '' });
                     }
                 }">
                <div class="mb-4">
                    <h2 class="font-semibold text-content-primary">{{ __('Requested Items') }}</h2>
                    <p class="mt-0.5 text-xs text-content-tertiary">{{ __('Add at least one product or describe the item manually.') }}</p>
                </div>

                @if(! empty($prefillItems) && ! old('items'))
                    <p class="mb-3 rounded-xl border border-accent-200 bg-accent-50 px-3 py-2 text-xs leading-5 text-accent-800 dark:border-accent-800/60 dark:bg-accent-950/30 dark:text-accent-200">
                        {{ __('Items are loaded from the selected quotation. Remove any item you do not want to request.') }}
                    </p>
                @endif

                <div class="space-y-3">
                    <template x-for="(row, index) in rows" :key="index">
                        <div class="space-y-3 rounded-xl border border-line bg-surface-elevated p-4">
                            <div class="flex items-center justify-between border-b border-line pb-2">
                                <span class="text-xs font-semibold uppercase tracking-wider text-content-secondary" x-text="`{{ __('Item') }} #${index + 1}`"></span>
                                <x-icon-button icon="delete" type="button" title="{{ __('Remove item') }}" @click="rows.splice(index, 1)" />
                            </div>

                            <div class="grid grid-cols-12 gap-3">
                                <div class="relative col-span-12 md:col-span-8">
                                    <label class="mb-1.5 block text-xs font-medium text-content-secondary">
                                        {{ __('What do you need?') }} <span class="text-rose-500">*</span>
                                    </label>
                                    <input type="hidden" :name="`items[${index}][product_id]`" :value="row.product_id">
                                    <div class="relative">
                                        <input type="text" x-model="row.description" :name="`items[${index}][description]`"
                                               maxlength="255" required
                                               @focus="row.browse = false; row.open = (row.description || '').length >= 3"
                                               @input="row.open = (row.description || '').length >= 3; row.browse = false; row.active = 0"
                                               @keydown.arrow-down.prevent="moveActive(index, row, row.open ? 1 : 0)"
                                               @keydown.arrow-up.prevent="moveActive(index, row, row.open ? -1 : 0)"
                                               @keydown.enter="if (row.open) { $event.preventDefault(); pickActive(index, row); }"
                                               @keydown.escape="row.open = false"
                                               @keydown.tab="row.open = false"
                                               @blur="setTimeout(() => row.open = false, 150)"
                                               placeholder="{{ __('Product name + main specs (e.g. IP PABX 32 Extension + 32-user license)') }}"
                                               autocomplete="off"
                                               class="h-10 w-full rounded-xl border-slate-300 pr-40 text-sm text-content-primary placeholder:text-content-tertiary focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                                        <button type="button" @mousedown.prevent="row.open = true; row.browse = true; row.active = 0"
                                                class="absolute right-1.5 top-1/2 inline-flex h-7 -translate-y-1/2 items-center gap-1 rounded-lg border border-line bg-surface px-2 text-xs font-semibold text-content-secondary transition hover:border-accent-500 hover:text-accent-600 active:scale-95">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
                                            {{ __('Pick from catalog') }}
                                        </button>
                                        <div x-show="row.open" x-cloak :data-product-menu="index"
                                             class="absolute z-20 mt-1 max-h-56 w-full divide-y divide-line overflow-y-auto rounded-xl border border-line bg-surface shadow-lg">
                                            <template x-for="(product, productIndex) in filteredProducts(row)" :key="product.id">
                                                <button type="button" @mousedown.prevent="pickProduct(row, product)"
                                                        @mouseenter="row.active = productIndex"
                                                        :data-option-index="productIndex"
                                                        :class="productIndex === row.active ? 'bg-accent-50 dark:bg-accent-500/10' : ''"
                                                        class="flex w-full items-center justify-between gap-2 px-3.5 py-2.5 text-left text-sm text-content-primary transition hover:bg-accent-50 dark:hover:bg-accent-500/10">
                                                    <span class="truncate font-medium" x-text="product.name"></span>
                                                    <span class="shrink-0 text-xs text-content-tertiary" x-text="product.sku ? `${product.sku} · ${product.unit}` : product.unit"></span>
                                                </button>
                                            </template>
                                            <p x-show="filteredProducts(row).length === 0" class="px-3.5 py-2.5 text-sm text-content-tertiary">{{ __('No matching catalog product - fill description manually.') }}</p>
                                        </div>
                                    </div>

                                    <p x-show="row.product_id" x-cloak class="mt-1.5 inline-flex max-w-full items-center gap-1.5 rounded-full border border-line bg-surface-elevated px-2.5 py-1 text-xs text-content-secondary">
                                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1.5 1.5" /><path d="M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7L13 18" /></svg>
                                        <span class="truncate">
                                            {{ __('Linked to catalog') }}:
                                            <span class="font-semibold text-content-primary" x-text="row.product_label"></span><span x-show="row.product_sku" class="text-content-tertiary" x-text="` · ${row.product_sku}`"></span>
                                        </span>
                                        <button type="button" @click="unlinkProduct(row)" title="{{ __('Unlink catalog product') }}" aria-label="{{ __('Unlink catalog product') }}"
                                                class="ml-1 rounded p-0.5 text-base leading-none text-content-tertiary transition hover:text-rose-500">&times;</button>
                                    </p>
                                </div>

                                <div class="col-span-4 md:col-span-2">
                                    <label class="mb-1.5 block text-xs font-medium text-content-secondary">{{ __('Qty') }} <span class="text-rose-500">*</span></label>
                                    <input type="number" :name="`items[${index}][quantity]`" x-model="row.quantity" min="0.01" step="0.01" required
                                           placeholder="{{ __('Qty') }}"
                                           class="h-10 w-full rounded-xl border-slate-300 text-sm tabular-nums text-content-primary placeholder:text-content-tertiary focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                                </div>
                                <div class="col-span-8 md:col-span-2">
                                    <label class="mb-1.5 block text-xs font-medium text-content-secondary">{{ __('Unit') }}</label>
                                    <input type="text" :name="`items[${index}][unit]`" x-model="row.unit" maxlength="16"
                                           placeholder="{{ __('Unit') }}"
                                           class="h-10 w-full rounded-xl border-slate-300 text-sm text-content-primary placeholder:text-content-tertiary focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                                </div>
                                <div class="col-span-12">
                                    <label class="mb-1.5 block text-xs font-medium text-content-secondary">{{ __('Item Note (Optional)') }}</label>
                                    <input type="text" :name="`items[${index}][notes]`" x-model="row.notes" maxlength="255"
                                           placeholder="{{ __('Target vendor, color, warranty, or delivery terms...') }}"
                                           class="h-10 w-full rounded-xl border-slate-300 text-sm text-content-primary placeholder:text-content-tertiary focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <button type="button" @click="addRow()"
                        class="mt-3 flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-dashed border-line px-4 text-sm font-medium text-content-secondary transition-colors hover:border-accent-500 hover:bg-surface-elevated hover:text-content-primary active:scale-95">
                    <span>+ {{ __('Add Another Item') }}</span>
                </button>
                @error('items') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                @foreach($errors->keys() as $errorKey)
                    @if(str_starts_with($errorKey, 'items.'))
                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $errors->first($errorKey) }}</p>
                    @endif
                @endforeach
            </div>

            <div class="flex items-center justify-end gap-2">
                <x-secondary-button as="a" :href="$backUrl">{{ __('Cancel') }}</x-secondary-button>
                <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
