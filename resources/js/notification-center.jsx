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

document.querySelectorAll('[data-notification-center]').forEach((element) => {
    createRoot(element).render(<NotificationCenter />);
});
