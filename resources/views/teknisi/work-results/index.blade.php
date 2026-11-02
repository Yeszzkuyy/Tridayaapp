<x-app-layout>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Work Results') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Survey and installation results you can read without technician access.') }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ url()->previous() }}" title="{{ __('Back') }}" />
    </div>

    <form method="GET" action="{{ route('teknisi.work-results.index') }}" class="mb-4 flex flex-wrap items-end gap-2">
        <div>
            <label for="q" class="block text-xs text-slate-500 mb-1">{{ __('Search survey title') }}</label>
            <input id="q" type="text" name="q" value="{{ $filters['q'] ?? '' }}"
                   class="w-64 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
        </div>
        <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
        <x-icon-button as="a" icon="reset" href="{{ route('teknisi.work-results.index') }}" title="{{ __('Reset') }}" />
    </form>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <section class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-600 overflow-hidden">
            <h2 class="px-5 py-3 border-b border-slate-200 dark:border-slate-600 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('Survey Results') }}</h2>
            <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($surveys as $survey)
                    <li class="px-5 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-slate-800 dark:text-slate-100 truncate">{{ $survey->title }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $survey->customer?->name ?? '-' }}
                                    • {{ $survey->survey_date?->format('d M Y') ?? '-' }}
                                    • {{ \Illuminate\Support\Str::headline($survey->status) }}
                                </p>
                            </div>
                            @if($canReadTechnicianPages)
                                <x-icon-button as="a" icon="view" href="{{ route('teknisi.surveys.show', $survey) }}" title="{{ __('View details') }}" />
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-slate-400">{{ __('No survey results yet.') }}</li>
                @endforelse
            </ul>
        </section>

        <section class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-600 overflow-hidden">
            <h2 class="px-5 py-3 border-b border-slate-200 dark:border-slate-600 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('Installation Results') }}</h2>
            <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($instalasis as $instalasi)
                    <li class="px-5 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-slate-800 dark:text-slate-100 truncate">{{ $instalasi->project?->project_name ?? '-' }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $instalasi->project?->customer?->name ?? '-' }}
                                    • {{ $instalasi->schedule_date?->format('d M Y') ?? '-' }}
                                    • {{ $instalasi->status }}
                                </p>
                            </div>
                            @if($canReadTechnicianPages)
                                <x-icon-button as="a" icon="view" href="{{ route('teknisi.instalasis.show', $instalasi) }}" title="{{ __('View details') }}" />
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-slate-400">{{ __('No installation results yet.') }}</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-app-layout>
