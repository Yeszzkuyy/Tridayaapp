import { createRoot } from 'react-dom/client';
import NotificationCenter from './components/NotificationCenter.jsx';
import { initEcho } from './echo.js';

// Island entry: mount Notification Center (bell + feed di header) di elemen
// bertanda `data-notification-center`. Konfigurasi awal dari server lewat
// `window.notifCenter` (lihat layouts/app.blade.php).
//
// Echo (Reverb) diinisialisasi lebih dulu supaya island langsung mendengar
// channel privat user saat mount; kalau tidak tersedia, island memakai
// fallback fetch/poll.
initEcho();

let mountedElement = null;

// Bell harus TAMPIL TERUS di navbar. Elemen mount diberi `wire:ignore` di
// header (tidak ikut diganti saat pindah menu wire:navigate); mountAll()
// di bawah adalah jaring pengaman kalau toh elemennya sampai terganti —
// pola sama dengan island lain di repo ini (glide-select, rubber-segment).
function mountAll() {
    const element = document.querySelector('[data-notification-center]');

    if (!element || element === mountedElement) return;

    // Elemen lama sudah lepas dari DOM (mis. morph mengganti layout):
    // putus dulu langganan realtime lama supaya tidak ada handler ganda.
    if (mountedElement && !mountedElement.isConnected && window.notifCenter?.userId != null) {
        try {
            window.Echo?.leave?.(`notifications.${window.notifCenter.userId}`);
        } catch (error) {
            /* Echo belum/tidak jalan — abaikan */
        }
    }

    // Fallback statis dari Blade (bell polos sebelum React siap) dibuang.
    element.querySelectorAll('[data-notif-fallback]').forEach((node) => node.remove());

    mountedElement = element;
    createRoot(element).render(<NotificationCenter />);
}

mountAll();

document.addEventListener('livewire:navigated', mountAll);
