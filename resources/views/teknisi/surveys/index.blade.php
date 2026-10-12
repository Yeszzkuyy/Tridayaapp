<x-app-layout>
<div class="px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Daftar Survey') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Semua data survey untuk setiap project') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <x-icon-button as="a" href="{{ config('services.survey-report-template') }}" target="_blank" rel="noopener"
                           title="{{ __('Create Report') }}" aria-label="{{ __('Create Report') }}">
                <x-icon name="file-text" class="h-5 w-5" />
            </x-icon-button>
            <x-icon-button as="a" icon="add" href="{{ route('teknisi.surveys.create') }}" title="{{ __('Tambah Survey') }}" />
        </div>
    </div>

    {{-- Filter --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-4 mb-4 flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('teknisi.surveys.index') }}" class="flex flex-wrap items-center gap-3 w-full">
            <div class="flex items-center gap-2 min-w-48 flex-1">
                <label class="text-sm text-slate-500 whitespace-nowrap">Project:</label>
                <select name="project_id" class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500 text-sm">
                    <option value="">{{ __('Semua Project') }}</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }}>
                            {{ $project->project_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2 min-w-48 flex-1">
                <label class="text-sm text-slate-500 whitespace-nowrap">Status:</label>
                <select name="status" class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500 text-sm">
                    <option value="">{{ __('Semua Status') }}</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="scheduled" {{ request('status') === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                    <option value="on_survey" {{ request('status') === 'on_survey' ? 'selected' : '' }}>On Survey</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                </select>
            </div>
            <div class="flex gap-2">
                <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
                <x-icon-button as="a" icon="reset" href="{{ route('teknisi.surveys.index') }}" title="{{ __('Reset') }}" />
            </div>
        </form>
    </div>

    {{-- Tabel --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">Project</th>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">Customer</th>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Tanggal Survey') }}</th>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Lokasi') }}</th>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('PIC') }}</th>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">Status</th>
                        <th class="px-6 py-4 text-right text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                    @forelse($surveys as $survey)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition group">
                            <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-100">{{ $survey->project?->project_name ?? '-' }}</td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $survey->project?->customer?->name ?? '-' }}</td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $survey->survey_date ? \Carbon\Carbon::parse($survey->survey_date)->format('d M Y') : '-' }}</td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $survey->location ?? '-' }}</td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $survey->pic ?? '-' }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $statusColors = ['draft' => 'slate', 'scheduled' => 'blue', 'on_survey' => 'yellow', 'completed' => 'green'];
                                    $statusLabels = ['draft' => 'Draft', 'scheduled' => 'Scheduled', 'on_survey' => 'On Survey', 'completed' => 'Completed'];
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($survey->status === 'completed') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                                    @elseif($survey->status === 'on_survey') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300
                                    @elseif($survey->status === 'scheduled') bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300
                                    @else bg-slate-100 text-slate-800 dark:bg-slate-600 dark:text-slate-300 @endif">
                                    {{ $statusLabels[$survey->status] ?? $survey->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                                    @if($survey->hasReport())
                                        <a href="{{ route('teknisi.surveys.report.view', $survey) }}" target="_blank" rel="noopener"
                                           title="{{ __('View Report') }}" aria-label="{{ __('View Report') }}"
                                           class="p-2 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 transition">
                                            <x-icon name="eye" class="w-4 h-4" />
                                        </a>
                                        <a href="{{ route('teknisi.surveys.report.download', $survey) }}"
                                           title="{{ __('Download Report') }}" aria-label="{{ __('Download Report') }}"
                                           class="p-2 rounded-lg bg-green-100 hover:bg-green-200 text-green-700 transition">
                                            <x-icon name="file-text" class="w-4 h-4" />
                                        </a>
                                        @can('manage-technician')
                                            <button type="button" @click="$dispatch('report-upload', { id: {{ $survey->id }} })"
                                                    title="{{ __('Replace Report') }}" aria-label="{{ __('Replace Report') }}"
                                                    class="p-2 rounded-lg bg-blue-100 hover:bg-blue-200 text-blue-700 transition">
                                                <x-icon name="edit" class="w-4 h-4" />
                                            </button>
                                        @endcan
                                    @else
                                        <a href="{{ config('services.survey-report-template') }}" target="_blank" rel="noopener"
                                           title="{{ __('Create Report') }}" aria-label="{{ __('Create Report') }}"
                                           class="p-2 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 transition">
                                            <x-icon name="file-text" class="w-4 h-4" />
                                        </a>
                                        @can('manage-technician')
                                            <button type="button" @click="$dispatch('report-upload', { id: {{ $survey->id }} })"
                                                    title="{{ __('Upload PDF') }}" aria-label="{{ __('Upload PDF') }}"
                                                    class="p-2 rounded-lg bg-accent-100 hover:bg-accent-200 text-accent-700 transition">
                                                <x-icon name="folder" class="w-4 h-4" />
                                            </button>
                                        @endcan
                                    @endif
                                    <a href="{{ route('teknisi.surveys.show', $survey) }}"
                                       title="{{ __('Lihat') }}"
                                       class="p-2 rounded-lg bg-accent-50 hover:bg-accent-100 text-accent-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                    </a>
                                    <a href="{{ route('teknisi.surveys.edit', $survey) }}"
                                       title="Edit"
                                       class="p-2 rounded-lg bg-accent-100 hover:bg-accent-200 text-accent-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                        </svg>
                                    </a>
                                    <form action="{{ route('teknisi.surveys.destroy', $survey) }}" method="POST"
                                          x-data
                                          @submit.prevent="if(confirm('{{ __('Yakin ingin menghapus survey ini?') }}')) $el.submit()">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="{{ __('Hapus') }}"
                                                class="p-2 rounded-lg bg-red-100 hover:bg-red-200 text-red-700 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-16 text-center text-slate-400">
                                <p>{{ __('Belum ada data survey.') }}</p>
                                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('Buat survey dulu, lalu unggah laporan PDF dari barisnya.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        <div class="mt-4">{{ $surveys->links() }}</div>
        </div>
    </div>

    @include('teknisi.surveys._report-modal')
</div>
</x-app-layout>
