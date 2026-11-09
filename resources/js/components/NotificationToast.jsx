import { useEffect } from 'react';
import { motion, useReducedMotion } from 'motion/react';
import { dismissToast } from '../toast-store.js';

/**
 * Kartu pop up notifikasi — tampilan disalin dari template 21st.dev
 * ("Toast" dengan varian default/success/error/warning), lalu warnanya
 * disesuaikan token aplikasi (accent/slate + mode gelap). Tidak memakai
 * sonner/shadcn supaya tidak menambah library dan tidak bentrok tema.
 *
 * Struktur kartu: ikon kecil di kiri, judul tebal + keterangan singkat,
 * tombol aksi opsional, dan tombol silang di kanan.
 */

const CARD_STYLES = {
    default: {
        card: 'border-slate-200 bg-white text-slate-800 dark:border-zinc-800 dark:bg-zinc-900 dark:text-slate-100',
        title: 'text-slate-800 dark:text-slate-100',
        icon: 'text-slate-400 dark:text-slate-500',
    },
    success: {
        card: 'border-green-600/50 bg-white text-slate-800 dark:border-green-500/50 dark:bg-zinc-900 dark:text-slate-100',
        title: 'text-green-600 dark:text-green-400',
        icon: 'text-green-600 dark:text-green-400',
    },
    error: {
        card: 'border-red-600/50 bg-white text-slate-800 dark:border-red-500/50 dark:bg-zinc-900 dark:text-slate-100',
        title: 'text-red-600 dark:text-red-400',
        icon: 'text-red-600 dark:text-red-400',
    },
    warning: {
        card: 'border-amber-600/50 bg-white text-slate-800 dark:border-amber-500/50 dark:bg-zinc-900 dark:text-slate-100',
        title: 'text-amber-600 dark:text-amber-400',
        icon: 'text-amber-600 dark:text-amber-400',
    },
};

const ACTION_STYLES = {
    default:
        'border-slate-300 text-slate-700 hover:bg-slate-700 hover:text-white dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-600 dark:hover:text-white',
    success:
        'border-green-600 text-green-600 hover:bg-green-600 hover:text-white dark:border-green-400 dark:text-green-400 dark:hover:bg-green-600 dark:hover:text-white',
    error:
        'border-red-600 text-red-600 hover:bg-red-600 hover:text-white dark:border-red-400 dark:text-red-400 dark:hover:bg-red-600 dark:hover:text-white',
    warning:
        'border-amber-600 text-amber-600 hover:bg-amber-600 hover:text-white dark:border-amber-400 dark:text-amber-400 dark:hover:bg-amber-600 dark:hover:text-white',
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

export default function NotificationToast({ toast, paused, onPauseChange }) {
    const reduceMotion = useReducedMotion();
    const meta = CARD_STYLES[toast.variant] || CARD_STYLES.default;

    // Hilang sendiri setelah `duration`; berhenti selama kursor/fokus ada di
    // kartu supaya user sempat membaca. 0 = bertahan sampai ditutup.
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
                className={`mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center ${meta.icon}`}
                style={toast.accent ? { color: toast.accent } : undefined}
            >
                {toast.icon || <VariantIcon variant={toast.variant} className="h-4 w-4" />}
            </span>
            <div className="min-w-0 space-y-0.5">
                {toast.title ? (
                    <h3 className={`text-xs font-medium leading-none ${meta.title}`}>{toast.title}</h3>
                ) : null}
                {toast.message ? (
                    <p className="text-xs leading-relaxed text-slate-500 line-clamp-3 dark:text-slate-400">
                        {toast.message}
                    </p>
                ) : null}
            </div>
        </>
    );

    return (
        <motion.div
            initial={reduceMotion ? { opacity: 0 } : { opacity: 0, y: 50, scale: 0.95 }}
            animate={reduceMotion ? { opacity: 1 } : { opacity: 1, y: 0, scale: 1 }}
            exit={reduceMotion ? { opacity: 0 } : { opacity: 0, y: 50, scale: 0.95 }}
            transition={{ duration: 0.3, ease: 'easeOut' }}
            role={toast.variant === 'error' ? 'alert' : 'status'}
            onMouseEnter={() => onPauseChange?.(true)}
            onMouseLeave={() => onPauseChange?.(false)}
            onFocus={() => onPauseChange?.(true)}
            onBlur={() => onPauseChange?.(false)}
            className={`pointer-events-auto flex w-full max-w-xs items-center justify-between gap-3 rounded-xl border p-3 shadow-md ${meta.card}`}
        >
            {interactive ? (
                <button type="button" onClick={openCard} className="flex min-w-0 items-start gap-2 text-left">
                    {body}
                </button>
            ) : (
                <div className="flex min-w-0 items-start gap-2">{body}</div>
            )}

            <div className="flex shrink-0 items-center gap-2">
                {toast.action?.label ? (
                    toast.action.href ? (
                        <a
                            href={toast.action.href}
                            onClick={() => dismissToast(toast.id)}
                            className={`inline-flex h-8 items-center rounded-xl border px-3 text-xs font-medium transition-all duration-200 active:scale-95 ${ACTION_STYLES[toast.variant] || ACTION_STYLES.default}`}
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
                            className={`inline-flex h-8 items-center rounded-xl border px-3 text-xs font-medium transition-all duration-200 active:scale-95 ${ACTION_STYLES[toast.variant] || ACTION_STYLES.default}`}
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
                    className="rounded-xl p-1 text-slate-400 transition-colors hover:bg-red-600 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 active:scale-95 dark:text-slate-500 dark:hover:text-white"
                >
                    <svg {...ICON_PROPS} className="h-3 w-3">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </motion.div>
    );
}
