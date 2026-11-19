import { useCallback, useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { AnimatePresence } from 'motion/react';
import NotificationToast from './NotificationToast.jsx';
import { subscribeToast } from '../toast-store.js';

/**
 * Panggung pop up notifikasi (pojok kanan bawah).
 *
 * Perilaku meniru Sonner (komponen 21st.dev "arunachalam/toast"):
 * - Kartu baru muncul di depan (paling bawah), kartu lama "menumpuk" di
 *   belakangnya dan hanya menyisakan sisi atas ~16px.
 * - Saat kursor diarahkan ke tumpukan, kartu mengembang jadi daftar penuh
 *   supaya semuanya bisa dibaca, dan hitungan hilangnya berhenti.
 * - Maksimal 3 kartu (lihat toast-store).
 *
 * Di-portal ke <body> supaya posisi `fixed` tidak "nyangkut" ke header yang
 * memakai backdrop-blur (masalah yang sama sudah ditangani panel lonceng).
 */
const PEEK = 16; // selisih tampak tiap kartu saat menumpuk
const GAP = 12; // jarak antar kartu saat mengembang

export default function ToastViewport() {
    const [toasts, setToasts] = useState(() => []);
    const [heights, setHeights] = useState({});
    const [mounted, setMounted] = useState(false);
    const [expanded, setExpanded] = useState(false);

    useEffect(() => {
        setMounted(true);

        return subscribeToast((next) => setToasts(next));
    }, []);

    const reportHeight = useCallback((id, height) => {
        setHeights((previous) => (previous[id] === height ? previous : { ...previous, [id]: height }));
    }, []);

    const count = toasts.length;

    // depth 0 = kartu paling depan (paling baru). expandedY menumpuk kartu
    // tepat di atas kartu di depannya memakai tinggi asli masing-masing.
    const { positions, expandedHeight, collapsedHeight } = useMemo(() => {
        const map = new Map();
        let stack = 0;

        [...toasts].reverse().forEach((toast, depth) => {
            const height = heights[toast.id] || 0;

            map.set(toast.id, {
                depth,
                expandedY: -stack,
                stackedY: -depth * PEEK,
                scale: 1 - depth * 0.05,
            });

            stack += height + GAP;
        });

        const front = toasts[count - 1];

        return {
            positions: map,
            expandedHeight: Math.max(0, stack - GAP),
            collapsedHeight: (front ? heights[front.id] || 0 : 0) + Math.max(0, count - 1) * PEEK,
        };
    }, [toasts, heights, count]);

    if (!mounted || typeof document === 'undefined') return null;

    return createPortal(
        <div
            aria-live="polite"
            aria-atomic="false"
            onMouseEnter={() => setExpanded(true)}
            onMouseLeave={() => setExpanded(false)}
            onFocus={() => setExpanded(true)}
            onBlur={() => setExpanded(false)}
            style={{ height: (expanded ? expandedHeight : collapsedHeight) || undefined, pointerEvents: count ? 'auto' : 'none' }}
            className="fixed inset-x-3 bottom-24 z-[1200] transition-[height] duration-300 ease-out sm:inset-x-auto sm:bottom-24 sm:right-6 sm:w-[420px]"
        >
            <AnimatePresence initial={false}>
                {toasts.map((toast) => {
                    const position = positions.get(toast.id) || {
                        depth: 0,
                        expandedY: 0,
                        stackedY: 0,
                        scale: 1,
                    };

                    return (
                        <NotificationToast
                            key={toast.id}
                            toast={toast}
                            paused={expanded}
                            depth={position.depth}
                            y={expanded ? position.expandedY : position.stackedY}
                            scale={expanded ? 1 : position.scale}
                            zIndex={count - position.depth}
                            clickable={expanded || position.depth === 0}
                            onHeight={reportHeight}
                        />
                    );
                })}
            </AnimatePresence>
        </div>,
        document.body,
    );
}
