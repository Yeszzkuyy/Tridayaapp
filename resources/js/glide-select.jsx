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
    wireSubmitGuard();
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

const SUBMIT_BTN = 'button[type="submit"], input[type="submit"]';

// Snippet loader milik user (size-8 diganti width/height 32 karena kelas itu belum ada di build).
const LOADER_HTML = `<div class="text-center" role="status">
  <svg class="mx-auto animate-spin text-indigo-600" width="32" height="32" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
  </svg>
  <p class="mt-4 font-medium text-gray-700" data-text>Loading...</p>
</div>`;

function setSubmitLocked(form, locked) {
    form.querySelectorAll(SUBMIT_BTN).forEach((b) => {
        b.disabled = locked;
        b.style.opacity = locked ? '.65' : '';
    });
    showSubmitOverlay(locked ? form : null);
}

// Loader submit: kartu tengah layar berisi snippet loader (satu bentuk dengan kartu sukses).
// Dibuat sekali, dipakai ulang; shell kartu inline style (dark-aware), isi pakai kelas Tailwind.
function showSubmitOverlay(form) {
    let el = document.getElementById('gs-submit-overlay');
    if (!el) {
        el = document.createElement('div');
        el.id = 'gs-submit-overlay';
        el.className = 'status-card-backdrop';
        el.style.display = 'none';
        el.innerHTML = `<div data-card>${LOADER_HTML}</div>`;
        document.body.appendChild(el);
    }
    // ponytail: jangan pakai atribut hidden — display inline menimpanya sehingga overlay abadi
    if (!form) {
        el.style.display = 'none';
        return;
    }
    const dark = document.documentElement.classList.contains('dark');
    // ponytail: left + top TIDAK di-set inline agar offset sidebar (CSS
    // .status-card-backdrop) dan tinggi navbar (CSS #gs-submit-overlay) berlaku
    Object.assign(el.style, {
        position: 'fixed',
        right: '0',
        bottom: '0',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        padding: '16px',
        background: dark ? 'rgba(2, 6, 23, .55)' : 'rgba(15, 23, 42, .4)',
        zIndex: '60'
    });
    const card = el.querySelector('[data-card]');
    Object.assign(card.style, {
        width: '100%',
        maxWidth: '384px',
        borderRadius: '16px',
        padding: '32px 40px',
        background: dark ? '#1e293b' : '#ffffff',
        boxShadow: '0 10px 30px rgba(0, 0, 0, .35)'
    });
    const text = el.querySelector('[data-text]');
    text.textContent = form.dataset.loadingText || fallbackLoadingText();
    if (dark) text.style.color = '#f1f5f9';
}

function fallbackLoadingText() {
    return 'Loading...';
}

// Anti-duplikat: kunci tombol saat submit benar-benar jalan.
// Dibuka lagi bila digagalkan (native 'invalid', atau required di bawah).
// Didaftarkan duluan agar wireRequiredValidation bisa membukanya lagi.
function wireSubmitGuard() {
    document.querySelectorAll('form:not([data-submit-guarded])').forEach((form) => {
        if (!form.querySelector('[data-glide-mount]') && !form.hasAttribute('data-loading-text')) return;
        form.dataset.submitGuarded = 'true';
        form.addEventListener('invalid', () => setSubmitLocked(form, false), true);
        form.addEventListener('submit', () => setSubmitLocked(form, true));
    });
    // Kembali via tombol back (bfcache): pastikan overlay hilang + tombol aktif lagi.
    if (!document.body.dataset.gsPageshow) {
        document.body.dataset.gsPageshow = 'true';
        window.addEventListener('pageshow', () => {
            document.querySelectorAll('form[data-submit-guarded]').forEach((form) => setSubmitLocked(form, false));
        });
    }
}

// Opsi A: hidden input lolos validasi browser, jadi cegat submit manual.
// Native required lain tetap jalan duluan (browser memblokir sebelum event submit).
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
                    setSubmitLocked(form, false);
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
