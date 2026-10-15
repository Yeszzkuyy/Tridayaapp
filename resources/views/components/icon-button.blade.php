{{-- Tombol ikon kotak pastel — putih saat idle, berwarna saat hover (gaya tombol
     aksi di menu Project). Tinggal panggil, tanpa utak-atik detail:

     <x-icon-button icon="filter" type="submit" title="Filter" />
     <x-icon-button as="a" icon="reset" href="{{ route('marketing.dashboard') }}" title="Reset" />
     <x-icon-button as="a" icon="view" href="{{ route('leads.show', $lead) }}" title="View" />
     <x-icon-button as="a" icon="edit" href="{{ route('leads.edit', $lead) }}" title="Edit" />
     <x-icon-button icon="delete" type="submit" title="Delete" />
     <x-icon-button as="a" icon="add" href="{{ route('leads.create') }}" title="Add" />
     <x-icon-button as="a" icon="back" href="{{ route('leads.index') }}" title="Back" />

     Props:
       as      : button | a            (default: button)
       href    : url, wajib bila as=a
       type    : submit | button ...    (default: button)
       icon    : filter | reset | leads | add | import | back | sparkles
                 | view | edit | delete | search | download | print | mail
                 | more                 (semua SVG inline, ikut warna teks;
                                        kosongkan + isi slot untuk ikon sendiri)
       variant : default | delete      (default: ngikut icon — delete otomatis
                                        merah; nama lama filter/reset tetap
                                        diterima, dipetakan ke default)
       size    : md (= h-10 w-10) | lg (= h-11 w-11)
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
    'delete' => 'delete',
    default => 'default',
};
// Nama varian lama tetap diterima biar view lama tidak rusak.
if (!in_array($variant, ['default', 'delete'], true)) {
    $variant = 'default';
}
$tooltip ??= $title;

$colors = $variant === 'delete'
    ? 'bg-red-50 text-red-600 hover:bg-red-100 dark:bg-red-500/15 dark:text-red-400 dark:hover:bg-red-500/25'
    : 'bg-accent-50 text-accent-700 hover:bg-accent-100 dark:bg-accent-500/15 dark:text-accent-300 dark:hover:bg-accent-500/25';

$sizes = [
    'md' => 'h-10 w-10',
    'lg' => 'h-11 w-11',
];
$sizeClass = $sizes[$size] ?? $sizes['md'];

$ring = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 focus-visible:ring-offset-2';

$btnClass = "group/btn relative inline-flex {$sizeClass} items-center justify-center overflow-hidden rounded-lg {$colors} transition-all duration-300 hover:scale-110 hover:shadow-md active:scale-95 {$ring}";

// Label: tombol jadi lebih lebar (ikon + teks) dengan gap, bukan kotak icon-only.
if ($label) {
    $btnClass = "group/btn relative inline-flex h-10 items-center justify-center gap-2 overflow-hidden rounded-lg px-4 text-sm font-medium {$colors} transition-all duration-300 hover:scale-105 hover:shadow-md active:scale-95 {$ring}";
}

$paths = [
    'leads' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01" />',
    'add' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />',
    'sparkles' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.9 15.5a2 2 0 0 0-1.4-1.4L3 12.6a.5.5 0 0 1 0-.9l5.5-1.4a2 2 0 0 0 1.4-1.4L11.4 3a.5.5 0 0 1 .9 0l1.5 5.9a2 2 0 0 0 1.4 1.4l5.9 1.5a.5.5 0 0 1 0 .9l-5.9 1.5a2 2 0 0 0-1.4 1.4l-1.5 5.9a.5.5 0 0 1-.9 0z" /><path stroke-linecap="round" d="M19 3v3M20.5 4.5h-3" />',
    'import' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />',
    'download' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />',
    'upload' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 7.5m0 0L7.5 12m4.5-4.5V21" />',
    'back' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />',
    'filter' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3z" />',
    'reset' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992m-.002-4.992v4.992M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />',
    'search' => '<path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607z" />',
    'view' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />',
    'edit' => '<path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />',
    'delete' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />',
    'print' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659" />',
    'mail' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />',
    'more' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5zM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5zM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5z" />',
];

$shine = '<span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover/btn:translate-x-full" aria-hidden="true"></span>';
@endphp

<div class="group relative">
    @if($as === 'a')
        <a href="{{ $href }}" title="{{ $title }}" aria-label="{{ $title }}"
           {{ $attributes->merge(['class' => $btnClass]) }}>
            @if($slot->isNotEmpty())
                {{ $slot }}
            @elseif(isset($paths[$icon]))
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true">{!! $paths[$icon] !!}</svg>
            @endif
            @if($label)
                <span>{{ $label }}</span>
            @endif
            {!! $shine !!}
        </a>
    @else
        <button type="{{ $type }}" title="{{ $title }}" aria-label="{{ $title }}"
                {{ $attributes->merge(['class' => $btnClass]) }}>
            @if($slot->isNotEmpty())
                {{ $slot }}
            @elseif(isset($paths[$icon]))
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true">{!! $paths[$icon] !!}</svg>
            @endif
            @if($label)
                <span>{{ $label }}</span>
            @endif
            {!! $shine !!}
        </button>
    @endif
    @if($tooltip)
        <span class="pointer-events-none absolute left-1/2 top-full z-10 mt-2 -translate-x-1/2 -translate-y-1 whitespace-nowrap rounded-lg bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 shadow-lg transition-all duration-200 group-hover:translate-y-0 group-hover:opacity-100 dark:bg-slate-700" role="tooltip">{{ $tooltip }}</span>
    @endif
</div>
