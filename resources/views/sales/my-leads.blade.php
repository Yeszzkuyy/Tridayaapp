<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">My Leads</h1>
            <p class="text-slate-500 mt-1">{{ __('Leads assigned to you by Management') }}</p>
        </div>
        <div class="flex gap-2">
            <x-icon-button as="a" icon="import" href="{{ route('sales.my-leads.export', request()->only(['search', 'status', 'touched', 'active', 'won_month', 'sort'])) }}" title="Export CSV" />
            <x-icon-button as="a" href="{{ route('sales.dar.export', request()->only(['date_from', 'date_to', 'user_id'])) }}" title="Export DAR">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75h6m-6-3h6m-7.5 10.5h9a2.25 2.25 0 0 0 2.25-2.25v-7.19a2.25 2.25 0 0 0-.66-1.59l-2.81-2.81a2.25 2.25 0 0 0-1.59-.66H6.75A2.25 2.25 0 0 0 4.5 7.5v11.25A2.25 2.25 0 0 0 6.75 21Z" />
                </svg>
            </x-icon-button>
            <x-icon-button as="a" icon="back" href="{{ route('sales.dashboard') }}" title="Dashboard Sales" />
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
                    <x-icon-button icon="filter" type="submit" title="Filter" />
                    @if(request('search') || request('status') || request('touched') || request('active') || request('won_month') || request('sort'))
                        <x-icon-button as="a" icon="reset" href="{{ route('sales.my-leads') }}" title="Reset" />
                    @endif
                </div>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">Lead / Customer</th>
                        <th class="px-6 py-4 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Status') }}</th>
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
                                    {{ $lead->customer->contact_person ? 'PIC: '.$lead->customer->contact_person : '' }}
                                </div>
                                <div class="mt-1 text-[11px] text-slate-500 whitespace-nowrap">
                                    {{ $lead->meetings_count }} {{ __('meeting') }} • {{ $lead->follow_ups_count }} {{ __('follow up') }}
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
                                    <a href="{{ route('leads.show', $lead) }}"
                                       title="{{ __('View details') }}"
                                       aria-label="{{ __('View details') }} {{ $lead->customer?->name }}"
                                       class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 transition dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">
                                        <x-icon name="eye" class="h-4 w-4" />
                                    </a>
                                    @if($lead->has_done_fu)
                                        <span title="{{ __('Done') }}" aria-label="{{ __('Done') }} {{ $lead->customer?->name }}"
                                              class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300">
                                            <x-icon name="check-circle" class="h-4 w-4" />
                                        </span>
                                    @else
                                        <a href="{{ route('sales.follow-ups.create', ['customer_id' => $lead->customer_id, 'lead_id' => $lead->id]) }}"
                                           title="{{ __('Create Follow Up') }}"
                                           aria-label="{{ __('Create Follow Up') }} {{ $lead->customer?->name }}"
                                           class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-700 transition dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20">
                                            <x-icon name="chat" class="h-4 w-4" />
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
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
</x-app-layout>
