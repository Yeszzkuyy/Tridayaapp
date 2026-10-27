<x-app-layout>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Delivery') }} {{ $goodsDelivery->delivery_number }}</h1>
                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full {{ \App\Models\GoodsDelivery::statusBadgeClasses($goodsDelivery->status) }}">
                    {{ \App\Models\GoodsDelivery::statusLabel($goodsDelivery->status) }}
                </span>
            </div>
            <p class="text-slate-500 mt-1">{{ __('Delivery note to customer.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <x-icon-button as="a" icon="back" href="{{ route('admin.goods-deliveries.index') }}" title="{{ __('Back') }}" />
            @can('manage-admin')
                @if($goodsDelivery->status === 'prepared')
                    <form method="POST" action="{{ route('admin.goods-deliveries.status', $goodsDelivery) }}">
                        @csrf
                        <input type="hidden" name="status" value="shipped">
                        <x-icon-button icon="add" type="submit" label="{{ __('Mark Shipped') }}" title="{{ __('Mark as shipped') }}" />
                    </form>
                @endif
                @if($goodsDelivery->status !== 'delivered')
                    <form method="POST" action="{{ route('admin.goods-deliveries.status', $goodsDelivery) }}">
                        @csrf
                        <input type="hidden" name="status" value="delivered">
                        <x-icon-button icon="add" type="submit" label="{{ __('Mark Delivered') }}" title="{{ __('Mark as delivered') }}" />
                    </form>
                @endif
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 text-green-700 px-4 py-3 text-sm dark:bg-green-900/30 dark:border-green-800 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm dark:bg-red-900/30 dark:border-red-800 dark:text-red-200">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
            <h2 class="font-semibold text-slate-800 dark:text-slate-100 mb-4">{{ __('Delivered Items') }}</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Item') }}</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Qty') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Serial Numbers') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($goodsDelivery->items as $item)
                            <tr>
                                <td class="px-3 py-3">
                                    <div class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $item->item_name }}</div>
                                    <div class="text-xs text-slate-500">{{ $item->part_number ?? $item->product?->part_number ?? __('Not filled') }}</div>
                                </td>
                                <td class="px-3 py-3 text-center text-sm text-slate-700 dark:text-slate-300">{{ number_format($item->quantity, 2) }} {{ $item->unit }}</td>
                                <td class="px-3 py-3">
                                    @forelse($item->serial_numbers ?? [] as $serial)
                                        <span class="inline-flex mb-1 mr-1 px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-700 font-mono text-[11px] text-slate-700 dark:text-slate-200">{{ $serial }}</span>
                                    @empty
                                        <span class="text-xs text-slate-400 italic">{{ __('No serial numbers') }}</span>
                                    @endforelse
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-4 text-sm">
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Project') }}</p>
                @if($goodsDelivery->project)
                    <a href="{{ route('projects.show', $goodsDelivery->project) }}" class="text-slate-800 dark:text-slate-100 hover:underline">{{ $goodsDelivery->project->project_name }}</a>
                @else
                    <p class="text-slate-400 italic">{{ __('Not filled') }}</p>
                @endif
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Customer') }}</p>
                <p class="text-slate-800 dark:text-slate-100">{{ $goodsDelivery->customer?->name ?? __('Not filled') }}</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Delivery Date') }}</p>
                    <p class="text-slate-800 dark:text-slate-100">{{ $goodsDelivery->delivery_date?->format('d M Y') }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Recipient') }}</p>
                    <p class="text-slate-800 dark:text-slate-100">{{ $goodsDelivery->recipient_name ?? __('Not filled') }}</p>
                </div>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Created By') }}</p>
                <p class="text-slate-800 dark:text-slate-100">{{ $goodsDelivery->creator?->name ?? __('Not filled') }}</p>
            </div>
            @if($goodsDelivery->notes)
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Notes') }}</p>
                    <p class="text-slate-700 dark:text-slate-300 whitespace-pre-line">{{ $goodsDelivery->notes }}</p>
                </div>
            @endif
            @if($goodsDelivery->status === 'delivered')
                <div class="rounded-xl border border-green-200 bg-green-50 text-green-700 px-3 py-2 text-xs dark:bg-green-900/30 dark:border-green-800 dark:text-green-200">
                    {{ __('Delivered. Closing is evaluated after BAST and customer invoices are settled.') }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
