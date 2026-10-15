<x-app-layout>
    <div >
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Detail Follow Up</h1>
                <p class="text-slate-500 mt-1">{{ $followUp->customer?->name ?? '-' }}</p>
            </div>
            <div class="flex gap-2">
                @can('manage-sales')
                    <x-icon-button as="a" icon="edit" href="{{ route('sales.follow-ups.edit', $followUp) }}" title="Edit" />
                @endcan
                <x-icon-button as="a" icon="back" href="{{ route('sales.follow-ups.index') }}" title="{{ __('Kembali') }}" />
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <dt class="text-xs text-slate-400">Customer</dt>
                    <dd class="font-medium text-slate-800">{{ $followUp->customer?->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Jenis') }}</dt>
                    <dd class="font-medium text-slate-800">{{ $followUp->type ? \App\Models\FollowUp::typeLabel($followUp->type) : '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Tanggal Follow Up') }}</dt>
                    <dd class="font-medium text-slate-800">{{ $followUp->follow_up_date ? $followUp->follow_up_date->format('d M Y') : '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Jam') }}</dt>
                    <dd class="font-medium text-slate-800">{{ $followUp->activity_time ? substr((string) $followUp->activity_time, 0, 5) : '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Lokasi') }}</dt>
                    <dd class="font-medium text-slate-800"><x-location-link :location="$followUp->location" preview /></dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Next Follow Up') }}</dt>
                    <dd class="font-medium text-slate-800">{{ $followUp->next_follow_up_date ? $followUp->next_follow_up_date->format('d M Y') : '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Terkait Lead') }}</dt>
                    <dd class="font-medium text-slate-800">{{ $followUp->lead ? '#'.$followUp->lead->id.' — '.\App\Models\Lead::salesStatusLabel($followUp->lead->status) : '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Dicatat oleh') }}</dt>
                    <dd class="font-medium text-slate-800">{{ $followUp->creator?->name ?? '-' }}</dd>
                </div>
            </dl>

            <div>
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">{{ __('Deskripsi') }}</h3>
                <p class="text-slate-700 whitespace-pre-wrap">{{ $followUp->description }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
