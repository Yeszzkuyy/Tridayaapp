{{-- Autocomplete lokasi ala Google Calendar (Places API New, programmatic).
     Pasang `data-location-autocomplete` pada input teks, lalu render komponen ini
     sekali di halaman — script-nya otomatis menghubungkan semua input bertanda.

     Dropdown dibuat sendiri (bukan widget bawaan Google) supaya tetap nyambung
     dengan Alpine x-model & gaya repo. Tanpa API key / API tidak aktif →
     input tetap berfungsi biasa (degrade graceful).

     <x-location-autocomplete /> --}}
@php
    $mapsKey = (string) config('services.google-maps.api_key', '');
    $mapsRegion = (string) config('services.google-maps.region', 'id');
@endphp

@once
<script>
    window.__locAutoload = window.__locAutoload || new Promise(function (resolve) {
        if (!@json($mapsKey)) return resolve(null); // tanpa key: autocomplete nonaktif
        if (window.google && google.maps && google.maps.importLibrary) return resolve(google.maps);
        window.__locAutoloadResolve = resolve;
        var s = document.createElement('script');
        s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(@json($mapsKey))
            + '&libraries=places&loading=async&callback=__locAutoloadResolve';
        s.async = true;
        s.onerror = function () { resolve(null); };
        document.head.appendChild(s);
    });

    window.__initLocationAutocomplete = window.__initLocationAutocomplete || function (input) {
        if (!input || input.__locBound) return;
        input.__locBound = true;
        input.setAttribute('autocomplete', 'off');

        window.__locAutoload.then(async function () {
            let lib;
            try {
                lib = await google.maps.importLibrary('places');
            } catch (e) {
                return; // Places API tidak aktif → input tetap polos
            }
            const AutocompleteSuggestion = lib.AutocompleteSuggestion;
            const AutocompleteSessionToken = lib.AutocompleteSessionToken;
            if (!AutocompleteSuggestion) return; // API key lama → degrade

            const region = @json($mapsRegion);
            let token = null;
            let seq = 0;
            let active = -1;
            let items = [];
            let preds = [];
            let timer = null;

            const box = document.createElement('div');
            box.className = 'fixed z-[70] hidden max-h-60 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-xl dark:border-slate-600 dark:bg-slate-800';
            box.setAttribute('role', 'listbox');
            document.body.appendChild(box);

            const place = () => {
                const r = input.getBoundingClientRect();
                box.style.left = r.left + 'px';
                box.style.top = (r.bottom + 4) + 'px';
                box.style.width = r.width + 'px';
            };
            const hide = () => { box.classList.add('hidden'); box.innerHTML = ''; items = []; preds = []; active = -1; };

            const highlight = (idx) => {
                items.forEach((el, i) => {
                    el.className = 'block w-full cursor-pointer px-3 py-2 text-left text-sm transition'
                        + (i === idx
                            ? ' bg-accent-500/10 text-accent-700 dark:bg-accent-500/20 dark:text-accent-200'
                            : ' text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700/60');
                });
                if (items[idx]) items[idx].scrollIntoView({ block: 'nearest' });
            };

            const choose = (p) => {
                input.value = p.text.text;
                hide();
                token = null; // sesi selesai setelah pilih (biaya per-sesi)
                input.__locJustChose = true;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                input.focus();
            };

            const search = async () => {
                const q = input.value.trim();
                if (q.length < 2) { hide(); return; }
                const mySeq = ++seq;
                try {
                    token = token || new AutocompleteSessionToken();
                    const { suggestions } = await AutocompleteSuggestion.fetchAutocompleteSuggestions({
                        input: q,
                        sessionToken: token,
                        includedRegionCodes: region ? [region] : undefined,
                    });
                    if (mySeq !== seq) return;
                    box.innerHTML = '';
                    items = [];
                    active = -1;
                    (suggestions || []).forEach((s) => {
                        const p = s.placePrediction;
                        if (!p) return;
                        const el = document.createElement('button');
                        el.type = 'button';
                        el.setAttribute('role', 'option');
                        el.innerHTML = '<span class="block font-semibold"></span><span class="block text-xs text-slate-400"></span>';
                        el.children[0].textContent = p.mainText ? p.mainText.text : p.text.text;
                        el.children[1].textContent = p.secondaryText ? p.secondaryText.text : '';
                        el.addEventListener('mousedown', (e) => { e.preventDefault(); choose(p); });
                        items.push(el);
                        preds.push(p);
                        box.appendChild(el);
                    });
                    if (items.length) { place(); box.classList.remove('hidden'); active = 0; highlight(0); }
                    else hide();
                } catch (e) {
                    if (mySeq === seq) hide();
                }
            };

            input.addEventListener('input', () => {
                if (input.__locJustChose) { input.__locJustChose = false; return; }
                clearTimeout(timer);
                timer = setTimeout(search, 250);
            });
            input.addEventListener('keydown', (e) => {
                if (box.classList.contains('hidden')) return;
                if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, items.length - 1); highlight(active); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); highlight(active); }
                else if (e.key === 'Enter' && items.length) {
                    e.preventDefault();
                    const idx = active >= 0 ? active : 0;
                    if (preds[idx]) choose(preds[idx]);
                } else if (e.key === 'Escape') hide();
            });
            input.addEventListener('blur', () => setTimeout(hide, 150));
            window.addEventListener('resize', hide);
            window.addEventListener('scroll', hide, true);
        });
    };

    // Input sudah ada di DOM saat script ini dirender (server-side) → langsung
    // ikat; DOMContentLoaded jadi jaring pengaman bila komponen dirender di head.
    const __bindAll = () => document.querySelectorAll('input[data-location-autocomplete]')
        .forEach(window.__initLocationAutocomplete);
    __bindAll();
    document.addEventListener('DOMContentLoaded', __bindAll);
</script>
@endonce
