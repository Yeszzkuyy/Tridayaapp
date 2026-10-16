import { useCallback, useEffect, useRef, useState } from 'react';
import { AnimatePresence, animate, motion, useReducedMotion } from 'motion/react';

/**
 * Notification Center — bell + feed di header.
 *
 * Konfigurasi dari server lewat `window.notifCenter` (layouts/app.blade.php):
 *   { userId, csrf, locale, initial: { unread, unassigned, items,
 *     next_cursor, has_more }, routes: { status, feed, readAll, clear,
 *     read, destroy }, i18n: { ... } }
 *
 * Item sudah dipresentasikan seragam oleh App\Support\NotificationPresenter
 * ({ id, type, app, title, body, chips, url, read, created_at }), jadi
 * komponen ini tidak pernah menyentuh bentuk payload notifikasi mentah.
 */
const CONFIG = (typeof window !== 'undefined' && window.notifCenter) || {};
const I18N = CONFIG.i18n || {};
const ROUTES = CONFIG.routes || {};
const LOCALE = CONFIG.locale || undefined;

/** Panel: dipasang di tengah bawah bell, digeser/clamp bila mepet tepi layar. */
const PANEL_WIDTH = 400;
const PANEL_GAP = 8;
const VIEWPORT_PAD = 12;

/** Aksen & ikon per tipe notifikasi (identitas warna, senada konvensi repo). */
const TYPE_META = {
    lead: { color: '#16a34a', icon: 'userPlus' },
    assigned: { color: '#0284c7', icon: 'userPlus' },
    followup: { color: '#d97706', icon: 'clock' },
    task: { color: '#7c3aed', icon: 'clipboard' },
    schedule_assigned: { color: '#4f46e5', icon: 'calendar' },
    schedule_changed: { color: '#4f46e5', icon: 'calendar' },
    schedule_reminder: { color: '#4f46e5', icon: 'bell' },
    sales_schedule_assigned: { color: '#4f46e5', icon: 'calendar' },
    whatsapp: { color: '#059669', icon: 'message' },
    proposal: { color: '#0891b2', icon: 'file' },
    technical_request_created: { color: '#0d9488', icon: 'wrench' },
    technical_request_reviewed: { color: '#0d9488', icon: 'wrench' },
    technical_work_completed: { color: '#0d9488', icon: 'checkCircle' },
    survey_created: { color: '#e11d48', icon: 'camera' },
    survey_report_uploaded: { color: '#e11d48', icon: 'camera' },
    generic: { color: '#64748b', icon: 'bell' },
};

const CHIP_TONES = {
    default: 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-slate-300',
    info: 'bg-sky-100 text-sky-700 dark:bg-sky-900/60 dark:text-sky-300',
    success: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300',
    warning: 'bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300',
    danger: 'bg-red-100 text-red-700 dark:bg-red-900/60 dark:text-red-300',
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

function tint(hex, alpha) {
    const n = parseInt(hex.slice(1), 16);
    const r = (n >> 16) & 255;
    const g = (n >> 8) & 255;
    const b = n & 255;

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function timeAgo(iso) {
    if (!iso) return '';
    const then = new Date(iso).getTime();
    if (Number.isNaN(then)) return '';

    const minutes = Math.floor(Math.max(0, Date.now() - then) / 60000);
    if (minutes < 1) return I18N.justNow || '';
    if (minutes < 60) return `${minutes} ${I18N.minute || 'mnt'}`;

    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} ${I18N.hour || 'jam'}`;

    const days = Math.floor(hours / 24);
    if (days < 7) return `${days} ${I18N.day || 'hari'}`;

    return new Date(then).toLocaleDateString(LOCALE, { day: 'numeric', month: 'short', year: 'numeric' });
}

function withId(template, id) {
    return (template || '').replace('__ID__', encodeURIComponent(id));
}

function TypeIcon({ name, className }) {
    switch (name) {
        case 'userPlus':
            return (
                <svg {...ICON_PROPS} className={className}>
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <path d="M19 8v6M22 11h-6" />
                </svg>
            );
        case 'clock':
            return (
                <svg {...ICON_PROPS} className={className}>
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 7v5l3 2" />
                </svg>
            );
        case 'clipboard':
            return (
                <svg {...ICON_PROPS} className={className}>
                    <rect x="8" y="2" width="8" height="4" rx="1" />
                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                </svg>
            );
        case 'calendar':
            return (
                <svg {...ICON_PROPS} className={className}>
                    <rect x="3" y="4" width="18" height="18" rx="2" />
                    <path d="M16 2v4M8 2v4M3 10h18" />
                </svg>
            );
        case 'message':
            return (
                <svg {...ICON_PROPS} className={className}>
                    <path d="M21 11.5a8.5 8.5 0 0 1-12.6 7.5L3 20.5l1.5-5.4A8.5 8.5 0 1 1 21 11.5z" />
                    <path d="M8.5 11.5h.01M12 11.5h.01M15.5 11.5h.01" />
                </svg>
            );
        case 'file':
            return (
                <svg {...ICON_PROPS} className={className}>
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <path d="M14 2v6h6" />
                </svg>
            );
        case 'wrench':
            return (
                <svg {...ICON_PROPS} className={className}>
                    <path d="M14.5 6.5a4.5 4.5 0 0 0-5.9 5.6L3 17.7a2.1 2.1 0 1 0 3.3 3.3l5.6-5.6a4.5 4.5 0 0 0 5.6-5.9l-2.4 2.4-2.9-.7-.7-2.9z" />
                </svg>
            );
        case 'checkCircle':
            return (
                <svg {...ICON_PROPS} className={className}>
                    <circle cx="12" cy="12" r="9" />
                    <path d="m8.5 12.5 2.5 2.5 4.5-5" />
                </svg>
            );
        case 'camera':
            return (
                <svg {...ICON_PROPS} className={className}>
                    <path d="M3 8a2 2 0 0 1 2-2h1.5l1-2h5l1 2H19a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                    <circle cx="12" cy="13" r="3.5" />
                </svg>
            );
        default:
            return (
                <svg {...ICON_PROPS} className={className}>
                    <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-4-5.66V5a2 2 0 1 0-4 0v.34A6 6 0 0 0 6 11v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9" />
                </svg>
            );
    }
}

function NotificationCard({ item, reduceMotion, onOpen, onDismiss }) {
    const meta = TYPE_META[item.type] || TYPE_META.generic;
    const fresh = Boolean(item._fresh) && !reduceMotion;

    return (
        <motion.div
            layout={!reduceMotion}
            initial={fresh ? { opacity: 0, y: -14, scale: 0.98 } : false}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={reduceMotion ? { opacity: 0 } : { opacity: 0, x: 20, transition: { duration: 0.16 } }}
            transition={{ duration: 0.24, ease: 'easeOut' }}
            className="group relative"
        >
            <button
                type="button"
                onClick={() => onOpen(item)}
                className="flex w-full items-start gap-3 rounded-xl px-3 py-2.5 text-left transition hover:bg-slate-50 dark:hover:bg-zinc-800/70"
            >
                <span
                    className="relative mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                    style={{ backgroundColor: tint(meta.color, 0.14), color: meta.color }}
                >
                    <TypeIcon name={meta.icon} className="h-[18px] w-[18px]" />
                    {!item.read && (
                        <span className="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-red-500 dark:border-zinc-900" />
                    )}
                </span>

                <span className="min-w-0 flex-1">
                    <span className="flex items-center gap-1.5">
                        <span className="text-[11px] font-semibold uppercase tracking-wide" style={{ color: meta.color }}>
                            {item.app}
                        </span>
                        <span className="text-[11px] text-slate-300 dark:text-slate-600">•</span>
                        <span className="text-[11px] text-slate-400 dark:text-slate-500">{timeAgo(item.created_at)}</span>
                    </span>
                    <span
                        className={`mt-0.5 block truncate text-[13px] leading-5 ${
                            item.read
                                ? 'font-medium text-slate-600 dark:text-slate-300'
                                : 'font-semibold text-slate-800 dark:text-slate-100'
                        }`}
                    >
                        {item.title}
                    </span>
                    {item.body ? (
                        <span className="mt-0.5 block text-xs leading-5 text-slate-500 line-clamp-2 dark:text-slate-400">
                            {item.body}
                        </span>
                    ) : null}
                    {item.chips && item.chips.length > 0 ? (
                        <span className="mt-1.5 flex flex-wrap gap-1.5">
                            {item.chips.map((chip, index) => (
                                <span
                                    key={`${chip.label}-${index}`}
                                    className={`inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold ${
                                        CHIP_TONES[chip.tone] || CHIP_TONES.default
                                    }`}
                                >
                                    {chip.label}
                                </span>
                            ))}
                        </span>
                    ) : null}
                </span>
            </button>

            <button
                type="button"
                onClick={(event) => onDismiss(event, item)}
                title={I18N.dismiss}
                aria-label={I18N.dismiss}
                className="absolute right-1.5 top-1.5 flex h-6 w-6 items-center justify-center rounded-lg text-slate-400 transition hover:bg-red-600 hover:text-white max-sm:opacity-100 sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100 dark:text-slate-500"
            >
                <svg {...ICON_PROPS} className="h-3.5 w-3.5">
                    <path d="M18 6 6 18M6 6l12 12" />
                </svg>
            </button>
        </motion.div>
    );
}

export default function NotificationCenter() {
    const reduceMotion = useReducedMotion();

    const [items, setItems] = useState(() => CONFIG.initial?.items ?? []);
    const [unread, setUnread] = useState(CONFIG.initial?.unread ?? 0);
    const [unassigned, setUnassigned] = useState(CONFIG.initial?.unassigned ?? 0);
    const [cursor, setCursor] = useState(CONFIG.initial?.next_cursor ?? null);
    const [hasMore, setHasMore] = useState(Boolean(CONFIG.initial?.has_more));
    const [open, setOpen] = useState(false);
    const [loadingMore, setLoadingMore] = useState(false);
    const [confirmClear, setConfirmClear] = useState(false);
    const [sessionExpired, setSessionExpired] = useState(false);
    const [connected, setConnected] = useState(false);
    const [live, setLive] = useState('');
    const [panelPos, setPanelPos] = useState(null);

    const rootRef = useRef(null);
    const bellRef = useRef(null);
    const openRef = useRef(false);
    const expiredRef = useRef(false);

    useEffect(() => {
        openRef.current = open;
    }, [open]);

    useEffect(() => {
        expiredRef.current = sessionExpired;
    }, [sessionExpired]);

    /**
     * Posisi panel: pas di tengah bawah ikon bell. Kalau tidak cukup ruang di
     * kanan, panel digeser ke kiri (clamp) supaya selalu utuh di layar —
     * tidak menempel tepi kanan dan tidak terpotong.
     */
    const placePanel = useCallback(() => {
        const bell = bellRef.current;

        if (!bell) return;

        const rect = bell.getBoundingClientRect();
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        const width = Math.min(PANEL_WIDTH, vw - VIEWPORT_PAD * 2);
        const top = rect.bottom + PANEL_GAP;
        const spaceBelow = vh - top - VIEWPORT_PAD;

        setPanelPos({
            left: Math.min(Math.max(VIEWPORT_PAD, rect.left + rect.width / 2 - width / 2), vw - width - VIEWPORT_PAD),
            top,
            width,
            maxHeight: Math.max(240, Math.min(spaceBelow, Math.round(vh * 0.7), 512)),
        });
    }, []);

    const togglePanel = useCallback(() => {
        if (!open) placePanel();
        setOpen((value) => !value);
    }, [open, placePanel]);

    /** Fetch JSON dengan CSRF & deteksi sesi kedaluwarsa. */
    const fetchJson = useCallback(async (url, options = {}) => {
        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                ...options,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(options.headers || {}),
                },
            });

            if (response.status === 401 || response.status === 419) {
                setSessionExpired(true);

                return null;
            }

            if (!response.ok) return null;

            const contentType = response.headers.get('content-type') || '';

            return contentType.includes('application/json') ? response.json() : null;
        } catch (error) {
            return null;
        }
    }, []);

    const applyStatus = useCallback((data, { merge = false } = {}) => {
        if (!data || typeof data.unread !== 'number') return;

        setUnread(data.unread);
        if (typeof data.unassigned === 'number') setUnassigned(data.unassigned);

        const incoming = Array.isArray(data.items) ? data.items : [];

        setItems((previous) => {
            if (!merge) return incoming;

            // Panel terbuka: jangan reset daftar (posisi scroll aman) —
            // sisipkan hanya item yang benar-benar baru, perbarui status baca.
            const known = new Map(previous.map((item) => [item.id, item]));
            const updated = previous.map((item) => {
                const fresh = incoming.find((candidate) => candidate.id === item.id);

                return fresh && fresh.read !== item.read ? { ...item, read: fresh.read } : item;
            });
            const added = incoming
                .filter((item) => !known.has(item.id))
                .map((item) => ({ ...item, _fresh: true }));

            return [...added, ...updated];
        });

        setCursor(data.next_cursor ?? null);
        setHasMore(Boolean(data.has_more));
    }, []);

    const refreshStatus = useCallback(
        async ({ merge } = {}) => {
            if (expiredRef.current || CONFIG.userId == null) return;

            const data = await fetchJson(ROUTES.status);

            if (data) applyStatus(data, { merge: merge ?? openRef.current });
        },
        [applyStatus, fetchJson],
    );

    /** Bell berdering saat notifikasi baru masuk (kecuali reduced-motion). */
    const ringBell = useCallback(() => {
        if (!bellRef.current || reduceMotion) return;

        animate(bellRef.current, { rotate: [0, 14, -10, 6, -3, 0] }, { duration: 0.7, ease: 'easeInOut' });
    }, [reduceMotion]);

    // Realtime via Laravel Echo (Reverb) — channel privat per user.
    // Echo di-bootstrap lazy oleh entry island (chunk async); kalau belum
    // siap, tunggu event `echo:ready`. Tanpa Echo, fallback poll tetap jalan.
    useEffect(() => {
        if (CONFIG.userId == null) return undefined;

        let channel = null;
        let handler = null;
        let connection = null;
        let onState = null;

        const detach = () => {
            if (channel && handler) {
                try {
                    channel.stopListening('.notification.created', handler);
                } catch (error) {
                    /* channel sudah mati — abaikan */
                }
            }

            if (connection && onState) connection.unbind('state_change', onState);

            channel = null;
            handler = null;
            connection = null;
            onState = null;
        };

        const attach = () => {
            const Echo = window.Echo;

            if (!Echo || channel) return;

            try {
                channel = Echo.private(`notifications.${CONFIG.userId}`);
            } catch (error) {
                channel = null;

                return;
            }

            handler = (payload) => {
                const item = payload?.item;

                if (!item || !item.id) return;

                setItems((previous) => (previous.some((known) => known.id === item.id) ? previous : [{ ...item, _fresh: true }, ...previous]));
                setUnread((previous) => (typeof payload.unread === 'number' ? payload.unread : previous + (item.read ? 0 : 1)));
                setLive(`${item.app ? `${item.app}: ` : ''}${item.title}`);
                ringBell();
            };

            channel.listen('.notification.created', handler);

            connection = Echo.connector?.pusher?.connection ?? null;

            if (connection) {
                onState = (state) => setConnected(state === 'connected');
                connection.bind('state_change', onState);
                setConnected(connection.state === 'connected');
            }
        };

        attach();
        window.addEventListener('echo:ready', attach);

        return () => {
            window.removeEventListener('echo:ready', attach);
            detach();
        };
    }, [ringBell]);

    // Fallback: poll 60 dtk saat socket putus + fetch saat tab kembali aktif.
    useEffect(() => {
        if (CONFIG.userId == null || sessionExpired) return undefined;

        const timer = setInterval(() => {
            if (document.hidden || expiredRef.current || connected) return;

            refreshStatus({ merge: openRef.current });
        }, 60000);

        const onWake = () => {
            if (expiredRef.current || document.hidden) return;

            refreshStatus({ merge: openRef.current });
        };

        window.addEventListener('focus', onWake);
        document.addEventListener('visibilitychange', onWake);

        return () => {
            clearInterval(timer);
            window.removeEventListener('focus', onWake);
            document.removeEventListener('visibilitychange', onWake);
        };
    }, [connected, refreshStatus, sessionExpired]);

    // Muat segar tiap panel dibuka.
    useEffect(() => {
        if (!open) return;

        setConfirmClear(false);
        refreshStatus({ merge: false });
    }, [open, refreshStatus]);

    // Panel tetap menempel di bawah bell saat layar di-resize / di-scroll.
    useEffect(() => {
        if (!open) return undefined;

        const onMove = () => placePanel();

        window.addEventListener('resize', onMove);
        window.addEventListener('scroll', onMove, { passive: true });

        return () => {
            window.removeEventListener('resize', onMove);
            window.removeEventListener('scroll', onMove);
        };
    }, [open, placePanel]);

    // Klik di luar & Esc menutup panel.
    useEffect(() => {
        if (!open) return undefined;

        const onPointerDown = (event) => {
            if (rootRef.current && !rootRef.current.contains(event.target)) setOpen(false);
        };
        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                setOpen(false);
                bellRef.current?.focus();
            }
        };

        document.addEventListener('mousedown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('mousedown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    // Jembatan ke Alpine store — dot sidebar memakai angka yang sama.
    useEffect(() => {
        const store = window.Alpine?.store?.('notif');

        if (store) {
            store.unread = unread;
            store.unassigned = unassigned;
        }
    }, [unread, unassigned]);

    useEffect(() => {
        if (sessionExpired && typeof window.toast === 'function') {
            window.toast(I18N.sessionExpired, false, { label: I18N.loginAgain, href: '/login' });
        }
    }, [sessionExpired]);

    const markRead = useCallback(
        async (item) => {
            if (!item || item.read) return;

            setItems((previous) => previous.map((known) => (known.id === item.id ? { ...known, read: true } : known)));
            setUnread((previous) => Math.max(0, previous - 1));

            const data = await fetchJson(withId(ROUTES.read, item.id), {
                method: 'POST',
                keepalive: true,
                headers: { 'X-CSRF-TOKEN': CONFIG.csrf },
            });

            if (data && typeof data.unread === 'number') setUnread(data.unread);
        },
        [fetchJson],
    );

    const openItem = useCallback(
        (item) => {
            markRead(item);

            if (item.url) window.location.assign(item.url);
        },
        [markRead],
    );

    const dismiss = useCallback(
        async (event, item) => {
            event.preventDefault();
            event.stopPropagation();

            setItems((previous) => previous.filter((known) => known.id !== item.id));
            if (!item.read) setUnread((previous) => Math.max(0, previous - 1));

            const data = await fetchJson(withId(ROUTES.destroy, item.id), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CONFIG.csrf },
            });

            if (data && typeof data.unread === 'number') setUnread(data.unread);
        },
        [fetchJson],
    );

    const markAllRead = useCallback(async () => {
        setItems((previous) => previous.map((item) => ({ ...item, read: true })));
        setUnread(0);

        await fetchJson(ROUTES.readAll, { method: 'POST', headers: { 'X-CSRF-TOKEN': CONFIG.csrf } });
    }, [fetchJson]);

    const clearAll = useCallback(async () => {
        setItems([]);
        setUnread(0);
        setCursor(null);
        setHasMore(false);
        setConfirmClear(false);

        await fetchJson(ROUTES.clear, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CONFIG.csrf } });
    }, [fetchJson]);

    const loadMore = useCallback(async () => {
        if (!cursor || loadingMore) return;

        setLoadingMore(true);

        try {
            const data = await fetchJson(`${ROUTES.feed}?cursor=${encodeURIComponent(cursor)}`);

            if (data && Array.isArray(data.items)) {
                setItems((previous) => {
                    const known = new Set(previous.map((item) => item.id));

                    return [...previous, ...data.items.filter((item) => !known.has(item.id))];
                });
                setCursor(data.next_cursor ?? null);
                setHasMore(Boolean(data.has_more));
            }
        } finally {
            setLoadingMore(false);
        }
    }, [cursor, fetchJson, loadingMore]);

    const maskStyle = {
        maskImage: 'linear-gradient(to bottom, transparent 0, black 12px, black calc(100% - 12px), transparent 100%)',
        WebkitMaskImage: 'linear-gradient(to bottom, transparent 0, black 12px, black calc(100% - 12px), transparent 100%)',
    };

    const hasItems = items.length > 0;

    return (
        <div ref={rootRef} className="relative">
            <button
                ref={bellRef}
                type="button"
                onClick={togglePanel}
                className={`beam-notif relative inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-600 transition-all duration-300 hover:scale-105 hover:bg-accent-500/10 hover:text-accent-600 active:scale-95 dark:text-slate-300 dark:hover:bg-accent-400/10 dark:hover:text-accent-400 ${
                    unread > 0 ? 'beam-on' : ''
                }`}
                aria-label={I18N.title}
                aria-haspopup="true"
                aria-expanded={open}
            >
                <TypeIcon name="bell" className="h-5 w-5" />
                <AnimatePresence>
                    {unread > 0 && (
                        <motion.span
                            key="badge"
                            initial={{ scale: 0.4, opacity: 0 }}
                            animate={{ scale: 1, opacity: 1 }}
                            exit={{ scale: 0.4, opacity: 0 }}
                            transition={{ type: 'spring', duration: 0.35, bounce: 0.4 }}
                            className="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"
                        >
                            {unread > 99 ? '99+' : unread}
                        </motion.span>
                    )}
                </AnimatePresence>
            </button>

            <span className="sr-only" aria-live="polite">
                {live}
            </span>

            <AnimatePresence>
                {open && (
                    <motion.div
                        key="panel"
                        role="dialog"
                        aria-label={I18N.title}
                        initial={reduceMotion ? { opacity: 0 } : { opacity: 0, y: -8, scale: 0.98 }}
                        animate={{ opacity: 1, y: 0, scale: 1 }}
                        exit={reduceMotion ? { opacity: 0 } : { opacity: 0, y: -6, scale: 0.98 }}
                        transition={{ duration: 0.18, ease: 'easeOut' }}
                        style={{
                            left: panelPos?.left ?? VIEWPORT_PAD,
                            top: panelPos?.top ?? 0,
                            width: panelPos?.width ?? PANEL_WIDTH,
                            maxHeight: panelPos?.maxHeight,
                            visibility: panelPos ? 'visible' : 'hidden',
                        }}
                        className="fixed z-50 flex origin-top flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/10 dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-black/40"
                    >
                        <div className="flex shrink-0 items-center gap-2 border-b border-slate-100 px-3.5 py-2.5 dark:border-zinc-800">
                            <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-accent-100 text-accent-600 dark:bg-accent-500/15 dark:text-accent-400">
                                <TypeIcon name="bell" className="h-4 w-4" />
                            </span>
                            <span className="text-sm font-semibold text-slate-800 dark:text-slate-100">{I18N.title}</span>
                            {unread > 0 && (
                                <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">
                                    {unread > 99 ? '99+' : unread}
                                </span>
                            )}

                            <span className="ml-auto flex items-center gap-1.5">
                                {confirmClear ? (
                                    <>
                                        <button
                                            type="button"
                                            onClick={clearAll}
                                            className="rounded-lg border border-red-200 px-2 py-1 text-[11px] font-medium text-red-600 transition hover:bg-red-600 hover:text-white dark:border-red-500/40 dark:text-red-400 dark:hover:bg-red-600 dark:hover:text-white"
                                        >
                                            {I18N.confirm}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setConfirmClear(false)}
                                            className="rounded-lg px-2 py-1 text-[11px] font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-zinc-800 dark:hover:text-slate-200"
                                        >
                                            {I18N.cancel}
                                        </button>
                                    </>
                                ) : (
                                    <>
                                        {unread > 0 && (
                                            <button
                                                type="button"
                                                onClick={markAllRead}
                                                className="rounded-lg px-2 py-1 text-[11px] font-medium text-accent-600 transition hover:bg-accent-50 dark:text-accent-400 dark:hover:bg-accent-500/10"
                                            >
                                                {I18N.markAllRead}
                                            </button>
                                        )}
                                        {hasItems && (
                                            <button
                                                type="button"
                                                onClick={() => setConfirmClear(true)}
                                                className="rounded-lg px-2 py-1 text-[11px] font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-zinc-800 dark:hover:text-slate-200"
                                            >
                                                {I18N.clear}
                                            </button>
                                        )}
                                    </>
                                )}
                            </span>
                        </div>

                        <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-1.5 py-1.5" style={maskStyle}>
                            {hasItems ? (
                                <AnimatePresence initial={false}>
                                    {items.map((item) => (
                                        <NotificationCard
                                            key={item.id}
                                            item={item}
                                            reduceMotion={reduceMotion}
                                            onOpen={openItem}
                                            onDismiss={dismiss}
                                        />
                                    ))}
                                </AnimatePresence>
                            ) : (
                                <div className="flex flex-col items-center gap-2 px-6 py-10 text-center">
                                    <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-zinc-800 dark:text-slate-500">
                                        <TypeIcon name="bell" className="h-5 w-5" />
                                    </span>
                                    <p className="text-sm font-medium text-slate-600 dark:text-slate-300">{I18N.empty}</p>
                                    <p className="text-xs text-slate-400 dark:text-slate-500">{I18N.emptyHint}</p>
                                </div>
                            )}
                        </div>

                        {hasMore && (
                            <div className="shrink-0 border-t border-slate-100 px-3.5 py-2 dark:border-zinc-800">
                                <button
                                    type="button"
                                    onClick={loadMore}
                                    disabled={loadingMore}
                                    className="w-full rounded-xl px-3 py-2 text-xs font-medium text-accent-600 transition hover:bg-accent-50 disabled:opacity-50 dark:text-accent-400 dark:hover:bg-accent-500/10"
                                >
                                    {loadingMore ? I18N.loading : I18N.loadMore}
                                </button>
                            </div>
                        )}
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}

