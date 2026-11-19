<x-app-layout>
    <div>
        @php
            $isEstimatorPricelist = auth()->user()->can('manage-price-requests') && ! auth()->user()->can('manage-sales') && ! auth()->user()->can('manage-admin');
            $backHref = $isEstimatorPricelist ? route('estimator.dashboard') : route('sales.dashboard');
            $canViewCost = auth()->user()->can('viewCost', \App\Models\Product::class);
            $canManageCost = $canManageCost ?? auth()->user()->can('manageCost', \App\Models\Product::class);

            $categoryOptions = collect(\App\Models\Product::SALE_CATEGORIES)
                ->map(fn ($category) => ['value' => $category, 'label' => \App\Models\Product::saleCategoryLabel($category)])
                ->values()->all();

            $filterParams = array_filter([
                'search' => request('search'),
                'category' => request('category'),
            ], fn ($value) => $value !== null && $value !== '');

            $chipUrl = function (string $key) use ($filterParams) {
                $params = $filterParams;
                if ($key !== 'all') $params['quick'] = $key;

                return route('products.index', $params);
            };

            $bulkUrl = route('products.index', array_merge($filterParams, ['bulk' => 1]));
            $bulkNeedsUrl = route('products.index', array_merge($filterParams, [
                'quick' => 'needs_recheck',
                'bulk' => 1,
            ]));
            $exitBulkUrl = route('products.index', array_merge($filterParams, $quick !== 'all' ? ['quick' => $quick] : []));

            $formatPct = fn ($value) => rtrim(rtrim(number_format((float) $value, 1, ',', '.'), '0'), ',');

            $chips = [
                'all' => ['label' => __('All Products'), 'dot' => null],
                'active' => ['label' => __('Active'), 'dot' => null],
                'needs_recheck' => ['label' => __('Needs Recheck'), 'dot' => 'bg-amber-500'],
                'negative_margin' => ['label' => __('Negative Margin'), 'dot' => 'bg-rose-500'],
                'expiring_soon' => ['label' => __('Expiring Soon'), 'dot' => 'bg-amber-400'],
            ];

            if (! $canViewCost) {
                $chips = array_intersect_key($chips, ['all' => true]);
            }
        @endphp

        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ $isEstimatorPricelist ? __('Pricelist') : __('Product Catalog') }}</h1>
                    @if($bulkMode)
                        <span class="inline-flex rounded-full border border-accent-200 bg-accent-50 px-2.5 py-1 text-xs font-semibold text-accent-700 dark:border-accent-800 dark:bg-accent-950/50 dark:text-accent-200">
                            {{ __('Bulk Price Update') }}
                        </span>
                    @endif
                </div>
                @unless($isEstimatorPricelist)
                    <p class="mt-1 text-slate-500">{{ __('Master goods & services for proposal BOQ.') }}</p>
                @endunless
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($bulkMode)
                    <x-back-button :href="$exitBulkUrl" />
                @else
                    <x-back-button :href="$backHref" />
                    @canany(['manage-sales', 'manage-admin', 'manage-products'])
                        <x-flow-button as="a" href="{{ route('products.create') }}" icon="add" label="{{ __('Add Product') }}" title="{{ __('Add Product') }}" />
                    @endcanany
                    @if($canManageCost)
                        <x-icon-button as="a" icon="edit" :href="$bulkUrl" label="{{ __('Review Prices') }}" title="{{ __('Bulk Edit Prices') }}" />
                    @endif
                @endif
            </div>
        </div>

        @if($bulkMode)
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-accent-200 bg-accent-50/70 px-4 py-3 text-sm text-accent-900 dark:border-accent-800/60 dark:bg-accent-950/30 dark:text-accent-100">
                <p class="min-w-0">
                    {{ __('Bulk edit only shows products with price history. Stock and warranty are copied from the latest price.') }}
                    <span class="block text-xs text-accent-700/80 dark:text-accent-200/80">{{ __('Use Set Price for products without price history.') }}</span>
                </p>
                @if($quick === 'needs_recheck')
                    <a href="{{ $bulkUrl }}" class="shrink-0 text-xs font-semibold underline underline-offset-2 hover:no-underline">{{ __('Show all products with price history') }}</a>
                @elseif(($counts['bulk_needs'] ?? 0) > 0)
                    <a href="{{ $bulkNeedsUrl }}" class="shrink-0 text-xs font-semibold underline underline-offset-2 hover:no-underline">{{ __('Show only Needs Recheck') }}</a>
                @endif
            </div>
        @else
            <form method="GET" action="{{ route('products.index') }}" class="mb-3 flex flex-wrap items-end gap-2 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                @if($quick !== 'all')
                    <input type="hidden" name="quick" value="{{ $quick }}">
                @endif
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search name / SKU...') }}"
                       class="h-10 min-w-[220px] flex-1 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                <div class="w-full sm:w-44">
                    <x-glide-select name="category" :options="$categoryOptions" :value="request('category')"
                                    :placeholder="__('All categories')" size="field" />
                </div>
                <x-icon-button icon="filter" type="submit" title="{{ __('Filter Products') }}" />
                <x-icon-button as="a" icon="reset" href="{{ route('products.index') }}" title="{{ __('Reset Filters & Reload') }}" />
            </form>

            <div class="mb-4 flex flex-wrap items-center gap-2">
                @foreach($chips as $key => $chip)
                    <a href="{{ $chipUrl($key) }}"
                       aria-current="{{ $quick === $key ? 'true' : 'false' }}"
                       class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition-all duration-200 {{ $quick === $key ? 'border-accent-600 bg-accent-600 text-white shadow-sm shadow-accent-600/20' : 'border-slate-200 bg-[var(--card-bg)] text-slate-600 hover:border-accent-400 hover:text-slate-900 dark:border-slate-700 dark:text-slate-300 dark:hover:text-slate-100' }}">
                        @if($chip['dot'])
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $quick === $key ? 'bg-white/80' : $chip['dot'] }}" aria-hidden="true"></span>
                        @endif
                        {{ $chip['label'] }}
                        <span class="inline-flex min-w-[20px] items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold leading-none tabular-nums {{ $quick === $key ? 'bg-white text-accent-700 dark:bg-white/20 dark:text-white' : 'bg-accent-50 text-accent-700 dark:bg-slate-700 dark:text-slate-300' }}">
                            {{ $counts[$key] ?? 0 }}
                        </span>
                    </a>
                @endforeach
            </div>

        @endif

        @if($bulkMode)
            <form id="bulk-price-form" method="POST" action="{{ route('products.bulk-price-update') }}"
                  x-data="bulkPrices()" @input="refresh()" @change="refresh()">
                @csrf
                @if(request('search'))
                    <input type="hidden" name="search" value="{{ request('search') }}">
                @endif
                @if(request('category'))
                    <input type="hidden" name="category" value="{{ request('category') }}">
                @endif
                <input type="hidden" name="quick" value="{{ $quick }}">
                <input type="hidden" name="bulk" value="1">

                <div class="mb-3">
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                        </svg>
                        <input type="search" @input="setQuery($event.target.value)"
                               placeholder="{{ __('Search product name or SKU...') }}"
                               class="h-10 w-full rounded-xl border-slate-300 pl-9 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="hidden border-b border-slate-100 bg-slate-50/80 px-5 py-2.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 lg:grid lg:grid-cols-12 lg:gap-3 dark:border-slate-700 dark:bg-slate-900/30 dark:text-slate-500">
                        <span class="lg:col-span-4">{{ __('Product') }}</span>
                        <span class="lg:col-span-2">{{ __('Cost') }}</span>
                        <span class="lg:col-span-3">{{ __('Supplier') }}</span>
                        <span class="lg:col-span-3">{{ __('Valid Until') }}</span>
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @forelse($products as $product)
                            @php
                                $log = $product->latestCostLog;
                                $margin = $product->margin_percentage;
                                $marginStatus = $product->margin_status;
                                $trend = $product->price_trend;
                                $originalValues = implode('|', [
                                    number_format((float) $log->price, 0, ',', '.'),
                                    (string) ($log->supplier_id ?? ''),
                                    $log->valid_until?->format('Y-m-d') ?? '',
                                ]);
                            @endphp
                            <div class="flex flex-col gap-3 p-4 transition-colors hover:bg-slate-50/90 lg:grid lg:grid-cols-12 lg:items-start lg:gap-3 lg:px-5 lg:py-3.5 dark:hover:bg-slate-700/35"
                                 data-bulk-row
                                 data-search="{{ \Illuminate\Support\Str::lower($product->name.' '.($product->sku ?? '')) }}"
                                 data-original="{{ $originalValues }}"
                                 x-show="matchesRow($el)">
                                <div class="min-w-0 lg:col-span-4">
                                    <p class="text-base font-bold text-slate-900 dark:text-slate-100">{{ $product->name }}</p>
                                    <p class="mt-0.5 text-[12px] font-medium text-slate-600 dark:text-slate-300">
                                        {{ $product->sku ?? '-' }} • {{ \App\Models\Product::saleCategoryLabel($product->category) }} • /{{ $product->unit }}
                                    </p>
                                    <p class="mt-1 flex flex-wrap items-center gap-2 text-[12px] text-slate-600 dark:text-slate-300">
                                        <span>{{ __('Selling') }} <strong class="font-semibold tabular-nums">Rp {{ number_format((float) $product->default_price, 0, ',', '.') }}</strong></span>
                                        @if($margin !== null && $marginStatus)
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold tabular-nums {{ $marginStatus === 'loss' ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300' : ($marginStatus === 'low' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200') }}">
                                                {{ $marginStatus === 'loss' ? __('Loss') : ($marginStatus === 'low' ? __('Low Margin') : __('Margin')) }}
                                                {{ $margin > 0 ? '+' : '' }}{{ $formatPct($margin) }}%
                                            </span>
                                        @endif
                                    </p>
                                    <p class="mt-1 text-[12px] text-slate-600 dark:text-slate-300">
                                        {{ $log->supplier?->name ?? __('No supplier') }}
                                        @if($log->stock_status)
                                            · {{ $log->stock_status === 'indent' ? __('Indent') : __('Ready') }}
                                        @endif
                                        @if($log->warranty_info)
                                            · {{ $log->warranty_info }}
                                        @endif
                                    </p>
                                </div>

                                <div class="lg:col-span-2">
                                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400 lg:hidden">{{ __('Cost') }}</label>
                                    <div class="relative">
                                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-semibold text-slate-500 dark:text-slate-400">Rp</span>
                                        <input type="text" inputmode="numeric" name="prices[{{ $product->id }}][cost]"
                                               value="{{ old("prices.{$product->id}.cost", number_format((float) $log->price, 0, ',', '.')) }}"
                                               @input="formatCost($event)"
                                               class="h-[40px] w-full rounded-xl border-slate-300 pl-9 text-sm tabular-nums focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-100">
                                    </div>
                                    @if($trend)
                                        <p class="mt-1 text-[12px] font-semibold tabular-nums {{ $trend['direction'] === 'up' ? 'text-rose-600 dark:text-rose-300' : 'text-emerald-600 dark:text-emerald-300' }}">
                                            {{ $trend['direction'] === 'up' ? '↑' : '↓' }} {{ $formatPct(abs($trend['percentage'])) }}%
                                        </p>
                                    @endif
                                </div>

                                <div class="lg:col-span-3">
                                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400 lg:hidden">{{ __('Supplier') }}</label>
                                    <x-glide-select name="prices[{{ $product->id }}][supplier_id]"
                                                    :options="$supplierOptions"
                                                    :value="old('prices.'.$product->id.'.supplier_id', $log->supplier_id)"
                                                    :placeholder="__('Select supplier')" size="field" />
                                </div>

                                <div class="lg:col-span-3">
                                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400 lg:hidden">{{ __('Valid Until') }}</label>
                                    <input type="date" name="prices[{{ $product->id }}][valid_until]"
                                           value="{{ old('prices.'.$product->id.'.valid_until', $log->valid_until?->format('Y-m-d')) }}"
                                           class="h-[40px] w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-100">
                                </div>
                            </div>
                        @empty
                            <div class="px-5 py-12 text-center">
                                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ __('No products with price history match this view.') }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ __('Use Set Price for products without price history.') }}</p>
                            </div>
                        @endforelse
                        @if($products->isNotEmpty())
                            <div x-show="visible === 0" x-cloak class="px-5 py-10 text-center">
                                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ __('No matching products found.') }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ __('Try another product name or SKU.') }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="h-44 sm:h-24" aria-hidden="true"></div>

                <div class="fixed inset-x-0 bottom-24 z-40 flex justify-center px-4 sm:bottom-6">
                    <div class="flex w-full max-w-2xl flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-2xl backdrop-blur dark:border-slate-700 dark:bg-slate-800/95">
                        <p class="text-sm text-slate-600 dark:text-slate-300">
                            <span class="font-bold tabular-nums text-slate-900 dark:text-slate-100" x-text="dirty"></span>
                            {{ __('item(s) changed') }}
                        </p>
                        <div class="flex items-center gap-2">
                            <a href="{{ $exitBulkUrl }}" @click="cancel($event)"
                               class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-300 bg-[var(--card-bg)] px-5 text-sm font-medium text-slate-700 transition hover:bg-[var(--menu-hover-bg)] active:scale-95 dark:border-slate-600 dark:text-slate-200">
                                {{ __('Cancel') }}
                            </a>
                            <button type="submit" :disabled="dirty === 0"
                                    class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-accent-600 px-5 text-sm font-semibold text-white transition hover:bg-accent-700 hover:shadow-lg hover:shadow-accent-500/30 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50">
                                <span>{{ __('Save Changes') }}</span>
                                <span class="tabular-nums" x-text="'(' + dirty + ')'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        @else
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="hidden border-b border-slate-100 bg-slate-50/80 px-5 py-2.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 lg:grid lg:grid-cols-12 lg:gap-3 dark:border-slate-700 dark:bg-slate-900/30 dark:text-slate-500">
                    <span class="lg:col-span-3">{{ __('Product') }}</span>
                    <span class="lg:col-span-2 text-right">{{ __('Cost') }}</span>
                    <span class="lg:col-span-2 pr-6 text-right">{{ __('Selling Price') }}</span>
                    <span class="lg:col-span-2">{{ __('Margin') }}</span>
                    <span class="lg:col-span-3 text-right">{{ __('Actions') }}</span>
                </div>

                <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($products as $product)
                        @php
                            $log = $product->latestCostLog;
                            $validUntil = $log?->valid_until;
                            $isExpired = $validUntil ? $validUntil->copy()->endOfDay()->isPast() : false;
                            $cost = $product->effectiveCostPrice();
                            $margin = $product->margin_percentage;
                            $marginStatus = $product->margin_status;
                            $trend = $product->price_trend;
                            $isZeroMargin = $margin !== null && abs((float) $margin) < 0.0001;

                            $priceStatus = 'needs';
                            if ($log && $validUntil && ! $isExpired && $product->cost_price !== null && $product->default_price > 0 && ! $isZeroMargin) {
                                $priceStatus = 'active';
                            } elseif ($log && $validUntil && $isExpired) {
                                $priceStatus = 'expired';
                            }

                            $marginText = match ($marginStatus) {
                                'loss' => __('Loss'),
                                'low' => __('Low Margin'),
                                'healthy' => __('Margin'),
                                default => null,
                            };
                            $marginClasses = match ($marginStatus) {
                                'loss' => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-950/50 dark:text-rose-200',
                                'low' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
                                'healthy' => 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200',
                                default => 'border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400',
                            };
                        @endphp
                        <div class="flex flex-col gap-3 p-4 transition-colors hover:bg-slate-50/90 lg:grid lg:grid-cols-12 lg:items-center lg:gap-3 lg:px-5 lg:py-3.5 dark:hover:bg-slate-700/35">
                            <div class="min-w-0 lg:col-span-3">
                                <p class="text-base font-bold text-slate-900 dark:text-slate-100">{{ $product->name }}</p>

                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    @unless($product->is_active)
                                        <span class="inline-flex rounded-full bg-slate-200 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-600 dark:text-slate-300">{{ __('Nonaktif') }}</span>
                                    @endunless
                                    @if($canViewCost)
                                        @if($priceStatus === 'active')
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                                {{ __('Active') }}
                                            </span>
                                        @elseif($priceStatus === 'expired')
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-2.5 py-0.5 text-[11px] font-semibold text-amber-800 dark:border-amber-700 dark:bg-amber-950/50 dark:text-amber-200">
                                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                                                {{ __('Expired') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-300 bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">
                                                <span class="h-1.5 w-1.5 rounded-full bg-slate-400" aria-hidden="true"></span>
                                                {{ __('Needs Recheck') }}
                                            </span>
                                        @endif
                                    @endif
                                </div>

                                <p class="mt-1 text-[12px] font-medium text-slate-600 dark:text-slate-300">
                                    {{ $product->sku ?? '-' }} • {{ \App\Models\Product::saleCategoryLabel($product->category) }} • /{{ $product->unit }}
                                </p>

                                @if($canViewCost)
                                    <p class="mt-1 text-[12px] text-slate-600 dark:text-slate-300">
                                        @if($log)
                                            {{ $log->supplier?->name ?? __('No supplier') }}
                                            @if($log->stock_status)
                                                · {{ $log->stock_status === 'indent' ? __('Indent').($log->stock_eta ? ' ('.$log->stock_eta.')' : '') : __('Ready') }}
                                            @endif
                                            @if($log->warranty_info)
                                                · {{ $log->warranty_info }}
                                            @endif
                                            · {{ __('Updated') }} {{ $log->created_at?->format('d M Y') }}
                                        @else
                                            {{ __('No price history yet.') }}
                                        @endif
                                    </p>
                                @endif
                            </div>

                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:contents">
                                <div class="text-right lg:col-span-2">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 lg:hidden">{{ __('Cost') }}</p>
                                    @if($canViewCost)
                                        @if($cost !== null)
                                            <p class="font-bold tabular-nums text-slate-900 dark:text-slate-100">
                                                Rp {{ number_format($cost, 0, ',', '.') }}
                                            </p>
                                            @if($trend)
                                                <p class="mt-0.5 text-[12px] font-semibold tabular-nums {{ $trend['direction'] === 'up' ? 'text-rose-600 dark:text-rose-300' : 'text-emerald-600 dark:text-emerald-300' }}"
                                                   title="{{ __('Compared to the previous cost') }}">
                                                    {{ $trend['direction'] === 'up' ? '↑' : '↓' }} {{ $formatPct(abs($trend['percentage'])) }}%
                                                </p>
                                            @endif
                                        @else
                                            <p class="font-semibold text-slate-400">—</p>
                                        @endif
                                    @else
                                        <p class="font-semibold text-slate-400">—</p>
                                    @endif
                                </div>

                                <div class="text-right lg:col-span-2 lg:pr-6">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 lg:hidden">{{ __('Selling Price') }}</p>
                                    <p class="font-semibold tabular-nums text-slate-800 dark:text-slate-100">
                                        Rp {{ number_format((float) $product->default_price, 0, ',', '.') }}
                                    </p>
                                </div>

                                <div class="lg:col-span-2">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 lg:hidden">{{ __('Margin') }}</p>
                                    @if($margin !== null && $marginText)
                                        <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold tabular-nums {{ $marginClasses }}">
                                            @if($marginStatus === 'loss')
                                                <svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 3.5h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                                                </svg>
                                            @endif
                                            {{ $marginText }} {{ $margin > 0 ? '+' : '' }}{{ $formatPct($margin) }}%
                                        </span>
                                    @else
                                        <p class="font-semibold text-slate-400">—</p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-1.5 lg:col-span-3 lg:justify-end">
                                @if($canManageCost)
                                    <x-icon-button as="a" :icon="$log ? 'upload' : 'add'" href="{{ route('products.price.edit', $product) }}"
                                                   :label="$log ? __('Update Price') : __('Set Price')"
                                                   :title="$log ? __('Update Price') : __('Set Price')" />
                                @endif
                                @canany(['manage-sales', 'manage-admin'])
                                    <x-icon-button as="a" icon="edit" href="{{ route('products.edit', $product) }}" title="{{ __('Edit Product Details') }}" />
                                    @if($product->is_active)
                                        <form action="{{ route('products.destroy', $product) }}" method="POST" class="inline"
                                              data-confirm="{{ __('Deactivate this product?') }}" data-confirm-tone="warning">
                                            @csrf @method('DELETE')
                                            <x-icon-button icon="delete" type="submit" title="{{ __('Deactivate Product') }}" />
                                        </form>
                                    @endif
                                @endcanany
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-12 text-center">
                            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ __('No products match this filter.') }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ __('Try another keyword, category, or filter.') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>

            @if($products->hasPages())
                <div class="mt-4">{{ $products->links() }}</div>
            @endif
        @endif
    </div>

    @push('scripts')
        <script>
            function bulkPrices() {
                return {
                    dirty: 0,
                    query: '',
                    visible: 0,
                    init() {
                        this.filterRows();
                        this.refresh();
                    },
                    setQuery(value) {
                        this.query = value;
                        this.filterRows();
                    },
                    matchesRow(row) {
                        const query = (this.query || '').trim().toLowerCase();
                        if (!query) return true;

                        return (row.dataset.search || '').includes(query);
                    },
                    filterRows() {
                        this.visible = [...this.$root.querySelectorAll('[data-bulk-row]')]
                            .filter((row) => this.matchesRow(row))
                            .length;
                    },
                    formatCost(event) {
                        const input = event.target;
                        const digits = input.value.replace(/\D/g, '');
                        input.value = digits === ''
                            ? ''
                            : new Intl.NumberFormat('id-ID').format(parseInt(digits, 10));
                        this.refresh();
                    },
                    refresh() {
                        let count = 0;
                        this.$root.querySelectorAll('[data-bulk-row]').forEach((row) => {
                            const values = [...row.querySelectorAll('input, select')]
                                .map((input) => input.value)
                                .join('|');
                            if (values !== row.dataset.original) count++;
                        });
                        this.dirty = count;
                    },
                    async cancel(event) {
                        const href = event.currentTarget.getAttribute('href');

                        if (this.dirty > 0 && typeof window.askConfirm === 'function') {
                            event.preventDefault();
                            const ok = await window.askConfirm({
                                title: @js(__('Discard price changes?')),
                                message: @js(__('Your unsaved price changes will be lost.')),
                                tone: 'warning',
                                confirmLabel: @js(__('Discard changes')),
                            });
                            if (!ok) return;
                        }

                        window.location.href = href;
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
