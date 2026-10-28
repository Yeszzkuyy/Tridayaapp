<x-app-layout>
<div class="px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Installation List') }}</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1">{{ __('Manage installation schedules and progress') }}</p>
        </div>
        <div class="flex items-center gap-2">
            @include('teknisi.instalasis._bast-dropdown')
            @can('manage-technician')
            <x-icon-button as="a" icon="add" href="{{ route('teknisi.instalasis.create') }}" label="{{ __('Add Installation') }}" title="{{ __('Add Installation') }}" />
            @endcan
        </div>
    </div>

    {{-- Filter --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-4 mb-4 flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('teknisi.instalasis.index') }}" class="flex flex-wrap items-center gap-3 w-full">
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ __('Status') }}</label>
                <select name="status" class="rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 text-sm focus:border-accent-500 focus:ring-accent-500">
                    <option value="">{{ __('All') }}</option>
                    @foreach(['scheduled' => __('Scheduled'), 'on_progress' => __('On Progress'), 'waiting' => __('Waiting'), 'completed' => __('Technically Complete'), 'cancelled' => __('Cancelled')] as $val => $lbl)
                        <option value="{{ $val }}" {{ request('status') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ __('Project') }}</label>
                <select name="project_id" class="rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 text-sm focus:border-accent-500 focus:ring-accent-500">
                    <option value="">{{ __('All') }}</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }}>
                            {{ $project->project_name }} — {{ $project->customer?->name ?? '-' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
            @if(request()->hasAny(['status', 'project_id']))
                <x-icon-button as="a" icon="reset" href="{{ route('teknisi.instalasis.index') }}" title="{{ __('Reset') }}" />
            @endif
        </form>
    </div>

    {{-- Tabel --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Project') }}</th>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Customer') }}</th>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Location') }}</th>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Technician PIC') }}</th>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Jadwal') }}</th>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Status') }}</th>
                        <th class="px-6 py-4 text-right text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                    @forelse($instalasis as $instalasi)
                        @php
                            $statusColors = [
                                'scheduled' => 'blue',
                                'on_progress' => 'yellow',
                                'waiting' => 'orange',
                                'completed' => 'green',
                                'cancelled' => 'slate',
                            ];
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition group">
                            <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-100">
                                {{ $instalasi->project?->project_name ?? '-' }}
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                {{ $instalasi->project?->customer?->name ?? '-' }}
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $instalasi->location ?? '-' }}</td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $instalasi->technician_pic ?? '-' }}</td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                {{ $instalasi->schedule_date?->format('d M Y') ?? '-' }}
                            </td>
                            <td class="px-6 py-4">
                                <x-status-badge :color="$statusColors[$instalasi->status] ?? 'slate'">
                                    {{ str($instalasi->status)->replace('_', ' ')->title() }}
                                </x-status-badge>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                                    <x-icon-button as="a" icon="view" href="{{ route('teknisi.instalasis.show', $instalasi) }}" title="{{ __('Detail') }}" />
                                    @can('manage-technician')
                                    <x-icon-button as="a" icon="edit" href="{{ route('teknisi.instalasis.edit', $instalasi) }}" title="{{ __('Edit') }}" />
                                    <form action="{{ route('teknisi.instalasis.destroy', $instalasi) }}" method="POST"
                                          onsubmit="return confirm('{{ __('Delete this installation?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-button icon="delete" type="submit" title="{{ __('Delete') }}" />
                                    </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-16 text-center text-slate-400 dark:text-slate-500">
                                {{ __('No installation data yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        <div class="mt-4">{{ $instalasis->links() }}</div>
        </div>
    </div>
</div>
</x-app-layout>
