<x-app-layout>
    <div>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('Supplier Directory') }}</h1>
            </div>
            <x-back-button :href="route('estimator.requests.index')" />
        </div>

        <form method="GET" action="{{ route('estimator.suppliers') }}" class="mb-4 flex flex-wrap items-end gap-2 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
            <div class="min-w-[240px] flex-1">
                <label for="supplier-search" class="mb-1 block text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ __('Search') }}</label>
                <input id="supplier-search" type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Name, contact person, phone, or email') }}"
                       class="h-10 w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
            </div>
            <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
            <x-icon-button as="a" icon="reset" href="{{ route('estimator.suppliers') }}" title="{{ __('Reset') }}" />
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @forelse($suppliers as $supplier)
                    <div class="flex flex-wrap items-center gap-4 p-4 sm:p-5">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300" aria-hidden="true">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21"/>
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-slate-800 dark:text-slate-100">{{ $supplier->name }}</p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                {{ $supplier->contact_person ?: __('No contact person') }}
                                @if($supplier->phone)
                                    &middot; {{ $supplier->phone }}
                                @endif
                                @if($supplier->email)
                                    &middot; {{ $supplier->email }}
                                @endif
                            </p>
                            <p class="mt-0.5 text-[11px] text-slate-400">
                                {{ trans_choice(':count product|:count products', $supplier->products_count, ['count' => $supplier->products_count]) }}
                                @unless($supplier->is_active)
                                    &middot; {{ __('Inactive') }}
                                @endunless
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($supplier->phone)
                                <x-icon-button as="a" icon="whatsapp" href="https://wa.me/{{ preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $supplier->phone)) }}" target="_blank" rel="noopener" title="{{ __('WhatsApp') }}" />
                            @endif
                            @if($supplier->email)
                                <x-icon-button as="a" icon="mail" href="mailto:{{ $supplier->email }}" title="{{ __('Email') }}" />
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-10 text-center">
                        <x-empty-state label="{{ __('suppliers') }}" description="{{ __('No suppliers match this filter.') }}" />
                    </div>
                @endforelse
            </div>
        </div>

        @if($suppliers->hasPages())
            <div class="mt-4">{{ $suppliers->links() }}</div>
        @endif
    </div>
</x-app-layout>
