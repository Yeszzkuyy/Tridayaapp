<x-app-layout>
    <div class="mx-auto max-w-4xl">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('New Product Price Request') }}</h1>
            </div>
            <x-back-button :href="$backUrl" />
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
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Customer (PT)') }} <span class="text-rose-500">*</span></label>
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
                        <label for="estimator-attachment" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Quotation / BOQ File (optional)') }}</label>
                        <input id="estimator-attachment" type="file" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.zip"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                        <p class="mt-1 text-[11px] text-slate-400">{{ __('Attach the quotation/BOQ if available. Max 10 MB.') }}</p>
                        @error('attachment') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="estimator-notes" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Notes') }}</label>
                        <textarea id="estimator-notes" name="notes" rows="3" maxlength="2000" placeholder="{{ __('Project context, target price, or anything Estimator should know.') }}"
                                  class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">{{ old('notes') }}</textarea>
                        @error('notes') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90" x-data="{ rows: @js(old('items', [['product_id' => '', 'description' => '', 'quantity' => 1, 'unit' => 'pcs', 'notes' => '']])) }">
                <div class="mb-4 flex items-center justify-between gap-2">
                    <div>
                        <h2 class="font-semibold text-slate-700 dark:text-slate-200">{{ __('Requested Items') }}</h2>
                        <p class="mt-0.5 text-[11px] text-slate-400">{{ __('Add at least one product or describe the item manually.') }}</p>
                    </div>
                    <x-icon-button icon="add" type="button" label="{{ __('Item') }}" title="{{ __('Item') }}" @click="rows.push({ product_id: '', description: '', quantity: 1, unit: 'pcs', notes: '' })" />
                </div>

                <div class="space-y-3">
                    <template x-for="(row, index) in rows" :key="index">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-600 dark:bg-slate-900/40">
                            <div class="grid grid-cols-12 gap-2">
                                <select :name="`items[${index}][product_id]`" x-model="row.product_id"
                                        @change="const option = $event.target.selectedOptions[0]; if (option?.dataset.name) { row.description = option.dataset.name; row.unit = option.dataset.unit || 'pcs'; }"
                                        class="col-span-12 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800 sm:col-span-5">
                                    <option value="">{{ __('Select product (optional)') }}</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}" data-name="{{ $product->name }}" data-unit="{{ $product->unit }}">{{ $product->name }}{{ $product->sku ? ' · '.$product->sku : '' }}</option>
                                    @endforeach
                                </select>
                                <input type="text" :name="`items[${index}][description]`" x-model="row.description" maxlength="255" placeholder="{{ __('Item description') }}"
                                       class="col-span-12 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800 sm:col-span-7">
                                <input type="number" :name="`items[${index}][quantity]`" x-model="row.quantity" min="0.01" step="0.01" placeholder="{{ __('Qty') }}"
                                       class="col-span-4 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800 sm:col-span-2">
                                <input type="text" :name="`items[${index}][unit]`" x-model="row.unit" maxlength="16" placeholder="{{ __('Unit') }}"
                                       class="col-span-4 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800 sm:col-span-2">
                                <input type="text" :name="`items[${index}][notes]`" x-model="row.notes" maxlength="255" placeholder="{{ __('Item note (optional)') }}"
                                       class="col-span-4 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800 sm:col-span-7">
                                <div class="col-span-12 flex items-center justify-end sm:col-span-1">
                                    <x-icon-button icon="delete" type="button" title="{{ __('Remove') }}" @click="rows.splice(index, 1)" />
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
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
