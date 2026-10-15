<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Verify Email - {{ config('app.name', 'Tridaya App') }}</title>

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

        .accent-btn-outline {
            background: transparent;
            border: 1px solid rgba(var(--accent), 0.5);
            color: rgb(var(--accent));
        }

        .accent-btn-outline:hover {
            background: rgba(var(--accent), 0.1);
            border-color: rgb(var(--accent));
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
        class="relative min-h-screen w-full overflow-hidden flex items-center justify-center px-4 py-6 sm:py-10"
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
                            class="w-12 h-12 sm:w-14 sm:h-14 object-contain"
                        >
                        <div
                            x-show="logoFailed"
                            x-cloak
                            class="accent-btn w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center text-lg sm:text-xl font-bold"
                        >W</div>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-bold">{{ config('app.name', 'Tridaya App') }}</h1>
                    <p class="text-xs sm:text-sm text-white/60 mt-1">Internal Engineer Management System</p>
                </div>

                <div class="text-center mb-6">
                    <h2 class="text-base sm:text-lg font-semibold">Verify Email</h2>
                    <p class="text-xs sm:text-sm text-white/50 mt-1">Thanks for signing up!</p>
                </div>

                <div class="text-xs sm:text-sm text-white/70 text-center leading-relaxed mb-6">
                    Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another.
                </div>

                @if (session('status') == 'verification-link-sent')
                    <div class="mb-4 text-xs sm:text-sm text-green-400 text-center font-medium">
                        A new verification link has been sent to the email address you provided during registration.
                    </div>
                @endif

                <div class="flex flex-col sm:flex-row gap-3">
                    <form method="POST" action="{{ route('verification.send') }}" class="flex-1">
                        @csrf
                        <button type="submit" class="accent-btn w-full text-white font-semibold py-2.5 sm:py-3 rounded-lg flex items-center justify-center gap-2 transition text-sm sm:text-base">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            Resend Verification
                        </button>
                    </form>

                    <form method="POST" action="{{ route('logout') }}" class="flex-1">
                        @csrf
                        <button type="submit" class="accent-btn-outline w-full font-semibold py-2.5 sm:py-3 rounded-lg flex items-center justify-center gap-2 transition text-sm sm:text-base">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Log Out
                        </button>
                    </form>
                </div>
            </div>

            <p class="text-center text-xs text-white/35 mt-6">
                &copy; {{ date('Y') }} {{ config('app.name', 'Tridaya App') }}. All rights reserved.
            </p>
        </div>
    </div>

</body>
</html>
