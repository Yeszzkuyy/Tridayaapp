import { createRoot } from 'react-dom/client';
import NotificationCenter from './components/NotificationCenter.jsx';

// Island entry: mount Notification Center (bell + feed di header) di elemen
// bertanda `data-notification-center`. Konfigurasi awal dari server lewat
// `window.notifCenter` (lihat layouts/app.blade.php).
document.querySelectorAll('[data-notification-center]').forEach((element) => {
    createRoot(element).render(<NotificationCenter />);
});
