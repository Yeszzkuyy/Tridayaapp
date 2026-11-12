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
    {{-- Stepper ringkas: satu badge tahap + 6 segmen. Rincian tahap muncul
         sebagai popover fixed (di-teleport ke body) supaya tidak terpotong
         oleh kartu tabel yang ber-overflow. --}}
    <div {{ $attributes->merge(['class' => 'group relative flex w-full min-w-[120px] flex-col gap-1.5']) }}
         x-data="{
            open: false,
            top: 0,
            left: 0,
            place() {
                const r = this.$refs.trigger.getBoundingClientRect();
                const panel = this.$refs.panel;
                const w = panel?.offsetWidth || 240;
                const h = panel?.offsetHeight || 220;
                let left = Math.min(Math.max(r.left, 8), Math.max(window.innerWidth - w - 8, 8));
                let top = r.bottom + 8;
                if (top + h > window.innerHeight - 8) top = Math.max(r.top - h - 8, 8);
                this.top = Math.round(top);
                this.left = Math.round(left);
            },
         }"
         x-ref="trigger"
         tabindex="0"
         role="group"
         aria-label="{{ $stages[$current] }} ({{ $index + 1 }}/{{ $total }})"
         @mouseenter="open = true; $nextTick(() => place())"
         @mouseleave="open = false"
         @focusin="open = true; $nextTick(() => place())"
         @focusout="open = false"
         @keydown.escape="open = false"
         @scroll.window.capture="open = false"
         @resize.window="open = false">
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

        <template x-teleport="body">
            <div x-show="open" x-cloak x-ref="panel"
                 :style="{ top: top + 'px', left: left + 'px' }"
                 class="fixed z-[95] w-60 rounded-xl border border-slate-200 bg-white p-3 shadow-lg dark:border-slate-700 dark:bg-slate-800">
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
        </template>
    </div>
@endif
