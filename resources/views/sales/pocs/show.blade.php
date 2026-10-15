<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">{{ \App\Models\Poc::typeLabel($poc->type) }}</h1>
                <p class="text-slate-500 mt-1">{{ $poc->customer?->name ?? '-' }}</p>
            </div>
            <div class="flex gap-2">
                @can('manage-sales')
                    <x-icon-button as="a" icon="edit" href="{{ route('sales.pocs.edit', $poc) }}" title="Edit" />
                @endcan
                <x-icon-button as="a" icon="back" href="{{ route('sales.pocs.index') }}" title="{{ __('Kembali') }}" />
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <dt class="text-xs text-slate-400">Customer</dt>
                    <dd class="font-medium text-slate-800">{{ $poc->customer?->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Tipe') }}</dt>
                    <dd class="font-medium text-slate-800">{{ \App\Models\Poc::typeLabel($poc->type) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Tanggal Jadwal') }}</dt>
                    <dd class="font-medium text-slate-800">{{ $poc->scheduled_date ? $poc->scheduled_date->format('d M Y') : '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Lokasi') }}</dt>
                    <dd class="font-medium text-slate-800">{{ $poc->location ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Status') }}</dt>
                    <dd class="font-medium text-slate-800">{{ \App\Models\Poc::statusLabel($poc->status) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Terkait Lead') }}</dt>
                    <dd class="font-medium text-slate-800">{{ $poc->lead?->customer?->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Dicatat oleh') }}</dt>
                    <dd class="font-medium text-slate-800">{{ $poc->creator?->name ?? '-' }}</dd>
                </div>
            </dl>

            <div>
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">{{ __('Catatan Hasil') }}</h3>
                <p class="text-slate-700 whitespace-pre-wrap">{{ $poc->result_notes ?: '-' }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
