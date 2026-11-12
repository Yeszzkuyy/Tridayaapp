/**
 * Kartu konfirmasi standar 3DY.
 *
 * Semua aksi yang butuh persetujuan (hapus, batal, tolak, terima, kirim,
 * arsip, ubah status) memakai kartu ini — bukan `window.confirm()` bawaan
 * browser. Satu kartu dipasang di layouts/app.blade.php, lalu halaman cukup
 * menambah penanda pada form/tautan/tombol:
 *
 *   data-confirm              pesan utama (wajib)
 *   data-confirm-title        judul kartu (opsional)
 *   data-confirm-tone         danger | success | warning | info
 *   data-confirm-label        label tombol aksi (opsional)
 *   data-confirm-cancel       label tombol batal (opsional)
 *   data-confirm-details      JSON [{"label":"Customer","value":"PT ..."}]
 *
 * Perilaku: fokus awal di tombol batal, Esc = batal, klik latar = batal,
 * Tab terkunci di dua tombol, dan form dikirim sekali setelah disetujui
 * (anti dobel-klik tetap ditangani submit loader yang sudah ada).
 */

const TONE_META = {
    danger: {
        chip: 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400',
        button: 'bg-red-600 hover:bg-red-700 shadow-red-500/30',
        ring: 'focus-visible:ring-red-500',
    },
    success: {
        chip: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400',
        button: 'bg-emerald-600 hover:bg-emerald-700 shadow-emerald-500/30',
        ring: 'focus-visible:ring-emerald-500',
    },
    warning: {
        chip: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
        button: 'bg-amber-600 hover:bg-amber-700 shadow-amber-500/30',
        ring: 'focus-visible:ring-amber-500',
    },
    info: {
        chip: 'bg-accent-100 text-accent-700 dark:bg-accent-500/15 dark:text-accent-400',
        button: 'bg-accent-600 hover:bg-accent-700 shadow-accent-500/30',
        ring: 'focus-visible:ring-accent-500',
    },
};

const VALID_TONES = Object.keys(TONE_META);

/** Kata kunci untuk menebak jenis aksi dari pesan konfirmasi. */
const TONE_HINTS = [
    { tone: 'warning', pattern: /(arsip|archive|pindah|move|deactivate|nonaktif|trash)/i },
    { tone: 'success', pattern: /(accept|terima|approve|setuju|submit|kirim|complete|selesai|mark .* sent)/i },
    { tone: 'danger', pattern: /(delete|hapus|remove|reject|tolak|cancel|batal|permanently|permanen)/i },
];

function normalizeTone(value, message = '') {
    if (VALID_TONES.includes(value)) return value;

    for (const hint of TONE_HINTS) {
        if (hint.pattern.test(message)) return hint.tone;
    }

    return 'info';
}

function normalizeOptions(input = {}) {
    const message = String(input.message || '');
    const details = Array.isArray(input.details)
        ? input.details
              .filter((row) => row && row.label)
              .map((row) => ({
                  label: String(row.label),
                  value: String(row.value ?? ''),
              }))
        : [];

    return {
        message,
        title: String(input.title || ''),
        tone: normalizeTone(input.tone, message),
        confirmLabel: String(input.confirmLabel || ''),
        cancelLabel: String(input.cancelLabel || ''),
        details,
    };
}

/** Label default diambil dari kartu yang dirender server (ikut terjemahan). */
function defaults() {
    const root = document.getElementById('gs-confirm-dialog');

    return {
        cancel: root?.dataset.cancelLabel || 'Cancel',
        confirm: root?.dataset.confirmLabel || 'Yes, continue',
        titles: {
            danger: root?.dataset.titleDanger || 'Confirm deletion',
            success: root?.dataset.titleSuccess || 'Confirm action',
            warning: root?.dataset.titleWarning || 'Please review',
            info: root?.dataset.titleInfo || 'Please confirm',
        },
    };
}

function parseDetails(raw) {
    if (!raw) return [];

    try {
        const parsed = JSON.parse(raw);

        return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        return [];
    }
}

function readOptions(element) {
    return {
        message: element.dataset.confirm || '',
        title: element.dataset.confirmTitle || '',
        tone: element.dataset.confirmTone || '',
        confirmLabel: element.dataset.confirmLabel || '',
        cancelLabel: element.dataset.confirmCancel || '',
        details: parseDetails(element.dataset.confirmDetails),
    };
}

/**
 * Daftarkan store Alpine. Dipanggil sekali dari app.js saat `alpine:init`
 * supaya kartu di layout bisa membaca status buka/tutup.
 */
export function registerConfirmStore(Alpine) {
    Alpine.store('confirm', {
        open: false,
        options: normalizeOptions(),
        resolver: null,
        tones: TONE_META,

        ask(input = {}) {
            const config = defaults();
            const options = normalizeOptions(input);

            options.title = options.title || config.titles[options.tone] || config.titles.info;
            options.cancelLabel = options.cancelLabel || config.cancel;
            options.confirmLabel = options.confirmLabel || config.confirm;

            return new Promise((resolve) => {
                if (this.resolver) {
                    this.resolver(false);
                }

                this.resolver = resolve;
                this.options = options;
                this.open = true;
            });
        },

        answer(accepted) {
            const resolver = this.resolver;

            this.resolver = null;
            this.open = false;

            if (resolver) resolver(Boolean(accepted));
        },
    });
}

/**
 * Tanya persetujuan dan tunggu hasilnya (Promise<boolean>).
 * Kalau Alpine belum siap, jatuh ke dialog bawaan browser sebagai jaring
 * pengaman supaya aksi tidak pernah macet.
 */
export function askConfirm(options = {}) {
    const store = window.Alpine?.store?.('confirm');

    if (!store) {
        return Promise.resolve(window.confirm(options.message || ''));
    }

    return store.ask(options);
}

window.askConfirm = askConfirm;

function isSubmitButton(element) {
    return (
        (element.tagName === 'BUTTON' || element.tagName === 'INPUT') &&
        Boolean(element.form) &&
        (element.type === 'submit' || element.type === 'image')
    );
}

/**
 * Pasang penangkap global untuk form & elemen ber-`data-confirm`.
 * Form yang sudah disetujui diberi penanda sementara supaya tidak
 * ditanya dua kali saat dikirim ulang secara program.
 */
export function installConfirmInterceptor(document = globalThis.document) {
    if (!document?.addEventListener) return;

    document.addEventListener(
        'submit',
        (event) => {
            const form = event.target;

            if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;

            if (form.dataset.confirmBypass === '1') {
                delete form.dataset.confirmBypass;

                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            const submitter =
                event.submitter && event.submitter.form === form ? event.submitter : null;

            askConfirm(readOptions(form)).then((accepted) => {
                if (!accepted) return;

                form.dataset.confirmBypass = '1';

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(submitter || undefined);
                } else {
                    form.submit();
                }
            });
        },
        true,
    );

    document.addEventListener(
        'click',
        (event) => {
            const element = event.target?.closest?.('[data-confirm]');

            if (!element || element.tagName === 'FORM') return;
            if (isSubmitButton(element)) return; // sudah ditangani lewat event submit

            if (element.dataset.confirmBypass === '1') {
                delete element.dataset.confirmBypass;

                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            askConfirm(readOptions(element)).then((accepted) => {
                if (!accepted) return;

                element.dataset.confirmBypass = '1';
                element.click();
            });
        },
        true,
    );
}
