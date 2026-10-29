{{-- Isi tab Activity customer. Dipakai halaman detail + endpoint poll
     (customers.timeline) supaya aktivitas baru muncul tanpa reload. --}}
@if($timeline->isNotEmpty())
    <div class="relative">
        <div class="absolute left-[15px] top-8 bottom-4 w-0.5 bg-slate-200 dark:bg-slate-700"></div>
        @foreach($timeline as $item)
            @php
                $loggedAt = $item['logged_at'] ?? null;
                $eventAt = $item['event_at'] ?? null;
                $eventDiffers = $eventAt && $loggedAt && ! $eventAt->equalTo($loggedAt);
            @endphp
            <div class="relative pl-10 pb-5 last:pb-0">
                <div class="absolute left-0 top-0">
                    <x-user-avatar :user="$item['user']" size="w-8 h-8" text="text-xs" />
                </div>
                <div class="bg-slate-50 dark:bg-slate-900/50 rounded-xl p-3.5">
                    <p class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm text-slate-800 dark:text-slate-100">
                        <span class="font-semibold">{{ $item['user']?->name ?? __('System') }}</span>
                        <x-timeline-badge :type="$item['type']" />
                        <span>{{ $item['title'] }}</span>
                        <span class="text-xs text-slate-400">·
                            <time datetime="{{ $loggedAt?->toIso8601String() }}"
                                  data-relative
                                  title="{{ $loggedAt?->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i') }} WIB">{{ $loggedAt?->diffForHumans() ?? '-' }}</time>
                        </span>
                    </p>
                    @if($item['detail'] || $item['context'])
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $item['detail'] }}@if($item['context']) · {{ $item['context'] }}@endif</p>
                    @endif
                    @if($eventDiffers)
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ in_array($item['type'], ['followup', 'meeting'], true) ? __('Event/schedule') : __('Event time') }}:
                            {{ $eventAt->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i') }} WIB
                        </p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@else
    <x-empty-state label="{{ __('activity') }}" />
@endif
