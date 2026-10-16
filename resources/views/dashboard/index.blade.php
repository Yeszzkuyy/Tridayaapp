<x-app-layout>
    {{-- Skeleton shimmer dashboard umum — hanya tampil sesaat setelah login.
         Konten asli tetap di-render server (SEO-safe); overlay ini sekadar veil. --}}
    <style>
        /* Veil hanya seluas area konten <main> (relative): sidebar + header tetap terlihat. */
        #dash-skeleton {
            position: absolute; inset: 0; z-index: 20;
            background-color: var(--bg);
            transition: opacity 0.3s ease;
        }
        #dash-skeleton.skel-done { opacity: 0; }
        .skel-block {
            position: relative; overflow: hidden;
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 1rem;
        }
        .skel-block::after {
            content: ""; position: absolute; inset: 0;
            background: linear-gradient(100deg, transparent 20%, rgba(148, 163, 184, 0.28) 50%, transparent 80%);
            background-size: 200% 100%;
            animation: skel-sweep 1.4s ease-in-out infinite;
        }
        .dark .skel-block::after {
            background: linear-gradient(100deg, transparent 20%, rgba(255, 255, 255, 0.09) 50%, transparent 80%);
            background-size: 200% 100%;
        }
        .skel-line { border-radius: 0.375rem; background: rgba(148, 163, 184, 0.35); }
        .dark .skel-line { background: rgba(255, 255, 255, 0.12); }
        @keyframes skel-sweep {
            from { background-position: 180% 0; }
            to { background-position: -80% 0; }
        }
        @media (prefers-reduced-motion: reduce) {
            .skel-block::after { animation: none; }
            #dash-skeleton { display: none; }
        }
    </style>
    <div id="dash-skeleton" hidden aria-hidden="true">
        {{-- Padding disamakan dengan <main> agar posisi 1:1 dengan konten asli (main jadi acuan absolute). --}}
        <div class="px-4 sm:px-6 lg:px-8 pb-8">
            <div class="mx-auto max-w-[1400px] space-y-6">
                {{-- Overview header --}}
                <div class="skel-block rounded-2xl px-5 py-6 sm:px-7 sm:py-7">
                    <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="skel-line h-3 w-40"></div>
                            <div class="skel-line mt-3 h-8 w-full max-w-md"></div>
                            <div class="skel-line mt-2 h-4 w-full max-w-lg"></div>
                        </div>
                        <div class="skel-line h-[68px] w-full shrink-0 lg:w-56"></div>
                    </div>
                </div>
                {{-- Summary cards --}}
                <div>
                    <div class="mb-3 flex items-end justify-between gap-2 px-1">
                        <div class="min-w-0">
                            <div class="skel-line h-5 w-36"></div>
                            <div class="skel-line mt-2 h-4 w-56 max-w-full"></div>
                        </div>
                        <div class="skel-line h-4 w-20 shrink-0"></div>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="skel-block p-5 sm:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="skel-line h-3 w-24"></div>
                                    <div class="skel-line mt-3 h-8 w-20"></div>
                                    <div class="skel-line mt-2 h-3 w-28"></div>
                                </div>
                                <div class="skel-line h-11 w-11 shrink-0"></div>
                            </div>
                        </div>
                        <div class="skel-block p-5 sm:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="skel-line h-3 w-24"></div>
                                    <div class="skel-line mt-3 h-8 w-20"></div>
                                    <div class="skel-line mt-2 h-3 w-28"></div>
                                </div>
                                <div class="skel-line h-11 w-11 shrink-0"></div>
                            </div>
                        </div>
                        <div class="skel-block p-5 sm:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="skel-line h-3 w-24"></div>
                                    <div class="skel-line mt-3 h-8 w-20"></div>
                                    <div class="skel-line mt-2 h-3 w-28"></div>
                                </div>
                                <div class="skel-line h-11 w-11 shrink-0"></div>
                            </div>
                        </div>
                        <div class="skel-block p-5 sm:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="skel-line h-3 w-24"></div>
                                    <div class="skel-line mt-3 h-8 w-20"></div>
                                    <div class="skel-line mt-2 h-3 w-28"></div>
                                </div>
                                <div class="skel-line h-11 w-11 shrink-0"></div>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Recent activities --}}
                <div class="skel-block p-5 sm:p-7">
                    <div class="flex items-end justify-between gap-3 border-b border-transparent pb-4">
                        <div>
                            <div class="skel-line h-5 w-40"></div>
                            <div class="skel-line mt-2 h-4 w-64 max-w-full"></div>
                        </div>
                        <div class="skel-line h-6 w-24 shrink-0"></div>
                    </div>
                    <div class="flex items-start gap-4 border-b border-transparent py-4">
                        <div class="skel-line h-10 w-10 shrink-0 !rounded-full"></div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div class="skel-line h-4 w-full max-w-sm"></div>
                                <div class="skel-line h-3 w-16 shrink-0"></div>
                            </div>
                            <div class="skel-line mt-2 h-3 w-48 max-w-full"></div>
                        </div>
                    </div>
                    <div class="flex items-start gap-4 border-b border-transparent py-4">
                        <div class="skel-line h-10 w-10 shrink-0 !rounded-full"></div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div class="skel-line h-4 w-full max-w-sm"></div>
                                <div class="skel-line h-3 w-16 shrink-0"></div>
                            </div>
                            <div class="skel-line mt-2 h-3 w-48 max-w-full"></div>
                        </div>
                    </div>
                    <div class="flex items-start gap-4 border-b border-transparent py-4">
                        <div class="skel-line h-10 w-10 shrink-0 !rounded-full"></div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div class="skel-line h-4 w-full max-w-sm"></div>
                                <div class="skel-line h-3 w-16 shrink-0"></div>
                            </div>
                            <div class="skel-line mt-2 h-3 w-48 max-w-full"></div>
                        </div>
                    </div>
                    <div class="flex items-start gap-4 py-4">
                        <div class="skel-line h-10 w-10 shrink-0 !rounded-full"></div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div class="skel-line h-4 w-full max-w-sm"></div>
                                <div class="skel-line h-3 w-16 shrink-0"></div>
                            </div>
                            <div class="skel-line mt-2 h-3 w-48 max-w-full"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        // ponytail: tampil hanya bila datang dari /login & belum pernah di tab ini;
        // jalan saat parse (sebelum paint) agar tanpa kedip
        (function () {
            var el = document.getElementById('dash-skeleton');
            if (!el) return;
            var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var fromLogin = /\/login/.test(document.referrer || '');
            var seen = false;
            try { seen = !!sessionStorage.getItem('dash-skel'); } catch (e) {}
            if (reduce || !fromLogin || seen) { el.remove(); return; }
            try { sessionStorage.setItem('dash-skel', '1'); } catch (e) {}
            el.hidden = false;
            setTimeout(function () {
                el.classList.add('skel-done');
                setTimeout(function () { el.remove(); }, 500);
            }, 3000);
        })();
    </script>
    @php
        $dashboardCards = [
            [
                'label' => __('Total Customers'),
                'value' => $customerCount ?? 0,
                'description' => __('Registered customers'),
                'icon' => 'users',
                'iconClass' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-300',
                'washClass' => 'bg-blue-500/5 dark:bg-blue-400/10',
            ],
            [
                'label' => __('Total Projects'),
                'value' => $totalProjects ?? 0,
                'description' => __('All projects'),
                'icon' => 'folder',
                'iconClass' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300',
                'washClass' => 'bg-indigo-500/5 dark:bg-indigo-400/10',
            ],
            [
                'label' => __('Active Projects'),
                'value' => $activeProjects ?? 0,
                'description' => __('Open and On Progress'),
                'icon' => 'activity',
                'iconClass' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300',
                'washClass' => 'bg-emerald-500/5 dark:bg-emerald-400/10',
            ],
            [
                'label' => __('Total Documents'),
                'value' => $documentCount ?? 0,
                'description' => __('Project documents'),
                'icon' => 'book',
                'iconClass' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300',
                'washClass' => 'bg-amber-500/5 dark:bg-amber-400/10',
            ],
        ];
    @endphp

    <div class="mx-auto max-w-[1400px] space-y-6">
        {{-- Overview header --}}
        <section class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white px-5 py-6 shadow-sm sm:px-7 sm:py-7 dark:border-[#334070] dark:bg-[#232b3e]" data-reveal>
            <div class="pointer-events-none absolute -right-16 -top-20 h-56 w-56 rounded-full bg-blue-500/5 dark:bg-blue-400/10" data-parallax-mouse="14"></div>
            <div class="pointer-events-none absolute bottom-0 right-24 h-1 w-28 rounded-full bg-accent-500/30" data-parallax-mouse="7"></div>

            <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-accent-600 dark:text-accent-300">
                            {{ \Carbon\Carbon::now()->locale(app()->getLocale())->translatedFormat('l, d F Y') }}
                        </p>
                        <span class="hidden h-1 w-1 rounded-full bg-slate-300 sm:block dark:bg-[#6e789e]"></span>
                        <span class="text-xs font-medium text-slate-400">{{ __('Operational summary') }}</span>
                    </div>
                    <h1 class="mt-3 max-w-3xl text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl dark:text-[#f2f6ff]">
                        {{ __('Welcome back,') }} {{ auth()->user()->name }}
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500 dark:text-[#a6b1d4]">
                        {{ __('Monitor customer, project, and documentation conditions of 3DY Group in one place.') }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-[#334070] dark:bg-[#2a3150]/60">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-accent-600 text-white shadow-sm shadow-accent-600/20">
                        <x-icon name="grid" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('General dashboard') }}</p>
                        <p class="mt-0.5 text-sm font-bold text-slate-700 dark:text-[#f2f6ff]">{{ __('3DY Group Overview') }}</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Summary cards --}}
        <section aria-labelledby="dashboard-summary-heading" data-reveal data-reveal-delay="1">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-2 px-1">
                <div>
                    <h2 id="dashboard-summary-heading" class="text-lg font-bold text-slate-900 dark:text-[#f2f6ff]">{{ __('Key summary') }}</h2>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-[#a6b1d4]">{{ __('Latest figures from available operational data.') }}</p>
                </div>
                <span class="text-xs font-semibold text-slate-400">{{ count($dashboardCards) }} {{ __('indicators') }}</span>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($dashboardCards as $card)
                    <article class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition duration-300 hover:border-accent-200 sm:p-6 dark:border-[#334070] dark:bg-[#232b3e] dark:hover:border-accent-500/40">
                        <div class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full {{ $card['washClass'] }} transition-transform duration-500 group-hover:scale-125"></div>
                        <div class="relative flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">{{ $card['label'] }}</p>
                                <p class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 tabular-nums dark:text-[#f2f6ff]"
                                   x-data="counter({{ $card['value'] }})" x-init="start()" x-text="display">{{ $card['value'] }}</p>
                                <p class="mt-1 text-xs font-medium text-slate-500 dark:text-[#a6b1d4]">{{ $card['description'] }}</p>
                            </div>
                            <span class="shrink-0 rounded-xl p-3 {{ $card['iconClass'] }}">
                                <x-icon name="{{ $card['icon'] }}" class="h-5 w-5" />
                            </span>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        {{-- Recent activities --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7 dark:border-[#334070] dark:bg-[#232b3e]" data-reveal data-reveal-delay="2" aria-labelledby="recent-activities-heading">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-100 pb-4 dark:border-[#334070]">
                <div>
                    <h2 id="recent-activities-heading" class="text-lg font-bold text-slate-900 dark:text-[#f2f6ff]">{{ __('Recent activity') }}</h2>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-[#a6b1d4]">{{ __('Latest changes recorded on projects.') }}</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500 dark:bg-[#334070] dark:text-[#a6b1d4]">
                    {{ $activities->count() }} {{ __('activity') }}
                </span>
            </div>

            @forelse($activities as $activity)
                <article class="group flex items-start gap-4 border-b border-slate-100 py-4 last:border-0 last:pb-0 dark:border-[#334070]">
                    <div class="shrink-0">
                        <x-user-avatar :user="$activity->user" size="w-10 h-10" text="text-sm" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                            <p class="min-w-0 text-sm leading-snug text-slate-700 dark:text-[#f2f6ff]">
                                <span class="font-bold text-slate-900 dark:text-[#f2f6ff]">{{ $activity->user?->name ?? __('System') }}</span>
                                <span class="text-slate-600 dark:text-[#a6b1d4]">{{ $activity->title ?? __('Activity') }}</span>
                            </p>
                            <time class="shrink-0 text-xs font-medium text-slate-400" datetime="{{ $activity->activity_date?->toIso8601String() }}">
                                {{ $activity->activity_date?->setTimezone('Asia/Jakarta')->diffForHumans() ?? __('Time not available') }}
                            </time>
                        </div>
                        <p class="mt-1 truncate text-xs text-slate-400 dark:text-[#6e789e]">
                            {{ $activity->project?->project_name ?? __('Project not available') }}
                        </p>
                    </div>
                </article>
            @empty
                <div class="rounded-xl bg-slate-50 px-5 py-12 text-center dark:bg-[#2a3150]/60">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-white text-slate-300 shadow-sm dark:bg-[#334070] dark:text-[#6e789e]">
                        <x-icon name="activity" class="h-6 w-6" />
                    </span>
                    <p class="mt-4 text-sm font-medium text-slate-500 dark:text-[#a6b1d4]">{{ __('No activity recorded yet.') }}</p>
                    <p class="mt-1 text-xs text-slate-400 dark:text-[#6e789e]">{{ __('Project activity will appear here.') }}</p>
                </div>
            @endforelse
        </section>
    </div>
</x-app-layout>
