{{-- FlowButton — port template 21st.dev "flow-button" (MIT), disesuaikan
     dengan bahasa desain 3DY App (token accent + ikon kontekstual).

     Efek: pill (rounded-[100px]) mengeras jadi rounded-xl, lingkaran accent
     tumbuh dari tengah menutup tombol, ikon aksi masuk dari kiri sementara
     panah keluar ke kanan, teks bergeser.

     <x-flow-button as="a" href="{{ route('...') }}" icon="clipboard-check" label="{{ __('Add Survey') }}" />
     <x-flow-button type="submit" variant="neutral" label="{{ __('Save') }}" />

     varian : accent (default) | neutral
     icon   : nama ikon <x-icon> (add, tools, clipboard-check, file-text, dst.)
     Aturan : 1–2 aksi utama per halaman; aksi lain tetap <x-icon-button>. --}}
@props([
    'as' => 'a',
    'href' => null,
    'type' => 'button',
    'label' => null,
    'title' => '',
    'variant' => 'accent',
    'icon' => null,
])

@php
$aria = $title ?: $label;

$variant = in_array($variant, ['accent', 'neutral', 'indigo', 'emerald'], true) ? $variant : 'accent';

$palettes = [
    'accent' => [
        'button' => 'border-accent-600/40 text-accent-700 hover:text-white dark:border-accent-400/40 dark:text-accent-300 dark:hover:text-white',
        'circle' => 'bg-accent-600 dark:bg-accent-500',
    ],
    'indigo' => [
        'button' => 'border-indigo-600/40 text-indigo-700 hover:text-white dark:border-indigo-400/40 dark:text-indigo-300 dark:hover:text-white',
        'circle' => 'bg-indigo-600 dark:bg-indigo-500',
    ],
    'emerald' => [
        'button' => 'border-emerald-600/40 text-emerald-700 hover:text-white dark:border-emerald-400/40 dark:text-emerald-300 dark:hover:text-white',
        'circle' => 'bg-emerald-600 dark:bg-emerald-500',
    ],
    'neutral' => [
        'button' => 'border-slate-300 text-slate-700 hover:text-white dark:border-slate-600 dark:text-slate-200 dark:hover:text-white',
        'circle' => 'bg-slate-800 dark:bg-slate-700',
    ],
];
$palette = $palettes[$variant];

$base = 'group relative inline-flex h-10 cursor-pointer items-center justify-center gap-1 overflow-hidden rounded-[100px] border-[1.5px] bg-transparent px-8 text-sm font-semibold transition-all duration-[600ms] ease-[cubic-bezier(0.23,1,0.32,1)] hover:rounded-xl hover:border-transparent active:scale-[0.95] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/50 focus-visible:ring-offset-2 motion-reduce:transition-none '.$palette['button'];

$iconLeft = 'absolute left-[-25%] z-[9] h-4 w-4 transition-all duration-[800ms] ease-[cubic-bezier(0.34,1.56,0.64,1)] group-hover:left-4 motion-reduce:transition-none';

$iconRight = 'absolute right-4 z-[9] h-4 w-4 transition-all duration-[800ms] ease-[cubic-bezier(0.34,1.56,0.64,1)] group-hover:right-[-25%] motion-reduce:transition-none';

$circle = 'absolute left-1/2 top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-[50%] opacity-0 transition-all duration-[800ms] ease-[cubic-bezier(0.19,1,0.22,1)] group-hover:h-[220px] group-hover:w-[220px] group-hover:opacity-100 motion-reduce:transition-none '.$palette['circle'];

$text = 'relative z-[1] -translate-x-3 whitespace-nowrap transition-all duration-[800ms] ease-out group-hover:translate-x-3 motion-reduce:transition-none';

// Peta ikon kecil untuk nama yang paling sering dipakai; nama lain jatuh ke
// <x-icon> (set ikon aplikasi lebih lengkap) supaya tidak pernah kosong.
$paths = [
    'add' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />',
    'tools' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14.5 6.2a3.6 3.6 0 00-4.9 4.9L4 16.7l3.3 3.3 5.6-5.6a3.6 3.6 0 004.9-4.9l-2.35 2.35-1.8-1.8 2.35-2.35z" />',
    'clipboard-check' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9 2 2 4-4" />',
    'file-text' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" /><path d="M14 2v4a2 2 0 0 0 2 2h4" /><path d="M10 9H8" /><path d="M16 13H8" /><path d="M16 17H8" />',
    'send' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.126A59.768 59.768 0 0 1 21.485 12 59.77 59.77 0 0 1 3.27 20.876L5.999 12zm0 0h7.5" />',
    'upload' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 7.5m0 0L7.5 12m4.5-4.5V21" />',
    'download' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />',
    'sparkles' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.9 15.5a2 2 0 0 0-1.4-1.4L3 12.6a.5.5 0 0 1 0-.9l5.5-1.4a2 2 0 0 0 1.4-1.4L11.4 3a.5.5 0 0 1 .9 0l1.5 5.9a2 2 0 0 0 1.4 1.4l5.9 1.5a.5.5 0 0 1 0 .9l-5.9 1.5a2 2 0 0 0-1.4 1.4l-1.5 5.9a.5.5 0 0 1-.9 0z" />',
];
@endphp

@if($as === 'a')
    <a href="{{ $href }}" title="{{ $aria }}" aria-label="{{ $aria }}"
       {{ $attributes->merge(['class' => $base]) }}>
        @if($icon)
            @if(isset($paths[$icon]))
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" class="{{ $iconLeft }}" aria-hidden="true">{!! $paths[$icon] !!}</svg>
            @else
                <x-icon :name="$icon" :stroke-width="2.5" class="{{ $iconLeft }}" />
            @endif
        @endif
        <span class="{{ $text }}">{{ $label }}</span>
        <span class="{{ $circle }}" aria-hidden="true"></span>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" class="{{ $iconRight }}" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
    </a>
@else
    <button type="{{ $type }}" title="{{ $aria }}" aria-label="{{ $aria }}"
            {{ $attributes->merge(['class' => $base]) }}>
        @if($icon)
            @if(isset($paths[$icon]))
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" class="{{ $iconLeft }}" aria-hidden="true">{!! $paths[$icon] !!}</svg>
            @else
                <x-icon :name="$icon" :stroke-width="2.5" class="{{ $iconLeft }}" />
            @endif
        @endif
        <span class="{{ $text }}">{{ $label }}</span>
        <span class="{{ $circle }}" aria-hidden="true"></span>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" class="{{ $iconRight }}" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
    </button>
@endif
