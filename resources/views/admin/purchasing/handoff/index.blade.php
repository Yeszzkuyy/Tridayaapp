<x-app-layout>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Purchasing Handoff') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Quotations that are accepted and already have a customer PO, ready to be processed by Purchasing.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1 rounded-xl border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 px-3 py-2 text-sm text-green-700 dark:text-green-300">
                {{ __('Ready for Purchasing') }}: <strong>{{ $readyCount }}</strong>
            </span>
            <span class="inline-flex items-center gap-1 rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-3 py-2 text-sm text-amber-700 dark:text-amber-300">
                {{ __('Waiting for Customer PO') }}: <strong>{{ $waitingCount }}</strong>
            </span>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.purchasing.index') }}" class="mb-4 flex flex-wrap items-end gap-2">
        <div>
            <label for="q" class="block text-xs text-slate-500 mb-1">{{ __('Search') }}</label>
            <input id="q" type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Quotation / PO number / customer') }}"
                   class="w-64 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
        </div>
        <div>
            <label for="pt_group" class="block text-xs text-slate-500 mb-1">{{ __('PT Group') }}</label>
            <select id="pt_group" name="pt_group" class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                <option value="">{{ __('All') }}</option>
                @foreach($ptGroups as $pt)
                    <option value="{{ $pt }}" @selected(($filters['pt_group'] ?? '') === $pt)>{{ $pt }}</option>
                @endforeach
            </select>
        </div>
        <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
        <x-icon-button as="a" icon="reset" href="{{ route('admin.purchasing.index') }}" title="{{ __('Reset') }}" />
    </form>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Quotation No.') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Customer') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Sales Owner') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Customer PO') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Delivery Deadline') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Items') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                    @forelse($proposals as $proposal)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition group">
                            <td class="px-4 py-3">
                                <p class="font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $proposal->proposal_number }}</p>
                                <p class="text-xs text-slate-500">{{ $proposal->proposal_date?->format('d M Y') ?? __('Not filled') }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                {{ $proposal->customer }}
                                @if($proposal->lead?->pt_group)
                                    <span class="block text-xs text-slate-400">{{ $proposal->lead->pt_group }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $proposal->lead?->assignee?->name ?? __('Not filled') }}</td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                <p class="font-medium">{{ $proposal->po_number ?? __('Not filled') }}</p>
                                <p class="text-xs text-slate-500">{{ $proposal->po_date?->format('d M Y') ?? __('Not filled') }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                {{ $proposal->delivery_deadline?->format('d M Y') ?? __('Not filled') }}
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400 max-w-xs">
                                <p class="text-xs">{{ $proposal->items->count() }} {{ __('item(s)') }}</p>
                                <p class="truncate">{{ $proposal->items->take(3)->map(fn ($item) => $item->quantity . '× ' . $item->description)->implode(', ') }}{{ $proposal->items->count() > 3 ? ' …' : '' }}</p>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">
                                    {{ $proposal->purchasingStatusLabel() }}
                                </span>
                                @if($proposal->project)
                                    <span class="mt-1 block text-xs text-slate-400">{{ __('Project created') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                                    <x-icon-button as="a" icon="view" href="{{ route('admin.purchasing.show', $proposal) }}" title="{{ __('Handoff Detail') }}" />
                                    @can('manage-admin')
                                        <x-icon-button as="a" icon="add" href="{{ route('admin.supplier-pos.create', ['proposal_id' => $proposal->id]) }}" title="{{ __('Create Supplier PO') }}" />
                                    @endcan
                                    @if($proposal->project)
                                        <x-icon-button as="a" icon="project" href="{{ route('projects.show', $proposal->project) }}" title="{{ __('View Project') }}" />
                                    @endif
                                    <x-icon-button as="a" icon="download" href="{{ route('sales.proposals.download-po', $proposal) }}" title="{{ __('Download PO') }}" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-8 text-center text-slate-400">{{ __('No quotations are ready for Purchasing yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($proposals->hasPages())<div class="p-4 border-t">{{ $proposals->links() }}</div>@endif
    </div>
</x-app-layout>
