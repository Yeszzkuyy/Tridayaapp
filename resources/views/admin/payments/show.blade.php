<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div><h1 class="text-3xl font-bold text-content-primary dark:text-white">{{ __('Payment Detail') }}</h1><p class="text-content-secondary mt-1">{{ $payment->invoice->invoice_number }}</p></div>
            <x-back-button href="{{ route('admin.payments.index') }}" />
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><dt class="text-xs text-content-tertiary">{{ __('Invoice') }}</dt><dd class="font-mono font-semibold text-content-primary"><a class="text-accent-600 hover:underline" href="{{ route('admin.invoices.show', $payment->invoice) }}">{{ $payment->invoice->invoice_number }}</a></dd></div>
                <div><dt class="text-xs text-content-tertiary">{{ __('Customer') }}</dt><dd class="font-medium text-content-primary">{{ $payment->invoice->customer->name }}</dd></div>
                <div><dt class="text-xs text-content-tertiary">{{ __('Tanggal Bayar') }}</dt><dd class="font-medium text-content-primary">{{ $payment->payment_date->format('d M Y') }}</dd></div>
                <div><dt class="text-xs text-content-tertiary">{{ __('Method') }}</dt><dd class="font-medium text-content-primary">{{ $payment->payment_method ?? '-' }}</dd></div>
                <div><dt class="text-xs text-content-tertiary">{{ __('Amount') }}</dt><dd class="font-semibold text-lg text-content-primary">Rp {{ number_format($payment->amount, 0, ',', '.') }}</dd></div>
                <div><dt class="text-xs text-content-tertiary">{{ __('Invoice Remaining') }}</dt><dd class="font-medium text-content-primary">Rp {{ number_format($payment->invoice->remainingAmount(), 0, ',', '.') }}</dd></div>
                <div><dt class="text-xs text-content-tertiary">{{ __('Created by') }}</dt><dd class="font-medium text-content-primary">{{ $payment->creator?->name ?? '-' }}</dd></div>
            </dl>
            @can('view-payment-proofs')
                @if($payment->proof_file)
                <div class="mt-6">
                    <h3 class="text-sm font-semibold text-content-secondary uppercase tracking-wider mb-3">{{ __('Transfer Proof') }}</h3>
                    <x-file-preview :url="route('admin.payments.proof', $payment)"
                                    :name="basename((string) $payment->proof_file)"
                                    label="{{ __('View Transfer Proof') }}" />
                </div>
                @endif
            @endcan
            @if($payment->notes)
            <div class="mt-4">
                <h3 class="text-sm font-semibold text-content-secondary uppercase tracking-wider mb-3">{{ __('Notes') }}</h3>
                <p class="text-content-secondary whitespace-pre-wrap">{{ $payment->notes }}</p>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
