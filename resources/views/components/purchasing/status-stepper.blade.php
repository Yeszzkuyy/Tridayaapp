@props(['proposal'])

@php
    $stages = \App\Models\Proposal::handoffStages();
    $keys = array_keys($stages);
    $current = $proposal->handoffStage();
    $index = $current !== null ? array_search($current, $keys, true) : null;
    $total = count($keys);

    // Warna semantik per tahap: abu = menunggu, indigo = butuh tindakan,
    // biru = proyek jalan, kuning = sedang diproses, teal = BAST, hijau = selesai.
    $badgeTone = match ($current) {
        'waiting_po' => 'border border-slate-300 bg-slate-100 text-slate-600 dark:border-slate-600 dark:bg-slate-700/60 dark:text-slate-300',
        'po_received' => 'bg-indigo-600 text-white shadow-sm shadow-indigo-500/30',
        'project_created' => 'border border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-700 dark:bg-blue-950/50 dark:text-blue-200',
        'in_purchasing' => 'border border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
        'bast' => 'border border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-700 dark:bg-teal-950/50 dark:text-teal-200',
        'closed' => 'border border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-200',
        default => 'bg-accent-600 text-white',
    };

    $activeBar = match ($current) {
        'waiting_po' => 'bg-slate-400',
        'po_received' => 'bg-indigo-600',
        'project_created' => 'bg-blue-500',
        'in_purchasing' => 'bg-amber-500',
        'bast' => 'bg-teal-500',
        'closed' => 'bg-emerald-500',
        default => 'bg-accent-600',
    };
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
            <span class="inline-flex max-w-full items-center truncate rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $badgeTone }}">
                {{ $stages[$current] }}
                @if ($current === 'po_received')
                    {{-- Penanda diam: menonjol tanpa animasi berulang. --}}
                    <span class="ml-1 inline-flex h-1.5 w-1.5 shrink-0 rounded-full bg-white ring-2 ring-white/40" aria-hidden="true"></span>
                @endif
            </span>
            <span class="shrink-0 text-[11px] font-semibold tabular-nums text-slate-400 dark:text-slate-500">{{ $index + 1 }}/{{ $total }}</span>
        </div>

        {{-- Bar progres 6 tahap: selesai hijau, aktif sesuai warna tahap, sisanya netral --}}
        <div class="flex gap-1" aria-hidden="true">
            @foreach ($keys as $i => $key)
                <span class="h-1.5 flex-1 rounded-full {{ $i < $index ? 'bg-emerald-500' : ($i === $index ? $activeBar : 'bg-slate-200 dark:bg-slate-600') }}"></span>
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
