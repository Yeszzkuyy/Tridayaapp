import { markFormBusy, releaseFormBusy } from './form-busy.js';
import { applyServerErrors } from './form-validation.js';

/**
 * Simpan cepat untuk modul Estimator, Produk/Pricelist, dan Sales Request.
 *
 * Alur normal aplikasi: POST lalu redirect, toast baru muncul setelah tab
 * selesai memuat ulang. Di modul ini POST dikirim lewat fetch; server membalas
 * JSON berisi pesan + URL tujuan (lihat InstantSaveResponse), sehingga toast
 * bisa tampil SEBELUM halaman berikutnya dimuat.
 *
 * Halaman menandai dirinya lewat `data-instant-save="1"` di <html>.
 * Form upload, form GET, form Livewire, dan form ber-`data-ajax` tetap
 * memakai jalur lama.
 */

const PENDING_KEY = '3dy:instant-toast';
const PENDING_MAX_AGE = 15000;

function isEmbedded() {
    return document.documentElement?.getAttribute('data-embed') === '1';
}

function notify(variant, message) {
    if (!message) return;

    if (typeof window.toastVariant === 'function') {
        window.toastVariant(message, variant);
        return;
    }

    if (typeof window.toast === 'function') {
        window.toast(message, variant !== 'error');
    }
}

/** Toast untuk halaman non-workspace: titipkan ke halaman berikutnya. */
function rememberPendingToast(variant, message) {
    try {
        sessionStorage.setItem(PENDING_KEY, JSON.stringify({ variant, message, at: Date.now() }));
    } catch (error) {
        /* storage penuh/diblokir — toast server tetap jadi cadangan */
    }
}

function restorePendingToast() {
    if (isEmbedded()) return;

    try {
        const raw = sessionStorage.getItem(PENDING_KEY);
        if (!raw) return;

        sessionStorage.removeItem(PENDING_KEY);

        const data = JSON.parse(raw);
        if (!data?.message || Date.now() - (data.at || 0) > PENDING_MAX_AGE) return;

        window.requestAnimationFrame(() => notify(data.variant || 'success', data.message));
    } catch (error) {
        /* data rusak — biarkan toast server yang tampil */
    }
}

function showUnmappedErrors(form, messages) {
    form.querySelector('[data-instant-save-errors]')?.remove();
    if (!messages.length) return;

    const box = document.createElement('div');
    box.setAttribute('data-instant-save-errors', '');
    box.className =
        'rounded-xl border border-red-300 bg-red-50 px-5 py-3 text-red-700 dark:border-red-700 dark:bg-red-900/20 dark:text-red-300';
    box.setAttribute('role', 'alert');

    const list = document.createElement('ul');
    list.className = 'list-inside list-disc space-y-1 text-sm';
    messages.forEach((message) => {
        const item = document.createElement('li');
        item.textContent = message;
        list.appendChild(item);
    });

    box.appendChild(list);
    form.prepend(box);
    box.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

function isEligible(form, event) {
    if (!form || event.defaultPrevented) return false;
    if (form.hasAttribute('data-ajax') || form.hasAttribute('data-instant-save-off')) return false;
    if (form.hasAttribute('wire:submit') || form.closest('[wire\\:id]')) return false;
    if ((form.getAttribute('method') || 'get').toLowerCase() === 'get') return false;
    if (form.target && form.target !== '_self') return false;

    try {
        const action = new URL(form.getAttribute('action') || window.location.href, window.location.origin);
        if (action.origin !== window.location.origin) return false;
    } catch (error) {
        return false;
    }

    return true;
}

function withEmbed(url) {
    if (!isEmbedded()) return url;

    try {
        const parsed = new URL(url, window.location.origin);
        if (parsed.origin !== window.location.origin) return url;
        if (!parsed.searchParams.has('embed')) parsed.searchParams.set('embed', '1');

        return parsed.toString();
    } catch (error) {
        return url;
    }
}

function backToNormal(form) {
    releaseFormBusy(form);
    delete form.dataset.instantSubmitting;
}

export function installInstantSave(document = globalThis.document, window = globalThis.window) {
    restorePendingToast();

    if (document.documentElement?.getAttribute('data-instant-save') !== '1') return;
    if (!document.addEventListener) return;

    const failedMessage =
        document.documentElement.getAttribute('data-instant-save-failed') ||
        'Failed to save. Please try again.';
    const defaultSavingLabel = document.querySelector('meta[name="saving-label"]')?.content || '';

    document.addEventListener('submit', async (event) => {
        const form = event.target?.closest?.('form');
        if (!form) return;

        // Klik ganda saat request pertama masih berjalan.
        if (form.dataset.instantSubmitting === '1') {
            event.preventDefault();
            return;
        }

        if (!isEligible(form, event)) return;

        event.preventDefault();
        form.dataset.instantSubmitting = '1';

        const submitter =
            event.submitter && event.submitter.form === form
                ? event.submitter
                : form.querySelector('button[type="submit"]');
        const label = form.dataset.loadingText || submitter?.dataset.savingLabel || defaultSavingLabel;

        markFormBusy(form, submitter, label, document);
        form.querySelector('[data-instant-save-errors]')?.remove();

        const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        if (token) headers['X-CSRF-TOKEN'] = token;

        try {
            const response = await fetch(form.getAttribute('action') || window.location.href, {
                method: 'POST',
                body: new FormData(form),
                headers,
                credentials: 'same-origin',
            });

            const isJson = (response.headers.get('content-type') || '').includes('application/json');
            const data = isJson ? await response.json().catch(() => null) : null;

            if (response.status === 422) {
                if (data?.errors) {
                    const result = applyServerErrors(form, data.errors);
                    if (result.unmapped.length) showUnmappedErrors(form, result.unmapped);
                } else {
                    notify('error', data?.message || failedMessage);
                }
                backToNormal(form);
                return;
            }

            if (!response.ok) {
                notify('error', data?.message || failedMessage);
                backToNormal(form);
                return;
            }

            // Server lama/route di luar daftar: redirect diikuti fetch menjadi
            // HTML. Jangan kirim ulang (bisa dobel), cukup pindah ke halaman
            // akhir dan biarkan flash server yang menampilkan toast.
            if (!data) {
                window.location.assign(withEmbed(response.url || window.location.href));
                return;
            }

            const variant = data.variant || 'success';
            const message = data.message || '';

            if (isEmbedded()) {
                notify(variant, message);
            } else if (message) {
                rememberPendingToast(variant, message);
            }

            const target = withEmbed(data.redirect || window.location.href);

            window.setTimeout(
                () => window.location.assign(target),
                isEmbedded() ? 180 : 0,
            );
        } catch (error) {
            notify('error', failedMessage);
            backToNormal(form);
        }
    });
}
