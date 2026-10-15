<x-app-layout>
    @if(session('success') && session('success_card'))
        <x-status-card :message="session('success')" />
    @endif
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-accent-600 dark:text-accent-300">{{ __('Data relasi') }}</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-800 dark:text-slate-100 sm:text-3xl">
                {{ __('Daftar Customer') }}
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ __('Kelola seluruh data customer Tridaya App.') }}
            </p>
        </div>

        @can('manage-sales')
            <x-icon-button as="a" icon="add" href="{{ route('customers.create') }}" title="{{ __('Add Customer') }}" size="lg" />
        @endcan
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="border-b border-slate-200/80 p-5 dark:border-slate-700 sm:p-6">
            <form method="GET" action="{{ route('customers.index') }}"
                  class="flex flex-col gap-3 sm:flex-row sm:items-start"
                  x-data="{ timer: null, loading: false, error: false }"
                  :aria-busy="loading">
                <div class="min-w-0 flex-1 sm:max-w-xl">
                    <label for="customer-search" class="sr-only">{{ __('Cari customer') }}</label>
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input id="customer-search"
                               type="search"
                               name="search"
                               value="{{ request('search') }}"
                               placeholder="{{ __('Cari nama, perusahaan, atau email...') }}"
                               autocomplete="off"
                               class="h-11 w-full rounded-xl border border-slate-300 pl-10 pr-4 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600"
                               x-on:input.debounce.400ms="
                                   loading = true;
                                   error = false;
                                   clearTimeout(timer);
                                    timer = setTimeout(() => {
                                        const pt = document.getElementById('customer-pt')?.value.trim() ?? '';
                                        const params = new URLSearchParams();
                                        if ($el.value.trim()) params.set('search', $el.value.trim());
                                        if (pt) params.set('pt_group', pt);
                                        fetch('{{ route('customers.index') }}?' + params.toString(), {
                                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                                        })
                                        .then(response => {
                                            if (!response.ok) throw new Error('Search failed');
                                            return response.text();
                                        })
                                        .then(html => {
                                            document.getElementById('customer-table').innerHTML = html;
                                            const url = params.toString() ? '{{ url('customers') }}?' + params.toString() : '{{ url('customers') }}';
                                            history.replaceState(null, '', url);
                                        })
                                       .catch(() => { error = true; })
                                       .finally(() => { loading = false; });
                                   }, 100);
                               "
                        >
                    </div>
                    <div class="min-h-5 pt-1.5 text-xs" aria-live="polite">
                        <span x-cloak x-show="loading" class="text-slate-400">{{ __('Mencari customer...') }}</span>
                        <span x-cloak x-show="error" class="text-red-500">{{ __('Pencarian gagal. Coba lagi.') }}</span>
                    </div>
                </div>
                <div class="w-full sm:w-44 sm:shrink-0">
                    <label for="customer-pt" class="sr-only">{{ __('Filter Company') }}</label>
                    <x-glide-select id="customer-pt" name="pt_group" size="lg" :label="__('Filter Company')"
                        :options="collect($ptGroups)->all()"
                        :value="request('pt_group', '')" :empty-label="__('Semua Company')" autosubmit />
                </div>
                <x-icon-button icon="search" type="submit" title="{{ __('Cari') }}" size="lg" />
            </form>
        </div>

        <div id="customer-table">
            @include('customers._list')
        </div>
        <div class="mt-4">{{ $customers->links() }}</div>
    </div>

</x-app-layout>
