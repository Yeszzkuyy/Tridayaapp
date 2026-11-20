@php
    $themePref = auth()->check() ? auth()->user()->preference('theme', 'system') : 'system';
    $accentPref = auth()->check() ? auth()->user()->preference('accent', 'ocean') : 'ocean';
    $initialDark = $themePref !== 'light';
    $pageContext = \App\Support\PageContext::forRequest(request());
    $instantSave = \App\Support\InstantSave::enabledFor(request());
    $formErrors = collect($errors->getBag('default')->getMessages())
        ->map(fn ($messages) => is_array($messages) ? ($messages[0] ?? null) : $messages)
        ->filter()
        ->all();
    $embedFlash = [
        'success' => session('success') ? (string) session('success') : null,
        'warning' => session('warning') ? (string) session('warning') : null,
        'info' => session('status') === 'google-linked'
            ? __('Your Google account has been successfully connected!')
            : null,
        // Error validasi dipetakan langsung ke isian + toast ringkasan
        // oleh resources/js/form-validation.js.
        'error' => session('error') ? (string) session('error') : null,
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-mode="{{ $themePref }}" data-theme="{{ $accentPref }}" data-embed="1" class="{{ $initialDark ? 'dark' : '' }}"
      data-instant-save-failed="{{ __('Failed to save. Please try again.') }}"
      @if($instantSave) data-instant-save="1" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="saving-label" content="{{ __('Saving...') }}">
    <meta name="validation-required" content="{{ __(':label is required.') }}">
    <meta name="validation-summary" content="{{ __('Please complete the required fields: :fields') }}">
    <meta name="validation-fix" content="{{ __('Please fix the highlighted fields: :fields') }}">
    <meta name="validation-more" content="{{ __('and :count more') }}">

    <title>{{ $pageContext['tabTitle'] }} — {{ config('app.name', 'Tridaya App') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800|outfit:400,500,600,700,800&display=swap" rel="stylesheet" />

    <script>
        window.__appearanceSaveFailed = @json(__('Failed to save theme preference. Please try again.'));
        window.__applyAppearance = function () {
            var root = document.documentElement;
            var mode = root.getAttribute('data-mode') || @json($themePref) || 'system';
            var accent = root.getAttribute('data-theme') || @json($accentPref) || 'ocean';
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            var dark = mode === 'dark' || (mode === 'system' && prefersDark);
            root.classList.toggle('dark', dark);
            root.setAttribute('data-mode', mode);
            root.setAttribute('data-theme', accent);
            window.__appearanceMode = mode;
            window.__appearanceAccent = accent;
        };
        window.__applyAppearance();
        try {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () { window.__applyAppearance(); });
        } catch (e) {}
        document.addEventListener('livewire:navigated', function () { window.__applyAppearance(); });
    </script>

    <script>
        window.__workspacePage = @json($pageContext);
        window.__workspaceFlash = @json($embedFlash);
        window.__formErrors = @json($formErrors);
        document.documentElement.classList.add('js', 'sidebar-settled');
        try { sessionStorage.removeItem('ws-embed-retry:' + location.pathname); } catch (e) {}
    </script>

    @livewireStyles

    {{-- Sengaja TANPA notification-center.jsx agar tiap tab tidak membuat
         koneksi Reverb/polling baru. Notifikasi & toast dipegang shell luar. --}}
    @vite(['resources/css/app.css','resources/js/app.js','resources/js/submit-loader.js'])

    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        [x-cloak]{display:none!important}
        :root{--bg:#f4f4f5}html.dark{--bg:#1c2235}
        body{background-color:var(--bg)}
        html.dark body{background-color:var(--bg)!important;color:var(--text-primary)}
        .dark .bg-white{background-color:var(--card-bg)!important}
        .dark .bg-slate-50{background-color:var(--card-bg)!important}
        .dark .bg-slate-100{background-color:var(--input-bg)!important}
        .dark .bg-slate-200{background-color:var(--card-border)!important}
        .dark .bg-slate-300{background-color:var(--card-border)!important}
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
        .dark th.text-slate-500,.dark th.text-slate-600{color:var(--text-secondary)!important}
        input:not([type=checkbox]):not([type=radio]):not([type=file]):not([type=color]):not([type=range]):not([type=hidden]),select,textarea{background-color:var(--input-bg)!important;border-color:var(--input-border)!important;color:var(--input-text)!important}
        input:not([type=checkbox]):not([type=radio]):not([type=file]):not([type=color]):not([type=range]):not([type=hidden]):hover,select:hover,textarea:hover{background-color:var(--input-bg-hover)!important}
        input:focus,select:focus,textarea:focus{border-color:var(--input-border-focus)!important}
        input::placeholder,textarea::placeholder{color:var(--text-muted)!important}
        .dark input:not([type=checkbox]):not([type=radio]):not([type=range]):not([type=color]){color-scheme:dark}
    </style>
</head>
<body class="font-sans antialiased min-h-screen flex flex-col">

    <div class="px-4 sm:px-6 lg:px-8 pt-5">
        @if($errors->any())
            <x-toast type="error" data-server-error-summary>
                <p class="font-semibold mb-1">{{ __('There were errors in the form:') }}</p>
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

    <script>
        function forceReveal(){document.querySelectorAll('[data-reveal]:not(.in-view)').forEach(function(el){el.classList.add('in-view')});}
        forceReveal();
        document.addEventListener('DOMContentLoaded',forceReveal);
        document.addEventListener('livewire:navigated',forceReveal);
    </script>

    <x-doc-lightbox />
    <x-confirm-dialog />
    <script type="module" src="{{ \Illuminate\Support\Facades\Vite::asset('resources/js/doc-thumbs.js') }}"></script>
    @stack('scripts')
    @livewireScripts
</body>
</html>
