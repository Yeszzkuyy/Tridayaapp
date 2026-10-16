<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Follow Up') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Record and monitor all customer follow ups.') }}</p>
        </div>
        @can('manage-sales')
            <x-icon-button as="a" icon="add" href="{{ route('sales.follow-ups.create') }}" title="{{ __('Add Follow Up') }}" />
        @endcan
    </div>

    {{-- Search --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5 mb-6">
        {{-- data-submit-guarded manual: form ini AJAX (fetch tanpa reload), jadi
             submit-guard glider dilewati — kalau tidak, overlay loading glider
             tidak pernah hilang dan tombol terkunci selamanya. --}}
        <form method="GET" data-ajax data-ajax-target="#followups-table" data-submit-guarded="ajax" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Search Customer') }}</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="{{ __('Customer name...') }}"
                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Status') }}</label>
                <x-glide-select name="overdue" label="{{ __('Status') }}"
                    :options="[
                        ['value' => 'today', 'label' => __('Hari ini')],
                        ['value' => 'upcoming', 'label' => __('Upcoming')],
                        ['value' => '1', 'label' => __('Due')],
                    ]"
                    :value="request('overdue', '')" :empty-label="__('All')" id="fu-status-glider" />
                <script>
                    document.getElementById('fu-status-glider')?.querySelector('input[type="hidden"]')
                        ?.addEventListener('change', function () { this.form?.requestSubmit(); });
                </script>
            </div>
            <div class="flex items-end gap-2">
                <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
                @if(request()->anyFilled(['search', 'overdue']))
                    <x-icon-button as="a" icon="reset" href="{{ route('sales.follow-ups.index') }}" title="{{ __('Reset') }}" data-ajax-reset />
                @endif
            </div>
        </form>
    </div>

    {{-- Follow Ups section --}}
    <div class="mb-8" id="followups">
        <div id="followups-table" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
            @include('sales.follow-ups._table')
        </div>
    </div>

</x-app-layout>
