<x-app-layout>
    @php
        $totalToday = $todaySchedules->count();
        $totalDone = $todaySchedules->where('status', 'completed')->count();
        $totalPending = $todaySchedules
            ->filter(fn ($s) => $s->status !== 'completed' && $s->start_at && $s->start_at->isPast())
            ->count();
        $totalOverdue = $overdueSchedules->count();
        $statusLabels = ['cool' => __('Cool'), 'warm' => __('Warm'), 'hot' => __('Hot'), 'won' => __('Won'), 'lost' => __('Lost')];
        $funnelTotal = (int) $funnel->sum();
    @endphp

    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-3xl">{{ __('Team Activity') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Daily schedule compliance and activity of the sales team.') }}</p>
        </div>
        <div class="flex gap-2">
            <x-icon-button as="a" icon="back" href="{{ route('manage-sales.index') }}" title="{{ __('Back') }}" />
        </div>
    </div>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Schedules Today') }}</p>
            <p class="mt-1 text-2xl font-extrabold tabular-nums text-slate-900 dark:text-white">{{ $totalToday }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Completed') }}</p>
            <p class="mt-1 text-2xl font-extrabold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $totalDone }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Waiting for Update') }}</p>
            <p class="mt-1 text-2xl font-extrabold tabular-nums text-amber-600 dark:text-amber-400">{{ $totalPending }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Overdue') }}</p>
            <p class="mt-1 text-2xl font-extrabold tabular-nums text-rose-600 dark:text-rose-400">{{ $totalOverdue }}</p>
        </div>
    </div>

    <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200 sm:text-base">{{ __('Schedule Compliance Today') }}</h2>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Green = report submitted, yellow = schedule passed but not reported, red = unreported past schedules.') }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 dark:bg-slate-700/50">
                    <tr class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-300">
                        <th class="px-5 py-3">{{ __('Sales') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Schedules Today') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Completed') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Waiting') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Overdue') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Follow Ups Today') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Other Activities') }}</th>
                        <th class="px-5 py-3 text-center">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($rows as $row)
                        @php
                            $badge = 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-200';
                            $badgeLabel = __('No Schedule');

                            if ($row['overdue'] > 0) {
                                $badge = 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300';
                                $badgeLabel = __('Overdue');
                            } elseif ($row['pending'] > 0) {
                                $badge = 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
                                $badgeLabel = __('Waiting for Update');
                            } elseif ($row['total'] > 0) {
                                $badge = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300';
                                $badgeLabel = __('On Track');
                            }
                        @endphp
                        <tr class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/60">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <x-user-avatar :user="$row['user']" size="w-7 h-7" text="text-[10px]" :clickable="false" />
                                    <a href="{{ route('manage-sales.sales-rep', $row['user']) }}" class="truncate text-sm font-medium text-slate-800 transition-colors hover:text-accent-600 dark:text-slate-100 dark:hover:text-accent-400">{{ $row['user']->name }}</a>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center text-sm tabular-nums text-slate-700 dark:text-slate-200">{{ $row['total'] }}</td>
                            <td class="px-4 py-3 text-center text-sm font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $row['done'] }}</td>
                            <td class="px-4 py-3 text-center text-sm font-semibold tabular-nums text-amber-600 dark:text-amber-400">{{ $row['pending'] }}</td>
                            <td class="px-4 py-3 text-center text-sm font-semibold tabular-nums text-rose-600 dark:text-rose-400">{{ $row['overdue'] }}</td>
                            <td class="px-4 py-3 text-center text-sm tabular-nums text-slate-700 dark:text-slate-200">{{ $row['follow_ups'] }}</td>
                            <td class="px-4 py-3 text-center text-sm tabular-nums text-slate-700 dark:text-slate-200">{{ $row['lead_activities'] }}</td>
                            <td class="px-5 py-3 text-center">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $badge }}">{{ $badgeLabel }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                {{ __('No sales team members found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Lead mengendap --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="mb-3">
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200 sm:text-base">{{ __('Stale Warm/Hot Leads') }}</h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('No follow up for more than 7 days.') }}</p>
            </div>

            @forelse($staleLeads as $lead)
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 py-2.5 last:border-0 dark:border-slate-700">
                    <div class="min-w-0">
                        <a href="{{ route('leads.show', $lead) }}" class="truncate text-sm font-medium text-slate-800 transition-colors hover:text-accent-600 dark:text-slate-100 dark:hover:text-accent-400">
                            {{ $lead->customer?->name ?? '-' }}
                        </a>
                        <p class="mt-0.5 line-clamp-1 text-xs text-slate-500 dark:text-slate-400">{{ $lead->kebutuhan ?: '-' }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $lead->status === 'hot' ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' }}">
                            {{ $statusLabels[$lead->status] ?? ucfirst((string) $lead->status) }}
                        </span>
                        <p class="mt-1 text-[11px] text-slate-400">{{ $lead->assignee?->name ?? __('Unassigned') }}</p>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No stale leads — all warm/hot leads have recent follow ups.') }}</p>
            @endforelse
        </div>

        {{-- Funnel --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="mb-3">
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200 sm:text-base">{{ __('Conversion Funnel') }}</h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Where leads currently sit.') }}</p>
            </div>

            @foreach($statusLabels as $status => $label)
                @php
                    $count = (int) ($funnel[$status] ?? 0);
                    $percent = $funnelTotal > 0 ? round($count / $funnelTotal * 100) : 0;
                    $bar = match ($status) {
                        'hot' => 'bg-rose-500',
                        'warm' => 'bg-amber-500',
                        'won' => 'bg-emerald-500',
                        'lost' => 'bg-slate-400',
                        default => 'bg-sky-500',
                    };
                @endphp
                <div class="mb-3 last:mb-0">
                    <div class="mb-1 flex items-center justify-between text-xs">
                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ $label }}</span>
                        <span class="tabular-nums text-slate-500 dark:text-slate-400">{{ $count }} ({{ $percent }}%)</span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                        <div class="h-full rounded-full {{ $bar }}" style="width: {{ $percent }}%"></div>
                    </div>
                </div>
            @endforeach

            @if($funnelTotal === 0)
                <p class="py-4 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No leads yet.') }}</p>
            @endif
        </div>
    </div>
</x-app-layout>
