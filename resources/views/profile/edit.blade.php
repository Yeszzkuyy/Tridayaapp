<x-app-layout>
    <div class="w-full space-y-6 tab-container">
        {{-- Header --}}
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-white">{{ __('Profile') }}</h1>
                <p class="mt-1 text-slate-500 dark:text-slate-400">{{ __('Your account information') }}</p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('dashboard') }}" title="{{ __('Back') }}" />
        </div>

        <x-profile-tabs />

        @if(session('status') === 'google-linked')
            <x-toast type="success">
                <p class="font-semibold">{{ __('Your Google account has been successfully connected!') }}</p>
            </x-toast>
        @endif

        @php
            $roleLabel = $user->roles->first()?->name ?? __('No Role');
            $secure = $user->hasSecurePassword();
            $isYeski = $user->hasAnimatedAvatarBorder();
            $joinDate = $user->created_at?->translatedFormat('d M Y') ?? '—';
            $coverUrl = $user->cover ? asset('storage/' . $user->cover) : null;
        @endphp

        {{-- Backdrop aurora + stage glass: blob warna di belakang panel frosted --}}
        <div class="relative overflow-hidden">
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
                <div class="absolute -top-24 -right-20 h-80 w-80 rounded-full bg-accent-500/20 blur-3xl"></div>
                <div class="absolute top-1/4 -left-24 h-72 w-72 rounded-full bg-indigo-500/15 blur-3xl"></div>
                <div class="absolute -bottom-24 right-1/3 h-72 w-72 rounded-full bg-cyan-400/15 blur-3xl"></div>
            </div>

            <div class="relative grid grid-cols-1 gap-6 rounded-3xl border border-white/40 bg-white/40 p-6 shadow-2xl backdrop-blur-xl sm:p-10 lg:grid-cols-3 dark:border-white/10 dark:bg-slate-800/40 dark:shadow-black/30">
                {{-- KIRI: kartu profil glassmorphism + cover/banner --}}
                <section class="flex flex-col self-start overflow-hidden rounded-2xl border border-white/40 bg-white/60 text-center shadow-lg backdrop-blur-xl lg:min-h-[32rem] dark:border-white/10 dark:bg-slate-800/50 dark:shadow-black/20">
                    {{-- Banner/cover --}}
                    <div class="relative h-40 w-full bg-gradient-to-br from-accent-500 via-accent-700 to-slate-900 sm:h-48">
                        @if($coverUrl)
                            <img src="{{ $coverUrl }}" alt="{{ __('Cover photo') }}" class="absolute inset-0 h-full w-full object-cover">
                        @endif
                        <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-t from-black/25 to-transparent"></div>

                        {{-- Aksi cover --}}
                        <div class="absolute inset-x-0 top-0 flex items-center justify-end gap-2 p-3">
                            <form method="POST" action="{{ route('profile.cover.update') }}" enctype="multipart/form-data" x-data>
                                @csrf
                                <button type="button" @click="$refs.cover.click()"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-black/45 text-white shadow-sm backdrop-blur transition hover:bg-black/65 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                                        title="{{ __('Change cover photo') }}" aria-label="{{ __('Change cover photo') }}">
                                    <x-icon name="camera" class="h-4 w-4" />
                                </button>
                                <input type="file" x-ref="cover" name="cover" class="hidden"
                                       accept="image/png,image/jpeg,image/webp,image/gif" x-on:change="$el.form.submit()">
                            </form>
                            @if($coverUrl)
                                <form method="POST" action="{{ route('profile.cover.remove') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-black/45 text-white shadow-sm backdrop-blur transition hover:bg-black/65 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                                            title="{{ __('Remove cover photo') }}" aria-label="{{ __('Remove cover photo') }}">
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-1 flex-col justify-center px-6 pb-6 sm:px-8 sm:pb-10">
                        {{-- Foto profil — menumpuk di atas banner --}}
                        <form method="POST" action="{{ route('profile.avatar.update') }}" enctype="multipart/form-data"
                              x-data="{ preview: @js($user->avatar ? asset('storage/' . $user->avatar) : null) }"
                              class="-mt-16 sm:-mt-20">
                            @csrf
                            <button type="button" @click="$refs.avatar.click()"
                                    class="group relative mx-auto block cursor-pointer rounded-full transition duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
                                    aria-label="{{ __('Change profile photo') }}">
                                @if($isYeski)
                                    <span aria-hidden="true" title="{{ __('Founder') }}"
                                          class="avatar-flame absolute -inset-2 rounded-full motion-reduce:animate-none bg-[conic-gradient(from_0deg,rgb(var(--accent-500))_0deg,rgb(var(--accent-300))_55deg,rgb(var(--accent-300)/0.35)_85deg,transparent_115deg,rgb(var(--accent-500))_120deg,rgb(var(--accent-300))_175deg,rgb(var(--accent-300)/0.35)_205deg,transparent_235deg,rgb(var(--accent-500))_240deg,rgb(var(--accent-300))_295deg,rgb(var(--accent-300)/0.35)_325deg,transparent_355deg)] blur-[2px] shadow-[0_0_10px_2px_rgb(var(--accent-500)/0.45)]"></span>
                                    <span aria-hidden="true" title="{{ __('Founder') }}"
                                          class="absolute -inset-2 rounded-full animate-spin [animation-duration:3.5s] motion-reduce:animate-none bg-[conic-gradient(from_0deg,rgb(var(--accent-500))_0deg,rgb(var(--accent-300))_120deg,rgb(var(--accent-300)/0.35)_150deg,transparent_180deg,rgb(var(--accent-500))_185deg,rgb(var(--accent-300))_305deg,rgb(var(--accent-300)/0.35)_335deg,transparent_360deg)] shadow-[0_0_14px_3px_rgb(var(--accent-500)/0.55)]"></span>
                                @endif
                                <img x-show="preview" x-cloak :src="preview" alt="{{ __('Profile photo') }}"
                                     class="relative h-28 w-28 rounded-full object-cover shadow-xl ring-4 sm:h-36 sm:w-36 {{ $isYeski ? 'ring-transparent' : 'ring-white dark:ring-slate-900' }}">
                                <div x-show="!preview" x-cloak aria-hidden="true"
                                     class="relative flex h-28 w-28 items-center justify-center rounded-full shadow-xl ring-4 sm:h-36 sm:w-36 {{ $isYeski ? 'bg-slate-900 ring-transparent' : 'bg-accent-100 ring-white dark:bg-accent-900/40 dark:ring-slate-900' }}">
                                    <span class="text-4xl font-bold sm:text-5xl {{ $isYeski ? 'text-amber-300' : 'text-accent-600 dark:text-accent-300' }}">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                </div>
                                {{-- Overlay hover: gelap + ikon kamera --}}
                                <span class="absolute inset-0 flex items-center justify-center rounded-full bg-black/50 text-white opacity-0 transition-opacity duration-200 group-hover:opacity-100 group-focus-visible:opacity-100">
                                    <x-icon name="camera" class="h-6 w-6" />
                                </span>
                                {{-- Ikon edit kecil, selalu terlihat --}}
                                <span class="absolute bottom-0 right-0 flex h-8 w-8 items-center justify-center rounded-full bg-accent-600 text-white shadow-lg ring-2 ring-white dark:ring-slate-900">
                                    <x-icon name="camera" class="h-4 w-4" />
                                </span>
                            </button>

                            <input type="file" x-ref="avatar" name="avatar" class="hidden"
                                   accept="image/png,image/jpeg,image/webp,image/gif"
                                   x-on:change="preview = URL.createObjectURL($event.target.files[0]); $el.form.submit()">
                        </form>

                        {{-- Nama + badges + email --}}
                        <div class="mt-6 space-y-4">
                            <h2 class="text-2xl font-extrabold tracking-tight text-slate-800 sm:text-3xl dark:text-white">{{ $user->name }}</h2>
                            <div class="flex flex-wrap items-center justify-center gap-2.5">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-orange-100 px-5 py-2 text-sm font-bold uppercase tracking-wider text-orange-700 dark:bg-orange-500/15 dark:text-orange-400">
                                    <x-icon name="user" class="h-4 w-4" />
                                    {{ $roleLabel }}
                                </span>
                                @unless($secure)
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-5 py-2 text-sm font-bold uppercase tracking-wider text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">
                                        {{ __('Password not changed yet') }}
                                    </span>
                                @endunless
                            </div>
                            <p class="flex items-center justify-center gap-1.5 pt-1 text-base text-slate-500 dark:text-slate-400">
                                <x-icon name="mail" class="h-5 w-5" />
                                {{ $user->email }}
                            </p>

                            {{-- Status / Tombol Tautkan Akun Google --}}
                            <div class="pt-2">
                                @if($user->google_id)
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3.5 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24">
                                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                                        </svg>
                                        {{ __('Google account connected') }}
                                    </span>
                                @else
                                    <a href="{{ route('auth.google') }}"
                                       class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-accent-400 hover:text-accent-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-accent-500">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24">
                                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                                        </svg>
                                        {{ __('Connect Google account') }}
                                    </a>
                                @endif
                            </div>

                            <p class="text-xs text-slate-400 dark:text-slate-500">{{ __('Click the photo to change your profile picture') }}</p>
                        </div>

                        <x-input-error class="mt-3" :messages="$errors->get('cover')" />
                        <x-input-error class="mt-3" :messages="$errors->get('avatar')" />
                    </div>
                </section>

                {{-- KANAN (2/3): info pribadi + aktivitas terbaru --}}
                <div class="grid gap-6 lg:col-span-2">
                    {{-- Personal Information --}}
                    <section class="rounded-2xl border border-white/40 bg-white/50 p-5 shadow-lg backdrop-blur-lg sm:p-8 dark:border-white/10 dark:bg-slate-800/40">
                        <div class="flex items-center justify-between gap-4 border-b border-white/40 pb-4 dark:border-white/10">
                            <div>
                                <h2 class="text-xl font-bold text-slate-800 dark:text-white">{{ __('Personal Information') }}</h2>
                                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ __('Your account details') }}</p>
                            </div>
                            <x-icon-button as="a" icon="edit" href="{{ route('settings.advanced') }}" label="{{ __('Edit Profile') }}" title="{{ __('Edit Profile') }}" />
                        </div>

                        <dl class="mt-6 grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-2">
                            {{-- Field: tile kotak glass --}}
                            <div class="flex items-center gap-4 rounded-2xl border border-white/40 bg-white/40 p-4 backdrop-blur-sm sm:p-5 dark:border-white/10 dark:bg-white/[0.06]">
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/60 text-slate-600 shadow-sm dark:bg-white/10 dark:text-slate-200">
                                    <x-icon name="user" class="h-6 w-6" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Full Name') }}</span>
                                    <span class="mt-0.5 block truncate text-base font-semibold text-slate-800 dark:text-slate-100">{{ $user->name }}</span>
                                </span>
                            </div>

                            <div class="flex items-center gap-4 rounded-2xl border border-white/40 bg-white/40 p-4 backdrop-blur-sm sm:p-5 dark:border-white/10 dark:bg-white/[0.06]">
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/60 text-slate-600 shadow-sm dark:bg-white/10 dark:text-slate-200">
                                    <x-icon name="briefcase" class="h-6 w-6" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Role') }}</span>
                                    <span class="mt-0.5 block truncate text-base font-semibold text-slate-800 dark:text-slate-100">{{ $roleLabel }}</span>
                                </span>
                            </div>

                            {{-- Placeholder statis: belum ada kolom phone di tabel users. --}}
                            <div class="flex items-center gap-4 rounded-2xl border border-white/40 bg-white/40 p-4 backdrop-blur-sm sm:p-5 dark:border-white/10 dark:bg-white/[0.06]">
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/60 text-slate-600 shadow-sm dark:bg-white/10 dark:text-slate-200">
                                    <x-icon name="phone" class="h-6 w-6" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Phone Number') }}</span>
                                    <span class="mt-0.5 block truncate text-base font-semibold text-slate-800 dark:text-slate-100">—</span>
                                </span>
                            </div>

                            {{-- Placeholder statis: belum ada kolom department di tabel users. --}}
                            <div class="flex items-center gap-4 rounded-2xl border border-white/40 bg-white/40 p-4 backdrop-blur-sm sm:p-5 dark:border-white/10 dark:bg-white/[0.06]">
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/60 text-slate-600 shadow-sm dark:bg-white/10 dark:text-slate-200">
                                    <x-icon name="building" class="h-6 w-6" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Department') }}</span>
                                    <span class="mt-0.5 block truncate text-base font-semibold text-slate-800 dark:text-slate-100">—</span>
                                </span>
                            </div>

                            <div class="flex items-center gap-4 rounded-2xl border border-white/40 bg-white/40 p-4 backdrop-blur-sm sm:p-5 dark:border-white/10 dark:bg-white/[0.06]">
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/60 text-slate-600 shadow-sm dark:bg-white/10 dark:text-slate-200">
                                    <x-icon name="calendar" class="h-6 w-6" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Join Date') }}</span>
                                    <span class="mt-0.5 block truncate text-base font-semibold text-slate-800 dark:text-slate-100">{{ $joinDate }}</span>
                                </span>
                            </div>
                        </dl>
                    </section>

                    {{-- Recent Activity --}}
                    <section class="rounded-2xl border border-white/40 bg-white/50 p-5 shadow-lg backdrop-blur-lg sm:p-8 dark:border-white/10 dark:bg-slate-800/40">
                        <div class="border-b border-white/40 pb-4 dark:border-white/10">
                            <h2 class="text-xl font-bold text-slate-800 dark:text-white">{{ __('Recent Activity') }}</h2>
                            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ __('Latest actions on your account') }}</p>
                        </div>

                        <div class="mt-6 grid grid-cols-1 gap-8 lg:grid-cols-5">
                            {{-- Timeline aktivitas --}}
                            {{-- Placeholder statis: ganti dengan sumber aktivitas nyata bila tersedia. --}}
                            <div class="relative lg:col-span-3">
                                {{-- Garis vertikal timeline --}}
                                <span aria-hidden="true" class="absolute left-[18px] top-2 bottom-2 w-px bg-slate-200 dark:bg-slate-700"></span>
                                <ol class="relative space-y-7">
                                @foreach([
                                    ['icon' => 'user', 'color' => 'bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-400', 'label' => __('Logged in from Jakarta'), 'time' => __('2 hours ago')],
                                    ['icon' => 'settings', 'color' => 'bg-violet-100 text-violet-600 dark:bg-violet-900/40 dark:text-violet-400', 'label' => __('Updated system settings'), 'time' => __('Yesterday')],
                                    ['icon' => 'check-circle', 'color' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400', 'label' => __('Changed account password'), 'time' => __('Last week')],
                                    ['icon' => 'user', 'color' => 'bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400', 'label' => __('Updated profile information'), 'time' => __('Last week')],
                                ] as $event)
                                    <li class="relative flex items-start gap-4">
                                        <span class="relative z-10 flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $event['color'] }}">
                                            <x-icon name="{{ $event['icon'] }}" class="h-4 w-4" />
                                        </span>
                                        <span class="min-w-0 pt-0.5">
                                            <span class="block truncate text-base font-semibold text-slate-800 dark:text-slate-100">{{ $event['label'] }}</span>
                                            <span class="mt-0.5 block text-sm text-slate-500 dark:text-slate-400">{{ $event['time'] }}</span>
                                        </span>
                                    </li>
                                @endforeach
                                </ol>
                            </div>

                            {{-- Statistik kecil --}}
                            <div class="grid grid-cols-2 content-start gap-4 lg:col-span-2 lg:grid-cols-1 xl:grid-cols-2">
                                {{-- Placeholder statis: angka contoh, ganti dengan sumber nyata bila tersedia. --}}
                                <div class="rounded-2xl border border-white/40 bg-white/40 p-6 backdrop-blur-sm dark:border-white/10 dark:bg-white/[0.06]">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-400">
                                        <x-icon name="briefcase" class="h-5 w-5" />
                                    </span>
                                    <p class="mt-4 text-3xl font-extrabold tabular-nums text-slate-800 dark:text-white">14</p>
                                    <p class="mt-0.5 text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Total Task') }}</p>
                                </div>
                                <div class="rounded-2xl border border-white/40 bg-white/40 p-6 backdrop-blur-sm dark:border-white/10 dark:bg-white/[0.06]">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400">
                                        <x-icon name="activity" class="h-5 w-5" />
                                    </span>
                                    <p class="mt-4 text-3xl font-extrabold tabular-nums text-slate-800 dark:text-white">2</p>
                                    <p class="mt-0.5 text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Active Session') }}</p>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>