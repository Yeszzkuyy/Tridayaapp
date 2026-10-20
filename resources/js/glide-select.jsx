import { createRoot } from 'react-dom/client';
import GlideSelect from './components/GlideSelect.jsx';
import './components/GlideSelect.css';

// Island entry (multi-mount): setiap [data-glide-mount] me-mount satu
// GlideSelect yang tersinkron ke hidden input di .glide-select-root yang sama.
// Opsi/nilai awal/placeholder dibaca dari data-* yang dirender Blade.

function themeProps() {
    const dark = document.documentElement.classList.contains('dark');
    let accent = '#2563eb';
    try {
        const c = window.getAppearanceColors?.();
        if (c?.accent600) accent = c.accent600;
    } catch {}
    if (accent === '#2563eb') {
        const raw = getComputedStyle(document.documentElement).getPropertyValue('--accent-600').trim();
        if (raw) accent = `rgb(${raw.replace(/\s+/g, ', ')})`;
    }
    // ponytail: warna dibaca sekali saat mount; submit/filter selalu full reload
    return dark
        ? { surfaceColor: '#0f172a', highlightColor: '#334155', textColor: '#f1f5f9', accentColor: accent }
        : { surfaceColor: '#f1f5f9', highlightColor: '#e2e8f0', textColor: '#334155', accentColor: accent };
}

const gliderRoots = new WeakMap();

function mountGlider(node) {
    node.dataset.mounted = 'true';
    const scope = node.closest('.glide-select-root');
    const hidden = scope?.querySelector('input[type="hidden"]');
    let options = [];
    try {
        options = JSON.parse(node.dataset.options ?? '[]');
    } catch {}
    if (!options.length) return;
    const root = createRoot(node);
    gliderRoots.set(node, root);
    root.render(
        <GlideSelect
            options={options}
            defaultValue={node.dataset.value ?? ''}
            placeholder={node.dataset.placeholder || 'Pilih…'}
            showTags={node.dataset.tags === '1'}
            size={node.dataset.size || 'md'}
            radius={10}
            ariaLabel={node.dataset.label || 'Pilih'}
            className={node.dataset.full === '1' ? 'glide-select--full' : ''}
            onChange={(value) => {
                if (hidden) {
                    hidden.value = value;
                    hidden.dispatchEvent(new Event('change', { bubbles: true }));
                }
                // Pilihan berubah = error required basi; sembunyikan.
                scope?.querySelector('.glide-select-error')?.setAttribute('hidden', '');
                if (node.dataset.autosubmit === '1') scope?.closest('form')?.submit();
            }}
            {...themeProps()}
        />
    );
}

function mountAll() {
    document.querySelectorAll('[data-glide-mount]:not([data-mounted])').forEach(mountGlider);
    wireRequiredValidation();
}

/**
 * Mount ulang island glider dengan opsi baru (mis. dropdown dependen
 * yang ikut pilihan lain). Nilai dikosongkan kecuali diisi eksplisit.
 * Mengembalikan false bila helper/node tidak siap (validasi server tetap jadi jaring pengaman).
 */
window.__glideRemount = function (mountNode, options, value = '') {
    if (!mountNode) return false;
    try {
        gliderRoots.get(mountNode)?.unmount();
    } catch {}
    mountNode.removeAttribute('data-mounted');
    try {
        mountNode.dataset.options = JSON.stringify(options ?? []);
    } catch {
        return false;
    }
    mountNode.dataset.value = value ?? '';
    const hidden = mountNode.closest('.glide-select-root')?.querySelector('input[type="hidden"]');
    if (hidden) hidden.value = value ?? '';
    mountGlider(mountNode);
    return true;
};

// Hidden input tidak ikut validasi native, jadi cegat submit jika pilihan wajib kosong.
// Submit loader global berjalan di document bubble dan akan melihat defaultPrevented.
function wireRequiredValidation() {
    document.querySelectorAll('form:not([data-glide-validated])').forEach((form) => {
        const nodes = [...form.querySelectorAll('[data-glide-mount][data-required="1"]')];
        if (!nodes.length) return;
        form.dataset.glideValidated = 'true';
        form.addEventListener('submit', (e) => {
            let firstBad = null;
            nodes.forEach((node) => {
                const scope = node.closest('.glide-select-root');
                const hidden = scope?.querySelector('input[type="hidden"]');
                const err = scope?.querySelector('.glide-select-error');
                if (hidden && !hidden.value) {
                    e.preventDefault();
                    if (err) {
                        if (node.dataset.requiredMessage) err.textContent = node.dataset.requiredMessage;
                        err.removeAttribute('hidden');
                    }
                    if (!firstBad) firstBad = node;
                } else if (err) {
                    err.setAttribute('hidden', '');
                }
            });
            if (firstBad) {
                const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                firstBad.scrollIntoView({ block: 'nearest', behavior: reduce ? 'auto' : 'smooth' });
                firstBad.querySelector('.glide-select__trigger')?.focus({ preventScroll: true });
            }
        });
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mountAll);
else mountAll();
// Navigasi Livewire (morph) tidak me-reload modul: mount ulang node baru.
// Guard data-mounted mencegah mount ganda pada node lama.
document.addEventListener('livewire:navigated', mountAll);
