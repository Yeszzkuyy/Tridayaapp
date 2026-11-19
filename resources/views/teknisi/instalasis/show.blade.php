<x-app-layout>
<div class="px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Installation Detail') }}</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1">{{ $instalasi->project?->project_name ?? '-' }}</p>
        </div>
        <div class="flex gap-2">
            @include('teknisi.instalasis._bast-dropdown')
            @can('manage-technician')
            <x-icon-button as="a" icon="edit" href="{{ route('teknisi.instalasis.edit', $instalasi) }}" title="{{ __('Edit') }}" />
            @endcan
            <x-icon-button as="a" icon="back" href="{{ auth()->user()->can('view-technician') ? route('teknisi.instalasis.index') : route('admin.bast.index') }}" title="{{ __('Back') }}" />
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
                <p class="text-xs text-slate-400">{{ __('Location') }}</p>
                <p class="font-medium text-slate-800 dark:text-slate-100">{{ $instalasi->location ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">{{ __('Technician PIC') }}</p>
                <p class="font-medium text-slate-800 dark:text-slate-100">{{ $instalasi->technician_pic ?? '-' }}</p>
            </div>
            @if($instalasi->technicalRequest?->supportUsers->isNotEmpty())
                <div>
                    <p class="text-xs text-slate-400">{{ __('Support Technicians') }}</p>
                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $instalasi->technicalRequest->supportUsers->pluck('name')->implode(', ') }}</p>
                </div>
            @endif
            <div>
                <p class="text-xs text-slate-400">{{ __('Jadwal') }}</p>
                <p class="font-medium text-slate-800 dark:text-slate-100">{{ $instalasi->schedule_date?->format('d M Y') ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">{{ __('Job Status') }}</p>
                <p class="font-medium text-slate-800 dark:text-slate-100">{{ $instalasi->job_status ?? '-' }}</p>
            </div>
            @if($instalasi->completed_at)
                <div>
                    <p class="text-xs text-slate-400">{{ __('Technically Complete') }}</p>
                    <p class="font-medium text-green-600 dark:text-green-400">{{ $instalasi->completed_at->format('d M Y H:i') }}</p>
                    <p class="text-xs text-slate-500">{{ $instalasi->completedBy?->name ?? '-' }}</p>
                </div>
            @endif
        </div>

        @if($instalasi->hasReport())
            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                <p class="text-xs text-slate-400 mb-2">{{ __('Installation Report') }}</p>
                <div class="flex items-center gap-2">
                    <x-file-preview :url="auth()->user()->can('view-technician') ? route('teknisi.instalasis.report.view', $instalasi) : route('admin.instalasi-bast.report', $instalasi)"
                                    :download="auth()->user()->can('view-technician') ? route('teknisi.instalasis.report.download', $instalasi) : route('admin.instalasi-bast.report.download', $instalasi)"
                                    :name="$instalasi->report_file_name ?: basename((string) $instalasi->report_file)"
                                    label="{{ __('View') }}" />
                    <x-icon-button as="a" icon="download" href="{{ auth()->user()->can('view-technician') ? route('teknisi.instalasis.report.download', $instalasi) : route('admin.instalasi-bast.report.download', $instalasi) }}" title="{{ __('Download') }}" label="{{ __('Download') }}" />
                    <span class="text-sm text-slate-600 dark:text-slate-300 truncate">{{ $instalasi->report_file_name }}</span>
                </div>
            </div>
        @elseif($instalasi->installation_report)
            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                <p class="text-xs text-slate-400 mb-1">{{ __('Installation Report') }}</p>
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
                <p class="text-xs text-slate-400 mb-2">{{ __('Documentation') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($instalasi->documentation as $doc)
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-slate-100 text-xs font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                            {{ is_array($doc) ? json_encode($doc) : $doc }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- BAST: wajib sebelum pekerjaan instalasi selesai --}}
        <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-700">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-xs text-slate-400">{{ __('BAST') }}</p>
                    <p class="text-sm font-semibold {{ $instalasi->isBastReady() ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">
                        {{ $instalasi->isBastReady() ? __('Signed by both parties') : __('Waiting for BAST') }}
                    </p>
                </div>
                @if($instalasi->bastDocument)
                    <x-file-preview :url="route('project-documents.preview', $instalasi->bastDocument)"
                                    :download="route('project-documents.download', $instalasi->bastDocument)"
                                    :name="$instalasi->bastDocument->file_name"
                                    label="{{ __('View BAST') }}" />
                @endif
            </div>

            @if($instalasi->project_id)
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-300">
                    {{ __('This installation belongs to a project. The official BAST is managed in the Admin BAST menu.') }}
                    @if(auth()->user()->can('manage-admin'))
                        <a href="{{ route('admin.bast.edit', $instalasi->project_id) }}" class="ml-1 font-semibold text-accent-600 hover:underline dark:text-accent-400">{{ __('Open BAST') }}</a>
                    @endif
                </div>
            @elseif(auth()->user()->can('manage-technician') || auth()->user()->can('manage-admin'))
                <form action="{{ route('teknisi.instalasis.bast', $instalasi) }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">{{ __('BAST Number') }}</label>
                        <input type="text" name="bast_number" value="{{ old('bast_number', $instalasi->bast_number) }}"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-700">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">{{ __('BAST Date') }}</label>
                        <input type="date" name="bast_date" value="{{ old('bast_date', $instalasi->bast_date?->format('Y-m-d')) }}"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-700">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">{{ __('3DY Signer') }}</label>
                        <input type="text" name="bast_signed_3dy_name" value="{{ old('bast_signed_3dy_name', $instalasi->bast_signed_3dy_name) }}"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-700">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">{{ __('3DY Signed At') }}</label>
                        <input type="date" name="bast_signed_3dy_at" value="{{ old('bast_signed_3dy_at', $instalasi->bast_signed_3dy_at?->format('Y-m-d')) }}"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-700">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">{{ __('Customer Signer') }}</label>
                        <input type="text" name="bast_signed_customer_name" value="{{ old('bast_signed_customer_name', $instalasi->bast_signed_customer_name) }}"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-700">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">{{ __('Customer Signed At') }}</label>
                        <input type="date" name="bast_signed_customer_at" value="{{ old('bast_signed_customer_at', $instalasi->bast_signed_customer_at?->format('Y-m-d')) }}"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-700">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs text-slate-500">{{ __('Signed BAST File') }}</label>
                        <input type="file" name="bast_file" accept=".pdf,.doc,.docx"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-accent-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white dark:border-slate-600 dark:bg-slate-700">
                        @error('bast_file') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2 flex justify-end">
                        <x-primary-button>{{ __('Save BAST') }}</x-primary-button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
