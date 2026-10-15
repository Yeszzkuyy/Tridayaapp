<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Data Partner') }}</h1>
            <p class="text-slate-500 mt-1">{{ $partners->total() }} partner terdaftar &mdash; supplier, vendor, kontraktor, partner, dan distributor</p>
        </div>
        @can('manage-marketing')
            <x-icon-button as="a" icon="add" href="{{ route('partners.create') }}" title="{{ __('Tambah Partner') }}" />
        @endcan
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">

        {{-- Toolbar --}}
        <form method="GET" class="p-5 border-b border-slate-200 dark:border-slate-600 flex flex-col sm:flex-row gap-3 sm:items-start"
              x-data="{ timer: null, loading: false, error: false }"
              :aria-busy="loading">
            <div class="relative flex-1 min-w-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                     stroke="currentColor" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
                <input type="search" name="search" value="{{ request('search') }}"
                       placeholder="{{ __('Cari nama, kontak, atau telepon...') }}"
                       autocomplete="off"
                       class="w-full pl-10 rounded-xl border border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600"
                       x-on:input.debounce.400ms="
                           loading = true;
                           error = false;
                           clearTimeout(timer);
                           timer = setTimeout(() => {
                               const params = new URLSearchParams(new FormData($el.form));
                               fetch('{{ route('partners.index') }}?' + params.toString(), {
                                   headers: { 'X-Requested-With': 'XMLHttpRequest' }
                               })
                               .then(response => {
                                   if (!response.ok) throw new Error('Search failed');
                                   return response.text();
                               })
                               .then(html => {
                                   document.getElementById('partner-table').innerHTML = html;
                                   const qs = params.toString();
                                   history.replaceState(null, '', qs ? '{{ url('partners') }}?' + qs : '{{ url('partners') }}');
                               })
                               .catch(() => { error = true; })
                               .finally(() => { loading = false; });
                           }, 100);
                       "
                >
                <div class="min-h-5 pt-1.5 text-xs" aria-live="polite">
                    <span x-cloak x-show="loading" class="text-slate-400">{{ __('Mencari partner...') }}</span>
                    <span x-cloak x-show="error" class="text-red-500">{{ __('Pencarian gagal. Coba lagi.') }}</span>
                </div>
            </div>
            <select name="type"
                    class="sm:w-44 rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                <option value="">{{ __('Semua Tipe') }}</option>
                @foreach(\App\Models\Partner::TYPES as $val => $label)
                    <option value="{{ $val }}" {{ request('type') == $val ? 'selected' : '' }}>{{ __($label) }}</option>
                @endforeach
            </select>
            <div class="flex items-center gap-2">
                <x-icon-button icon="filter" type="submit" title="Filter" />
                <x-icon-button as="a" icon="reset" href="{{ route('partners.index') }}" title="Reset" />
            </div>
        </form>

        <div id="partner-table">
            @include('partners._table')
        </div>
    </div>
</x-app-layout>
