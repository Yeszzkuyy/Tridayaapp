<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Product Catalog') }}</h1>
                <p class="text-slate-500 mt-1">{{ __('Master goods & services for proposal BOQ.') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <x-icon-button as="a" icon="back" href="{{ route('sales.dashboard') }}" title="{{ __('Back') }}" />
                @canany(['manage-sales', 'manage-admin'])
                    <x-icon-button as="a" icon="add" href="{{ route('products.create') }}" title="{{ __('Add') }}" />
                @endcanany
            </div>
        </div>

        <form method="GET" action="{{ route('products.index') }}" class="flex flex-wrap items-center gap-2 mb-4">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search name / SKU...') }}"
                   class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
            <select name="category" class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
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

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-4">
            @forelse($products as $product)
                <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700/50 {{ !$loop->last ? 'border-b border-slate-100 dark:border-slate-700' : '' }}">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate">
                            {{ $product->name }}
                            @unless($product->is_active)
                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-200 text-slate-600 dark:bg-slate-600 dark:text-slate-300">{{ __('Nonaktif') }}</span>
                            @endunless
                            @can('manageCost', \App\Models\Product::class)
                                @if($product->cost_price !== null && (float) $product->default_price === (float) $product->cost_price)
                                    <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">{{ __('Review Price') }}</span>
                                @endif
                            @endcan
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ $product->sku ?? '-' }} • {{ \App\Models\Product::saleCategoryLabel($product->category) }} • /{{ $product->unit }} • Rp {{ number_format($product->default_price, 0, ',', '.') }}
                        </p>
                        @can('viewCost', \App\Models\Product::class)
                            @if($product->latestCostLog)
                                <p class="text-[11px] text-slate-400 dark:text-slate-500">
                                    {{ __('Cost') }}: Rp {{ number_format((float) $product->latestCostLog->price, 0, ',', '.') }}
                                    @if($product->latestCostLog->supplier)
                                        • {{ $product->latestCostLog->supplier->name }}
                                    @endif
                                    • {{ $product->latestCostLog->created_at?->format('d M Y') }}
                                </p>
                            @endif
                        @endcan
                    </div>
                    @canany(['manage-sales', 'manage-admin'])
                        <x-icon-button as="a" icon="edit" href="{{ route('products.edit', $product) }}" title="{{ __('Edit') }}" />
                        @if($product->is_active)
                            <form action="{{ route('products.destroy', $product) }}" method="POST" class="inline"
                                  onsubmit="return confirm('{{ __('Deactivate this product?') }}')">
                                @csrf @method('DELETE')
                                <x-icon-button icon="delete" type="submit" title="{{ __('Deactivate') }}" />
                            </form>
                        @endif
                    @endcanany
                </div>
            @empty
                <p class="text-slate-500 text-center py-8">{{ __('No products yet.') }}</p>
            @endforelse
            <div class="mt-4">{{ $products->links() }}</div>
        </div>
    </div>
</x-app-layout>
