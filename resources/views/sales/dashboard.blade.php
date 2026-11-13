<x-app-layout>
    @include('partials.lynk-dashboard-motion')

    {{-- 1. Open Header Style --}}
    <div class="lynk-enter flex flex-col gap-3 pt-1 mb-6 sm:flex-row sm:items-center sm:justify-between" style="--d: 0ms">
        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
            {{ __('Sales Dashboard') }}
        </h1>
        <div class="flex flex-wrap items-center gap-2.5">
            <div class="inline-flex items-center gap-2 rounded-full border border-slate-200/80 bg-white px-4 py-2 text-xs font-semibold text-slate-600 shadow-sm dark:border-slate-800/70 dark:bg-slate-800/90 dark:text-slate-300">
                <x-icon name="calendar" class="h-3.5 w-3.5 text-accent-600 dark:text-accent-400" />
                <span>{{ now()->locale(app()->getLocale())->translatedFormat('l, d F Y') }}</span>
            </div>
            <x-icon-button as="a" icon="leads" href="{{ route('sales.my-leads') }}" title="{{ __('My Leads') }}" />
        </div>
    </div>

    {{-- Banner Notifikasi Motivasi / Congratulation Dinamis --}}
    @if(isset($myKpi['celebration']))
        <div class="relative overflow-hidden rounded-3xl border p-5 mb-6 bg-gradient-to-r {{ $myKpi['celebration']['bg_gradient'] }} bg-white dark:bg-slate-800 shadow-sm transition-all duration-300">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-2xl shadow-inner {{ $myKpi['celebration']['icon_color'] }}">
                        @if($myKpi['celebration']['type'] === 'congratulations')
                            🎉
                        @elseif($myKpi['celebration']['type'] === 'progress')
                            🚀
                        @else
                            💪
                        @endif
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider {{ $myKpi['celebration']['type'] === 'congratulations' ? 'bg-emerald-500 text-white' : ($myKpi['celebration']['type'] === 'progress' ? 'bg-blue-600 text-white' : 'bg-amber-500 text-white') }}">
                                {{ $myKpi['celebration']['badge'] }}
                            </span>
                            <span class="text-xs text-slate-400 font-medium">{{ \Carbon\Carbon::create(null, $myKpi['period_month'], 1)->locale(app()->getLocale())->translatedFormat('F Y') }}</span>
                        </div>
                        <h2 class="mt-1 text-lg font-bold text-slate-800 dark:text-white leading-tight">
                            {{ $myKpi['celebration']['title'] }}
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 mt-0.5">
                            {{ $myKpi['celebration']['description'] }}
                        </p>
                    </div>
                </div>
                @if($myKpi['actual_deals'] > 0 || $myKpi['actual_revenue'] > 0)
                    <div class="shrink-0 flex items-center gap-3 bg-white/80 dark:bg-slate-900/80 backdrop-blur px-4 py-2.5 rounded-2xl border border-slate-200/60 dark:border-slate-700">
                        <div class="text-right">
                            <p class="text-[10px] uppercase font-bold text-slate-400">{{ __('Closing Bulan Ini') }}</p>
                            <p class="text-base font-extrabold text-slate-800 dark:text-white tabular-nums">{{ $myKpi['actual_deals'] }} PO / Deal</p>
                        </div>
                        <div class="h-8 w-px bg-slate-200 dark:bg-slate-700"></div>
                        <div class="text-right">
                            <p class="text-[10px] uppercase font-bold text-slate-400">{{ __('Omset') }}</p>
                            <p class="text-base font-extrabold text-emerald-600 dark:text-emerald-400 tabular-nums">Rp {{ number_format($myKpi['actual_revenue'], 0, ',', '.') }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @php
        $uid = auth()->id();
        $activeSeries = \App\Support\SparklineBuilder::monthlyCounts(
            \App\Models\Lead::where('assigned_to', $uid)->whereNotIn('status', ['won', 'lost']),
            'created_at'
        );
        $wonSeries = \App\Support\SparklineBuilder::monthlyCounts(
            \App\Models\Lead::where('assigned_to', $uid)->where('status', 'won'),
            'updated_at'
        );
        $fuSeries = \App\Support\SparklineBuilder::monthlyCounts(
            \App\Models\FollowUp::where('created_by', $uid),
            'created_at'
        );
        $overdueSeries = \App\Support\SparklineBuilder::monthlyCounts(
            \App\Models\Lead::where('assigned_to', $uid)->assignmentOverdue(),
            'assigned_at'
        );

        $salesKpiCards = [
            [
                'label' => __('Active Leads'),
                'value' => (int) ($kpi['active'] ?? 0),
                'url' => route('sales.my-leads', ['active' => 1]),
                'icon' => 'briefcase',
                'ambient' => 'bg-violet-500/15 dark:bg-violet-500/20',
                'iconBg' => 'bg-violet-500/10 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300',
                'borderHover' => 'hover:border-violet-400/50 dark:hover:border-violet-500/40',
                'color' => '#8b5cf6',
                'gradId' => 'sales-spark-1',
                'spark' => \App\Support\SparklineBuilder::fromSeries($activeSeries, (int) ($kpi['active'] ?? 0)),
            ],
            [
                'label' => __('Won This Month'),
                'value' => (int) ($kpi['won_month'] ?? 0),
                'url' => route('sales.my-leads', ['won_month' => 1]),
                'icon' => 'check-circle',
                'ambient' => 'bg-emerald-500/15 dark:bg-emerald-500/20',
                'iconBg' => 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
                'borderHover' => 'hover:border-emerald-400/50 dark:hover:border-emerald-500/40',
                'color' => '#10b981',
                'gradId' => 'sales-spark-2',
                'spark' => \App\Support\SparklineBuilder::fromSeries($wonSeries, (int) ($kpi['won_month'] ?? 0)),
            ],
            [
                'label' => __('Overdue Leads'),
                'value' => (int) ($kpi['overdue'] ?? 0),
                'url' => route('sales.my-leads', ['overdue' => 1]),
                'icon' => 'phone',
                'ambient' => 'bg-rose-500/15 dark:bg-rose-500/20',
                'iconBg' => 'bg-rose-500/10 text-rose-600 dark:bg-rose-500/15 dark:text-rose-300',
                'borderHover' => 'hover:border-rose-400/50 dark:hover:border-rose-500/40',
                'color' => '#f43f5e',
                'gradId' => 'sales-spark-3',
                'spark' => \App\Support\SparklineBuilder::fromSeries($overdueSeries, (int) ($kpi['overdue'] ?? 0)),
            ],
            [
                'label' => __('Follow Up Hari Ini'),
                'value' => (int) ($kpi['followups_today'] ?? 0),
                'url' => route('sales.follow-ups.index', ['tab' => 'due_today']),
                'icon' => 'chat',
                'ambient' => 'bg-amber-500/15 dark:bg-amber-500/20',
                'iconBg' => 'bg-amber-500/10 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300',
                'borderHover' => 'hover:border-amber-400/50 dark:hover:border-amber-500/40',
                'color' => '#f59e0b',
                'gradId' => 'sales-spark-4',
                'spark' => \App\Support\SparklineBuilder::fromSeries($fuSeries, (int) ($kpi['followups_today'] ?? 0)),
            ],
            [
                'label' => __('Upcoming Follow Ups'),
                'value' => (int) ($kpi['followups_upcoming'] ?? 0),
                'url' => route('sales.follow-ups.index', ['tab' => 'upcoming']),
                'icon' => 'activity',
                'ambient' => 'bg-pink-500/15 dark:bg-pink-500/20',
                'iconBg' => 'bg-pink-500/10 text-pink-600 dark:bg-pink-500/15 dark:text-pink-300',
                'borderHover' => 'hover:border-pink-400/50 dark:hover:border-pink-500/40',
                'color' => '#ec4899',
                'gradId' => 'sales-spark-5',
                'spark' => \App\Support\SparklineBuilder::fromSeries($fuSeries, (int) ($kpi['followups_upcoming'] ?? 0)),
            ],
        ];
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4 mb-6">
        @foreach($salesKpiCards as $idx => $sc)
            @php $dMs = 100 + ($idx * 80); @endphp
            <a href="{{ $sc['url'] }}"
               style="--d: {{ $dMs }}ms"
               class="lynk-enter lynk-kpi-card group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg active:scale-[0.98] dark:border-slate-800/60 dark:bg-slate-800/90 {{ $sc['borderHover'] }}">
                <span aria-hidden="true" class="pointer-events-none absolute -right-10 -top-10 h-28 w-28 rounded-full blur-2xl transition-opacity duration-500 group-hover:opacity-100 {{ $sc['ambient'] }}"></span>
                <div class="relative z-10 flex items-center gap-3.5">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition-transform duration-300 group-hover:scale-105 {{ $sc['iconBg'] }}">
                        <x-icon name="{{ $sc['icon'] }}" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">{{ $sc['label'] }}</p>
                        <p class="mt-0.5 text-2xl font-extrabold tracking-tight text-slate-900 tabular-nums dark:text-white"
                           data-countup="{{ $sc['value'] }}" data-countup-delay="{{ $dMs + 20 }}">{{ number_format($sc['value']) }}</p>
                    </div>
                </div>
                <div class="relative z-10 mt-3 -mx-5 -mb-5 pt-1">
                    <svg viewBox="0 0 240 52" class="h-11 w-full overflow-visible" preserveAspectRatio="none" aria-hidden="true">
                        <defs>
                            <linearGradient id="{{ $sc['gradId'] }}" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="{{ $sc['color'] }}" stop-opacity="{{ $sc['spark']['is_flat'] ? '0.10' : '0.32' }}" />
                                <stop offset="100%" stop-color="{{ $sc['color'] }}" stop-opacity="0.0" />
                            </linearGradient>
                        </defs>
                        <path class="lynk-spark-area" d="{{ $sc['spark']['area'] }}" fill="url(#{{ $sc['gradId'] }})" />
                        <path class="lynk-spark-line" pathLength="100" d="{{ $sc['spark']['line'] }}" fill="none" stroke="{{ $sc['color'] }}" stroke-width="2.2" stroke-linecap="round" />
                    </svg>
                </div>
            </a>
        @endforeach
    </div>

    {{-- Card KPI Personal (Hanya untuk Sales yang Sedang Login) --}}
    @if(isset($myKpi))
        <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-5 border-b border-slate-100 dark:border-slate-700/60 pb-4">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-2xl bg-accent-100 text-accent-700 dark:bg-accent-900/40 dark:text-accent-300 font-extrabold flex items-center justify-center text-sm shadow-sm">
                        🎯
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-bold text-slate-800 dark:text-white text-lg">{{ __('KPI Personal Saya') }}</h2>
                            <span class="text-xs px-2.5 py-0.5 rounded-full font-bold {{ $myKpi['overall_pct'] >= 100 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : ($myKpi['overall_pct'] >= 60 ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300') }}">
                                {{ $myKpi['overall_pct'] }}% {{ __('Achieved') }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-slate-400">{{ __('Pipeline:') }} <strong class="text-slate-700 dark:text-slate-200">{{ $myKpi['active_leads'] }} {{ __('Active Leads') }}</strong></span>
                    <span class="text-slate-300 dark:text-slate-600">·</span>
                    <span class="text-xs text-slate-400">{{ __('Conversion:') }} <strong class="text-slate-700 dark:text-slate-200">{{ $myKpi['conversion_rate'] }}%</strong></span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                {{-- Metric 1: Realisasi Closing dari uang masuk --}}
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-700/50">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Realisasi Closing (Lunas)') }}</span>
                        <span class="text-xs font-extrabold px-2 py-0.5 rounded-full {{ $myKpi['revenue_pct'] >= 100 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-accent-100 text-accent-700 dark:bg-accent-950/60 dark:text-accent-300' }}">
                            {{ $myKpi['revenue_pct'] }}%
                        </span>
                    </div>
                    <div class="flex items-baseline justify-between mb-1">
                        <p class="text-xl font-black text-slate-800 dark:text-white tabular-nums">
                            Rp {{ number_format($myKpi['actual_revenue'], 0, ',', '.') }}
                        </p>
                        <span class="text-xs text-slate-400 tabular-nums">
                            / Rp {{ number_format($myKpi['target_revenue'], 0, ',', '.') }}
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mb-2 tabular-nums">
                        {{ __('Closed deals') }}: <strong class="text-slate-700 dark:text-slate-200">{{ $myKpi['actual_deals'] }} PO</strong>
                    </p>
                    <div class="w-full bg-slate-200/80 dark:bg-slate-700 h-2.5 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-700 {{ $myKpi['revenue_pct'] >= 100 ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : 'bg-gradient-to-r from-accent-500 to-accent-600' }}" style="width: {{ min(100, $myKpi['revenue_pct']) }}%"></div>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-2 flex items-center justify-between">
                        @if($myKpi['revenue_shortfall'] > 0)
                            <span>{{ __('Target remaining:') }} <strong class="text-amber-600 dark:text-amber-400 tabular-nums">Rp {{ number_format($myKpi['revenue_shortfall'], 0, ',', '.') }}</strong></span>
                        @else
                            <span class="text-emerald-600 dark:text-emerald-400 font-bold">🎉 {{ __('Monthly target has been exceeded!') }}</span>
                        @endif
                        <span class="text-[10px] text-slate-400">{{ __('Month') }} {{ \Carbon\Carbon::create(null, $myKpi['period_month'], 1)->locale(app()->getLocale())->translatedFormat('F') }}</span>
                    </p>
                </div>

                {{-- Metric 2: Target Closing Deals / PO --}}
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-700/50">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Target Closing Deals (PO)') }}</span>
                        <span class="text-xs font-extrabold px-2 py-0.5 rounded-full {{ $myKpi['deals_pct'] >= 100 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300' }}">
                            {{ $myKpi['deals_pct'] }}%
                        </span>
                    </div>
                    <div class="flex items-baseline justify-between mb-2">
                        <p class="text-xl font-black text-slate-800 dark:text-white tabular-nums">
                            {{ $myKpi['actual_deals'] }} <span class="text-sm font-semibold text-slate-400">{{ __('Closing') }}</span>
                        </p>
                        <span class="text-xs text-slate-400 tabular-nums">
                            / {{ $myKpi['target_deals'] }} Deals
                        </span>
                    </div>
                    <div class="w-full bg-slate-200/80 dark:bg-slate-700 h-2.5 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-700 {{ $myKpi['deals_pct'] >= 100 ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : 'bg-gradient-to-r from-indigo-500 to-blue-500' }}" style="width: {{ min(100, $myKpi['deals_pct']) }}%"></div>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-2 flex items-center justify-between">
                        @if($myKpi['deals_shortfall'] > 0)
                            <span>{{ __('Target remaining:') }} <strong class="text-indigo-600 dark:text-indigo-400 tabular-nums">{{ $myKpi['deals_shortfall'] }} Deal / PO</strong> {{ __('remaining') }}</span>
                        @else
                            <span class="text-emerald-600 dark:text-emerald-400 font-bold">🚀 {{ __('Closing target achieved!') }}</span>
                        @endif
                        <span class="text-[10px] text-slate-400">{{ $myKpi['active_leads'] }} {{ __('active leads ready to close') }}</span>
                    </p>
                </div>

                {{-- Metric 3: Gross Margin --}}
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-700/50">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Gross Margin') }}</span>
                        @if(! $myKpi['margin_incomplete'])
                            <span class="text-xs font-extrabold px-2 py-0.5 rounded-full {{ $myKpi['margin_pct'] >= 100 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300' }}">
                                {{ $myKpi['margin_pct'] }}%
                            </span>
                        @endif
                    </div>
                    <div class="flex items-baseline justify-between mb-1">
                        <p class="text-xl font-black text-slate-800 dark:text-white tabular-nums">
                            Rp {{ number_format($myKpi['actual_margin'], 0, ',', '.') }}
                        </p>
                        <span class="text-xs text-slate-400 tabular-nums">
                            / Rp {{ number_format($myKpi['target_margin'], 0, ',', '.') }}
                        </span>
                    </div>
                    @if($myKpi['margin_incomplete'])
                        <p class="text-[11px] text-amber-600 dark:text-amber-400 mb-2">{{ __('Margin not yet complete - cost price is missing on some items.') }}</p>
                    @else
                        <div class="w-full bg-slate-200/80 dark:bg-slate-700 h-2.5 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-700 {{ $myKpi['margin_pct'] >= 100 ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : 'bg-gradient-to-r from-violet-500 to-purple-500' }}" style="width: {{ min(100, $myKpi['margin_pct']) }}%"></div>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-2">
                            @if($myKpi['margin_shortfall'] > 0)
                                {{ __('Target remaining:') }} <strong class="text-violet-600 dark:text-violet-400 tabular-nums">Rp {{ number_format($myKpi['margin_shortfall'], 0, ',', '.') }}</strong>
                            @else
                                <span class="text-emerald-600 dark:text-emerald-400 font-bold">🎉 {{ __('Margin target achieved!') }}</span>
                            @endif
                        </p>
                    @endif
                </div>
            </div>

            @if(!empty($myKpi['target_notes']))
                <div class="mt-4 p-3 rounded-xl bg-accent-50 dark:bg-accent-950/30 border border-accent-100 dark:border-accent-900/40 text-xs text-accent-900 dark:text-accent-200 flex items-start gap-2">
                    <span class="font-bold shrink-0">📌 {{ __('Management Note:') }}</span>
                    <span>{{ $myKpi['target_notes'] }}</span>
                </div>
            @endif
        </div>
    @endif

    {{-- Customer-Centric Hub: daftar customer milik sales + aksi cepat --}}
    @include('sales.partials.customer-hub', ['hubResetRoute' => route('sales.dashboard')])

    {{-- Peringkat Tim & Target Bulanan Sales --}}
    @if(isset($salesLeaderboard) && $salesLeaderboard->isNotEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6" data-reveal>
            <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                <h2 class="font-bold text-slate-800 dark:text-white text-lg">{{ __('Peringkat & Target Bulanan Sales') }}</h2>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                    {{ $salesLeaderboard->count() }} {{ __('Team Members') }}
                </span>
            </div>

            <div class="space-y-3">
                @foreach($salesLeaderboard as $rep)
                    @php
                        $isMe = (int) $rep['user']->id === (int) auth()->id();
                    @endphp
                    <div class="relative overflow-hidden rounded-2xl border p-4 transition-all duration-300 {{ $isMe ? 'bg-accent-50/50 dark:bg-accent-950/20 border-accent-400/60 shadow-sm ring-1 ring-accent-400/30' : 'bg-slate-50/60 dark:bg-slate-900/30 border-slate-100 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-700/20' }}">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            {{-- Rank & Profile --}}
                            <div class="flex items-center gap-3.5 min-w-[220px]">
                                <div class="w-8 text-center shrink-0">
                                    @if($rep['rank'] === 1)
                                        <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 font-black text-sm shadow-sm ring-2 ring-amber-400/40">1🥇</span>
                                    @elseif($rep['rank'] === 2)
                                        <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300 font-black text-sm shadow-sm ring-2 ring-slate-300">2🥈</span>
                                    @elseif($rep['rank'] === 3)
                                        <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-amber-900/10 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 font-black text-sm shadow-sm ring-2 ring-amber-700/30">3🥉</span>
                                    @else
                                        <span class="text-xs font-bold text-slate-400 dark:text-slate-500">#{{ $rep['rank'] }}</span>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="font-bold text-sm text-slate-800 dark:text-white truncate">{{ $rep['user']->name }}</p>
                                        @if($isMe)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-accent-600 text-white uppercase tracking-wider">
                                                {{ __('Anda') }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        {{ $rep['actual_deals'] }} {{ __('Closing Deal') }} · <span class="font-semibold text-slate-700 dark:text-slate-200 tabular-nums">Rp {{ number_format($rep['actual_revenue'], 0, ',', '.') }}</span>
                                    </p>
                                </div>
                            </div>

                            {{-- Progress Bar & Percentage --}}
                            <div class="flex-1 max-w-md w-full">
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span class="text-slate-400 text-[11px]">{{ __('Capaian Omset:') }} Rp {{ number_format($rep['target_revenue'], 0, ',', '.') }}</span>
                                    <span class="font-bold tabular-nums {{ $rep['revenue_pct'] >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-700 dark:text-slate-200' }}">
                                        {{ $rep['revenue_pct'] }}%
                                    </span>
                                </div>
                                <div class="w-full bg-slate-200 dark:bg-slate-700 h-2.5 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-500 {{ $rep['revenue_pct'] >= 100 ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : ($rep['revenue_pct'] >= 50 ? 'bg-gradient-to-r from-blue-500 to-indigo-500' : 'bg-gradient-to-r from-amber-500 to-orange-500') }}" style="width: {{ min(100, $rep['revenue_pct']) }}%"></div>
                                </div>
                            </div>

                            {{-- Badge Status --}}
                            <div class="shrink-0 text-right min-w-[100px]">
                                @if($rep['overall_pct'] >= 100)
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                        🏆 {{ __('Tercapai') }}
                                    </span>
                                @elseif($rep['actual_deals'] > 0)
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                        🚀 {{ __('On Track') }}
                                    </span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                        💪 {{ __('Kejar Target') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <h2 class="font-semibold text-slate-700 dark:text-slate-200">{{ __('My Schedule') }}</h2>
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

    {{-- Daily Report (dari Meeting hari ini) --}}
    @php
        $reportStatus = [
            'done' => ['label' => __('Report complete'), 'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
            'partial' => ['label' => __('Partially reported'), 'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'],
            'missing' => ['label' => __('Not reported'), 'class' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300'],
        ][$dailyReport['status']] ?? ['label' => __('Report complete'), 'class' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'];
    @endphp
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <div class="flex items-center gap-2">
                <h2 class="font-semibold text-slate-700 dark:text-slate-200">{{ __('Daily Report') }}</h2>
                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $reportStatus['class'] }}">{{ $reportStatus['label'] }}</span>
            </div>
            <a href="{{ route('sales.meetings.index') }}" class="text-sm font-medium text-accent-600 hover:text-accent-500">{{ __('Open tracker') }}</a>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Today') }}</p>
                <p class="mt-1.5 text-2xl font-extrabold text-slate-800 dark:text-white tabular-nums">{{ $dailyReport['total'] }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Report Sent') }}</p>
                <p class="mt-1.5 text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 tabular-nums">{{ $dailyReport['sent'] }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Report Pending') }}</p>
                <p class="mt-1.5 text-2xl font-extrabold text-amber-600 dark:text-amber-400 tabular-nums">{{ $dailyReport['pending'] }}</p>
            </div>
            <a href="{{ $dailyReport['pending'] > 0 ? route('sales.meetings.index', ['status' => 'pending']) : route('sales.meetings.create') }}"
               class="flex items-center justify-center gap-2 rounded-xl border border-dashed border-accent-300 px-4 py-3 text-sm font-semibold text-accent-700 transition hover:bg-accent-50 dark:border-accent-700 dark:text-accent-300 dark:hover:bg-accent-900/20">
                <x-icon name="add" class="h-4 w-4" />
                {{ $dailyReport['pending'] > 0 ? __('Send report') : __('New Meeting') }}
            </a>
        </div>
        @if($dailyReport['total'] === 0)
            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ __('No meeting today.') }}</p>
        @elseif($dailyReport['last_report_at'])
            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ __('Last report sent at :time.', ['time' => $dailyReport['last_report_at']->format('H:i')]) }}</p>
        @endif
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 mb-6" data-reveal>
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
                <h2 class="font-semibold text-slate-700 dark:text-slate-200">{{ __('Deal PO This Month') }}</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('My PO') }}</p>
                <p class="mt-0.5 text-2xl font-extrabold text-slate-800 tabular-nums dark:text-white">
                    Rp {{ number_format($dealPoTotal ?? 0, 0, ',', '.') }}
                    <span class="text-sm font-semibold text-slate-400">· {{ $dealPoDeals ?? 0 }} PO</span>
                </p>
            </div>
            <p class="text-right text-xs text-slate-500 dark:text-slate-400">{{ __('Company total (all Sales) per PT') }}</p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach($dealPoMonth ?? [] as $pt => $row)
                <div class="rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-3">
                    <span class="inline-flex px-1.5 py-0.5 rounded {{ \App\Models\Lead::PT_COLORS[$pt] ?? 'bg-indigo-50 text-indigo-700' }} text-[11px] font-semibold">{{ $pt }}</span>
                    <p class="mt-1.5 text-sm font-bold text-slate-800 tabular-nums dark:text-slate-100">Rp {{ number_format((float) ($row['value'] ?? 0), 0, ',', '.') }}</p>
                    <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">{{ (int) ($row['count'] ?? 0) }} PO</p>
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

    <div style="--d: 580ms" class="lynk-enter w-full rounded-2xl border border-slate-100 bg-white p-5 sm:p-6 shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90">
        <h2 class="font-semibold text-slate-800 dark:text-slate-100 mb-4">{{ __('My Lead Status') }}</h2>
        @if(($funnelTotal ?? 0) > 0)
            <div class="flex flex-col sm:flex-row items-center justify-between gap-6 max-w-xl mx-auto">
                <div class="relative w-44 shrink-0">
                    <x-donut-chart :data="$donutSales" :size="176" :strokeWidth="24" :scroll="true" />
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center px-6">
                        <span class="text-2xl sm:text-3xl font-extrabold text-slate-900 tabular-nums dark:text-white" data-countup="{{ $funnelTotal }}" data-countup-delay="600">{{ $funnelTotal }}</span>
                        <span class="mt-0.5 text-[11px] font-medium text-slate-400 dark:text-slate-400">{{ __('Total Leads') }}</span>
                    </div>
                </div>
                <div class="w-full flex-1 space-y-2">
                    @foreach($donutSales as $seg)
                        <a href="{{ route('sales.my-leads', ['status' => $seg['key']]) }}"
                           class="flex items-center justify-between gap-2 rounded-xl px-3 py-2 text-xs transition hover:bg-slate-50/90 dark:hover:bg-slate-700/35">
                            <span class="flex min-w-0 items-center gap-2.5">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $seg['color'] }}"></span>
                                <span class="truncate font-medium text-slate-600 dark:text-slate-300">{{ $seg['label'] }}</span>
                            </span>
                            <span class="shrink-0 font-bold text-slate-800 tabular-nums dark:text-slate-100">
                                <span data-countup="{{ (int) $seg['value'] }}" data-countup-delay="620">{{ $seg['value'] }}</span>
                                <span class="font-normal text-slate-400">({{ $funnelTotal > 0 ? round($seg['value'] / $funnelTotal * 100) : 0 }}%)</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @else
            <div class="rounded-xl bg-slate-50/70 dark:bg-slate-900/30 px-5 py-8 text-center">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('No leads assigned to you yet.') }}</p>
            </div>
        @endif
    </div>
</x-app-layout>
