<x-app-layout>
    <div x-data="{ tips: localStorage.getItem('sales_followup_tips') !== 'off' }">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('My Leads') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Leads assigned to you by Management') }}</p>
        </div>
        <div class="flex gap-2">
            <button type="button"
                    @click="tips = true; localStorage.setItem('sales_followup_tips', 'on')"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-300 bg-white text-slate-500 transition-all duration-300 hover:bg-slate-50 hover:text-accent-600 active:scale-95 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                    title="{{ __('Show guide') }}">
                <span class="text-sm font-bold">?</span>
                <span class="sr-only">{{ __('Show guide') }}</span>
            </button>
            <x-icon-button as="a" icon="import" href="{{ route('sales.my-leads.export', request()->only(['search', 'status', 'touched', 'active', 'won_month', 'sort'])) }}" title="{{ __('Export CSV') }}" />
            <x-icon-button as="a" icon="back" href="{{ route('sales.dashboard') }}" title="{{ __('Sales Dashboard') }}" />
        </div>
    </div>

    @php($newLeadCount = $leads->getCollection()->whereNull('acknowledged_at')->count())
    <div x-show="tips" x-cloak
         class="mb-4 rounded-2xl border border-accent-200 bg-accent-50/60 p-4 dark:border-accent-700 dark:bg-accent-900/20">
        <div class="flex items-start gap-3">
            <x-icon name="bolt" class="h-5 w-5 shrink-0 text-accent-600 dark:text-accent-400" />
            <div class="min-w-0 flex-1 text-sm text-slate-700 dark:text-slate-200">
                <p class="font-semibold">{{ __('Quick guide: 3 steps to follow up') }}</p>
                <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <div class="rounded-xl border border-accent-200/70 bg-white/70 px-3 py-2 dark:border-accent-700/60 dark:bg-slate-800/60">
                        <span class="text-xs font-semibold text-accent-700 dark:text-accent-300">1</span>
                        <p class="mt-0.5">{{ __('Pick a prospect from the table.') }}</p>
                    </div>
                    <div class="rounded-xl border border-accent-200/70 bg-white/70 px-3 py-2 dark:border-accent-700/60 dark:bg-slate-800/60">
                        <span class="text-xs font-semibold text-accent-700 dark:text-accent-300">2</span>
                        <p class="mt-0.5">{{ __('Click the + Follow Up button in the Actions column.') }}</p>
                    </div>
                    <div class="rounded-xl border border-accent-200/70 bg-white/70 px-3 py-2 dark:border-accent-700/60 dark:bg-slate-800/60">
                        <span class="text-xs font-semibold text-accent-700 dark:text-accent-300">3</span>
                        <p class="mt-0.5">{{ __('Fill the interaction result and save it to the timeline.') }}</p>
                    </div>
                </div>
                @if($newLeadCount > 0)
                    <p class="mt-2 font-medium text-accent-700 dark:text-accent-300">{{ __('You have :count new lead(s) from Management. Record the first follow up today.', ['count' => $newLeadCount]) }}</p>
                @endif
            </div>
            <button type="button" @click="localStorage.setItem('sales_followup_tips', 'off'); tips = false"
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-white hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                    title="{{ __('Dismiss') }}">
                <span class="sr-only">{{ __('Dismiss') }}</span>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600">
        <div class="p-5 border-b dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-t-2xl">
            <form method="GET" action="{{ route('sales.my-leads') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                @if(request()->filled('active'))
                    <input type="hidden" name="active" value="{{ request('active') }}">
                @endif
                @if(request()->filled('won_month'))
                    <input type="hidden" name="won_month" value="{{ request('won_month') }}">
                @endif
                <div>
                    <label for="my-leads-search" class="text-sm font-medium text-slate-500">{{ __('Search customer') }}</label>
                    <input id="my-leads-search" type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search customer...') }}"
                           class="mt-1 w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-500">{{ __('Status') }}</label>
                    <x-glide-select name="status" class="mt-1" :label="__('Status')"
                        :options="collect($statuses ?? [])->map(fn ($s) => ['value' => $s, 'label' => ucfirst($s)])->all()"
                        :value="request('status', '')" :empty-label="__('All Statuses')" autosubmit />
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-500">{{ __('Activity') }}</label>
                    <x-glide-select name="touched" class="mt-1" :label="__('Activity')"
                        :options="[['value' => 'yes', 'label' => __('Touched')], ['value' => 'no', 'label' => __('Untouched')]]"
                        :value="request('touched', '')" :empty-label="__('All Activity')" autosubmit />
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-500">{{ __('Sort by') }}</label>
                    <x-glide-select name="sort" class="mt-1" :label="__('Sort by')"
                        :options="[['value' => 'oldest', 'label' => __('Oldest')], ['value' => 'customer', 'label' => __('Customer A-Z')]]"
                        :value="request('sort', '')" :empty-label="__('Newest')" autosubmit />
                </div>
                <div class="sm:col-span-2 lg:col-span-1 flex items-end gap-2">
                    <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
                    @if(request('search') || request('status') || request('touched') || request('active') || request('won_month') || request('sort'))
                        <x-icon-button as="a" icon="reset" href="{{ route('sales.my-leads') }}" title="{{ __('Reset') }}" />
                    @endif
                </div>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Lead / Customer') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Status') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider whitespace-nowrap">{{ __('Follow Up') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Needs / Progress') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider whitespace-nowrap">{{ __('Entry Date') }}</th>
                        <th class="px-6 py-4 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-600">
                    @forelse($leads as $lead)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                            <td class="px-6 py-4">
                                <div class="font-medium text-slate-900">{{ $lead->customer->name ?? 'N/A' }}</div>
                                <div class="text-sm text-slate-500">
                                    @if($lead->pt_group)<span class="inline-flex px-1.5 py-0.5 rounded {{ \App\Models\Lead::PT_COLORS[$lead->pt_group] ?? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' }} text-[11px] font-semibold mr-1">{{ $lead->pt_group }}</span>@endif
                                    {{ $lead->customer?->picName() ? 'PIC: '.$lead->customer->picName() : '' }}
                                </div>
                                <div class="mt-1">
                                    @if($lead->has_done_fu)
                                        <span class="inline-flex px-1.5 py-0.5 rounded text-[11px] font-semibold bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">{{ __('Done') }}</span>
                                    @else
                                        <span class="inline-flex px-1.5 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">{{ __('Pending FU') }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap
                                    @switch($lead->status)
                                        @case('cool') bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 @break
                                        @case('warm') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300 @break
                                        @case('hot') bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300 @break
                                        @case('won') bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300 @break
                                        @case('lost') bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 @break
                                        @default bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200
                                    @endswitch
                                ">
                                    {{ \App\Models\Lead::salesStatusLabel($lead->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold whitespace-nowrap {{ $lead->follow_ups_count > 0 ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300' }}">
                                    {{ $lead->follow_ups_count }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="min-w-[220px] max-w-[340px]">
                                    @if($lead->kebutuhan)
                                        <p class="text-sm text-slate-700 line-clamp-2" title="{{ $lead->kebutuhan }}">{{ $lead->kebutuhan }}</p>
                                    @endif
                                    @if($lead->solusi)
                                        <p class="mt-1 text-xs text-slate-500 line-clamp-1" title="{{ $lead->solusi }}">{{ __('Solution') }}: {{ $lead->solusi }}</p>
                                    @endif
                                    @if($lead->progress_notes)
                                        <p class="mt-1 text-xs text-slate-500 line-clamp-1" title="{{ $lead->progress_notes }}">{{ __('Progress') }}: {{ $lead->progress_notes }}</p>
                                    @endif
                                    @if(!$lead->kebutuhan && !$lead->solusi && !$lead->progress_notes)
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                @if($lead->incoming_date)
                                    <span class="text-sm text-slate-700">{{ $lead->incoming_date->format('d M Y') }}</span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <div class="flex justify-end gap-2">
                                    <x-icon-button as="a" icon="view" href="{{ route('leads.show', $lead) }}" title="{{ __('View details') }}" />
                                    @if($lead->has_done_fu)
                                        <x-icon-button as="a" icon="mail" href="{{ route('sales.follow-ups.create', ['customer_id' => $lead->customer_id, 'lead_id' => $lead->id]) }}" title="{{ __('Follow Up') }}" />
                                    @else
                                        <span @if($loop->first) data-tour="followup" @endif>
                                            <x-icon-button as="a" icon="add" label="{{ __('Follow Up') }}" href="{{ route('sales.follow-ups.create', ['customer_id' => $lead->customer_id, 'lead_id' => $lead->id]) }}" title="{{ __('Follow Up') }}" />
                                        </span>
                                    @endif
                        </tr>
                    @empty
                        <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-300">
                                    <x-icon name="briefcase" class="h-6 w-6" />
                                </span>
                                <p class="mt-4 text-sm font-medium text-slate-500">{{ __('No leads assigned to you yet.') }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ __('New leads from Management will appear here.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t">
            {{ $leads->links() }}
        </div>
    </div>
    </div>

    @if($leads->isNotEmpty())
        <script type="module" src="{{ \Illuminate\Support\Facades\Vite::asset('resources/js/leads-tour.js') }}"></script>
    @endif
</x-app-layout>
