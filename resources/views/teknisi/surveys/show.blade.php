<x-app-layout>
<div class="px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Detail Survey') }}</h1>
            <p class="text-slate-500 mt-1 max-w-[560px] truncate" title="{{ $survey->title ?? '' }}">{{ $survey->title ?? '-' }}</p>
        </div>
        <div class="flex gap-2">
            <x-icon-button as="a" icon="edit" href="{{ route('teknisi.surveys.edit', $survey) }}" title="{{ __('Edit') }}" />
            <x-icon-button as="a" icon="back" href="{{ route('teknisi.surveys.index') }}" title="{{ __('Kembali') }}" />
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
        <div class="space-y-6">
            {{-- Status Badge --}}
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-slate-500">{{ __('Status') }}:</span>
                @php
                    $statusLabels = ['draft' => __('Draft'), 'scheduled' => __('Scheduled'), 'on_survey' => __('On Survey'), 'completed' => __('Completed')];
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
                {{-- Judul --}}
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">{{ __('Judul') }}</p>
                    <p class="text-slate-800 dark:text-slate-100 font-semibold break-words">{{ $survey->title ?? '-' }}</p>
                </div>

                {{-- Customer --}}
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">{{ __('Customer') }}</p>
                    <p class="text-slate-800 dark:text-slate-100 break-words">{{ $survey->customer?->name ?? '-' }}</p>
                </div>

                {{-- Sales --}}
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">{{ __('Sales') }}</p>
                    <p class="text-slate-800 dark:text-slate-100 break-words">{{ $survey->sales?->name ?? '-' }}</p>
                </div>

                {{-- Tanggal Survey --}}
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">{{ __('Tanggal Survey') }}</p>
                    <p class="text-slate-800 dark:text-slate-100">{{ $survey->survey_date ? \Carbon\Carbon::parse($survey->survey_date)->format('d M Y') : '-' }}</p>
                </div>

                {{-- Lokasi --}}
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">{{ __('Lokasi') }}</p>
                    <p class="text-slate-800 dark:text-slate-100 break-words">{{ $survey->location ?? '-' }}</p>
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
                <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">{{ __('Sales Request') }}</p>
                <p class="text-slate-800 dark:text-slate-100 whitespace-pre-wrap break-words">{{ $survey->sales_request ?: '-' }}</p>
            </div>

            {{-- Survey Data --}}
            <div>
                <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">{{ __('Data Survey') }}</p>
                <p class="text-slate-800 dark:text-slate-100 whitespace-pre-wrap break-words">{{ $survey->survey_data ?: '-' }}</p>
            </div>

            {{-- Survey Report --}}
            <div>
                <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">{{ __('Laporan Survey') }}</p>
                <p class="text-slate-800 dark:text-slate-100 whitespace-pre-wrap break-words">{{ $survey->survey_report ?: '-' }}</p>
            </div>

            {{-- Notes --}}
            <div>
                <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">{{ __('Catatan') }}</p>
                <p class="text-slate-800 dark:text-slate-100 whitespace-pre-wrap break-words">{{ $survey->notes ?: '-' }}</p>
            </div>

            <hr class="border-slate-200 dark:border-slate-600">

            {{-- Laporan Survey (PDF) --}}
            <div class="overflow-hidden rounded-2xl border border-accent-200 bg-accent-50/60 dark:border-accent-500/30 dark:bg-accent-500/10">
                <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 flex-1 items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-accent-600 text-white shadow-lg shadow-accent-500/20">
                            <x-icon name="file-text" class="h-6 w-6" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wider text-accent-700 dark:text-accent-300">{{ __('Survey Report File') }}</p>
                            @php $reportName = $survey->report_file_name ?: basename((string) $survey->report_file); @endphp
                            @if($survey->hasReport())
                                <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ __('Report ready to review') }}</p>
                                <p class="mt-1 inline-flex max-w-full items-center gap-1.5 rounded-lg border border-accent-200 bg-white/70 px-2.5 py-1 text-xs font-medium text-slate-600 dark:border-accent-500/30 dark:bg-slate-800/70 dark:text-slate-300" title="{{ basename($reportName) }}">
                                    <x-icon name="file-text" class="h-3.5 w-3.5 shrink-0" />
                                    <span class="truncate">{{ basename($reportName) }}</span>
                                </p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('The private PDF report is stored securely.') }}</p>
                            @else
                                <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ __('No PDF report yet') }}</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400 break-words">{{ __('Create the report from the template, then upload the completed PDF here.') }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2 self-end sm:self-center">
                        @if($survey->hasReport())
                            <x-icon-button as="a" icon="view" href="{{ route('teknisi.surveys.report.view', $survey) }}" target="_blank" rel="noopener"
                                           title="{{ __('View') }}" label="{{ __('View') }}" />
                            <x-icon-button as="a" icon="download" href="{{ route('teknisi.surveys.report.download', $survey) }}"
                                           title="{{ __('Download') }}" label="{{ __('Download') }}" />
                            @can('manage-technician')
                                <x-icon-button icon="edit" title="{{ __('Replace') }}" label="{{ __('Replace') }}"
                                               onclick="(function(){var c=document.querySelector('[data-survey-report-modal]'),r=c&&c.querySelector('.fixed.inset-0.z-50'),h=c&&c.querySelector('[data-report-survey-id]'),f=c&&c.querySelector('[data-report-upload-form]'),id='{{ $survey->id }}';if(!c||!r)return;if(h)h.value=id;if(f)f.action=c.dataset.uploadUrl.replace('__SURVEY__',id);r.style.display='block';r.querySelectorAll('[x-show]').forEach(function(e){e.style.display='block'});window.dispatchEvent(new CustomEvent('report-upload',{detail:{id:id}}))})()" />
                            @endcan
                        @else
                            <x-icon-button as="a" href="{{ config('services.survey-report-template') }}" target="_blank" rel="noopener"
                                           title="{{ __('Create') }}" label="{{ __('Create') }}">
                                <x-icon name="file-text" class="h-4 w-4" />
                            </x-icon-button>
                            @can('manage-technician')
                                <x-icon-button icon="upload" title="{{ __('Upload') }}" label="{{ __('Upload') }}"
                                               onclick="(function(){var c=document.querySelector('[data-survey-report-modal]'),r=c&&c.querySelector('.fixed.inset-0.z-50'),h=c&&c.querySelector('[data-report-survey-id]'),f=c&&c.querySelector('[data-report-upload-form]'),id='{{ $survey->id }}';if(!c||!r)return;if(h)h.value=id;if(f)f.action=c.dataset.uploadUrl.replace('__SURVEY__',id);r.style.display='block';r.querySelectorAll('[x-show]').forEach(function(e){e.style.display='block'});window.dispatchEvent(new CustomEvent('report-upload',{detail:{id:id}}))})()" />
                            @endcan
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('teknisi.surveys._report-modal')
</div>
</x-app-layout>
