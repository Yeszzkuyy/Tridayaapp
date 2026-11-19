/**
 * Onboarding singkat My Leads (Driver.js):
 * menyorot tombol Follow Up pertama supaya sales baru tahu mulai dari mana.
 * Hanya jalan sekali (localStorage) dan hanya kalau tabel punya data.
 */
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';

const STORAGE_KEY = 'myleads_tour_done_v1';

export function initLeadsTour() {
    if (localStorage.getItem(STORAGE_KEY) === '1') {
        return;
    }

    const target = document.querySelector('[data-tour="followup"]');

    if (!target) {
        return;
    }

    const tour = driver({
        showProgress: true,
        allowClose: true,
        overlayColor: 'rgba(15, 23, 42, 0.6)',
        stagePadding: 6,
        steps: [
            {
                element: '[data-tour="followup"]',
                popover: {
                    title: 'Mulai follow up di sini',
                    description: 'Klik tombol ini untuk mencatat hasil telepon, chat, atau meeting dengan prospek. Riwayatnya otomatis masuk ke timeline.',
                    side: 'left',
                    align: 'center',
                    nextBtnText: 'Mengerti',
                    doneBtnText: 'Selesai',
                },
            },
        ],
        onDestroyed: () => {
            localStorage.setItem(STORAGE_KEY, '1');
        },
    });

    tour.drive();
}

initLeadsTour();
