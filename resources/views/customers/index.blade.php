<x-app-layout>
    @if(session('success') && session('success_card'))
        <x-status-card :message="session('success')" />
    @endif

    @php
        // Chip filter cepat (tanpa mengubah pencarian/PT yang aktif).
        $chipQuery = request()->except('page', 'pic');
        $chipUrl = fn (string $value) => route('customers.index', array_filter(
            array_merge($chipQuery, $value === '' ? [] : ['pic' => $value]),
            fn ($v) => $v !== null && $v !== '',
        ));
        $chips = [
            '' => __('All'),
            'has' => __('With PIC'),
            'none' => __('No PIC'),
        ];
    @endphp

    <div class="w-full space-y-6">
        {{-- Open header: judul + jumlah + filter cepat + CTA utama --}}
        <div class="flex flex-col gap-4 pt-1 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-slate-100">
                        {{ __('Customer List') }}
                    </h1>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 tabular-nums dark:bg-slate-700/70 dark:text-slate-300">
                        {{ number_format($customers->total()) }} {{ __('Customers') }}
                    </span>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2" role="group" aria-label="{{ __('Customer filters') }}">
                    @foreach($chips as $value => $label)
                        @php $isActive = ($picFilter ?? '') === $value; @endphp
                        <a href="{{ $chipUrl($value) }}"
                           @if($isActive) aria-current="true" @endif
                           class="inline-flex h-8 items-center rounded-xl px-3 text-xs font-semibold transition-colors active:scale-95 {{ $isActive
                               ? 'bg-accent-600 text-white shadow-sm shadow-accent-500/25'
                               : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700/60 dark:hover:text-white' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
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
                    <input type="hidden" name="pic" value="{{ $picFilter ?? '' }}" data-customer-pic>

                    <div class="min-w-0 flex-1 sm:max-w-xl">
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
                                            const pic = document.querySelector('[data-customer-pic]')?.value.trim() ?? '';
                                            const params = new URLSearchParams();
                                            if ($el.value.trim()) params.set('search', $el.value.trim());
                                            if (pt) params.set('pt_group', pt);
                                            if (pic) params.set('pic', pic);
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
                            <span x-cloak x-show="loading" class="text-slate-400">{{ __('Searching customers...') }}</span>
                            <span x-cloak x-show="error" class="text-red-500">{{ __('Search failed. Try again.') }}</span>
                        </div>
                    </div>

                    <div class="w-full sm:w-44 sm:shrink-0">
                        <label for="customer-pt" class="sr-only">{{ __('Filter by Company') }}</label>
                        <x-glide-select id="customer-pt" name="pt_group" :label="__('Filter by Company')"
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
