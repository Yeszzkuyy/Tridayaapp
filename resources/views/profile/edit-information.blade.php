<x-app-layout>
    <div class="w-full space-y-6 tab-container">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-white">{{ __('Edit Profile') }}</h1>
                <p class="mt-1 text-slate-500 dark:text-slate-400">{{ __('Update your personal information') }}</p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('profile.edit') }}" title="{{ __('Back') }}" />
        </div>

        <x-profile-tabs />

        @php
            $roleLabel = $user->roles->first()?->name ?? __('No Role');
            $joinDate = $user->created_at?->translatedFormat('d M Y') ?? '—';
        @endphp

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-8">
            <header class="border-b border-slate-200 pb-4 dark:border-slate-700">
                <h2 class="text-xl font-bold text-slate-800 dark:text-white">{{ __('Personal Information') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Manage the information shown on your profile.') }}</p>
            </header>

            <form method="POST" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
                @csrf
                @method('patch')

                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <x-input-label for="name" :value="__('Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" maxlength="16" required autofocus autocomplete="name" />
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
                        <x-input-error class="mt-2" :messages="$errors->get('email')" />
                    </div>

                    <div>
                        <x-input-label for="phone" :value="__('Phone Number')" />
                        <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full" :value="old('phone', $user->phone)" maxlength="30" autocomplete="tel" placeholder="08xxxxxxxxxx" />
                        <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                    </div>

                    <div>
                        <x-input-label for="role" :value="__('Role')" />
                        <x-text-input id="role" type="text" class="mt-1 block w-full bg-slate-100 dark:bg-slate-700" :value="$roleLabel" readonly />
                    </div>

                    <div>
                        <x-input-label for="join_date" :value="__('Join Date')" />
                        <x-text-input id="join_date" type="text" class="mt-1 block w-full bg-slate-100 dark:bg-slate-700" :value="$joinDate" readonly />
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <button type="submit"
                            class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Save') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                    @if (session('status') === 'profile-updated')
                        <p class="text-sm font-medium text-green-600 dark:text-green-400">{{ __('Saved.') }}</p>
                    @endif
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
