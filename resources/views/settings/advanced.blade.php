<x-app-layout>
    <div class="w-full space-y-6 tab-container">
        {{-- Header --}}
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">{{ __('Pengaturan Lanjutan') }}</h1>
                <p class="mt-1 text-slate-500">{{ __('Keamanan akun Anda') }}</p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('settings.edit') }}" title="{{ __('Kembali') }}" />
        </div>

        <x-profile-tabs />

        @if(!($unlocked ?? false))
            {{-- Terkunci: konfirmasi password inline, tanpa pindah halaman --}}
            <div class="mx-auto max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm sm:p-8 dark:border-slate-600 dark:bg-slate-800">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-accent-50 text-accent-700 dark:bg-accent-500/10 dark:text-accent-400">
                    <x-icon name="settings" class="h-6 w-6" />
                </span>
                <h2 class="mt-4 text-lg font-bold text-slate-800 dark:text-white">{{ __('Konfirmasi Password') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Masukkan password Anda untuk membuka area ini.') }}</p>

                <form method="POST" action="{{ route('settings.advanced.confirm') }}" class="mt-6 space-y-4 text-left">
                    @csrf
                    <div>
                        <x-input-label for="advanced_password" :value="__('Password')" />
                        <x-text-input id="advanced_password" name="password" type="password" class="mt-1 block w-full" autocomplete="current-password" autofocus />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>
                    <button type="submit"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-accent-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-accent-500">
                        {{ __('Buka') }}
                    </button>
                </form>
            </div>
        @else
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                @include('settings.partials.update-account-form')
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                @include('profile.partials.update-password-form')
            </div>

            <div class="rounded-2xl border border-red-200 bg-white p-6 shadow-sm dark:border-red-900/50 dark:bg-slate-800">
                @include('profile.partials.delete-user-form')
            </div>
        @endif
    </div>
</x-app-layout>
