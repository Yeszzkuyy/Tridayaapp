{{-- FlowButton — port apa adanya dari template 21st.dev "flow-button"
     (https://21st.dev/@xubohuah/components/flow-button, MIT).
     Komponen aslinya 100% CSS (tanpa state/JS), jadi dipindahkan ke Blade
     tanpa React/lucide — panah memakai SVG inline.

     Efek hover: pill (rounded-[100px]) berubah jadi kotak (rounded-xl),
     lingkaran tumbuh dari tengah hingga menutup tombol, panah kanan keluar
     dan panah kiri masuk, teks bergeser.

     <x-flow-button as="a" href="{{ route('...') }}" label="{{ __('Add Survey') }}" />
     <x-flow-button type="submit" label="{{ __('Save') }}" />

     Aturan: untuk 1–2 aksi utama saja; aksi lain tetap <x-icon-button>. --}}
@props([
    'as' => 'a',
    'href' => null,
    'type' => 'button',
    'label' => null,
    'title' => '',
])

@php
$aria = $title ?: $label;

$base = 'group relative inline-flex h-10 cursor-pointer items-center justify-center gap-1 overflow-hidden rounded-[100px] border-[1.5px] border-slate-900/30 bg-transparent px-8 text-sm font-semibold text-slate-900 transition-all duration-[600ms] ease-[cubic-bezier(0.23,1,0.32,1)] hover:rounded-xl hover:border-transparent hover:text-white active:scale-[0.95] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/50 focus-visible:ring-offset-2 motion-reduce:transition-none dark:border-slate-100/40 dark:text-slate-100 dark:hover:text-slate-900';

$arrow = 'absolute z-[9] h-4 w-4 fill-none transition-all duration-[800ms] ease-[cubic-bezier(0.34,1.56,0.64,1)] motion-reduce:transition-none';

$arrowLeft = $arrow.' left-[-25%] stroke-slate-900 group-hover:left-4 group-hover:stroke-white dark:stroke-slate-100 dark:group-hover:stroke-slate-900';

$arrowRight = $arrow.' right-4 stroke-slate-900 group-hover:right-[-25%] group-hover:stroke-white dark:stroke-slate-100 dark:group-hover:stroke-slate-900';

$circle = 'absolute left-1/2 top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-[50%] bg-slate-900 opacity-0 transition-all duration-[800ms] ease-[cubic-bezier(0.19,1,0.22,1)] group-hover:h-[220px] group-hover:w-[220px] group-hover:opacity-100 motion-reduce:transition-none dark:bg-slate-100';

$text = 'relative z-[1] -translate-x-3 whitespace-nowrap transition-all duration-[800ms] ease-out group-hover:translate-x-3 motion-reduce:transition-none';

$arrowSvg = '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>';
@endphp

@if($as === 'a')
    <a href="{{ $href }}" title="{{ $aria }}" aria-label="{{ $aria }}"
       {{ $attributes->merge(['class' => $base]) }}>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="{{ $arrowLeft }}" aria-hidden="true">{!! $arrowSvg !!}</svg>
        <span class="{{ $text }}">{{ $label }}</span>
        <span class="{{ $circle }}" aria-hidden="true"></span>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="{{ $arrowRight }}" aria-hidden="true">{!! $arrowSvg !!}</svg>
    </a>
@else
    <button type="{{ $type }}" title="{{ $aria }}" aria-label="{{ $aria }}"
            {{ $attributes->merge(['class' => $base]) }}>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="{{ $arrowLeft }}" aria-hidden="true">{!! $arrowSvg !!}</svg>
        <span class="{{ $text }}">{{ $label }}</span>
        <span class="{{ $circle }}" aria-hidden="true"></span>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="{{ $arrowRight }}" aria-hidden="true">{!! $arrowSvg !!}</svg>
    </button>
@endif
