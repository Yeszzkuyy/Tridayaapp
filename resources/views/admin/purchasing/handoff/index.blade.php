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
        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] table-fixed divide-y divide-slate-100 text-sm dark:divide-slate-700">
                <thead class="bg-slate-50/80 dark:bg-slate-900/30">
                    <tr>
                        <th class="w-[13%] px-2.5 py-3.5 whitespace-nowrap text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Quotation No.') }}</th>
                        <th class="w-[16%] px-2.5 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Customer') }}</th>
                        <th class="hidden w-[10%] px-2.5 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 xl:table-cell">{{ __('Sales Owner') }}</th>
                        <th class="w-[15%] px-2.5 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Customer PO') }}</th>
                        <th class="w-[13%] px-2.5 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Delivery Deadline') }}</th>
                        <th class="hidden w-[10%] px-2.5 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 xl:table-cell">{{ __('Items') }}</th>
                        <th class="w-[14%] px-2.5 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Status') }}</th>
                        <th class="w-[84px] px-2.5 py-3.5 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Actions') }}</th>
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
                            $poName = $proposal->po_document_path ? basename((string) $proposal->po_document_path) : null;
                            $poUrl = $proposal->po_document_path ? route('sales.proposals.download-po', $proposal) : null;
                            $poKind = $poName ? \App\Rules\SecureFile::previewKind($poName) : null;
                        @endphp
                        <tr class="transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-700/35">
                            <td class="px-2.5 py-3.5">
                                <p class="truncate font-semibold tabular-nums text-slate-800 dark:text-slate-100" title="{{ $proposal->proposal_number }}">{{ $proposal->proposal_number }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $proposal->proposal_date?->format('d M Y') ?? __('Not filled') }}</p>
                            </td>
                            <td class="px-2.5 py-3.5 text-slate-700 dark:text-slate-300">
                                <p class="truncate" title="{{ $proposal->customer }}">{{ $proposal->customer }}</p>
                                @if($proposal->lead?->pt_group)
                                    <span class="mt-0.5 inline-block rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $proposal->lead->pt_group }}</span>
                                @endif
                            </td>
                            <td class="hidden px-2.5 py-3.5 text-slate-700 dark:text-slate-300 xl:table-cell">
                                <span class="block truncate">{{ $proposal->lead?->assignee?->name ?? __('Not filled') }}</span>
                            </td>
                            <td class="px-2.5 py-3.5 text-slate-700 dark:text-slate-300">
                                <p class="truncate font-medium" title="{{ $proposal->po_number ?? __('Not filled') }}">{{ $proposal->po_number ?? __('Not filled') }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $proposal->po_date?->format('d M Y') ?? __('Not filled') }}</p>
                                <p class="line-clamp-1 text-xs text-slate-500 dark:text-slate-400" title="{{ $proposal->paymentTermsSummary() }}">
                                    {{ $proposal->paymentTermsSummary() }}
                                </p>
                            </td>
                            <td class="px-2.5 py-3.5 text-slate-700 dark:text-slate-300">
                                <p class="truncate">{{ $proposal->delivery_deadline?->format('d M Y') ?? __('Not set yet') }}</p>
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
                            <td class="hidden px-2.5 py-3.5 text-slate-600 dark:text-slate-400 xl:table-cell">
                                <p class="text-xs">{{ $proposal->items->count() }} {{ __('item(s)') }}</p>
                                <p class="line-clamp-2" title="{{ $itemSummary }}">{{ $itemSummary }}</p>
                            </td>
                            <td class="px-2.5 py-3.5">
                                <x-purchasing.status-stepper :proposal="$proposal" />
                            </td>
                            <td class="px-2.5 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    {{-- Aksi utama: buka detail handoff (atau quotation bila PO belum ada) --}}
                                    @if($proposal->po_document_path)
                                        <x-icon-button as="a" icon="view" href="{{ route('admin.purchasing.show', $proposal) }}" title="{{ __('Handoff Detail') }}" />
                                    @else
                                        <x-icon-button as="a" icon="view" href="{{ route('sales.proposals.show', $proposal) }}" title="{{ __('View Quotation') }}" />
                                    @endif

                                    {{-- Aksi lain dirapatkan ke menu agar baris tidak melebar --}}
                                    <x-dropdown align="right" width="w-56" content-classes="py-1 bg-white dark:bg-slate-800">
                                        <x-slot name="trigger">
                                            <x-icon-button icon="more" title="{{ __('More actions') }}" />
                                        </x-slot>
                                        <x-slot name="content">
                                            @if($proposal->isReadyForSupplierPo())
                                                @can('manage-admin')
                                                    <a href="{{ route('admin.supplier-pos.create', ['proposal_id' => $proposal->id]) }}"
                                                       class="flex items-center gap-2.5 px-4 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/60">
                                                        <x-icon name="add" class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                                        {{ __('Create Supplier PO') }}
                                                    </a>
                                                @endcan
                                            @endif
                                            <a href="{{ route('sales.proposals.show', $proposal) }}"
                                               class="flex items-center gap-2.5 px-4 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/60">
                                                <x-icon name="file-text" class="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                                                {{ __('View Quotation') }}
                                            </a>
                                            @if($proposal->project)
                                                <a href="{{ route('projects.show', $proposal->project) }}"
                                                   class="flex items-center gap-2.5 px-4 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/60">
                                                    <x-icon name="project" class="h-4 w-4 text-violet-600 dark:text-violet-400" />
                                                    {{ __('View Project') }}
                                                </a>
                                            @endif
                                            @if($poUrl)
                                                <div class="my-1 border-t border-slate-100 dark:border-slate-700"></div>
                                                <button type="button"
                                                        class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-slate-700 transition-colors hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/60"
                                                        @click="window.openDocPreview({ kind: @js($poKind), url: @js($poUrl), filename: @js($poName), download: @js($poUrl) })">
                                                    <x-icon name="file-text" class="h-4 w-4 text-teal-600 dark:text-teal-400" />
                                                    {{ __('Preview Customer PO') }}
                                                </button>
                                                <a href="{{ $poUrl }}"
                                                   class="flex items-center gap-2.5 px-4 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/60">
                                                    <x-icon name="download" class="h-4 w-4 text-teal-600 dark:text-teal-400" />
                                                    {{ __('Download Customer PO') }}
                                                </a>
                                            @endif
                                        </x-slot>
                                    </x-dropdown>
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
