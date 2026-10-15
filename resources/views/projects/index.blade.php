<x-app-layout>
<div class="max-w-[1400px] mx-auto space-y-8">
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Daftar Project</h1>
            <p class="text-slate-500 mt-1">Semua project, termasuk yang selesai atau di-hold</p>
        </div>
        <div class="flex gap-2">
            @can('manage-technician')
                <x-icon-button look="soft" as="a" icon="add" href="{{ route('projects.create') }}" title="{{ __('Tambah Project') }}" />
            @endcan
        </div>
    </div>

    {{-- Statistik --}}
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5 sm:p-6">
            <p class="text-sm text-slate-500">Total Project</p>
            <p class="mt-2 text-3xl font-bold text-slate-800 tabular-nums">{{ $totalProjects ?? 0 }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5 sm:p-6">
            <p class="text-sm text-slate-500">Project Aktif (Open + Progress)</p>
            <p class="mt-2 text-3xl font-bold text-green-600 tabular-nums">{{ $activeProjects ?? 0 }}</p>
        </div>
    </div>

    {{-- Tabel --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200 whitespace-nowrap">Project</th>
                        <th class="px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200 whitespace-nowrap">Customer</th>
                        <th class="px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200 whitespace-nowrap">Jenis Pekerjaan</th>
                        {{-- <th class="px-4 py-4 text-left text-xs uppercase tracking-wider text-slate-500">PIC Engineer</th> --}}
                        <th class="px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200 whitespace-nowrap">Status</th>
                        <th class="px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200 whitespace-nowrap">Progress</th>
                        <th class="px-4 py-3.5 text-right text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200 whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                    @forelse($projects as $project)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition group">
                            <td class="px-4 py-5 font-semibold text-slate-800 max-w-[220px] truncate">{{ $project->project_name }}</td>
                            <td class="px-4 py-5 text-slate-600 max-w-[180px] truncate">{{ $project->customer?->name ?? '-' }}</td>
                            <td class="px-4 py-5 text-slate-600 max-w-[160px] truncate">{{ $project->workType?->name ?? '-' }}</td>
                            {{-- <td class="px-4 py-4 text-slate-600">{{ $project->pic_engineer ?? '-' }}</td> --}}
                            <td class="px-4 py-5 whitespace-nowrap">
                                <x-status-badge :color="$statusBadgeColors[$project->status?->name] ?? 'slate'">
                                    {{ $project->status?->name ?? 'Belum Memulai' }}
                                </x-status-badge>
                            </td>
                            <td class="px-4 py-5 whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-20 h-2 bg-slate-200 dark:bg-slate-600 rounded-full overflow-hidden">
                                        <div class="h-full bg-accent-600 rounded-full" style="width: {{ $project->progress ?? 0 }}%"></div>
                                    </div>
                                    <span class="text-xs font-medium text-slate-600 tabular-nums">{{ $project->progress ?? 0 }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-5 whitespace-nowrap">
                                <div class="flex justify-end gap-1.5 opacity-60 group-hover:opacity-100 transition-opacity">
                                    <x-icon-button look="soft" as="a" icon="view" href="{{ route('customers.show', $project->customer_id) }}" title="{{ __('Lihat customer') }}" />
                                    <x-icon-button look="soft" as="a" icon="view" href="{{ route('projects.show', $project) }}" title="{{ __('Detail project') }}" />
                                    @can('manage-technician')
                                        <x-icon-button look="soft" as="a" icon="edit" href="{{ route('projects.edit', $project) }}" title="{{ __('Edit project') }}" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-16 text-center text-slate-400">
                                Belum ada project yang aktif (Open / Progress).
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