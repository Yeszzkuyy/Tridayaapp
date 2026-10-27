<x-app-layout>
    @php
        $modeStyles = [
            'office' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
            'field' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'online' => 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
        ];
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Meeting') }}</h1>
            <p class="mt-1 text-slate-500 dark:text-slate-400">{{ __('Daily meeting tracker — results, proof, and follow-up progress.') }}</p>
        </div>
        <x-icon-button as="a" icon="add" href="{{ route('sales.meetings.create') }}" label="{{ __('New Meeting') }}" title="{{ __('New Meeting') }}" />
    </div>

    {{-- KPI --}}
    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach([
            ['label' => __('Today'), 'value' => $kpi['today'], 'style' => 'bg-blue-50 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'],
            ['label' => __('This Week'), 'value' => $kpi['week'], 'style' => 'bg-indigo-50 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300'],
            ['label' => __('Report Pending'), 'value' => $kpi['pending'], 'style' => 'bg-amber-50 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'],
            ['label' => __('Won This Month'), 'value' => $kpi['won'], 'style' => 'bg-green-50 text-green-800 dark:bg-green-900/30 dark:text-green-300'],
        ] as $card)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $card['label'] }}</p>
                <p class="mt-1 inline-flex rounded-full px-2.5 py-0.5 text-xl font-extrabold tabular-nums {{ $card['style'] }}">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Tab + filter --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        @if($canSeeTeam)
            <div class="inline-flex gap-1 rounded-2xl bg-slate-100 p-1.5 dark:bg-slate-700">
                @foreach(['my' => __('My Meetings'), 'team' => __('Team Meetings')] as $key => $label)
                    <a href="{{ route('sales.meetings.index', array_filter(['tab' => $key, 'range' => $range, 'status' => request('status'), 'mode' => request('mode'), 'outcome' => request('outcome'), 'q' => request('q')])) }}"
                       class="rounded-xl px-4 py-2 text-sm font-medium transition-colors {{ $tab === $key
                           ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-800 dark:text-slate-100'
                           : 'text-slate-500 hover:text-slate-800 dark:text-slate-300' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        @else
            <span class="inline-flex rounded-2xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ __('My Meetings') }}</span>
        @endif

        <form method="GET" action="{{ route('sales.meetings.index') }}" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <select name="range" onchange="this.form.submit()" class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                @foreach(['today' => __('Today'), 'week' => __('This Week'), 'month' => __('This Month'), 'all' => __('All')] as $key => $label)
                    <option value="{{ $key }}" @selected($range === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status" onchange="this.form.submit()" class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                <option value="">{{ __('All statuses') }}</option>
                <option value="pending" @selected(request('status') === 'pending')>{{ __('Report Pending') }}</option>
                <option value="completed" @selected(request('status') === 'completed')>{{ __('Completed') }}</option>
            </select>
            <select name="mode" onchange="this.form.submit()" class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                <option value="">{{ __('All modes') }}</option>
                @foreach(\App\Models\FollowUp::MEETING_MODES as $mode)
                    <option value="{{ $mode }}" @selected(request('mode') === $mode)>{{ \App\Models\FollowUp::meetingModeLabel($mode) }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search customer...') }}"
                   class="h-10 w-44 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
            <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
        </form>
    </div>

    {{-- Daftar per tanggal --}}
    @if($grouped->isEmpty())
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-600 dark:bg-slate-800">
            <x-empty-state label="{{ __('meetings') }}" description="{{ __('No meetings in this period yet.') }}">
                <x-icon-button as="a" icon="add" href="{{ route('sales.meetings.create') }}" label="{{ __('New Meeting') }}" title="{{ __('New Meeting') }}" />
            </x-empty-state>
        </div>
    @else
        <div class="space-y-5">
            @foreach($grouped as $date => $items)
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-600 dark:bg-slate-800">
                    <header class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-5 py-3 dark:border-slate-600 dark:bg-slate-700/50">
                        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                            {{ $date !== '-' ? \Carbon\Carbon::parse($date)->translatedFormat('l, d M Y') : '-' }}
                        </h2>
                        <span class="text-xs font-medium text-slate-400">{{ $items->count() }} {{ __('meetings') }}</span>
                    </header>
                    <div class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($items as $meeting)
                            <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $meeting->customer?->name ?? '-' }}</span>
                                        @if($meeting->meeting_mode)
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $modeStyles[$meeting->meeting_mode] ?? 'bg-slate-100 text-slate-600' }}">
                                                {{ \App\Models\FollowUp::meetingModeLabel($meeting->meeting_mode) }}
                                            </span>
                                        @endif
                                        @if($meeting->meeting_outcome)
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ \App\Models\FollowUp::outcomeBadgeClass($meeting->meeting_outcome) }}">
                                                {{ \App\Models\FollowUp::outcomeLabel($meeting->meeting_outcome) }}
                                            </span>
                                        @elseif($meeting->follow_up_date && $meeting->follow_up_date->lte(today()))
                                            <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                                {{ __('Report Pending') }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="mt-1 line-clamp-1 text-sm text-slate-500 dark:text-slate-400">{{ $meeting->description ?: '-' }}</p>
                                    <p class="mt-1 text-xs text-slate-400">
                                        {{ $meeting->activity_time ? substr((string) $meeting->activity_time, 0, 5).' • ' : '' }}
                                        {{ __('by :name', ['name' => $meeting->creator?->name ?? '-']) }}
                                        @if($meeting->hasMeetingReport()) • {{ count($meeting->meeting_photos ?? []) }} {{ __('photos') }} @endif
                                    </p>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <x-icon-button as="a" icon="view" href="{{ route('sales.meetings.show', $meeting) }}" title="{{ __('View details') }}" />
                                    @if(! $meeting->completed_at && $meeting->follow_up_date && $meeting->follow_up_date->lte(today()))
                                        <x-icon-button as="a" icon="add" href="{{ route('sales.meetings.report', $meeting) }}" label="{{ __('Input Report') }}" title="{{ __('Input Report') }}" />
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</x-app-layout>
