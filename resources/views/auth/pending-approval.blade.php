<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Pending Approval') }} - {{ config('app.name', 'Tridaya App') }}</title>

    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo/Logo3dydark.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            color-scheme: dark;
            --base: #070a14;
            --shell: #111625;
            --shell-border: rgb(59 130 246 / .25);
            --accent: #2563eb;
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
            position: absolute; border-radius: 50%; filter: blur(90px); opacity: .32; pointer-events: none;
        }
        .orb-1 {
            top: -12%; left: 10%; width: 500px; height: 500px;
            background: radial-gradient(circle, #2563eb, #1d4ed8);
        }
        .orb-2 {
            bottom: -15%; right: 8%; width: 520px; height: 520px;
            background: radial-gradient(circle, #0284c7, #0f766e);
        }
        .grid-pattern {
            position: absolute; inset: 0;
            background-image: radial-gradient(rgba(255, 255, 255, 0.07) 1px, transparent 1px);
            background-size: 26px 26px;
            pointer-events: none;
        }

        .stage {
            position: relative; z-index: 1; display: grid; min-height: 100dvh; padding: 24px 16px; place-items: center;
        }
        .shell {
            position: relative;
            width: min(440px, 100%);
            padding: 38px 32px 34px;
            border: 1px solid var(--shell-border);
            border-radius: 28px;
            background: rgba(17, 22, 37, 0.88);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 35px 80px -25px rgb(0 0 0 / .8), 0 0 45px -15px rgb(37 99 235 / .2);
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
            {{-- Brand Logo --}}
            <div class="flex items-center justify-center gap-2.5 mb-7">
                <picture>
                    <source srcset="{{ asset('images/logo/Logo3dylight-256.webp') }}" type="image/webp">
                    <img src="{{ asset('images/logo/Logo3dylight.png') }}" alt="3DY Group" class="h-9 w-auto bg-white p-1 rounded-xl shadow-sm">
                </picture>
                <span class="text-lg font-bold tracking-tight text-white">3DY App</span>
            </div>

            {{-- Avatar & Identity --}}
            <div class="relative mx-auto mb-4 inline-block">
                @if($user?->avatar_url)
                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="h-20 w-20 rounded-full border-2 border-slate-700 object-cover shadow-md mx-auto">
                @else
                    <div class="flex h-20 w-20 items-center justify-center rounded-full bg-slate-800 text-2xl font-bold text-slate-200 border-2 border-slate-700 mx-auto">
                        {{ strtoupper(substr($user?->name ?? 'U', 0, 1)) }}
                    </div>
                @endif
                <span class="absolute -bottom-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full bg-amber-500 text-slate-950 shadow-md ring-2 ring-slate-900" title="{{ __('Pending Permissions') }}">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />
                    </svg>
                </span>
            </div>

            <h1 class="text-xl font-bold text-white mb-1 tracking-tight">
                {{ $user?->name ?? __('New User') }}
            </h1>
            <p class="text-xs text-slate-400 font-normal mb-5">
                {{ $user?->email }}
            </p>

            {{-- Status Badge --}}
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/25 text-amber-300 text-xs font-semibold mb-5">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                <span>{{ __('Waiting for Admin Approval') }}</span>
            </div>

            {{-- To-the-point explanation (English) --}}
            <p class="text-sm text-slate-300 leading-relaxed max-w-sm mx-auto mb-7 font-normal">
                {{ __('Your account is connected. To start accessing the system, your account requires permissions and is currently being processed by an administrator.') }}
            </p>

            {{-- Action Buttons --}}
            <div class="space-y-2.5">
                <a href="{{ route('dashboard') }}"
                   class="group relative flex w-full items-center justify-center gap-2 overflow-hidden rounded-xl bg-accent-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md transition-all duration-200 hover:bg-accent-500 hover:shadow-accent-500/25 active:scale-[0.99]">
                    <svg class="h-4 w-4 transition-transform group-hover:rotate-180 duration-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>{{ __('Check Access Status') }}</span>
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-700/60 bg-transparent px-5 py-2.5 text-xs font-medium text-slate-400 transition hover:bg-slate-800/60 hover:text-slate-200">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span>{{ __('Sign Out') }}</span>
                    </button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
