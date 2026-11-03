<x-app-layout>
    @include('partials.lynk-dashboard-motion')

    @php
        $sparkPaths = [
            [
                'id' => 'tek-spark-1',
                'color' => '#8b5cf6',
                'iconBg' => 'bg-violet-500/10 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300',
                'borderHover' => 'hover:border-violet-400/50 dark:hover:border-violet-500/40',
                'line' => 'M0,44 C22,42 32,18 56,20 C80,22 92,40 118,34 C144,28 158,10 184,16 C206,21 222,6 240,4',
                'area' => 'M0,44 C22,42 32,18 56,20 C80,22 92,40 118,34 C144,28 158,10 184,16 C206,21 222,6 240,4 L240,52 L0,52 Z',
            ],
            [
                'id' => 'tek-spark-2',
                'color' => '#10b981',
                'iconBg' => 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
                'borderHover' => 'hover:border-emerald-400/50 dark:hover:border-emerald-500/40',
                'line' => 'M0,40 C26,16 46,16 70,32 C94,48 114,14 142,18 C170,22 186,36 210,16 C224,6 232,6 240,4',
                'area' => 'M0,40 C26,16 46,16 70,32 C94,48 114,14 142,18 C170,22 186,36 210,16 C224,6 232,6 240,4 L240,52 L0,52 Z',
            ],
            [
                'id' => 'tek-spark-3',
                'color' => '#f59e0b',
                'iconBg' => 'bg-amber-500/10 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300',
                'borderHover' => 'hover:border-amber-400/50 dark:hover:border-amber-500/40',
                'line' => 'M0,46 C28,44 44,14 72,22 C100,30 116,42 144,20 C172,2 194,28 218,12 C228,6 234,6 240,5',
                'area' => 'M0,46 C28,44 44,14 72,22 C100,30 116,42 144,20 C172,2 194,28 218,12 C228,6 234,6 240,5 L240,52 L0,52 Z',
            ],
            [
                'id' => 'tek-spark-4',
                'color' => '#ec4899',
                'iconBg' => 'bg-pink-500/10 text-pink-600 dark:bg-pink-500/15 dark:text-pink-300',
                'borderHover' => 'hover:border-pink-400/50 dark:hover:border-pink-500/40',
                'line' => 'M0,42 C20,22 42,38 68,26 C94,14 116,40 146,28 C176,16 196,8 218,14 C228,16 234,6 240,4',
                'area' => 'M0,42 C20,22 42,38 68,26 C94,14 116,40 146,28 C176,16 196,8 218,14 C228,16 234,6 240,4 L240,52 L0,52 Z',
            ],
        ];

        $dashboardCards = [
            [
                'label' => __('Total Technicians'),
                'value' => $technicians->count(),
                'icon' => 'users',
            ],
            [
                'label' => __('Active Technicians'),
                'value' => count($activeTechnicians),
                'icon' => 'activity',
            ],
            [
                'label' => __('Completed Projects'),
                'value' => $doneProjects,
                'icon' => 'check-circle',
            ],
            [
                'label' => __('Ongoing Projects'),
                'value' => $runningProjects->count(),
                'icon' => 'tools',
            ],
        ];

        // Tampilkan seluruh status yang memiliki proyek (count > 0) agar genap 100%
        $donutStatuses = $statusCounts
            ->filter(fn ($s) => $s['count'] > 0)
            ->sortByDesc('count')
            ->values();
        $totalShown = $donutStatuses->sum('count');

        $donutData = $donutStatuses->map(fn ($s) => [
            'label' => $s['name'],
            'value' => $s['count'],
            'color' => $statusBarColors[$s['name']] ?? '#64748b',
        ])->values();

        $doneCount = $statusCounts->firstWhere('name', 'Done')['count'] ?? 0;
        $donePct = $totalShown > 0 ? round($doneCount / $totalShown * 100) : 0;
        $pct = fn ($n) => $totalShown > 0 ? round($n / $totalShown * 100) : 0;
    @endphp

    <div class="w-full space-y-6">
        {{-- 1. Open Header Style (delay 0ms) --}}
        <div class="lynk-enter flex flex-col gap-3 pt-1 sm:flex-row sm:items-center sm:justify-between" style="--d: 0ms">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-slate-100">
                {{ __('Welcome back,') }} {{ auth()->user()->name }} <span aria-hidden="true">👋</span>
            </h1>

            <div class="flex flex-wrap items-center gap-2.5">
                <div class="inline-flex items-center gap-2 rounded-full border border-slate-200/80 bg-white px-4 py-2 text-xs font-semibold text-slate-600 shadow-sm dark:border-slate-800/70 dark:bg-slate-800/90 dark:text-slate-300">
                    <x-icon name="calendar" class="h-3.5 w-3.5 text-accent-600 dark:text-accent-400" />
                    <span>{{ $now->translatedFormat('l, d F Y') }}</span>
                </div>

                <x-icon-button as="a" href="{{ route('teknisi.jadwal') }}" label="{{ __('Open Schedule') }}" title="{{ __('Open Schedule') }}">
                    <x-icon name="calendar" class="h-4 w-4" />
                </x-icon-button>
            </div>
        </div>

        {{-- 2. 4 Kartu KPI Bergaya Lynk (delay 100ms, 180ms, 260ms, 340ms) --}}
        <section aria-label="{{ __('Key summary') }}">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($dashboardCards as $idx => $card)
                    @php
                        $sp = $sparkPaths[$idx % count($sparkPaths)];
                        $delayMs = 100 + ($idx * 80);
                    @endphp
                    <article style="--d: {{ $delayMs }}ms"
                             class="lynk-enter lynk-kpi-card group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg dark:border-slate-800/60 dark:bg-slate-800/90 {{ $sp['borderHover'] }}">
                        <div class="flex items-center gap-3.5">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition-transform duration-300 group-hover:scale-105 {{ $sp['iconBg'] }}">
                                <x-icon name="{{ $card['icon'] }}" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                                <p class="mt-0.5 text-2xl font-extrabold tracking-tight text-slate-900 tabular-nums sm:text-3xl dark:text-white"
                                   data-countup="{{ (int) $card['value'] }}" data-countup-delay="{{ $delayMs + 20 }}">
                                    {{ number_format($card['value']) }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 -mx-5 -mb-5 pt-1">
                            <svg viewBox="0 0 240 52" class="h-12 w-full overflow-visible" preserveAspectRatio="none" aria-hidden="true">
                                <defs>
                                    <linearGradient id="{{ $sp['id'] }}" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="{{ $sp['color'] }}" stop-opacity="0.32" />
                                        <stop offset="100%" stop-color="{{ $sp['color'] }}" stop-opacity="0.0" />
                                    </linearGradient>
                                </defs>
                                <path class="lynk-spark-area" d="{{ $sp['area'] }}" fill="url(#{{ $sp['id'] }})" />
                                <path class="lynk-spark-line" pathLength="100" d="{{ $sp['line'] }}" fill="none" stroke="{{ $sp['color'] }}" stroke-width="2.2" stroke-linecap="round" />
                            </svg>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        {{-- 3. Baris Tengah: Work Progress Side-by-Side Donut (420ms) & Latest Projects (500ms) --}}
        <div class="grid grid-cols-1 items-stretch gap-6 lg:grid-cols-12">
            {{-- Work Progress (Side-by-Side Donut Chart & Legend) --}}
            <section style="--d: 420ms"
                     class="lynk-enter flex h-full flex-col justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6 lg:col-span-5 dark:border-slate-800/60 dark:bg-slate-800/90"
                     aria-labelledby="progress-heading">
                <div class="flex items-center justify-between gap-3 pb-3">
                    <h2 id="progress-heading" class="text-base font-semibold text-slate-800 sm:text-lg dark:text-slate-100">{{ __('Work Progress') }}</h2>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 tabular-nums dark:bg-slate-700/70 dark:text-slate-300">
                        {{ $totalShown }} {{ __('Projects') }}
                    </span>
                </div>

                <div class="my-auto flex flex-col items-center justify-between gap-6 py-2 sm:flex-row" x-data="{
                    selected: null,
                    segments: @js($donutData->values()),
                    total: {{ $totalShown }},
                    select(label) {
                        this.selected = this.selected === label ? null : label;
                        this.hl();
                    },
                    hl() {
                        const key = this.selected;
                        document.querySelectorAll('#teknisi-donut-chart .donut-seg').forEach((seg) => {
                            if (key && seg.dataset.key === key) seg.dataset.active = 'true';
                            else delete seg.dataset.active;
                        });
                    },
                    activeSeg() { return this.segments.find((s) => (s.key || s.label) === this.selected); },
                }"
                x-on:donut-select.window="select($event.detail.key)"
                >
                    @if($donutData->isNotEmpty() && $totalShown > 0)
                        <div class="relative w-40 shrink-0 sm:w-44">
                            <div id="teknisi-donut-chart" class="w-full">
                                <x-donut-chart :data="$donutData" :size="176" :strokeWidth="24" :scroll="true" />
                            </div>
                            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center px-4 text-center">
                                <div x-show="!activeSeg()" class="flex flex-col items-center justify-center">
                                    <span class="text-2xl font-extrabold text-slate-900 tabular-nums sm:text-3xl dark:text-white"
                                          data-countup="{{ $totalShown }}" data-countup-delay="450">{{ $totalShown }}</span>
                                    <span class="mt-0.5 text-[11px] font-medium text-slate-400 dark:text-slate-400">{{ __('Total Projects') }}</span>
                                </div>
                                <div x-show="activeSeg()" x-cloak class="flex flex-col items-center justify-center">
                                    <span class="max-w-full truncate text-[11px] font-medium text-slate-400 dark:text-slate-400" x-text="activeSeg() ? activeSeg().label : ''"></span>
                                    <span class="mt-0.5 text-2xl font-extrabold text-slate-900 tabular-nums dark:text-white" x-text="activeSeg() ? activeSeg().value : ''"></span>
                                    <span class="text-xs font-medium text-slate-400" x-text="activeSeg() && total ? '[' + Math.round(activeSeg().value / total * 100) + '%]' : ''"></span>
                                </div>
                            </div>
                        </div>

                        <div class="w-full flex-1 space-y-2">
                            @foreach($donutData as $seg)
                                <button type="button" @click="select('{{ $seg['label'] }}')"
                                        class="flex w-full items-center justify-between gap-2 rounded-xl px-2.5 py-1.5 text-xs transition hover:bg-slate-50/90 dark:hover:bg-slate-700/35"
                                        :class="selected === '{{ $seg['label'] }}' && 'bg-slate-50 dark:bg-slate-700/40 ring-1 ring-slate-200 dark:ring-slate-600'">
                                    <span class="flex min-w-0 items-center gap-2.5 font-medium text-slate-600 dark:text-slate-300">
                                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $seg['color'] }}"></span>
                                        <span class="truncate">{{ $seg['label'] }}</span>
                                    </span>
                                    <span class="shrink-0 font-bold text-slate-800 tabular-nums dark:text-slate-100">
                                        <span data-countup="{{ (int) $seg['value'] }}" data-countup-delay="480">{{ $seg['value'] }}</span>
                                        <span class="ml-0.5 font-normal text-slate-400">(<span data-countup="{{ $pct($seg['value']) }}" data-countup-suffix="%" data-countup-delay="480">{{ $pct($seg['value']) }}%</span>)</span>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    @else
                        <div class="w-full">
                            <x-empty-state label="{{ __('project status data') }}" />
                        </div>
                    @endif
                </div>
            </section>

            {{-- Latest Projects (Clean List dengan Date Block & Mini Progress) --}}
            <section style="--d: 500ms"
                     class="lynk-enter flex h-full flex-col justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6 lg:col-span-7 dark:border-slate-800/60 dark:bg-slate-800/90"
                     aria-labelledby="recent-projects-heading">
                <div>
                    <div class="flex items-center justify-between gap-3 pb-3">
                        <h2 id="recent-projects-heading" class="text-base font-semibold text-slate-800 sm:text-lg dark:text-slate-100">{{ __('Project Terbaru') }}</h2>
                        <a href="{{ route('projects.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-accent-600 transition hover:text-accent-700 dark:text-accent-400">
                            {{ __('View All') }} <x-icon name="chevron-right" class="h-4 w-4" />
                        </a>
                    </div>

                    <div class="mt-1 space-y-1.5">
                        @forelse($recentProjects as $project)
                            @php
                                $dt = $project->created_at ? $project->created_at->setTimezone('Asia/Jakarta') : $now;
                                $isDone = $project->status?->name === 'Done';
                                $isProg = $project->status?->name === 'On Progress';
                                $progVal = (int) ($project->progress ?? 0);
                                if ($progVal <= 0) {
                                    $progVal = $isDone ? 100 : ($isProg ? 60 : 25);
                                }
                                $barColor = $isDone ? 'bg-emerald-500' : ($isProg ? 'bg-amber-500' : 'bg-indigo-500');
                                $boxColor = $isDone
                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                                    : ($isProg ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' : 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300');
                            @endphp
                            <article class="group flex flex-col gap-2.5 rounded-xl p-2.5 transition-colors hover:bg-slate-50/90 sm:flex-row sm:items-center sm:justify-between dark:hover:bg-slate-700/35">
                                <div class="flex min-w-0 items-center gap-3.5">
                                    <div class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-xl {{ $boxColor }}">
                                        <span class="text-sm font-extrabold leading-none tabular-nums">{{ $dt->format('d') }}</span>
                                        <span class="mt-0.5 text-[10px] font-bold uppercase tracking-wider opacity-80">{{ strtoupper($dt->format('M')) }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <a href="{{ route('projects.show', $project) }}" class="block truncate text-sm font-semibold text-slate-800 hover:text-accent-600 dark:text-slate-100 dark:hover:text-accent-400">
                                            {{ $project->project_name }}
                                        </a>
                                        <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                                            {{ $project->customer?->name ?? '-' }} · {{ __('Technician:') }} {{ $project->pic_engineer ?: ($project->support_technicians ?: '-') }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center gap-3">
                                    <div class="hidden w-16 overflow-hidden rounded-full bg-slate-100 sm:block dark:bg-slate-700/70" title="{{ $progVal }}%">
                                        <div class="lynk-progress-bar h-1.5 rounded-full {{ $barColor }}" style="--target-w: {{ $progVal }}%; --d: 500ms"></div>
                                    </div>
                                    <x-status-badge :color="($project->status ? ($statusBadgeColors[$project->status->name] ?? 'slate') : 'slate')">
                                        {{ $project->status?->name ?? '-' }}
                                    </x-status-badge>
                                </div>
                            </article>
                        @empty
                            <x-empty-state label="{{ __('projects') }}" />
                        @endforelse
                    </div>
                </div>
            </section>
        </div>

        {{-- 4. Baris Bawah: Active Technicians (580ms) & Recent Activity (660ms) --}}
        <div class="grid grid-cols-1 items-stretch gap-6 lg:grid-cols-12">
            {{-- Active Technicians dengan Tab Switcher (Active | Idle) --}}
            <section x-data="{ tab: 'active' }"
                     style="--d: 580ms"
                     class="lynk-enter flex h-full flex-col justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6 lg:col-span-7 dark:border-slate-800/60 dark:bg-slate-800/90"
                     aria-labelledby="active-technicians-heading">
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-3">
                        <h2 id="active-technicians-heading" class="text-base font-semibold text-slate-800 sm:text-lg dark:text-slate-100">{{ __('Active Technicians') }}</h2>

                        <div class="inline-flex items-center rounded-xl border border-slate-200/80 bg-slate-50 p-1 text-xs font-semibold dark:border-slate-700 dark:bg-slate-900/50">
                            <button type="button"
                                    @click="tab = 'active'"
                                    :class="tab === 'active'
                                        ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-800 dark:text-white'
                                        : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                                    class="rounded-lg px-3 py-1 transition tabular-nums">
                                {{ __('Active') }} ({{ count($activeTechnicians) }})
                            </button>
                            <button type="button"
                                    @click="tab = 'idle'"
                                    :class="tab === 'idle'
                                        ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-800 dark:text-white'
                                        : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                                    class="rounded-lg px-3 py-1 transition tabular-nums">
                                {{ __('Idle') }} ({{ $idleTechnicians->count() }})
                            </button>
                        </div>
                    </div>

                    {{-- Panel Active --}}
                    <div x-show="tab === 'active'" class="mt-1 max-h-96 space-y-1 overflow-y-auto pr-1">
                        @forelse($activeTechnicians as $technician)
                            @php
                                $ongoingCount = $projectsByTechnician[$technician->id]->count();
                            @endphp
                            <article class="flex items-start gap-3.5 rounded-xl p-2.5 transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-700/35">
                                <div class="relative mt-0.5 shrink-0">
                                    <x-user-avatar :user="$technician" size="w-9 h-9" text="text-xs" />
                                    <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-green-500 dark:border-slate-800"></span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $technician->name }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400 tabular-nums">
                                        {{ $ongoingCount }} {{ $ongoingCount === 1 ? __('ongoing project') : __('ongoing projects') }}
                                    </p>
                                    <div class="mt-1 flex flex-col gap-0.5">
                                        @foreach($projectsByTechnician[$technician->id] as $project)
                                            <a href="{{ route('projects.show', $project) }}"
                                               class="truncate text-xs font-medium text-accent-600 transition hover:text-accent-700 hover:underline dark:text-accent-400">
                                                {{ $project->project_name }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                                <x-status-badge color="green">{{ __('Active') }}</x-status-badge>
                            </article>
                        @empty
                            <x-empty-state label="{{ __('active technicians') }}" />
                        @endforelse
                    </div>

                    {{-- Panel Idle --}}
                    <div x-show="tab === 'idle'" x-cloak class="mt-1 max-h-96 space-y-1 overflow-y-auto pr-1">
                        @forelse($idleTechnicians as $technician)
                            <article class="flex items-center gap-3.5 rounded-xl p-2.5 transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-700/35">
                                <x-user-avatar :user="$technician" size="w-9 h-9" text="text-xs" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $technician->name }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('No ongoing projects') }}</p>
                                </div>
                                <x-status-badge color="slate">{{ __('Idle') }}</x-status-badge>
                            </article>
                        @empty
                            <x-empty-state label="{{ __('idle technicians') }}" />
                        @endforelse
                    </div>
                </div>
            </section>

            {{-- Recent Activity --}}
            <section style="--d: 660ms"
                     class="lynk-enter flex h-full flex-col justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6 lg:col-span-5 dark:border-slate-800/60 dark:bg-slate-800/90"
                     aria-labelledby="recent-activities-heading">
                <div>
                    <div class="flex items-center justify-between gap-3 pb-3">
                        <h2 id="recent-activities-heading" class="text-base font-semibold text-slate-800 sm:text-lg dark:text-slate-100">{{ __('Recent Activity') }}</h2>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 tabular-nums dark:bg-slate-700/70 dark:text-slate-300">
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
                                            <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $activity->user?->name ?? __('System') }}</span>
                                            <span class="text-slate-500 dark:text-slate-300">{{ __($activity->displayTitle()) }}</span>
                                        </p>
                                        <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                                            {{ $activity->project?->project_name ?? __('Project not available') }}
                                        </p>
                                    </div>
                                </div>
                                <time class="shrink-0 text-xs font-medium text-slate-400 tabular-nums">
                                    {{ $activity->activity_date?->setTimezone('Asia/Jakarta')->shortRelativeDiffForHumans() ?? '-' }}
                                </time>
                            </article>
                        @empty
                            <x-empty-state label="{{ __('activity') }}" />
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
