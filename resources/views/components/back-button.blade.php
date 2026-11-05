{{-- BackButton — gaya khusus untuk navigasi "kembali".
     Berbeda dari flow button: bentuk tetap rounded-xl, ikon berada di "well"
     yang berubah accent saat hover, dan garis kecepatan (motion lines) di
     belakang panah memanjang sehingga terasa "mundur".

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

$base = 'group/back relative inline-flex h-10 shrink-0 items-center gap-2 overflow-hidden rounded-xl border border-slate-200 bg-white pl-2 pr-3.5 text-sm font-semibold text-slate-700 shadow-sm transition-all duration-300 hover:border-accent-300 hover:bg-accent-50 hover:text-accent-700 hover:shadow active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/50 focus-visible:ring-offset-2 motion-reduce:transition-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-accent-500/50 dark:hover:bg-accent-500/10 dark:hover:text-accent-300';

$well = 'relative flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-slate-500 transition-colors duration-300 group-hover/back:bg-accent-600 group-hover/back:text-white motion-reduce:transition-none dark:bg-slate-700/70 dark:text-slate-300 dark:group-hover/back:bg-accent-500';

$line = 'absolute left-1 h-[1.5px] rounded-full bg-current transition-all duration-300 motion-reduce:transition-none';
@endphp

@if($as === 'a')
    <a href="{{ $href }}" title="{{ $aria }}" aria-label="{{ $aria }}"
       {{ $attributes->merge(['class' => $base]) }}>
        <span class="{{ $well }}" aria-hidden="true">
            <span class="{{ $line }} top-[8px] w-1.5 opacity-40 group-hover/back:w-3 group-hover/back:opacity-60"></span>
            <span class="{{ $line }} top-1/2 w-2.5 -translate-y-1/2 opacity-60 group-hover/back:w-4 group-hover/back:opacity-80"></span>
            <span class="{{ $line }} bottom-[8px] w-1.5 opacity-40 group-hover/back:w-3 group-hover/back:opacity-60"></span>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"
                 class="relative ml-1.5 h-3.5 w-3.5 rotate-180 transition-transform duration-300 group-hover/back:-translate-x-0.5 motion-reduce:transition-none">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </span>
        <span class="whitespace-nowrap">{{ $text }}</span>
    </a>
@else
    <button type="{{ $type }}" title="{{ $aria }}" aria-label="{{ $aria }}"
            {{ $attributes->merge(['class' => $base]) }}>
        <span class="{{ $well }}" aria-hidden="true">
            <span class="{{ $line }} top-[8px] w-1.5 opacity-40 group-hover/back:w-3 group-hover/back:opacity-60"></span>
            <span class="{{ $line }} top-1/2 w-2.5 -translate-y-1/2 opacity-60 group-hover/back:w-4 group-hover/back:opacity-80"></span>
            <span class="{{ $line }} bottom-[8px] w-1.5 opacity-40 group-hover/back:w-3 group-hover/back:opacity-60"></span>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"
                 class="relative ml-1.5 h-3.5 w-3.5 rotate-180 transition-transform duration-300 group-hover/back:-translate-x-0.5 motion-reduce:transition-none">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </span>
        <span class="whitespace-nowrap">{{ $text }}</span>
    </button>
@endif
