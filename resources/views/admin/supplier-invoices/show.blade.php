<x-app-layout>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ $supplierInvoice->internal_ref_number }}</h1>
                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full {{ \App\Models\SupplierInvoice::statusBadgeClasses($supplierInvoice->status) }}">
                    {{ \App\Models\SupplierInvoice::statusLabel($supplierInvoice->status) }}
                </span>
            </div>
            <p class="text-slate-500 mt-1">{{ __('Supplier invoice') }} {{ $supplierInvoice->supplier_invoice_number }} · {{ $supplierInvoice->supplier?->name }}</p>
        </div>
        <div class="flex items-center gap-2">
            <x-icon-button as="a" icon="back" href="{{ route('admin.supplier-invoices.index') }}" title="{{ __('Back') }}" />
            @if($supplierInvoice->invoice_file_path)
                <x-icon-button as="a" icon="download" href="{{ route('admin.supplier-invoices.file', $supplierInvoice) }}" title="{{ __('Download invoice file') }}" />
            @endif
            @can('manage-admin')
                @if($supplierInvoice->status !== 'cancelled' && $supplierInvoice->payments->isEmpty())
                    <form method="POST" action="{{ route('admin.supplier-invoices.cancel', $supplierInvoice) }}">
                        @csrf
                        <x-icon-button icon="delete" type="submit" title="{{ __('Cancel invoice') }}" />
                    </form>
                @endif
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 text-green-700 px-4 py-3 text-sm dark:bg-green-900/30 dark:border-green-800 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm dark:bg-red-900/30 dark:border-red-800 dark:text-red-200">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-5">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
                <h2 class="font-semibold text-slate-800 dark:text-slate-100 mb-4">{{ __('Invoice Summary') }}</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Amount') }}</p>
                        <p class="font-mono font-semibold text-slate-800 dark:text-slate-100">Rp {{ number_format($supplierInvoice->amount, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Paid') }}</p>
                        <p class="font-mono font-semibold text-green-600 dark:text-green-400">Rp {{ number_format($supplierInvoice->paid_amount, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Remaining') }}</p>
                        <p class="font-mono font-semibold text-amber-600 dark:text-amber-400">Rp {{ number_format($supplierInvoice->remainingAmount(), 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Type') }}</p>
                        <p class="text-slate-800 dark:text-slate-100">{{ \App\Models\SupplierInvoice::typeLabel($supplierInvoice->invoice_type) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
                <h2 class="font-semibold text-slate-800 dark:text-slate-100 mb-4">{{ __('Payments to Supplier') }}</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Date') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Method') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Amount') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Notes') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @forelse($supplierInvoice->payments as $payment)
                                <tr>
                                    <td class="px-3 py-3 text-sm text-slate-700 dark:text-slate-300">{{ $payment->payment_date?->format('d M Y') }}</td>
                                    <td class="px-3 py-3 text-sm text-slate-700 dark:text-slate-300">{{ $payment->payment_method ?? __('Not filled') }}</td>
                                    <td class="px-3 py-3 text-right font-mono text-sm text-slate-800 dark:text-slate-100">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $payment->notes ?? __('Not filled') }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex justify-end gap-2">
                                            @if($payment->proof_file)
                                                <x-icon-button as="a" icon="download" href="{{ route('admin.supplier-payments.proof', $payment) }}" title="{{ __('Download proof') }}" />
                                            @endif
                                            @can('manage-admin')
                                                <form method="POST" action="{{ route('admin.supplier-payments.destroy', $payment) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-icon-button icon="delete" type="submit" title="{{ __('Delete payment') }}" />
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-slate-400">{{ __('No payments recorded yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @can('manage-admin')
                    @if($supplierInvoice->status !== 'cancelled' && $supplierInvoice->remainingAmount() > 0)
                        <form id="payment-form" method="POST" action="{{ route('admin.supplier-invoices.payments.store', $supplierInvoice) }}" enctype="multipart/form-data"
                              class="mt-5 pt-5 border-t border-slate-200 dark:border-slate-700 grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">{{ __('Amount (Rp)') }} <span class="text-red-500">*</span></label>
                                <input type="number" step="0.01" min="0.01" max="{{ $supplierInvoice->remainingAmount() }}" name="amount" required
                                       class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">{{ __('Payment Date') }} <span class="text-red-500">*</span></label>
                                <x-datepicker name="payment_date" required value="{{ date('Y-m-d') }}" />
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">{{ __('Method') }}</label>
                                <input type="text" name="payment_method" maxlength="100" placeholder="{{ __('Transfer / Cash') }}"
                                       class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">{{ __('Proof') }}</label>
                                <input type="file" name="proof_file" accept=".pdf,.jpg,.jpeg,.png"
                                       class="w-full rounded-xl border-slate-300 text-xs dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                            </div>
                            <div>
                                <button type="submit"
                                        class="group relative overflow-hidden w-full rounded-xl bg-accent-600 hover:bg-accent-700 text-white px-4 py-2.5 text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                                    {{ __('Save Payment') }}
                                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                                </button>
                            </div>
                        </form>
                    @endif
                @endcan
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-4 text-sm">
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Supplier PO') }}</p>
                @if($supplierInvoice->purchaseOrder)
                    <a href="{{ route('admin.supplier-pos.show', $supplierInvoice->purchaseOrder) }}" class="font-mono font-semibold text-accent-600 dark:text-accent-400 hover:underline">{{ $supplierInvoice->purchaseOrder->po_number }}</a>
                @else
                    <p class="text-slate-400 italic">{{ __('Not filled') }}</p>
                @endif
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Goods Receipt') }}</p>
                @if($supplierInvoice->goodsReceipt)
                    <a href="{{ route('admin.goods-receipts.show', $supplierInvoice->goodsReceipt) }}" class="font-mono text-slate-800 dark:text-slate-100 hover:underline">{{ $supplierInvoice->goodsReceipt->receipt_number }}</a>
                @else
                    <p class="text-slate-400 italic">{{ __('Not filled') }}</p>
                @endif
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Project') }}</p>
                @if($supplierInvoice->project)
                    <a href="{{ route('projects.show', $supplierInvoice->project) }}" class="text-slate-800 dark:text-slate-100 hover:underline">{{ $supplierInvoice->project->project_name }}</a>
                @else
                    <p class="text-slate-400 italic">{{ __('Not filled') }}</p>
                @endif
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Issue Date') }}</p>
                    <p class="text-slate-800 dark:text-slate-100">{{ $supplierInvoice->issue_date?->format('d M Y') }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Due Date') }}</p>
                    <p class="text-slate-800 dark:text-slate-100">{{ $supplierInvoice->due_date?->format('d M Y') ?? __('Not filled') }}</p>
                </div>
            </div>
            @if($supplierInvoice->notes)
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Notes') }}</p>
                    <p class="text-slate-700 dark:text-slate-300 whitespace-pre-line">{{ $supplierInvoice->notes }}</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
