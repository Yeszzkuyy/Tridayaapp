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

            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
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

                    <div class="sm:col-span-2">
                        <x-input-label for="signature" :value="__('Digital Signature (PNG, transparent background)')" />
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Used automatically in the signature block of the official quotation PDF.') }}
                        </p>
                        @if($user->signature_path)
                            <div class="mt-3 flex flex-wrap items-center gap-4 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/40">
                                <img src="{{ asset('storage/'.$user->signature_path) }}" alt="{{ __('Digital Signature') }}" class="h-20 w-auto object-contain">
                                <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                    <input type="checkbox" name="remove_signature" value="1" class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                                    {{ __('Remove current signature') }}
                                </label>
                            </div>
                        @endif
                        <input id="signature" name="signature" type="file" accept="image/png,image/webp"
                               class="mt-3 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-accent-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-accent-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:file:bg-slate-700 dark:file:text-slate-100">
                        <x-input-error class="mt-2" :messages="$errors->get('signature')" />
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <button type="submit"
                            class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Save') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>                </div>
            </form>
        </section>
    </div>
</x-app-layout>
