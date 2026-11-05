{{-- FlowButton — CTA utama dengan efek "aliran" (gradient border + sheen
     yang bergerak). Port CSS murni dari gaya 21st.dev flow-button, tanpa
     React/lucide supaya tetap server-rendered & i18n aman.

     <x-flow-button as="a" href="{{ route('...') }}" icon="add" label="{{ __('Add Survey') }}" />
     <x-flow-button type="submit" icon="send" label="{{ __('Send') }}" />

     Aturan: hanya untuk SATU aksi utama per halaman. Aksi sekunder tetap
     memakai <x-icon-button> (soft). Ukuran mengikuti standar tombol §7:
     h-10 rounded-xl, ikon h-4 w-4 stroke 3.5, teks text-sm font-semibold. --}}
@props([
    'as' => 'a',
    'href' => null,
    'type' => 'button',
    'icon' => 'add',
    'label' => null,
    'title' => '',
])

@php
$paths = [
    'add' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />',
    'sparkles' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.9 15.5a2 2 0 0 0-1.4-1.4L3 12.6a.5.5 0 0 1 0-.9l5.5-1.4a2 2 0 0 0 1.4-1.4L11.4 3a.5.5 0 0 1 .9 0l1.5 5.9a2 2 0 0 0 1.4 1.4l5.9 1.5a.5.5 0 0 1 0 .9l-5.9 1.5a2 2 0 0 0-1.4 1.4l-1.5 5.9a.5.5 0 0 1-.9 0z" /><path stroke-linecap="round" d="M19 3v3M20.5 4.5h-3" />',
    'upload' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 7.5m0 0L7.5 12m4.5-4.5V21" />',
    'send' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.126A59.768 59.768 0 0 1 21.485 12 59.77 59.77 0 0 1 3.27 20.876L5.999 12zm0 0h7.5" />',
    'download' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />',
];
$ring = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/50 focus-visible:ring-offset-2';
$base = "group relative inline-flex h-10 shrink-0 items-center justify-center overflow-hidden rounded-xl p-[1.5px] transition-all duration-300 hover:shadow-lg hover:shadow-accent-500/40 hover:brightness-105 active:scale-95 {$ring}";
$aria = $title ?: $label;
@endphp

@once
<style>
    .flow-btn-ring {
        position: absolute;
        inset: 0;
        border-radius: inherit;
        background: linear-gradient(110deg,
            rgb(var(--accent-400)) 0%,
            rgb(var(--accent-600)) 25%,
            rgb(var(--accent-300)) 50%,
            rgb(var(--accent-600)) 75%,
            rgb(var(--accent-400)) 100%);
        background-size: 300% 300%;
        animation: flow-btn-shift 6s linear infinite;
    }

    .flow-btn-sheen {
        position: absolute;
        inset: 0;
        border-radius: inherit;
        background: linear-gradient(115deg,
            transparent 35%,
            rgba(255, 255, 255, 0.38) 50%,
            transparent 65%);
        background-size: 250% 100%;
        background-position: 180% 0;
        animation: flow-btn-sweep 3.8s ease-in-out infinite;
        pointer-events: none;
    }

    @keyframes flow-btn-shift {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    @keyframes flow-btn-sweep {
        0%, 62% { background-position: 180% 0; }
        100% { background-position: -80% 0; }
    }

    @media (prefers-reduced-motion: reduce) {
        .flow-btn-ring, .flow-btn-sheen { animation: none; }
        .flow-btn-sheen { display: none; }
    }
</style>
@endonce

@if($as === 'a')
    <a href="{{ $href }}" title="{{ $aria }}" aria-label="{{ $aria }}"
       {{ $attributes->merge(['class' => $base]) }}>
        <span class="flow-btn-ring" aria-hidden="true"></span>
        <span class="relative z-10 flex h-full w-full items-center justify-center gap-2 rounded-[10px] bg-accent-600 px-4 text-sm font-semibold text-white transition-colors duration-300 group-hover:bg-accent-700">
            <span class="flow-btn-sheen" aria-hidden="true"></span>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3.5" stroke="currentColor" class="relative h-4 w-4 shrink-0" aria-hidden="true">{!! $paths[$icon] ?? $paths['add'] !!}</svg>
            <span class="relative">{{ $label }}</span>
        </span>
    </a>
@else
    <button type="{{ $type }}" title="{{ $aria }}" aria-label="{{ $aria }}"
            {{ $attributes->merge(['class' => $base]) }}>
        <span class="flow-btn-ring" aria-hidden="true"></span>
        <span class="relative z-10 flex h-full w-full items-center justify-center gap-2 rounded-[10px] bg-accent-600 px-4 text-sm font-semibold text-white transition-colors duration-300 group-hover:bg-accent-700">
            <span class="flow-btn-sheen" aria-hidden="true"></span>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3.5" stroke="currentColor" class="relative h-4 w-4 shrink-0" aria-hidden="true">{!! $paths[$icon] ?? $paths['add'] !!}</svg>
            <span class="relative">{{ $label }}</span>
        </span>
    </button>
@endif
