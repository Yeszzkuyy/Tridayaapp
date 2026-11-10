<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">{{ __('Edit Invoice') }}</h1>
                <p class="text-slate-500 mt-1">{{ $invoice->invoice_number }}</p>
            </div>
            <x-back-button href="{{ route('admin.invoices.index') }}" />
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <form action="{{ route('admin.invoices.update', $invoice) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Customer') }} <span class="text-red-500">*</span></label>
                    <x-searchable-select
                        name="customer_id"
                        :options="$customers->mapWithKeys(fn ($c) => [$c->id => $c->name])->all()"
                        :selected="old('customer_id', $invoice->customer_id)"
                        placeholder="{{ __('Search & select customer...') }}"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Related Project') }}</label>
                    <select name="project_id" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500"
                            x-data="{ customerId: '{{ old('customer_id', $invoice->customer_id) }}' }"
                            @searchable-select:changed.window="customerId = $event.detail.id; $el.value = ''">
                        <option value="">{{ __('-- Not linked to a project --') }}</option>
                        @foreach($projects as $p) <option value="{{ $p->id }}" data-customer="{{ $p->customer_id }}"
                                x-show="!customerId || customerId == '{{ $p->customer_id }}'"
                                {{ old('project_id', $invoice->project_id) == $p->id ? 'selected' : '' }}>{{ $p->project_name }}</option> @endforeach
                    </select>
                    @error('project_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <input type="hidden" name="proposal_id" value="{{ old('proposal_id', $invoice->proposal_id) }}">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Customer PO Number') }}</label>
                        <input type="text" name="customer_po_number" maxlength="100" value="{{ old('customer_po_number', $invoice->customer_po_number) }}"
                               placeholder="{{ __('e.g. PO/2026/001') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @error('customer_po_number') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-2 text-xs text-slate-500 self-end">
                        @if($invoice->payment_terms)
                            {{ __('Payment Terms') }}: <span class="font-medium text-slate-700">{{ $invoice->payment_terms }}</span>
                        @elseif($invoice->proposal)
                            {{ __('Payment Terms') }}: <span class="font-medium text-slate-700">{{ $invoice->proposal->paymentTermsSummary() }}</span>
                        @else
                            {{ __('Payment terms are taken from the quotation.') }}
                        @endif
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Invoice Type') }} <span class="text-red-500">*</span></label>
                    <select name="invoice_type" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @foreach($types as $type => $label)
                            <option value="{{ $type }}" @selected(old('invoice_type', $invoice->invoice_type ?? 'full') === $type)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Amount (Rp)') }} <span class="text-red-500">*</span></label>
                        <input id="invoice-amount" type="number" name="amount" value="{{ old('amount', $invoice->amount) }}" required step="0.01" min="0" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Discount (Rp)') }}</label>
                        <input id="invoice-discount" type="number" name="discount" value="{{ old('discount', $invoice->discount ?? 0) }}" step="0.01" min="0" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Tax (Rp)') }}</label>
                        <input id="invoice-tax" type="number" name="tax" value="{{ old('tax', $invoice->tax ?? 0) }}" step="0.01" min="0" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                    </div>
                </div>
                <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3 text-sm dark:bg-slate-900/50">
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('Items subtotal') }}</span>
                    <span id="invoice-subtotal-preview" class="font-mono font-semibold text-slate-800 dark:text-slate-100">Rp 0</span>
                </div>

                @include('admin.invoices._items', ['items' => $invoice->items->map(fn ($item) => [
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount,
                ])->all()])

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Status') }}</label>
                        <select name="status" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                            @foreach($statuses as $val => $label) <option value="{{ $val }}" {{ old('status', $invoice->status) == $val ? 'selected' : '' }}>{{ $label }}</option> @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Issue Date') }} <span class="text-red-500">*</span></label>
                        <x-datepicker name="issue_date" required value="{{ old('issue_date', $invoice->issue_date->format('Y-m-d')) }}"></x-datepicker>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Due Date') }}</label>
                        <x-datepicker name="due_date" value="{{ old('due_date', $invoice->due_date?->format('Y-m-d')) }}"></x-datepicker>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Notes') }}</label>
                    <textarea name="notes" rows="2" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('notes', $invoice->notes) }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Invoice File') }}</label>
                    <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                    <p class="mt-1 text-xs text-slate-400">{{ __('Optional. The file also appears in the customer Documents tab.') }} · {{ __('Maximum 20MB') }}</p>
                    @error('file') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-3">
                    <x-primary-button type="submit">{{ __('Update') }}</x-primary-button>
                    <x-secondary-button as="a" href="{{ route('admin.invoices.index') }}">{{ __('Cancel') }}</x-secondary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
