{{--
    Komponen Alpine untuk daftar aktivitas "hidup":
    - label waktu relatif ("baru saja", "5 menit lalu") dihitung ulang tiap 30 detik;
    - daftar di-poll tiap 60 detik (hanya saat tab aktif) dan diganti bila isinya berubah,
      jadi aktivitas baru muncul tanpa reload.

    Pakai: x-data="liveFeed(@js($url), 60000)"  (url null = hanya label waktu)
--}}
@once
<script>
    function liveFeed(url = null, pollMs = 60000) {
        return {
            labelTimer: null,
            pollTimer: null,

            init() {
                this.refreshLabels();
                this.labelTimer = setInterval(() => this.refreshLabels(), 30000);

                if (url) {
                    this.pollTimer = setInterval(() => this.poll(url), pollMs);
                }
            },

            destroy() {
                clearInterval(this.labelTimer);
                clearInterval(this.pollTimer);
            },

            /** Hitung ulang teks "x menit lalu" dari atribut datetime. */
            refreshLabels() {
                const nodes = this.$el.querySelectorAll('time[data-relative]');
                if (nodes.length === 0) {
                    return;
                }

                const locale = document.documentElement.lang || 'en';
                const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
                const units = [
                    ['year', 31536000],
                    ['month', 2592000],
                    ['week', 604800],
                    ['day', 86400],
                    ['hour', 3600],
                    ['minute', 60],
                ];

                nodes.forEach((el) => {
                    const iso = el.getAttribute('datetime');
                    if (!iso) {
                        return;
                    }

                    const seconds = (Date.now() - new Date(iso).getTime()) / 1000;

                    for (const [unit, size] of units) {
                        if (Math.abs(seconds) >= size) {
                            el.textContent = rtf.format(-Math.round(seconds / size), unit);
                            return;
                        }
                    }

                    el.textContent = rtf.format(-Math.round(seconds), 'second');
                });
            },

            /** Ambil ulang daftar dari server; ganti hanya kalau isinya beda. */
            async poll(feedUrl) {
                if (document.hidden) {
                    return;
                }

                try {
                    const response = await fetch(feedUrl, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        return;
                    }

                    const html = (await response.text()).trim();
                    if (html === '' || html === this.$el.innerHTML.trim()) {
                        return;
                    }

                    this.$el.innerHTML = html;
                    this.refreshLabels();
                    this.$el.dispatchEvent(new CustomEvent('feed:updated', { bubbles: true }));
                } catch (error) {
                    // Offline / server sibuk: coba lagi di putaran berikutnya.
                }
            },
        };
    }
</script>
@endonce
