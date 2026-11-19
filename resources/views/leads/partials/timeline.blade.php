{{-- Daftar riwayat follow-up lead. Dipakai halaman detail + endpoint poll
     (leads.timeline) supaya catatan baru muncul tanpa reload. --}}
@forelse($timeline ?? [] as $item)
    <div class="flex items-start gap-3 p-3 bg-slate-50 dark:bg-slate-700 rounded-xl">
        <span class="mt-0.5 inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold shrink-0 {{ $item['type'] === 'meeting' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' }}">
            {{ $item['title'] }}
        </span>
        <div class="flex-1 min-w-0">
            <p class="text-sm text-slate-800 dark:text-slate-100 line-clamp-2">{{ $item['summary'] ?: '-' }}</p>
            <p class="mt-1 text-xs text-slate-500">
                {{ $item['date'] ? \Carbon\Carbon::parse($item['date'])->format('d M Y') : '-' }}
                @if(!empty($item['time'])) • {{ substr((string) $item['time'], 0, 5) }} @endif
                @if(!empty($item['location'])) • {{ $item['location'] }} @endif
                @if($item['by']) • {{ $item['by'] }} @endif
            </p>
        </div>
        @if($item['url'])
            <x-icon-button as="a" icon="view" href="{{ $item['url'] }}" title="{{ __('View details') }}" class="shrink-0" />
        @endif
    </div>
@empty
    <p class="text-slate-500">{{ __('No follow up logs for this lead yet.') }}</p>
@endforelse
