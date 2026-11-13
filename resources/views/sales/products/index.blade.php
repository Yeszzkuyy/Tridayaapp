<x-app-layout>
    <div>
        @php
            $isEstimatorPricelist = auth()->user()->can('manage-price-requests') && ! auth()->user()->can('manage-sales') && ! auth()->user()->can('manage-admin');
            $backHref = $isEstimatorPricelist ? route('estimator.dashboard') : route('sales.dashboard');
        @endphp
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ $isEstimatorPricelist ? __('Pricelist') : __('Product Catalog') }}</h1>
                @unless($isEstimatorPricelist)
                    <p class="mt-1 text-slate-500">{{ __('Master goods & services for proposal BOQ.') }}</p>
                @endunless
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-back-button :href="$backHref" />
                @canany(['manage-sales', 'manage-admin', 'manage-products'])
                    <x-icon-button variant="solid" as="a" icon="add" href="{{ route('products.create') }}" label="{{ __('Add Product') }}" title="{{ __('Add') }}" />
                @endcanany
            </div>
        </div>

        <form method="GET" action="{{ route('products.index') }}" class="mb-4 flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search name / SKU...') }}"
                   class="h-10 min-w-[220px] flex-1 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
            <select name="category" class="h-10 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                <option value="">{{ __('All categories') }}</option>
                @foreach(\App\Models\Product::SALE_CATEGORIES as $cat)
                    <option value="{{ $cat }}" @selected(request('category') === $cat)>{{ \App\Models\Product::saleCategoryLabel($cat) }}</option>
                @endforeach
            </select>
            <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
            <x-icon-button as="a" icon="reset" href="{{ route('products.index') }}" title="{{ __('Reset') }}" />
            @can('manageCost', \App\Models\Product::class)
                <x-icon-button as="a" icon="view" href="{{ route('products.index', ['price_review' => 1]) }}" label="{{ __('Review Prices') }}" title="{{ __('Review Prices') }}" />
            @endcan
        </form>

        @can('manageCost', \App\Models\Product::class)
            @if($needsPriceReviewCount > 0)
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                    <span>{{ __(':count products have a selling price equal to the cost price and need review.', ['count' => $needsPriceReviewCount]) }}</span>
                    <x-icon-button as="a" icon="view" href="{{ route('products.index', ['price_review' => 1]) }}" label="{{ __('Open Review List') }}" title="{{ __('Open Review List') }}" />
                </div>
            @endif
        @endcan

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @forelse($products as $product)
                    @php
                        $log = $product->latestCostLog;
                        $validUntil = $log?->valid_until;
                        $isExpired = $validUntil ? $validUntil->copy()->endOfDay()->isPast() : false;
                    @endphp
                    <div class="flex flex-col gap-4 p-4 transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-700/35 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-base font-bold text-slate-900 dark:text-slate-100">{{ $product->name }}</p>
                                @unless($product->is_active)
                                    <span class="inline-flex rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-600 dark:text-slate-300">{{ __('Nonaktif') }}</span>
                                @endunless
                                @can('manageCost', \App\Models\Product::class)
                                    @if($product->cost_price !== null && (float) $product->default_price === (float) $product->cost_price)
                                        <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">{{ __('Review Price') }}</span>
                                    @endif
                                @endcan
                                @can('viewCost', \App\Models\Product::class)
                                    @if($validUntil && ! $isExpired)
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200">
                                            <span class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                            {{ __('Active (Valid until :date)', ['date' => $validUntil->format('d M Y')]) }}
                                        </span>
                                    @elseif($validUntil)
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:border-amber-700 dark:bg-amber-950/50 dark:text-amber-200">
                                            <span class="h-2 w-2 rounded-full bg-amber-500" aria-hidden="true"></span>
                                            {{ __('Expired :date - Needs Recheck', ['date' => $validUntil->format('d M Y')]) }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-300 bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">
                                            <span class="h-2 w-2 rounded-full bg-slate-400" aria-hidden="true"></span>
                                            {{ __('Needs Recheck') }}
                                        </span>
                                    @endif
                                @endcan
                            </div>

                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                {{ $product->sku ?? '-' }} • {{ \App\Models\Product::saleCategoryLabel($product->category) }} • /{{ $product->unit }}
                                @unless($isEstimatorPricelist)
                                    • {{ __('Selling Price') }}: <span class="font-semibold text-slate-700 dark:text-slate-200">Rp {{ number_format($product->default_price, 0, ',', '.') }}</span>
                                @endunless
                            </p>

                            @can('viewCost', \App\Models\Product::class)
                                @if($log)
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 rounded-xl border border-slate-100 bg-slate-50/80 px-3.5 py-2.5 text-sm text-slate-700 dark:border-slate-700/70 dark:bg-slate-900/40 dark:text-slate-200">
                                        <span>
                                            <span class="text-slate-500 dark:text-slate-400">{{ __('Cost') }}:</span>
                                            <strong class="font-bold tabular-nums text-slate-900 dark:text-slate-100">Rp {{ number_format((float) $log->price, 0, ',', '.') }}</strong>
                                        </span>
                                        @if($log->supplier)
                                            <span>
                                                <span class="text-slate-500 dark:text-slate-400">{{ __('Supplier') }}:</span>
                                                <strong class="font-semibold">{{ $log->supplier->name }}</strong>
                                            </span>
                                        @endif
                                        @if($log->stock_status)
                                            <span>
                                                <span class="text-slate-500 dark:text-slate-400">{{ __('Stock') }}:</span>
                                                <strong class="font-semibold">{{ $log->stock_status === 'indent' ? __('Indent').($log->stock_eta ? ' ('.$log->stock_eta.')' : '') : __('Ready') }}</strong>
                                            </span>
                                        @endif
                                        @if($log->warranty_info)
                                            <span>
                                                <span class="text-slate-500 dark:text-slate-400">{{ __('Warranty') }}:</span>
                                                <strong class="font-semibold">{{ $log->warranty_info }}</strong>
                                            </span>
                                        @endif
                                        <span class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ __('Updated') }} {{ $log->created_at?->format('d M Y') }}
                                        </span>
                                    </div>
                                @else
                                    <p class="text-xs italic text-slate-400 dark:text-slate-500">{{ __('No cost price recorded yet.') }}</p>
                                @endif
                            @endcan
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            @can('manageCost', \App\Models\Product::class)
                                <x-icon-button as="a" icon="upload" href="{{ route('products.price.edit', $product) }}" label="{{ __('Update Price') }}" title="{{ __('Update Price') }}" />
                            @endcan
                            @canany(['manage-sales', 'manage-admin'])
                                <x-icon-button as="a" icon="edit" href="{{ route('products.edit', $product) }}" title="{{ __('Edit') }}" />
                                @if($product->is_active)
                                    <form action="{{ route('products.destroy', $product) }}" method="POST" class="inline"
                                          data-confirm="{{ __('Deactivate this product?') }}" data-confirm-tone="warning">
                                        @csrf @method('DELETE')
                                        <x-icon-button icon="delete" type="submit" title="{{ __('Deactivate') }}" />
                                    </form>
                                @endif
                            @endcanany
                        </div>
                    </div>
                @empty
                    <p class="py-10 text-center text-sm text-slate-500">{{ __('No products yet.') }}</p>
                @endforelse
            </div>
        </div>
        @if($products->hasPages())
            <div class="mt-4">{{ $products->links() }}</div>
        @endif
    </div>
</x-app-layout>
