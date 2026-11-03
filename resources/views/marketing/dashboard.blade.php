<x-app-layout>
    @include('partials.lynk-dashboard-motion')

    {{-- 1. Open Header Style --}}
    <div class="lynk-enter flex flex-col gap-3 pt-1 mb-6 sm:flex-row sm:items-center sm:justify-between" style="--d: 0ms">
        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('Dashboard Marketing') }}</h1>
        <div class="flex flex-wrap items-center gap-2.5">
            <div class="inline-flex items-center gap-2 rounded-full border border-slate-200/80 bg-white px-4 py-2 text-xs font-semibold text-slate-600 shadow-sm dark:border-slate-800/70 dark:bg-slate-800/90 dark:text-slate-300">
                <x-icon name="calendar" class="h-3.5 w-3.5 text-accent-600 dark:text-accent-400" />
                <span>{{ now()->locale(app()->getLocale())->translatedFormat('l, d F Y') }}</span>
            </div>
            <x-icon-button as="a" icon="leads" href="{{ route('leads.index') }}" title="{{ __('View Leads') }}" />
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5 mb-6">
        <form method="GET" action="{{ route('marketing.dashboard') }}" class="flex flex-wrap items-end gap-x-6 gap-y-4">
            <div class="flex flex-wrap sm:flex-nowrap items-end gap-4">
                <div>
                    <label class="text-sm font-medium text-slate-500">{{ __('From Date') }}</label>
                    <x-datepicker name="date_from" value="{{ $dateFrom }}" class="mt-1"></x-datepicker>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-500">{{ __('To Date') }}</label>
                    <x-datepicker name="date_to" value="{{ $dateTo }}" class="mt-1"></x-datepicker>
                </div>
            </div>

            <div class="flex items-end gap-2">
                <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
                <x-icon-button as="a" icon="reset" href="{{ route('marketing.dashboard') }}" title="{{ __('Reset') }}" />
            </div>

            <div class="flex items-end">
                @php
                    $presets = [
                        __('This Month') => [now()->startOfMonth()->toDateString(), now()->toDateString()],
                        __('3 Months') => [now()->subMonths(2)->startOfMonth()->toDateString(), now()->toDateString()],
                        __('6 Months') => [now()->subMonths(5)->startOfMonth()->toDateString(), now()->toDateString()],
                    ];
                    $activeRange = $dateFrom . '|' . $dateTo;
                    $presetItems = [];
                    $activeIndex = -1;
                    foreach ($presets as $label => [$from, $to]) {
                        $presetItems[] = ['label' => $label, 'url' => route('marketing.dashboard', ['date_from' => $from, 'date_to' => $to])];
                        if ($activeRange === $from . '|' . $to) $activeIndex = count($presetItems) - 1;
                    }
                @endphp
                @if($activeIndex >= 0)
                    <div id="marketing-range-segment" data-items='@json($presetItems)' data-active="{{ $activeIndex }}"></div>
                    @vite('resources/js/rubber-segment.jsx')
                    <noscript>
                        <div class="inline-flex items-center rounded-xl border border-slate-200 dark:border-slate-600 overflow-hidden divide-x divide-slate-200 dark:divide-slate-600">
                            @foreach($presets as $label => [$from, $to])
                                @php $active = $activeRange === $from . '|' . $to; @endphp
                                <a href="{{ route('marketing.dashboard', ['date_from' => $from, 'date_to' => $to]) }}"
                                   class="group relative overflow-hidden px-4 py-2.5 text-sm font-medium transition {{ $active ? 'bg-accent-600 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                                    {{ $label }}
                                </a>
                            @endforeach
                        </div>
                    </noscript>
                @else
                    <div class="inline-flex items-center rounded-xl border border-slate-200 dark:border-slate-600 overflow-hidden divide-x divide-slate-200 dark:divide-slate-600">
                        @foreach($presets as $label => [$from, $to])
                            @php $active = $activeRange === $from . '|' . $to; @endphp
                            <a href="{{ route('marketing.dashboard', ['date_from' => $from, 'date_to' => $to]) }}"
                               class="group relative overflow-hidden px-4 py-2.5 text-sm font-medium transition {{ $active ? 'bg-accent-600 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </form>
    </div>

    @php
        $trendTotals = collect($trend ?? [])->pluck('total')->map(fn ($v) => (int) $v)->all();
        $mktActiveSeries = \App\Support\SparklineBuilder::monthlyCounts(
            \App\Models\Lead::whereNotIn('status', ['won', 'lost']),
            'created_at'
        );
        $mktWonSeries = \App\Support\SparklineBuilder::monthlyCounts(
            \App\Models\Lead::where('status', 'won'),
            'updated_at'
        );

        $mktKpiCards = [
            [
                'label' => __('Leads This Month'),
                'value' => (int) ($stats['this_month'] ?? 0),
                'suffix' => '',
                'sub' => ($stats['this_month'] >= $stats['last_month'] ? '▲ ' : '▼ ') . __('last month:') . ' ' . $stats['last_month'],
                'subClass' => $stats['this_month'] >= $stats['last_month'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500 dark:text-rose-400',
                'icon' => 'calendar',
                'ambient' => 'bg-violet-500/15 dark:bg-violet-500/20',
                'iconBg' => 'bg-violet-500/10 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300',
                'borderHover' => 'hover:border-violet-400/50 dark:hover:border-violet-500/40',
                'color' => '#8b5cf6',
                'gradId' => 'mkt-spark-1',
                'spark' => \App\Support\SparklineBuilder::fromSeries($trendTotals, (int) ($stats['this_month'] ?? 0)),
            ],
            [
                'label' => __('Total Leads'),
                'value' => (int) ($stats['total'] ?? 0),
                'suffix' => '',
                'sub' => __('All recorded leads'),
                'subClass' => 'text-slate-500 dark:text-slate-400',
                'icon' => 'users',
                'ambient' => 'bg-indigo-500/15 dark:bg-indigo-500/20',
                'iconBg' => 'bg-indigo-500/10 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300',
                'borderHover' => 'hover:border-indigo-400/50 dark:hover:border-indigo-500/40',
                'color' => '#6366f1',
                'gradId' => 'mkt-spark-2',
                'spark' => \App\Support\SparklineBuilder::fromSeries($trendTotals, (int) ($stats['total'] ?? 0)),
            ],
            [
                'label' => __('Active Leads'),
                'value' => (int) ($stats['active'] ?? 0),
                'suffix' => '',
                'sub' => __('In pipeline'),
                'subClass' => 'text-amber-600 dark:text-amber-400',
                'icon' => 'bolt',
                'ambient' => 'bg-amber-500/15 dark:bg-amber-500/20',
                'iconBg' => 'bg-amber-500/10 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300',
                'borderHover' => 'hover:border-amber-400/50 dark:hover:border-amber-500/40',
                'color' => '#f59e0b',
                'gradId' => 'mkt-spark-3',
                'spark' => \App\Support\SparklineBuilder::fromSeries($mktActiveSeries, (int) ($stats['active'] ?? 0)),
            ],
            [
                'label' => __('Won'),
                'value' => (int) ($stats['won'] ?? 0),
                'suffix' => '',
                'sub' => __('Lost:') . ' ' . $stats['lost'],
                'subClass' => 'text-rose-500 dark:text-rose-400',
                'icon' => 'check-circle',
                'ambient' => 'bg-emerald-500/15 dark:bg-emerald-500/20',
                'iconBg' => 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
                'borderHover' => 'hover:border-emerald-400/50 dark:hover:border-emerald-500/40',
                'color' => '#10b981',
                'gradId' => 'mkt-spark-4',
                'spark' => \App\Support\SparklineBuilder::fromSeries($mktWonSeries, (int) ($stats['won'] ?? 0)),
            ],
            [
                'label' => __('Conversion Rate'),
                'value' => (int) ($stats['conversion'] ?? 0),
                'suffix' => '%',
                'sub' => __('won ÷ (won + lost)'),
                'subClass' => 'text-slate-400',
                'icon' => 'chart-bar',
                'ambient' => 'bg-pink-500/15 dark:bg-pink-500/20',
                'iconBg' => 'bg-pink-500/10 text-pink-600 dark:bg-pink-500/15 dark:text-pink-300',
                'borderHover' => 'hover:border-pink-400/50 dark:hover:border-pink-500/40',
                'color' => '#ec4899',
                'gradId' => 'mkt-spark-5',
                'spark' => \App\Support\SparklineBuilder::fromSeries($mktWonSeries, (int) ($stats['conversion'] ?? 0)),
            ],
        ];
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        @foreach($mktKpiCards as $idx => $mc)
            @php $dMs = 100 + ($idx * 80); @endphp
            <div style="--d: {{ $dMs }}ms"
                 class="lynk-enter lynk-kpi-card group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg dark:border-slate-800/60 dark:bg-slate-800/90 {{ $mc['borderHover'] }}">
                <span aria-hidden="true" class="pointer-events-none absolute -right-10 -top-10 h-28 w-28 rounded-full blur-2xl transition-opacity duration-500 group-hover:opacity-100 {{ $mc['ambient'] }}"></span>
                <div class="relative z-10">
                    <div class="flex items-center gap-3.5">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition-transform duration-300 group-hover:scale-105 {{ $mc['iconBg'] }}">
                            <x-icon name="{{ $mc['icon'] }}" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">{{ $mc['label'] }}</p>
                            <p class="mt-0.5 text-2xl font-extrabold tracking-tight text-slate-900 tabular-nums dark:text-white"
                               data-countup="{{ $mc['value'] }}" data-countup-suffix="{{ $mc['suffix'] }}" data-countup-delay="{{ $dMs + 20 }}">{{ $mc['value'] }}{{ $mc['suffix'] }}</p>
                        </div>
                    </div>
                    <p class="mt-3 text-xs font-medium {{ $mc['subClass'] }}">{{ $mc['sub'] }}</p>
                </div>
                <div class="relative z-10 mt-3 -mx-5 -mb-5 pt-1">
                    <svg viewBox="0 0 240 52" class="h-11 w-full overflow-visible" preserveAspectRatio="none" aria-hidden="true">
                        <defs>
                            <linearGradient id="{{ $mc['gradId'] }}" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="{{ $mc['color'] }}" stop-opacity="{{ $mc['spark']['is_flat'] ? '0.10' : '0.32' }}" />
                                <stop offset="100%" stop-color="{{ $mc['color'] }}" stop-opacity="0.0" />
                            </linearGradient>
                        </defs>
                        <path class="lynk-spark-area" d="{{ $mc['spark']['area'] }}" fill="url(#{{ $mc['gradId'] }})" />
                        <path class="lynk-spark-line" pathLength="100" d="{{ $mc['spark']['line'] }}" fill="none" stroke="{{ $mc['color'] }}" stroke-width="2.2" stroke-linecap="round" />
                    </svg>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Tren lead masuk --}}
        <div style="--d: 420ms" class="lynk-enter rounded-2xl border border-slate-100 bg-white p-5 sm:p-6 shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90">
            <h2 class="font-semibold text-slate-800 dark:text-slate-100 mb-4">{{ __('Incoming Leads') }} — {{ $dateFrom }} {{ __('s/d') }} {{ $dateTo }}</h2>
            @php $maxTrend = max($trend->max('total'), 1); @endphp
            <div class="flex items-end justify-between gap-3 h-44">
                @foreach($trend as $month)
                    <div class="flex-1 flex flex-col items-center gap-1 h-full justify-end">
                        <span class="text-xs font-semibold text-slate-600 tabular-nums">{{ $month->total }}</span>
                        <div class="w-full max-w-12 rounded-t-[4px] bg-accent-500" data-grow-h
                             style="--h: {{ max(round($month->total / $maxTrend * 100), 2) }}%; --d: {{ $loop->index * 120 }}ms"></div>
                        <span class="text-[11px] text-slate-500 whitespace-nowrap">{{ $month->label }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Lead per sumber --}}
        <div style="--d: 500ms" class="lynk-enter rounded-2xl border border-slate-100 bg-white p-5 sm:p-6 shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90">
            <h2 class="font-semibold text-slate-800 dark:text-slate-100 mb-4">{{ __('Leads by Source') }}</h2>
            @if($perSource->isEmpty())
                <p class="text-sm text-slate-500 py-8 text-center">{{ __('No lead data yet.') }}</p>
            @else
                @php $maxSource = max($perSource->max('total'), 1); @endphp
                <div class="space-y-3">
                    @foreach($perSource as $row)
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="text-slate-600 dark:text-slate-300 font-medium capitalize">{{ str_replace('_', ' ', $row->source) }}</span>
                                <span class="font-bold text-slate-800 dark:text-slate-100 tabular-nums" data-countup="{{ (int) $row->total }}" data-countup-delay="520">{{ $row->total }}</span>
                            </div>
                            <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-700/70 overflow-hidden">
                                <div class="lynk-progress-bar h-full rounded-full bg-accent-500"
                                     style="--target-w: {{ round($row->total / $maxSource * 100) }}%; --d: {{ 500 + ($loop->index * 80) }}ms"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Donut Lead per Status (Side-by-Side Donut Chart & Legend) + tabel dinamis --}}
    <div style="--d: 580ms" class="lynk-enter w-full rounded-2xl border border-slate-100 bg-white p-5 sm:p-6 shadow-sm mt-6 dark:border-slate-800/60 dark:bg-slate-800/90" x-data="{
        selectedStatus: null,
        leads: @js($leadsByStatus),
        segments: @js($donutMarketing->values()),
        total: {{ $funnelTotal }},
        select(status) {
            this.selectedStatus = this.selectedStatus === status ? null : status;
            this.hl();
        },
        hl() {
            const key = this.selectedStatus;
            document.querySelectorAll('#status-donut-chart .donut-seg').forEach((seg) => {
                if (key && seg.dataset.key === key) seg.dataset.active = 'true';
                else delete seg.dataset.active;
            });
        },
        activeSeg() { return this.segments.find((s) => s.key === this.selectedStatus); },
        statusLabel(key) { const s = this.segments.find((s) => s.key === key); return s ? s.label : key; },
        filteredLeads() {
            if (this.selectedStatus) return (this.leads[this.selectedStatus] || []).map((l) => ({ ...l, _status: this.selectedStatus }));
            return Object.entries(this.leads).flatMap(([status, arr]) => (arr || []).map((l) => ({ ...l, _status: status })));
        },
    }"
    x-on:donut-select.window="select($event.detail.key)"
    >
        <h2 class="font-semibold text-slate-800 dark:text-slate-100 mb-4">{{ __('Lead Pipeline by Status') }}</h2>
        <div class="flex flex-col sm:flex-row items-center justify-between gap-6 max-w-2xl mx-auto">
            <div class="relative w-44 shrink-0">
                <div id="status-donut-chart" class="w-full">
                    <x-donut-chart :data="$donutMarketing" :size="176" :strokeWidth="24" :scroll="true" />
                </div>
                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center px-6">
                    <span class="mt-0.5 text-2xl sm:text-3xl font-extrabold text-slate-900 tabular-nums dark:text-white" x-text="activeSeg() ? activeSeg().value : total">{{ $stats['total'] }}</span>
                    <span class="text-[11px] font-medium text-slate-400 dark:text-slate-400 truncate max-w-full" x-text="activeSeg() ? activeSeg().label : '{{ __('Total Leads') }}'">{{ __('Total Leads') }}</span>
                    <span x-show="activeSeg() && total > 0" class="text-xs font-medium text-slate-400" x-text="activeSeg() ? '[' + Math.round(activeSeg().value / total * 100) + '%]' : ''"></span>
                </div>
            </div>

            {{-- Legenda interaktif vertikal --}}
            <div class="w-full flex-1 grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                @foreach($donutMarketing as $seg)
                    <button type="button" @click="select('{{ $seg['key'] }}')"
                            class="flex items-center justify-between gap-2 rounded-xl px-3 py-2 text-xs transition hover:bg-slate-50/90 dark:hover:bg-slate-700/35"
                            :class="selectedStatus === '{{ $seg['key'] }}' && 'bg-slate-50 dark:bg-slate-700/40 ring-1 ring-slate-200 dark:ring-slate-600'">
                        <span class="flex min-w-0 items-center gap-2.5">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $seg['color'] }}"></span>
                            <span class="truncate font-medium text-slate-600 dark:text-slate-300">{{ $seg['label'] }}</span>
                        </span>
                        <span class="shrink-0 font-bold text-slate-800 tabular-nums dark:text-slate-100">
                            <span data-countup="{{ (int) $seg['value'] }}" data-countup-delay="620">{{ $seg['value'] }}</span>
                            <span class="font-normal text-slate-400">({{ $funnelTotal > 0 ? round($seg['value'] / $funnelTotal * 100) : 0 }}%)</span>
                        </span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Tabel lead — selalu tampil: semua lead bila belum filter, per status bila diklik --}}
        <div class="mt-6 border-t border-slate-100 dark:border-slate-700 pt-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-slate-700 dark:text-slate-200">
                    <span x-show="!selectedStatus">{{ __('All Leads') }}</span>
                    <span x-show="selectedStatus">{{ __('Lead Detail') }} <span class="text-accent-600 dark:text-accent-400 uppercase" x-text="selectedStatus"></span></span>
                    <span class="text-sm font-medium text-slate-400">(<span x-text="filteredLeads().length"></span> {{ __('lead') }})</span>
                </h3>
                <button type="button" x-show="selectedStatus" @click="select(selectedStatus)"
                        class="text-sm font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition">
                    {{ __('Show all') }}
                </button>
            </div>

            <template x-if="filteredLeads().length > 0">
                <div class="overflow-x-auto rounded-xl border border-slate-100 dark:border-slate-700">
                    <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-700 text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900/40">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Lead / Customer') }}</th>
                                <th x-show="!selectedStatus" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Source') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Partner') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Entry Date') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700 bg-white dark:bg-slate-800">
                            <template x-for="lead in filteredLeads()" :key="lead.id">
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-slate-800 dark:text-slate-100" x-text="lead.name"></p>
                                        <p class="text-xs text-slate-500" x-show="lead.company" x-text="lead.company"></p>
                                    </td>
                                    <td x-show="!selectedStatus" class="px-4 py-3 text-slate-600 dark:text-slate-300 uppercase" x-text="statusLabel(lead._status)"></td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300 capitalize" x-text="lead.source"></td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300" x-text="lead.partner"></td>
                                    <td class="px-4 py-3 text-right text-slate-500 whitespace-nowrap" x-text="lead.date"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>

            <template x-if="filteredLeads().length === 0">
                <div class="rounded-xl bg-slate-50 dark:bg-slate-900/40 px-5 py-8 text-center">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                        <span x-show="!selectedStatus">{{ __('No leads in this date range.') }}</span>
                        <span x-show="selectedStatus">{{ __('No leads with status') }} <span class="uppercase" x-text="selectedStatus"></span> {{ __('in this date range.') }}</span>
                    </p>
                </div>
            </template>
        </div>
    </div>
</x-app-layout>
