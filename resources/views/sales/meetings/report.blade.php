<x-app-layout>
    @php
        $followUp = $meeting ?? $followUp;
        $backUrl = $returnTo === 'schedule'
            ? route('sales.schedules.index', $returnDate ? ['date' => $returnDate] : [])
            : route('sales.meetings.index');
    @endphp
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <nav class="mb-1 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                    <a href="{{ route('sales.schedules.index') }}" class="hover:text-accent-600 dark:hover:text-accent-400">{{ __('Sales Schedules') }}</a>
                    <span>/</span>
                    <a href="{{ route('sales.meetings.index') }}" class="hover:text-accent-600 dark:hover:text-accent-400">{{ __('Meeting') }}</a>
                    <span>/</span>
                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ __('Daily Report') }}</span>
                </nav>
                <h2 class="flex items-center gap-2 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                    <svg class="h-7 w-7 text-accent-600 dark:text-accent-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>{{ __('Send Daily Report') }}</span>
                </h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Review the meeting data below. No need to retype anything.') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <x-icon-button as="a" icon="back" href="{{ $backUrl }}" title="{{ __('Back') }}" />
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
        {{-- Konteks meeting --}}
        <div class="mb-6 rounded-2xl border border-accent-200/60 bg-gradient-to-r from-accent-50 via-white to-sky-50 p-5 shadow-sm dark:border-slate-700/70 dark:from-slate-800/80 dark:via-slate-800/50 dark:to-slate-900">
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                            {{ __('Meeting') }}
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $followUp->follow_up_date?->format('l, d M Y') ?? '-' }}
                        </span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                        {{ $followUp->customer?->name ?? $followUp->lead?->customer?->name ?? '-' }}
                    </h3>
                    @if($followUp->lead)
                        <p class="text-xs text-slate-600 dark:text-slate-300">
                            <span class="font-medium text-slate-700 dark:text-slate-200">{{ __('Lead') }}:</span>
                            {{ $followUp->lead->title ?? $followUp->lead->customer?->name }}
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Ringkasan yang akan dikirim --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ __('Daily Report Summary') }}
            </h3>

            <div class="mt-4 grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                <p class="text-slate-500">{{ __('Customer') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $followUp->customer?->name ?? '-' }}</span></p>
                <p class="text-slate-500">{{ __('Meeting Mode') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ \App\Models\FollowUp::meetingModeLabel($followUp->meeting_mode) }}</span></p>
                <p class="text-slate-500">{{ __('Meeting Date') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $followUp->follow_up_date?->format('d M Y') ?? '-' }}</span></p>
                <p class="text-slate-500">{{ __('Time') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $followUp->activity_time ? substr((string) $followUp->activity_time, 0, 5) : '-' }}</span></p>
                <p class="text-slate-500">{{ __('Location') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $followUp->location ?: '-' }}</span></p>
                <p class="text-slate-500">{{ __('Next Meeting') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $followUp->next_follow_up_date?->format('d M Y') ?? '-' }}</span></p>
            </div>

            <div class="mt-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Summary Meeting') }}</p>
                <p class="mt-1 whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ $followUp->description ?: '-' }}</p>
            </div>

            @if(!empty($followUp->meeting_photos))
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                    {{ count($followUp->meeting_photos) }} {{ __('photos') }} {{ __('attached from New Meeting.') }}
                </p>
            @endif
        </div>

        <form action="{{ route('sales.meetings.report.store', $meeting) }}" method="POST" class="mt-6">
            @csrf
            @if($returnTo)
                <input type="hidden" name="return" value="{{ $returnTo }}">
            @endif
            @if($returnDate)
                <input type="hidden" name="date" value="{{ $returnDate }}">
            @endif

            <div class="flex items-center justify-end gap-3">
                <a href="{{ $backUrl }}"
                   class="group relative overflow-hidden rounded-xl bg-accent-500 px-6 py-2.5 text-center text-sm font-medium text-white transition-all duration-300 hover:bg-accent-600 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                    {{ __('Cancel') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </a>
                <x-primary-button type="submit">{{ __('Send Daily Report') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
