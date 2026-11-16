@php
    $themePref = auth()->check() ? auth()->user()->preference('theme', 'system') : 'system';
    $accentPref = auth()->check() ? auth()->user()->preference('accent', 'ocean') : 'ocean';
    // Sama seperti layout utama: mode system dirender gelap dulu (optimistik),
    // lalu script head mengoreksi sesuai skema OS sebelum paint pertama.
    $initialDark = $themePref !== 'light';
    $formErrors = collect($errors->getBag('default')->getMessages())
        ->map(fn ($messages) => is_array($messages) ? ($messages[0] ?? null) : $messages)
        ->filter()
        ->all();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-mode="{{ $themePref }}" data-theme="{{ $accentPref }}" class="{{ $initialDark ? 'dark' : '' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="validation-required" content="{{ __(':label is required.') }}">
        <meta name="validation-summary" content="{{ __('Please complete the required fields: :fields') }}">
        <meta name="validation-fix" content="{{ __('Please fix the highlighted fields: :fields') }}">
        <meta name="validation-more" content="{{ __('and :count more') }}">
        <script>window.__formErrors = @json($formErrors);</script>

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('favicon.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/logo/Logo3dydark.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800|outfit:400,500,600,700,800&display=swap" rel="stylesheet" />

        <script>
            // Koreksi mode "system" sebelum paint pertama (tanpa kilatan tema).
            (function () {
                var root = document.documentElement;
                var mode = root.getAttribute('data-mode') || 'system';
                var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                root.classList.toggle('dark', mode === 'dark' || (mode === 'system' && prefersDark));
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-[var(--bg)] text-[var(--text-primary)]">
        <div class="flex min-h-screen flex-col items-center px-4 pb-10 pt-6 sm:justify-center sm:pt-0">
            <a href="/" aria-label="{{ config('app.name') }}">
                <img src="{{ asset('images/logo/Logo3dydark.png') }}" alt="{{ config('app.name') }}" width="256" height="256"
                     fetchpriority="high" decoding="async"
                     class="h-16 w-auto aspect-square object-contain dark:hidden">
                <img src="{{ asset('images/logo/Logo3dylight.png') }}" alt="" aria-hidden="true" width="256" height="256"
                     fetchpriority="high" decoding="async"
                     class="hidden h-16 w-auto aspect-square object-contain dark:block">
            </a>

            <div class="mt-6 w-full overflow-hidden rounded-2xl border border-[var(--card-border)] bg-[var(--card-bg)] px-6 py-6 shadow-xl shadow-slate-900/5 dark:shadow-black/40 sm:max-w-md">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
