{{-- Filter My Leads: cepat di atas, filter lanjutan bisa dilipat. --}}
<div class="p-5 border-b border-slate-200 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-700/40 rounded-t-2xl">
    <form method="GET" action="{{ route('sales.my-leads') }}" class="space-y-4">
        @if(request()->filled('active'))
            <input type="hidden" name="active" value="{{ request('active') }}">
        @endif
        @if(request()->filled('won_month'))
            <input type="hidden" name="won_month" value="{{ request('won_month') }}">
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label for="my-leads-search" class="text-sm font-medium text-slate-500 dark:text-slate-300">{{ __('Search customer') }}</label>
                <input id="my-leads-search" type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Customer or project...') }}"
                       class="mt-1 w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-500 dark:text-slate-300">{{ __('Pipeline Stage') }}</label>
                <x-glide-select name="status" class="mt-1" :label="__('Pipeline Stage')"
                    :options="collect($statuses ?? [])->map(fn ($s) => ['value' => $s, 'label' => \App\Models\Lead::salesStatusLabel($s)])->all()"
                    :value="request('status', '')" :empty-label="__('All Stages')" autosubmit />
            </div>
            <div>
                <label class="text-sm font-medium text-slate-500 dark:text-slate-300">{{ __('Next Follow Up') }}</label>
                <x-glide-select name="next_follow_up" class="mt-1" :label="__('Next Follow Up')"
                    :options="[
                        ['value' => 'today', 'label' => __('Due Today')],
                        ['value' => 'upcoming', 'label' => __('Upcoming')],
                        ['value' => 'none', 'label' => __('Not Scheduled')],
                    ]"
                    :value="request('next_follow_up', '')" :empty-label="__('All Schedules')" autosubmit />
            </div>
            <div>
                <label class="text-sm font-medium text-slate-500 dark:text-slate-300">{{ __('Last Activity') }}</label>
                <x-glide-select name="last_activity" class="mt-1" :label="__('Last Activity')"
                    :options="[
                        ['value' => 'today', 'label' => __('Today')],
                        ['value' => 'week', 'label' => __('This Week')],
                        ['value' => 'month', 'label' => __('This Month')],
                        ['value' => 'none', 'label' => __('No Activity')],
                    ]"
                    :value="request('last_activity', '')" :empty-label="__('All Activity')" autosubmit />
            </div>
            <div>
                <label class="text-sm font-medium text-slate-500 dark:text-slate-300">{{ __('Sort by') }}</label>
                <x-glide-select name="sort" class="mt-1" :label="__('Sort by')"
                    :options="[['value' => 'oldest', 'label' => __('Oldest')], ['value' => 'customer', 'label' => __('Customer A-Z')]]"
                    :value="request('sort', '')" :empty-label="__('Newest')" autosubmit />
            </div>
        </div>

        <div class="flex flex-wrap items-end justify-between gap-3">
            <details class="group w-full sm:w-auto">
                <summary class="cursor-pointer select-none text-sm font-medium text-slate-500 transition hover:text-accent-600 dark:text-slate-300 dark:hover:text-accent-400">
                    {{ __('More filters') }}
                </summary>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="my-leads-company" class="text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('Company') }}</label>
                        <input id="my-leads-company" type="text" name="company" value="{{ request('company') }}" placeholder="{{ __('Company name...') }}"
                               class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                    </div>
                    <div>
                        <label for="my-leads-pic" class="text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('PIC') }}</label>
                        <input id="my-leads-pic" type="text" name="pic" value="{{ request('pic') }}" placeholder="{{ __('PIC name...') }}"
                               class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                    </div>
                    <div>
                        <label for="my-leads-project" class="text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('Project') }}</label>
                        <input id="my-leads-project" type="text" name="project" value="{{ request('project') }}" placeholder="{{ __('Project name...') }}"
                               class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('Follow Up') }}</label>
                        <x-glide-select name="overdue" class="mt-1" :label="__('Follow Up')"
                            :options="[['value' => '1', 'label' => __('Overdue (>24 hours)')]]"
                            :value="request('overdue', '')" :empty-label="__('All')" autosubmit />
                    </div>
                </div>
            </details>

            <div class="flex items-center gap-2">
                <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
                @if(request()->anyFilled(['search', 'company', 'pic', 'project', 'status', 'last_activity', 'next_follow_up', 'overdue', 'touched', 'active', 'won_month', 'sort']))
                    <x-icon-button as="a" icon="reset" href="{{ route('sales.my-leads') }}" title="{{ __('Reset') }}" />
                @endif
            </div>
        </div>
    </form>
</div>
