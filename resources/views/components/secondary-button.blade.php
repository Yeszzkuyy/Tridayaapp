{{-- Tombol Cancel (batal di form/modal). Gaya solid accent + shine. --}}
@props([
    'as' => 'button',
    'type' => 'button',
    'href' => null,
])

@php
$classes = 'group relative inline-flex items-center justify-center overflow-hidden rounded-xl bg-accent-500 px-6 py-2.5 text-center text-sm font-medium text-white transition-all duration-300 hover:scale-105 hover:bg-accent-600 hover:shadow-lg hover:shadow-accent-500/30 active:scale-95';
@endphp

@if($as === 'a')
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
    </button>
@endif
