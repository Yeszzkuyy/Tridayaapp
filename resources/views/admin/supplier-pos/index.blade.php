<x-app-layout>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Supplier Purchase Orders') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Orders issued by 3DY to suppliers. Separate from customer purchase orders.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <x-icon-button as="a" icon="back" href="{{ route('admin.purchasing.index') }}" title="{{ __('Back') }}" />
            @can('manage-admin')
                <x-icon-button as="a" icon="add" href="{{ route('admin.supplier-pos.create') }}" label="{{ __('Create Supplier PO') }}" title="{{ __('Create Supplier PO') }}" />
            @endcan
        </div>
    </div>

    <form method="GET" action="{{ route('admin.supplier-pos.index') }}" class="mb-4 flex flex-wrap items-end gap-2">
        <div>
            <label for="q" class="block text-xs text-slate-500 mb-1">{{ __('Search') }}</label>
            <input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('PO number / supplier') }}"
                   class="w-64 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
        </div>
        <div>
            <label for="status" class="block text-xs text-slate-500 mb-1">{{ __('Status') }}</label>
            <select id="status" name="status" class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                <option value="">{{ __('All') }}</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Models\SupplierPurchaseOrder::statusLabel($status) }}</option>
                @endforeach
            </select>
        </div>
        <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
        <x-icon-button as="a" icon="reset" href="{{ route('admin.supplier-pos.index') }}" title="{{ __('Reset') }}" />
    </form>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('PO Number') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Supplier') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Quotation') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Project') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Issue Date') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Expected Delivery') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Items') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Total') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                    @forelse($orders as $order)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition group">
                            <td class="px-4 py-3 font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $order->po_number }}</td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $order->supplier?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                @if($order->proposal)
                                    <a href="{{ route('admin.purchasing.show', $order->proposal) }}" class="font-mono text-sm text-accent-600 dark:text-accent-400 hover:underline">{{ $order->proposal->proposal_number }}</a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                @if($order->project)
                                    <a href="{{ route('projects.show', $order->project) }}" class="hover:underline">{{ $order->project->project_name }}</a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $order->issue_date?->format('d M Y') ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $order->expected_delivery_date?->format('d M Y') ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ $order->items_count }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-800 dark:text-slate-100">Rp {{ number_format($order->grand_total, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full {{ \App\Models\SupplierPurchaseOrder::statusBadgeClasses($order->status) }}">
                                    {{ \App\Models\SupplierPurchaseOrder::statusLabel($order->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                                    <x-icon-button as="a" icon="view" href="{{ route('admin.supplier-pos.show', $order) }}" title="{{ __('Purchase order detail') }}" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="py-8 text-center text-slate-400">{{ __('No supplier purchase orders yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())<div class="p-4 border-t">{{ $orders->links() }}</div>@endif
    </div>
</x-app-layout>
