<x-app-layout>
<div class="px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ $project->project_name }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Project Detail') }}</p>
        </div>
        <div class="flex gap-2">
            <x-icon-button as="a" href="{{ route('project-documents.index', $project) }}" title="{{ __('Documents') }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
            </x-icon-button>
            @can('update', $project)
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

        {{-- Tab: Informasi --}}
        <div x-show="tab === 'info'" x-transition>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div><p class="text-xs text-slate-400">{{ __('Project Name') }}</p><p class="font-medium text-slate-800">{{ $project->project_name }}</p></div>
                    <div><p class="text-xs text-slate-400">{{ __('Customer') }}</p><p class="font-medium text-slate-800">{{ $project->customer?->name ?? '-' }}</p></div>
                    <div><p class="text-xs text-slate-400">{{ __('Jenis Pekerjaan') }}</p><p class="font-medium text-slate-800">{{ $project->workType?->name ?? '-' }}</p></div>
                    <div><p class="text-xs text-slate-400">{{ __('Status') }}</p><x-status-badge :color="$project->status?->color ?? 'slate'">{{ $project->status?->name ?? __('Not Started') }}</x-status-badge></div>
                    <div><p class="text-xs text-slate-400">{{ __('Account Manager') }}</p><p class="font-medium text-slate-800">{{ $project->accountManager?->name ?? '-' }}</p></div>
                    <div><p class="text-xs text-slate-400">{{ __('PIC Engineer') }}</p><p class="font-medium text-slate-800">{{ $project->engineer?->name ?? ($project->pic_engineer ?? '-') }}</p></div>
                    <div><p class="text-xs text-slate-400">{{ __('Support Technicians') }}</p><p class="font-medium text-slate-800">{{ $project->supportUsers->isNotEmpty() ? $project->supportUsers->pluck('name')->implode(', ') : ($project->support_technicians ?? '-') }}</p></div>
                </div>
                @if($project->description)
                    <div class="mt-4 pt-4 border-t"><p class="text-xs text-slate-400">{{ __('Description') }}</p><p class="text-slate-700">{{ $project->description }}</p></div>
                @endif
            </div>
        </div>

        {{-- Tab: Workflow --}}
        <div x-show="tab === 'workflow'" x-transition>
            @php
                $steps = $workflow['steps'];
                $progress = $workflow['progress'];
            @endphp

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-lg font-bold text-slate-800">{{ __('Workflow Progress') }}</h3>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                        {{ $workflow['done'] }}/{{ $workflow['total'] }} {{ __('steps completed') }}
                    </span>
                </div>
                <div class="mb-8">
                    <div class="flex justify-between text-sm text-slate-500 mb-2">
                        <span>{{ __('Progress') }}</span>
                        <span class="font-bold text-slate-800">{{ $progress }}%</span>
                    </div>
                    <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-accent-600 rounded-full transition-all duration-500" style="width: {{ $progress }}%"></div>
                    </div>
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
                                    <h4 class="text-sm font-bold text-slate-800">{{ $step['label'] }}</h4>
                                    @if($step['url'] && ($step['done'] ? $step['can_view'] : $step['can_start']))
                                        <a href="{{ $step['url'] }}" class="text-xs text-accent-600 hover:text-accent-700 dark:text-accent-400 dark:hover:text-accent-300 font-medium shrink-0">
                                            {{ $step['done'] ? __('View') : __('Start') }} →
                                        </a>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5">{{ $step['detail'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
</x-app-layout>
