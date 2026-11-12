<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-white">{{ $invoice->invoice_number }}</h1>
                <p class="text-slate-500 mt-1 dark:text-slate-400">{{ $invoice->customer->name }}</p>
            </div>
            <x-back-button href="{{ route('customers.show', $invoice->customer) }}" />
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Invoice No.') }}</dt>
                    <dd class="font-mono font-semibold text-slate-800 dark:text-slate-100">{{ $invoice->invoice_number }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Customer') }}</dt>
                    <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $invoice->customer->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Issue Date') }}</dt>
                    <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $invoice->issue_date->format('d M Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Due Date') }}</dt>
                    <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $invoice->due_date?->format('d M Y') ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Type') }}</dt>
                    <dd class="font-medium text-slate-800 dark:text-slate-100">{{ \App\Models\Invoice::typeLabel($invoice->invoice_type) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Amount') }}</dt>
                    <dd class="font-semibold text-lg text-slate-800 dark:text-slate-100">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Status') }}</dt>
                    <dd>
                        <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full {{ \App\Models\Invoice::statusBadgeClasses($invoice->status) }}">
                            {{ \App\Models\Invoice::statusLabel($invoice->status) }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Customer PO Number') }}</dt>
                    <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $invoice->customer_po_number ?: __('Not set yet') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Quotation') }}</dt>
                    <dd class="font-medium text-slate-800 dark:text-slate-100">
                        @if($invoice->proposal)
                            <a href="{{ route('sales.proposals.show', $invoice->proposal) }}" class="text-accent-600 dark:text-accent-400 hover:underline">{{ $invoice->proposal->proposal_number }}</a>
                        @else
                            <span class="text-slate-400 italic">{{ __('No quotation linked') }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Billing Address') }}</dt>
                    <dd class="font-medium text-slate-800 dark:text-slate-100 whitespace-pre-line">{{ $invoice->billing_address ?: ($invoice->customer?->billingAddress() ?: __('Not set yet')) }}</dd>
                </div>
            </dl>
            @if($invoice->notes)
                <div class="mt-6">
                    <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">{{ __('Notes') }}</h3>
                    <p class="text-slate-700 dark:text-slate-300 whitespace-pre-wrap">{{ $invoice->notes }}</p>
                </div>
            @endif
        </div>

        @if($invoice->items->isNotEmpty())
            <div class="mt-6 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">{{ __('Invoice Items') }}</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Description') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Qty') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Unit') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Price') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Subtotal') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach($invoice->items as $item)
                                <tr>
                                    <td class="px-3 py-3 text-sm text-slate-800 dark:text-slate-100">{{ $item->description }}</td>
                                    <td class="px-3 py-3 text-right text-sm text-slate-700 dark:text-slate-300">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-sm text-slate-700 dark:text-slate-300">{{ $item->unit }}</td>
                                    <td class="px-3 py-3 text-right text-sm text-slate-700 dark:text-slate-300">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-right text-sm font-medium text-slate-800 dark:text-slate-100">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="mt-6 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
            <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">{{ __('Payments') }}</h3>
            @forelse($invoice->payments as $payment)
                <div class="border-b border-slate-100 dark:border-slate-700 py-3 last:border-0">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Rp {{ number_format($payment->amount, 0, ',', '.') }}</p>
                            <p class="text-xs text-slate-500">{{ $payment->payment_date->format('d M Y') }}</p>
                            <p class="text-xs text-slate-400">{{ $payment->payment_method ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-slate-400 text-sm text-center py-4">{{ __('No payments yet.') }}</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
