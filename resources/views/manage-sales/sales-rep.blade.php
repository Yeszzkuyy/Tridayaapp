<x-app-layout>
    @include('partials.lynk-dashboard-motion')

    <div class="lynk-enter flex flex-col gap-3 pt-1 mb-6 sm:flex-row sm:items-center sm:justify-between" style="--d: 0ms">
        <div>
            <div class="flex items-center gap-2">
                <x-back-button :href="route('manage-sales.kpi')" />
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ $rep->name }}</h1>
            </div>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Sales snapshot and customer list') }}</p>
        </div>
        <div class="inline-flex items-center gap-2 rounded-full border border-slate-200/80 bg-white px-4 py-2 text-xs font-semibold text-slate-600 shadow-sm dark:border-slate-800/70 dark:bg-slate-800/90 dark:text-slate-300">
            <x-icon name="calendar" class="h-3.5 w-3.5 text-accent-600 dark:text-accent-400" />
            <span>{{ now()->locale(app()->getLocale())->translatedFormat('F Y') }}</span>
        </div>
    </div>

    @php
        $paidSeries = \App\Support\SparklineBuilder::monthlyCounts(
            \App\Models\Payment::whereHas('invoice', fn ($q) => $q->whereHas('proposal', fn ($p) => $p->where('created_by', $rep->id))),
            'created_at'
        );
        $leadSeries = \App\Support\SparklineBuilder::monthlyCounts(
            \App\Models\Lead::where('assigned_to', $rep->id),
            'created_at'
        );

        $repCards = [
            [
                'label' => __('Target Omset'),
                'value' => 'Rp '.number_format($myKpi['target_revenue'], 0, ',', '.'),
                'hint' => $myKpi['revenue_pct'].'% '.__('achieved'),
                'icon' => 'chart-bar',
                'ambient' => 'bg-accent-500/15 dark:bg-accent-500/20',
                'iconBg' => 'bg-accent-500/10 text-accent-600 dark:bg-accent-500/15 dark:text-accent-300',
                'color' => '#b45309',
                'gradId' => 'rep-spark-target',
                'spark' => \App\Support\SparklineBuilder::fromSeries($paidSeries, (float) $myKpi['paid_revenue']),
            ],
            [
                'label' => __('Realisasi Closing (Lunas)'),
                'value' => 'Rp '.number_format($myKpi['paid_revenue'], 0, ',', '.'),
                'hint' => __('Paid invoices this month'),
                'icon' => 'check-circle',
                'ambient' => 'bg-emerald-500/15 dark:bg-emerald-500/20',
                'iconBg' => 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
                'color' => '#10b981',
                'gradId' => 'rep-spark-paid',
                'spark' => \App\Support\SparklineBuilder::fromSeries($paidSeries, (float) $myKpi['paid_revenue']),
            ],
            [
                'label' => __('PO Masuk'),
                'value' => 'Rp '.number_format($myKpi['verified_po_revenue'], 0, ',', '.'),
                'hint' => $myKpi['verified_po_deals'].' '.__('verified PO'),
                'icon' => 'briefcase',
                'ambient' => 'bg-indigo-500/15 dark:bg-indigo-500/20',
                'iconBg' => 'bg-indigo-500/10 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300',
                'color' => '#6366f1',
                'gradId' => 'rep-spark-po',
                'spark' => \App\Support\SparklineBuilder::fromSeries($leadSeries, (int) $myKpi['verified_po_deals']),
            ],
            [
                'label' => __('Active Leads'),
                'value' => (string) $kpi['active'],
                'hint' => $kpi['followups_today'].' '.__('follow ups today'),
                'icon' => 'users',
                'ambient' => 'bg-violet-500/15 dark:bg-violet-500/20',
                'iconBg' => 'bg-violet-500/10 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300',
                'color' => '#8b5cf6',
                'gradId' => 'rep-spark-leads',
                'spark' => \App\Support\SparklineBuilder::fromSeries($leadSeries, (int) $kpi['active']),
            ],
        ];
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($repCards as $index => $card)
            <div class="lynk-enter relative overflow-hidden rounded-2xl border border-slate-100 bg-white p-4 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg dark:border-slate-800/60 dark:bg-slate-800/90" style="--d: {{ 100 + ($index * 80) }}ms">
                <div class="pointer-events-none absolute -right-10 -top-10 h-28 w-28 rounded-full blur-2xl {{ $card['ambient'] }}"></div>
                <div class="relative flex items-center gap-3.5">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $card['iconBg'] }}">
                        <x-icon :name="$card['icon']" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                        <p class="truncate text-xl font-extrabold tabular-nums text-slate-900 dark:text-slate-100">{{ $card['value'] }}</p>
                    </div>
                </div>
                <p class="relative mt-3 text-xs font-medium text-slate-500 dark:text-slate-400">{{ $card['hint'] }}</p>
                <div class="relative mt-2 -mx-4 -mb-4 pt-1">
                    <svg viewBox="0 0 240 52" class="h-10 w-full overflow-visible" preserveAspectRatio="none" aria-hidden="true">
                        <defs>
                            <linearGradient id="{{ $card['gradId'] }}" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="{{ $card['color'] }}" stop-opacity="{{ $card['spark']['is_flat'] ? '0.10' : '0.32' }}" />
                                <stop offset="100%" stop-color="{{ $card['color'] }}" stop-opacity="0.0" />
                            </linearGradient>
                        </defs>
                        <path class="lynk-spark-area" d="{{ $card['spark']['area'] }}" fill="url(#{{ $card['gradId'] }})" />
                        <path class="lynk-spark-line" pathLength="100" d="{{ $card['spark']['line'] }}" fill="none" stroke="{{ $card['color'] }}" stroke-width="2.2" stroke-linecap="round" />
                    </svg>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6">
        @include('sales.partials.customer-hub', [
            'hubResetRoute' => route('manage-sales.sales-rep', $rep),
        ])
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="lynk-enter rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90" style="--d: 460ms">
            <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">{{ __('Recent Updates') }}</h2>
            <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-800/60">
                @forelse($repActivities as $activity)
                    <div class="py-2.5">
                        <div class="flex items-center justify-between gap-3">
                            <p class="truncate text-sm font-medium text-slate-700 dark:text-slate-200">{{ $activity->customer?->name ?? '-' }}</p>
                            <span class="shrink-0 text-[11px] text-slate-400">{{ $activity->created_at?->locale(app()->getLocale())->translatedFormat('d M Y') }}</span>
                        </div>
                        <p class="mt-0.5 line-clamp-2 text-xs text-slate-500 dark:text-slate-400">{{ $activity->description }}</p>
                    </div>
                @empty
                    <p class="py-6 text-center text-xs text-slate-400">{{ __('No update note yet') }}</p>
                @endforelse
            </div>
        </div>

        <div class="lynk-enter rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90" style="--d: 500ms">
            <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">{{ __('Latest Leads') }}</h2>
            <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-800/60">
                @forelse($repLeads as $lead)
                    <div class="flex items-center justify-between gap-3 py-2.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-700 dark:text-slate-200">{{ $lead->customer?->name ?? '-' }}</p>
                            <p class="text-[11px] text-slate-400">{{ $lead->kebutuhan ?: __('No requirement yet') }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold {{ \App\Models\Lead::statusBadgeClasses($lead->status) }}">
                            {{ \App\Models\Lead::salesStatusLabel($lead->status) }}
                        </span>
                    </div>
                @empty
                    <p class="py-6 text-center text-xs text-slate-400">{{ __('No leads yet') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
