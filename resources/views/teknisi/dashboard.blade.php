<x-app-layout>
    @php
        $dashboardCards = [
            [
                'label' => __('Total Technicians'),
                'value' => $technicians->count(),
                'icon' => 'users',
                'iconClass' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300',
            ],
            [
                'label' => __('Active Technicians'),
                'value' => count($activeTechnicians),
                'icon' => 'activity',
                'iconClass' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
            ],
            [
                'label' => __('Completed Projects'),
                'value' => $doneProjects,
                'icon' => 'check-circle',
                'iconClass' => 'bg-green-50 text-green-600 dark:bg-green-500/15 dark:text-green-300',
            ],
            [
                'label' => __('Ongoing Projects'),
                'value' => $runningProjects->count(),
                'icon' => 'tools',
                'iconClass' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300',
            ],
        ];

        // Donut: maksimal 6 irisan. Top 5 status terbesar tampil sendiri,
        // sisanya digabung "Lainnya" agar warna + proporsi tetap terbaca.
        $donutStatuses = $statusCounts
            ->reject(fn ($s) => $s['name'] === 'Maintenance')
            ->filter(fn ($s) => $s['count'] > 0)
            ->sortByDesc('count')
            ->values();
        $topStatuses = $donutStatuses->take(5);
        $othersCount = $donutStatuses->skip(5)->sum('count');
        $totalShown = $donutStatuses->sum('count');

        $donutData = $topStatuses->map(fn ($s) => [
            'label' => $s['name'],
            'value' => $s['count'],
            'color' => $statusBarColors[$s['name']] ?? '#64748b',
        ])->values();
        if ($othersCount > 0) {
            $donutData->push(['label' => __('Others'), 'value' => $othersCount, 'color' => '#64748b']);
        }

        $doneCount = $statusCounts->firstWhere('name', 'Done')['count'] ?? 0;
        $donePct = $totalShown > 0 ? round($doneCount / $totalShown * 100) : 0;
        $pct = fn ($n) => $totalShown > 0 ? round($n / $totalShown * 100) : 0;
    @endphp

    <div class="w-full space-y-6">
        {{-- Overview header (compact, tanpa filler text & kotak statis) --}}
        <section class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm sm:px-6 sm:py-5 dark:border-slate-700 dark:bg-slate-800" data-reveal>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-accent-600 dark:text-accent-300">
                        {{ $now->translatedFormat('l, d F Y') }}
                    </p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        {{ __('Welcome back,') }} {{ auth()->user()->name }}
                    </h1>
                </div>

                <div class="flex shrink-0 items-center">
                    <x-icon-button as="a" href="{{ route('teknisi.jadwal') }}" label="{{ __('Open Schedule') }}" title="{{ __('Open Schedule') }}">
                        <x-icon name="calendar" class="h-4 w-4" />
                    </x-icon-button>
                </div>
            </div>
        </section>

        {{-- 4 Kartu KPI Utama (langsung di bawah Welcome Banner, tanpa judul & deskripsi filler) --}}
        <section aria-label="{{ __('Key summary') }}" data-reveal data-reveal-delay="1">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($dashboardCards as $card)
                    <article class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-700 dark:bg-slate-800">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                            <p class="mt-1.5 text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 tabular-nums dark:text-white">
                                {{ number_format($card['value']) }}
                            </p>
                        </div>
                        <span class="shrink-0 rounded-xl p-3 {{ $card['iconClass'] }}">
                            <x-icon name="{{ $card['icon'] }}" class="h-5 w-5" />
                        </span>
                    </article>
                @endforeach
            </div>
        </section>

        {{-- Baris Tengah: Work Progress (5) & Latest Projects (7) --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Work Progress --}}
            <section class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:col-span-5 dark:border-slate-700 dark:bg-slate-800" data-reveal data-reveal-delay="2" aria-labelledby="progress-heading">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3.5 dark:border-slate-700">
                    <h2 id="progress-heading" class="text-base sm:text-lg font-semibold text-slate-800 dark:text-slate-100">{{ __('Work Progress') }}</h2>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 tabular-nums dark:bg-slate-700 dark:text-slate-300">
                        {{ $totalShown }} {{ __('Projects') }}
                    </span>
                </div>

                <div class="mt-4 flex flex-1 flex-col items-center justify-between gap-4" x-data="{
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
                        <div class="relative w-full max-w-[196px]">
                            <div id="teknisi-donut-chart" class="w-full">
                                <x-donut-chart :data="$donutData" :size="196" :strokeWidth="26" :scroll="true" />
                            </div>
                            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center px-6 text-center">
                                <div x-show="!activeSeg()" class="flex flex-col items-center justify-center">
                                    <span class="text-[11px] font-medium text-slate-400 dark:text-slate-500">{{ __('Completed') }}</span>
                                    <span class="text-2xl font-extrabold text-slate-800 tabular-nums dark:text-slate-100">{{ $donePct }}%</span>
                                </div>
                                <div x-show="activeSeg()" class="flex flex-col items-center justify-center">
                                    <span class="max-w-full truncate text-[11px] font-medium text-slate-400 dark:text-slate-500" x-text="activeSeg() ? activeSeg().label : ''"></span>
                                    <span class="mt-0.5 text-2xl font-extrabold text-slate-800 tabular-nums dark:text-slate-100" x-text="activeSeg() ? activeSeg().value : ''"></span>
                                    <span class="text-xs font-medium text-slate-400" x-text="activeSeg() && total ? '[' + Math.round(activeSeg().value / total * 100) + '%]' : ''"></span>
                                </div>
                            </div>
                        </div>
                    @else
                        <x-empty-state label="{{ __('project status data') }}" />
                    @endif

                    {{-- Legenda interaktif ringkas --}}
                    <div class="grid w-full grid-cols-1 gap-1 sm:grid-cols-2">
                        @foreach($donutData as $seg)
                            <button type="button" @click="select('{{ $seg['label'] }}')"
                                    class="flex items-center justify-between gap-2 rounded-xl px-2.5 py-1.5 text-xs transition hover:bg-slate-50 dark:hover:bg-slate-700/40"
                                    :class="selected === '{{ $seg['label'] }}' && 'bg-slate-50 dark:bg-slate-700/40 ring-1 ring-slate-200 dark:ring-slate-600'">
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $seg['color'] }}"></span>
                                    <span class="truncate font-semibold text-slate-700 dark:text-slate-200">{{ $seg['label'] }}</span>
                                </span>
                                <span class="shrink-0 font-bold text-slate-800 tabular-nums dark:text-slate-100">
                                    {{ $seg['value'] }}
                                    <span class="font-medium text-slate-400">· {{ $pct($seg['value']) }}%</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Latest Projects --}}
            <section class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:col-span-7 dark:border-slate-700 dark:bg-slate-800" data-reveal data-reveal-delay="3" aria-labelledby="recent-projects-heading">
                <div>
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3.5 dark:border-slate-700">
                        <h2 id="recent-projects-heading" class="text-base sm:text-lg font-semibold text-slate-800 dark:text-slate-100">{{ __('Project Terbaru') }}</h2>
                        <a href="{{ route('projects.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-accent-600 transition hover:text-accent-700 dark:text-accent-400">
                            {{ __('View All') }} <x-icon name="chevron-right" class="h-4 w-4" />
                        </a>
                    </div>

                    <div class="mt-2 divide-y divide-slate-100 dark:divide-slate-700/70">
                        @forelse($recentProjects as $project)
                            <article class="group flex flex-col gap-2 py-3 transition duration-200 hover:bg-slate-50/70 last:pb-0 sm:flex-row sm:items-center dark:hover:bg-slate-700/30">
                                <div class="min-w-0 flex-1 px-1">
                                    <a href="{{ route('projects.show', $project) }}" class="block truncate text-sm font-semibold text-slate-800 hover:text-accent-600 dark:text-slate-100 dark:hover:text-accent-400">
                                        {{ $project->project_name }}
                                    </a>
                                    <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">{{ $project->customer?->name ?? '-' }}</p>
                                </div>
                                <div class="shrink-0 px-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ __('Technician:') }} {{ $project->pic_engineer ?: ($project->support_technicians ?: '-') }}
                                </div>
                                <x-status-badge :color="($project->status ? ($statusBadgeColors[$project->status->name] ?? 'slate') : 'slate')">
                                    {{ $project->status?->name ?? '-' }}
                                </x-status-badge>
                                <div class="w-24 shrink-0 px-1 text-right text-xs text-slate-400 tabular-nums">
                                    {{ $project->created_at ? $project->created_at->setTimezone('Asia/Jakarta')->format('d M Y') : '-' }}
                                </div>
                            </article>
                        @empty
                            <x-empty-state label="{{ __('projects') }}" />
                        @endforelse
                    </div>
                </div>
            </section>
        </div>

        {{-- Baris Bawah: Active Technicians (7) & Recent Activity (5) --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Active Technicians dengan Tab Switcher (Active | Idle) & Scroll Halus --}}
            <section x-data="{ tab: 'active' }"
                     class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:col-span-7 dark:border-slate-700 dark:bg-slate-800"
                     data-reveal data-reveal-delay="4" aria-labelledby="active-technicians-heading">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3.5 dark:border-slate-700">
                    <h2 id="active-technicians-heading" class="text-base sm:text-lg font-semibold text-slate-800 dark:text-slate-100">{{ __('Active Technicians') }}</h2>

                    <div class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 p-1 text-xs font-semibold dark:border-slate-700 dark:bg-slate-900/50">
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
                <div x-show="tab === 'active'" class="mt-3 max-h-96 divide-y divide-slate-100 overflow-y-auto pr-1 dark:divide-slate-700/70">
                    @forelse($activeTechnicians as $technician)
                        @php
                            $ongoingCount = $projectsByTechnician[$technician->id]->count();
                        @endphp
                        <article class="flex items-start gap-3 py-3 first:pt-1 last:pb-1">
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
                <div x-show="tab === 'idle'" x-cloak class="mt-3 max-h-96 divide-y divide-slate-100 overflow-y-auto pr-1 dark:divide-slate-700/70">
                    @forelse($idleTechnicians as $technician)
                        <article class="flex items-center gap-3 py-2.5 first:pt-1 last:pb-1">
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
            </section>

            {{-- Recent Activity (Deduplicated & Consistent English Microcopy) --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:col-span-5 dark:border-slate-700 dark:bg-slate-800" data-reveal data-reveal-delay="5" aria-labelledby="recent-activities-heading">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3.5 dark:border-slate-700">
                    <h2 id="recent-activities-heading" class="text-base sm:text-lg font-semibold text-slate-800 dark:text-slate-100">{{ __('Recent Activity') }}</h2>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 tabular-nums dark:bg-slate-700 dark:text-slate-300">
                        {{ $activities->count() }} {{ __('Recent Activities') }}
                    </span>
                </div>

                <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-700/70">
                    @forelse($activities as $activity)
                        <article class="flex items-start gap-3.5 py-3 first:pt-1 last:pb-0">
                            <x-user-avatar :user="$activity->user" size="w-9 h-9" text="text-xs" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm leading-snug text-slate-700 dark:text-slate-200">
                                    <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $activity->user?->name ?? __('System') }}</span>
                                    <span class="text-slate-600 dark:text-slate-300">{{ __($activity->displayTitle()) }}</span>
                                </p>
                                <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                                    @if($activity->project)
                                        {{ $activity->project->project_name }} ·
                                    @endif
                                    {{ $activity->activity_date?->setTimezone('Asia/Jakarta')->diffForHumans() }}
                                </p>
                            </div>
                        </article>
                    @empty
                        <x-empty-state label="{{ __('activity') }}" />
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
