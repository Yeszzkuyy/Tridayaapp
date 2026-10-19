<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-accent-600 dark:text-accent-300">
                {{ now()->locale(app()->getLocale())->translatedFormat('l, d F Y') }}
            </p>
            <h1 class="mt-2 text-3xl font-bold text-slate-800">{{ __('Sales Dashboard') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Halo, :name. Ringkasan performa lead Anda.', ['name' => auth()->user()->name]) }}</p>
        </div>
        <x-icon-button as="a" icon="leads" href="{{ route('sales.my-leads') }}" title="{{ __('My Leads') }}" />
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4 mb-6">
        <a href="{{ route('sales.my-leads', ['active' => 1]) }}"
           class="relative h-full flex flex-col justify-between overflow-hidden bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5 hover:shadow transition">
            <div class="absolute -right-8 -top-8 w-24 h-24 rounded-full bg-blue-500/5"></div>
            <div class="relative flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Active Leads') }}</p>
                    <p class="text-2xl font-extrabold text-slate-800 dark:text-white mt-1.5">{{ $kpi['active'] ?? 0 }}</p>
                </div>
                <span class="shrink-0 p-2.5 rounded-xl bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-300">
                    <x-icon name="briefcase" class="h-5 w-5" />
                </span>
            </div>
        </a>
        <a href="{{ route('sales.my-leads', ['won_month' => 1]) }}"
           class="relative h-full flex flex-col justify-between overflow-hidden bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5 hover:shadow transition">
            <div class="absolute -right-8 -top-8 w-24 h-24 rounded-full bg-green-500/5"></div>
            <div class="relative flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Won This Month') }}</p>
                    <p class="text-2xl font-extrabold text-green-700 dark:text-green-300 mt-1.5">{{ $kpi['won_month'] ?? 0 }}</p>
                </div>
                <span class="shrink-0 p-2.5 rounded-xl bg-green-100 text-green-600 dark:bg-green-900/40 dark:text-green-300">
                    <x-icon name="check-circle" class="h-5 w-5" />
                </span>
            </div>
        </a>
        <a href="{{ route('sales.follow-ups.index', ['overdue' => 1]) }}"
           class="relative h-full flex flex-col justify-between overflow-hidden bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5 hover:shadow transition">
            <div class="absolute -right-8 -top-8 w-24 h-24 rounded-full bg-red-500/5"></div>
            <div class="relative flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Overdue Follow Ups') }}</p>
                    <p class="mt-1.5 text-2xl font-extrabold {{ ($kpi['overdue'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-800 dark:text-white' }}">{{ $kpi['overdue'] ?? 0 }}</p>
                </div>
                <span class="shrink-0 p-2.5 rounded-xl bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-300">
                    <x-icon name="phone" class="h-5 w-5" />
                </span>
            </div>
        </a>
        <a href="{{ route('sales.follow-ups.index', ['overdue' => 'today']) }}"
           class="relative h-full flex flex-col justify-between overflow-hidden bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5 hover:shadow transition">
            <div class="absolute -right-8 -top-8 w-24 h-24 rounded-full bg-yellow-500/5"></div>
            <div class="relative flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Follow Up Hari Ini') }}</p>
                    <p class="text-2xl font-extrabold text-slate-800 dark:text-white mt-1.5">{{ $kpi['followups_today'] ?? 0 }}</p>
                </div>
                <span class="shrink-0 p-2.5 rounded-xl bg-yellow-100 text-yellow-600 dark:bg-yellow-900/40 dark:text-yellow-300">
                    <x-icon name="chat" class="h-5 w-5" />
                </span>
            </div>
        </a>
        <a href="{{ route('sales.follow-ups.index', ['overdue' => 'upcoming']) }}"
           class="relative h-full flex flex-col justify-between overflow-hidden bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5 hover:shadow transition">
            <div class="absolute -right-8 -top-8 w-24 h-24 rounded-full bg-purple-500/5"></div>
            <div class="relative flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Upcoming Follow Ups') }}</p>
                    <p class="text-2xl font-extrabold text-slate-800 dark:text-white mt-1.5">{{ $kpi['followups_upcoming'] ?? 0 }}</p>
                </div>
                <span class="shrink-0 p-2.5 rounded-xl bg-purple-100 text-purple-600 dark:bg-purple-900/40 dark:text-purple-300">
                    <x-icon name="activity" class="h-5 w-5" />
                </span>
            </div>
        </a>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 mb-6">
        <div class="flex flex-wrap items-end justify-between gap-2 mb-4">
            <div>
                <h2 class="font-semibold text-slate-700 dark:text-slate-200">{{ __('My Schedule') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Summary of your sales agenda.') }}</p>
            </div>
            <a href="{{ route('sales.schedules.index') }}" class="text-sm font-medium text-accent-600 hover:text-accent-500">{{ __('View agenda') }}</a>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach(['today' => __('Today'), 'upcoming' => __('Upcoming'), 'completed' => __('Completed'), 'cancelled' => __('Cancelled')] as $key => $label)
                <div class="rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $label }}</p>
                    <p class="mt-1.5 text-2xl font-extrabold text-slate-800 dark:text-white">{{ $scheduleSummary[$key] ?? 0 }}</p>
                </div>
            @endforeach
        </div>
        @if(($scheduleTypes ?? collect())->isNotEmpty())
            <div class="flex flex-wrap gap-2 mt-3">
                @foreach($scheduleTypes as $type => $total)
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-100 text-blue-800">
                        {{ \App\Models\SalesSchedule::typeLabel($type) }}: {{ $total }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 mb-6" data-reveal>
        <div class="flex flex-wrap items-end justify-between gap-2 mb-4">
            <div>
                <h2 class="font-semibold text-slate-700 dark:text-slate-200">{{ __('Income Bulan Ini') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Total invoice bulan :month per PT/company.', ['month' => now()->translatedFormat('F Y')]) }}</p>
            </div>
            <p class="text-xl font-extrabold text-slate-800 tabular-nums dark:text-white">Rp {{ number_format($incomeTotal ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach($incomeMonth ?? [] as $pt => $total)
                <div class="rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-3">
                    <span class="inline-flex px-1.5 py-0.5 rounded {{ \App\Models\Lead::PT_COLORS[$pt] ?? 'bg-indigo-50 text-indigo-700' }} text-[11px] font-semibold">{{ $pt }}</span>
                    <p class="mt-1.5 text-sm font-bold text-slate-800 tabular-nums dark:text-slate-100">Rp {{ number_format($total, 0, ',', '.') }}</p>
                </div>
            @endforeach
        </div>
    </div>

    @if(($dueFollowUps ?? collect())->isNotEmpty() || ($myTasks ?? collect())->isNotEmpty())
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5 min-w-0">
                <div class="flex items-center gap-2 mb-3">
                    <span class="shrink-0 rounded-lg p-2 bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-300">
                        <x-icon name="phone" class="h-4 w-4" />
                    </span>
                    <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ __('Urgent Follow Ups') }}</h3>
                </div>
                @forelse($dueFollowUps as $fu)
                    <a href="{{ route('sales.follow-ups.show', $fu) }}" class="block py-2 border-b border-slate-100 dark:border-slate-700 last:border-0 hover:underline min-w-0">
                        <span class="block text-sm font-medium text-slate-800 dark:text-slate-100 truncate">{{ $fu->customer?->name ?? '-' }}</span>
                        <span class="block text-xs text-slate-500">{{ $fu->follow_up_date?->format('d M Y') ?? '-' }}</span>
                    </a>
                @empty
                    <p class="text-sm text-slate-400">{{ __('None.') }}</p>
                @endforelse
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5 min-w-0">
                <div class="flex items-center gap-2 mb-3">
                    <span class="shrink-0 rounded-lg p-2 bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-300">
                        <x-icon name="file-text" class="h-4 w-4" />
                    </span>
                    <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ __('Active Inside Sales Tasks') }}</h3>
                </div>
                @forelse($myTasks as $task)
                    <a href="{{ route('lead-tasks.show', $task) }}" class="block py-2 border-b border-slate-100 dark:border-slate-700 last:border-0 hover:underline min-w-0">
                        <span class="block text-sm font-medium text-slate-800 dark:text-slate-100 truncate">{{ $task->title }}</span>
                        <span class="block text-xs text-slate-500 truncate">{{ $task->assignee?->name ?? __('Not assigned yet') }}</span>
                    </a>
                @empty
                    <p class="text-sm text-slate-400">{{ __('None.') }}</p>
                @endforelse
            </div>
        </div>
    @endif

    <div class="w-full bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6" data-reveal>
        <h2 class="font-semibold text-slate-700 dark:text-slate-200 mb-1">{{ __('My Lead Status') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">{{ __('Click a status to view those leads in My Leads.') }}</p>
        @if(($funnelTotal ?? 0) > 0)
            <div class="relative w-full max-w-[280px] mx-auto">
                <x-donut-chart :data="$donutSales" :size="260" :strokeWidth="34" :scroll="true" />
                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center px-10">
                    <span class="text-xs font-medium text-slate-400 dark:text-slate-500">{{ __('Total Leads') }}</span>
                    <span class="mt-0.5 text-3xl font-bold text-slate-800 tabular-nums dark:text-slate-100">{{ $funnelTotal }}</span>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-1 gap-1 sm:grid-cols-2">
                @foreach($donutSales as $seg)
                    <a href="{{ route('sales.my-leads', ['status' => $seg['key']]) }}"
                       class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm transition hover:bg-slate-50 dark:hover:bg-slate-700/40">
                        <span class="flex min-w-0 items-center gap-2.5">
                            <span class="h-3 w-3 shrink-0 rounded-full" style="background-color: {{ $seg['color'] }}"></span>
                            <span class="truncate font-medium text-slate-600 dark:text-slate-300">{{ $seg['label'] }}</span>
                        </span>
                        <span class="shrink-0 font-semibold text-slate-700 tabular-nums dark:text-slate-200">{{ $seg['value'] }}
                            <span class="font-medium text-slate-400">· {{ $funnelTotal > 0 ? round($seg['value'] / $funnelTotal * 100) : 0 }}%</span>
                        </span>
                    </a>
                @endforeach
            </div>
        @else
            <div class="rounded-xl bg-slate-50 dark:bg-slate-900/40 px-5 py-8 text-center">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('No leads assigned to you yet.') }}</p>
            </div>
        @endif
    </div>
</x-app-layout>
