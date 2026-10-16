

import AlpineBundle from 'alpinejs';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import { mountLoginGalaxy } from './galaxy.js';

// Satu copy Alpine untuk seluruh app: Livewire menyuntik Alpine miliknya
// sendiri (window.Alpine) + start saat DOMContentLoaded. Start ganda dari
// dua copy = double-init seluruh x-data (store terbelah, listener ganda).
// Jadi pakai copy Livewire bila ada; bundle sendiri hanya fallback bila
// Livewire absen, dan start manual hanya dalam kasus fallback itu.
const Alpine = window.Alpine ?? AlpineBundle;
window.Alpine = Alpine;
window.marked = marked;
window.DOMPurify = DOMPurify;

/* ============================================================
   Appearance — mode (light/dark/system) × accent (theme)
   Token CSS di resources/css/themes/accent/*.css
   ============================================================ */
function applyAppearance(mode, accent) {
    const root = document.documentElement;
    const dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    root.classList.toggle('dark', dark);
    root.setAttribute('data-mode', mode);
    root.setAttribute('data-theme', accent);
    try {
        localStorage.setItem('appearance-mode', mode);
        localStorage.setItem('appearance-accent', accent);
    } catch (e) {}
    window.dispatchEvent(new CustomEvent('appearance:change'));
}

function persistAppearance(mode, accent, attempt = 1) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    fetch('/settings/appearance', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
        body: JSON.stringify({ theme: mode, accent }),
    }).then((res) => {
        // fetch() tidak melempar error untuk status 4xx/5xx — tanpa cek ini
        // kegagalan simpan (mis. 419 token kedaluwarsa) berjalan diam-diam dan
        // nilai DB vs localStorage nyimpang → kilatan tema salah saat navigasi.
        if (!res.ok) throw new Error('HTTP ' + res.status);
    }).catch((err) => {
        if (attempt < 2) {
            setTimeout(() => persistAppearance(mode, accent, attempt + 1), 3000);
            return;
        }
        console.warn('[appearance] gagal menyimpan preferensi tema ke server:', err);
    });
}

window.getAppearanceColors = function () {
    const css = getComputedStyle(document.documentElement);
    const t = (name) => css.getPropertyValue(name).trim();
    const rgb = (name) => `rgb(${t(name).replace(/\s+/g, ', ')})`;
    return {
        dark: document.documentElement.classList.contains('dark'),
        theme: document.documentElement.dataset.theme || 'ocean',
        accent500: rgb('--accent-500'),
        accent600: rgb('--accent-600'),
        accent700: rgb('--accent-700'),
        info: rgb('--semantic-info'),
        success: rgb('--semantic-success'),
        warning: rgb('--semantic-warning'),
        danger: rgb('--semantic-danger'),
        cardBg: t('--card-bg'),
    };
};

/* ============================================================
   Web Push (VAPID) — daftar SW diam-diam, subscribe hanya
   saat user menekan "Aktifkan" (Settings > Notifikasi).
   Dipakai lewat window.WebPush: status(), enable(), disable().
   ============================================================ */
function urlBase64ToUint8Array(base64) {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = window.atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

async function swRegistration() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return null;
    try {
        return await navigator.serviceWorker.register('/sw.js');
    } catch (e) {
        return null;
    }
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content;
}

window.WebPush = {
    supported: 'serviceWorker' in navigator && 'PushManager' in window,

    async status() {
        if (!this.supported || !window.vapidPublicKey) return 'unsupported';
        if (Notification.permission === 'denied') return 'blocked';
        const reg = await swRegistration();
        if (!reg) return 'unsupported';
        const sub = await reg.pushManager.getSubscription();
        return sub ? 'subscribed' : Notification.permission;
    },

    async enable() {
        if (!this.supported || !window.vapidPublicKey) return 'unsupported';
        if (Notification.permission === 'denied') return 'blocked';
        // Minta izin eksplisit dulu (wajib user gesture; sebagian browser
        // mengabaikan prompt implisit dari subscribe()).
        if (Notification.permission === 'default') {
            const perm = await Notification.requestPermission();
            if (perm !== 'granted') return perm;
        }
        const reg = await swRegistration();
        if (!reg) return 'unsupported';
        let sub = await reg.pushManager.getSubscription();
        if (!sub) {
            sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(window.vapidPublicKey),
            });
        }
        const json = sub.toJSON();
        await fetch('/push-subscriptions', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
            body: JSON.stringify({
                endpoint: sub.endpoint,
                keys: json.keys,
                contentEncoding: (PushManager.supportedContentEncodings || ['aes128gcm'])[0],
            }),
        });
        return 'subscribed';
    },

    async disable() {
        const reg = await swRegistration();
        const sub = reg ? await reg.pushManager.getSubscription() : null;
        if (sub) {
            try {
                await fetch('/push-subscriptions', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
                    body: JSON.stringify({ endpoint: sub.endpoint }),
                });
            } catch (e) {}
            await sub.unsubscribe();
        }
        return 'unsubscribed';
    },

    init() {
        // Daftarkan SW lebih awal agar push bisa tiba walau tab ditutup.
        if (this.supported && window.vapidPublicKey) swRegistration();
    },
};

window.WebPush.init();

document.addEventListener('alpine:init', () => {
    Alpine.store('appearance', {
        mode: window.__appearanceMode || 'system',
        accent: window.__appearanceAccent || 'ocean',
        media: window.matchMedia('(prefers-color-scheme: dark)'),
        init() {
            // Saat mode=system, ikuti perubahan tema OS tanpa reload.
            this.media.addEventListener('change', () => {
                if (this.mode === 'system') applyAppearance(this.mode, this.accent);
            });
        },
        setMode(mode) {
            this.mode = mode;
            applyAppearance(this.mode, this.accent);
            persistAppearance(this.mode, this.accent);
        },
        setAccent(accent) {
            this.accent = accent;
            applyAppearance(this.mode, this.accent);
            persistAppearance(this.mode, this.accent);
        },
    });
    Alpine.store('appearance').init();
    // Notification Center (bell + feed) dirender React island:
    // resources/js/components/NotificationCenter.jsx. Store Alpine ini
    // tinggal jembatan angka untuk dot sidebar — island yang meng-update.
    Alpine.store('notif', {
        unread: window.notifCenter?.initial?.unread ?? 0,
        unassigned: window.notifCenter?.initial?.unassigned ?? 0,
    });

    Alpine.data('counter', (target, duration = 900) => ({
        display: 0,
        start() {
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (reduce || target <= 0) { this.display = target; return; }
            const t0 = performance.now();
            const tick = (now) => {
                const p = Math.min((now - t0) / duration, 1);
                const eased = 1 - Math.pow(1 - p, 4);
                this.display = Math.round(target * eased);
                if (p < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        },
    }));
});

if (!window.Livewire) Alpine.start();

/* ============================================================
   Sidebar grup — buka/tutup mengikuti halaman AKTIF, bukan klik.
   Header grup adalah link navigasi; lipatan dibuka/ditutup dengan
   animasi setiap wire:navigate selesai (livewire:navigated).
   Cocokkan prefix path link anak terhadap URL saat ini.
   ============================================================ */
function sidebarGroupMatches(group) {
    const path = window.location.pathname.replace(/\/+$/, '') || '/';
    const links = group.querySelectorAll('a[href]');
    for (const a of links) {
        let p = '';
        try {
            p = new URL(a.href).pathname.replace(/\/+$/, '') || '/';
        } catch (e) {
            continue;
        }
        if (p !== '/' && (path === p || path.startsWith(p + '/'))) return true;
    }
    return false;
}

function syncSidebarGroups() {
    document.querySelectorAll('#sidebar-navigation .sb-group').forEach((group) => {
        const want = sidebarGroupMatches(group);
        const has = group.hasAttribute('data-open');
        let scope = null;
        try {
            scope = window.Alpine ? Alpine.$data(group) : null;
        } catch (e) {
            scope = null;
        }
        if (scope && typeof scope.open === 'boolean') {
            if (scope.open !== want) scope.open = want; // flip → transisi lipat beranimasi
            else if (want && !has) group.setAttribute('data-open', '');
            else if (!want && has) group.removeAttribute('data-open');
        } else if (want !== has) {
            // Tanpa scope Alpine: mainkan atribut langsung (transisi tetap jalan).
            // Buka: pastikan tertutup dulu + reflow agar animasi unfold replay.
            if (want) group.removeAttribute('data-open');
            void group.offsetHeight;
            if (want) group.setAttribute('data-open', '');
            else group.removeAttribute('data-open');
        }
    });
}
document.addEventListener('livewire:navigated', () => syncSidebarGroups());

/* ============================================================
   Toast global + form AJAX (tanpa refresh).
   - <form data-ajax data-ajax-target="#tabel"> (GET): ganti target
     dengan partial `html` + pushState URL.
   - <form data-ajax> (POST/PUT/DELETE): JSON {ok, message,
     redirect?}; tanpa redirect + data-ajax-remove="tr" → hapus
     baris terdekat + toast. Sukses + redirect → pindah sekali.
   - 422: kotak error inline di atas form. Gagal total: submit biasa.
   - Diabaikan bila event sudah di-cancel (mis. confirm() batal).
   ============================================================ */
window.toast = function (message, ok = true, action = null) {
    const el = document.createElement('div');
    el.className = 'fixed bottom-6 right-6 z-[100] flex items-center gap-2 px-5 py-3 rounded-xl shadow-lg text-white transition-opacity ' + (ok ? 'bg-green-600' : 'bg-red-600');
    const icon = document.createElement('span');
    icon.setAttribute('aria-hidden', 'true');
    icon.innerHTML = ok
        ? '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5 shrink-0"><circle cx="12" cy="12" r="8.5" /><path stroke-linecap="round" stroke-linejoin="round" d="m8.5 12.3 2.4 2.4 4.8-5" /></svg>'
        : '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5 shrink-0"><circle cx="12" cy="12" r="8.5" /><path stroke-linecap="round" d="M9 9l6 6M15 9l-6 6" /></svg>';
    el.appendChild(icon);
    const text = document.createElement('span');
    text.textContent = message;
    el.appendChild(text);
    if (action && action.href && action.label) {
        const a = document.createElement('a');
        a.href = action.href;
        a.textContent = action.label;
        a.className = 'font-bold underline underline-offset-2 whitespace-nowrap';
        el.appendChild(a);
    }
    document.body.appendChild(el);
    setTimeout(() => {
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 300);
    }, 3500);
};

function ajaxShowErrors(form, errors) {
    form.querySelector('[data-ajax-errors]')?.remove();
    const box = document.createElement('div');
    box.setAttribute('data-ajax-errors', '');
    box.className = 'rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 px-5 py-3 mb-4';
    const ul = document.createElement('ul');
    ul.className = 'list-disc list-inside text-sm space-y-1';
    Object.values(errors || {}).flat().forEach((msg) => {
        const li = document.createElement('li');
        li.textContent = msg;
        ul.appendChild(li);
    });
    box.appendChild(ul);
    form.prepend(box);
    box.scrollIntoView({ block: 'nearest' });
}

function ajaxToggleReset(form) {
    const reset = form.parentElement?.querySelector('[data-ajax-reset]') || document.querySelector('[data-ajax-reset]');
    if (!reset) return;
    const filled = [...new FormData(form).values()].some((v) => String(v ?? '').trim() !== '');
    reset.classList.toggle('hidden', !filled);
}

// Skeleton baris tabel selama filter AJAX berjalan: jumlah kolom dibaca dari
// thead target (fallback 4), baris pulse bawaan Tailwind. Diganti respons asli.
function ajaxSkeleton(target, rows = 5) {
    const table = target.querySelector('table');
    const tbody = table?.querySelector('tbody');
    if (!tbody) return;
    const cols = table.querySelectorAll('thead th').length || 4;
    let cells = '';
    for (let c = 0; c < cols; c++) {
        cells += '<td class="px-6 py-4"><div class="h-4 rounded-md bg-slate-200 dark:bg-slate-700 animate-pulse"></div></td>';
    }
    let html = '';
    for (let r = 0; r < rows; r++) html += '<tr>' + cells + '</tr>';
    tbody.innerHTML = html;
}

document.addEventListener('submit', async (e) => {
    const form = e.target?.closest?.('form[data-ajax]');
    if (!form || e.defaultPrevented) return;
    e.preventDefault();

    form.querySelector('[data-ajax-errors]')?.remove();
    const btn = form.querySelector('[type="submit"]');
    btn?.setAttribute('disabled', '');
    const done = () => btn?.removeAttribute('disabled');

    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (token) headers['X-CSRF-TOKEN'] = token;

    try {
        if ((form.method || 'get').toLowerCase() === 'get') {
            const params = new URLSearchParams(new FormData(form));
            const url = form.action + (form.action.includes('?') ? '&' : '?') + params.toString();
            const target = form.dataset.ajaxTarget ? document.querySelector(form.dataset.ajaxTarget) : null;
            if (target) ajaxSkeleton(target);
            const res = await fetch(url, { headers });
            const data = await res.json();
            if (data.html && form.dataset.ajaxTarget) {
                document.querySelector(form.dataset.ajaxTarget).innerHTML = data.html;
            }
            history.pushState({}, '', url);
            ajaxToggleReset(form);
            done();
            return;
        }

        const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers });
        if (res.status === 422) {
            ajaxShowErrors(form, (await res.json()).errors);
            done();
            return;
        }
        if (!res.ok) throw new Error();
        const data = await res.json();
        if (data.message) window.toast(data.message, true);
        if (data.redirect) {
            window.location.href = data.redirect;
            return;
        }
        if (form.dataset.ajaxRemove) {
            const row = form.closest(form.dataset.ajaxRemove);
            const tbody = row?.closest('tbody');
            row?.remove();
            if (tbody && !tbody.querySelector('tr')) {
                tbody.innerHTML = '<tr><td colspan="99" class="py-16 text-center text-slate-400">Tidak ada data.</td></tr>';
            }
        }
        done();
    } catch (err) {
        done();
        form.submit();
    }
});

/* Sidebar grup buka/tutup mengikuti halaman aktif; transisi buka-tutup
   sepenuhnya CSS (sidebar-nav.css). Tidak ada lagi garis bercabang SVG. */

/* Galaxy starfield — hanya di halaman yang menyediakan #galaxy (login). */
if (document.getElementById('galaxy')) {
    try {
        mountLoginGalaxy();
    } catch (e) {}
}

