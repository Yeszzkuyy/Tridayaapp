<x-app-layout>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Procurement Reports') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Unbilled receipts, undelivered Supplier POs, and outstanding supplier invoices.') }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('admin.hub') }}" title="{{ __('Back') }}" />
    </div>

    <form method="GET" action="{{ route('admin.purchasing.reports') }}" class="mb-5 flex flex-wrap items-end gap-2">
        <div>
            <label for="pt_group" class="block text-xs text-slate-500 mb-1">{{ __('PT Group') }}</label>
            <select id="pt_group" name="pt_group" class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                <option value="">{{ __('All') }}</option>
                @foreach($ptGroups as $group)
                    <option value="{{ $group }}" @selected($ptGroup === $group)>{{ $group }}</option>
                @endforeach
            </select>
        </div>
        <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
        <x-icon-button as="a" icon="reset" href="{{ route('admin.purchasing.reports') }}" title="{{ __('Reset') }}" />
    </form>

    <div class="space-y-6">
        <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-slate-800 dark:text-slate-100">{{ __('Verified Receipts Not Yet Invoiced') }}</h2>
                    <p class="text-xs text-slate-500 mt-0.5">{{ __('Supplier has delivered, but no AP invoice has been recorded.') }}</p>
                </div>
                <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">{{ $unbilled->count() }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                    <thead class="bg-slate-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Receipt') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Supplier PO') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Supplier') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Project') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Received Date') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Items') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                        @forelse($unbilled as $receipt)
                            <tr>
                                <td class="px-4 py-3 font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $receipt->receipt_number }}</td>
                                <td class="px-4 py-3 font-mono text-sm text-slate-700 dark:text-slate-300">{{ $receipt->purchaseOrder?->po_number ?? __('Not filled') }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $receipt->purchaseOrder?->supplier?->name ?? __('Not filled') }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $receipt->project?->project_name ?? __('Not filled') }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $receipt->received_date?->format('d M Y') }}</td>
                                <td class="px-4 py-3 text-center text-slate-600 dark:text-slate-400">{{ $receipt->items->count() }}</td>
                                <td class="px-4 py-3 text-right">
                                    <x-icon-button as="a" icon="view" href="{{ route('admin.goods-receipts.show', $receipt) }}" title="{{ __('View receipt') }}" />
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-6 text-center text-slate-400">{{ __('All verified receipts have been invoiced.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-slate-800 dark:text-slate-100">{{ __('Supplier POs Awaiting Complete Delivery') }}</h2>
                    <p class="text-xs text-slate-500 mt-0.5">{{ __('Ordered quantity is still greater than verified received quantity.') }}</p>
                </div>
                <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300">{{ $pendingOrders->count() }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                    <thead class="bg-slate-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('PO Number') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Supplier') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Project') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Issue Date') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Received / Ordered') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                        @forelse($pendingOrders as $order)
                            @php
                                $ordered = (float) $order->items->sum('quantity_ordered');
                                $received = (float) $order->items->sum('quantity_received');
                            @endphp
                            <tr>
                                <td class="px-4 py-3 font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $order->po_number }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $order->supplier?->name ?? __('Not filled') }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $order->project?->project_name ?? __('Not filled') }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $order->issue_date?->format('d M Y') }}</td>
                                <td class="px-4 py-3 text-center text-slate-700 dark:text-slate-300">{{ number_format($received, 2) }} / {{ number_format($ordered, 2) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full {{ \App\Models\SupplierPurchaseOrder::statusBadgeClasses($order->status) }}">
                                        {{ \App\Models\SupplierPurchaseOrder::statusLabel($order->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <x-icon-button as="a" icon="view" href="{{ route('admin.supplier-pos.show', $order) }}" title="{{ __('View purchase order') }}" />
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-6 text-center text-slate-400">{{ __('No outstanding Supplier POs.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-slate-800 dark:text-slate-100">{{ __('Outstanding Supplier Invoices') }}</h2>
                    <p class="text-xs text-slate-500 mt-0.5">{{ __('AP invoices that are unpaid or partially paid.') }}</p>
                </div>
                <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">{{ $outstandingInvoices->count() }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                    <thead class="bg-slate-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Internal Ref') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Supplier') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Project') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Due Date') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Amount') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Remaining') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                        @forelse($outstandingInvoices as $invoice)
                            <tr>
                                <td class="px-4 py-3 font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $invoice->internal_ref_number }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $invoice->supplier?->name ?? __('Not filled') }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $invoice->project?->project_name ?? __('Not filled') }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $invoice->due_date?->format('d M Y') ?? __('Not filled') }}</td>
                                <td class="px-4 py-3 text-right font-mono text-slate-800 dark:text-slate-100">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-mono text-amber-600 dark:text-amber-400">Rp {{ number_format($invoice->remainingAmount(), 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full {{ \App\Models\SupplierInvoice::statusBadgeClasses($invoice->status) }}">
                                        {{ \App\Models\SupplierInvoice::statusLabel($invoice->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <x-icon-button as="a" icon="view" href="{{ route('admin.supplier-invoices.show', $invoice) }}" title="{{ __('View invoice') }}" />
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="py-6 text-center text-slate-400">{{ __('No outstanding supplier invoices.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
