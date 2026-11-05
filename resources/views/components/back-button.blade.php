{{-- BackButton — gaya khusus navigasi "kembali" (revisi: hover kontras).
     Idle: surface terang + well abu. Hover: seluruh tombol terisi accent
     penuh (teks/ikon putih), well jadi kaca putih, garis kecepatan memanjang,
     panah bergeser kiri + tombol terangkat. Jadi perubahan statusnya jelas.

     <x-back-button href="{{ route('customers.index') }}" />
     <x-back-button :href="route('...')" label="{{ __('Back to Customer') }}" />

     Kandidat gaya utama tombol Back — belum dipakai di semua halaman. --}}
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

$base = 'group/back relative inline-flex h-10 shrink-0 items-center gap-2 overflow-hidden rounded-xl border border-slate-200 bg-white pl-1.5 pr-4 text-sm font-semibold text-slate-700 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:border-accent-600 hover:bg-accent-600 hover:text-white hover:shadow-lg hover:shadow-accent-500/30 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/50 focus-visible:ring-offset-2 motion-reduce:transition-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-accent-500 dark:hover:bg-accent-600 dark:hover:text-white dark:hover:shadow-accent-500/25';

$well = 'relative flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-slate-500 transition-colors duration-300 group-hover/back:bg-white/20 group-hover/back:text-white motion-reduce:transition-none dark:bg-slate-700/70 dark:text-slate-300 dark:group-hover/back:bg-white/20 dark:group-hover/back:text-white';

$line = 'absolute left-1.5 h-[1.5px] rounded-full bg-current transition-all duration-300 motion-reduce:transition-none';

$sheen = 'pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/35 to-transparent transition-transform duration-700 ease-out group-hover/back:translate-x-full';
@endphp

@if($as === 'a')
    <a href="{{ $href }}" title="{{ $aria }}" aria-label="{{ $aria }}"
       {{ $attributes->merge(['class' => $base]) }}>
        <span class="{{ $well }}" aria-hidden="true">
            <span class="{{ $line }} top-[9px] w-2 opacity-45 group-hover/back:w-4 group-hover/back:opacity-70"></span>
            <span class="{{ $line }} top-1/2 w-3.5 -translate-y-1/2 opacity-70 group-hover/back:w-5 group-hover/back:opacity-90"></span>
            <span class="{{ $line }} bottom-[9px] w-2 opacity-45 group-hover/back:w-4 group-hover/back:opacity-70"></span>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.75" stroke="currentColor"
                 class="relative ml-2 h-4 w-4 rotate-180 transition-transform duration-300 group-hover/back:-translate-x-1 motion-reduce:transition-none">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </span>
        <span class="relative z-10 whitespace-nowrap">{{ $text }}</span>
        <span class="{{ $sheen }}" aria-hidden="true"></span>
    </a>
@else
    <button type="{{ $type }}" title="{{ $aria }}" aria-label="{{ $aria }}"
            {{ $attributes->merge(['class' => $base]) }}>
        <span class="{{ $well }}" aria-hidden="true">
            <span class="{{ $line }} top-[9px] w-2 opacity-45 group-hover/back:w-4 group-hover/back:opacity-70"></span>
            <span class="{{ $line }} top-1/2 w-3.5 -translate-y-1/2 opacity-70 group-hover/back:w-5 group-hover/back:opacity-90"></span>
            <span class="{{ $line }} bottom-[9px] w-2 opacity-45 group-hover/back:w-4 group-hover/back:opacity-70"></span>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.75" stroke="currentColor"
                 class="relative ml-2 h-4 w-4 rotate-180 transition-transform duration-300 group-hover/back:-translate-x-1 motion-reduce:transition-none">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </span>
        <span class="relative z-10 whitespace-nowrap">{{ $text }}</span>
        <span class="{{ $sheen }}" aria-hidden="true"></span>
    </button>
@endif
