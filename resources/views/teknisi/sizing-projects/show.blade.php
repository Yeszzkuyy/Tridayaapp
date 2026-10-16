<x-app-layout>
@php
    $statuses = [
        'draft' => __('Draft'),
        'in_progress' => __('In Progress'),
        'waiting_approval' => __('Waiting Approval'),
        'completed' => __('Completed'),
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
        <div class="flex items-center gap-3">
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Sizing Project') }}</h1>
            <x-status-badge color="{{ $statusColors[$sizingProject->status] ?? 'slate' }}">
                {{ $statuses[$sizingProject->status] ?? $sizingProject->status }}
            </x-status-badge>
        </div>
        <div class="flex items-center gap-2">
            <x-icon-button as="a" icon="edit" href="{{ route('teknisi.sizing-projects.edit', $sizingProject) }}" title="{{ __('Edit') }}" />
            <x-icon-button as="a" icon="back" href="{{ route('teknisi.sizing-projects.index') }}" title="{{ __('Back') }}" />
        </div>
    </div>

    @if (session('success'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
            <div>
                <dt class="text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Project') }}</dt>
                <dd class="mt-1 text-sm text-slate-800 dark:text-slate-100">{{ $sizingProject->project->project_name ?? '—' }}</dd>
            </div>

            <div>
                <dt class="text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Customer') }}</dt>
                <dd class="mt-1 text-sm text-slate-800 dark:text-slate-100">{{ $sizingProject->project?->customer?->name ?? '—' }}</dd>
            </div>

            <div>
                <dt class="text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Sales PIC') }}</dt>
                <dd class="mt-1 text-sm text-slate-800 dark:text-slate-100">{{ $sizingProject->sales_pic ?? '—' }}</dd>
            </div>

            <div>
                <dt class="text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Quantity') }}</dt>
                <dd class="mt-1 text-sm text-slate-800 dark:text-slate-100">{{ $sizingProject->quantity ?? '—' }}</dd>
            </div>
        </dl>

        <div class="grid grid-cols-1 gap-4 mt-6">
            @php
                $sections = [
                    __('Customer Needs') => $sizingProject->customer_needs,
                    __('Recommendation') => $sizingProject->recommendation,
                    __('Specification') => $sizingProject->specifications,
                    __('Topology') => $sizingProject->topology,
                    __('Technical Notes') => $sizingProject->technical_notes,
                    __('Notes') => $sizingProject->notes,
                ];
            @endphp

            @foreach ($sections as $label => $value)
                <div>
                    <dt class="text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ $label }}</dt>
                    <dd class="mt-1 text-sm text-slate-800 dark:text-slate-100 whitespace-pre-line">
                        {{ $value ?: '—' }}
                    </dd>
                </div>
            @endforeach
        </div>
    </div>
</div>
</x-app-layout>