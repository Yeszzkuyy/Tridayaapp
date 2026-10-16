/**
 * Bootstrap Laravel Echo (Laravel Reverb) — lazy, dipanggil entry island.
 *
 * `laravel-echo` + `pusher-js` (~30 kB gzip) sengaja di-import dinamis supaya
 * masuk chunk async terpisah: first paint halaman tidak menunggu paket ini.
 * Setelah siap, event `echo:ready` dikirim supaya Notification Center langsung
 * berlangganan channel privat user.
 *
 * Browser konek lewat wss publik (Cloudflare tunnel -> nginx /app & /apps ->
 * 127.0.0.1:8080); kredensial dari VITE_REVERB_* saat build. Kalau Reverb
 * tidak dikonfigurasi (key kosong), island tetap jalan dengan fallback fetch
 * saat panel dibuka + poll 60 detik.
 */
export async function initEcho() {
    if (typeof window === 'undefined') return null;
    if (window.Echo) return window.Echo;

    const key = import.meta.env.VITE_REVERB_APP_KEY;

    if (!key) return null;

    try {
        const [{ default: Echo }, { default: Pusher }] = await Promise.all([
            import('laravel-echo'),
            import('pusher-js'),
        ]);

        if (window.Echo) return window.Echo;

        window.Pusher = Pusher;

        window.Echo = new Echo({
            broadcaster: 'reverb',
            key,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
            wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN':
                        window.notifCenter?.csrf ??
                        document.querySelector('meta[name="csrf-token"]')?.content ??
                        '',
                },
            },
        });

        window.dispatchEvent(new CustomEvent('echo:ready'));

        return window.Echo;
    } catch (error) {
        // Pembundelan/pemuatan Echo gagal: fallback polling tetap bekerja.
        return null;
    }
}
