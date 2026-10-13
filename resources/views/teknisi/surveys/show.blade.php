<x-app-layout>
<div class="px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Detail Survey') }}</h1>
            <p class="text-slate-500 mt-1">{{ $survey->project?->project_name ?? '-' }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('teknisi.surveys.edit', $survey) }}"
               class="px-5 py-2.5 rounded-xl bg-accent-100 hover:bg-accent-200 text-accent-700 font-medium transition">
                Edit
            </a>
            <a href="{{ route('teknisi.surveys.index') }}"
               class="px-5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-700 font-medium transition">
                {{ __('Kembali') }}
            </a>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
        <div class="space-y-6">
            {{-- Status Badge --}}
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-slate-500">Status:</span>
                @php
                    $statusLabels = ['draft' => 'Draft', 'scheduled' => 'Scheduled', 'on_survey' => 'On Survey', 'completed' => 'Completed'];
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                    @if($survey->status === 'completed') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                    @elseif($survey->status === 'on_survey') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300
                    @elseif($survey->status === 'scheduled') bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300
                    @else bg-slate-100 text-slate-800 dark:bg-slate-600 dark:text-slate-300 @endif">
                    {{ $statusLabels[$survey->status] ?? $survey->status }}
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                {{-- Project --}}
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">Project</p>
                    <p class="text-slate-800 dark:text-slate-100 font-semibold">{{ $survey->project?->project_name ?? '-' }}</p>
                </div>

                {{-- Customer --}}
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">Customer</p>
                    <p class="text-slate-800 dark:text-slate-100">{{ $survey->project?->customer?->name ?? '-' }}</p>
                </div>

                {{-- Tanggal Survey --}}
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">{{ __('Tanggal Survey') }}</p>
                    <p class="text-slate-800 dark:text-slate-100">{{ $survey->survey_date ? \Carbon\Carbon::parse($survey->survey_date)->format('d M Y') : '-' }}</p>
                </div>

                {{-- Lokasi --}}
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">{{ __('Lokasi') }}</p>
                    <p class="text-slate-800 dark:text-slate-100">{{ $survey->location ?? '-' }}</p>
                </div>

                {{-- PIC --}}
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">{{ __('PIC') }}</p>
                    <p class="text-slate-800 dark:text-slate-100">{{ $survey->pic ?? '-' }}</p>
                </div>
            </div>

            <hr class="border-slate-200 dark:border-slate-600">

            {{-- Sales Request --}}
            <div>
                <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">Sales Request</p>
                <p class="text-slate-800 dark:text-slate-100 whitespace-pre-wrap">{{ $survey->sales_request ?: '-' }}</p>
            </div>

            {{-- Survey Data --}}
            <div>
                <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">{{ __('Data Survey') }}</p>
                <p class="text-slate-800 dark:text-slate-100 whitespace-pre-wrap">{{ $survey->survey_data ?: '-' }}</p>
            </div>

            {{-- Survey Report --}}
            <div>
                <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">{{ __('Laporan Survey') }}</p>
                <p class="text-slate-800 dark:text-slate-100 whitespace-pre-wrap">{{ $survey->survey_report ?: '-' }}</p>
            </div>

            {{-- Notes --}}
            <div>
                <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">{{ __('Catatan') }}</p>
                <p class="text-slate-800 dark:text-slate-100 whitespace-pre-wrap">{{ $survey->notes ?: '-' }}</p>
            </div>

            <hr class="border-slate-200 dark:border-slate-600">

            {{-- Laporan Survey (PDF) --}}
            <div>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs uppercase tracking-wider text-slate-400">{{ __('Survey Report File') }}</p>
                    <div class="flex items-center gap-2">
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
                                <button type="button" x-data="" @click="$dispatch('report-upload', { id: {{ $survey->id }} })"
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
                                <button type="button" x-data="" @click="$dispatch('report-upload', { id: {{ $survey->id }} })"
                                        title="{{ __('Upload PDF') }}" aria-label="{{ __('Upload PDF') }}"
                                        class="p-2 rounded-lg bg-accent-100 hover:bg-accent-200 text-accent-700 transition">
                                    <x-icon name="folder" class="w-4 h-4" />
                                </button>
                            @endcan
                        @endif
                    </div>
                </div>
                <p class="mt-1 text-slate-800 dark:text-slate-100">
                    {{ $survey->hasReport() ? __('Report uploaded.') : __('No report uploaded yet.') }}
                </p>
            </div>
        </div>
    </div>

    @include('teknisi.surveys._report-modal')
</div>
</x-app-layout>
