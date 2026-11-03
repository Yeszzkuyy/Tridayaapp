<x-app-layout>
    @php
        $user = auth()->user();
        $canViewProjects = $user && $user->canAny([
            'view-technician', 'manage-technician',
            'view-sales', 'manage-sales',
            'view-admin', 'manage-admin',
        ]);
        $projectsIndexUrl = $canViewProjects ? route('projects.index') : route('customers.index');

        $agendaBadgeClasses = [
            'violet' => 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-500/15 dark:text-violet-300 dark:border-violet-500/30',
            'cyan' => 'bg-cyan-50 text-cyan-700 border-cyan-200 dark:bg-cyan-500/15 dark:text-cyan-300 dark:border-cyan-500/30',
            'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:border-emerald-500/30',
            'blue' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:border-blue-500/30',
            'indigo' => 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:border-indigo-500/30',
            'rose' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:border-rose-500/30',
            'amber' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/30',
        ];
    @endphp

    <div class="w-full space-y-6">
        {{-- Town Hall Overview Header (compact, tanpa filler text & kotak statis) --}}
        <section class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm sm:px-6 sm:py-5 dark:border-slate-700 dark:bg-slate-800" data-reveal>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-accent-500/10 px-3 py-1 text-xs font-semibold text-accent-700 dark:bg-accent-400/15 dark:text-accent-300">
                            <x-icon name="calendar" class="h-3.5 w-3.5" />
                            {{ ($now ?? \Carbon\Carbon::now('Asia/Jakarta'))->locale(app()->getLocale())->translatedFormat('l, d F Y') }}
                        </span>
                    </div>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        {{ __('Welcome back,') }} {{ $user?->name }}
                    </h1>
                </div>
            </div>
        </section>

        {{-- Key Summary (4 Clickable Cards langsung di bawah Welcome Banner) --}}
        <section aria-label="{{ __('Key Summary') }}" data-reveal data-reveal-delay="1">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Card 1: Total Customers --}}
                <a wire:navigate.hover href="{{ route('customers.index') }}"
                   class="group flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-accent-400 hover:shadow-md active:scale-[0.98] sm:p-6 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-accent-500">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ __('Total Customers') }}</p>
                            <p class="mt-2.5 text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 tabular-nums dark:text-white">
                                {{ number_format($customerCount ?? 0) }}
                            </p>
                        </div>
                        <span class="shrink-0 rounded-xl bg-blue-50 p-3 text-blue-600 transition-colors group-hover:bg-blue-600 group-hover:text-white dark:bg-blue-500/15 dark:text-blue-300 dark:group-hover:bg-blue-500 dark:group-hover:text-white">
                            <x-icon name="users" class="h-5 w-5" />
                        </span>
                    </div>
                    <div class="mt-4 flex items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-700/70">
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 tabular-nums dark:bg-emerald-500/15 dark:text-emerald-300">
                                +{{ number_format($newCustomersThisMonth ?? 0) }}
                            </span>
                            <span>{{ __('new this month') }}</span>
                        </span>
                        <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-400 transition-transform group-hover:translate-x-0.5 group-hover:text-accent-600 dark:group-hover:text-accent-400" />
                    </div>
                </a>

                {{-- Card 2: Total / Completed Projects --}}
                <a wire:navigate.hover href="{{ $projectsIndexUrl }}"
                   class="group flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-accent-400 hover:shadow-md active:scale-[0.98] sm:p-6 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-accent-500">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ __('Total / Completed Projects') }}</p>
                            <div class="mt-2.5 flex items-baseline gap-2">
                                <p class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 tabular-nums dark:text-white">
                                    {{ number_format($totalProjects ?? 0) }}
                                </p>
                                <span class="text-xs font-semibold text-emerald-600 tabular-nums dark:text-emerald-400">
                                    ({{ number_format($completedProjectsTotal ?? 0) }} {{ __('Done') }})
                                </span>
                            </div>
                        </div>
                        <span class="shrink-0 rounded-xl bg-indigo-50 p-3 text-indigo-600 transition-colors group-hover:bg-indigo-600 group-hover:text-white dark:bg-indigo-500/15 dark:text-indigo-300 dark:group-hover:bg-indigo-500 dark:group-hover:text-white">
                            <x-icon name="check-circle" class="h-5 w-5" />
                        </span>
                    </div>
                    <div class="mt-4 flex items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-700/70">
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700 tabular-nums dark:bg-indigo-500/15 dark:text-indigo-300">
                                +{{ number_format($completedProjectsThisMonth ?? 0) }}
                            </span>
                            <span>{{ __('completed / BAST this month') }}</span>
                        </span>
                        <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-400 transition-transform group-hover:translate-x-0.5 group-hover:text-accent-600 dark:group-hover:text-accent-400" />
                    </div>
                </a>

                {{-- Card 3: Active Projects --}}
                <a wire:navigate.hover href="{{ $projectsIndexUrl }}"
                   class="group flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-accent-400 hover:shadow-md active:scale-[0.98] sm:p-6 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-accent-500">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ __('Active Projects') }}</p>
                            <p class="mt-2.5 text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 tabular-nums dark:text-white">
                                {{ number_format($activeProjects ?? 0) }}
                            </p>
                        </div>
                        <span class="shrink-0 rounded-xl bg-emerald-50 p-3 text-emerald-600 transition-colors group-hover:bg-emerald-600 group-hover:text-white dark:bg-emerald-500/15 dark:text-emerald-300 dark:group-hover:bg-emerald-500 dark:group-hover:text-white">
                            <x-icon name="activity" class="h-5 w-5" />
                        </span>
                    </div>
                    <div class="mt-4 flex items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-700/70">
                        <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                            <span class="inline-flex items-center gap-1">
                                <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                                <strong class="font-semibold text-slate-700 tabular-nums dark:text-slate-200">{{ number_format($openProjectsCount ?? 0) }}</strong> {{ __('Open') }}
                            </span>
                            <span class="text-slate-300 dark:text-slate-600">·</span>
                            <span class="inline-flex items-center gap-1">
                                <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                <strong class="font-semibold text-slate-700 tabular-nums dark:text-slate-200">{{ number_format($onProgressProjectsCount ?? 0) }}</strong> {{ __('On Progress') }}
                            </span>
                        </div>
                        <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-400 transition-transform group-hover:translate-x-0.5 group-hover:text-accent-600 dark:group-hover:text-accent-400" />
                    </div>
                </a>

                {{-- Card 4: Total Documents & KB --}}
                <a wire:navigate.hover href="{{ route('documents.index') }}"
                   class="group flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-accent-400 hover:shadow-md active:scale-[0.98] sm:p-6 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-accent-500">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ __('Total Documents & KB') }}</p>
                            <p class="mt-2.5 text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 tabular-nums dark:text-white">
                                {{ number_format($totalDocsAndKb ?? ($documentCount ?? 0)) }}
                            </p>
                        </div>
                        <span class="shrink-0 rounded-xl bg-amber-50 p-3 text-amber-600 transition-colors group-hover:bg-amber-600 group-hover:text-white dark:bg-amber-500/15 dark:text-amber-300 dark:group-hover:bg-amber-500 dark:group-hover:text-white">
                            <x-icon name="book" class="h-5 w-5" />
                        </span>
                    </div>
                    <div class="mt-4 flex items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-700/70">
                        <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                            <span><strong class="font-semibold text-slate-700 tabular-nums dark:text-slate-200">{{ number_format($documentCount ?? 0) }}</strong> {{ __('Project Docs') }}</span>
                            <span class="text-slate-300 dark:text-slate-600">·</span>
                            <span><strong class="font-semibold text-slate-700 tabular-nums dark:text-slate-200">{{ number_format($kbTotalCount ?? 0) }}</strong> {{ __('KB Articles') }}</span>
                        </div>
                        <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-400 transition-transform group-hover:translate-x-0.5 group-hover:text-accent-600 dark:group-hover:text-accent-400" />
                    </div>
                </a>
            </div>
        </section>

        {{-- Main 2-Column Content Grid --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Left Column: My Agenda & Tasks Today + Recent Activity --}}
            <div class="space-y-6 lg:col-span-7">
                {{-- Widget 1: My Agenda & Tasks Today --}}
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 dark:border-slate-700 dark:bg-slate-800" data-reveal data-reveal-delay="2" aria-labelledby="my-agenda-heading">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3.5 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <h2 id="my-agenda-heading" class="text-base sm:text-lg font-semibold text-slate-800 dark:text-slate-100">
                                {{ __('My Agenda & Tasks Today') }}
                            </h2>
                            <span class="rounded-full bg-accent-500/10 px-2.5 py-0.5 text-xs font-semibold text-accent-700 tabular-nums dark:bg-accent-400/15 dark:text-accent-300">
                                {{ ($agendaItems ?? collect())->count() }}
                            </span>
                        </div>

                        @if(!empty($agendaMeta['action_url']))
                            <a wire:navigate.hover href="{{ $agendaMeta['action_url'] }}"
                               class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-accent-400 hover:text-accent-600 active:scale-95 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-200 dark:hover:border-accent-500 dark:hover:text-accent-400">
                                <span>{{ $agendaMeta['action_label'] ?? __('View All') }}</span>
                                <x-icon name="chevron-right" class="h-3.5 w-3.5" />
                            </a>
                        @endif
                    </div>

                    <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-700/70">
                        @forelse(($agendaItems ?? collect()) as $item)
                            @php
                                $badgeClass = $agendaBadgeClasses[$item['badge_color'] ?? 'blue'] ?? $agendaBadgeClasses['blue'];
                            @endphp
                            <a wire:navigate.hover href="{{ $item['url'] ?? '#' }}"
                               class="group -mx-2 flex flex-col gap-2.5 rounded-xl px-3 py-3.5 transition-colors hover:bg-slate-50/80 active:scale-[0.99] sm:flex-row sm:items-center sm:justify-between dark:hover:bg-slate-700/40">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="mt-0.5 inline-flex shrink-0 items-center rounded-lg border px-2.5 py-1 text-[11px] font-semibold {{ $badgeClass }}">
                                        {{ $item['type_label'] }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-800 group-hover:text-accent-600 dark:text-slate-100 dark:group-hover:text-accent-400">
                                            {{ $item['title'] }}
                                        </p>
                                        <p class="mt-0.5 truncate text-xs font-medium text-slate-500 dark:text-slate-400">
                                            {{ $item['subtitle'] }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center gap-2 sm:pl-3">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-600 tabular-nums dark:bg-slate-700 dark:text-slate-300">
                                        <x-icon name="clock" class="h-3 w-3 text-slate-400 dark:text-slate-400" />
                                        {{ $item['time_label'] }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full border border-slate-200 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600 dark:border-slate-600 dark:text-slate-300">
                                        {{ $item['status_label'] }}
                                    </span>
                                    <x-icon name="chevron-right" class="h-4 w-4 text-slate-400 transition-transform group-hover:translate-x-0.5 group-hover:text-accent-600 dark:group-hover:text-accent-400" />
                                </div>
                            </a>
                        @empty
                            <div class="rounded-xl bg-slate-50 px-5 py-10 text-center dark:bg-slate-900/40">
                                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500">
                                    <x-icon name="clipboard-check" class="h-5 w-5" />
                                </span>
                                <p class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('No scheduled tasks for today') }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('You are all caught up! New schedules, follow-ups, or project tasks will appear here.') }}</p>
                            </div>
                        @endforelse
                    </div>
                </section>

                {{-- Widget 2: Recent Activity --}}
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 dark:border-slate-700 dark:bg-slate-800" data-reveal data-reveal-delay="3" aria-labelledby="recent-activities-heading">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3.5 dark:border-slate-700">
                        <h2 id="recent-activities-heading" class="text-base sm:text-lg font-semibold text-slate-800 dark:text-slate-100">
                            {{ __('Recent Activity') }}
                        </h2>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 tabular-nums dark:bg-slate-700 dark:text-slate-300">
                            {{ $activities->count() }} {{ __('Recent Activities') }}
                        </span>
                    </div>

                    <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-700/70">
                        @forelse($activities as $activity)
                            <article class="flex items-start gap-3.5 py-3.5 first:pt-1 last:pb-0">
                                <div class="shrink-0">
                                    <x-user-avatar :user="$activity->user" size="w-9 h-9" text="text-xs" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                                        <p class="min-w-0 text-sm leading-snug text-slate-700 dark:text-slate-200">
                                            <span class="font-semibold text-slate-900 dark:text-white">{{ $activity->user?->name ?? __('System') }}</span>
                                            <span class="text-slate-600 dark:text-slate-300">{{ __($activity->displayTitle()) }}</span>
                                        </p>
                                        <time class="shrink-0 text-xs font-medium text-slate-400 tabular-nums dark:text-slate-500" datetime="{{ $activity->activity_date?->toIso8601String() }}">
                                            {{ $activity->activity_date?->setTimezone('Asia/Jakarta')->diffForHumans() ?? __('Time not available') }}
                                        </time>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
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
                            </article>
                        @empty
                            <div class="rounded-xl bg-slate-50 px-5 py-10 text-center dark:bg-slate-900/40">
                                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500">
                                    <x-icon name="activity" class="h-5 w-5" />
                                </span>
                                <p class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('No activity recorded yet.') }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Project activity will appear here.') }}</p>
                            </div>
                        @endforelse
                    </div>
                </section>
            </div>

            {{-- Right Column: Project Status Breakdown + Company Board & Latest KB --}}
            <div class="space-y-6 lg:col-span-5">
                {{-- Widget 3: Project Status Breakdown --}}
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 dark:border-slate-700 dark:bg-slate-800" data-reveal data-reveal-delay="2" aria-labelledby="project-status-heading">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3.5 dark:border-slate-700">
                        <h2 id="project-status-heading" class="text-base sm:text-lg font-semibold text-slate-800 dark:text-slate-100">
                            {{ __('Project Status Breakdown') }}
                        </h2>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 tabular-nums dark:bg-slate-700 dark:text-slate-300">
                            {{ number_format($totalProjects ?? 0) }} {{ __('Projects') }}
                        </span>
                    </div>

                    <div class="mt-4 space-y-3.5">
                        @forelse(($projectStatusBreakdown ?? collect()) as $statusItem)
                            @php
                                $barWidth = $statusItem['count'] > 0
                                    ? min(100, max(4, (float) $statusItem['percentage']))
                                    : 0;
                            @endphp
                            <div>
                                <div class="mb-1.5 flex items-center justify-between gap-2 text-xs">
                                    <span class="flex min-w-0 items-center gap-2 font-semibold text-slate-700 dark:text-slate-200">
                                        <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $statusItem['dot_class'] }}"></span>
                                        <span class="truncate">{{ __($statusItem['name']) }}</span>
                                    </span>
                                    <span class="shrink-0 font-semibold text-slate-800 tabular-nums dark:text-slate-100">
                                        {{ number_format($statusItem['count']) }}
                                        <span class="ml-1 font-medium text-slate-400 dark:text-slate-400">({{ $statusItem['percentage'] }}%)</span>
                                    </span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700/70">
                                    <div class="h-full rounded-full transition-all duration-500 {{ $statusItem['bar_class'] }}"
                                         style="width: {{ $barWidth }}%"></div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl bg-slate-50 px-5 py-8 text-center dark:bg-slate-900/40">
                                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('No project status data available.') }}</p>
                            </div>
                        @endforelse
                    </div>

                    @if($canViewProjects)
                        <div class="mt-5 border-t border-slate-100 pt-3.5 dark:border-slate-700">
                            <a wire:navigate.hover href="{{ route('projects.index') }}"
                               class="inline-flex items-center gap-1 text-xs font-semibold text-accent-600 transition hover:text-accent-700 dark:text-accent-400 dark:hover:text-accent-300">
                                <span>{{ __('Explore all projects') }}</span>
                                <x-icon name="chevron-right" class="h-3.5 w-3.5" />
                            </a>
                        </div>
                    @endif
                </section>

                {{-- Widget 4: Company Board & Latest KB --}}
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 dark:border-slate-700 dark:bg-slate-800" data-reveal data-reveal-delay="3" aria-labelledby="company-board-heading">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3.5 dark:border-slate-700">
                        <h2 id="company-board-heading" class="text-base sm:text-lg font-semibold text-slate-800 dark:text-slate-100">
                            {{ __('Company Board & Latest KB') }}
                        </h2>

                        <a href="{{ $user?->can('manage-admin') ? route('knowledge-base.index') : route('ai.assistant.index') }}"
                           class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-accent-400 hover:text-accent-600 active:scale-95 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-200 dark:hover:border-accent-500 dark:hover:text-accent-400">
                            <span>{{ $user?->can('manage-admin') ? __('Manage KB') : __('Ask AI Assistant') }}</span>
                            <x-icon name="chevron-right" class="h-3.5 w-3.5" />
                        </a>
                    </div>

                    <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-700/70">
                        @forelse(($kbHighlights ?? collect()) as $kbItem)
                            @php
                                $isProductKb = ($kbItem['source'] ?? '') === __('Product KB');
                            @endphp
                            <a href="{{ $kbItem['url'] ?? route('ai.assistant.index') }}"
                               class="group -mx-2 flex items-start justify-between gap-3 rounded-xl px-3 py-3 transition-colors hover:bg-slate-50/80 active:scale-[0.99] dark:hover:bg-slate-700/40">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $isProductKb ? 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300' : 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300' }}">
                                        <x-icon :name="$isProductKb ? 'cube' : 'book'" class="h-4 w-4" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-800 group-hover:text-accent-600 dark:text-slate-100 dark:group-hover:text-accent-400">
                                            {{ $kbItem['title'] }}
                                        </p>
                                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                            <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                                {{ $kbItem['source'] }}
                                            </span>
                                            <span>·</span>
                                            <span class="truncate">{{ $kbItem['category'] }}</span>
                                            <span>·</span>
                                            <span class="tabular-nums">{{ $kbItem['date_label'] }}</span>
                                        </div>
                                    </div>
                                </div>
                                <x-icon name="chevron-right" class="mt-2 h-4 w-4 shrink-0 text-slate-400 transition-transform group-hover:translate-x-0.5 group-hover:text-accent-600 dark:group-hover:text-accent-400" />
                            </a>
                        @empty
                            <div class="rounded-xl bg-slate-50 px-5 py-8 text-center dark:bg-slate-900/40">
                                <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500">
                                    <x-icon name="book" class="h-5 w-5" />
                                </span>
                                <p class="mt-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('No knowledge base items yet') }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Published SOPs and product guides will appear here.') }}</p>
                            </div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
