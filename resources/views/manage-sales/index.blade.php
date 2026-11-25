    <x-app-layout>
    @include('partials.lynk-dashboard-motion')

    <div class="lynk-enter flex flex-col gap-3 pt-1 mb-6 sm:flex-row sm:items-center sm:justify-between" style="--d: 0ms">
        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('Manage Sales') }}</h1>
        <div class="flex flex-wrap items-center gap-2">
            <x-icon-button as="a" icon="calendar" href="{{ route('sales.schedules.index') }}" label="{{ __('Sales Agenda') }}" title="{{ __('Sales Agenda') }}" />
            <div class="inline-flex w-fit items-center gap-2 rounded-full border border-slate-200/80 bg-white px-4 py-2 text-xs font-semibold text-slate-600 shadow-sm dark:border-slate-800/70 dark:bg-slate-800/90 dark:text-slate-300">
                <x-icon name="calendar" class="h-3.5 w-3.5 text-accent-600 dark:text-accent-400" />
                <span>{{ now()->locale(app()->getLocale())->translatedFormat('l, d F Y') }}</span>
            </div>
        </div>
    </div>

    @include('manage-sales.partials.tabs')

    @php
        $allLeadSeries = \App\Support\SparklineBuilder::monthlyCounts(\App\Models\Lead::query(), 'created_at');
        $unassignedSeries = \App\Support\SparklineBuilder::monthlyCounts(\App\Models\Lead::whereNull('assigned_to'), 'created_at');
        $wonLeadSeries = \App\Support\SparklineBuilder::monthlyCounts(\App\Models\Lead::where('status', 'won'), 'updated_at');
        $lostLeadSeries = \App\Support\SparklineBuilder::monthlyCounts(\App\Models\Lead::where('status', 'lost'), 'updated_at');

        $mgmtSalesCards = [
            [
                'label' => __('Total Leads'),
                'value' => (int) ($stats['total'] ?? 0),
                'icon' => 'users',
                'ambient' => 'bg-violet-500/15 dark:bg-violet-500/20',
                'iconBg' => 'bg-violet-500/10 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300',
                'color' => '#8b5cf6',
                'gradId' => 'mng-spark-1',
                'spark' => \App\Support\SparklineBuilder::fromSeries($allLeadSeries, (int) ($stats['total'] ?? 0)),
            ],
            [
                'label' => __('Not assigned yet'),
                'value' => (int) ($stats['unassigned'] ?? 0),
                'icon' => 'bolt',
                'ambient' => 'bg-rose-500/15 dark:bg-rose-500/20',
                'iconBg' => 'bg-rose-500/10 text-rose-600 dark:bg-rose-500/15 dark:text-rose-300',
                'color' => '#f43f5e',
                'gradId' => 'mng-spark-2',
                'spark' => \App\Support\SparklineBuilder::fromSeries($unassignedSeries, (int) ($stats['unassigned'] ?? 0)),
            ],
            [
                'label' => __('Won This Month'),
                'value' => (int) ($stats['won_month'] ?? 0),
                'icon' => 'check-circle',
                'ambient' => 'bg-emerald-500/15 dark:bg-emerald-500/20',
                'iconBg' => 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
                'color' => '#10b981',
                'gradId' => 'mng-spark-3',
                'spark' => \App\Support\SparklineBuilder::fromSeries($wonLeadSeries, (int) ($stats['won_month'] ?? 0)),
            ],
            [
                'label' => __('Lost This Month'),
                'value' => (int) ($stats['lost_month'] ?? 0),
                'icon' => 'activity',
                'ambient' => 'bg-amber-500/15 dark:bg-amber-500/20',
                'iconBg' => 'bg-amber-500/10 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300',
                'color' => '#f59e0b',
                'gradId' => 'mng-spark-4',
                'spark' => \App\Support\SparklineBuilder::fromSeries($lostLeadSeries, (int) ($stats['lost_month'] ?? 0)),
            ],
        ];
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        @foreach($mgmtSalesCards as $idx => $mc)
            @php $dMs = 100 + ($idx * 80); @endphp
            <div style="--d: {{ $dMs }}ms"
                 class="lynk-enter lynk-kpi-card group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg dark:border-slate-800/60 dark:bg-slate-800/90">
                <span aria-hidden="true" class="pointer-events-none absolute -right-10 -top-10 h-28 w-28 rounded-full blur-2xl transition-opacity duration-500 group-hover:opacity-100 {{ $mc['ambient'] }}"></span>
                <div class="relative z-10 flex items-center gap-3.5">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition-transform duration-300 group-hover:scale-105 {{ $mc['iconBg'] }}">
                        <x-icon name="{{ $mc['icon'] }}" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">{{ $mc['label'] }}</p>
                        <p class="mt-0.5 text-2xl font-extrabold tracking-tight text-slate-900 tabular-nums dark:text-white"
                           data-countup="{{ $mc['value'] }}" data-countup-delay="{{ $dMs + 20 }}">{{ number_format($mc['value']) }}</p>
                    </div>
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

        <div style="--d: 420ms" class="lynk-enter lynk-kpi-card rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg dark:border-slate-800/60 dark:bg-slate-800/90">
            <div class="flex items-center gap-3.5">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-pink-500/10 text-pink-600 dark:bg-pink-500/15 dark:text-pink-300">
                    <x-icon name="phone" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <p class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Overdue Leads') }}</p>
                    <p class="mt-0.5 text-2xl font-extrabold tracking-tight text-slate-900 tabular-nums dark:text-white"
                       data-countup="{{ (int) ($stats['overdue'] ?? 0) }}" data-countup-delay="440">{{ $stats['overdue'] ?? 0 }}</p>
                </div>
            </div>
            @if(($bySales ?? collect())->isNotEmpty())
                <div class="mt-3 space-y-1 pt-2">
                    @foreach($bySales as $row)
                        <p class="flex justify-between text-xs text-slate-500 dark:text-slate-400">
                            @if($row->assignee)
                                <a href="{{ route('manage-sales.sales-rep', $row->assignee) }}" class="truncate transition-colors hover:text-accent-600 dark:hover:text-accent-400">{{ $row->assignee->name }}</a>
                            @else
                                <span class="truncate">-</span>
                            @endif
                            <span class="font-bold text-slate-700 dark:text-slate-200 tabular-nums">{{ $row->total }}</span>
                        </p>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600">
        <div class="p-5 border-b dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-t-2xl">
            <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="text-sm font-medium text-slate-500">{{ __('Search Customer') }}</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="{{ __('Customer name...') }}"
                           class="mt-1 w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-500">{{ __('Assignment Status') }}</label>
                    <x-glide-select name="assignment" class="mt-1" :label="__('Assignment Status')" :empty-label="__('All')"
                        :options="[['value' => 'new', 'label' => __('NEW (Unassigned)')], ['value' => 'assigned', 'label' => __('ASSIGNED')]]"
                        :value="request('assignment', '')" autosubmit />
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-500">{{ __('Sales Activity') }}</label>
                    <x-glide-select name="touched" class="mt-1" :label="__('Sales Activity')" :empty-label="__('All')"
                        :options="[['value' => 'yes', 'label' => __('Touched')], ['value' => 'no', 'label' => __('Untouched')]]"
                        :value="request('touched', '')" autosubmit />
                </div>
                <div class="sm:col-span-2 lg:col-span-1 flex items-end gap-2">
                    <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
                    <x-icon-button as="a" icon="reset" href="{{ route('manage-sales.index') }}" title="{{ __('Reset') }}" />
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Lead / Customer') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Status') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Activity') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Requirement') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Entry Date') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Lead from PT') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Assign / Direct to Sales') }}</th>
                        <th class="px-6 py-4 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-600">
                    @forelse($leads as $lead)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition align-middle" data-lead-row="{{ $lead->id }}">
                            <td class="px-6 py-4">
                                <div class="font-medium text-slate-900">{{ $lead->customer->name ?? __('N/A') }}</div>
                                <div class="text-sm text-slate-500">
                                    @if($lead->pt_group)<span class="inline-flex px-1.5 py-0.5 rounded {{ \App\Models\Lead::PT_COLORS[$lead->pt_group] ?? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' }} text-[11px] font-semibold mr-1">{{ $lead->pt_group }}</span>@endif
                                    {{ $lead->customer?->picName() ? 'PIC: '.$lead->customer->picName() : '' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center" data-assignee-cell>
                                @if($lead->assignee)
                                    <div class="inline-flex items-center justify-center gap-2">
                                        <x-user-avatar :user="$lead->assignee" size="w-7 h-7" text="text-[11px]" color="green" :clickable="false" />
                                        <div class="flex flex-col items-start text-left leading-tight">
                                            <span class="text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $lead->assignee->name }}</span>
                                            <span class="text-[10px] uppercase tracking-wide text-green-600 dark:text-green-400">{{ __('Assigned') }}</span>
                                        </div>
                                    </div>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-700 dark:text-blue-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                        {{ __('New') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                @php $touched = ($lead->follow_ups_count ?? 0) > 0; @endphp
                                @if($touched)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300 whitespace-nowrap">
                                        <x-icon name="check-circle" class="h-3.5 w-3.5" />
                                        {{ __('Touched') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300 whitespace-nowrap">
                                        <span class="h-1.5 w-1.5 rounded-full bg-yellow-500"></span>
                                        {{ __('Untouched') }}
                                    </span>
                                @endif
                                <span class="block mt-1 text-[11px] text-slate-500 whitespace-nowrap">
                                    {{ $lead->follow_ups_count ?? 0 }} {{ __('follow ups') }}
                                </span>
                                @if($lead->last_follow_up_at)
                                    <span class="block text-[11px] text-slate-400 whitespace-nowrap">
                                        {{ __('Last') }}: {{ \Carbon\Carbon::parse($lead->last_follow_up_at)->format('d M Y') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-left max-w-[260px]">
                                <x-requirement-cell :text="$lead->kebutuhan" />
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($lead->incoming_date)
                                    <span class="text-sm text-slate-700">{{ $lead->incoming_date->format('d M Y') }}</span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-left">
                                @if($lead->pt_group)
                                    <span class="inline-flex px-1.5 py-0.5 rounded {{ \App\Models\Lead::PT_COLORS[$lead->pt_group] ?? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' }} text-[11px] font-semibold">{{ $lead->pt_group }}</span>
                                @else
                                    <span class="text-sm text-slate-400 dark:text-slate-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-left">
                                @if(in_array($lead->status, ['won', 'lost'], true))
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 dark:text-slate-300">
                                        <x-icon name="check-circle" class="h-4 w-4" />
                                        {{ __('Lead closed') }}
                                    </span>
                                    <span class="block mt-1 text-[11px] text-slate-400">{{ __('Cannot be reassigned') }}</span>
                                @else
                                    @php
                                        $hasProgress = $lead->acknowledged_at !== null
                                            || ($lead->follow_ups_count ?? 0) > 0
                                            || ($lead->sales_schedules_count ?? 0) > 0
                                            || ($lead->proposals_count ?? 0) > 0
                                            || ($lead->technical_requests_count ?? 0) > 0;
                                        $confirmDetails = array_values(array_filter([
                                            ['label' => __('Customer'), 'value' => $lead->customer->name ?? __('N/A')],
                                            ['label' => __('Stage'), 'value' => \App\Models\Lead::salesStatusLabel($lead->status)],
                                            $lead->assignee ? ['label' => __('Current sales'), 'value' => $lead->assignee->name] : null,
                                            ($lead->follow_ups_count ?? 0) > 0 ? ['label' => __('Follow ups'), 'value' => (string) $lead->follow_ups_count] : null,
                                            ($lead->sales_schedules_count ?? 0) > 0 ? ['label' => __('Meetings'), 'value' => (string) $lead->sales_schedules_count] : null,
                                            ($lead->proposals_count ?? 0) > 0 ? ['label' => __('Quotation'), 'value' => $lead->proposals_count.($lead->latest_proposal_status ? ' · '.\App\Models\Proposal::statusLabel($lead->latest_proposal_status) : '')] : null,
                                            ($lead->technical_requests_count ?? 0) > 0 ? ['label' => __('Engineer requests'), 'value' => (string) $lead->technical_requests_count] : null,
                                            ['label' => __('Move to'), 'value' => __('Selected sales')],
                                        ]));
                                    @endphp
                                    @can('manage-sales-leads')
                                        <form action="{{ route('manage-sales.assign', $lead) }}" method="POST" class="flex items-center gap-2"
                                              data-ajax data-assign-form
                                              @if(request('assignment') === 'new') data-ajax-remove="tr" @endif
                                              data-confirm="{{ $hasProgress
                                                  ? __('This lead already has progress. Move it to the selected sales? History stays with the lead, but the new sales becomes the owner.')
                                                  : __('Move this lead to the selected sales?') }}"
                                              data-confirm-title="{{ __('Move lead to another sales?') }}"
                                              data-confirm-tone="warning"
                                              data-confirm-label="{{ __('Yes, move lead') }}"
                                              data-confirm-details="{{ json_encode($confirmDetails, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }}">
                                            @csrf
                                            <x-glide-select name="assigned_to" size="sm" class="w-40" :label="__('Assign to Sales')"
                                                :placeholder="__('— Select Sales —')"
                                                :options="$salesUsers->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all()"
                                                :value="$lead->assigned_to" required />
                                            <x-icon-button type="submit" title="{{ __('Assign to Sales') }}" label="{{ __('Assign') }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                                                </svg>
                                            </x-icon-button>
                                        </form>
                                    @endcan
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                @if($lead->customer)
                                    <x-icon-button as="a" icon="view" href="{{ route('customers.show', $lead->customer) }}"
                                        label="{{ __('View') }}" title="{{ __('View customer detail') }}" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-500">
                                {{ __('No leads from marketing yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t">
            {{ $leads->links() }}
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('ajax:success', function (event) {
                const form = event.detail?.form;
                if (!form || !form.matches('[data-assign-form]')) return;

                const data = event.detail?.data || {};
                const row = form.closest('[data-lead-row]');
                const cell = row?.querySelector('[data-assignee-cell]');
                if (!cell || !data.assignee_name) return;

                const wrap = document.createElement('div');
                wrap.className = 'inline-flex items-center justify-center gap-2';

                const avatar = document.createElement('span');
                avatar.className = 'inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300';
                avatar.textContent = data.assignee_initial || String(data.assignee_name).charAt(0).toUpperCase();

                const info = document.createElement('div');
                info.className = 'flex flex-col items-start text-left leading-tight';

                const name = document.createElement('span');
                name.className = 'text-xs font-semibold text-content-primary';
                name.textContent = data.assignee_name;

                const status = document.createElement('span');
                status.className = 'text-xs uppercase tracking-wide text-emerald-600 dark:text-emerald-400';
                status.textContent = @json(__('Assigned'));

                info.append(name, status);
                wrap.append(avatar, info);
                cell.replaceChildren(wrap);
            });
        </script>
    @endpush
</x-app-layout>