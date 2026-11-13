<x-app-layout>
    @php
        $tabs = [
            ['key' => 'all', 'label' => __('All Open')],
            ['key' => 'due_today', 'label' => __('Due')],
            ['key' => 'upcoming', 'label' => __('Upcoming')],
            ['key' => 'completed', 'label' => __('Completed')],
        ];
        $tabUrl = fn (string $key) => route('sales.follow-ups.index', array_merge(
            request()->except(['tab', 'overdue', 'page']),
            ['tab' => $key]
        ));
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Follow Up') }}</h1>
        </div>
        @can('manage-sales')
            <x-icon-button as="a" variant="solid" reveal icon="add"
                           href="{{ route('sales.follow-ups.create') }}"
                           wire:navigate.hover
                           label="{{ __('Add Follow Up') }}"
                           title="{{ __('Add Follow Up') }}"
                           class="shrink-0" />
        @endcan
    </div>

    <div class="bg-[var(--card-bg)] dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-4 mb-4">
        <nav class="flex flex-wrap gap-1.5" aria-label="{{ __('Follow Up tabs') }}">
            @foreach($tabs as $tab)
                @php($isActive = ($activeTab ?? 'all') === $tab['key'])
                <a href="{{ $tabUrl($tab['key']) }}"
                   aria-current="{{ $isActive ? 'page' : 'false' }}"
                   class="inline-flex h-9 items-center gap-2 rounded-xl px-3 text-xs font-semibold transition-all duration-300 active:scale-95 {{ $isActive ? 'bg-accent-600 text-white shadow-sm shadow-accent-500/30' : 'border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700' }}">
                    {{ $tab['label'] }}
                    <span class="inline-flex min-w-5 justify-center rounded-full px-1.5 py-0.5 text-[10px] {{ $isActive ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300' }}">{{ $counts[$tab['key']] ?? 0 }}</span>
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('sales.follow-ups.index') }}" data-submit-guarded="ajax" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <input type="hidden" name="tab" value="{{ $activeTab ?? 'all' }}">
            {{-- Kompatibilitas tautan lama (?overdue=today/upcoming/1) → tab Due. --}}
            <input type="hidden" name="overdue" value="{{ request('overdue') }}">

            <div class="lg:col-span-2">
                <label for="fu-search" class="block text-xs font-medium text-slate-500 dark:text-slate-300 mb-1">{{ __('Search Customer') }}</label>
                <input id="fu-search" type="text" name="search" value="{{ request('search') }}"
                       placeholder="{{ __('Customer or project...') }}"
                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 dark:text-slate-300 mb-1">{{ __('Pipeline Stage') }}</label>
                <x-glide-select name="stage" label="{{ __('Pipeline Stage') }}"
                    :options="collect($stages ?? [])->map(fn ($s) => ['value' => $s, 'label' => ucfirst($s)])->all()"
                    :value="request('stage', '')" :empty-label="__('All Stages')" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 dark:text-slate-300 mb-1">{{ __('Follow Up Status') }}</label>
                <x-glide-select name="status" label="{{ __('Follow Up Status') }}"
                    :options="collect(\App\Models\FollowUp::STATUSES)->map(fn ($s) => ['value' => $s, 'label' => \App\Models\FollowUp::statusLabel($s)])->all()"
                    :value="request('status', '')" :empty-label="__('All Statuses')" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 dark:text-slate-300 mb-1">{{ __('Activity Type') }}</label>
                <x-glide-select name="activity_type" label="{{ __('Activity Type') }}"
                    :options="collect($types ?? [])->map(fn ($t) => ['value' => $t, 'label' => \App\Models\FollowUp::typeLabel($t)])->all()"
                    :value="request('activity_type', '')" :empty-label="__('All Types')" />
            </div>
            <div>
                <label for="fu-customer" class="block text-xs font-medium text-slate-500 dark:text-slate-300 mb-1">{{ __('Customer') }}</label>
                <input id="fu-customer" type="text" name="customer" value="{{ request('customer') }}"
                       placeholder="{{ __('Customer name...') }}"
                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
            </div>
            <div>
                <label for="fu-from" class="block text-xs font-medium text-slate-500 dark:text-slate-300 mb-1">{{ __('Date from') }}</label>
                <input id="fu-from" type="date" name="date_from" value="{{ request('date_from') }}"
                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
            </div>
            <div>
                <label for="fu-to" class="block text-xs font-medium text-slate-500 dark:text-slate-300 mb-1">{{ __('Date to') }}</label>
                <input id="fu-to" type="date" name="date_to" value="{{ request('date_to') }}"
                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
            </div>
            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4">
                <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
                @if(request()->anyFilled(['search', 'stage', 'status', 'activity_type', 'customer', 'date_from', 'date_to', 'overdue']))
                    <x-icon-button as="a" icon="reset" href="{{ route('sales.follow-ups.index', ['tab' => $activeTab ?? 'all']) }}" title="{{ __('Reset') }}" />
                @endif
            </div>
        </form>
    </div>

    <div x-data="followUpComplete()" class="mb-8">
        <div id="followups-table" class="bg-[var(--card-bg)] dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
            @include('sales.follow-ups._table')
        </div>

        @include('sales.follow-ups._complete-modal')
    </div>

    <script>
        window.followUpComplete = function () {
            return {
                open: false,
                action: '',
                customer: '',
                outcome: '',
                nextDate: '',
                nextTime: '',
                openComplete(detail) {
                    this.action = detail.action;
                    this.customer = detail.customer || '';
                    this.outcome = '';
                    this.nextDate = '';
                    this.nextTime = '';
                    this.open = true;
                },
                close() {
                    this.open = false;
                },
            };
        };
    </script>
</x-app-layout>
