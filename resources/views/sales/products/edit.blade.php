<x-app-layout>
    <div class="max-w-2xl">
        <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100 mb-6">{{ __('Edit Produk') }}</h1>
        <form action="{{ route('products.update', $product) }}" method="POST" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-4">
            @csrf @method('PUT')
            @include('sales.products._form')
            <div class="flex items-center gap-2 pt-2">
                <button type="submit" class="group relative overflow-hidden px-5 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-500 text-white text-sm font-medium transition-all duration-300 hover:scale-105 hover:shadow-lg hover:shadow-accent-500/40 hover:brightness-110 active:scale-95">
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full"></span>
                    {{ __('Save') }}
                </button>
                <a href="{{ route('products.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-white dark:border-slate-600 dark:text-slate-200 text-sm font-medium transition">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
