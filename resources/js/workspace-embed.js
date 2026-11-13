/**
 * Jembatan antara isi tab (layout embed, di dalam iframe) dan shell luar.
 *
 * Hanya aktif bila `<html data-embed="1">` dan berada di dalam iframe
 * same-origin (`window.parent !== window`).
 */

function isEmbedded() {
    if (typeof window === 'undefined' || typeof document === 'undefined') return false;
    return (
        document.documentElement?.getAttribute('data-embed') === '1' &&
        window.parent &&
        window.parent !== window
    );
}

function send(type, payload = {}) {
    if (!isEmbedded()) return;
    try {
        window.parent.postMessage({ source: '3dy-workspace', type, ...payload }, window.location.origin);
    } catch (e) {}
}

function stripEmbedParam(rawUrl) {
    try {
        const u = new URL(rawUrl, window.location.origin);
        u.searchParams.delete('embed');
        return u.pathname + (u.search || '') + (u.hash || '');
    } catch (e) {
        return rawUrl;
    }
}

export function initWorkspaceEmbed() {
    if (!isEmbedded()) return;

    // 1. Jika redirect berakhir di halaman login, minta shell luar reload penuh.
    if (window.location.pathname === '/login' || document.querySelector('form[action*="/login"]')) {
        send('login');
        return;
    }

    // 2. Patch fetch: sertakan header X-Workspace-Embed pada request same-origin
    //    supaya navigasi Livewire / AJAX di dalam tab tetap memakai layout embed.
    if (typeof window.fetch === 'function' && !window.fetch.__workspacePatched) {
        const origFetch = window.fetch.bind(window);
        const patched = function (input, init = {}) {
            try {
                const raw = typeof input === 'string' ? input : input?.url || '';
                const url = new URL(raw, window.location.origin);
                if (url.origin === window.location.origin) {
                    const headers = new Headers(init.headers || (typeof input !== 'string' ? input?.headers : undefined) || {});
                    headers.set('X-Workspace-Embed', '1');
                    init = { ...init, headers };
                }
            } catch (e) {}
            return origFetch(input, init);
        };
        patched.__workspacePatched = true;
        window.fetch = patched;
    }

    // 3. Teruskan window.toast dari dalam tab ke shell luar supaya pop up
    //    muncul di pojok layar utama dan tidak hilang saat pindah tab.
    window.toast = function (message, ok = true, action = null) {
        send('toast', {
            variant: ok ? 'success' : 'error',
            message: String(message ?? ''),
            action: action && action.label ? { label: action.label, href: action.href || null } : null,
        });
    };

    // 4. Kirim metadata halaman saat ini ke shell luar.
    const reportMeta = () => {
        const meta = window.__workspacePage || {};
        send('meta', {
            url: stripEmbedParam(window.location.href),
            path: window.location.pathname,
            title: meta.tabTitle || document.title.replace(/\s*[—-]\s*Tridaya App.*$/i, '').trim(),
            sectionLabel: meta.sectionLabel || '',
            sectionIcon: meta.sectionIcon || 'grid',
            dirty: false,
        });
    };

    // 5. Teruskan flash session (simpan/hapus berhasil dari POST + redirect)
    //    sekali saja ke shell luar.
    const reportFlash = () => {
        const flash = window.__workspaceFlash;
        if (!flash || flash.__sent) return;
        flash.__sent = true;
        ['success', 'warning', 'info', 'error'].forEach((variant) => {
            if (flash[variant]) {
                send('toast', { variant, message: String(flash[variant]) });
            }
        });
    };

    // 6. Deteksi form yang sedang diketik (unsaved changes).
    //    Filter GET (pencarian/filter tabel) diabaikan karena bukan data form.
    let dirty = false;
    const setDirty = (next) => {
        if (dirty === next) return;
        dirty = next;
        send('dirty', { dirty });
    };

    const isTrackableField = (el) => {
        if (!el || !el.tagName) return false;
        if (!['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName)) return false;
        if (el.type === 'hidden' || el.type === 'search' || el.disabled || el.readOnly) return false;
        if (el.hasAttribute('data-workspace-ignore-dirty')) return false;
        const form = el.closest('form');
        if (!form) return false;
        const method = (form.getAttribute('method') || 'get').toLowerCase();
        return method === 'post';
    };

    document.addEventListener(
        'input',
        (e) => {
            if (isTrackableField(e.target)) setDirty(true);
        },
        true,
    );
    document.addEventListener(
        'change',
        (e) => {
            if (isTrackableField(e.target)) setDirty(true);
        },
        true,
    );
    document.addEventListener(
        'submit',
        () => {
            setDirty(false);
        },
        true,
    );

    // 7. Sinkron tema dari shell luar bila user mengubah tampilan di tab lain.
    window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin) return;
        const data = event.data || {};
        if (data.source !== '3dy-workspace-host') return;
        if (data.type === 'appearance') {
            const root = document.documentElement;
            if (data.mode) root.setAttribute('data-mode', data.mode);
            if (data.accent) root.setAttribute('data-theme', data.accent);
            if (typeof window.__applyAppearance === 'function') window.__applyAppearance();
        }
    });

    reportMeta();
    reportFlash();
    document.addEventListener('livewire:navigated', () => {
        setDirty(false);
        reportMeta();
    });
}
