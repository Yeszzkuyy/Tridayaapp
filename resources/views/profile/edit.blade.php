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
                <section class="overflow-hidden rounded-2xl border border-white/40 bg-white/60 text-center shadow-lg backdrop-blur-xl dark:border-white/10 dark:bg-slate-800/50 dark:shadow-black/20">
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

                    <div class="px-6 pb-6 sm:px-8 sm:pb-8">
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
                                     class="relative h-28 w-28 rounded-full object-cover shadow-xl ring-4 sm:h-32 sm:w-32 {{ $isYeski ? 'ring-transparent' : 'ring-white dark:ring-slate-900' }}">
                                <div x-show="!preview" x-cloak aria-hidden="true"
                                     class="relative flex h-28 w-28 items-center justify-center rounded-full shadow-xl ring-4 sm:h-32 sm:w-32 {{ $isYeski ? 'bg-slate-900 ring-transparent' : 'bg-accent-100 ring-white dark:bg-accent-900/40 dark:ring-slate-900' }}">
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
                                @if($secure)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-5 py-2 text-sm font-bold uppercase tracking-wider text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">
                                        <x-icon name="check-circle" class="h-4 w-4" />
                                        {{ __('Account Secure') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-5 py-2 text-sm font-bold uppercase tracking-wider text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">
                                        {{ __('Password not changed yet') }}
                                    </span>
                                @endif
                            </div>
                            <p class="flex items-center justify-center gap-1.5 pt-1 text-base text-slate-500 dark:text-slate-400">
                                <x-icon name="mail" class="h-5 w-5" />
                                {{ $user->email }}
                            </p>
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
                            <a href="{{ route('settings.advanced') }}"
                               class="group relative inline-flex shrink-0 items-center gap-2 overflow-hidden rounded-xl bg-accent-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all duration-300 hover:scale-105 hover:bg-accent-500 hover:shadow-lg hover:shadow-accent-500/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 focus-visible:ring-offset-2 active:scale-95">
                                <span aria-hidden="true" class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full"></span>
                                <x-icon name="edit" class="h-4 w-4" />
                                {{ __('Edit Profile') }}
                            </a>
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