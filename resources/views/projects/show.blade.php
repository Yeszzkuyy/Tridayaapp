<x-app-layout>
<div class="px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-3xl font-bold text-slate-800">{{ $project->project_name }}</h1>
                <x-status-badge :color="$project->status?->color ?? 'slate'">{{ $project->status ? __($project->status->name) : __('Not Started') }}</x-status-badge>
            </div>
            <p class="text-slate-500 mt-1">{{ __('Project Detail') }}</p>
        </div>
        <div class="flex gap-2">
            @can('manage-technician')
                <x-icon-button as="a" icon="edit" href="{{ route('projects.edit', $project) }}" title="{{ __('Edit Project') }}" />
            @endcan
            <x-icon-button as="a" icon="back" href="{{ route('projects.index') }}" title="{{ __('Back') }}" />
        </div>
    </div>

    {{-- Tabs --}}
    <div x-data="{ tab: 'info' }" class="space-y-6">
        <div class="border-b border-slate-200 dark:border-slate-700">
            <nav class="flex gap-6">
                <button @click="tab = 'info'" :class="tab === 'info' ? 'border-accent-600 text-accent-600 dark:border-accent-400 dark:text-accent-300' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'" class="inline-flex items-center gap-2 border-b-2 px-1 pb-3 pt-1 text-sm font-semibold transition">
                    <span>{{ __('Information') }}</span>
                </button>
                <button @click="tab = 'workflow'" :class="tab === 'workflow' ? 'border-accent-600 text-accent-600 dark:border-accent-400 dark:text-accent-300' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'" class="inline-flex items-center gap-2 border-b-2 px-1 pb-3 pt-1 text-sm font-semibold transition">
                    <span>{{ __('Workflow') }}</span>
                </button>
            </nav>
        </div>

        {{-- Tab: Information --}}
        <div x-show="tab === 'info'" x-transition>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="grid grid-cols-1 gap-x-10 gap-y-8 md:grid-cols-2">
                    <div>
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-4">{{ __('Project') }}</h2>
                        <dl class="space-y-3">
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-sm text-slate-500">{{ __('Customer') }}</dt>
                                <dd class="text-sm font-medium text-slate-800 text-right">{{ $project->customer?->name ?? '—' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-sm text-slate-500">{{ __('Work Type') }}</dt>
                                <dd class="text-sm font-medium text-slate-800 text-right">{{ $project->workType?->name ?? '—' }}</dd>
                            </div>
                        </dl>
                    </div>
                    <div>
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-4">{{ __('Team') }}</h2>
                        <dl class="space-y-3">
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-sm text-slate-500">{{ __('Account Manager') }}</dt>
                                <dd class="text-sm font-medium text-slate-800 text-right">{{ $project->accountManager?->name ?? '—' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-sm text-slate-500">{{ __('PIC Engineer') }}</dt>
                                <dd class="text-sm font-medium text-slate-800 text-right">{{ $project->pic_engineer ?? '—' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-sm text-slate-500">{{ __('Support Technicians') }}</dt>
                                <dd class="text-sm font-medium text-slate-800 text-right">{{ $project->support_technicians ?? '—' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
                @if($project->description)
                    <div class="mt-8 pt-6 border-t border-slate-100">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">{{ __('Description') }}</h2>
                        <p class="text-sm text-slate-700">{{ $project->description }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Tab: Workflow --}}
        <div x-show="tab === 'workflow'" x-transition>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-lg font-bold text-slate-800">{{ __('Workflow') }}</h3>
                    <span class="text-sm font-bold text-slate-800">{{ $progress }}%</span>
                </div>

                {{-- Progress bar --}}
                <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden mb-8">
                    <div class="h-full bg-accent-600 rounded-full transition-all duration-500" style="width: {{ $progress }}%"></div>
                </div>

                {{-- Timeline --}}
                <div class="relative">
                    <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-slate-200"></div>

                    @foreach($steps as $i => $step)
                        <div class="relative flex items-start gap-4 pb-8 last:pb-0">
                            {{-- Dot --}}
                            <div class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $step['done'] ? 'bg-green-500 text-white' : 'bg-slate-200 text-slate-400' }}">
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
                                        <h4 class="text-sm font-bold text-slate-800">{{ $step['label'] }}</h4>
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
</x-app-layout>
