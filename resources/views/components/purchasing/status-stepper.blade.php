@props(['proposal'])

@php
    $stages = \App\Models\Proposal::handoffStages();
    $keys = array_keys($stages);
    $current = $proposal->handoffStage();
    $index = $current !== null ? array_search($current, $keys, true) : null;
    $total = count($keys);
@endphp

@if ($current === null)
    <span {{ $attributes->merge(['class' => 'inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold '.\App\Models\Proposal::statusBadgeClasses($proposal->status)]) }}>
        {{ \App\Models\Proposal::statusLabel($proposal->status) }}
    </span>
@else
    <div {{ $attributes->merge(['class' => 'group relative flex w-full min-w-[140px] flex-col gap-1.5']) }}
         tabindex="0"
         role="img"
         aria-label="{{ $stages[$current] }} ({{ $index + 1 }}/{{ $total }})">
        <div class="flex items-center justify-between gap-2">
            <span class="inline-flex max-w-full truncate rounded-full bg-accent-600 px-2.5 py-0.5 text-[11px] font-semibold text-white">
                {{ $stages[$current] }}
            </span>
            <span class="shrink-0 text-[11px] font-semibold tabular-nums text-slate-400 dark:text-slate-500">{{ $index + 1 }}/{{ $total }}</span>
        </div>

        {{-- Bar progres 6 tahap: selesai hijau, aktif aksen, sisanya netral --}}
        <div class="flex gap-1" aria-hidden="true">
            @foreach ($keys as $i => $key)
                <span class="h-1.5 flex-1 rounded-full {{ $i < $index ? 'bg-emerald-500' : ($i === $index ? 'bg-accent-600' : 'bg-slate-200 dark:bg-slate-600') }}"></span>
            @endforeach
        </div>

        {{-- Rincian tahap saat kursor diarahkan / fokus keyboard --}}
        <div class="pointer-events-none absolute left-0 top-full z-20 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-3 opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100 group-focus:opacity-100 dark:border-slate-700 dark:bg-slate-800">
            <ol class="space-y-1.5">
                @foreach ($stages as $key => $label)
                    @php $i = array_search($key, $keys, true); @endphp
                    <li class="flex items-center gap-2 text-xs {{ $i < $index ? 'text-emerald-600 dark:text-emerald-400' : ($i === $index ? 'font-semibold text-slate-800 dark:text-slate-100' : 'text-slate-400 dark:text-slate-500') }}">
                        <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-[10px] font-bold {{ $i < $index ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : ($i === $index ? 'bg-accent-600 text-white' : 'bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-500') }}">
                            {{ $i < $index ? '✓' : $i + 1 }}
                        </span>
                        <span class="truncate">{{ $label }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
@endif
