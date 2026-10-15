import { createRoot } from 'react-dom/client';
import { useEffect, useState } from 'react';
import RubberSegment from './components/RubberSegment.jsx';
import './components/RubberSegment.css';

// Island entry: me-mount RubberSegment di elemen bertanda `data-rubber-segment`
// (atau #marketing-range-segment untuk kompatibilitas lama).
//
// Konfigurasi lewat data-*:
//   data-items      : JSON [{value,label,url?}] atau array label string
//   data-active     : index ATAU value yang aktif (default 0)
//   data-mode       : 'navigate' (klik = pindah ke url item) | 'event'
//                     (klik = dispatch CustomEvent, untuk kontrol state lokal)
//   data-event-name : nama event untuk mode 'event' (default 'rubber-segment:change'),
//                     detail: { id, value, index }
//   data-label      : aria-label segmen
//   data-size/radius: ukuran (md|lg) & radius (default 10)
//
// Sinkronisasi dari luar (mis. state Alpine yang berubah dari jalur lain):
//   window.dispatchEvent(new CustomEvent('rubber-segment:set',
//     { detail: { id: '<elemen id>', value: '<value item>' } }))

function accentColor() {
    try {
        const c = window.getAppearanceColors?.();
        if (c?.accent600) return c.accent600;
    } catch {}
    const raw = getComputedStyle(document.documentElement).getPropertyValue('--accent-600').trim();
    return raw ? `rgb(${raw.replace(/\s+/g, ', ')})` : '#2563eb';
}

// Wrapper controlled: value bisa digerakkan dari luar lewat event 'rubber-segment:set'.
function SegmentIsland({ id, items, defaultValue, onChange, colors }) {
    const [value, setValue] = useState(defaultValue);
    useEffect(() => {
        const handler = (e) => {
            if (e?.detail?.id === id) setValue(e.detail.value);
        };
        window.addEventListener('rubber-segment:set', handler);
        return () => window.removeEventListener('rubber-segment:set', handler);
    }, [id]);
    return (
        <RubberSegment
            items={items}
            value={value}
            onChange={(v, i) => {
                setValue(v);
                onChange?.(v, i);
            }}
            {...colors}
        />
    );
}

function mountOne(el) {
    if (!el || el.dataset.mounted) return;
    let raw = [];
    try {
        raw = JSON.parse(el.dataset.items ?? '[]');
    } catch {}
    if (!raw.length) return;
    el.dataset.mounted = 'true';

    const list = raw.map((it) =>
        typeof it === 'string' ? { value: it, label: it } : { value: it.value ?? it.label, label: it.label, url: it.url }
    );
    const activeRaw = String(el.dataset.active ?? '0');
    const activeIdx = /^\d+$/.test(activeRaw)
        ? parseInt(activeRaw, 10)
        : Math.max(0, list.findIndex((it) => it.value === activeRaw));
    const active = Math.max(0, Math.min(activeIdx, list.length - 1));
    const dark = document.documentElement.classList.contains('dark');
    const mode = el.dataset.mode ?? 'navigate';

    createRoot(el).render(
        <SegmentIsland
            id={el.id || el.dataset.segmentId || 'rubber-segment'}
            items={list.map((it) => ({ value: it.value, label: it.label }))}
            defaultValue={list[active].value}
            onChange={(value, index) => {
                if (mode === 'event') {
                    window.dispatchEvent(
                        new CustomEvent(el.dataset.eventName ?? 'rubber-segment:change', {
                            detail: { id: el.id, value, index },
                        })
                    );
                } else if (list[index]?.url) {
                    window.location.href = list[index].url;
                }
            }}
            colors={{
                trackColor: dark ? '#0f172a' : '#e2e8f0',
                thumbColor: accentColor(),
                textColor: dark ? '#cbd5e1' : '#475569',
                activeTextColor: '#ffffff',
                size: el.dataset.size ?? 'md',
                radius: parseInt(el.dataset.radius ?? '10', 10),
                'aria-label': el.dataset.label ?? 'Segmented control',
            }}
        />
    );
}

function mountAll() {
    document
        .querySelectorAll('[data-rubber-segment], #marketing-range-segment')
        .forEach(mountOne);
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mountAll);
else mountAll();
// Navigasi Livewire (morph) tidak me-reload modul: mount ulang bila node baru.
document.addEventListener('livewire:navigated', mountAll);
