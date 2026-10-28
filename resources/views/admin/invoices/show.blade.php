<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-white">{{ $invoice->invoice_number }}</h1>
                <p class="text-slate-500 mt-1 dark:text-slate-400">{{ $invoice->customer->name }}</p>
            </div>
            <div class="flex gap-2">
                @can('manage-admin')
                    @if($invoice->status !== 'cancelled' && ! $invoice->isFullyPaid())
                        <x-icon-button as="a" icon="add" href="{{ route('admin.payments.create', ['invoice_id' => $invoice->id]) }}" title="{{ __('Record Payment') }}" />
                    @endif
                    <x-icon-button as="a" icon="edit" href="{{ route('admin.invoices.edit', $invoice) }}" title="{{ __('Edit') }}" />
                @endcan
                <x-icon-button as="a" icon="back" href="{{ route('admin.invoices.index') }}" title="{{ __('Back') }}" />
            </div>
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
                    <dt class="text-xs text-slate-400">{{ __('Project') }}</dt>
                    <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $invoice->project?->project_name ?? '-' }}</dd>
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
                    <dt class="text-xs text-slate-400">{{ __('Paid') }}</dt>
                    <dd class="font-medium text-slate-800 dark:text-slate-100">
                        Rp {{ number_format($invoice->paidAmount(), 0, ',', '.') }}
                        <span class="text-xs text-slate-400">· {{ __('Remaining') }} Rp {{ number_format($invoice->remainingAmount(), 0, ',', '.') }}</span>
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
                    <dt class="text-xs text-slate-400">{{ __('Payment Terms') }}</dt>
                    <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $invoice->proposal?->paymentTermsSummary() ?: __('Not set yet') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Billing Address') }}</dt>
                    <dd class="font-medium text-slate-800 dark:text-slate-100 whitespace-pre-line">{{ $invoice->customer?->billingAddress() ?: __('Not set yet') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Finance PIC') }}</dt>
                    <dd class="font-medium text-slate-800 dark:text-slate-100">
                        {{ $invoice->customer?->financeContact() ?: __('Not set yet') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('NPWP') }}</dt>
                    <dd class="font-medium text-slate-800 dark:text-slate-100">
                        @if($invoice->customer?->npwp)
                            {{ $invoice->customer->npwp }}
                            @if($invoice->customer->npwp_document_path)
                                <a href="{{ route('customers.npwp', $invoice->customer) }}" target="_blank" class="ml-1 text-xs text-indigo-600 hover:underline">{{ __('Download') }}</a>
                            @endif
                        @else
                            <span class="text-slate-400 italic">{{ __('Not set yet') }}</span>
                        @endif
                    </dd>
                </div>
            </dl>
            @if($invoice->notes)
                <div class="mt-6">
                    <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">{{ __('Notes') }}</h3>
                    <p class="text-slate-700 dark:text-slate-300 whitespace-pre-wrap">{{ $invoice->notes }}</p>
                </div>
            @endif
            <div class="mt-4 text-xs text-slate-400">{{ __('Created by:') }} {{ $invoice->creator?->name ?? '-' }}</div>
        </div>

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
                        <div class="flex gap-2">
                            @if($payment->proof_file)
                                <x-icon-button as="a" icon="download" href="{{ route('admin.payments.proof', $payment) }}" target="_blank" title="{{ __('Proof') }}" />
                            @endif
                            <x-icon-button as="a" icon="view" href="{{ route('admin.payments.show', $payment) }}" title="{{ __('Detail') }}" />
                        </div>
                    </div>
                    @if($payment->notes)<p class="text-xs text-slate-400 mt-1">{{ $payment->notes }}</p>@endif
                </div>
            @empty
                <p class="text-slate-400 text-sm text-center py-4">{{ __('No payments yet.') }}</p>
            @endforelse
        </div>

        <div class="mt-6 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
            <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">{{ __('Invoice File') }}</h3>
            @forelse($invoice->documents as $document)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-700 py-3 last:border-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $document->file_name }}</p>
                        <p class="text-xs text-slate-400">{{ $document->created_at->format('d M Y') }}</p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <x-icon-button as="a" icon="view" href="{{ route('project-documents.preview', $document) }}" target="_blank" title="{{ __('Preview') }}" />
                        <x-icon-button as="a" icon="download" href="{{ route('project-documents.download', $document) }}" title="{{ __('Download') }}" />
                    </div>
                </div>
            @empty
                <p class="text-slate-400 text-sm text-center py-4">{{ __('No file uploaded yet.') }}</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
