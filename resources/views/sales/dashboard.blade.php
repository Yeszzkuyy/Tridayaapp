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
                                {{ $myKpi['overall_pct'] }}% Capaian
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ __('Tracking progres target pribadi Anda untuk periode :month :year (Hanya Anda yang melihat)', ['month' => \Carbon\Carbon::create(null, $myKpi['period_month'], 1)->locale(app()->getLocale())->translatedFormat('F'), 'year' => $myKpi['period_year']]) }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-slate-400">Pipeline: <strong class="text-slate-700 dark:text-slate-200">{{ $myKpi['active_leads'] }} Lead Aktif</strong></span>
                    <span class="text-slate-300 dark:text-slate-600">·</span>
                    <span class="text-xs text-slate-400">Konversi: <strong class="text-slate-700 dark:text-slate-200">{{ $myKpi['conversion_rate'] }}%</strong></span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- Metric 1: Target Omset --}}
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-700/50">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Target Omset') }}</span>
                        <span class="text-xs font-extrabold px-2 py-0.5 rounded-full {{ $myKpi['revenue_pct'] >= 100 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-accent-100 text-accent-700 dark:bg-accent-950/60 dark:text-accent-300' }}">
                            {{ $myKpi['revenue_pct'] }}%
                        </span>
                    </div>
                    <div class="flex items-baseline justify-between mb-2">
                        <p class="text-xl font-black text-slate-800 dark:text-white tabular-nums">
                            Rp {{ number_format($myKpi['actual_revenue'], 0, ',', '.') }}
                        </p>
                        <span class="text-xs text-slate-400 tabular-nums">
                            / Rp {{ number_format($myKpi['target_revenue'], 0, ',', '.') }}
                        </span>
                    </div>
                    <div class="w-full bg-slate-200/80 dark:bg-slate-700 h-2.5 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-700 {{ $myKpi['revenue_pct'] >= 100 ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : 'bg-gradient-to-r from-accent-500 to-accent-600' }}" style="width: {{ min(100, $myKpi['revenue_pct']) }}%"></div>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-2 flex items-center justify-between">
                        @if($myKpi['revenue_shortfall'] > 0)
                            <span>Sisa target: <strong class="text-amber-600 dark:text-amber-400 tabular-nums">Rp {{ number_format($myKpi['revenue_shortfall'], 0, ',', '.') }}</strong></span>
                        @else
                            <span class="text-emerald-600 dark:text-emerald-400 font-bold">🎉 Target omset telah terlampaui!</span>
                        @endif
                        <span class="text-[10px] text-slate-400">Bulan {{ \Carbon\Carbon::create(null, $myKpi['period_month'], 1)->locale(app()->getLocale())->translatedFormat('F') }}</span>
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
                            {{ $myKpi['actual_deals'] }} <span class="text-sm font-semibold text-slate-400">Closing</span>
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
                            <span>Sisa target: <strong class="text-indigo-600 dark:text-indigo-400 tabular-nums">{{ $myKpi['deals_shortfall'] }} Deal / PO</strong> lagi</span>
                        @else
                            <span class="text-emerald-600 dark:text-emerald-400 font-bold">🚀 Target closing sukses tercapai!</span>
                        @endif
                        <span class="text-[10px] text-slate-400">{{ $myKpi['active_leads'] }} leads aktif siap closing</span>
                    </p>
                </div>
            </div>

            @if(!empty($myKpi['target_notes']))
                <div class="mt-4 p-3 rounded-xl bg-accent-50 dark:bg-accent-950/30 border border-accent-100 dark:border-accent-900/40 text-xs text-accent-900 dark:text-accent-200 flex items-start gap-2">
                    <span class="font-bold shrink-0">📌 Catatan Management:</span>
                    <span>{{ $myKpi['target_notes'] }}</span>
                </div>
            @endif
        </div>
    @endif

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

    {{-- Peringkat Tim & Target Bulanan Sales --}}
    @if(isset($salesLeaderboard) && $salesLeaderboard->isNotEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6" data-reveal>
            <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                <div>
                    <h2 class="font-bold text-slate-800 dark:text-white text-lg">{{ __('Peringkat & Target Bulanan Sales') }}</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        {{ __('Papan peringkat performa sales bulan :month :year berdasarkan capaian target omset dan closing deal.', ['month' => now()->locale(app()->getLocale())->translatedFormat('F'), 'year' => now()->year]) }}
                    </p>
                </div>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                    {{ $salesLeaderboard->count() }} Anggota Tim
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
                                        {{ $rep['actual_deals'] }} Closing Deal · <span class="font-semibold text-slate-700 dark:text-slate-200 tabular-nums">Rp {{ number_format($rep['actual_revenue'], 0, ',', '.') }}</span>
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

    {{-- Meeting tracker (dipisah dari Follow Up) --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 mb-6">
        <div class="flex flex-wrap items-end justify-between gap-2 mb-4">
            <div>
                <h2 class="font-semibold text-slate-700 dark:text-slate-200">{{ __('Meeting') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Daily meeting tracker summary.') }}</p>
            </div>
            <a href="{{ route('sales.meetings.index') }}" class="text-sm font-medium text-accent-600 hover:text-accent-500">{{ __('Open tracker') }}</a>
        </div>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Today') }}</p>
                <p class="mt-1.5 text-2xl font-extrabold text-slate-800 dark:text-white">{{ $meetingsToday ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Report Pending') }}</p>
                <p class="mt-1.5 text-2xl font-extrabold text-amber-600 dark:text-amber-400">{{ $meetingsPending ?? 0 }}</p>
            </div>
            <a href="{{ route('sales.meetings.create') }}"
               class="flex items-center justify-center gap-2 rounded-xl border border-dashed border-accent-300 px-4 py-3 text-sm font-semibold text-accent-700 transition hover:bg-accent-50 dark:border-accent-700 dark:text-accent-300 dark:hover:bg-accent-900/20">
                <x-icon name="add" class="h-4 w-4" />
                {{ __('New Meeting') }}
            </a>
        </div>
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
