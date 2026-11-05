<x-app-layout>
    @if(session('success') && session('success_card'))
        <x-status-card :message="session('success')" />
    @endif

    <div class="w-full space-y-6">
        {{-- Open header: judul + deskripsi singkat + CTA utama --}}
        <div class="flex flex-col gap-4 pt-1 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-slate-100">
                    {{ __('Customer List') }}
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Manage all Tridaya App customer data.') }}
                </p>
            </div>

            @can('manage-sales')
                <x-icon-button as="a" variant="solid" reveal icon="add"
                               href="{{ route('customers.create') }}"
                               label="{{ __('Add Customer') }}"
                               title="{{ __('Add Customer') }}"
                               class="shrink-0" />
            @endcan
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90">
            <div class="border-b border-slate-100 p-4 sm:p-5 dark:border-slate-700/70">
                <form method="GET" action="{{ route('customers.index') }}"
                      class="flex flex-col gap-3 sm:flex-row sm:items-center"
                      x-data="{ timer: null, loading: false, error: false }"
                      :aria-busy="loading">
                    <div class="relative min-w-0 flex-1 sm:max-w-xl">
                        <label for="customer-search" class="sr-only">{{ __('Search customer') }}</label>
                        <div class="relative">
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input id="customer-search"
                                   type="search"
                                   name="search"
                                   value="{{ request('search') }}"
                                   placeholder="{{ __('Search name, company, or email...') }}"
                                   autocomplete="off"
                                   class="h-10 w-full rounded-xl border border-slate-300 pl-10 pr-4 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600"
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
                        {{-- Status pencarian diposisikan absolut agar tinggi baris
                             filter tetap 40px dan sejajar dengan dropdown/tombol. --}}
                        <div class="pointer-events-none absolute left-1 top-full mt-1 text-xs" aria-live="polite">
                            <span x-cloak x-show="loading" class="text-slate-400">{{ __('Searching customers...') }}</span>
                            <span x-cloak x-show="error" class="text-red-500">{{ __('Search failed. Try again.') }}</span>
                        </div>
                    </div>

                    <div class="w-full sm:w-44 sm:shrink-0">
                        <label for="customer-pt" class="sr-only">{{ __('Filter by Company') }}</label>
                        <x-glide-select id="customer-pt" name="pt_group" size="field" :label="__('Filter by Company')"
                            :options="collect($ptGroups)->all()"
                            :value="request('pt_group', '')" :empty-label="__('All Companies')" autosubmit />
                    </div>

                    <x-icon-button icon="search" type="submit" title="{{ __('Search') }}" class="shrink-0" />
                </form>
            </div>

            <div id="customer-table">
                @include('customers._list')
            </div>

            <div class="border-t border-slate-100 px-4 py-3 sm:px-5 dark:border-slate-700/70">
                {{ $customers->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
