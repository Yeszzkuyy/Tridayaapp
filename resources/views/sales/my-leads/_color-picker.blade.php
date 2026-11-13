{{-- Tombol warna lead: titik + label kecil, diletakkan dekat nama customer. --}}
<x-dropdown align="left" width="w-44">
    <x-slot name="trigger">
        <button type="button"
                class="inline-flex h-6 items-center gap-1 rounded-full border border-slate-200 bg-[var(--card-bg)] px-1.5 text-[10px] font-semibold text-slate-500 transition hover:border-accent-300 hover:text-accent-600 active:scale-95 dark:border-slate-600 dark:text-slate-300 dark:hover:text-accent-400"
                title="{{ __('Change lead color') }}" aria-label="{{ __('Change lead color') }}">
            <span class="h-2.5 w-2.5 rounded-full ring-1 ring-black/10 dark:ring-white/20" :class="colorDotClass()" aria-hidden="true"></span>
            {{ __('Color') }}
        </button>
    </x-slot>
    <x-slot name="content">
        <p class="px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Lead color') }}</p>
        @foreach(['default' => __('Default'), 'red' => __('Red'), 'yellow' => __('Yellow'), 'green' => __('Green')] as $value => $label)
            <button type="button" x-on:click="setColor('{{ $value }}')"
                    class="flex w-full items-center gap-2 px-3 py-2 text-start text-sm text-slate-700 transition hover:bg-[var(--menu-hover-bg)] dark:text-slate-200">
                <span class="h-3 w-3 rounded-full {{ \App\Models\Lead::colorDotClasses($value === 'default' ? null : $value) }}"></span>
                <span class="flex-1">{{ $label }}</span>
                <span x-show="color === '{{ $value === 'default' ? '' : $value }}'" class="text-accent-600 dark:text-accent-400">&#10003;</span>
            </button>
        @endforeach
    </x-slot>
</x-dropdown>
