import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { AnimatePresence } from 'motion/react';
import NotificationToast from './NotificationToast.jsx';
import { subscribeToast } from '../toast-store.js';

/**
 * Panggung pop up notifikasi (pojok kanan bawah).
 *
 * Di-portal ke <body> supaya posisi `fixed` tidak "nyangkut" ke header yang
 * memakai backdrop-blur (masalah yang sama sudah ditangani panel lonceng).
 */
export default function ToastViewport() {
    const [toasts, setToasts] = useState(() => []);
    const [mounted, setMounted] = useState(false);
    const [pausedId, setPausedId] = useState(null);

    useEffect(() => {
        setMounted(true);

        return subscribeToast((next) => setToasts(next));
    }, []);

    if (!mounted || typeof document === 'undefined') return null;

    return createPortal(
        <div
            aria-live="polite"
            aria-atomic="false"
            className="pointer-events-none fixed inset-x-3 bottom-3 z-[100] flex w-auto flex-col items-stretch gap-2 sm:inset-x-auto sm:bottom-6 sm:right-6 sm:w-[420px] sm:gap-3"
        >
            <AnimatePresence initial={false}>
                {toasts.map((toast) => (
                    <NotificationToast
                        key={toast.id}
                        toast={toast}
                        paused={pausedId === toast.id}
                        onPauseChange={(value) => setPausedId(value ? toast.id : null)}
                    />
                ))}
            </AnimatePresence>
        </div>,
        document.body,
    );
}
