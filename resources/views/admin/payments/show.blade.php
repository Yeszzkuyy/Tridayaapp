<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div><h1 class="text-3xl font-bold text-slate-800">{{ __('Detail Pembayaran') }}</h1><p class="text-slate-500 mt-1">{{ $payment->invoice->invoice_number }}</p></div>
            <x-icon-button as="a" icon="back" href="{{ route('admin.payments.index') }}" title="{{ __('Kembali') }}" />
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><dt class="text-xs text-slate-400">{{ __('Invoice') }}</dt><dd class="font-mono font-semibold text-slate-800">{{ $payment->invoice->invoice_number }}</dd></div>
                <div><dt class="text-xs text-slate-400">{{ __('Customer') }}</dt><dd class="font-medium text-slate-800">{{ $payment->invoice->customer->name }}</dd></div>
                <div><dt class="text-xs text-slate-400">{{ __('Tanggal Bayar') }}</dt><dd class="font-medium text-slate-800">{{ $payment->payment_date->format('d M Y') }}</dd></div>
                <div><dt class="text-xs text-slate-400">{{ __('Metode') }}</dt><dd class="font-medium text-slate-800">{{ $payment->payment_method ?? '-' }}</dd></div>
                <div><dt class="text-xs text-slate-400">{{ __('Nominal') }}</dt><dd class="font-semibold text-lg text-slate-800">Rp {{ number_format($payment->amount, 0, ',', '.') }}</dd></div>
                <div><dt class="text-xs text-slate-400">{{ __('Dibuat oleh') }}</dt><dd class="font-medium text-slate-800">{{ $payment->creator?->name ?? '-' }}</dd></div>
            </dl>
            @if($payment->proof_file)
            <div class="mt-6">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">{{ __('Bukti Transfer') }}</h3>
                <x-icon-button as="a" icon="download" href="{{ route('admin.payments.proof', $payment) }}" target="_blank" label="{{ __('Lihat Bukti Transfer') }}" title="{{ __('Lihat Bukti Transfer') }}" />
            </div>
            @endif
            @if($payment->notes)
            <div class="mt-4">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">{{ __('Catatan') }}</h3>
                <p class="text-slate-700 whitespace-pre-wrap">{{ $payment->notes }}</p>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
