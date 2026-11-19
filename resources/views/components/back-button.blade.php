{{-- BackButton v3 — panel accent meluncur dari kiri saat hover.
     Idle: surface token tema (bukan bg-white, agar tidak kena override
     dark-mode `!important`), panah accent. Hover: panel accent-600 menggeser
     masuk dari kiri (bukan fade/grow), teks & panah jadi putih, panah
     bergeser kiri dengan sedikit pantulan + bayangan panah tertinggal.

     <x-back-button href="{{ route('customers.index') }}" />
     <x-back-button :href="route('...')" label="{{ __('Back to Customer') }}" />

     Kandidat gaya utama tombol Back. Jangan pakai bg-white di sini:
     `.dark .bg-white{...!important}` mematikan hover di dark mode. --}}
@props([
    'as' => 'a',
    'href' => null,
    'type' => 'button',
    'label' => null,
    'title' => '',
])

@php
$aria = $title ?: ($label ?: __('Back'));
$text = $label ?: __('Back');

$base = 'group/back relative inline-flex h-10 shrink-0 items-center gap-2 overflow-hidden rounded-xl border border-slate-200 bg-[var(--card-bg)] pl-3 pr-4 text-sm font-semibold text-slate-700 shadow-sm transition-all duration-300 hover:border-accent-600 hover:shadow-md hover:shadow-accent-500/20 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/50 focus-visible:ring-offset-2 motion-reduce:transition-none dark:border-slate-700 dark:text-slate-200 dark:hover:border-accent-500 dark:hover:shadow-accent-500/20';

$panel = 'pointer-events-none absolute inset-0 -translate-x-full bg-accent-600 transition-transform duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] group-hover/back:translate-x-0 motion-reduce:transition-none';

$arrow = 'h-4 w-4 rotate-180 text-accent-600 transition-all duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)] group-hover/back:-translate-x-1 group-hover/back:text-white motion-reduce:transition-none dark:text-accent-400';

$ghostArrow = 'absolute left-0 h-4 w-4 rotate-180 text-white opacity-0 transition-all delay-100 duration-300 ease-out group-hover/back:-translate-x-1.5 group-hover/back:opacity-40 motion-reduce:transition-none';

$textClass = 'relative z-10 whitespace-nowrap transition-colors duration-300 group-hover/back:text-white';
@endphp

@if($as === 'a')
    <a href="{{ $href }}" title="{{ $aria }}" aria-label="{{ $aria }}"
       {{ $attributes->merge(['class' => $base]) }}>
        <span class="{{ $panel }}" aria-hidden="true"></span>
        <span class="relative z-10 flex h-4 w-4 shrink-0 items-center justify-center" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.75" stroke="currentColor" class="{{ $ghostArrow }}">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.75" stroke="currentColor" class="{{ $arrow }}">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </span>
        <span class="{{ $textClass }}">{{ $text }}</span>
    </a>
@else
    <button type="{{ $type }}" title="{{ $aria }}" aria-label="{{ $aria }}"
            {{ $attributes->merge(['class' => $base]) }}>
        <span class="{{ $panel }}" aria-hidden="true"></span>
        <span class="relative z-10 flex h-4 w-4 shrink-0 items-center justify-center" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.75" stroke="currentColor" class="{{ $ghostArrow }}">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.75" stroke="currentColor" class="{{ $arrow }}">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </span>
        <span class="{{ $textClass }}">{{ $text }}</span>
    </button>
@endif
