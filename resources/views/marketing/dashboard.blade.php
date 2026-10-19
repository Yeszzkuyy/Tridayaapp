<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Dashboard Marketing') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Lead and pipeline performance summary') }}</p>
        </div>
        <x-icon-button as="a" icon="leads" href="{{ route('leads.index') }}" title="{{ __('View Leads') }}" />
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
                                @php($active = $activeRange === $from . '|' . $to)
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
                            @php($active = $activeRange === $from . '|' . $to)
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

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="relative h-full flex flex-col justify-between overflow-hidden bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
            <div class="absolute -right-8 -top-8 w-24 h-24 rounded-full bg-blue-500/5"></div>
            <div class="relative flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Leads This Month') }}</p>
                    <p class="text-2xl font-extrabold text-slate-800 dark:text-white mt-1.5">{{ $stats['this_month'] }}</p>
                    <p class="text-xs mt-1 {{ $stats['this_month'] >= $stats['last_month'] ? 'text-green-600' : 'text-red-500' }}">
                        {{ $stats['this_month'] >= $stats['last_month'] ? '▲' : '▼' }} {{ __('last month:') }} {{ $stats['last_month'] }}
                    </p>
                </div>
                <div class="shrink-0 p-2.5 rounded-xl bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
            </div>
        </div>

        <div class="relative h-full flex flex-col justify-between overflow-hidden bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
            <div class="absolute -right-8 -top-8 w-24 h-24 rounded-full bg-indigo-500/5"></div>
            <div class="relative flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Total Leads') }}</p>
                    <p class="text-2xl font-extrabold text-slate-800 dark:text-white mt-1.5">{{ $stats['total'] }}</p>
                </div>
                <div class="shrink-0 p-2.5 rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
            </div>
        </div>

        <div class="relative h-full flex flex-col justify-between overflow-hidden bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
            <div class="absolute -right-8 -top-8 w-24 h-24 rounded-full bg-amber-500/5"></div>
            <div class="relative flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Active Leads') }}</p>
                    <p class="text-2xl font-extrabold text-slate-800 dark:text-white mt-1.5">{{ $stats['active'] }}</p>
                </div>
                <div class="shrink-0 p-2.5 rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
            </div>
        </div>

        <div class="relative h-full flex flex-col justify-between overflow-hidden bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
            <div class="absolute -right-8 -top-8 w-24 h-24 rounded-full bg-green-500/5"></div>
            <div class="relative flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Won') }}</p>
                    <p class="text-2xl font-extrabold text-slate-800 dark:text-white mt-1.5">{{ $stats['won'] }}</p>
                    <p class="text-xs text-red-500 mt-1">{{ __('Lost:') }} {{ $stats['lost'] }}</p>
                </div>
                <div class="shrink-0 p-2.5 rounded-xl bg-green-100 text-green-600 dark:bg-green-900/40 dark:text-green-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
        </div>

        <div class="relative h-full flex flex-col justify-between overflow-hidden bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
            <div class="absolute -right-8 -top-8 w-24 h-24 rounded-full bg-cyan-500/5"></div>
            <div class="relative flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Conversion Rate') }}</p>
                    <p class="text-2xl font-extrabold text-slate-800 dark:text-white mt-1.5">{{ $stats['conversion'] }}%</p>
                    <p class="text-xs text-slate-400 mt-1">{{ __('won ÷ (won + lost)') }}</p>
                </div>
                <div class="shrink-0 p-2.5 rounded-xl bg-cyan-100 text-cyan-600 dark:bg-cyan-900/40 dark:text-cyan-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941"></path></svg>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Tren lead masuk --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6" data-reveal>
            <h2 class="font-semibold text-slate-700 dark:text-slate-200 mb-4">{{ __('Incoming Leads') }} — {{ $dateFrom }} {{ __('s/d') }} {{ $dateTo }}</h2>
            @php($maxTrend = max($trend->max('total'), 1))
            <div class="flex items-end justify-between gap-3 h-44">
                @foreach($trend as $month)
                    <div class="flex-1 flex flex-col items-center gap-1 h-full justify-end">
                        <span class="text-xs font-semibold text-slate-600">{{ $month->total }}</span>
                        <div class="w-full max-w-12 rounded-t-[4px] bg-accent-500" data-grow-h
                             style="--h: {{ max(round($month->total / $maxTrend * 100), 2) }}%; --d: {{ $loop->index * 120 }}ms"></div>
                        <span class="text-[11px] text-slate-500 whitespace-nowrap">{{ $month->label }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Lead per sumber --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6" data-reveal>
            <h2 class="font-semibold text-slate-700 dark:text-slate-200 mb-4">{{ __('Leads by Source') }}</h2>
            @if($perSource->isEmpty())
                <p class="text-sm text-slate-500 py-8 text-center">{{ __('No lead data yet.') }}</p>
            @else
                @php($maxSource = max($perSource->max('total'), 1))
                <div class="space-y-3">
                    @foreach($perSource as $row)
                        <div>
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="text-slate-600 capitalize">{{ str_replace('_', ' ', $row->source) }}</span>
                                <span class="font-semibold text-slate-700">{{ $row->total }}</span>
                            </div>
                            <div class="h-2.5 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                <div class="h-full rounded-full bg-accent-500" data-grow-w
                                     style="--w: {{ round($row->total / $maxSource * 100) }}%; --d: {{ $loop->index * 120 }}ms"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Donut Lead per Status (SVG, tanpa ApexCharts) + tabel dinamis --}}
    <div class="w-full bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 mt-4" data-reveal x-data="{
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
        <h2 class="font-semibold text-slate-700 dark:text-slate-200 mb-4">{{ __('Lead Pipeline by Status') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 -mt-2 mb-4">{{ __('Click a segment to view lead details for that status.') }}</p>
        <div class="relative w-full max-w-[320px] mx-auto">
            <div id="status-donut-chart" class="w-full">
                <x-donut-chart :data="$donutMarketing" :size="280" :strokeWidth="34" :scroll="true" />
            </div>
            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center px-10">
                <span class="text-xs font-medium text-slate-400 dark:text-slate-500 truncate max-w-full" x-text="activeSeg() ? activeSeg().label : '{{ __('Total Leads') }}'">{{ __('Total Leads') }}</span>
                <span class="mt-0.5 text-3xl font-bold text-slate-800 tabular-nums dark:text-slate-100" x-text="activeSeg() ? activeSeg().value : total">{{ $stats['total'] }}</span>
                <span x-show="activeSeg() && total > 0" class="text-sm font-medium text-slate-400" x-text="activeSeg() ? '[' + Math.round(activeSeg().value / total * 100) + '%]' : ''"></span>
            </div>
        </div>

        {{-- Legenda interaktif (hover menyorot segmen, klik membuka detail) --}}
        <div class="mt-4 grid grid-cols-1 gap-1 sm:grid-cols-2">
            @foreach($donutMarketing as $seg)
                <button type="button" @click="select('{{ $seg['key'] }}')"
                        class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm transition hover:bg-slate-50 dark:hover:bg-slate-700/40"
                        :class="selectedStatus === '{{ $seg['key'] }}' && 'bg-slate-50 dark:bg-slate-700/40 ring-1 ring-slate-200 dark:ring-slate-600'">
                    <span class="flex min-w-0 items-center gap-2.5">
                        <span class="h-3 w-3 shrink-0 rounded-full" style="background-color: {{ $seg['color'] }}"></span>
                        <span class="truncate font-medium text-slate-600 dark:text-slate-300">{{ $seg['label'] }}</span>
                    </span>
                    <span class="shrink-0 font-semibold text-slate-700 tabular-nums dark:text-slate-200">{{ $seg['value'] }}
                        <span class="font-medium text-slate-400">· {{ $funnelTotal > 0 ? round($seg['value'] / $funnelTotal * 100) : 0 }}%</span>
                    </span>
                </button>
            @endforeach
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
