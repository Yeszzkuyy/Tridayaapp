<x-app-layout>
    @php
        $user = auth()->user();
        $canViewProjects = $user && $user->canAny([
            'view-technician', 'manage-technician',
            'view-sales', 'manage-sales',
            'view-admin', 'manage-admin',
        ]);
        $projectsIndexUrl = $canViewProjects ? route('projects.index') : route('customers.index');

        $dateBlocks = [
            'violet' => [
                'box' => 'bg-violet-50 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
                'bar' => 'bg-violet-500',
                'pill' => 'bg-violet-50 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
            ],
            'cyan' => [
                'box' => 'bg-cyan-50 text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-300',
                'bar' => 'bg-cyan-500',
                'pill' => 'bg-cyan-50 text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-300',
            ],
            'emerald' => [
                'box' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                'bar' => 'bg-emerald-500',
                'pill' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
            ],
            'blue' => [
                'box' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
                'bar' => 'bg-indigo-500',
                'pill' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
            ],
            'indigo' => [
                'box' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
                'bar' => 'bg-indigo-500',
                'pill' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
            ],
            'rose' => [
                'box' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
                'bar' => 'bg-rose-500',
                'pill' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
            ],
            'amber' => [
                'box' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
                'bar' => 'bg-amber-500',
                'pill' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
            ],
        ];

        $allStatuses = collect($projectStatusBreakdown ?? []);
        $nonZeroStatuses = $allStatuses->filter(fn ($s) => ($s['count'] ?? 0) > 0)->values();
        $legendStatuses = $nonZeroStatuses->isNotEmpty()
            ? $nonZeroStatuses->take(5)
            : $allStatuses->take(5);

        $donutChartData = $nonZeroStatuses->map(fn ($s) => [
            'label' => __($s['name']),
            'value' => (int) $s['count'],
            'color' => $s['hex_color'] ?? '#6366f1',
            'key' => $s['name'],
        ])->values();
    @endphp

    <div class="w-full space-y-6">
        {{-- 1. Open Header Style (Tanpa Kotak Pembungkus) --}}
        <div class="flex flex-col gap-3 pt-1 sm:flex-row sm:items-center sm:justify-between" data-reveal>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-slate-100">
                {{ __('Welcome back,') }} {{ $user?->name }} <span aria-hidden="true">👋</span>
            </h1>

            <div class="inline-flex w-fit items-center gap-2 rounded-full border border-slate-200/80 bg-white px-4 py-2 text-xs font-semibold text-slate-600 shadow-sm dark:border-slate-800/70 dark:bg-slate-800/90 dark:text-slate-300">
                <x-icon name="calendar" class="h-3.5 w-3.5 text-accent-600 dark:text-accent-400" />
                <span>{{ ($now ?? \Carbon\Carbon::now('Asia/Jakarta'))->locale(app()->getLocale())->translatedFormat('l, d F Y') }}</span>
            </div>
        </div>

        {{-- 2. 4 Kartu KPI dengan Icon Kiri, Trend Text Bersih & Mini Sparkline SVG --}}
        <section aria-label="{{ __('Key Summary') }}" data-reveal data-reveal-delay="1">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {{-- Card 1: Total Customers (Violet / Indigo) --}}
                <a wire:navigate.hover href="{{ route('customers.index') }}"
                   class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-violet-400/50 hover:shadow-md active:scale-[0.98] dark:border-slate-800/60 dark:bg-slate-800/90 dark:hover:border-violet-500/40">
                    <div>
                        <div class="flex items-center gap-3.5">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300">
                                <x-icon name="users" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Total Customers') }}</p>
                                <p class="mt-0.5 text-2xl font-extrabold tracking-tight text-slate-900 tabular-nums sm:text-3xl dark:text-white">
                                    {{ number_format($customerCount ?? 0) }}
                                </p>
                            </div>
                        </div>

                        <p class="mt-3.5 flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                            <span class="inline-flex items-center font-semibold text-violet-600 tabular-nums dark:text-violet-400">
                                <svg class="mr-0.5 h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.22 14.78a.75.75 0 0 0 1.06 0l7.22-7.22v5.69a.75.75 0 0 0 1.5 0v-7.5a.75.75 0 0 0-.75-.75h-7.5a.75.75 0 0 0 0 1.5h5.69l-7.22 7.22a.75.75 0 0 0 0 1.06Z" clip-rule="evenodd" />
                                </svg>
                                +{{ number_format($newCustomersThisMonth ?? 0) }}
                            </span>
                            <span>{{ __('new this month') }}</span>
                        </p>
                    </div>

                    <div class="mt-3 -mx-5 -mb-5 pt-1">
                        <svg viewBox="0 0 240 46" class="h-11 w-full overflow-visible" preserveAspectRatio="none" aria-hidden="true">
                            <defs>
                                <linearGradient id="spark-customers" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#8b5cf6" stop-opacity="0.28" />
                                    <stop offset="100%" stop-color="#8b5cf6" stop-opacity="0.0" />
                                </linearGradient>
                            </defs>
                            <path d="M0,38 C36,36 58,20 94,24 C130,28 156,10 192,14 C214,16 228,6 240,8 L240,46 L0,46 Z" fill="url(#spark-customers)" />
                            <path d="M0,38 C36,36 58,20 94,24 C130,28 156,10 192,14 C214,16 228,6 240,8" fill="none" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" />
                        </svg>
                    </div>
                </a>

                {{-- Card 2: Total / Completed Projects (Emerald / Teal) --}}
                <a wire:navigate.hover href="{{ $projectsIndexUrl }}"
                   class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-emerald-400/50 hover:shadow-md active:scale-[0.98] dark:border-slate-800/60 dark:bg-slate-800/90 dark:hover:border-emerald-500/40">
                    <div>
                        <div class="flex items-center gap-3.5">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300">
                                <x-icon name="check-circle" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Total / Completed Projects') }}</p>
                                <p class="mt-0.5 text-2xl font-extrabold tracking-tight text-slate-900 tabular-nums sm:text-3xl dark:text-white">
                                    {{ number_format($totalProjects ?? 0) }}
                                </p>
                            </div>
                        </div>

                        <p class="mt-3.5 flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                            <span class="inline-flex items-center font-semibold text-emerald-600 tabular-nums dark:text-emerald-400">
                                <svg class="mr-0.5 h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.22 14.78a.75.75 0 0 0 1.06 0l7.22-7.22v5.69a.75.75 0 0 0 1.5 0v-7.5a.75.75 0 0 0-.75-.75h-7.5a.75.75 0 0 0 0 1.5h5.69l-7.22 7.22a.75.75 0 0 0 0 1.06Z" clip-rule="evenodd" />
                                </svg>
                                {{ number_format($completedProjectsTotal ?? 0) }} {{ __('Done') }}
                            </span>
                            <span>· +{{ number_format($completedProjectsThisMonth ?? 0) }} {{ __('BAST this month') }}</span>
                        </p>
                    </div>

                    <div class="mt-3 -mx-5 -mb-5 pt-1">
                        <svg viewBox="0 0 240 46" class="h-11 w-full overflow-visible" preserveAspectRatio="none" aria-hidden="true">
                            <defs>
                                <linearGradient id="spark-projects" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#10b981" stop-opacity="0.28" />
                                    <stop offset="100%" stop-color="#10b981" stop-opacity="0.0" />
                                </linearGradient>
                            </defs>
                            <path d="M0,36 C42,34 66,28 102,22 C138,16 164,24 198,12 C216,7 228,9 240,6 L240,46 L0,46 Z" fill="url(#spark-projects)" />
                            <path d="M0,36 C42,34 66,28 102,22 C138,16 164,24 198,12 C216,7 228,9 240,6" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" />
                        </svg>
                    </div>
                </a>

                {{-- Card 3: Active Projects (Amber / Yellow) --}}
                <a wire:navigate.hover href="{{ $projectsIndexUrl }}"
                   class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-amber-400/50 hover:shadow-md active:scale-[0.98] dark:border-slate-800/60 dark:bg-slate-800/90 dark:hover:border-amber-500/40">
                    <div>
                        <div class="flex items-center gap-3.5">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300">
                                <x-icon name="activity" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Active Projects') }}</p>
                                <p class="mt-0.5 text-2xl font-extrabold tracking-tight text-slate-900 tabular-nums sm:text-3xl dark:text-white">
                                    {{ number_format($activeProjects ?? 0) }}
                                </p>
                            </div>
                        </div>

                        <p class="mt-3.5 flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                            <span class="font-semibold text-amber-600 tabular-nums dark:text-amber-400">{{ number_format($openProjectsCount ?? 0) }} {{ __('Open') }}</span>
                            <span>·</span>
                            <span class="font-semibold text-slate-700 tabular-nums dark:text-slate-300">{{ number_format($onProgressProjectsCount ?? 0) }} {{ __('On Progress') }}</span>
                        </p>
                    </div>

                    <div class="mt-3 -mx-5 -mb-5 pt-1">
                        <svg viewBox="0 0 240 46" class="h-11 w-full overflow-visible" preserveAspectRatio="none" aria-hidden="true">
                            <defs>
                                <linearGradient id="spark-active" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#f59e0b" stop-opacity="0.28" />
                                    <stop offset="100%" stop-color="#f59e0b" stop-opacity="0.0" />
                                </linearGradient>
                            </defs>
                            <path d="M0,34 C34,26 68,32 104,18 C140,8 172,22 204,14 C220,10 230,12 240,9 L240,46 L0,46 Z" fill="url(#spark-active)" />
                            <path d="M0,34 C34,26 68,32 104,18 C140,8 172,22 204,14 C220,10 230,12 240,9" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" />
                        </svg>
                    </div>
                </a>

                {{-- Card 4: Total Documents & KB (Pink / Rose) --}}
                <a wire:navigate.hover href="{{ route('documents.index') }}"
                   class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-pink-400/50 hover:shadow-md active:scale-[0.98] dark:border-slate-800/60 dark:bg-slate-800/90 dark:hover:border-pink-500/40">
                    <div>
                        <div class="flex items-center gap-3.5">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-pink-500/10 text-pink-600 dark:bg-pink-500/15 dark:text-pink-300">
                                <x-icon name="book" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Total Documents & KB') }}</p>
                                <p class="mt-0.5 text-2xl font-extrabold tracking-tight text-slate-900 tabular-nums sm:text-3xl dark:text-white">
                                    {{ number_format($totalDocsAndKb ?? ($documentCount ?? 0)) }}
                                </p>
                            </div>
                        </div>

                        <p class="mt-3.5 flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                            <span class="font-semibold text-pink-600 tabular-nums dark:text-pink-400">{{ number_format($documentCount ?? 0) }} {{ __('Project Docs') }}</span>
                            <span>·</span>
                            <span class="font-semibold text-slate-700 tabular-nums dark:text-slate-300">{{ number_format($kbTotalCount ?? 0) }} {{ __('KB') }}</span>
                        </p>
                    </div>

                    <div class="mt-3 -mx-5 -mb-5 pt-1">
                        <svg viewBox="0 0 240 46" class="h-11 w-full overflow-visible" preserveAspectRatio="none" aria-hidden="true">
                            <defs>
                                <linearGradient id="spark-docs" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#ec4899" stop-opacity="0.28" />
                                    <stop offset="100%" stop-color="#ec4899" stop-opacity="0.0" />
                                </linearGradient>
                            </defs>
                            <path d="M0,38 C40,32 76,34 112,22 C148,12 174,18 206,10 C222,7 232,8 240,5 L240,46 L0,46 Z" fill="url(#spark-docs)" />
                            <path d="M0,38 C40,32 76,34 112,22 C148,12 174,18 206,10 C222,7 232,8 240,5" fill="none" stroke="#ec4899" stroke-width="2" stroke-linecap="round" />
                        </svg>
                    </div>
                </a>
            </div>
        </section>

        {{-- 3. Baris Tengah: My Agenda & Tasks Today (7) & Project Status Breakdown Donut (5) --}}
        <div class="grid grid-cols-1 items-stretch gap-6 lg:grid-cols-12">
            {{-- Kiri (lg:col-span-7): My Agenda & Tasks Today --}}
            <section class="flex h-full flex-col justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6 lg:col-span-7 dark:border-slate-800/60 dark:bg-slate-800/90" data-reveal data-reveal-delay="2" aria-labelledby="my-agenda-heading">
                <div>
                    <div class="flex items-center justify-between gap-3 pb-3">
                        <div class="flex items-center gap-2.5">
                            <h2 id="my-agenda-heading" class="text-base font-semibold text-slate-800 sm:text-lg dark:text-slate-100">
                                {{ __('My Agenda & Tasks Today') }}
                            </h2>
                            <span class="rounded-full bg-accent-500/10 px-2.5 py-0.5 text-xs font-semibold text-accent-700 tabular-nums dark:bg-accent-400/15 dark:text-accent-300">
                                {{ ($agendaItems ?? collect())->count() }}
                            </span>
                        </div>

                        @if(!empty($agendaMeta['action_url']))
                            <a wire:navigate.hover href="{{ $agendaMeta['action_url'] }}"
                               class="inline-flex items-center gap-1 text-xs font-semibold text-accent-600 transition hover:text-accent-700 dark:text-accent-400 dark:hover:text-accent-300">
                                <span>{{ $agendaMeta['action_label'] ?? __('View All') }}</span>
                                <x-icon name="chevron-right" class="h-3.5 w-3.5" />
                            </a>
                        @endif
                    </div>

                    <div class="mt-1 space-y-1.5">
                        @forelse(($agendaItems ?? collect()) as $item)
                            @php
                                $theme = $dateBlocks[$item['badge_color'] ?? 'blue'] ?? $dateBlocks['blue'];
                                $prog = min(100, max(10, (int) ($item['progress'] ?? 45)));
                            @endphp
                            <a wire:navigate.hover href="{{ $item['url'] ?? '#' }}"
                               class="group flex items-center justify-between gap-3.5 rounded-xl p-2.5 transition-colors hover:bg-slate-50/90 active:scale-[0.99] dark:hover:bg-slate-700/35">
                                <div class="flex min-w-0 items-center gap-3.5">
                                    {{-- Date Block Box bergaya kalender --}}
                                    <div class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-xl {{ $theme['box'] }}">
                                        <span class="text-sm font-extrabold leading-none tabular-nums">{{ $item['day'] ?? ($now ?? now())->format('d') }}</span>
                                        <span class="mt-0.5 text-[10px] font-bold uppercase tracking-wider opacity-80">{{ $item['month'] ?? strtoupper(($now ?? now())->format('M')) }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-800 transition-colors group-hover:text-accent-600 dark:text-slate-100 dark:group-hover:text-accent-400">
                                            {{ $item['title'] }}
                                        </p>
                                        <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                                            {{ $item['subtitle'] }} · <span class="tabular-nums">{{ $item['time_label'] }}</span>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center gap-3">
                                    {{-- Visual mini progress bar --}}
                                    <div class="hidden w-16 overflow-hidden rounded-full bg-slate-100 sm:block dark:bg-slate-700/70" title="{{ $prog }}%">
                                        <div class="h-1.5 rounded-full {{ $theme['bar'] }}" style="width: {{ $prog }}%"></div>
                                    </div>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $theme['pill'] }}">
                                        {{ $item['status_label'] }}
                                    </span>
                                </div>
                            </a>
                        @empty
                            <div class="rounded-xl bg-slate-50/70 px-5 py-10 text-center dark:bg-slate-900/30">
                                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-white text-slate-400 shadow-sm dark:bg-slate-800 dark:text-slate-500">
                                    <x-icon name="clipboard-check" class="h-5 w-5" />
                                </span>
                                <p class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('No scheduled tasks for today') }}</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>

            {{-- Kanan (lg:col-span-5): Project Status Breakdown (Side-by-Side Donut Chart & Legend) --}}
            <section class="flex h-full flex-col justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6 lg:col-span-5 dark:border-slate-800/60 dark:bg-slate-800/90" data-reveal data-reveal-delay="2" aria-labelledby="project-status-heading">
                <div class="flex items-center justify-between gap-3 pb-3">
                    <h2 id="project-status-heading" class="text-base font-semibold text-slate-800 sm:text-lg dark:text-slate-100">
                        {{ __('Project Status Breakdown') }}
                    </h2>
                    @if($canViewProjects)
                        <a wire:navigate.hover href="{{ route('projects.index') }}"
                           class="inline-flex items-center gap-1 text-xs font-semibold text-accent-600 transition hover:text-accent-700 dark:text-accent-400 dark:hover:text-accent-300">
                            <span>{{ __('Explore all projects') }}</span>
                            <x-icon name="chevron-right" class="h-3.5 w-3.5" />
                        </a>
                    @endif
                </div>

                @if($legendStatuses->isNotEmpty())
                    <div class="my-auto flex flex-col items-center justify-between gap-6 py-2 sm:flex-row">
                        {{-- Sisi Kiri: SVG Donut Chart Multi-Warna --}}
                        <div class="relative w-40 shrink-0 sm:w-44">
                            @if($donutChartData->isNotEmpty())
                                <x-donut-chart :data="$donutChartData" :size="176" :strokeWidth="24" :scroll="true" />
                            @else
                                <svg viewBox="0 0 176 176" class="h-auto w-full">
                                    <circle cx="88" cy="88" r="76" fill="transparent" stroke-width="24" style="stroke: var(--card-border)" />
                                </svg>
                            @endif
                            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center px-4 text-center">
                                <span class="text-2xl font-extrabold tracking-tight text-slate-900 tabular-nums sm:text-3xl dark:text-white">
                                    {{ number_format($totalProjects ?? 0) }}
                                </span>
                                <span class="mt-0.5 text-[11px] font-medium text-slate-400 dark:text-slate-400">
                                    {{ __('Total Projects') }}
                                </span>
                            </div>
                        </div>

                        {{-- Sisi Kanan: Daftar Legend Vertikal --}}
                        <div class="w-full flex-1 space-y-2.5">
                            @foreach($legendStatuses as $statusItem)
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <span class="flex min-w-0 items-center gap-2.5 font-medium text-slate-600 dark:text-slate-300">
                                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $statusItem['hex_color'] ?? '#6366f1' }}"></span>
                                        <span class="truncate">{{ __($statusItem['name']) }}</span>
                                    </span>
                                    <span class="shrink-0 font-bold text-slate-800 tabular-nums dark:text-slate-100">
                                        {{ number_format($statusItem['count']) }}
                                        <span class="ml-0.5 font-normal text-slate-400">({{ round($statusItem['percentage']) }}%)</span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="my-auto rounded-xl bg-slate-50/70 px-5 py-8 text-center dark:bg-slate-900/30">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('No project status data available.') }}</p>
                    </div>
                @endif
            </section>
        </div>

        {{-- 4. Baris Bawah: Recent Activity (6) & Company Board & Latest KB (6) --}}
        <div class="grid grid-cols-1 items-stretch gap-6 lg:grid-cols-12">
            {{-- Kiri (lg:col-span-6): Recent Activity --}}
            <section class="flex h-full flex-col justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6 lg:col-span-6 dark:border-slate-800/60 dark:bg-slate-800/90" data-reveal data-reveal-delay="3" aria-labelledby="recent-activities-heading">
                <div>
                    <div class="flex items-center justify-between gap-3 pb-3">
                        <h2 id="recent-activities-heading" class="text-base font-semibold text-slate-800 sm:text-lg dark:text-slate-100">
                            {{ __('Recent Activity') }}
                        </h2>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 tabular-nums dark:bg-slate-700/70 dark:text-slate-300">
                            {{ $activities->count() }} {{ __('Recent Activities') }}
                        </span>
                    </div>

                    <div class="mt-1 space-y-1">
                        @forelse($activities as $activity)
                            <article class="flex items-center justify-between gap-3.5 rounded-xl p-2.5 transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-700/35">
                                <div class="flex min-w-0 items-center gap-3.5">
                                    <div class="shrink-0">
                                        <x-user-avatar :user="$activity->user" size="w-9 h-9" text="text-xs" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm leading-snug text-slate-700 dark:text-slate-200">
                                            <span class="font-semibold text-slate-900 dark:text-white">{{ $activity->user?->name ?? __('System') }}</span>
                                            <span class="text-slate-500 dark:text-slate-300">{{ __($activity->displayTitle()) }}</span>
                                        </p>
                                        <div class="mt-0.5 flex items-center gap-1.5 truncate text-xs text-slate-500 dark:text-slate-400">
                                            @if($activity->project)
                                                @if($canViewProjects)
                                                    <a wire:navigate.hover href="{{ route('projects.show', $activity->project) }}"
                                                       class="truncate font-medium text-accent-600 hover:underline dark:text-accent-400">
                                                        {{ $activity->project->project_name }}
                                                    </a>
                                                @else
                                                    <span class="truncate font-medium text-slate-600 dark:text-slate-300">{{ $activity->project->project_name }}</span>
                                                @endif
                                                @if($activity->project->customer)
                                                    <span class="text-slate-300 dark:text-slate-600">·</span>
                                                    <span class="truncate">{{ $activity->project->customer->name }}</span>
                                                @endif
                                            @else
                                                <span>{{ __('Project not available') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <time class="shrink-0 text-xs font-medium text-slate-400 tabular-nums dark:text-slate-400" datetime="{{ $activity->activity_date?->toIso8601String() }}">
                                    {{ $activity->activity_date?->setTimezone('Asia/Jakarta')->shortRelativeDiffForHumans() ?? '-' }}
                                </time>
                            </article>
                        @empty
                            <div class="rounded-xl bg-slate-50/70 px-5 py-10 text-center dark:bg-slate-900/30">
                                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-white text-slate-400 shadow-sm dark:bg-slate-800 dark:text-slate-500">
                                    <x-icon name="activity" class="h-5 w-5" />
                                </span>
                                <p class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('No activity recorded yet.') }}</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>

            {{-- Kanan (lg:col-span-6): Company Board & Latest KB --}}
            <section class="flex h-full flex-col justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6 lg:col-span-6 dark:border-slate-800/60 dark:bg-slate-800/90" data-reveal data-reveal-delay="3" aria-labelledby="company-board-heading">
                <div>
                    <div class="flex items-center justify-between gap-3 pb-3">
                        <h2 id="company-board-heading" class="text-base font-semibold text-slate-800 sm:text-lg dark:text-slate-100">
                            {{ __('Company Board & Latest KB') }}
                        </h2>

                        <a href="{{ $user?->can('manage-admin') ? route('knowledge-base.index') : route('ai.assistant.index') }}"
                           class="inline-flex items-center gap-1 text-xs font-semibold text-accent-600 transition hover:text-accent-700 dark:text-accent-400 dark:hover:text-accent-300">
                            <span>{{ $user?->can('manage-admin') ? __('Manage KB') : __('Ask AI Assistant') }}</span>
                            <x-icon name="chevron-right" class="h-3.5 w-3.5" />
                        </a>
                    </div>

                    <div class="mt-1 space-y-1.5">
                        @forelse(($kbHighlights ?? collect()) as $idx => $kbItem)
                            @php
                                $isProductKb = ($kbItem['source'] ?? '') === __('Product KB');
                                $kbColors = [
                                    'bg-pink-500/10 text-pink-600 dark:bg-pink-500/15 dark:text-pink-300',
                                    'bg-indigo-500/10 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300',
                                    'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
                                    'bg-amber-500/10 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300',
                                ];
                                $iconBoxClass = $kbColors[$idx % count($kbColors)];
                            @endphp
                            <a href="{{ $kbItem['url'] ?? route('ai.assistant.index') }}"
                               class="group flex items-center justify-between gap-3.5 rounded-xl p-2.5 transition-colors hover:bg-slate-50/90 active:scale-[0.99] dark:hover:bg-slate-700/35">
                                <div class="flex min-w-0 items-center gap-3.5">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $iconBoxClass }}">
                                        <x-icon :name="$isProductKb ? 'cube' : 'book'" class="h-4 w-4" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-800 transition-colors group-hover:text-accent-600 dark:text-slate-100 dark:group-hover:text-accent-400">
                                            {{ $kbItem['title'] }}
                                        </p>
                                        <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                                            <span class="font-medium text-slate-600 dark:text-slate-300">{{ $kbItem['source'] }}</span>
                                            <span>·</span>
                                            <span>{{ $kbItem['category'] }}</span>
                                        </p>
                                    </div>
                                </div>
                                <span class="shrink-0 text-xs font-medium text-slate-400 tabular-nums dark:text-slate-400">
                                    {{ $kbItem['date_label'] }}
                                </span>
                            </a>
                        @empty
                            <div class="rounded-xl bg-slate-50/70 px-5 py-10 text-center dark:bg-slate-900/30">
                                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-white text-slate-400 shadow-sm dark:bg-slate-800 dark:text-slate-500">
                                    <x-icon name="book" class="h-5 w-5" />
                                </span>
                                <p class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('No knowledge base items yet') }}</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
