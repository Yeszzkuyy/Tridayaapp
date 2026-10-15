<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in - {{ config('app.name', 'Tridaya App') }}</title>

    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo/Logo3dydark.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">

    <script>
        // Halaman login selalu tampil gelap di semua mode.
        document.documentElement.classList.add('dark');
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }

        :root {
            box-sizing: border-box;
            color-scheme: light dark;
            --accent: #2563eb;
            --accent-soft: rgb(37 99 235 / .12);
            --base: #000;
            --shell: #ffffff;
            --shell-border: rgb(59 130 246 / .4);
            --field: #f8fafc;
            --field-border: #cbd5e1;
            --text: #0f172a;
            --muted: #5b6b84;
            --placeholder: #8a94a8;
            --danger: #dc2626;
            --warn: #b45309;
            --success: #15803d;
            --aurora-1: rgb(30 90 200 / .5);
            --aurora-2: rgb(14 130 200 / .35);
            --aurora-3: rgb(16 150 130 / .5);
            --aurora-4: rgb(70 90 220 / .22);
            --vig: transparent;
            --ring-dim: rgb(100 116 139 / .5);
        }

        .dark {
            --accent: #3b82f6;
            --accent-soft: rgb(59 130 246 / .2);
            --base: #000;
            --shell: #252b40;
            --shell-border: rgb(34 211 238 / .45);
            --field: #1e2438;
            --field-border: #3a4260;
            --text: #fff;
            --muted: #a5aec6;
            --placeholder: #626c90;
            --danger: #fb7185;
            --warn: #fbbf24;
            --success: #34d399;
            --aurora-1: rgb(30 90 200 / .5);
            --aurora-2: rgb(14 130 200 / .35);
            --aurora-3: rgb(16 150 130 / .5);
            --aurora-4: rgb(70 90 220 / .22);
            --vig: rgb(0 0 0 / .5);
            --ring-dim: rgb(148 163 184 / .35);
        }

        *, *::before, *::after { box-sizing: inherit; }
        html, body { height: 100%; margin: 0; }

        body {
            overflow: hidden;
            color: var(--text);
            background: var(--base);
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
            letter-spacing: -.02em;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        /* ===== Background only: parallax ===== */
        .bg { position: fixed; inset: 0; z-index: 0; overflow: hidden; }
        .layer { position: absolute; inset: -9%; transition: transform .6s cubic-bezier(.2, .7, .2, 1); will-change: transform; }
        .aurora {
            background:
                radial-gradient(38% 34% at 15% 18%, var(--aurora-1), transparent 70%),
                radial-gradient(34% 30% at 88% 12%, var(--aurora-2), transparent 70%),
                radial-gradient(46% 38% at 55% 104%, var(--aurora-3), transparent 70%);
        }
        .aurora::after {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(30% 24% at 72% 58%, var(--aurora-4), transparent 70%);
            animation: drift 20s ease-in-out infinite alternate;
        }
        @keyframes drift { to { transform: translate3d(-6%, 5%, 0) scale(1.15); } }
        #galaxy { position: absolute; inset: 0; }
        #galaxy canvas { display: block; width: 100% !important; height: 100% !important; }
        .vig { position: absolute; inset: 0; background: radial-gradient(transparent 55%, var(--vig)); pointer-events: none; }

        /* ===== Card ===== */
        .stage { position: relative; z-index: 1; display: grid; max-height: 100dvh; min-height: 100dvh; padding: 20px; overflow-y: auto; place-items: center; }
        .shell {
            position: relative;
            display: grid;
            width: min(1034px, 100%);
            padding: 28px;
            zoom: .85;
            border: 1px solid var(--shell-border);
            border-radius: 36px;
            grid-template-columns: 489px 1fr;
            gap: 0;
            color: #fff;
            background: var(--shell);
            box-shadow: 0 40px 90px -30px rgb(0 0 0 / .35), 0 0 60px -22px rgb(34 211 238 / .3);
            animation: rise .9s cubic-bezier(.2, .8, .2, 1) both;
        }
        .dark .shell { box-shadow: 0 40px 90px -30px rgb(0 0 0 / .7), 0 0 60px -22px rgb(34 211 238 / .35); }
        @keyframes rise { from { opacity: 0; transform: translate3d(0, 30px, 0); } }

        @property --a { syntax: "<angle>"; inherits: false; initial-value: 0deg; }
        .shell::before {
            content: "";
            position: absolute;
            inset: -1px;
            padding: 2px;
            border-radius: inherit;
            background: conic-gradient(from var(--a), transparent 0 58%, rgb(34 211 238 / .95) 78%, #3b82f6 90%, transparent 100%);
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
            animation: sweep 7s linear infinite;
        }
        @keyframes sweep { to { --a: 360deg; } }
        .sp { position: absolute; width: var(--s, 22px); height: var(--s, 22px); color: #7dd3fc; filter: drop-shadow(0 0 8px currentColor); pointer-events: none; animation: twinkle var(--t, 3.2s) ease-in-out infinite; animation-delay: var(--dl, 0s); }
        .sp svg { display: block; width: 100%; height: 100%; fill: currentColor; }
        @keyframes twinkle { 0%, 100% { opacity: .15; transform: scale(.5) rotate(0); } 50% { opacity: 1; transform: scale(1) rotate(45deg); } }
        .sp.a { top: 70px; left: -14px; }
        .sp.b { top: -12px; right: -12px; --s: 28px; --dl: .8s; color: #a5b4fc; }
        .sp.d { bottom: -14px; left: 60px; --s: 24px; --dl: 2.2s; color: #5eead4; }

        /* ===== Brand panel ===== */
        .brand {
            position: relative;
            display: flex;
            min-height: 560px;
            flex-direction: column;
            overflow: hidden;
            padding: 50px 49px 0;
            border: 1px solid rgb(255 255 255 / .18);
            border-radius: 24px;
            background: linear-gradient(180deg, #2562ea, #1d4ed7);
            box-shadow: inset 0 1px 0 rgb(255 255 255 / .12);
        }
        .orn, .orn2 { position: absolute; inset: 0; overflow: hidden; border-radius: inherit; pointer-events: none; }
        .orn { z-index: 0; }
        .orn2 { z-index: 2; }
        .lockup, .brand h2, .art { position: relative; z-index: 1; }
        .dotgrid {
            position: absolute;
            top: -6px;
            right: -6px;
            width: 190px;
            height: 150px;
            opacity: .5;
            background: radial-gradient(circle, rgb(255 255 255 / .65) 1.3px, transparent 1.8px) 0 0 / 16px 16px;
            -webkit-mask: radial-gradient(circle at 100% 0, #000 10%, transparent 72%);
            mask: radial-gradient(circle at 100% 0, #000 10%, transparent 72%);
        }
        .rings { position: absolute; bottom: -90px; left: -90px; width: 300px; height: 300px; }
        .rings i { position: absolute; inset: 0; border: 1.5px solid rgb(255 255 255 / .2); border-radius: 50%; animation: ping 6s ease-out infinite; }
        .rings i:nth-child(2) { animation-delay: 2s; }
        .rings i:nth-child(3) { animation-delay: 4s; }
        @keyframes ping { from { opacity: .9; transform: scale(.35); } to { opacity: 0; transform: scale(1.5); } }
        .pt { position: absolute; bottom: -12px; left: var(--x); width: var(--z, 6px); height: var(--z, 6px); border-radius: 50%; background: rgb(255 255 255 / .55); box-shadow: 0 0 10px rgb(255 255 255 / .6); animation: rise2 var(--u, 9s) linear infinite; animation-delay: var(--dl, 0s); }
        @keyframes rise2 { 0% { opacity: 0; transform: translate3d(0, 0, 0); } 10% { opacity: 1; } 90% { opacity: .8; } 100% { opacity: 0; transform: translate3d(var(--dx, 20px), -690px, 0); } }
        .plus { position: absolute; width: 14px; height: 14px; opacity: .55; animation: plusf 8s ease-in-out infinite alternate; }
        .plus::before, .plus::after { content: ""; position: absolute; border-radius: 2px; background: #fff; }
        .plus::before { top: 0; left: 6px; width: 2px; height: 14px; }
        .plus::after { top: 6px; left: 0; width: 14px; height: 2px; }
        @keyframes plusf { to { transform: translate3d(8px, -14px, 0) rotate(90deg); } }
        .shine { position: absolute; top: -20%; bottom: -20%; left: -50%; width: 34%; background: linear-gradient(100deg, transparent, rgb(255 255 255 / .16), transparent); transform: skewX(-18deg); animation: shn 7s ease-in-out 1.6s infinite; }
        @keyframes shn { 0% { left: -50%; } 35%, 100% { left: 140%; } }

        .lockup { display: inline-flex; align-items: center; }
        .lockup-img { display: block; width: auto; height: 52px; padding: 5px; border-radius: 10px; background: #fff; }
        .lockup i { width: 1px; height: 50px; margin: 0 14px; background: rgb(255 255 255 / .28); }
        .lockup b { color: #e8f0ff; font-size: 21px; font-weight: 600; letter-spacing: -.03em; }
        .brand h2 { margin: 32px 0 0; font-size: 37px; font-weight: 700; letter-spacing: -.04em; line-height: 1.1; text-wrap: balance; }
        .art { display: block; width: calc(100% + 98px); max-width: none; height: auto; margin: auto -49px 0; filter: drop-shadow(0 18px 32px rgb(2 6 23 / .35)); align-self: center; }

        /* ===== Form panel ===== */
        .panel { display: flex; max-width: 440px; flex-direction: column; justify-content: center; padding: 56px 22px 20px 56px; color: var(--text); }
        .panel .field, .panel .row, .panel .btn, .panel .status, .panel .foot {
            animation: in .7s cubic-bezier(.2, .8, .2, 1) both;
            animation-delay: calc(.35s + var(--i, 0) * .07s);
        }
        @keyframes in { from { opacity: 0; transform: translate3d(0, 14px, 0); } }
        .panel h1, .panel .sub { animation: none; }
        .panel h1 { margin: 0; font-size: 46px; font-weight: 700; letter-spacing: -.05em; line-height: 1.1; }
        .sub { margin: 12px 0 38px; color: var(--muted); font-size: 18px; letter-spacing: -.03em; }

        .status { margin: 0 0 1.1rem; padding: .8rem .9rem; border: 1px solid rgb(22 163 74 / .35); border-radius: .55rem; color: var(--success); background: rgb(22 163 74 / .1); font-size: .85rem; line-height: 1.5; }

        .field { margin-bottom: 26px; }
        label.t { display: block; margin-bottom: 12px; font-size: 15px; font-weight: 700; letter-spacing: -.03em; }
        .box { position: relative; border: 1px solid var(--field-border); border-radius: 10px; background: var(--field); transition: border-color .25s, box-shadow .25s; }
        .box:focus-within:not(.bad) {
            border-color: transparent;
            background:
                linear-gradient(var(--field), var(--field)) padding-box,
                conic-gradient(from var(--a), #fff 0deg, var(--accent) 22deg, rgb(59 130 246 / .08) 70deg, var(--ring-dim) 70deg, var(--ring-dim) 360deg) border-box;
            box-shadow: 0 0 0 4px var(--accent-soft), 0 0 26px -6px var(--accent);
            animation: borderflow 3s linear infinite;
        }
        @keyframes borderflow { to { --a: 360deg; } }
        .box.bad { border-color: var(--danger); animation: shake .4s; }
        @keyframes shake { 20% { transform: translateX(-6px); } 45% { transform: translateX(5px); } 70% { transform: translateX(-3px); } }
        input[type="email"], input[type="password"], input[type="text"] {
            width: 100%;
            height: 60px;
            padding: 0 56px 0 18px;
            border: 0;
            border-radius: 10px;
            outline: none;
            color: var(--text);
            background: transparent;
            font: inherit;
            font-size: 17px;
            letter-spacing: -.02em;
        }
        input::placeholder { color: var(--placeholder); }
        input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus {
            -webkit-text-fill-color: var(--text);
            caret-color: var(--text);
            transition: background-color 9999s ease-in-out 0s;
        }
        .eye { position: absolute; top: 10px; right: 10px; display: grid; width: 40px; height: 40px; border: 0; border-radius: 8px; color: var(--muted); background: transparent; cursor: pointer; place-items: center; transition: background .2s, color .2s; }
        .eye:hover { color: var(--text); background: var(--accent-soft); }
        .eye svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
        .msg { margin: .45rem 0 0; padding: 0; color: var(--danger); font-size: 13px; line-height: 1.45; list-style: none; }
        .caps { margin: .45rem 0 0; color: var(--warn); font-size: 13px; line-height: 1.45; }

        .row { display: flex; align-items: center; justify-content: space-between; margin: 0 0 24px; font-size: 15px; }
        .chk { display: flex; align-items: center; gap: 10px; color: var(--text); cursor: pointer; user-select: none; }
        .chk input { position: absolute; opacity: 0; }
        .cb { display: grid; width: 18px; height: 18px; border: 1.5px solid var(--field-border); border-radius: 5px; background: var(--field); place-items: center; transition: background .2s, border-color .2s, transform .2s; }
        .cb svg { width: 12px; height: 12px; fill: none; stroke: #fff; stroke-width: 3; stroke-dasharray: 20; stroke-dashoffset: 20; transition: stroke-dashoffset .25s .05s; }
        .chk input:checked + .cb { border-color: var(--accent); background: var(--accent); transform: scale(1.08); }
        .chk input:checked + .cb svg { stroke-dashoffset: 0; }
        .chk input:focus-visible + .cb { outline: 2px solid var(--accent); outline-offset: 2px; }
        a.login-link { position: relative; color: var(--accent); font-weight: 600; text-decoration: none; }
        a.login-link::after { content: ""; position: absolute; right: 0; bottom: -2px; left: 0; height: 1.5px; background: currentColor; transform: scaleX(0); transform-origin: left; transition: transform .3s; }
        a.login-link:hover::after, a.login-link:focus-visible::after { transform: scaleX(1); }
        a:focus-visible, button:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; border-radius: 6px; }

        .btn {
            position: relative;
            width: 100%;
            height: 62px;
            overflow: hidden;
            border: 0;
            border-radius: 8px;
            color: #fff;
            background: var(--accent);
            cursor: pointer;
            font: inherit;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -.03em;
            transition: transform .2s, box-shadow .3s, background .3s;
        }
        .btn::before { content: ""; position: absolute; top: 0; bottom: 0; left: -80%; width: 60%; background: linear-gradient(100deg, transparent, rgb(255 255 255 / .28), transparent); transform: skewX(-20deg); transition: left .7s; }
        .btn:hover:not(:disabled) { box-shadow: 0 14px 28px -12px var(--accent-soft); transform: translateY(-1px); }
        .btn:hover:not(:disabled)::before { left: 130%; }
        .btn:active:not(:disabled) { transform: scale(.99); }
        .btn:disabled { cursor: not-allowed; opacity: .85; }
        .btn .lab, .btn .spn { position: absolute; inset: 0; display: grid; transition: transform .35s cubic-bezier(.2, .8, .2, 1), opacity .25s; place-items: center; }
        .btn .spn { opacity: 0; transform: translateY(100%); }
        .btn .spn i { width: 22px; height: 22px; border: 3px solid rgb(255 255 255 / .35); border-top-color: #fff; border-radius: 50%; animation: sp .7s linear infinite; }
        @keyframes sp { to { transform: rotate(360deg); } }
        .btn.loading .lab { opacity: 0; transform: translateY(-100%); }
        .btn.loading .spn { opacity: 1; transform: none; }
        .foot { margin: 26px 0 0; color: var(--muted); font-size: 15px; text-align: center; }

        /* ===== Ornaments: background ===== */
        .glowline { position: absolute; bottom: -2px; left: 50%; width: min(760px, 80%); height: 2px; background: linear-gradient(90deg, transparent, rgb(45 212 191 / .9), rgb(59 130 246 / .9), transparent); filter: blur(1px); box-shadow: 0 0 50px 14px rgb(20 184 166 / .28); transform: translateX(-50%); }

        /* ===== Text reveal on load ===== */
        .w { display: inline-block; white-space: nowrap; }
        .c { display: inline-block; opacity: 0; transform-origin: 50% 100%; animation: chr .85s cubic-bezier(.2, .9, .2, 1) forwards, glint 1.1s ease forwards; animation-delay: var(--d), var(--g); }
        @keyframes chr { from { opacity: 0; transform: translateY(.55em) rotateX(-80deg) scale(.9); filter: blur(10px); } to { opacity: 1; transform: none; filter: blur(0); } }
        @keyframes glint { 0%, 100% { color: inherit; text-shadow: none; } 45% { color: #9fd6ff; text-shadow: 0 0 18px rgb(96 180 255 / .85), 0 0 40px rgb(59 130 246 / .6); } }
        .wd { display: inline-block; opacity: 0; animation: wrd .9s cubic-bezier(.2, .9, .2, 1) forwards; animation-delay: var(--d); }
        @keyframes wrd { from { opacity: 0; transform: translateX(-22px); letter-spacing: .08em; filter: blur(9px); } to { opacity: 1; transform: none; filter: blur(0); } }
        .tw { opacity: 0; animation: tw .01s forwards; animation-delay: var(--d); }
        @keyframes tw { to { opacity: 1; } }
        .sub.typing::after { content: ""; display: inline-block; width: 2px; height: 1em; margin-left: 3px; background: #60a5fa; vertical-align: -.12em; animation: blink .6s steps(1) infinite; }
        @keyframes blink { 50% { opacity: 0; } }

        @media (max-width: 1023px) {
            .panel { padding: 40px 24px 20px 36px; }
            .brand { padding: 36px 32px 0; }
            .art { width: calc(100% + 64px); margin: auto -32px 0; }
        }

        @media (max-width: 860px) {
            body { overflow: auto; }
            .stage { max-height: none; padding: 14px; place-items: start center; }
            .shell { padding: 14px; border-radius: 28px; grid-template-columns: 1fr; zoom: 1; }
            .brand { min-height: 0; padding: 22px 20px 0; }
            .lockup-img { height: 40px; }
            .lockup i { height: 38px; margin: 0 12px; }
            .lockup b { font-size: 18px; }
            .brand h2 { margin-top: 1.1rem; font-size: 1.5rem; }
            .art { width: min(100%, 20rem); height: auto; margin: 1rem auto 0; }
            .panel { max-width: none; padding: 26px 12px 12px; }
            .panel h1 { font-size: 2rem; }
            .sub { margin-bottom: 26px; font-size: 1rem; }
            .sp.a { display: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; animation-delay: 0s !important; }
        }
    </style>
</head>
<body>
    <div class="bg" aria-hidden="true">
        <div class="layer aurora" data-d="10"></div>
        <div class="layer"><div id="galaxy"></div></div>
        <div class="vig"></div><div class="glowline"></div>
    </div>

    <main class="stage">
        <section class="shell">
            <span class="sp a"><svg viewBox="0 0 24 24"><path d="M12 0c1 7 5 11 12 12-7 1-11 5-12 12-1-7-5-11-12-12 7-1 11-5 12-12z"/></svg></span><span class="sp b"><svg viewBox="0 0 24 24"><path d="M12 0c1 7 5 11 12 12-7 1-11 5-12 12-1-7-5-11-12-12 7-1 11-5 12-12z"/></svg></span><span class="sp d"><svg viewBox="0 0 24 24"><path d="M12 0c1 7 5 11 12 12-7 1-11 5-12 12-1-7-5-11-12-12 7-1 11-5 12-12z"/></svg></span>

            <aside class="brand" aria-labelledby="visual-title">
                <div class="orn" aria-hidden="true"><span class="dotgrid"></span><span class="rings"><i></i><i></i><i></i></span>
                    <span class="pt" style="--x:12%;--u:9s;--dx:18px"></span><span class="pt" style="--x:28%;--u:12s;--dl:-4s;--z:4px;--dx:-14px"></span><span class="pt" style="--x:47%;--u:10s;--dl:-7s;--z:8px;--dx:22px"></span><span class="pt" style="--x:66%;--u:13s;--dl:-2s;--z:5px;--dx:-20px"></span><span class="pt" style="--x:84%;--u:8s;--dl:-5s;--z:6px;--dx:12px"></span>
                    <span class="plus" style="left:58%;top:34%"></span><span class="plus" style="left:12%;top:52%;animation-delay:-3s"></span><span class="plus" style="left:88%;top:46%;animation-delay:-5s"></span></div>
                <div class="orn2" aria-hidden="true"><span class="shine"></span></div>
                <div class="lockup">
                    <picture>
                        <source srcset="{{ asset('images/logo/Logo3dylight-256.webp') }}" type="image/webp">
                        <img class="lockup-img" src="{{ asset('images/logo/Logo3dylight.png') }}" alt="3DY Group" width="2160" height="2160">
                    </picture>
                    <i aria-hidden="true"></i><b>3DY App</b>
                </div>
                <h2 id="visual-title">Connecting people, projects, and operations.</h2>
                <picture>
                    <source srcset="{{ asset('images/Login-Page/finaldesign-cut.webp') }}" type="image/webp">
                    <img
                        class="art"
                        src="{{ asset('images/Login-Page/finaldesign-cut.png') }}"
                        alt=""
                        aria-hidden="true"
                        loading="eager"
                        decoding="async"
                        fetchpriority="high"
                        width="1024"
                        height="1024"
                    >
                </picture>
            </aside>

            <div class="panel">
                <h1 id="login-title">Welcome back</h1>
                <p class="sub">Sign in to your 3DY account</p>

                <x-auth-session-status
                    class="status"
                    role="status"
                    aria-live="polite"
                    :status="session('status')"
                    style="--i:0"
                />

                <form
                    method="POST"
                    action="{{ route('login') }}"
                    x-data="{ isSubmitting: false }"
                    x-on:submit="isSubmitting = true"
                    novalidate
                >
                    @csrf

                    <div class="field" style="--i:1">
                        <label class="t" for="email">Email</label>
                        <div class="box{{ $errors->has('email') ? ' bad' : '' }}">
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                placeholder="name@company.com"
                                required
                                autofocus
                                autocomplete="username"
                                aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                                aria-describedby="email-error"
                            >
                        </div>
                        <div id="email-error" aria-live="polite">
                            <x-input-error :messages="$errors->get('email')" class="msg" />
                        </div>
                    </div>

                    <div x-data="{ showPassword: false, capsLock: false }" class="field" style="--i:2">
                        <label class="t" for="password">Password</label>
                        <div class="box{{ $errors->has('password') ? ' bad' : '' }}">
                            <input
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                name="password"
                                required
                                autocomplete="current-password"
                                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                aria-describedby="password-error caps-lock-warning"
                                x-on:keyup="capsLock = $event.getModifierState('CapsLock')"
                                x-on:keydown="capsLock = $event.getModifierState('CapsLock')"
                            >
                            <button
                                type="button"
                                x-on:click="showPassword = !showPassword"
                                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                :aria-pressed="showPassword"
                                aria-controls="password"
                                class="eye"
                            >
                                <svg x-show="!showPassword" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg x-show="showPassword" x-cloak viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 5.1A9.7 9.7 0 0112 5c6.4 0 10 7 10 7a17 17 0 01-3.2 4M6.5 6.7C3.7 8.5 2 12 2 12s3.6 7 10 7c1.7 0 3.2-.4 4.5-1M9.9 9.9a3 3 0 004.2 4.2"/></svg>
                            </button>
                        </div>
                        <p id="caps-lock-warning" x-show="capsLock" x-cloak role="alert" class="caps">Caps Lock is on</p>
                        <div id="password-error" aria-live="polite">
                            <x-input-error :messages="$errors->get('password')" class="msg" />
                        </div>
                    </div>

                    <div class="row" style="--i:3">
                        <label class="chk" for="remember">
                            <input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember'))>
                            <span class="cb" aria-hidden="true"><svg viewBox="0 0 12 12"><path d="M2 6.5l2.6 2.6L10 3.5"/></svg></span>Remember me
                        </label>
                        @if (Route::has('password.request'))
                            <a class="login-link" href="{{ route('password.request') }}">Forgot password?</a>
                        @endif
                    </div>

                    <button
                        type="submit"
                        class="btn"
                        style="--i:4"
                        x-bind:disabled="isSubmitting"
                        x-bind:aria-busy="isSubmitting"
                        x-bind:class="{ 'loading': isSubmitting }"
                    >
                        <span class="lab" x-show="!isSubmitting">Sign in</span>
                        <span class="spn" x-show="isSubmitting" x-cloak aria-hidden="true"><i></i></span>
                    </button>
                </form>

            </div>
        </section>
    </main>

    <script>
        (() => {
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const finePointer = window.matchMedia('(hover: hover)').matches;

            /* Parallax applies to the background layers only */
            const layers = [...document.querySelectorAll('.layer[data-d]')];
            const move = (nx, ny) => {
                if (reduce || !finePointer) return;
                layers.forEach((l) => {
                    const d = +l.dataset.d;
                    l.style.transform = `translate3d(${-nx * d}px,${-ny * d}px,0)`;
                });
            };
            window.addEventListener('pointermove', (e) => {
                move((e.clientX / window.innerWidth) * 2 - 1, (e.clientY / window.innerHeight) * 2 - 1);
            });

            /* Text reveal on every load */
            if (!reduce) {
                const chars = (el, start, step, glintAt) => {
                    if (!el) return 0;
                    const txt = el.textContent;
                    let i = 0;
                    el.setAttribute('aria-label', txt);
                    el.textContent = '';
                    txt.split(' ').forEach((word, wi) => {
                        if (wi) el.appendChild(document.createTextNode(' '));
                        const w = document.createElement('span');
                        w.className = 'w';
                        w.setAttribute('aria-hidden', 'true');
                        word.split('').forEach((ch) => {
                            const s = document.createElement('span');
                            s.className = 'c';
                            s.textContent = ch;
                            s.style.setProperty('--d', `${start + i * step}s`);
                            s.style.setProperty('--g', `${glintAt + i * step * 1.3}s`);
                            w.appendChild(s);
                            i++;
                        });
                        el.appendChild(w);
                    });
                    return i;
                };
                const words = (el, start, step) => {
                    if (!el) return;
                    const parts = el.textContent.split(' ');
                    el.setAttribute('aria-label', el.textContent);
                    el.textContent = '';
                    parts.forEach((p, idx) => {
                        if (idx) el.appendChild(document.createTextNode(' '));
                        const s = document.createElement('span');
                        s.className = 'wd';
                        s.setAttribute('aria-hidden', 'true');
                        s.textContent = p;
                        s.style.setProperty('--d', `${start + idx * step}s`);
                        el.appendChild(s);
                    });
                };
                chars(document.querySelector('.panel h1'), .3, .05, 1.2);
                chars(document.querySelector('.lockup b'), .25, .05, 1.0);
                words(document.querySelector('.brand h2'), .4, .13);
                const sub = document.querySelector('.sub');
                const st = sub.textContent;
                let n = 0;
                sub.setAttribute('aria-label', st);
                sub.textContent = '';
                st.split('').forEach((ch) => {
                    const s = document.createElement('span');
                    s.className = 'tw';
                    s.setAttribute('aria-hidden', 'true');
                    s.textContent = ch;
                    s.style.setProperty('--d', `${.95 + n * .03}s`);
                    sub.appendChild(s);
                    n++;
                });
                sub.classList.add('typing');
                setTimeout(() => sub.classList.remove('typing'), (.95 + n * .03 + 1.2) * 1000);
            }
        })();
    </script>
    <script>
        // Reset posisi chatbot ke kanan-bawah tiap tiba di halaman login
        // (habis logout / sesi berakhir / ganti user di browser yang sama).
        // Kunci per-user tak diketahui saat guest → sapu semua ber-prefix.
        // Riwayat percakapan (3dy.ai.v2.conv.*) sengaja dipertahankan.
        try {
            Object.keys(localStorage)
                .filter((k) => k.indexOf('3dy.ai.v2.pos.') === 0
                             || k.indexOf('3dy.ai.v2.chatoff.') === 0)
                .forEach((k) => localStorage.removeItem(k));
        } catch (e) {}
    </script>
</body>
</html>
