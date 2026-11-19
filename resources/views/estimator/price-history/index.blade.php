<x-app-layout>
    <div>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('Product Price History') }}</h1>
            </div>
            <x-back-button :href="route('estimator.requests.index')" />
        </div>

        <form method="GET" action="{{ route('estimator.price-history') }}" class="mb-4 flex flex-wrap items-end gap-2 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
            <div class="min-w-[220px] flex-1">
                <label for="history-product" class="mb-1 block text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ __('Product') }}</label>
                <select id="history-product" name="product_id" class="h-10 w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                    <option value="">{{ __('All products') }}</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" @selected((string) request('product_id') === (string) $product->id)>{{ $product->name }}{{ $product->sku ? ' · '.$product->sku : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="history-supplier" class="mb-1 block text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ __('Supplier') }}</label>
                <select id="history-supplier" name="supplier_id" class="h-10 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                    <option value="">{{ __('All suppliers') }}</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) request('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="history-from" class="mb-1 block text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ __('From') }}</label>
                <input id="history-from" type="date" name="date_from" value="{{ request('date_from') }}" class="h-10 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
            </div>
            <div>
                <label for="history-to" class="mb-1 block text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ __('To') }}</label>
                <input id="history-to" type="date" name="date_to" value="{{ request('date_to') }}" class="h-10 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
            </div>
            <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
            <x-icon-button as="a" icon="reset" href="{{ route('estimator.price-history') }}" title="{{ __('Reset') }}" />
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3">{{ __('Updated') }}</th>
                            <th class="px-5 py-3">{{ __('Product') }}</th>
                            <th class="px-5 py-3">{{ __('Supplier') }}</th>
                            <th class="px-5 py-3 text-right">{{ __('Cost') }}</th>
                            <th class="px-5 py-3">{{ __('Stock') }}</th>
                            <th class="px-5 py-3">{{ __('Warranty') }}</th>
                            <th class="px-5 py-3">{{ __('Valid Until') }}</th>
                            <th class="px-5 py-3">{{ __('Customer / PT') }}</th>
                            <th class="px-5 py-3">{{ __('Estimator') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @forelse($logs as $log)
                            <tr class="align-top">
                                <td class="px-5 py-3 whitespace-nowrap text-slate-500 dark:text-slate-400">{{ $log->created_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') ?? '-' }}</td>
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $log->product?->name ?? '-' }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $log->product?->sku ?: '-' }}</p>
                                </td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $log->supplier?->name ?? '-' }}</td>
                                <td class="px-5 py-3 text-right font-semibold tabular-nums text-slate-800 dark:text-slate-100">Rp {{ number_format((float) $log->price, 0, ',', '.') }}</td>
                                <td class="px-5 py-3">
                                    @if($log->stock_status)
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $log->stock_status === 'indent' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' }}">
                                            {{ $log->stock_status === 'indent' ? __('Indent') : __('Ready') }}
                                        </span>
                                        @if($log->stock_eta)<p class="mt-0.5 text-[11px] text-slate-400">{{ $log->stock_eta }}</p>@endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $log->warranty_info ?: '-' }}</td>
                                <td class="px-5 py-3 whitespace-nowrap tabular-nums text-slate-600 dark:text-slate-300">{{ $log->valid_until?->format('d M Y') ?? '-' }}</td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $log->estimatorRequestItem?->estimatorRequest?->customer?->name ?? '-' }}</td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $log->updater?->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-12 text-center">
                                    <x-empty-state label="{{ __('price history') }}" description="{{ __('No cost price history matches this filter.') }}" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($logs->hasPages())
            <div class="mt-4">{{ $logs->links() }}</div>
        @endif
    </div>
</x-app-layout>
