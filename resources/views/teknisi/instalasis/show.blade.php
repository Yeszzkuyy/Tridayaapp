<x-app-layout>
<div class="px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Detail Instalasi') }}</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1">{{ $instalasi->project?->project_name ?? '-' }}</p>
        </div>
        <div class="flex gap-2">
            @include('teknisi.instalasis._bast-dropdown')
            @can('manage-technician')
            <x-icon-button as="a" icon="edit" href="{{ route('teknisi.instalasis.edit', $instalasi) }}" title="{{ __('Edit') }}" />
            @endcan
            <x-icon-button as="a" icon="back" href="{{ route('teknisi.instalasis.index') }}" title="{{ __('Kembali') }}" />
        </div>
    </div>

    @php
        $statusColors = [
            'scheduled' => 'blue',
            'on_progress' => 'yellow',
            'waiting' => 'orange',
            'completed' => 'green',
            'cancelled' => 'slate',
        ];
    @endphp

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div>
                <p class="text-xs text-slate-400">{{ __('Project') }}</p>
                <p class="font-medium text-slate-800 dark:text-slate-100">{{ $instalasi->project?->project_name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">{{ __('Customer') }}</p>
                <p class="font-medium text-slate-800 dark:text-slate-100">{{ $instalasi->project?->customer?->name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">{{ __('Status') }}</p>
                <x-status-badge :color="$statusColors[$instalasi->status] ?? 'slate'">
                    {{ str($instalasi->status ?? '-')->replace('_', ' ')->title() }}
                </x-status-badge>
            </div>
            <div>
                <p class="text-xs text-slate-400">{{ __('Lokasi') }}</p>
                <p class="font-medium text-slate-800 dark:text-slate-100">{{ $instalasi->location ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">{{ __('PIC Teknisi') }}</p>
                <p class="font-medium text-slate-800 dark:text-slate-100">{{ $instalasi->technician_pic ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">{{ __('Jadwal') }}</p>
                <p class="font-medium text-slate-800 dark:text-slate-100">{{ $instalasi->schedule_date?->format('d M Y') ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">{{ __('Job Status') }}</p>
                <p class="font-medium text-slate-800 dark:text-slate-100">{{ $instalasi->job_status ?? '-' }}</p>
            </div>
        </div>

        @if($instalasi->hasReport())
            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                <p class="text-xs text-slate-400 mb-2">{{ __('Laporan Instalasi') }}</p>
                <div class="flex items-center gap-2">
                    <x-icon-button as="a" icon="view" href="{{ route('teknisi.instalasis.report.view', $instalasi) }}" target="_blank" rel="noopener" title="{{ __('View') }}" label="{{ __('View') }}" />
                    <x-icon-button as="a" icon="download" href="{{ route('teknisi.instalasis.report.download', $instalasi) }}" title="{{ __('Download') }}" label="{{ __('Download') }}" />
                    <span class="text-sm text-slate-600 dark:text-slate-300 truncate">{{ $instalasi->report_file_name }}</span>
                </div>
            </div>
        @elseif($instalasi->installation_report)
            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                <p class="text-xs text-slate-400 mb-1">{{ __('Laporan Instalasi') }}</p>
                <p class="text-slate-700 dark:text-slate-200 whitespace-pre-wrap">{{ $instalasi->installation_report }}</p>
            </div>
        @endif

        @if($instalasi->checklist && count($instalasi->checklist))
            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                <p class="text-xs text-slate-400 mb-2">{{ __('Checklist') }}</p>
                <ul class="space-y-1">
                    @foreach($instalasi->checklist as $item)
                        <li class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            {{ is_array($item) ? json_encode($item) : $item }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($instalasi->documentation && count($instalasi->documentation))
            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                <p class="text-xs text-slate-400 mb-2">{{ __('Dokumentasi') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($instalasi->documentation as $doc)
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-slate-100 text-xs font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                            {{ is_array($doc) ? json_encode($doc) : $doc }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
</x-app-layout>
