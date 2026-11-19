import { useEffect, useRef } from 'react';
import { motion, useReducedMotion } from 'motion/react';
import { dismissToast } from '../toast-store.js';

/**
 * Kartu pop up notifikasi (pojok kanan bawah).
 *
 * Tampilannya mengikuti item panel lonceng: ikon dalam kotak kecil
 * berwarna, judul tebal + keterangan, tombol aksi opsional, dan tombol
 * silang. Warna aksen notifikasi memakai `accent` dari NotificationCenter
 * supaya jenis kabar tetap dikenali (lead, jadwal, price request, dst.).
 *
 * Posisi & tumpukan diatur ToastViewport (kartu ini hanya menggambar satu
 * kartu dan melaporkan tingginya untuk perhitungan tumpukan).
 */

const CARD_TONES = {
    default: {
        chip: 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-slate-300',
        title: 'text-slate-900 dark:text-white',
    },
    info: {
        chip: 'bg-sky-100 text-sky-700 dark:bg-sky-900/60 dark:text-sky-300',
        title: 'text-slate-900 dark:text-white',
    },
    success: {
        chip: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300',
        title: 'text-slate-900 dark:text-white',
    },
    warning: {
        chip: 'bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300',
        title: 'text-slate-900 dark:text-white',
    },
    error: {
        chip: 'bg-red-100 text-red-700 dark:bg-red-900/60 dark:text-red-300',
        title: 'text-slate-900 dark:text-white',
    },
};

const ACTION_STYLES = {
    default:
        'border-slate-300 text-slate-700 hover:bg-slate-700 hover:text-white dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-600 dark:hover:text-white',
    info:
        'border-sky-600 text-sky-700 hover:bg-sky-600 hover:text-white dark:border-sky-400 dark:text-sky-400 dark:hover:bg-sky-600 dark:hover:text-white',
    success:
        'border-emerald-600 text-emerald-700 hover:bg-emerald-600 hover:text-white dark:border-emerald-400 dark:text-emerald-400 dark:hover:bg-emerald-600 dark:hover:text-white',
    warning:
        'border-amber-600 text-amber-700 hover:bg-amber-600 hover:text-white dark:border-amber-400 dark:text-amber-400 dark:hover:bg-amber-600 dark:hover:text-white',
    error:
        'border-red-600 text-red-700 hover:bg-red-600 hover:text-white dark:border-red-400 dark:text-red-400 dark:hover:bg-red-600 dark:hover:text-white',
};

const ICON_PROPS = {
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 2,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
    viewBox: '0 0 24 24',
    'aria-hidden': true,
};

const TITLES =
    (typeof window !== 'undefined' && window.notifCenter?.i18n?.toastTitles) || {};

function tint(hex, alpha) {
    const value = String(hex || '#64748b').replace('#', '');
    const n = parseInt(value, 16);
    const r = (n >> 16) & 255;
    const g = (n >> 8) & 255;
    const b = n & 255;

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function VariantIcon({ variant, className }) {
    switch (variant) {
        case 'success':
            return (
                <svg {...ICON_PROPS} className={className}>
                    <circle cx="12" cy="12" r="10" />
                    <path d="m9 12 2 2 4-4" />
                </svg>
            );
        case 'error':
            return (
                <svg {...ICON_PROPS} className={className}>
                    <circle cx="12" cy="12" r="10" />
                    <path d="M12 8v4M12 16h.01" />
                </svg>
            );
        case 'warning':
            return (
                <svg {...ICON_PROPS} className={className}>
                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3" />
                    <path d="M12 9v4M12 17h.01" />
                </svg>
            );
        default:
            return (
                <svg {...ICON_PROPS} className={className}>
                    <circle cx="12" cy="12" r="10" />
                    <path d="M12 16v-4M12 8h.01" />
                </svg>
            );
    }
}

const DISMISS_LABEL =
    (typeof window !== 'undefined' && window.notifCenter?.i18n?.dismiss) || 'Dismiss';

export default function NotificationToast({ toast, paused, depth = 0, y = 0, scale = 1, zIndex = 1, clickable = true, onHeight }) {
    const reduceMotion = useReducedMotion();
    const meta = CARD_TONES[toast.variant] || CARD_TONES.default;
    const cardRef = useRef(null);
    const title = toast.title || TITLES[toast.variant] || TITLES.default || '';

    // Laporkan tinggi asli kartu ke ToastViewport untuk menghitung tumpukan
    // (dan tinggi panggung saat mengembang).
    useEffect(() => {
        const element = cardRef.current;

        if (!element || !onHeight) return undefined;

        const report = () => onHeight(toast.id, element.offsetHeight);

        report();

        if (typeof ResizeObserver === 'undefined') return undefined;

        const observer = new ResizeObserver(report);
        observer.observe(element);

        return () => observer.disconnect();
    }, [toast.id, onHeight]);

    // Hilang sendiri setelah `duration`; berhenti selama kursor/fokus ada di
    // tumpukan supaya user sempat membaca. 0 = bertahan sampai ditutup.
    useEffect(() => {
        if (!toast.duration || paused) return undefined;

        const timer = window.setTimeout(() => dismissToast(toast.id), toast.duration);

        return () => window.clearTimeout(timer);
    }, [toast.id, toast.duration, paused]);

    const interactive = Boolean(toast.onOpen || toast.action?.href);

    const openCard = () => {
        if (toast.onOpen) {
            toast.onOpen();
        } else if (toast.action?.href) {
            window.location.assign(toast.action.href);
        }
    };

    const accent = toast.accent
        ? { backgroundColor: tint(toast.accent, 0.14), color: toast.accent }
        : undefined;

    const body = (
        <>
            <span
                className={`mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${meta.chip}`}
                style={accent}
            >
                {toast.icon || <VariantIcon variant={toast.variant} className="h-5 w-5" />}
            </span>
            <div className="min-w-0 flex-1 space-y-1">
                {title ? (
                    <h3 className={`text-sm font-semibold leading-snug ${meta.title}`}>{title}</h3>
                ) : null}
                {toast.message ? (
                    <p className="text-sm leading-relaxed text-slate-600 line-clamp-3 dark:text-slate-300">
                        {toast.message}
                    </p>
                ) : null}
            </div>
        </>
    );

    return (
        <motion.div
            ref={cardRef}
            initial={reduceMotion ? { opacity: 0 } : { opacity: 0, y: y + 40, scale: 0.95 }}
            animate={reduceMotion ? { opacity: 1 } : { opacity: 1, y, scale }}
            exit={reduceMotion ? { opacity: 0 } : { opacity: 0, y: y + 24, scale: scale * 0.96 }}
            transition={reduceMotion ? { duration: 0 } : { duration: 0.36, ease: [0.22, 1, 0.36, 1] }}
            role={toast.variant === 'error' ? 'alert' : 'status'}
            style={{
                zIndex,
                transformOrigin: 'bottom center',
                pointerEvents: clickable ? 'auto' : 'none',
            }}
            className="absolute inset-x-0 bottom-0 flex w-full max-w-[420px] items-start justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl shadow-slate-900/10 dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-black/40"
        >
            {interactive ? (
                <button type="button" onClick={openCard} className="flex min-w-0 flex-1 items-start gap-3 text-left">
                    {body}
                </button>
            ) : (
                <div className="flex min-w-0 flex-1 items-start gap-3">{body}</div>
            )}

            <div className="flex shrink-0 items-center gap-2">
                {toast.action?.label ? (
                    toast.action.href ? (
                        <a
                            href={toast.action.href}
                            onClick={() => dismissToast(toast.id)}
                            className={`inline-flex h-9 items-center rounded-xl border px-3.5 text-xs font-semibold transition-all duration-200 active:scale-95 ${ACTION_STYLES[toast.variant] || ACTION_STYLES.default}`}
                        >
                            {toast.action.label}
                        </a>
                    ) : (
                        <button
                            type="button"
                            onClick={() => {
                                dismissToast(toast.id);
                                toast.action.onClick?.();
                            }}
                            className={`inline-flex h-9 items-center rounded-xl border px-3.5 text-xs font-semibold transition-all duration-200 active:scale-95 ${ACTION_STYLES[toast.variant] || ACTION_STYLES.default}`}
                        >
                            {toast.action.label}
                        </button>
                    )
                ) : null}

                <button
                    type="button"
                    onClick={() => dismissToast(toast.id)}
                    aria-label={DISMISS_LABEL}
                    title={DISMISS_LABEL}
                    className="rounded-xl p-1.5 text-slate-400 transition-colors hover:bg-red-600 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 active:scale-95 dark:text-slate-500 dark:hover:text-white"
                >
                    <svg {...ICON_PROPS} className="h-3.5 w-3.5">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </motion.div>
    );
}
