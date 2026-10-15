<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Forgot Password - {{ config('app.name', 'Tridaya App') }}</title>

    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo/Logo3dydark.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --accent: 37, 99, 235;
            --accent-strong: 29, 78, 216;
        }

        html, body {
            height: 100%;
            margin: 0;
            font-family: 'Figtree', sans-serif;
        }

        .bg-video-layer {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0;
            transition: opacity 1.5s ease-in-out;
            z-index: 1;
        }

        .bg-video-layer.is-active {
            opacity: 1;
        }

        .bg-fallback-gradient {
            position: absolute;
            inset: 0;
            z-index: 0;
            background: linear-gradient(160deg, #1a1a1a 0%, #0a0a0a 60%, #000000 100%);
        }

        .bg-overlay {
            position: absolute;
            inset: 0;
            z-index: 2;
            background: linear-gradient(to bottom, rgba(0,0,0,0.45), rgba(0,0,0,0.7));
        }

        .glass-card {
            background: rgba(15, 15, 15, 0.55);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(var(--accent), 0.18);
        }

        .input-glass {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.14);
            transition: border-color .2s ease;
        }

        .input-glass:focus-within {
            border-color: rgba(var(--accent), 0.7);
        }

        .accent-text {
            color: rgb(var(--accent));
        }

        .accent-icon {
            color: rgba(var(--accent), 0.85);
        }

        .accent-btn {
            background: linear-gradient(to right, rgb(var(--accent-strong)), rgb(var(--accent)));
        }

        .accent-btn:hover {
            filter: brightness(1.1);
        }
    </style>
</head>
<body class="text-white antialiased">

    <div
        x-data="{
            videos: {{ Illuminate\Support\Js::from([
                'videos/backgrounds/scene-1.mp4',
                'videos/backgrounds/scene-2.mp4',
                'videos/backgrounds/scene-3.mp4',
                'videos/backgrounds/scene-4.mp4',
                'videos/backgrounds/scene-5.mp4',
            ]) }},
            activeIndex: 0,
            reducedMotion: false,
            timer: null,
            init() {
                this.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                if (this.reducedMotion || this.videos.length <= 1) return;
                this.timer = setInterval(() => {
                    this.activeIndex = (this.activeIndex + 1) % this.videos.length;
                }, 8000);
            }
        }"
        class="relative min-h-screen w-full overflow-hidden flex items-center justify-center px-4 py-10"
    >
        <div class="bg-fallback-gradient"></div>

        <template x-if="!reducedMotion">
            <template x-for="(src, index) in videos" :key="index">
                <video
                    :src="'{{ asset('') }}' + src"
                    class="bg-video-layer"
                    :class="{ 'is-active': activeIndex === index }"
                    autoplay
                    muted
                    loop
                    playsinline
                    preload="auto"
                    x-on:error="$el.style.display = 'none'"
                ></video>
            </template>
        </template>

        <div class="bg-overlay"></div>

        <div class="relative z-10 w-full max-w-md">

            <div class="glass-card rounded-2xl shadow-2xl p-5 sm:p-8">

                <div class="flex flex-col items-center text-center mb-6">
                    <div x-data="{ logoFailed: false }" class="mb-3">
                        <img
                            x-show="!logoFailed"
                            x-on:error="logoFailed = true"
                            src="{{ asset('images/logo/Logo3dydark-256.webp') }}"
                            alt="Logo"
                            class="w-14 h-14 object-contain"
                        >
                        <div
                            x-show="logoFailed"
                            x-cloak
                            class="accent-btn w-14 h-14 rounded-xl flex items-center justify-center text-xl font-bold"
                        >W</div>
                    </div>
                    <h1 class="text-2xl font-bold">{{ config('app.name', 'Tridaya App') }}</h1>
                    <p class="text-sm text-white/60 mt-1">Internal Engineer Management System</p>
                </div>

                <div class="text-center mb-6">
                    <h2 class="text-lg font-semibold">Forgot Password</h2>
                    <p class="text-sm text-white/50 mt-1">Enter your email to receive a reset link</p>
                </div>

                <x-auth-session-status class="mb-4 text-center" :status="session('status')" />

                <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                    @csrf

                    <div>
                        <div class="input-glass rounded-lg flex items-center px-3 py-2.5">
                            <svg class="w-5 h-5 accent-icon mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="email"
                                placeholder="Email address"
                                class="bg-transparent border-0 focus:ring-0 w-full text-white placeholder-white/35 p-0"
                            >
                        </div>
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <button
                        type="submit"
                        class="accent-btn w-full text-white font-semibold py-3 rounded-lg flex items-center justify-center gap-2 transition"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Send Reset Link
                    </button>

                    <div class="text-center pt-2">
                        <a href="{{ route('login') }}" class="text-sm accent-text hover:opacity-80">
                            Back to Login
                        </a>
                    </div>
                </form>
            </div>

            <p class="text-center text-xs text-white/35 mt-6">
                &copy; {{ date('Y') }} {{ config('app.name', 'Tridaya App') }}. All rights reserved.
            </p>
        </div>
    </div>

</body>
</html>
