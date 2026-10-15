<x-app-layout>
    @php
        $currentTheme = $user->preference('theme', 'system');
        $currentAccent = $user->preference('accent', 'ocean');
        $currentLocale = $user->preference('locale', 'en');
        $notifyEmail = (bool) $user->preference('notify_email', true);
        $notifySystem = (bool) $user->preference('notify_system', true);
        $notifyPush = (bool) $user->preference('notify_push', true);

        $themeOptions = [
            ['value' => 'light', 'label' => __('Terang'), 'icon' => 'sun'],
            ['value' => 'dark', 'label' => __('Gelap'), 'icon' => 'moon'],
            ['value' => 'system', 'label' => __('Sistem'), 'icon' => 'settings'],
        ];

        $accentOptions = [
            ['value' => 'ocean', 'label' => 'Ocean', 'color' => '#3b82f6'],
            ['value' => 'terracotta', 'label' => 'Terracotta', 'color' => '#b45309'],
            ['value' => 'purple', 'label' => 'Purple', 'color' => '#7c3aed'],
            ['value' => 'emerald', 'label' => 'Emerald', 'color' => '#059669'],
        ];
    @endphp

    <div class="w-full space-y-6 tab-container">
        {{-- Header --}}
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">{{ __('Setting') }}</h1>
                <p class="mt-1 text-slate-500">{{ __('Preferensi aplikasi Anda') }}</p>
            </div>
            <div class="flex items-center gap-3">
                <x-icon-button as="a" icon="back" href="{{ route('dashboard') }}" title="{{ __('Kembali') }}" />
            </div>
        </div>

        <x-profile-tabs />

        @if (session('status') === 'settings-updated')
            <div class="rounded-xl border border-green-300 bg-green-100 px-5 py-3 text-sm text-green-700 dark:border-green-700 dark:bg-green-900/30 dark:text-green-400">
                {{ __('Pengaturan berhasil disimpan.') }}
            </div>
        @endif

        <form id="settings-form" method="POST" action="{{ route('settings.update') }}" class="space-y-6">
            @csrf
            @method('patch')

            {{-- Tampilan --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-600 dark:bg-slate-800"
                     x-data="{
                        theme: '{{ $currentTheme }}',
                        accent: '{{ $currentAccent }}',
                        apply(t) {
                            this.theme = t;
                            $store.appearance.setMode(t);
                        },
                        applyAccent(a) {
                            this.accent = a;
                            $store.appearance.setAccent(a);
                        }
                     }">
                <header>
                    <h2 class="text-lg font-bold text-slate-800">{{ __('Tampilan') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Pilih tema dan aksen aplikasi saat Anda masuk.') }}</p>
                </header>

                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    @foreach($themeOptions as $option)
                        <label class="cursor-pointer">
                            <input type="radio" name="theme" value="{{ $option['value'] }}" class="peer sr-only"
                                   x-model="theme" @change="apply('{{ $option['value'] }}')"
                                   @checked($currentTheme === $option['value'])>
                            <span class="flex flex-col items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-5 text-sm font-semibold text-slate-600 transition peer-checked:border-accent-500 peer-checked:bg-accent-50 peer-checked:text-accent-700 peer-checked:ring-2 peer-checked:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-300 dark:peer-checked:border-accent-400 dark:peer-checked:bg-accent-900/30 dark:peer-checked:text-accent-300 dark:peer-checked:ring-accent-400">
                                <x-icon name="{{ $option['icon'] }}" class="h-5 w-5" />
                                {{ $option['label'] }}
                            </span>
                        </label>
                    @endforeach
                </div>
                <x-input-error class="mt-2" :messages="$errors->get('theme')" />

                <div class="mt-5">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('Aksen warna') }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ __('Warna yang dipakai tombol utama, link, sidebar aktif, dan fokus.') }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach($accentOptions as $option)
                            <label class="cursor-pointer">
                                <input type="radio" name="accent" value="{{ $option['value'] }}" class="peer sr-only"
                                       x-model="accent" @change="applyAccent('{{ $option['value'] }}')"
                                       @checked($currentAccent === $option['value'])>
                                <span class="flex flex-col items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm font-semibold text-slate-600 transition peer-checked:border-accent-500 peer-checked:bg-accent-50 peer-checked:text-accent-700 peer-checked:ring-2 peer-checked:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-300 dark:peer-checked:border-accent-400 dark:peer-checked:bg-accent-900/30 dark:peer-checked:text-accent-300 dark:peer-checked:ring-accent-400">
                                    <span class="inline-block h-6 w-6 rounded-full ring-2 ring-white" style="background-color: {{ $option['color'] }}"></span>
                                    {{ $option['label'] }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('accent')" />
                </div>
            </section>

            {{-- Notifikasi --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                <header>
                    <h2 class="text-lg font-bold text-slate-800">{{ __('Notifikasi') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Atur bagaimana Anda menerima pemberitahuan.') }}</p>
                </header>

                <div class="mt-5 divide-y divide-slate-100 dark:divide-slate-700">
                    <label class="flex cursor-pointer items-center justify-between gap-4 py-4 first:pt-0">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('Notifikasi Email') }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ __('Jika dimatikan, notifikasi email tidak dikirim (notifikasi sistem & push tetap aktif).') }}</span>
                        </span>
                        <span class="relative inline-flex shrink-0 items-center">
                            <input type="checkbox" name="notify_email" value="1" class="peer sr-only" @checked($notifyEmail)>
                            <span class="toggle-track h-6 w-11 rounded-full transition peer-focus-visible:ring-2 peer-focus-visible:ring-accent-500 peer-focus-visible:ring-offset-2"></span>
                            <span class="toggle-knob pointer-events-none absolute left-0.5 top-0.5 h-5 w-5 rounded-full shadow-sm transition peer-checked:translate-x-5"></span>
                        </span>
                    </label>

                    <label class="flex cursor-pointer items-center justify-between gap-4 py-4 last:pb-0">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('Notifikasi Sistem') }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ __('Tampilkan notifikasi di dalam aplikasi.') }}</span>
                        </span>
                        <span class="relative inline-flex shrink-0 items-center">
                            <input type="checkbox" name="notify_system" value="1" class="peer sr-only" @checked($notifySystem)>
                            <span class="toggle-track h-6 w-11 rounded-full transition peer-focus-visible:ring-2 peer-focus-visible:ring-accent-500 peer-focus-visible:ring-offset-2"></span>
                            <span class="toggle-knob pointer-events-none absolute left-0.5 top-0.5 h-5 w-5 rounded-full shadow-sm transition peer-checked:translate-x-5"></span>
                        </span>
                    </label>

                    <label class="flex cursor-pointer items-center justify-between gap-4 py-4 last:pb-0">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('Web Push') }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ __('Kirim notifikasi ke browser, tetap tiba walau tab ditutup.') }}</span>
                        </span>
                        <span class="relative inline-flex shrink-0 items-center">
                            <input type="checkbox" name="notify_push" value="1" class="peer sr-only" @checked($notifyPush)>
                            <span class="toggle-track h-6 w-11 rounded-full transition peer-focus-visible:ring-2 peer-focus-visible:ring-accent-500 peer-focus-visible:ring-offset-2"></span>
                            <span class="toggle-knob pointer-events-none absolute left-0.5 top-0.5 h-5 w-5 rounded-full shadow-sm transition peer-checked:translate-x-5"></span>
                        </span>
                    </label>
                </div>

                {{-- Status push browser ini --}}
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-600 dark:bg-slate-900/40"
                     x-data="{ pushState: 'checking', pushMsg: '' }"
                     x-init="(async () => { pushState = await window.WebPush.status() })()">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        <span x-cloak x-show="pushState === 'checking'">{{ __('Memeriksa status push browser ini…') }}</span>
                        <span x-cloak x-show="pushState === 'subscribed'">{{ __('Push aktif di browser ini.') }}</span>
                        <span x-cloak x-show="pushState === 'default'">{{ __('Browser ini belum mengizinkan notifikasi.') }}</span>
                        <span x-cloak x-show="pushState === 'granted'">{{ __('Izin diberikan, tapi browser ini belum terdaftar — tekan Aktifkan.') }}</span>
                        <span x-cloak x-show="pushState === 'blocked'">{{ __('Notifikasi diblokir di browser — izinkan lewat ikon gembok di address bar.') }}</span>
                        <span x-cloak x-show="pushState === 'unsupported'">{{ __('Browser ini tidak mendukung Web Push.') }}</span>
                        <span x-cloak x-show="pushState === 'unsubscribed'">{{ __('Push nonaktif di browser ini.') }}</span>
                        <span class="text-red-500" x-cloak x-show="pushMsg" x-text="pushMsg"></span>
                    </p>
                    <div class="flex gap-2">
                        <button type="button" x-cloak x-show="pushState === 'default' || pushState === 'granted' || pushState === 'unsubscribed'"
                                @click="pushMsg = ''; window.WebPush.enable().then(s => pushState = s).catch((e) => pushMsg = '{{ __('Gagal mengaktifkan push:') }} ' + (e && e.message ? e.message : e))"
                                class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">{{ __('Aktifkan di browser ini') }}</button>
                        <button type="button" x-cloak x-show="pushState === 'subscribed'"
                                @click="window.WebPush.disable().then(s => pushState = s)"
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-white dark:border-slate-600 dark:text-slate-200">{{ __('Nonaktifkan') }}</button>
                    </div>
                </div>
            </section>

            {{-- Bahasa --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                <header>
                    <h2 class="text-lg font-bold text-slate-800">{{ __('Bahasa') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Pilih bahasa tampilan aplikasi.') }}</p>
                </header>

                <div class="mt-5 max-w-xs">
                    <x-input-label for="locale" :value="__('Bahasa')" />
                    <select id="locale" name="locale"
                            class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-accent-500 focus:ring-accent-500">
                        <option value="id" @selected($currentLocale === 'id')>Indonesia</option>
                        <option value="en" @selected($currentLocale === 'en')>English</option>
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('locale')" />
                </div>
            </section>

            {{-- Simpan otomatis saat ada perubahan (tanpa tombol; noscript tetap ada tombol) --}}
            <noscript>
                <div class="flex items-center gap-4">
                    <button type="submit"
                            class="inline-flex items-center rounded-xl bg-accent-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-accent-700">
                        {{ __('Simpan Pengaturan') }}
                    </button>
                </div>
            </noscript>
        </form>

        <style>
            /* Toggle kontras mengikuti mode: ON = gelap di light, terang di dark.
               Class sendiri (bukan utility bg-*) agar lolos override !important dark-mode global. */
            .toggle-track{background-color:#cbd5e1}
            .peer:checked ~ .toggle-track{background-color:#1e293b}
            .toggle-knob{background-color:#fff}
            .dark .toggle-track{background-color:var(--card-border)}
            .dark .peer:checked ~ .toggle-track{background-color:#fff}
            .dark .peer:checked ~ .toggle-knob{background-color:#1e293b}
        </style>
        <script>
            // Autosave: tiap perubahan setting langsung PATCH tanpa reload.
            (function () {
                var form = document.getElementById('settings-form');
                if (!form) return;
                var timer = null;
                var localeInput = form.querySelector('[name="locale"]');
                var lastLocale = localeInput ? localeInput.value : null;
                form.addEventListener('change', function () {
                    clearTimeout(timer);
                    timer = setTimeout(function () {
                        var token = document.querySelector('meta[name="csrf-token"]')
                            ? document.querySelector('meta[name="csrf-token"]').content : null;
                        fetch(form.action, {
                            method: 'POST',
                            body: new FormData(form),
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
                        }).then(function (res) {
                            if (!res.ok) throw new Error();
                            return res.json();
                        }).then(function () {
                            var locale = localeInput ? localeInput.value : null;
                            if (locale && locale !== lastLocale) {
                                lastLocale = locale;
                                window.location.reload();
                            }
                        }).catch(function () {
                            if (window.toast) window.toast('{{ __('Gagal menyimpan pengaturan.') }}', false);
                        });
                    }, 400);
                });
            })();
        </script>

        {{-- Area lanjutan (digerbang password) — kartu link full-width --}}
        <a href="{{ route('settings.advanced') }}"
           class="group flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-accent-400 hover:shadow-lg dark:border-slate-600 dark:bg-slate-800">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-accent-50 text-accent-700 dark:bg-accent-500/10 dark:text-accent-400">
                <x-icon name="settings" class="h-5 w-5" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-bold text-slate-800 dark:text-white">{{ __('Pengaturan Lanjutan') }}</span>
                <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">{{ __('Ganti password dan hapus akun. Area ini dilindungi konfirmasi password.') }}</span>
            </span>
            <x-icon name="chevron-right" class="h-5 w-5 shrink-0 text-slate-400 transition group-hover:translate-x-1 group-hover:text-accent-600 dark:group-hover:text-accent-400" />
        </a>
    </div>
</x-app-layout>
