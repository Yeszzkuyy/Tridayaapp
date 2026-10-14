{{-- Tombol ikon untuk membuka isi sebuah input di Google Maps (search),
     atau langsung membukanya bila ternyata URL (Zoom/Meet/dll).
     Dipakai di form: pasangkan dengan id input lewat prop `input`.

     <x-icon-button ...>  standar repo; tombol ini tidak mengirim form.

     <x-maps-open-button input="fu-location" />
--}}
@props([
    'input' => null,
    'title' => null,
])

@php $title ??= __('Open in Google Maps'); @endphp

<x-icon-button :title="$title" {{ $attributes }} :data-maps-input="$input" onclick="window.__mapsOpen(this)">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
</x-icon-button>

@once
<script>
    window.__mapsOpen = window.__mapsOpen || function (btn) {
        var input = document.getElementById(btn.getAttribute('data-maps-input'));
        var v = input ? String(input.value || '').trim() : '';
        if (!v) return;
        var isUrl = /^https?:\/\//i.test(v) || (/^([a-z0-9-]+\.)+[a-z]{2,}(\/.*)?$/i.test(v) && !/\s/.test(v));
        var url = isUrl
            ? (/^https?:\/\//i.test(v) ? v : 'https://' + v)
            : 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(v);
        window.open(url, '_blank', 'noopener');
    };
</script>
@endonce
