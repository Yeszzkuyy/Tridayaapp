<x-app-layout>
    @php
        $stageTabs = [
            'all' => __('All'),
            'waiting_po' => __('Waiting for Customer PO'),
            'po_received' => __('PO Received'),
            'in_purchasing' => __('In Purchasing'),
            'project_created' => __('Project Created'),
            'bast_closed' => __('BAST & Closed'),
        ];
        $tabUrl = function (string $key) use ($filters) {
            $query = array_filter([
                'stage' => $key !== 'all' ? $key : null,
                'q' => $filters['q'] ?? null,
                'pt_group' => $filters['pt_group'] ?? null,
            ]);

            return route('admin.purchasing.index').($query ? '?'.http_build_query($query) : '');
        };
    @endphp

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-3xl">{{ __('Purchasing Handoff') }}</h1>
    </div>

    {{-- Tab tahap handoff beserta jumlahnya --}}
    <nav class="mb-4 flex flex-wrap items-center gap-2" aria-label="{{ __('Handoff Stages') }}">
        @foreach ($stageTabs as $key => $label)
            <a href="{{ $tabUrl($key) }}"
               class="inline-flex items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-semibold transition-all duration-200 {{ $stage === $key ? 'border-accent-600 bg-accent-600 text-white shadow-sm shadow-accent-600/30' : 'border-slate-200/80 bg-[var(--card-bg)] text-slate-600 hover:border-accent-400 dark:border-slate-700 dark:text-slate-300' }}">
                {{ $label }}
                <span class="inline-flex min-w-[20px] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-bold leading-none tabular-nums {{ $stage === $key ? 'bg-white text-accent-700' : 'bg-accent-600 text-white' }}">
                    {{ $stageCounts[$key] ?? 0 }}
                </span>
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('admin.purchasing.index') }}" class="mb-4 flex flex-wrap items-end gap-2">
        <input type="hidden" name="stage" value="{{ $stage }}">
        <div>
            <label for="q" class="mb-1 block text-xs text-slate-500">{{ __('Search') }}</label>
            <input id="q" type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Quotation / PO number / customer') }}"
                   class="w-64 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
        </div>
        <div>
            <label for="pt_group" class="mb-1 block text-xs text-slate-500">{{ __('PT Group') }}</label>
            <select id="pt_group" name="pt_group" class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                <option value="">{{ __('All') }}</option>
                @foreach($ptGroups as $pt)
                    <option value="{{ $pt }}" @selected(($filters['pt_group'] ?? '') === $pt)>{{ $pt }}</option>
                @endforeach
            </select>
        </div>
        <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
        <x-icon-button as="a" icon="reset" href="{{ $tabUrl($stage) }}" title="{{ __('Reset') }}" />
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90">
        <div class="overflow-x-auto lg:overflow-visible">
            <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-slate-700">
                <thead class="bg-slate-50/80 dark:bg-slate-900/30">
                    <tr>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Quotation No.') }}</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Customer') }}</th>
                        <th class="hidden px-3 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 lg:table-cell">{{ __('Sales Owner') }}</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Customer PO') }}</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Delivery Deadline') }}</th>
                        <th class="hidden px-3 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 md:table-cell">{{ __('Items') }}</th>
                        <th class="w-[170px] px-3 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Status') }}</th>
                        <th class="w-[190px] px-3 py-3.5 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($proposals as $proposal)
                        @php
                            $deadlineDays = $proposal->delivery_deadline
                                ? (int) now()->startOfDay()->diffInDays($proposal->delivery_deadline, false)
                                : null;
                            $itemSummary = $proposal->items->take(3)
                                ->map(fn ($item) => rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.').'× '.$item->description)
                                ->implode(', ');
                        @endphp
                        <tr class="transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-700/35">
                            <td class="px-3 py-3.5">
                                <p class="whitespace-nowrap font-semibold tabular-nums text-slate-800 dark:text-slate-100">{{ $proposal->proposal_number }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $proposal->proposal_date?->format('d M Y') ?? __('Not filled') }}</p>
                            </td>
                            <td class="px-3 py-3.5 text-slate-700 dark:text-slate-300">
                                <p class="truncate" title="{{ $proposal->customer }}">{{ $proposal->customer }}</p>
                                @if($proposal->lead?->pt_group)
                                    <span class="mt-0.5 inline-block rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $proposal->lead->pt_group }}</span>
                                @endif
                            </td>
                            <td class="hidden px-3 py-3.5 text-slate-700 dark:text-slate-300 lg:table-cell">
                                <span class="block truncate">{{ $proposal->lead?->assignee?->name ?? __('Not filled') }}</span>
                            </td>
                            <td class="px-3 py-3.5 text-slate-700 dark:text-slate-300">
                                <p class="whitespace-nowrap font-medium">{{ $proposal->po_number ?? __('Not filled') }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $proposal->po_date?->format('d M Y') ?? __('Not filled') }}</p>
                                <p class="line-clamp-1 text-xs text-slate-500 dark:text-slate-400" title="{{ $proposal->paymentTermsSummary() }}">
                                    {{ $proposal->paymentTermsSummary() }}
                                </p>
                            </td>
                            <td class="px-3 py-3.5 text-slate-700 dark:text-slate-300">
                                <p class="whitespace-nowrap">{{ $proposal->delivery_deadline?->format('d M Y') ?? __('Not set yet') }}</p>
                                @if($deadlineDays !== null)
                                    @if($deadlineDays < 0)
                                        <span class="mt-1 inline-flex rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-semibold text-red-700 dark:bg-red-950/60 dark:text-red-300">
                                            {{ __('Overdue by :days days', ['days' => abs($deadlineDays)]) }}
                                        </span>
                                    @elseif($deadlineDays <= 7)
                                        <span class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">
                                            {{ __('Due in :days days', ['days' => $deadlineDays]) }}
                                        </span>
                                    @else
                                        <span class="mt-1 block text-[11px] text-slate-400 dark:text-slate-500">{{ __(':days days left', ['days' => $deadlineDays]) }}</span>
                                    @endif
                                @endif
                                @if($proposal->handoffWarnings())
                                    <p class="mt-1 text-xs font-medium text-amber-600 dark:text-amber-400">{{ __(':count field(s) incomplete', ['count' => count($proposal->handoffWarnings())]) }}</p>
                                @endif
                            </td>
                            <td class="hidden px-3 py-3.5 text-slate-600 dark:text-slate-400 md:table-cell">
                                <p class="text-xs">{{ $proposal->items->count() }} {{ __('item(s)') }}</p>
                                <p class="line-clamp-2" title="{{ $itemSummary }}">{{ $itemSummary }}</p>
                            </td>
                            <td class="px-3 py-3.5">
                                <x-purchasing.status-stepper :proposal="$proposal" />
                            </td>
                            <td class="px-3 py-3.5 text-right">
                                <div class="flex flex-wrap items-center justify-end gap-1.5">
                                    <x-icon-button as="a" icon="view" href="{{ route('admin.purchasing.show', $proposal) }}" title="{{ __('Handoff Detail') }}" />
                                    @if($proposal->isReadyForSupplierPo())
                                        @can('manage-admin')
                                            <x-icon-button as="a" icon="add" href="{{ route('admin.supplier-pos.create', ['proposal_id' => $proposal->id]) }}" title="{{ __('Create Supplier PO') }}" />
                                        @endcan
                                    @endif
                                    @if($proposal->project)
                                        <x-icon-button as="a" icon="project" href="{{ route('projects.show', $proposal->project) }}" title="{{ __('View Project') }}" />
                                    @endif
                                    <x-file-preview icon="file-text"
                                                    :url="route('sales.proposals.download-po', $proposal)"
                                                    :name="basename((string) $proposal->po_document_path)" />
                                    <x-icon-button as="a" icon="download" href="{{ route('sales.proposals.download-po', $proposal) }}" title="{{ __('Download PO') }}" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-10 text-center text-sm italic text-slate-400 dark:text-slate-500">{{ __('No quotations match this view.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($proposals->hasPages())<div class="border-t border-slate-100 p-4 dark:border-slate-700/60">{{ $proposals->links() }}</div>@endif
    </div>
</x-app-layout>
