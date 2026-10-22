<x-app-layout>
<div class="max-w-[1400px] mx-auto space-y-8">
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Project List') }}</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1">{{ __('All projects, including completed or on-hold ones.') }}</p>
        </div>
        <div class="flex gap-2">
            @can('manage-technician')
                <x-icon-button as="a" icon="add" href="{{ route('projects.create') }}" title="{{ __('Add Project') }}" />
            @endcan
        </div>
    </div>

    {{-- Tab & filter (FR-PROJ-03) --}}
    @php
        $projectTabs = [];
        if ($isTech || $isManager) {
            $projectTabs['all'] = __('All Projects');
        }
        if ($isTech) {
            $projectTabs['assigned'] = __('My Assigned Projects');
        }
        $projectTabs['sales'] = __('My Sales Projects');
    @endphp
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="inline-flex flex-wrap gap-1 rounded-2xl bg-slate-100 p-1.5 dark:bg-slate-700">
            @foreach($projectTabs as $key => $label)
                <a href="{{ route('projects.index', array_filter(['tab' => $key, 'status' => $statusFilter])) }}"
                   class="rounded-xl px-4 py-2 text-sm font-medium transition-colors {{ $tab === $key
                       ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-800 dark:text-slate-100'
                       : 'text-slate-500 hover:bg-slate-200 hover:text-slate-800 dark:text-slate-300 dark:hover:bg-slate-600 dark:hover:text-slate-100' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('projects.index') }}" class="flex items-center gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <select name="status" onchange="this.form.submit()"
                    class="h-10 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                <option value="">{{ __('All statuses') }}</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->id }}" @selected((int) $statusFilter === (int) $status->id)>{{ $status->name }}</option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- Statistik --}}
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5 sm:p-6">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Total Projects') }}</p>
            <p class="mt-2 text-3xl font-bold text-slate-800 dark:text-slate-100 tabular-nums">{{ $totalProjects ?? 0 }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5 sm:p-6">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Active Projects (Open + Progress)') }}</p>
            <p class="mt-2 text-3xl font-bold text-green-600 dark:text-green-400 tabular-nums">{{ $activeProjects ?? 0 }}</p>
        </div>
    </div>

    {{-- Tabel --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200 whitespace-nowrap">{{ __('Project') }}</th>
                        <th class="px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200 whitespace-nowrap">{{ __('Customer') }}</th>
                        <th class="px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200 whitespace-nowrap">{{ __('Jenis Pekerjaan') }}</th>
                        {{-- <th class="px-4 py-4 text-left text-xs uppercase tracking-wider text-slate-500">PIC Engineer</th> --}}
                        <th class="px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200 whitespace-nowrap">{{ __('Status') }}</th>
                        <th class="px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200 whitespace-nowrap">{{ __('Progress') }}</th>
                        <th class="px-4 py-3.5 text-right text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200 whitespace-nowrap">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                    @forelse($projects as $project)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition group">
                            <td class="px-4 py-5 font-semibold text-slate-800 dark:text-slate-100 max-w-[220px] truncate">{{ $project->project_name }}</td>
                            <td class="px-4 py-5 text-slate-600 dark:text-slate-300 max-w-[180px] truncate">{{ $project->customer?->name ?? '-' }}</td>
                            <td class="px-4 py-5 text-slate-600 dark:text-slate-300 max-w-[160px] truncate">{{ $project->workType?->name ?? '-' }}</td>
                            {{-- <td class="px-4 py-4 text-slate-600">{{ $project->pic_engineer ?? '-' }}</td> --}}
                            <td class="px-4 py-5 whitespace-nowrap">
                                <x-status-badge :color="$statusBadgeColors[$project->status?->name] ?? 'slate'">
                                    {{ $project->status?->name ?? __('Not Started') }}
                                </x-status-badge>
                            </td>
                            <td class="px-4 py-5 whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-20 h-2 bg-slate-200 dark:bg-slate-600 rounded-full overflow-hidden">
                                        <div class="h-full bg-accent-600 rounded-full" style="width: {{ $project->progress ?? 0 }}%"></div>
                                    </div>
                                    <span class="text-xs font-medium text-slate-600 dark:text-slate-300 tabular-nums">{{ $project->progress ?? 0 }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-5 whitespace-nowrap">
                                <div class="flex justify-end gap-1.5 opacity-60 group-hover:opacity-100 transition-opacity">
                                    <x-icon-button as="a" icon="customer" href="{{ route('customers.show', $project->customer_id) }}" title="{{ __('View customer') }}" />
                                    <x-icon-button as="a" icon="view" href="{{ route('projects.show', $project) }}" title="{{ __('Project detail') }}" />
                                    @can('update', $project)
                                        <x-icon-button as="a" icon="edit" href="{{ route('projects.edit', $project) }}" title="{{ __('Edit project') }}" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-16 text-center text-slate-400 dark:text-slate-500">
                                {{ __('There are no active projects (Open / Progress).') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        <div class="mt-4">{{ $projects->links() }}</div>
        </div>
    </div>
</div>
</x-app-layout>