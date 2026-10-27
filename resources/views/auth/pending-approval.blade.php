<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Menunggu Persetujuan') }} - {{ config('app.name', 'Tridaya App') }}</title>

    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo/Logo3dydark.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            color-scheme: dark;
            --base: #070a14;
            --shell: #151b2e;
            --shell-border: rgb(59 130 246 / .3);
            --field: #1e2438;
            --accent: #3b82f6;
            --text: #f8fafc;
            --muted: #94a3b8;
        }

        html, body {
            height: 100%;
            margin: 0;
            background: var(--base);
            color: var(--text);
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            letter-spacing: -.02em;
        }

        .bg {
            position: fixed; inset: 0; z-index: 0; overflow: hidden; background: #070a14;
        }
        .orb {
            position: absolute; border-radius: 50%; filter: blur(80px); opacity: .35; pointer-events: none;
        }
        .orb-1 {
            top: -10%; left: 15%; width: 480px; height: 480px;
            background: radial-gradient(circle, #2563eb, #1d4ed8);
        }
        .orb-2 {
            bottom: -15%; right: 10%; width: 520px; height: 520px;
            background: radial-gradient(circle, #0284c7, #0f766e);
        }
        .grid-pattern {
            position: absolute; inset: 0;
            background-image: radial-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px);
            background-size: 26px 26px;
            pointer-events: none;
        }

        .stage {
            position: relative; z-index: 1; display: grid; min-height: 100dvh; padding: 24px 16px; place-items: center;
        }
        .shell {
            position: relative;
            width: min(520px, 100%);
            padding: 36px 32px;
            border: 1px solid var(--shell-border);
            border-radius: 28px;
            background: var(--shell);
            box-shadow: 0 30px 80px -20px rgb(0 0 0 / .7), 0 0 50px -15px rgb(59 130 246 / .25);
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="bg" aria-hidden="true">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="grid-pattern"></div>
    </div>

    <main class="stage">
        <div class="shell">
            {{-- Logo Header --}}
            <div class="flex items-center justify-center gap-3 mb-6">
                <picture>
                    <source srcset="{{ asset('images/logo/Logo3dylight-256.webp') }}" type="image/webp">
                    <img src="{{ asset('images/logo/Logo3dylight.png') }}" alt="3DY Group" class="h-10 w-auto bg-white p-1 rounded-xl shadow-md">
                </picture>
                <span class="text-xl font-bold tracking-tight text-white">3DY App</span>
            </div>

            {{-- Avatar & Badge --}}
            <div class="relative mx-auto mb-5 inline-block">
                @if($user?->avatar_url)
                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="h-20 w-20 rounded-full border-2 border-accent-400/40 object-cover shadow-lg mx-auto">
                @else
                    <div class="flex h-20 w-20 items-center justify-center rounded-full bg-accent-600/20 text-2xl font-bold text-accent-400 border-2 border-accent-400/40 mx-auto">
                        {{ strtoupper(substr($user?->name ?? 'U', 0, 1)) }}
                    </div>
                @endif
                <span class="absolute bottom-0 right-0 flex h-6 w-6 items-center justify-center rounded-full bg-amber-500 text-white shadow-md ring-2 ring-slate-900" title="{{ __('Menunggu Role') }}">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>

            {{-- Greeting & Info --}}
            <h1 class="text-2xl font-bold text-white mb-1 tracking-tight">
                {{ __('Pendaftaran Akun Berhasil!') }}
            </h1>
            <p class="text-slate-300 font-medium text-sm mb-4">
                {{ $user?->name }} <span class="text-slate-500">({{ $user?->email }})</span>
            </p>

            {{-- Status Pill --}}
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs font-semibold mb-6">
                <span class="h-2 w-2 rounded-full bg-amber-400 animate-pulse"></span>
                {{ __('Status: Menunggu Pemberian Role Divisi') }}
            </div>

            {{-- Description Box --}}
            <div class="p-4 rounded-xl bg-slate-800/60 border border-slate-700/60 text-slate-300 text-sm leading-relaxed text-left mb-6 space-y-2">
                <p>
                    {{ __('Akun Google Anda telah berhasil diverifikasi dan terhubung ke sistem internal 3DY App.') }}
                </p>
                <p class="text-slate-400 text-xs">
                    {{ __('Demi keamanan data operasional, akun baru memerlukan pembagian role (Teknisi, Sales, Marketing, Admin, dsb.) oleh Super Admin / HR sebelum dapat mengakses menu dashboard.') }}
                </p>
            </div>

            {{-- Action Buttons --}}
            <div class="space-y-3">
                <a href="{{ route('dashboard') }}"
                   class="group relative flex w-full items-center justify-center gap-2 overflow-hidden rounded-xl bg-accent-600 px-5 py-3 text-sm font-semibold text-white shadow-lg transition-all duration-300 hover:bg-accent-500 hover:shadow-accent-500/25 active:scale-[0.99]">
                    <svg class="h-4 w-4 transition-transform group-hover:rotate-180 duration-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>{{ __('Cek Status / Masuk ke Dashboard') }}</span>
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-700/80 bg-slate-800/40 px-5 py-2.5 text-xs font-medium text-slate-400 transition hover:bg-slate-800 hover:text-white">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span>{{ __('Keluar (Sign Out)') }}</span>
                    </button>
                </form>
            </div>

            <p class="mt-6 text-[11px] text-slate-500">
                PT Tridaya Sinergi Berkelanjutan &bull; Enterprise Operations System
            </p>
        </div>
    </main>
</body>
</html>
