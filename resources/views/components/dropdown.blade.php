@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1 bg-white'])

@php
$width = match ($width) {
    '48' => 'w-48',
    default => $width,
};
@endphp

{{-- Panel di-teleport ke body + fixed agar tidak terpotong ancestor
     ber-overflow (mis. tabel overflow-x-auto). Posisi dihitung dari
     trigger saat dibuka; menu ditutup saat scroll/resize/Esc/klik luar.
     :style WAJIB object (string menimpa display:none milik x-show). --}}
<div class="relative"
     x-data="{
        open: false,
        panelTop: 0,
        panelLeft: 0,
        place() {
            const trigger = this.$refs.trigger;
            const panel = this.$refs.panel;
            if (! trigger || ! panel) return;
            const r = trigger.getBoundingClientRect();
            const w = panel.offsetWidth || 208;
            const h = panel.offsetHeight || 0;
            let left = @js($align) === 'left' ? r.left : r.right - w;
            left = Math.min(Math.max(left, 8), Math.max(window.innerWidth - w - 8, 8));
            let top = r.bottom + 8;
            if (top + h > window.innerHeight - 8) top = Math.max(r.top - h - 8, 8);
            this.panelTop = Math.round(top);
            this.panelLeft = Math.round(left);
        },
     }"
     @click.outside="open = false"
     @close.stop="open = false"
     @keydown.escape.window="open = false"
     @scroll.window.capture="open = false"
     @resize.window="open = false">
    <div x-ref="trigger" @click="open = ! open; if (open) $nextTick(() => place())">
        {{ $trigger }}
    </div>

    <template x-teleport="body">
        <div x-show="open" x-cloak
             x-ref="panel"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-75"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="fixed z-[100] {{ $width }} rounded-md shadow-lg"
             :style="{ top: panelTop + 'px', left: panelLeft + 'px' }"
             @click="open = false">
            <div class="rounded-md ring-1 ring-black ring-opacity-5 {{ $contentClasses }}">
                {{ $content }}
            </div>
        </div>
    </template>
</div>
