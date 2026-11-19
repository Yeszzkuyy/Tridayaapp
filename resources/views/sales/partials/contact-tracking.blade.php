<script>
    (function () {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

        document.querySelectorAll('[data-contact-channel]').forEach(function (el) {
            el.addEventListener('click', function () {
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
        });
    })();
</script>
