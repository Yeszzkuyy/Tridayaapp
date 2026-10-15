<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Confirm Password - {{ config('app.name', 'Tridaya App') }}</title>

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
            },
            showPassword: false
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
                    <h2 class="text-base sm:text-lg font-semibold">Confirm Password</h2>
                    <p class="text-xs sm:text-sm text-white/50 mt-1">This is a secure area. Please confirm your password before continuing.</p>
                </div>

                <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
                    @csrf

                    <div>
                        <div class="input-glass rounded-lg flex items-center px-3 py-2.5">
                            <svg class="w-5 h-5 accent-icon mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <input
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                name="password"
                                required
                                autocomplete="current-password"
                                placeholder="Password"
                                class="bg-transparent border-0 focus:ring-0 w-full text-white placeholder-white/35 p-0 text-sm sm:text-base"
                            >
                            <button type="button" x-on:click="showPassword = !showPassword" class="text-white/50 hover:text-white ml-2 shrink-0">
                                <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg x-show="showPassword" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                                </svg>
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <button
                        type="submit"
                        class="accent-btn w-full text-white font-semibold py-2.5 sm:py-3 rounded-lg flex items-center justify-center gap-2 transition text-sm sm:text-base"
                    >
                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        Confirm
                    </button>
                </form>
            </div>

            <p class="text-center text-xs text-white/35 mt-6">
                &copy; {{ date('Y') }} {{ config('app.name', 'Tridaya App') }}. All rights reserved.
            </p>
        </div>
    </div>

</body>
</html>
