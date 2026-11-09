/**
 * Antrean toast global — satu sumber untuk pop up notifikasi.
 *
 * Dua sumber masuk ke antrean yang sama:
 *   1. Notifikasi baru dari server (Notification Center, realtime) — kartu
 *      dengan ikon jenis kabar + klik membuka halaman terkait.
 *   2. `window.toast(message, ok, action)` lama (simpan gagal/berhasil) —
 *      tetap dipakai view Blade & app.js, sekarang ikut tampil di kartu baru.
 *
 * Store sengaja tidak menyentuh DOM: ToastViewport yang merender (React).
 * Toast yang masuk SEBELUM viewport siap tetap tersimpan di antrean dan
 * langsung tampil begitu viewport di-mount (lihat subscribeToast).
 */

/** Maksimal kartu yang tampil bersamaan; yang tertua dibuang. */
const MAX_VISIBLE = 3;

const VARIANTS = ['default', 'success', 'error', 'warning'];

let toasts = [];
let sequence = 0;
const listeners = new Set();

function emit() {
    listeners.forEach((listener) => listener(toasts));
}

export function subscribeToast(listener) {
    listeners.add(listener);
    listener(toasts);

    return () => {
        listeners.delete(listener);
    };
}

export function getToasts() {
    return toasts;
}

/**
 * Tambahkan satu toast ke antrean.
 *
 * @param {object} input
 * @param {'default'|'success'|'error'|'warning'} [input.variant]
 * @param {string} [input.title]    judul tebal (mis. nama jenis kabar)
 * @param {string} [input.message]  keterangan singkat
 * @param {string} [input.accent]   warna ikon khusus (hex) — dipakai kabar masuk
 * @param {React.ReactNode} [input.icon] ikon pengganti ikon bawaan varian
 * @param {number} [input.duration] milidetik sebelum hilang sendiri (0 = tetap)
 * @param {{label: string, href?: string, onClick?: Function}} [input.action]
 * @param {Function} [input.onOpen]    klik kartu (biasanya buka halaman terkait)
 * @param {Function} [input.onDismiss] dipanggil saat kartu ditutup
 */
export function pushToast(input = {}) {
    const variant = VARIANTS.includes(input.variant) ? input.variant : 'default';
    const fallbackDuration = variant === 'error' ? 8000 : 5000;

    const toast = {
        id: `toast-${++sequence}`,
        variant,
        title: input.title || '',
        message: input.message || '',
        accent: input.accent || null,
        icon: input.icon || null,
        duration: Number.isFinite(input.duration) ? input.duration : fallbackDuration,
        action: input.action && input.action.label ? input.action : null,
        onOpen: typeof input.onOpen === 'function' ? input.onOpen : null,
        onDismiss: typeof input.onDismiss === 'function' ? input.onDismiss : null,
    };

    toasts = [...toasts, toast].slice(-MAX_VISIBLE);
    emit();

    return toast.id;
}

export function dismissToast(id) {
    const target = toasts.find((toast) => toast.id === id);

    if (!target) return;

    toasts = toasts.filter((toast) => toast.id !== id);
    emit();

    if (target.onDismiss) {
        try {
            target.onDismiss();
        } catch (error) {
            /* callback pemanggil bermasalah — jangan sampai antrean macet */
        }
    }
}

export function dismissAllToasts() {
    toasts = [];
    emit();
}

/**
 * Pasang API lama `window.toast(message, ok, action)` di atas antrean baru.
 * Bentuk pemanggilan dipertahankan supaya view Blade & app.js tidak perlu
 * diubah: ok=true → kartu hijau, ok=false → kartu merah, action opsional
 * ({label, href}) tampil sebagai tombol di kanan kartu.
 */
export function installLegacyToast() {
    window.toast = function toast(message, ok = true, action = null) {
        pushToast({
            variant: ok ? 'success' : 'error',
            message: String(message ?? ''),
            action,
        });
    };
}
