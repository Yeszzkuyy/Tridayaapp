@php
    $themePref = auth()->check() ? auth()->user()->preference('theme', 'system') : 'system';
    $accentPref = auth()->check() ? auth()->user()->preference('accent', 'ocean') : 'ocean';
    // Class dark dirender dari server agar morph wire:navigate tidak menghapusnya
    // (HTML server tanpa class = class 'dark' client terbuang -> balik light).
    // Mode system TETAP dirender 'dark' (optimistik): server tak tahu skema OS,
    // dan bila dibiarkan kosong, replaceHtmlAttributes() Livewire menghapus class
    // 'dark' tiap navigasi -> kilatan frame putih di mode system (OS gelap).
    // Untuk OS terang, __applyAppearance() mengoreksi SEBELUM paint pertama
    // (script head saat load, hook onSwap saat navigasi) jadi tak ada kilatan
    // terbalik.
    $initialDark = $themePref !== 'light';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-mode="{{ $themePref }}" data-theme="{{ $accentPref }}" class="{{ $initialDark ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Tridaya App') }}</title>

    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo/Logo3dydark.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800|exo-2:500,600,700,800&display=swap" rel="stylesheet" />

    <script>
        // Sumber kebenaran: localStorage (ditulis tiap ganti tema) fallback ke
        // data-mode server. Dijalankan saat full load DAN tiap livewire:navigated
        // (navigasi SPA tidak me-reload <head>, morph justru bisa menghapus
        // class 'dark' client karena respons server tak memuatnya untuk mode system).
        window.__applyAppearance = function () {
            var root = document.documentElement;
            var pref = root.getAttribute('data-mode') || @json($themePref);
            var stored = null;
            try { stored = localStorage.getItem('appearance-mode'); } catch (e) {}
            var mode = stored || pref || 'system';
            // Migrasi dari key lama 'dark-mode' bila belum pakai appearance-mode
            if (!stored) {
                var legacy = null;
                try { legacy = localStorage.getItem('dark-mode'); } catch (e) {}
                if (legacy === 'true') mode = 'dark';
                else if (legacy === 'false') mode = 'light';
            }
            var accent = @json($accentPref);
            try { accent = localStorage.getItem('appearance-accent') || accent; } catch (e) {}
            var dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme:dark)').matches);
            root.classList.toggle('dark', dark);
            root.setAttribute('data-mode', mode);
            root.setAttribute('data-theme', accent);
            window.__appearanceMode = mode;
            window.__appearanceAccent = accent;
        };
        window.__applyAppearance();
        // Sinkron saat skema OS berubah (mode system) tanpa reload.
        try {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () { window.__applyAppearance(); });
        } catch (e) {}
        // Jaring pengaman terakhir: bila setelah paint halaman tidak sinkron
        // dengan preferensi, pulihkan + beri tahu di console supaya celah apa
        // pun yang tersisa ketahuan (bukan diam-diam salah tema).
        function __appearanceSelfHeal() {
            try {
                var root = document.documentElement;
                var shouldDark = window.__appearanceMode === 'dark'
                    || (window.__appearanceMode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                if (root.classList.contains('dark') !== shouldDark) {
                    window.__applyAppearance();
                    console.warn('[appearance] kelas dark tidak sinkron setelah paint — dipulihkan otomatis');
                }
            } catch (e) {}
        }
        document.addEventListener('livewire:navigated', function () { window.__applyAppearance(); setTimeout(__appearanceSelfHeal, 120); });
        setTimeout(__appearanceSelfHeal, 300);
        // wire:navigate: Livewire menyalin atribut <html> dari hasil render server
        // (replaceHtmlAttributes) — data-theme/class bisa beda dengan localStorage,
        // sehingga halaman baru sempat ter-paint dengan tema salah (frame putih di
        // border kartu + aksen ungu) sampai livewire:navigated memulihkannya.
        // Hook onSwap dipanggil SETELAH body baru terpasang tapi SEBELUM paint
        // pertama, jadi koreksi temanya tak pernah terlihat mata.
        document.addEventListener('livewire:navigating', function (e) {
            if (!e.detail || typeof e.detail.onSwap !== 'function') return;
            e.detail.onSwap(function () {
                window.__applyAppearance();
                // Class yang dipasang JS ikut ter-reset oleh replaceHtmlAttributes.
                var root = document.documentElement;
                root.classList.add('js');
                try { if (localStorage.getItem('sidebar-collapsed') === '1') root.classList.add('sidebar-collapsed'); } catch (err) {}
            });
        });
    </script>

    <script>
        (function () {
            try { if (localStorage.getItem('sidebar-collapsed') === '1') document.documentElement.classList.add('sidebar-collapsed'); } catch (e) {}
        })();
    </script>

    @php
        $notifInit = ['unread' => 0, 'unassigned' => 0, 'items' => []];
        if (auth()->check() && auth()->user()->canany(['manage-sales-leads', 'manage-sales'])) {
            $notifInit = [
                'unread' => auth()->user()->unreadNotifications()->count(),
                'unassigned' => \App\Models\Lead::whereNull('assigned_to')->count(),
                'items' => \App\Http\Controllers\NotificationController::itemsFor(auth()->user()),
            ];
        }
    @endphp
    <script>window.notifInit = @json($notifInit);</script>
    @auth
    <script>window.vapidPublicKey = @json(config('webpush.vapid.public_key'));</script>
    @endauth
    {{-- Penanda JS aktif sepagi mungkin (sebelum CSS) agar animasi appear
         sempat mulai dari state awal, bukan langsung final.
         reveal-on: [data-reveal] disembunyikan (opacity 0) HANYA bila JS
         jalan; fallback mandiri di sini memaksa tampil bila script ekor
         body gagal — cegah dashboard blank putih.

         PENTING: navigasi di app ini full page reload (Livewire JS tidak
         dimuat), jadi animasi reveal akan main ulang tiap pindah menu dan
         itulah sumber "jebret". Karena itu reveal HANYA pada kunjungan
         pertama per sesi; kunjungan berikutnya langsung tampil final. --}}
    <script>
        document.documentElement.classList.add('js');
        (function () {
            var seen = false;
            try { seen = sessionStorage.getItem('ui-revealed') === '1'; } catch (e) {}
            if (!seen) {
                // Kunjungan pertama: animasi reveal + staggered sub-menu sidebar.
                // Navigasi = full page reload, jadi tanpa penanda ini tiap
                // pindah menu memutar ulang efek "turun-turun" sub-menu aktif.
                document.documentElement.classList.add('reveal-on');
                try { sessionStorage.setItem('ui-revealed', '1'); } catch (e) {}
                setTimeout(function () {
                    try { document.querySelectorAll('[data-reveal]:not(.in-view)').forEach(function (el) { el.classList.add('in-view'); }); } catch (e) {}
                }, 1200);
            } else {
                document.documentElement.classList.add('sidebar-settled');
            }
        })();
    </script>

    @livewireStyles

    @vite(['resources/css/app.css','resources/js/app.js'])

    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        [x-cloak]{display:none!important}
        .icon-expand{display:none}

        /* ============================================================
           Sidebar morph — lebar berubah dengan spring (linear() easing
           hasil sampling respons critically-damped: k380 c35 m0.75),
           bukan bezier. Delay/spring dinaikkan hanya di width; yang
           rail pakai lebar sama supaya konten ikut propulsion.
           ============================================================ */
        :root{
            --sb-w:16rem;
            --sb-w-rail:4.25rem;
            --sb-dur:.32s;
            --sb-ease:linear(0.000, 0.100, 0.289, 0.475, 0.629, 0.745, 0.829, 0.887, 0.926, 0.952, 0.969, 0.981, 0.988, 0.992, 0.995);
            --sb-ease-soft:cubic-bezier(.16,1,.3,1);
            /* Label: enter lebih lambat (200ms + delay 80ms), exit cepat (120ms) */
            --sb-label-in:200ms;
            --sb-label-in-delay:80ms;
            --sb-label-out:120ms;
        }

        /* Label sidebar: memudar + geser, asimetris enter/exit ala template.
           max-width:0 (bukan display:none) supaya ruang ikut menyusut
           halus dan ikon tetap center di rail. */
        .sidebar .sidebar-hide,
        .sidebar nav a > span,
        .sidebar nav button > span{
            max-width:16rem;
            overflow:hidden;
            transition:
                opacity var(--sb-label-in) var(--sb-ease-soft) var(--sb-label-in-delay),
                transform var(--sb-label-in) var(--sb-ease-soft) var(--sb-label-in-delay),
                max-width var(--sb-label-in) var(--sb-ease-soft) var(--sb-label-in-delay);
        }

        .sidebar{position:fixed;top:0;left:0;height:100vh;width:var(--sb-w);background:var(--sidebar-bg);border-right:1px solid var(--sidebar-border);z-index:999;transform:translateX(-100%);transition:transform .3s ease-in-out,width var(--sb-dur) var(--sb-ease);overflow-y:auto}
        .sidebar.open{transform:translateX(0)}
        .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:998}
        .sidebar-overlay.active{display:block}
        .main-content{margin-left:0;width:100%;min-height:100vh;display:flex;flex-direction:column;transition:margin-left var(--sb-dur) var(--sb-ease),width var(--sb-dur) var(--sb-ease)}

        /* Rail hover: strip tipis di tepi sidebar, muncul garis saat hover.
           Memakai --sb-w-rail (bukan 76px) supaya rail ikut.spring. */
        .sb-rail{position:absolute;top:0;bottom:0;left:100%;width:1rem;z-index:21;cursor:pointer;background:transparent;transition:background-color 200ms ease}
        .sb-rail:hover,.sb-rail:focus-visible{background:rgb(var(--accent-500)/.07)}
        .sb-rail::after{content:"";position:absolute;top:50%;left:50%;width:1px;height:34px;margin-top:-17px;margin-left:-.5px;border-radius:2px;background:var(--sidebar-border);opacity:0;transition:opacity 200ms ease,background-color 200ms ease}
        .sb-rail:hover::after,.sb-rail:focus-visible::after{opacity:1;background:rgb(var(--accent-500)/.5)}

        @media(min-width:1024px){
            .sidebar{position:fixed;left:0;top:0;transform:translateX(0)!important;width:var(--sb-w);height:100vh;overflow:hidden}
            .sidebar-overlay{display:none!important}
            .main-content{margin-left:var(--sb-w);width:calc(100% - var(--sb-w));flex:1;min-width:0}
            .app-wrapper{display:flex;min-height:100vh}
            #hamburgerBtn{display:none!important}
            .sb-rail{display:block}
        }

        @media(max-width:1023px){
            .sb-rail{display:none}
        }

        @media(min-width:1024px){
            /* Sidebar rail (icons only) — width jujur ditransisi */
            html.sidebar-collapsed{--sb-w:var(--sb-w-rail)}
            html.sidebar-collapsed .sidebar-hide,
            html.sidebar-collapsed .sidebar nav a > span,
            html.sidebar-collapsed .sidebar nav button > span{
                max-width:0;
                opacity:0;
                transform:translateX(-4px);
                pointer-events:none;
                transition:
                    opacity var(--sb-label-out) ease,
                    transform var(--sb-label-out) ease,
                    max-width var(--sb-label-out) ease;
            }
            html.sidebar-collapsed .sidebar nav button > svg.ml-auto{display:none!important}
            html.sidebar-collapsed .sidebar nav a,
            html.sidebar-collapsed .sidebar nav button{justify-content:center;padding-left:0;padding-right:0}
            /* gap:0 WAJIB:Utility gap-3 tetap menghitung 12px walau label
               max-width:0, jadi ikon terdorong ~6px dari tengah rail.
               Tanpa ini semua ikon menu terlihat tidak simetris. */
            html.sidebar-collapsed .sidebar nav a,
            html.sidebar-collapsed .sidebar nav button,
            html.sidebar-collapsed .sidebar .sb-head,
            html.sidebar-collapsed .sidebar-user,
            html.sidebar-collapsed .sidebar-collapse{gap:0}
            /* ml-auto pada span notif/label tetap menyerap ruang kosong meski
               max-width:0 → ikon terdorong ke tepi kiri. Setel margin 0. */
            html.sidebar-collapsed .sidebar nav a > span.ml-auto,
            html.sidebar-collapsed .sidebar nav button > span.ml-auto{margin-left:0!important;margin-right:0!important}
            /* Toggle sub-menu tak ada gunanya di rail (sub-menu tersembunyi)
               dan diameternya bikin header grup tidak simetris. */
            html.sidebar-collapsed .sidebar .sb-toggle{display:none!important}
            html.sidebar-collapsed .sidebar .sb-head{padding-left:0;padding-right:0;justify-content:center}
            html.sidebar-collapsed .sidebar nav{padding-left:.5rem;padding-right:.5rem}
            html.sidebar-collapsed .sidebar-logo{padding-left:.5rem;padding-right:.5rem;justify-content:center}
            html.sidebar-collapsed .sidebar-logo img{height:1.75rem}
            html.sidebar-collapsed .icon-collapse{display:none}
            html.sidebar-collapsed .icon-expand{display:block}
            html.sidebar-collapsed .sidebar-user,
            html.sidebar-collapsed .sidebar-collapse{justify-content:center;padding-left:0;padding-right:0}
        }
        @media(max-width:1023px){
            .app-wrapper{display:flex;min-height:100vh}
            .sidebar{width:18rem}
            .main-content{flex:1}
        }

        /* Reduced motion: matikan morph lebar + label + stagger (template:
           morph duration 0). Sub-menu sudah ditangani sidebar-nav.css. */
        @media(prefers-reduced-motion:reduce){
            .sidebar,.main-content{transition:none}
            .sidebar .sidebar-hide,
            .sidebar nav a > span,
            .sidebar nav button > span{transition:none}
        }

        /* Penanda halaman aktif di navbar: fade+geser halus tiap navigasi.
           Dipicu ulang via JS (livewire:navigated); hanya elemen aktif. */
        @media(prefers-reduced-motion:no-preference){
            @keyframes nav-active-in{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:none}}
            .nav-active-swap{animation:nav-active-in 250ms ease-out}
        }

        .dark .bg-white{background-color:var(--card-bg)!important}
        .dark #darkToggle .bg-white{background-color:#fff!important}
        .dark .bg-slate-50{background-color:var(--card-bg)!important}
        .dark .bg-slate-100{background-color:var(--input-bg)!important}
        .dark .bg-slate-200{background-color:var(--card-border)!important}
        .dark .bg-slate-300{background-color:var(--card-border)!important}
        /* Toggle ON memakai accent token walau dark-mode mengoverride bg-slate-300 */
        .peer:checked ~ .peer-checked\:bg-blue-600{background-color:rgb(var(--accent-600) / 1)!important}
        .dark .bg-gray-300{background-color:var(--card-border)!important}
        .dark .bg-gray-100{background-color:var(--input-bg)!important}
        .dark .hover\:bg-slate-100:hover{background-color:var(--input-bg)!important}
        .dark .hover\:bg-slate-200:hover{background-color:var(--card-border)!important}
        .dark .hover\:bg-slate-300:hover{background-color:var(--card-border)!important}
        .dark .hover\:bg-slate-50:hover{background-color:var(--card-bg-hover)!important}
        .dark .hover\:bg-gray-50:hover{background-color:var(--card-bg-hover)!important}
        .dark .border-slate-200{border-color:var(--card-border)!important}
        .dark .border-slate-300{border-color:var(--input-border)!important}
        .dark .text-slate-900,.dark .text-gray-800{color:var(--text-primary)!important}
        .dark .text-slate-800,.dark .text-gray-900,.dark .text-slate-700,.dark .text-gray-700{color:var(--text-primary)!important}
        .dark .text-slate-600,.dark .text-gray-600{color:var(--text-secondary)!important}
        .dark .text-slate-500,.dark .text-gray-500{color:var(--text-muted)!important}
        /* Teks tanpa class warna ikut var tema — jangan biarkan hitam bawaan browser di mode gelap */
        /* Warna dasar halaman sepagi mungkin (sebelum CSS Vite tiba):
           tanpa ini var(--bg) belum ada saat cat pertama -> putih sekilas di mode gelap. */
        :root{--bg:#f1f5f9}html.dark{--bg:#1c2235}
        body{background-color:var(--bg)}
        html.dark body{background-color:var(--bg)!important;color:var(--text-primary)}
        .dark th.text-slate-500,.dark th.text-slate-600{color:var(--text-secondary)!important}
        input:not([type=checkbox]):not([type=radio]):not([type=file]):not([type=color]):not([type=range]):not([type=hidden]),select,textarea{background-color:var(--input-bg)!important;border-color:var(--input-border)!important;color:var(--input-text)!important}
        input:not([type=checkbox]):not([type=radio]):not([type=file]):not([type=color]):not([type=range]):not([type=hidden]):hover,select:hover,textarea:hover{background-color:var(--input-bg-hover)!important}
        input:focus,select:focus,textarea:focus{border-color:var(--input-border-focus)!important}
        input::placeholder,textarea::placeholder{color:var(--text-muted)!important}
        .dark input:not([type=checkbox]):not([type=radio]):not([type=range]):not([type=color]){color-scheme:dark}
        /* Progress bar pindah menu (wire:navigate): strip accent meluncur
           kiri-ke-kanan selama navigating; hormati reduced-motion. */
        #navigate-progress{position:fixed;top:0;left:0;height:3px;width:35%;z-index:1002;opacity:0;pointer-events:none;border-radius:0 3px 3px 0;background:linear-gradient(90deg,rgb(var(--accent-500)/0),rgb(var(--accent-500)),rgb(var(--accent-400)));box-shadow:0 0 12px rgb(var(--accent-500)/.7);transform:translateX(-110%);transition:opacity .25s ease}
        #navigate-progress[data-run]{opacity:1;animation:nav-progress-slide 1.1s ease-in-out infinite}
        @keyframes nav-progress-slide{0%{transform:translateX(-110%)}100%{transform:translateX(310%)}}
        @media(prefers-reduced-motion:reduce){#navigate-progress{display:none}}
    </style>
</head>
<body class="font-sans antialiased">

    <div id="navigate-progress" aria-hidden="true"></div>
    <button id="sidebarRail" class="sb-rail" type="button" tabindex="-1"
            aria-label="{{ __('Perkecil sidebar') }}"></button>
    <div id="sidebarOverlay" class="sidebar-overlay dark:bg-black/60"></div>

    <div class="app-wrapper">
        <div id="sidebar" class="sidebar">
            @include('layouts.partials.sidebar')
        </div>

        <div class="main-content">
            @include('layouts.partials.header')
            @include('partials.avatar-lightbox')

            <div class="px-4 sm:px-6 lg:px-8 pt-5">
                @if(session('success') && !session('success_card'))
                    <x-toast type="success">{{ session('success') }}</x-toast>
                @endif
                @if(session('error'))
                    <x-toast type="error">{{ session('error') }}</x-toast>
                @endif
                @if($errors->any())
                    <x-toast type="error">
                        <p class="font-semibold mb-1">{{ __('Terdapat kesalahan pada form:') }}</p>
                        <ul class="list-disc list-inside text-sm">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-toast>
                @endif
            </div>

            <main class="relative flex-1 px-4 sm:px-6 lg:px-8 pb-8">
                @isset($header)<div class="mb-6">{{ $header }}</div>@endisset
                {{ $slot }}
            </main>

            <footer class="px-4 sm:px-6 lg:px-8 pb-4 text-center text-[11px] text-slate-400 dark:text-slate-500">
                Tridayaapp.com
            </footer>
        </div>
    </div>

    <script>
        // Idempoten: dipanggil saat load awal DAN setiap livewire:navigated
        // (navigasi tanpa refresh me-morph ulang konten; elemen yang
        // diganti morph kehilangan listener, jadi binding diulang di sini
        // dengan pengaman dataset.bound agar tidak ganda).
        // Safety-net opsi A: paksa tampilkan [data-reveal] bila observer tak pernah
        // fire / JS error parsial — cegah dashboard blank putih (opacity: 0).
        function forceReveal(){document.querySelectorAll('[data-reveal]:not(.in-view)').forEach(function(el){el.classList.add('in-view')});}
        // Reveal mandiri (di luar initChrome): satu-satunya penentu konten
        // tampil — kegagalan sidebar/parallax tak boleh bikin blank putih.
        function initReveal(){
            // Reveal saat scroll — hormati prefers-reduced-motion.
            // Elemen yang sudah di viewport langsung in-view sinkron (tanpa
            // tunggu callback observer) agar konten atas tidak kedip/pop-in.
            // Fallback timer memaksa tampil bila observer tak fire (JS error,
            // morph Livewire) — cegah blank putih.
            try{
            if(!window.matchMedia('(prefers-reduced-motion: reduce)').matches&&'IntersectionObserver' in window){
                if(!window.__revealIO){
                    window.__revealIO=new IntersectionObserver(function(entries){
                        entries.forEach(function(en){if(en.isIntersecting){en.target.classList.add('in-view');window.__revealIO.unobserve(en.target)}});
                    },{threshold:0.12});
                }
                var vh=window.innerHeight||0;
                document.querySelectorAll('[data-reveal]:not(.in-view)').forEach(function(el){
                    var r=el.getBoundingClientRect();
                    if(r.top<vh&&r.bottom>0){el.classList.add('in-view');}
                    else{window.__revealIO.observe(el);}
                });
            }else{
                document.querySelectorAll('[data-reveal]').forEach(function(el){el.classList.add('in-view')});
            }
            }catch(e){forceReveal();}
            if(window.__revealFallback)clearTimeout(window.__revealFallback);
            window.__revealFallback=setTimeout(forceReveal,1500);
        }
        // Hover pill meluncur ala template (SharedLayoutBg): satu pill
        // selebar nav mengikuti item yang di-hover. Tanpa :scope (rapuh di
        // sebagian browser) — keanggotaan dicek via nav.contains. Class
        // html.sb-pill/js dipasang ulang tiap panggilan (bukan sekali saja)
        // karena morph wire:navigate menimpa atribut <html> — nav di-persist
        // sehingga guard pillBound tetap '1' antar navigasi.
        function initHoverPill(){
            var nav=document.getElementById('sidebar-navigation');if(!nav)return;
            document.documentElement.classList.add('js','sb-pill');
            if(window.matchMedia('(prefers-reduced-motion: reduce)').matches)return;
            var pill=nav.querySelector(':scope > .sb-hover-pill');
            if(!pill){pill=document.createElement('span');pill.className='sb-hover-pill';pill.setAttribute('aria-hidden','true');nav.append(pill);}
            if(nav.dataset.pillBound)return;nav.dataset.pillBound='1';
            function place(el){
                var x=0,y=0,node=el;
                while(node&&node!==nav){x+=node.offsetLeft;y+=node.offsetTop;node=node.offsetParent;}
                pill.style.width=el.offsetWidth+'px';
                pill.style.height=el.offsetHeight+'px';
                pill.style.transform='translate('+x+'px,'+y+'px)';
                pill.style.opacity='1';
            }
            nav.addEventListener('mousemove',function(e){
                if(document.documentElement.classList.contains('sidebar-collapsed')&&window.innerWidth>=1024){pill.style.opacity='0';return;}
                var t=e.target&&e.target.closest?e.target.closest('a, .sb-head'):null;
                if(!t||!nav.contains(t)) {pill.style.opacity='0';return;}
                if(t.tagName==='A'){var hd=t.closest('.sb-head');if(hd&&nav.contains(hd))t=hd;}
                if(t.closest('.sb-group:not([data-open]) .sb-sub')){pill.style.opacity='0';return;}
                place(t);
            });
            nav.addEventListener('mouseleave',function(){pill.style.opacity='0';});
        }
        function initChrome(){
            var s=document.getElementById('sidebar'),o=document.getElementById('sidebarOverlay'),h=document.getElementById('hamburgerBtn');
            if(!s||!o)return;
            function isMobileBar(){return window.innerWidth<1024}
            var lastFocus=null;
            var FOCUSABLE='a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';
            // Scroll-lock ala template: body position:fixed (posisi scroll
            // terjaga) — overflow:hidden saja bikin halaman lompat di mobile.
            var sbScrollY=0,sbBodyPrev=null;
            function lockBar(){sbScrollY=window.scrollY||0;sbBodyPrev={l:document.body.style.left,o:document.body.style.overflow,p:document.body.style.position,r:document.body.style.right,t:document.body.style.top};document.body.style.position='fixed';document.body.style.top=(-sbScrollY)+'px';document.body.style.left='0';document.body.style.right='0';document.body.style.overflow='hidden';}
            function unlockBar(){if(!sbBodyPrev)return;document.body.style.position=sbBodyPrev.p;document.body.style.top=sbBodyPrev.t;document.body.style.left=sbBodyPrev.l;document.body.style.right=sbBodyPrev.r;document.body.style.overflow=sbBodyPrev.o;sbBodyPrev=null;window.scrollTo(0,sbScrollY);}
            // Sheet mobile ala template: scroll-lock + inert + fokus masuk,
            // tutup kembalikan fokus. Desktop (sidebar terlihat) tak disentuh.
            function open(){s.classList.add('open');o.classList.add('active');if(!isMobileBar())return;lastFocus=document.activeElement;s.setAttribute('role','dialog');s.setAttribute('aria-modal','true');s.removeAttribute('inert');lockBar();var f=s.querySelector(FOCUSABLE);if(f)f.focus({preventScroll:true});}
            function close(restore){s.classList.remove('open');o.classList.remove('active');if(!isMobileBar())return;s.removeAttribute('role');s.removeAttribute('aria-modal');s.setAttribute('inert','');unlockBar();if(restore!==false&&lastFocus&&document.contains(lastFocus))lastFocus.focus({preventScroll:true});}
            close(false);
            if(isMobileBar()){s.setAttribute('inert','');}
            if(h&&!h.dataset.bound){h.dataset.bound='1';h.addEventListener('click',function(){s.classList.contains('open')?close():open()});}
            if(!o.dataset.bound){o.dataset.bound='1';o.addEventListener('click',function(){close();});}

            var root=document.documentElement;
            var cb=document.getElementById('sidebarCollapseBtn');
            function syncTitles(){
                var collapsed=root.classList.contains('sidebar-collapsed');
                s.querySelectorAll('.sidebar nav a, .sidebar nav button, .sidebar-user, .sidebar-collapse').forEach(function(el){
                    var t=el.textContent.replace(/\s+/g,' ').trim();
                    if(collapsed&&t){el.setAttribute('title',t)}else{el.removeAttribute('title')}
                });
            }
            function setCollapsed(v){
                root.classList.toggle('sidebar-collapsed',v);
                try{localStorage.setItem('sidebar-collapsed',v?'1':'0')}catch(e){}
                var btn=document.getElementById('sidebarCollapseBtn');
                if(btn){btn.setAttribute('aria-expanded',String(!v));btn.setAttribute('aria-label',v?@json(__('Perluas sidebar')):@json(__('Perkecil sidebar')))}
            syncTitles();
            }
            syncTitles();
            if(cb&&!cb.dataset.bound){cb.dataset.bound='1';cb.addEventListener('click',function(){
                if(window.innerWidth<1024){close();return}
                setCollapsed(!root.classList.contains('sidebar-collapsed'));
            });}
            // Rail tipis di tepi sidebar (desktop): toggle yang sama.
            var rail=document.getElementById('sidebarRail');
            if(rail&&!rail.dataset.bound){rail.dataset.bound='1';rail.addEventListener('click',function(){
                if(window.innerWidth<1024)return;
                setCollapsed(!root.classList.contains('sidebar-collapsed'));
            });}
            // Klik header grup / toggle saat rail → lebarkan dulu, lalu buka
            // submenu-nya (di rail submenu tak punya ruang untuk ditampilkan).
            s.querySelectorAll('nav button[type="button"], nav a[aria-controls], .sb-group > div > a').forEach(function(b){
                if(b.dataset.bound)return;b.dataset.bound='1';
                b.addEventListener('click',function(){
                    if(window.innerWidth<1024||!root.classList.contains('sidebar-collapsed'))return;
                    setCollapsed(false);
                    var group=b.closest('.sb-group');if(!group)return;
                    var scope=null;try{scope=window.Alpine?Alpine.$data(group):null}catch(e){}
                    if(scope&&typeof scope.open==='boolean'&&!scope.open)scope.open=true;
                });
            });

            s.querySelectorAll('a,button[type="submit"]').forEach(function(e){if(e.dataset.bound)return;e.dataset.bound='1';e.addEventListener('click',function(){if(window.innerWidth<1024)close(false)})});
            initHoverPill();
            if(!window.__chromeGlobals){
                window.__chromeGlobals=true;
                // ⌘B / Ctrl+B = toggle rail (ala template animated sidebar).
                document.addEventListener('keydown',function(e){
                    if(e.key.toLowerCase()!=='b'||!(e.metaKey||e.ctrlKey))return;
                    e.preventDefault();
                    if(window.innerWidth<1024){var sb=document.getElementById('sidebar');
                        if(!sb)return;
                        sb.classList.contains('open')?close():open();
                        return;}
                    root.classList.toggle('sidebar-collapsed');
                    setCollapsed(root.classList.contains('sidebar-collapsed'));
                });
                window.addEventListener('resize',function(){if(window.innerWidth>=1024){var sb=document.getElementById('sidebar'),ov=document.getElementById('sidebarOverlay');if(sb){sb.classList.remove('open');sb.removeAttribute('role');sb.removeAttribute('aria-modal');sb.removeAttribute('inert');}if(ov)ov.classList.remove('active');unlockBar();}});
                document.addEventListener('keydown',function(e){if(e.key!=='Escape')return;var sb=document.getElementById('sidebar');if(sb&&sb.classList.contains('open'))close();});
                // Focus trap sheet mobile ala template (Tab muter di dalam sidebar).
                document.addEventListener('keydown',function(e){
                    if(e.key!=='Tab')return;
                    var sb=document.getElementById('sidebar');
                    if(!sb||!sb.classList.contains('open')||window.innerWidth>=1024)return;
                    var f=Array.prototype.slice.call(sb.querySelectorAll('a[href],button:not([disabled]),[tabindex]:not([tabindex="-1"])')).filter(function(el){return el.offsetParent!==null&&!el.closest('.sb-group:not([data-open]) .sb-sub')});
                    if(!f.length){e.preventDefault();return;}
                    var first=f[0],last=f[f.length-1];
                    if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}
                    else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}
                });
            }
            // Toggle tema header sudah dihapus dari UI: satu-satunya sumber
            // perubahan tema adalah Settings ($store.appearance, persist server).
            // Blok #darkToggle lama dihapus agar tak ada penulis localStorage-only
            // yang divergen dari preferensi server bila tombol ditambah lagi.
            var nav=document.getElementById('sidebar-navigation');
            if(nav){
                nav.scrollTop=+(sessionStorage.getItem('sidebar-scroll')||0);
                if(!nav.dataset.bound){nav.dataset.bound='1';nav.addEventListener('scroll',function(){sessionStorage.setItem('sidebar-scroll',nav.scrollTop)});}
            }
            // Reveal konten — delegasi ke initReveal() mandiri (lihat atas).
            initReveal();
            // Parallax mouse halus untuk [data-parallax-mouse] — nonaktif di
            // touch / reduced-motion; kembali ke posisi awal saat mouse pergi
            if(!window.matchMedia('(prefers-reduced-motion: reduce)').matches&&window.matchMedia('(hover: hover)').matches){
                var pxEls=Array.prototype.slice.call(document.querySelectorAll('[data-parallax-mouse]'));
                pxEls.forEach(function(el){
                    if(el.dataset.pxInit)return;el.dataset.pxInit='1';
                    var host=el.closest('section')||document.body;
                    var max=parseFloat(el.getAttribute('data-parallax-mouse'))||10;
                    var pxRaf=null;
                    host.addEventListener('mousemove',function(ev){
                        var r=host.getBoundingClientRect();
                        var dx=(ev.clientX-r.left)/r.width-0.5,dy=(ev.clientY-r.top)/r.height-0.5;
                        if(pxRaf)cancelAnimationFrame(pxRaf);
                        pxRaf=requestAnimationFrame(function(){
                            el.style.setProperty('--px',(dx*max).toFixed(1)+'px');
                            el.style.setProperty('--py',(dy*max).toFixed(1)+'px');
                        });
                    });
                    host.addEventListener('mouseleave',function(){
                        el.style.setProperty('--px','0px');
                        el.style.setProperty('--py','0px');
                    });
                });
            }
        }
        function initChromeSafe(){try{initChrome()}catch(e){try{forceReveal()}catch(_){}}}
        function initRevealSafe(){try{initReveal()}catch(e){try{forceReveal()}catch(_){}}}
        // Sidebar di-persist (Livewire persist): DOM-nya tidak ikut morph, jadi status aktif
        // (highlight + grup terbuka) harus disinkron manual tiap navigasi.
        // Hanya class/aria yang disentuh — node tidak diganti, animasi tak replay.
        // (AI Assistant sengaja full reload; server me-render statusnya segar.)
        function syncSidebarActive(){
            var nav=document.getElementById('sidebar-navigation');if(!nav)return;
            var active=(nav.dataset.navActive||'').split(' ').filter(Boolean);
            var inactive=(nav.dataset.navInactive||'').split(' ').filter(Boolean);
            if(!active.length&&!inactive.length)return;
            var here=window.location.pathname.replace(/\/+$/,'')||'/';
            function swap(el,on,extra){
                active.forEach(function(c){el.classList.toggle(c,on);});
                inactive.forEach(function(c){el.classList.toggle(c,!on);});
                (extra||'').split(' ').filter(Boolean).forEach(function(c){el.classList.toggle(c,on);});
                if(el.tagName==='A')el.setAttribute('aria-current',on?'page':'false');
            }
            var links=Array.prototype.slice.call(nav.querySelectorAll('a[href]'));
            var best=null,bestLen=-1;
            links.forEach(function(a){
                var p;try{p=new URL(a.href,window.location.origin).pathname.replace(/\/+$/,'')||'/';}catch(e){return;}
                if(p!==here&&here.indexOf(p+'/')!==0)return;
                // Header grup dan sub-item pertama URL-nya SAMA (landing grup = item pertama),
                // header selalu muncul duluan di DOM. Saat panjang sama, menangkan sub-item
                // agar highlight submenu pertama tidak hilang (header ikut nyala via grup di bawah).
                var isSub=!!a.closest('.sb-sub-item');
                var bestIsSub=!!(best&&best.closest('.sb-sub-item'));
                if(p.length>bestLen||(p.length===bestLen&&isSub&&!bestIsSub)){bestLen=p.length;best=a;}
            });
            links.forEach(function(a){
                var head=a.closest('.sb-head');
                if(head)swap(head,false,'sb-active');else swap(a,false);
            });
            if(!best)return;
            var head=best.closest('.sb-head');
            var item=best.closest('.sb-sub-item');
            if(item){
                swap(best,true);
                var group=item.closest('.sb-group');
                if(group){
                    group.setAttribute('data-open','');
                    try{var scope=window.Alpine?Alpine.$data(group):null;if(scope)scope.open=true;}catch(e){}
                    var h=group.querySelector('.sb-head');
                    if(h)swap(h,true,'sb-active');
                }
            }else if(head){swap(head,true,'sb-active');}
            else{swap(best,true);}
        }
        document.addEventListener('livewire:navigated',function(){try{syncSidebarActive();}catch(e){}});
        // Penanda aktif navbar (breadcrumb + badge halaman): mainkan ulang
        // animasi fade tiap navigasi. Hanya elemen penanda — sisa navbar diam.
        document.addEventListener('livewire:navigated',function(){
            try{
                document.querySelectorAll('.app-header nav[aria-label="Breadcrumb"],.app-header .app-header-badge').forEach(function(el){
                    el.classList.remove('nav-active-swap');
                    void el.offsetWidth;
                    el.classList.add('nav-active-swap');
                });
            }catch(e){}
        });
        document.addEventListener('DOMContentLoaded',initChromeSafe);
        document.addEventListener('livewire:navigated',initChromeSafe);
        document.addEventListener('DOMContentLoaded',initRevealSafe);
        document.addEventListener('livewire:navigated',initRevealSafe);
        // Script di ekor body = DOM sudah ter-parse: reveal sinkron langsung,
        // tak menunggu DOMContentLoaded/initChrome.
        initRevealSafe();
        // Last-resort: jalan saat parse, tak tergantung initChrome sukses.
        setTimeout(function(){try{forceReveal()}catch(e){}},2000);
        // Progress bar pindah halaman: navigasi di app ini full page reload,
        // jadi bar dinyalakan saat link internal diklik (bukan event
        // livewire:navigating yang tidak pernah fire). Failsafe + pageshow
        // (bfcache) memastikan bar tak pernah menggantung.
        (function(){
            var bar=document.getElementById('navigate-progress');if(!bar||bar.dataset.navBound)return;bar.dataset.navBound='1';
            var failsafe=null;
            function run(){bar.setAttribute('data-run','');if(failsafe)clearTimeout(failsafe);failsafe=setTimeout(stop,10000);}
            function stop(){bar.removeAttribute('data-run');if(failsafe){clearTimeout(failsafe);failsafe=null;}}
            document.addEventListener('click',function(e){
                var a=e.target&&e.target.closest?e.target.closest('a[href]'):null;
                if(!a||a.target||a.hasAttribute('download'))return;
                var href=a.getAttribute('href')||'';
                if(!href||href.charAt(0)==='#'||href.indexOf('mailto:')===0||href.indexOf('tel:')===0)return;
                if(a.origin&&a.origin!==window.location.origin)return;
                if(e.metaKey||e.ctrlKey||e.shiftKey||e.altKey||e.button!==0)return;
                run();
            },true);
            // Submit form = navigasi juga (kecuali AJAX internal).
            document.addEventListener('submit',function(e){
                var f=e.target;if(!f||f.dataset&&f.dataset.ajax)return;
                if(f.method&&f.method.toLowerCase()==='post'&&e.defaultPrevented)return;
                run();
            },true);
            window.addEventListener('pageshow',stop);
            stop();
        })();
        // Pertahankan posisi scroll halaman saat reload dari halaman yang sama
        // (mis. preset This Month/3/6 Month + submit filter dashboard marketing):
        // reload full page me-reset scroll ke atas. Pulihkan hanya bila datang
        // dari path yang sama; navigasi dari menu lain tetap mulai dari atas.
        // Restore instan (bukan smooth) — tujuannya tidak pindah, bukan animasi.
        (function(){
            if(window.__pageScrollBound)return;window.__pageScrollBound=true;
            try{
                if('scrollRestoration' in history)history.scrollRestoration='manual';
                var key='page-scroll:'+window.location.pathname;
                var ref=null;
                try{ref=document.referrer?new URL(document.referrer).pathname:null;}catch(e){}
                var y=+(sessionStorage.getItem(key)||0);
                if(y>0&&ref===window.location.pathname){window.scrollTo(0,y);}
                window.addEventListener('beforeunload',function(){
                    try{sessionStorage.setItem(key,String(window.scrollY||0));}catch(e){}
                });
            }catch(e){}
        })();
    </script>
    {{-- 3DY AI Chat V2 (pengganti floating lama; file lama dipertahankan tapi tidak di-mount) --}}
    @include('components.ai-chat-v2')
    @livewireScripts
</body>
</html>
