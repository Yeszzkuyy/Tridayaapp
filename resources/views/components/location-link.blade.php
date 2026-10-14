{{-- Link lokasi ala Google Calendar: klik teks = buka Google Maps (search),
     tombol panah = Google Maps Directions (navigasi), opsional preview peta.
     Bila isi lokasi ternyata URL (Zoom/Meet/dll) → langsung dibuka sebagai link.

     <x-location-link :location="$fu->location" />           teks + tombol arah
     <x-location-link :location="$fu->location" preview />   + preview peta
     <x-location-link :location="$fu->location" compact />   versi tabel (link saja)
--}}
@props([
    'location' => null,
    'preview' => false,
    'compact' => false,
])

@php
$loc = trim((string) $location);

// URL meeting online / link Google Maps → buka langsung, bukan dicari di Maps.
$isUrl = $loc !== '' && (
    preg_match('#^https?://#i', $loc)
    || (preg_match('#^([a-z0-9-]+\.)+[a-z]{2,}(/.*)?$#i', $loc) && !str_contains($loc, ' '))
);

$href = $isUrl
    ? (preg_match('#^https?://#i', $loc) ? $loc : 'https://'.$loc)
    : 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($loc);
$dirUrl = 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($loc).'&travelmode=driving';
$embedUrl = 'https://maps.google.com/maps?q='.rawurlencode($loc).'&z=15&output=embed';
@endphp

@if ($loc === '')
    <span {{ $attributes->merge(['class' => 'text-slate-400']) }}>-</span>
@elseif ($compact)
    <a href="{{ $href }}" target="_blank" rel="noopener" title="{{ $isUrl ? __('Open meeting link') : __('Open in Google Maps') }}"
       {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-accent-600 hover:text-accent-500 hover:underline dark:text-accent-400']) }}>
        <x-icon name="map-pin" class="h-3.5 w-3.5 shrink-0" />
        <span class="max-w-[14rem] truncate">{{ $loc }}</span>
    </a>
@else
    <div {{ $attributes->merge(['class' => 'flex items-start gap-2']) }}>
        <x-icon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
        <div class="min-w-0 flex-1">
            <a href="{{ $href }}" target="_blank" rel="noopener" title="{{ $isUrl ? __('Open meeting link') : __('Open in Google Maps') }}"
               class="block break-words text-sm font-medium text-accent-600 hover:underline dark:text-accent-400">{{ $loc }}</a>
            @if ($preview && ! $isUrl)
                <div class="mt-2 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-600">
                    <iframe src="{{ $embedUrl }}" class="h-40 w-full" loading="lazy" style="border:0"
                            referrerpolicy="no-referrer-when-downgrade" title="{{ __('Map preview') }}" allowfullscreen></iframe>
                </div>
            @endif
        </div>
        @if (! $isUrl)
            <x-icon-button as="a" :href="$dirUrl" target="_blank" rel="noopener" title="{{ __('Directions') }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.5 11.5 20.5 4l-7.5 16.5-2-6.5-7.5-2.5z" /></svg>
            </x-icon-button>
        @endif
    </div>
@endif
