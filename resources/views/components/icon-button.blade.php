{{-- Tombol ikon kotak + slash shine saat hover (efek ala menu customer / manage sales).
     Tinggal panggil, tanpa utak-atik detail:

     <x-icon-button icon="filter" type="submit" title="Filter" />
     <x-icon-button as="a" icon="reset" href="{{ route('marketing.dashboard') }}" title="Reset" />
     <x-icon-button as="a" icon="leads" href="{{ route('leads.index') }}" title="Lihat Lead" />
     <x-icon-button as="a" icon="add" href="{{ route('leads.create') }}" title="Tambah Lead" />
     <x-icon-button as="a" icon="back" href="{{ route('leads.index') }}" title="Kembali" />

     Props:
       as      : button | a            (default: button)
       href    : url, wajib bila as=a
       type    : submit | button ...    (default: button)
        icon    : filter | reset | leads | add | import | back | sparkles
                                        (filter & reset = PNG statis;
                                         lead aksi tabel = SVG; kosongkan + isi slot
                                         untuk ikon sendiri)
       variant : filter | reset         (default: ngikut icon; atur manual bila pakai slot)
       size    : md (= h-10 w-10) | lg (= h-11 w-11, ala tombol Cari customer)
       title   : tooltip + title/aria-label
       tooltip : teks tooltip (default: = title; kosong = tanpa tooltip)
       label   : teks nama di samping ikon (bila diisi tombol jadi lebih lebar,
                 bukan kotak icon-only)
--}}
@props([
    'as' => 'button',
    'href' => null,
    'type' => 'button',
    'icon' => null,
    'variant' => null,
    'size' => 'md',
    'title' => '',
    'tooltip' => null,
    'label' => null,
])

@php
$variant ??= match ($icon) {
    'reset', 'import', 'back' => 'reset',
    default => 'filter',
};
$tooltip ??= $title;

$colors = [
    'filter' => 'bg-accent-600 hover:bg-accent-500',
    'reset' => 'bg-accent-500 hover:bg-accent-400',
][$variant] ?? 'bg-accent-600 hover:bg-accent-500';

$sizes = [
    'md' => 'h-10 w-10',
    'lg' => 'h-11 w-11',
];
$sizeClass = $sizes[$size] ?? $sizes['md'];

$btnClass = "relative inline-flex {$sizeClass} items-center justify-center overflow-hidden rounded-xl text-white shadow-sm transition-all duration-300 hover:scale-110 hover:shadow-lg hover:shadow-accent-500/40 hover:brightness-110 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 focus-visible:ring-offset-2 active:scale-95 {$colors}";

// Label: tombol jadi lebih lebar (ikon + teks) dengan gap, bukan kotak icon-only.
if ($label) {
    $btnClass = "relative inline-flex h-10 items-center justify-center gap-2 overflow-hidden rounded-xl px-4 text-sm font-medium text-white shadow-sm transition-all duration-300 hover:scale-110 hover:shadow-lg hover:shadow-accent-500/40 hover:brightness-110 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 focus-visible:ring-offset-2 active:scale-95 {$colors}";
}

$paths = [
    'leads' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01" />',
    'add' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />',
    'sparkles' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.9 15.5a2 2 0 0 0-1.4-1.4L3 12.6a.5.5 0 0 1 0-.9l5.5-1.4a2 2 0 0 0 1.4-1.4L11.4 3a.5.5 0 0 1 .9 0l1.5 5.9a2 2 0 0 0 1.4 1.4l5.9 1.5a.5.5 0 0 1 0 .9l-5.9 1.5a2 2 0 0 0-1.4 1.4l-1.5 5.9a.5.5 0 0 1-.9 0z" /><path stroke-linecap="round" d="M19 3v3M20.5 4.5h-3" />',
    'import' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />',
    'back' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />',
];

// filter & reset = PNG statis (GIF hover dihapus demi hemat ~140KB per tombol)
$gifIcons = ['filter' => 'filter', 'reset' => 'reset'];
@endphp

<div class="group relative">
    @if($as === 'a')
        <a href="{{ $href }}" title="{{ $title }}" aria-label="{{ $title }}"
           {{ $attributes->merge(['class' => $btnClass]) }}>
            @if($slot->isNotEmpty())
                {{ $slot }}
            @elseif(isset($gifIcons[$icon]))
                <img src="{{ asset('icons/' . $gifIcons[$icon] . '.png') }}" alt="" loading="lazy" decoding="async" width="20" height="20"
                     class="block h-5 w-5" />
            @elseif(isset($paths[$icon]))
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true">{!! $paths[$icon] !!}</svg>
            @endif
            @if($label)
                <span>{{ $label }}</span>
            @endif
            <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
        </a>
    @else
        <button type="{{ $type }}" title="{{ $title }}" aria-label="{{ $title }}"
                {{ $attributes->merge(['class' => $btnClass]) }}>
            @if($slot->isNotEmpty())
                {{ $slot }}
            @elseif(isset($gifIcons[$icon]))
                <img src="{{ asset('icons/' . $gifIcons[$icon] . '.png') }}" alt="" loading="lazy" decoding="async" width="20" height="20"
                     class="block h-5 w-5" />
            @elseif(isset($paths[$icon]))
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true">{!! $paths[$icon] !!}</svg>
            @endif
            @if($label)
                <span>{{ $label }}</span>
            @endif
            <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
        </button>
    @endif
    @if($tooltip)
        <span class="pointer-events-none absolute left-1/2 top-full z-10 mt-2 -translate-x-1/2 -translate-y-1 whitespace-nowrap rounded-lg bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 shadow-lg transition-all duration-200 group-hover:translate-y-0 group-hover:opacity-100 dark:bg-slate-700" role="tooltip">{{ $tooltip }}</span>
    @endif
</div>
