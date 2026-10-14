<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Product Catalog') }}</h1>
                <p class="text-slate-500 mt-1">{{ __('Master barang & jasa untuk BOQ proposal.') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <x-icon-button as="a" icon="back" href="{{ route('sales.dashboard') }}" title="{{ __('Back') }}" />
                @canany(['manage-sales', 'manage-admin'])
                    <x-icon-button as="a" icon="add" href="{{ route('products.create') }}" title="{{ __('Add') }}" />
                @endcanany
            </div>
        </div>

        <form method="GET" action="{{ route('products.index') }}" class="flex flex-wrap items-center gap-2 mb-4">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Cari nama / SKU...') }}"
                   class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
            <select name="category" class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                <option value="">{{ __('Semua kategori') }}</option>
                @foreach(\App\Models\Product::SALE_CATEGORIES as $cat)
                    <option value="{{ $cat }}" @selected(request('category') === $cat)>{{ \App\Models\Product::saleCategoryLabel($cat) }}</option>
                @endforeach
            </select>
            <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
            <x-icon-button as="a" icon="reset" href="{{ route('products.index') }}" title="{{ __('Reset') }}" />
        </form>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-4">
            @forelse($products as $product)
                <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700/50 {{ !$loop->last ? 'border-b border-slate-100 dark:border-slate-700' : '' }}">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate">
                            {{ $product->name }}
                            @unless($product->is_active)
                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-200 text-slate-600 dark:bg-slate-600 dark:text-slate-300">{{ __('Nonaktif') }}</span>
                            @endunless
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ $product->sku ?? '-' }} • {{ \App\Models\Product::saleCategoryLabel($product->category) }} • /{{ $product->unit }} • Rp {{ number_format($product->default_price, 0, ',', '.') }}
                        </p>
                    </div>
                    @canany(['manage-sales', 'manage-admin'])
                        <a href="{{ route('products.edit', $product) }}" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}"
                           class="h-10 w-10 inline-flex items-center justify-center rounded-lg bg-blue-100 hover:bg-blue-200 text-blue-700 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.862 4.487z"/></svg>
                        </a>
                        @if($product->is_active)
                            <form action="{{ route('products.destroy', $product) }}" method="POST" class="inline"
                                  onsubmit="return confirm('{{ __('Nonaktifkan produk ini?') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" title="{{ __('Deactivate') }}" aria-label="{{ __('Deactivate') }}"
                                        class="h-10 w-10 inline-flex items-center justify-center rounded-lg bg-red-100 hover:bg-red-200 text-red-700 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 12h-15"/></svg>
                                </button>
                            </form>
                        @endif
                    @endcanany
                </div>
            @empty
                <p class="text-slate-500 text-center py-8">{{ __('Belum ada produk.') }}</p>
            @endforelse
            <div class="mt-4">{{ $products->links() }}</div>
        </div>
    </div>
</x-app-layout>
