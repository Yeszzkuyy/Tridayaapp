<x-app-layout>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Supplier Invoices') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Accounts payable invoices from suppliers. Separate from customer invoices.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <x-icon-button as="a" icon="back" href="{{ route('admin.hub') }}" title="{{ __('Back') }}" />
            @can('manage-admin')
                <x-icon-button as="a" icon="add" href="{{ route('admin.supplier-invoices.create') }}" label="{{ __('Record Supplier Invoice') }}" title="{{ __('Record Supplier Invoice') }}" />
            @endcan
        </div>
    </div>

    <form method="GET" action="{{ route('admin.supplier-invoices.index') }}" class="mb-4 flex flex-wrap items-end gap-2">
        <div>
            <label for="q" class="block text-xs text-slate-500 mb-1">{{ __('Search') }}</label>
            <input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Invoice / supplier / PO') }}"
                   class="w-64 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
        </div>
        <div>
            <label for="supplier_id" class="block text-xs text-slate-500 mb-1">{{ __('Supplier') }}</label>
            <select id="supplier_id" name="supplier_id" class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                <option value="">{{ __('All') }}</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected((string) request('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="block text-xs text-slate-500 mb-1">{{ __('Status') }}</label>
            <select id="status" name="status" class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                <option value="">{{ __('All') }}</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Models\SupplierInvoice::statusLabel($status) }}</option>
                @endforeach
            </select>
        </div>
        <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
        <x-icon-button as="a" icon="reset" href="{{ route('admin.supplier-invoices.index') }}" title="{{ __('Reset') }}" />
    </form>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Internal Ref') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Supplier Invoice No.') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Supplier') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Supplier PO') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Type') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Issue / Due') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Amount') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Paid') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                    @forelse($invoices as $invoice)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition group">
                            <td class="px-4 py-3 font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $invoice->internal_ref_number }}</td>
                            <td class="px-4 py-3 font-mono text-sm text-slate-700 dark:text-slate-300">{{ $invoice->supplier_invoice_number }}</td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $invoice->supplier?->name ?? __('Not filled') }}</td>
                            <td class="px-4 py-3 font-mono text-sm text-slate-700 dark:text-slate-300">
                                @if($invoice->purchaseOrder)
                                    <a href="{{ route('admin.supplier-pos.show', $invoice->purchaseOrder) }}" class="hover:underline">{{ $invoice->purchaseOrder->po_number }}</a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ \App\Models\SupplierInvoice::typeLabel($invoice->invoice_type) }}</td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                {{ $invoice->issue_date?->format('d M Y') }}
                                <span class="block text-xs text-slate-400">{{ $invoice->due_date?->format('d M Y') ?? __('Not filled') }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-slate-800 dark:text-slate-100">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-600 dark:text-slate-400">Rp {{ number_format($invoice->paid_amount, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full {{ \App\Models\SupplierInvoice::statusBadgeClasses($invoice->status) }}">
                                    {{ \App\Models\SupplierInvoice::statusLabel($invoice->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                                    @can('manage-admin')
                                        @if($invoice->status !== 'cancelled' && $invoice->remainingAmount() > 0)
                                            <x-icon-button as="a" icon="add" href="{{ route('admin.supplier-invoices.show', $invoice) }}#payment-form" title="{{ __('Record Payment') }}" />
                                        @endif
                                    @endcan
                                    <x-icon-button as="a" icon="view" href="{{ route('admin.supplier-invoices.show', $invoice) }}" title="{{ __('View invoice') }}" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="py-8 text-center text-slate-400">{{ __('No supplier invoices yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($invoices->hasPages())<div class="p-4 border-t">{{ $invoices->links() }}</div>@endif
    </div>
</x-app-layout>
