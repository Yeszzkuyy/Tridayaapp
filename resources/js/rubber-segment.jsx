import { createRoot } from 'react-dom/client';
import RubberSegment from './components/RubberSegment.jsx';
import './components/RubberSegment.css';

// Island entry: me-mount RubberSegment di dashboard marketing.
// Data (label + URL preset + index aktif) dibaca dari data-* Blade;
// klik segmen = navigasi GET biasa (controller tidak berubah).

function accentColor() {
    try {
        const c = window.getAppearanceColors?.();
        if (c?.accent600) return c.accent600;
    } catch {}
    const raw = getComputedStyle(document.documentElement).getPropertyValue('--accent-600').trim();
    return raw ? `rgb(${raw.replace(/\s+/g, ', ')})` : '#2563eb';
}

function mount() {
    const el = document.getElementById('marketing-range-segment');
    if (!el || el.dataset.mounted) return;
    el.dataset.mounted = 'true';

    let presets = [];
    try {
        presets = JSON.parse(el.dataset.items ?? '[]');
    } catch {}
    if (!presets.length) return;

    const active = Math.max(0, Math.min(parseInt(el.dataset.active ?? '0', 10) || 0, presets.length - 1));
    const dark = document.documentElement.classList.contains('dark');

    // ponytail: warna dibaca sekali saat mount; navigasi selalu full reload jadi tidak perlu reaktif
    createRoot(el).render(
        <RubberSegment
            items={presets.map((p) => p.label)}
            defaultValue={presets[active].label}
            onChange={(_, index) => {
                const url = presets[index]?.url;
                if (url) window.location.href = url;
            }}
            trackColor={dark ? '#0f172a' : '#e2e8f0'}
            thumbColor={accentColor()}
            textColor={dark ? '#cbd5e1' : '#475569'}
            activeTextColor="#ffffff"
            size="md"
            radius={10}
            aria-label="Rentang tanggal dashboard"
        />
    );
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount);
else mount();
// Navigasi Livewire (morph) tidak me-reload modul: mount ulang bila node baru.
document.addEventListener('livewire:navigated', mount);
