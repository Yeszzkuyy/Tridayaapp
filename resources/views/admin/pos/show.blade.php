<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div><h1 class="text-3xl font-bold text-slate-800">{{ $purchaseOrder->po_number }}</h1><p class="text-slate-500 mt-1">{{ $purchaseOrder->customer->name }}</p></div>
            <div class="flex gap-2">
                @can('manage-admin')<x-icon-button as="a" icon="edit" href="{{ route('admin.pos.edit', $purchaseOrder) }}" title="{{ __('Edit') }}" />@endcan
                <x-icon-button as="a" icon="back" href="{{ route('admin.pos.index') }}" title="{{ __('Kembali') }}" />
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><dt class="text-xs text-slate-400">{{ __('No PO') }}</dt><dd class="font-mono font-semibold text-slate-800">{{ $purchaseOrder->po_number }}</dd></div>
                <div><dt class="text-xs text-slate-400">{{ __('Customer') }}</dt><dd class="font-medium text-slate-800">{{ $purchaseOrder->customer->name }}</dd></div>
                <div><dt class="text-xs text-slate-400">{{ __('Proyek') }}</dt><dd class="font-medium text-slate-800">{{ $purchaseOrder->project?->project_name ?? '-' }}</dd></div>
                <div><dt class="text-xs text-slate-400">{{ __('Tanggal Terbit') }}</dt><dd class="font-medium text-slate-800">{{ $purchaseOrder->issue_date->format('d M Y') }}</dd></div>
                <div><dt class="text-xs text-slate-400">{{ __('Nominal') }}</dt><dd class="font-semibold text-lg text-slate-800">Rp {{ number_format($purchaseOrder->amount, 0, ',', '.') }}</dd></div>
                <div><dt class="text-xs text-slate-400">{{ __('Status') }}</dt>
                    <dd><span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full
                        @if($purchaseOrder->status === 'selesai') bg-green-100 text-green-700
                        @elseif($purchaseOrder->status === 'dibatalkan') bg-red-100 text-red-700
                        @elseif($purchaseOrder->status === 'diproses') bg-yellow-100 text-yellow-700
                        @else bg-slate-100 text-slate-600 @endif">{{ $purchaseOrder->statusLabel() }}</span></dd>
                </div>
            </dl>
            <div class="mt-6">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">{{ __('Item') }}</h3>
                <p class="text-slate-700 whitespace-pre-wrap">{{ $purchaseOrder->items }}</p>
            </div>
            @if($purchaseOrder->notes)
            <div class="mt-4">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">{{ __('Catatan') }}</h3>
                <p class="text-slate-700 whitespace-pre-wrap">{{ $purchaseOrder->notes }}</p>
            </div>
            @endif
            <div class="mt-4 text-xs text-slate-400">{{ __('Dibuat oleh:') }} {{ $purchaseOrder->creator?->name ?? '-' }}</div>
        </div>
    </div>
</x-app-layout>
