{{--
    Halaman auth (login, verifikasi email, pending approval, reset password)
    tidak boleh hidup di dalam panel tab workspace. Saat sesi habis, request
    dari iframe dialihkan ke /login; tanpa pengaman ini form login muncul kecil
    di dalam panel dan sidebar/navbar induk tetap terlihat sampai user
    me-refresh manual. Dijalankan di <head> agar tidak ada kedipan.
--}}
<script data-workspace-auth-guard>
    (function () {
        if (window.self === window.top) return;

        var target = window.location.href;
        try {
            var url = new URL(target);
            url.searchParams.delete('embed');
            target = url.toString();
        } catch (e) {}

        // Shell workspace mendengarkan pesan ini. Kalau listener-nya belum
        // siap, navigasi langsung ke window teratas tetap menyelamatkan
        // halaman dari login yang tampil di dalam panel.
        try {
            window.parent.postMessage(
                { source: '3dy-workspace', type: 'login', url: target },
                window.location.origin,
            );
        } catch (e) {}

        try {
            window.top.location.replace(target);
        } catch (e) {}
    })();
</script>
