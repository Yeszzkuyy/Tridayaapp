import { useEffect, useRef } from 'react';
import { motion, useReducedMotion } from 'motion/react';
import { dismissToast } from '../toast-store.js';

/**
 * Kartu pop up notifikasi — tampilan disalin dari template 21st.dev
 * ("arunachalam/toast"): ikon kecil kiri, judul tebal + keterangan,
 * tombol aksi opsional, dan tombol silang di kanan.
 *
 * Posisi & tumpukan diatur ToastViewport (kartu ini hanya menggambar satu
 * kartu dan melaporkan tingginya untuk perhitungan tumpukan).
 */

const CARD_STYLES = {
    default: {
        card: 'border-slate-200 bg-white text-slate-800 dark:border-zinc-800 dark:bg-zinc-900 dark:text-slate-100',
        title: 'text-slate-900 dark:text-white',
        icon: 'text-slate-500 dark:text-slate-300',
    },
    success: {
        card: 'border-green-600/50 bg-white text-slate-800 dark:border-green-500/50 dark:bg-zinc-900 dark:text-slate-100',
        title: 'text-green-700 dark:text-green-400',
        icon: 'text-green-600 dark:text-green-400',
    },
    error: {
        card: 'border-red-600/50 bg-white text-slate-800 dark:border-red-500/50 dark:bg-zinc-900 dark:text-slate-100',
        title: 'text-red-700 dark:text-red-400',
        icon: 'text-red-600 dark:text-red-400',
    },
    warning: {
        card: 'border-amber-600/50 bg-white text-slate-800 dark:border-amber-500/50 dark:bg-zinc-900 dark:text-slate-100',
        title: 'text-amber-700 dark:text-amber-400',
        icon: 'text-amber-600 dark:text-amber-400',
    },
};

const ACTION_STYLES = {
    default:
        'border-slate-300 text-slate-700 hover:bg-slate-700 hover:text-white dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-600 dark:hover:text-white',
    success:
        'border-green-600 text-green-700 hover:bg-green-600 hover:text-white dark:border-green-400 dark:text-green-400 dark:hover:bg-green-600 dark:hover:text-white',
    error:
        'border-red-600 text-red-700 hover:bg-red-600 hover:text-white dark:border-red-400 dark:text-red-400 dark:hover:bg-red-600 dark:hover:text-white',
    warning:
        'border-amber-600 text-amber-700 hover:bg-amber-600 hover:text-white dark:border-amber-400 dark:text-amber-400 dark:hover:bg-amber-600 dark:hover:text-white',
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
    const meta = CARD_STYLES[toast.variant] || CARD_STYLES.default;
    const cardRef = useRef(null);

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

    const body = (
        <>
            <span
                className={`mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center ${meta.icon}`}
                style={toast.accent ? { color: toast.accent } : undefined}
            >
                {toast.icon || <VariantIcon variant={toast.variant} className="h-5 w-5" />}
            </span>
            <div className="min-w-0 space-y-1">
                {toast.title ? (
                    <h3 className={`text-sm font-semibold leading-snug ${meta.title}`}>{toast.title}</h3>
                ) : null}
                {toast.message ? (
                    <p className="text-sm leading-relaxed text-slate-700 line-clamp-3 dark:text-slate-200">
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
            className={`absolute inset-x-0 bottom-0 flex w-full max-w-[420px] items-center justify-between gap-3 rounded-xl border p-4 shadow-md ${meta.card}`}
        >
            {interactive ? (
                <button type="button" onClick={openCard} className="flex min-w-0 items-start gap-2.5 text-left">
                    {body}
                </button>
            ) : (
                <div className="flex min-w-0 items-start gap-2.5">{body}</div>
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
                    className="rounded-xl p-1.5 text-slate-500 transition-colors hover:bg-red-600 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 active:scale-95 dark:text-slate-300 dark:hover:text-white"
                >
                    <svg {...ICON_PROPS} className="h-3.5 w-3.5">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </motion.div>
    );
}
