<x-app-layout>
@php
    $statuses = [
        'draft' => 'Draft',
        'in_progress' => 'In Progress',
        'waiting_approval' => 'Waiting Approval',
        'completed' => 'Completed',
    ];

    $statusColors = [
        'draft' => 'slate',
        'in_progress' => 'yellow',
        'waiting_approval' => 'orange',
        'completed' => 'green',
    ];
@endphp

<div class="px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">Sizing Projects</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1">{{ __('Kelola hasil sizing / usulan solusi per project') }}</p>
        </div>
        <x-icon-button as="a" icon="add" href="{{ route('teknisi.sizing-projects.create') }}" label="{{ __('+ Tambah Sizing') }}" title="{{ __('+ Tambah Sizing') }}" />
    </div>

    @if (session('success'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filter --}}
    <form method="GET" action="{{ route('teknisi.sizing-projects.index') }}"
          class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-4 mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Status</label>
            <select name="status"
                    class="rounded-xl border-slate-300 dark:bg-slate-900 dark:border-slate-600 dark:text-slate-100 text-sm focus:border-accent-500 focus:ring-accent-500">
                <option value="">{{ __('Semua Status') }}</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Project</label>
            <select name="project_id"
                    class="rounded-xl border-slate-300 dark:bg-slate-900 dark:border-slate-600 dark:text-slate-100 text-sm focus:border-accent-500 focus:ring-accent-500">
                <option value="">{{ __('Semua Project') }}</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>
                        {{ $project->project_name }}
                        @if ($project->customer) — {{ $project->customer->name }} @endif
                    </option>
                @endforeach
            </select>
        </div>

        <x-icon-button icon="filter" type="submit" title="Filter" />

        @if (request()->filled('status') || request()->filled('project_id'))
            <x-icon-button as="a" icon="reset" href="{{ route('teknisi.sizing-projects.index') }}" title="Reset" />
        @endif
    </form>

    {{-- Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        @php
                            $headers = ['Project', 'Customer', 'Sales PIC', 'Quantity', 'Status', __('Aksi')];
                        @endphp
                        @foreach ($headers as $header)
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-600">
                    @forelse ($sizings as $sizing)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                            <td class="px-4 py-3 text-sm text-slate-800 dark:text-slate-100">
                                {{ $sizing->project->project_name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                {{ $sizing->project?->customer?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                {{ $sizing->sales_pic ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                {{ $sizing->quantity ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <x-status-badge color="{{ $statusColors[$sizing->status] ?? 'slate' }}">
                                    {{ $statuses[$sizing->status] ?? $sizing->status }}
                                </x-status-badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <x-icon-button as="a" icon="view" href="{{ route('teknisi.sizing-projects.show', $sizing) }}" title="{{ __('Lihat') }}" />
                                    <x-icon-button as="a" icon="edit" href="{{ route('teknisi.sizing-projects.edit', $sizing) }}" title="Edit" />
                                    <form action="{{ route('teknisi.sizing-projects.destroy', $sizing) }}" method="POST"
                                          onsubmit="return confirm('{{ __('Hapus sizing project ini?') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-button icon="delete" type="submit" title="{{ __('Hapus') }}" />
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state label="sizing project" description="{{ __('Belum ada data sizing.') }}" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        <div class="mt-4">{{ $sizings->links() }}</div>
        </div>
    </div>
</div>
</x-app-layout>