<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">{{ $invoice->invoice_number }}</h1>
                <p class="text-slate-500 mt-1">{{ $invoice->customer->name }}</p>
            </div>
            <div class="flex gap-2">
                </div>
                @forelse($invoice->payments as $payment)
                    <div class="border-b border-slate-100 py-3 last:border-0">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">Rp {{ number_format($payment->amount, 0, ',', '.') }}</p>
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
        </div>
    </div>
</x-app-layout>
