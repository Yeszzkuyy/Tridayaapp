<x-app-layout>
    @include('partials.lynk-dashboard-motion')

    {{-- Open header: judul menyatu dengan background, tanggal + aksi di kanan. --}}
    <div class="mb-6 flex flex-col gap-3 pt-1 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
            {{ __('Inside Sales') }}
        </h1>
        <div class="flex flex-wrap items-center gap-2.5">
            <div class="inline-flex items-center gap-2 rounded-full border border-slate-200/80 bg-white px-4 py-2 text-xs font-semibold text-slate-600 shadow-sm dark:border-slate-800/70 dark:bg-slate-800/90 dark:text-slate-300">
                <x-icon name="calendar" class="h-3.5 w-3.5 text-accent-600 dark:text-accent-400" />
                <span>{{ now()->locale(app()->getLocale())->translatedFormat('l, d F Y') }}</span>
            </div>
            <x-icon-button as="a" icon="leads" href="{{ route('lead-tasks.index') }}" title="{{ __('All Requests') }}" />
        </div>
    </div>

    {{-- Ringkasan antrean --}}
    @php
        $cards = [
            [
                'label' => __('New Request'),
                'value' => (int) $kpi['open'],
                'icon' => 'message',
                'ambient' => 'bg-blue-500/15 dark:bg-blue-500/20',
                'iconBg' => 'bg-blue-500/10 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300',
                'color' => '#3b82f6',
                'gradId' => 'inside-spark-1',
                'spark' => \App\Support\SparklineBuilder::fromSeries($spark['open'], (int) $kpi['open']),
            ],
            [
                'label' => __('In Progress'),
                'value' => (int) $kpi['in_progress'],
                'icon' => 'activity',
                'ambient' => 'bg-amber-500/15 dark:bg-amber-500/20',
                'iconBg' => 'bg-amber-500/10 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300',
                'color' => '#f59e0b',
                'gradId' => 'inside-spark-2',
                'spark' => \App\Support\SparklineBuilder::fromSeries($spark['in_progress'], (int) $kpi['in_progress']),
            ],
            [
                'label' => __('Quotation Created'),
                'value' => (int) $kpi['quotation_ready'],
                'icon' => 'file-text',
                'ambient' => 'bg-violet-500/15 dark:bg-violet-500/20',
                'iconBg' => 'bg-violet-500/10 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300',
                'color' => '#8b5cf6',
                'gradId' => 'inside-spark-3',
                'spark' => \App\Support\SparklineBuilder::fromSeries($spark['quotation_ready'], (int) $kpi['quotation_ready']),
            ],
            [
                'label' => __('Done'),
                'value' => (int) $kpi['done'],
                'icon' => 'check-circle',
                'ambient' => 'bg-emerald-500/15 dark:bg-emerald-500/20',
                'iconBg' => 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
                'color' => '#10b981',
                'gradId' => 'inside-spark-4',
                'spark' => \App\Support\SparklineBuilder::fromSeries($spark['done'], (int) $kpi['done']),
            ],
        ];
    @endphp

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($cards as $idx => $card)
            @php $delay = 100 + ($idx * 80); @endphp
            <div style="--d: {{ $delay }}ms"
                 class="lynk-enter lynk-kpi-card group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg dark:border-slate-800/60 dark:bg-slate-800/90">
                <span aria-hidden="true" class="pointer-events-none absolute -right-10 -top-10 h-28 w-28 rounded-full blur-2xl transition-opacity duration-500 group-hover:opacity-100 {{ $card['ambient'] }}"></span>
                <div class="relative z-10 flex items-center gap-3.5">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition-transform duration-300 group-hover:scale-105 {{ $card['iconBg'] }}">
                        <x-icon name="{{ $card['icon'] }}" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                        <p class="mt-0.5 text-2xl font-extrabold tracking-tight text-slate-900 tabular-nums dark:text-white"
                           data-countup="{{ $card['value'] }}" data-countup-delay="{{ $delay + 20 }}">{{ number_format($card['value']) }}</p>
                    </div>
                </div>
                <div class="relative z-10 -mx-5 -mb-5 mt-3 pt-1">
                    <svg viewBox="0 0 240 52" class="h-11 w-full overflow-visible" preserveAspectRatio="none" aria-hidden="true">
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

    {{-- Antrean request --}}
    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-700/60">
            <h2 class="font-semibold text-slate-700 dark:text-slate-200">{{ __('Request Queue') }}</h2>
            <span class="text-xs text-slate-400">{{ $tasks->count() }} {{ __('request(s)') }}</span>
        </div>

        @if($tasks->isEmpty())
            <div class="px-5 py-12 text-center">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('No requests yet.') }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('New requests from Sales will appear here.') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:bg-slate-900/40">
                        <tr>
                            <th class="px-5 py-3">{{ __('Request') }}</th>
                            <th class="px-5 py-3">{{ __('Customer') }}</th>
                            <th class="px-5 py-3">{{ __('Sales') }}</th>
                            <th class="px-5 py-3">{{ __('Due') }}</th>
                            <th class="px-5 py-3">{{ __('Status') }}</th>
                            <th class="px-5 py-3">{{ __('Quotation') }}</th>
                            <th class="px-5 py-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($tasks as $task)
                            @php
                                $statusClass = match ($task->status) {
                                    'in_progress' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                                    'review' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
                                    'done' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                                    default => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                                };
                            @endphp
                            <tr class="transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-700/35">
                                <td class="px-5 py-3">
                                    <p class="max-w-[260px] truncate font-medium text-slate-800 dark:text-slate-100">{{ $task->title }}</p>
                                    <p class="mt-0.5 text-xs text-slate-400">{{ $task->items_count }} {{ __('item(s)') }}</p>
                                </td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $task->lead?->customer?->name ?? '-' }}</td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $task->creator?->name ?? '-' }}</td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $task->due_date?->format('d M Y') ?? '-' }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $statusClass }}">
                                        {{ \App\Models\LeadTask::statusLabel($task->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    @if($task->proposal)
                                        @if($task->proposal->pdf_path)
                                            <a href="{{ route('inside-sales.quotations.pdf', $task) }}" class="font-semibold text-accent-600 hover:underline dark:text-accent-400">
                                                {{ $task->proposal->proposal_number }}
                                            </a>
                                        @else
                                            <span class="font-semibold text-slate-600 dark:text-slate-300">{{ $task->proposal->proposal_number }}</span>
                                        @endif
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <x-icon-button as="a" icon="view" href="{{ route('lead-tasks.show', $task) }}" title="{{ __('View details') }}" />
                                        @if(! $task->proposal && $task->items_count > 0)
                                            <x-icon-button as="a" icon="sparkles" href="{{ route('inside-sales.quotations.create', $task) }}" title="{{ __('Create Quotation') }}" />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
