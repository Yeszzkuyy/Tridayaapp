<x-app-layout>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Goods Receipts') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Record goods received from suppliers, including partial deliveries and serial numbers.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <x-icon-button as="a" icon="back" href="{{ route('admin.hub') }}" title="{{ __('Back') }}" />
            @can('manage-admin')
                <x-icon-button as="a" icon="add" href="{{ route('admin.goods-receipts.create') }}" label="{{ __('Receive Goods') }}" title="{{ __('Receive Goods') }}" />
            @endcan
        </div>
    </div>

    <form method="GET" action="{{ route('admin.goods-receipts.index') }}" class="mb-4 flex flex-wrap items-end gap-2">
        <div>
            <label for="q" class="block text-xs text-slate-500 mb-1">{{ __('Search') }}</label>
            <input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Receipt / delivery note / PO') }}"
                   class="w-64 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
        </div>
        <div>
            <label for="status" class="block text-xs text-slate-500 mb-1">{{ __('Status') }}</label>
            <select id="status" name="status" class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                <option value="">{{ __('All') }}</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Models\GoodsReceipt::statusLabel($status) }}</option>
                @endforeach
            </select>
        </div>
        <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
        <x-icon-button as="a" icon="reset" href="{{ route('admin.goods-receipts.index') }}" title="{{ __('Reset') }}" />
    </form>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Receipt Number') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Supplier PO') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Supplier') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Project') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Received Date') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Items') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                    @forelse($receipts as $receipt)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition group">
                            <td class="px-4 py-3 font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $receipt->receipt_number }}</td>
                            <td class="px-4 py-3 font-mono text-sm text-slate-700 dark:text-slate-300">{{ $receipt->purchaseOrder?->po_number ?? __('Not filled') }}</td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $receipt->purchaseOrder?->supplier?->name ?? __('Not filled') }}</td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                @if($receipt->project)
                                    <a href="{{ route('projects.show', $receipt->project) }}" class="hover:underline">{{ $receipt->project->project_name }}</a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $receipt->received_date?->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-center text-slate-600 dark:text-slate-400">{{ $receipt->items_count }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full {{ \App\Models\GoodsReceipt::statusBadgeClasses($receipt->status) }}">
                                    {{ \App\Models\GoodsReceipt::statusLabel($receipt->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                                    <x-icon-button as="a" icon="view" href="{{ route('admin.goods-receipts.show', $receipt) }}" title="{{ __('View receipt') }}" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-8 text-center text-slate-400">{{ __('No goods receipts yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($receipts->hasPages())<div class="p-4 border-t">{{ $receipts->links() }}</div>@endif
    </div>
</x-app-layout>
