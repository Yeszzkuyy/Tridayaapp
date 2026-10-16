<x-app-layout>
<div class="px-4 sm:px-6 lg:px-8">

    {{-- Banner Header --}}
    <div data-rise class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-800 via-slate-900 to-indigo-950 px-5 py-6 sm:px-8 sm:py-7 mb-6">
        <div class="pointer-events-none absolute -top-20 -right-16 h-56 w-56 rounded-full bg-indigo-500/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-24 -left-12 h-52 w-52 rounded-full bg-blue-500/10 blur-3xl"></div>

        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-400 to-blue-600 text-lg font-bold text-white shadow-lg sm:h-14 sm:w-14 sm:text-xl">
                    {{ strtoupper(Str::substr($project->project_name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <h1 class="truncate text-2xl font-bold text-white sm:text-3xl">{{ $project->project_name }}</h1>
                    <p class="mt-1 flex items-center gap-1.5 truncate text-sm text-slate-300">
                        <x-icon name="briefcase" class="h-4 w-4 shrink-0 text-slate-400" />
                        {{ $project->customer?->name ?? '—' }} • {{ $project->workType?->name ?? '—' }}
                    </p>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <x-status-badge :color="$project->status?->color ?? 'slate'">{{ $project->status ? __($project->status->name) : __('Not Started') }}</x-status-badge>
                @can('manage-technician')
                    <x-icon-button as="a" icon="edit" href="{{ route('projects.edit', $project) }}" title="{{ __('Edit Project') }}" />
                @endcan
                <x-icon-button as="a" icon="back" href="{{ route('projects.index') }}" title="{{ __('Back') }}" />
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div x-data="{
            tab: 'info',
            moveIndicator(btn) {
                if (!btn) return;
                const i = this.$refs.indicator;
                i.style.width = btn.offsetWidth + 'px';
                i.style.left = btn.offsetLeft + 'px';
            }
         }"
         x-init="$nextTick(() => moveIndicator($refs.tabs.querySelector('.tab-active')))"
         @resize.window.debounce.150ms="moveIndicator($refs.tabs.querySelector('.tab-active'))"
         data-rise="1"
         class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">

        {{-- Tab Navigation --}}
        <div class="border-b border-slate-200 dark:border-slate-600">
            <nav x-ref="tabs" class="relative flex gap-4 sm:gap-6 px-4 sm:px-6 overflow-x-auto whitespace-nowrap scrollbar-hide">
                <span x-ref="indicator" class="tab-indicator absolute bottom-0 left-0 h-0.5 w-0 rounded-full bg-accent-500"></span>

                <button @click="tab = 'info'; moveIndicator($el)"
                        :class="tab === 'info' ? 'tab-active text-accent-600 dark:text-accent-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                        class="shrink-0 py-3 sm:py-4 px-1 font-medium text-xs sm:text-sm transition-colors duration-200">
                    <x-icon name="grid" class="h-4 w-4 mr-1.5 inline-block align-[-1px]" /> {{ __('Information') }}
                </button>
                <button @click="tab = 'workflow'; moveIndicator($el)"
                        :class="tab === 'workflow' ? 'tab-active text-accent-600 dark:text-accent-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                        class="shrink-0 py-3 sm:py-4 px-1 font-medium text-xs sm:text-sm transition-colors duration-200">
                    <x-icon name="activity" class="h-4 w-4 mr-1.5 inline-block align-[-1px]" /> {{ __('Workflow') }}
                </button>
            </nav>
        </div>

        {{-- Tab Content --}}
        <div class="p-6">

            {{-- TAB: INFORMATION --}}
            <div x-show="tab === 'info'" x-transition>
                @php
                    $doneStages = collect($steps)->where('done', true)->count();
                    $stats = [
                        ['icon' => 'chart-bar', 'label' => __('Progress'), 'value' => (int) ($project->progress ?? 0), 'suffix' => '%', 'chip' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400'],
                        ['icon' => 'check-circle', 'label' => __('Stages Done'), 'value' => $doneStages, 'suffix' => '/' . count($steps), 'chip' => 'bg-green-50 text-green-600 dark:bg-green-500/10 dark:text-green-400'],
                        ['icon' => 'book', 'label' => __('Documents'), 'value' => $documentCount, 'suffix' => '', 'chip' => 'bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-400'],
                        ['icon' => 'users', 'label' => __('Team Members'), 'value' => $teamCount, 'suffix' => '', 'chip' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400'],
                    ];
                    $infoFields = [
                        ['icon' => 'building', 'label' => __('Customer'), 'value' => $project->customer?->name],
                        ['icon' => 'briefcase', 'label' => __('Work Type'), 'value' => $project->workType?->name],
                        ['icon' => 'user', 'label' => __('Account Manager'), 'value' => $project->accountManager?->name],
                        ['icon' => 'user', 'label' => __('PIC Engineer'), 'value' => $project->pic_engineer],
                        ['icon' => 'calendar', 'label' => __('Created At'), 'value' => $project->created_at?->format('d M Y')],
                    ];
                @endphp

                {{-- Statistik --}}
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
                    @foreach($stats as $i => $stat)
                        <div class="stat-card bg-slate-50 dark:bg-slate-900/50 rounded-xl p-4 shadow-sm flex items-center gap-3" data-rise="{{ $i + 1 }}">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg {{ $stat['chip'] }} shrink-0">
                                <x-icon name="{{ $stat['icon'] }}" class="w-5 h-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-2xl font-bold tabular-nums text-slate-800 dark:text-slate-100">
                                    <span x-data="counter({{ $stat['value'] }})" x-init="start()" x-text="display"></span><span class="text-base text-slate-400">{{ $stat['suffix'] }}</span>
                                </p>
                                <p class="text-[11px] sm:text-xs text-slate-500">{{ $stat['label'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Informasi Project --}}
                    <div data-rise="2" class="bg-slate-50 dark:bg-slate-900/50 rounded-xl p-6">
                        <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">{{ __('Project Information') }}</h3>
                        <div class="space-y-1">
                            @foreach($infoFields as $field)
                                <div class="info-row flex items-start gap-3 rounded-lg px-3 py-2 -mx-3">
                                    <x-icon name="{{ $field['icon'] }}" class="w-4 h-4 mt-1 text-indigo-400 shrink-0" />
                                    <div class="min-w-0">
                                        <p class="text-xs text-slate-400">{{ $field['label'] }}</p>
                                        <p class="font-medium text-slate-800 dark:text-slate-100">{{ $field['value'] ?? '—' }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Tim Project --}}
                    <div data-rise="3" class="bg-slate-50 dark:bg-slate-900/50 rounded-xl p-6">
                        <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">{{ __('Project Team') }}</h3>
                        <div class="space-y-1">
                            <div class="info-row flex items-start gap-3 rounded-lg px-3 py-2 -mx-3">
                                <x-icon name="user" class="w-4 h-4 mt-1 text-indigo-400 shrink-0" />
                                <div class="min-w-0">
                                    <p class="text-xs text-slate-400">{{ __('Account Manager') }}</p>
                                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $project->accountManager?->name ?? '—' }}</p>
                                </div>
                            </div>
                            <div class="info-row flex items-start gap-3 rounded-lg px-3 py-2 -mx-3">
                                <x-icon name="user" class="w-4 h-4 mt-1 text-indigo-400 shrink-0" />
                                <div class="min-w-0">
                                    <p class="text-xs text-slate-400">{{ __('PIC Engineer') }}</p>
                                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $project->pic_engineer ?? '—' }}</p>
                                </div>
                            </div>
                            <div class="info-row flex items-start gap-3 rounded-lg px-3 py-2 -mx-3">
                                <x-icon name="users" class="w-4 h-4 mt-1 text-indigo-400 shrink-0" />
                                <div class="min-w-0">
                                    <p class="text-xs text-slate-400">{{ __('Support Technicians') }}</p>
                                    <div class="flex flex-wrap gap-1.5 mt-1">
                                        @forelse($supportTechnicians as $tech)
                                            <span class="inline-flex items-center rounded-full bg-indigo-50 dark:bg-indigo-500/10 px-2.5 py-1 text-xs font-medium text-indigo-600 dark:text-indigo-300">{{ $tech }}</span>
                                        @empty
                                            <span class="text-slate-400">—</span>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Deskripsi --}}
                @if($project->description)
                    <div data-rise="3" class="mt-6 bg-slate-50 dark:bg-slate-900/50 rounded-xl p-6">
                        <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">{{ __('Description') }}</h3>
                        <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-line">{{ $project->description }}</p>
                    </div>
                @endif

                {{-- Aktivitas Terbaru --}}
                <div data-rise="3" class="mt-6 bg-slate-50 dark:bg-slate-900/50 rounded-xl p-6">
                    <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">{{ __('Recent Activity') }}</h3>
                    @if($activities->isNotEmpty())
                        <div class="relative">
                            <div class="absolute left-[15px] top-8 bottom-4 w-0.5 bg-slate-200 dark:bg-slate-700"></div>
                            @foreach($activities as $activity)
                                <div class="relative pl-10 pb-5 last:pb-0" data-rise="{{ min($loop->iteration, 4) }}">
                                    <div class="absolute left-0 top-0">
                                        <x-user-avatar :user="$activity->user" size="w-8 h-8" text="text-xs" />
                                    </div>
                                    <div class="bg-white dark:bg-slate-800 rounded-xl p-3.5 shadow-sm">
                                        <p class="text-sm text-slate-800 dark:text-slate-100">
                                            <span class="font-semibold">{{ $activity->user?->name ?? 'System' }}</span>
                                            {{ $activity->title ?? 'Activity' }}
                                            <span class="text-xs text-slate-400">· {{ ($activity->activity_date ?? $activity->created_at)?->diffForHumans() }}</span>
                                        </p>
                                        @if($activity->description)
                                            <p class="text-xs text-slate-500 mt-0.5">{{ $activity->description }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-slate-400 text-sm text-center py-4">{{ __('No activity yet.') }}</p>
                    @endif
                </div>
            </div>

            {{-- TAB: WORKFLOW --}}
            <div x-show="tab === 'workflow'" x-transition>
                <div class="bg-slate-50 dark:bg-slate-900/50 rounded-xl p-6">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">{{ __('Workflow Progress') }}</h3>
                        <span class="text-sm font-bold tabular-nums text-slate-800 dark:text-slate-100">{{ $progress }}%</span>
                    </div>

                    {{-- Progress bar --}}
                    <div class="h-2.5 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden mb-8">
                        <div class="h-full bg-accent-600 rounded-full transition-all duration-500" style="width: {{ $progress }}%"></div>
                    </div>

                    {{-- Timeline --}}
                    <div class="relative">
                        <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-slate-200 dark:bg-slate-700"></div>

                        @foreach($steps as $i => $step)
                            <div class="relative flex items-start gap-4 pb-8 last:pb-0">
                                {{-- Dot --}}
                                <div class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $step['done'] ? 'bg-green-500 text-white' : 'bg-white dark:bg-slate-800 text-slate-400 border border-slate-200 dark:border-slate-600' }}">
                                    @if($step['done'])
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    @else
                                        <span class="text-xs font-bold">{{ $i + 1 }}</span>
                                    @endif
                                </div>

                                {{-- Content --}}
                                <div class="flex-1 min-w-0 pt-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $step['label'] }}</h4>
                                            <x-status-badge :color="$step['status_color']">{{ $step['status_label'] }}</x-status-badge>
                                        </div>
                                        @if($step['link'])
                                            <a href="{{ $step['link'] }}" class="text-xs text-accent-600 hover:text-accent-700 dark:text-accent-400 dark:hover:text-accent-300 font-medium shrink-0">
                                                {{ $step['done'] ? __('View') : __('Start') }} →
                                            </a>
                                        @endif
                                    </div>
                                    @if($step['date'])
                                        <p class="text-xs text-slate-500 mt-1">{{ $step['date']->format('d M Y') }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</x-app-layout>
