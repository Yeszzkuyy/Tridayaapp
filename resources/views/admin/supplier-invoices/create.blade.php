<x-app-layout>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Record Supplier Invoice') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Select a Supplier PO, then record the supplier invoice and optional receipt reference.') }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('admin.supplier-invoices.index') }}" title="{{ __('Back') }}" />
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 mb-5">
        <form method="GET" action="{{ route('admin.supplier-invoices.create') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-72 flex-1">
                <label for="supplier_purchase_order_id" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Supplier PO') }}</label>
                <select id="supplier_purchase_order_id" name="supplier_purchase_order_id" required onchange="this.form.submit()"
                        class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                    <option value="">{{ __('-- Select supplier PO --') }}</option>
                    @foreach($orders as $order)
                        <option value="{{ $order->id }}" @selected($selectedOrder?->id === $order->id)>
                            {{ $order->po_number }} · {{ $order->supplier?->name }} · Rp {{ number_format($order->grand_total, 0, ',', '.') }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    @if($selectedOrder)
        <form action="{{ route('admin.supplier-invoices.store') }}" method="POST" enctype="multipart/form-data"
              class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-6">
            @csrf
            <input type="hidden" name="supplier_purchase_order_id" value="{{ $selectedOrder->id }}">
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">

            @if($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm dark:bg-red-900/30 dark:border-red-800 dark:text-red-200">{{ $errors->first() }}</div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 p-4 text-sm">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Supplier') }}</p>
                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $selectedOrder->supplier?->name }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Project') }}</p>
                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $selectedOrder->project?->project_name ?? __('Not filled') }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('PO Total') }}</p>
                    <p class="font-mono font-medium text-slate-800 dark:text-slate-100">Rp {{ number_format($selectedOrder->grand_total, 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Supplier Invoice Number') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="supplier_invoice_number" value="{{ old('supplier_invoice_number') }}" required maxlength="100"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Invoice Type') }} <span class="text-red-500">*</span></label>
                    <select name="invoice_type" class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                        @foreach($types as $type)
                            <option value="{{ $type }}" @selected(old('invoice_type', 'full') === $type)>{{ \App\Models\SupplierInvoice::typeLabel($type) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Amount (Rp)') }} <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Issue Date') }} <span class="text-red-500">*</span></label>
                    <x-datepicker name="issue_date" required value="{{ old('issue_date', date('Y-m-d')) }}" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Due Date') }}</label>
                    <x-datepicker name="due_date" value="{{ old('due_date') }}" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Goods Receipt Reference') }}</label>
                    <select name="goods_receipt_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                        <option value="">{{ __('-- No receipt reference --') }}</option>
                        @foreach($receipts as $receipt)
                            <option value="{{ $receipt->id }}" @selected((int) old('goods_receipt_id', $selectedReceiptId) === $receipt->id)>
                                {{ $receipt->receipt_number }} · {{ $receipt->received_date?->format('d M Y') }}
                            </option>
                        @endforeach
                    </select>
                    @if($receipts->isEmpty())
                        <p class="mt-1 text-xs text-slate-400">{{ __('No verified receipt for this PO yet.') }}</p>
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Invoice File') }}</label>
                    <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                           class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Notes') }}</label>
                <textarea name="notes" rows="2" class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">{{ old('notes') }}</textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit"
                        class="group relative overflow-hidden rounded-xl bg-accent-600 hover:bg-accent-700 text-white px-6 py-2.5 text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                    {{ __('Save') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </button>
                <a href="{{ route('admin.supplier-invoices.index') }}"
                   class="group relative overflow-hidden rounded-xl bg-accent-500 hover:bg-accent-600 text-white px-6 py-2.5 text-sm font-medium text-center transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                    {{ __('Cancel') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </a>
            </div>
        </form>
    @else
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-8 text-center text-slate-400">
            {{ __('Select a supplier PO to record its invoice.') }}
        </div>
    @endif
</x-app-layout>
