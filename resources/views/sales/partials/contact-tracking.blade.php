<script>
    (function () {
        if (window.__contactTrackingBound) return;
        window.__contactTrackingBound = true;

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

        // Delegasi ke document: tombol kontak bisa berada di menu tiga titik
        // (di-teleport ke body) atau di tabel yang diganti lewat AJAX, jadi
        // binding per elemen saat halaman dimuat tidak menjangkau semuanya.
        document.addEventListener('click', function (event) {
            const el = event.target?.closest?.('[data-contact-channel]');
            if (!el) return;

            const url = el.dataset.contactUrl;
            if (!url) return;

            fetch(url, {
                method: 'POST',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                },
                body: JSON.stringify({
                    channel: el.dataset.contactChannel,
                    follow_up_id: el.dataset.contactFollowUp || null,
                }),
            }).catch(function () {
                // Diamkan: membuka WA/Email/Call tidak boleh gagal karena audit.
            });
        });
    })();
</script>
