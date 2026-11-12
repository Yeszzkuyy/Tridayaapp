<x-app-layout>
    @if(session('success') && session('success_card'))
        <x-status-card :message="session('success')" />
    @endif

    <div>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ auth()->user()->can('manage-price-requests') ? __('Estimator Queue') : __('Product Price Requests') }}</h1>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <x-back-button :href="route('dashboard')" />
                @can('create', \App\Models\EstimatorRequest::class)
                    <x-icon-button variant="solid" as="a" icon="add" href="{{ route('estimator.requests.create') }}" label="{{ __('New Request') }}" title="{{ __('New Request') }}" />
                @endcan
            </div>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
            <div class="rounded-2xl border border-amber-100 bg-white p-4 shadow-sm dark:border-amber-900/40 dark:bg-slate-800">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-300">{{ __('Waiting') }}</p>
                <p class="mt-1 text-2xl font-extrabold tabular-nums text-slate-900 dark:text-slate-100">{{ number_format($summary['waiting']) }}</p>
            </div>
            <div class="rounded-2xl border border-blue-100 bg-white p-4 shadow-sm dark:border-blue-900/40 dark:bg-slate-800">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-blue-600 dark:text-blue-300">{{ __('In Progress') }}</p>
                <p class="mt-1 text-2xl font-extrabold tabular-nums text-slate-900 dark:text-slate-100">{{ number_format($summary['in_progress']) }}</p>
            </div>
            <div class="rounded-2xl border border-cyan-100 bg-white p-4 shadow-sm dark:border-cyan-900/40 dark:bg-slate-800">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-cyan-600 dark:text-cyan-300">{{ __('Price Received') }}</p>
                <p class="mt-1 text-2xl font-extrabold tabular-nums text-slate-900 dark:text-slate-100">{{ number_format($summary['answered']) }}</p>
            </div>
            <div class="rounded-2xl border border-orange-100 bg-white p-4 shadow-sm dark:border-orange-900/40 dark:bg-slate-800">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-orange-600 dark:text-orange-300">{{ __('Revision') }}</p>
                <p class="mt-1 text-2xl font-extrabold tabular-nums text-slate-900 dark:text-slate-100">{{ number_format($summary['revision']) }}</p>
            </div>
            <div class="rounded-2xl border border-green-100 bg-white p-4 shadow-sm dark:border-green-900/40 dark:bg-slate-800">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-green-600 dark:text-green-300">{{ __('Completed') }}</p>
                <p class="mt-1 text-2xl font-extrabold tabular-nums text-slate-900 dark:text-slate-100">{{ number_format($summary['completed']) }}</p>
            </div>
        </div>

        <form method="GET" action="{{ route('estimator.requests.index') }}" class="mb-4 flex flex-wrap items-end gap-2 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
            <div class="min-w-[220px] flex-1">
                <label for="estimator-queue-q" class="mb-1 block text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ __('Search') }}</label>
                <input id="estimator-queue-q" type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('PT name or request number') }}"
                       class="h-10 w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
            </div>
            <div>
                <label for="estimator-queue-status" class="mb-1 block text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ __('Status') }}</label>
                <select id="estimator-queue-status" name="status" class="h-10 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="open" @selected(request('status') === 'open')>{{ __('Open only') }}</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Models\EstimatorRequest::statusLabel($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="estimator-queue-from" class="mb-1 block text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ __('From') }}</label>
                <input id="estimator-queue-from" type="date" name="date_from" value="{{ request('date_from') }}" class="h-10 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
            </div>
            <div>
                <label for="estimator-queue-to" class="mb-1 block text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ __('To') }}</label>
                <input id="estimator-queue-to" type="date" name="date_to" value="{{ request('date_to') }}" class="h-10 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
            </div>
            <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
            <x-icon-button as="a" icon="reset" href="{{ route('estimator.requests.index') }}" title="{{ __('Reset') }}" />
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3">{{ __('Customer / PT') }}</th>
                            <th class="px-5 py-3">{{ __('Requested by') }}</th>
                            <th class="px-5 py-3 text-center">{{ __('Items') }}</th>
                            <th class="px-5 py-3">{{ __('Status') }}</th>
                            <th class="px-5 py-3">{{ __('Last Update') }}</th>
                            <th class="px-5 py-3">{{ __('Age') }}</th>
                            <th class="px-5 py-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @forelse($requests as $estimatorRequest)
                            @php
                                $ageDays = (int) $estimatorRequest->created_at?->diffInDays(now());
                                $lastUpdate = collect([$estimatorRequest->updated_at, $estimatorRequest->items->max('answered_at')])
                                    ->filter()
                                    ->map(fn ($date) => \Illuminate\Support\Carbon::parse($date))
                                    ->max();
                            @endphp
                            <tr class="transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-700/35">
                                <td class="px-5 py-4">
                                    <a href="{{ route('estimator.requests.show', $estimatorRequest) }}" class="font-medium text-slate-800 hover:text-accent-600 dark:text-slate-100 dark:hover:text-accent-400">
                                        {{ $estimatorRequest->customer?->name ?? '-' }}
                                    </a>
                                    <p class="mt-0.5 text-[11px] text-slate-400">
                                        {{ $estimatorRequest->customer?->pt_group ?: '-' }} · #{{ $estimatorRequest->id }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">
                                    <div class="flex items-center gap-2">
                                        @if($estimatorRequest->requester)
                                            <x-user-avatar :user="$estimatorRequest->requester" size="w-6 h-6" text="text-[10px]" :clickable="false" />
                                        @endif
                                        <span class="truncate max-w-[150px]">{{ $estimatorRequest->requester?->name ?? '-' }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-center tabular-nums text-slate-600 dark:text-slate-300">
                                    {{ $estimatorRequest->answered_items_count }}/{{ $estimatorRequest->items_count }}
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ \App\Models\EstimatorRequest::statusColor($estimatorRequest->status) }}">
                                        {{ \App\Models\EstimatorRequest::statusLabel($estimatorRequest->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-slate-600 dark:text-slate-300">
                                    {{ $lastUpdate?->timezone('Asia/Jakarta')->format('d M Y, H:i') ?? '-' }}
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-slate-600 dark:text-slate-300">
                                    {{ trans_choice(':count day|:count days', $ageDays, ['count' => $ageDays]) }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <x-icon-button as="a" icon="view" href="{{ route('estimator.requests.show', $estimatorRequest) }}" title="{{ __('View details') }}" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center">
                                    <x-empty-state label="{{ __('product price requests') }}" description="{{ __('No product price requests match this filter. Create a new request or reset the filter.') }}" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($requests->hasPages())
            <div class="mt-4">{{ $requests->links() }}</div>
        @endif
    </div>
</x-app-layout>
