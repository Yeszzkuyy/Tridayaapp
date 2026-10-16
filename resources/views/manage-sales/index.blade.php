<x-app-layout>
    @if(session('success') && session('success_card'))
        <x-status-card :message="session('success')" />
    @endif
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Manage Sales') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Leads from marketing — fill in the solution, follow-up progress, and assign to Sales') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('Total Leads') }}</p>
            <p class="mt-1 text-3xl font-bold text-slate-800 dark:text-slate-100">{{ $stats['total'] ?? 0 }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('Not assigned yet') }}</p>
            <p class="mt-1 text-3xl font-bold {{ ($stats['unassigned'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-800 dark:text-slate-100' }}">{{ $stats['unassigned'] ?? 0 }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('Won This Month') }}</p>
            <p class="mt-1 text-3xl font-bold text-green-700 dark:text-green-300">{{ $stats['won_month'] ?? 0 }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('Lost This Month') }}</p>
            <p class="mt-1 text-3xl font-bold text-slate-800 dark:text-slate-100">{{ $stats['lost_month'] ?? 0 }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('Overdue Follow Ups') }}</p>
            <p class="mt-1 text-3xl font-bold text-slate-800 dark:text-slate-100">{{ $stats['overdue'] ?? 0 }}</p>
            @if(($bySales ?? collect())->isNotEmpty())
                <div class="mt-2 space-y-1 border-t border-slate-100 dark:border-slate-700 pt-2">
                    @foreach($bySales as $row)
                        <p class="flex justify-between text-xs text-slate-500 dark:text-slate-400">
                            <span class="truncate">{{ $row->assignee?->name ?? '-' }}</span>
                            <span class="font-bold text-slate-700 dark:text-slate-200">{{ $row->total }}</span>
                        </p>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600">
        <div class="p-5 border-b dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-t-2xl">
            <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="text-sm font-medium text-slate-500">{{ __('Search Customer') }}</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="{{ __('Customer name...') }}"
                           class="mt-1 w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-500">{{ __('Assignment Status') }}</label>
                    <x-glide-select name="assignment" class="mt-1" :label="__('Assignment Status')" :empty-label="__('All')"
                        :options="[['value' => 'new', 'label' => __('NEW (Unassigned)')], ['value' => 'assigned', 'label' => __('ASSIGNED')]]"
                        :value="request('assignment', '')" autosubmit />
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-500">{{ __('Sales Activity') }}</label>
                    <x-glide-select name="touched" class="mt-1" :label="__('Sales Activity')" :empty-label="__('All')"
                        :options="[['value' => 'yes', 'label' => __('Touched')], ['value' => 'no', 'label' => __('Untouched')]]"
                        :value="request('touched', '')" autosubmit />
                </div>
                <div class="sm:col-span-2 lg:col-span-1 flex items-end gap-2">
                    <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
                    <x-icon-button as="a" icon="reset" href="{{ route('manage-sales.index') }}" title="{{ __('Reset') }}" />
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Lead / Customer') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Status') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Activity') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Requirement') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Entry Date') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Lead from PT') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Assign / Direct to Sales') }}</th>
                        <th class="px-6 py-4 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-600">
                    @forelse($leads as $lead)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition align-middle">
                            <td class="px-6 py-4">
                                <div class="font-medium text-slate-900">{{ $lead->customer->name ?? __('N/A') }}</div>
                                <div class="text-sm text-slate-500">
                                    @if($lead->pt_group)<span class="inline-flex px-1.5 py-0.5 rounded {{ \App\Models\Lead::PT_COLORS[$lead->pt_group] ?? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' }} text-[11px] font-semibold mr-1">{{ $lead->pt_group }}</span>@endif
                                    {{ $lead->customer->contact_person ? 'PIC: '.$lead->customer->contact_person : '' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($lead->assignee)
                                    <div class="inline-flex items-center justify-center gap-2">
                                        <x-user-avatar :user="$lead->assignee" size="w-7 h-7" text="text-[11px]" color="green" :clickable="false" />
                                        <div class="flex flex-col items-start text-left leading-tight">
                                            <span class="text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $lead->assignee->name }}</span>
                                            <span class="text-[10px] uppercase tracking-wide text-green-600 dark:text-green-400">{{ __('Assigned') }}</span>
                                        </div>
                                    </div>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-700 dark:text-blue-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                        {{ __('New') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                @php $touched = ($lead->follow_ups_count ?? 0) > 0; @endphp
                                @if($touched)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300 whitespace-nowrap">
                                        <x-icon name="check-circle" class="h-3.5 w-3.5" />
                                        {{ __('Touched') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300 whitespace-nowrap">
                                        <span class="h-1.5 w-1.5 rounded-full bg-yellow-500"></span>
                                        {{ __('Untouched') }}
                                    </span>
                                @endif
                                <span class="block mt-1 text-[11px] text-slate-500 whitespace-nowrap">
                                    {{ $lead->follow_ups_count ?? 0 }} {{ __('follow ups') }}
                                </span>
                                @if($lead->last_follow_up_at)
                                    <span class="block text-[11px] text-slate-400 whitespace-nowrap">
                                        {{ __('Last') }}: {{ \Carbon\Carbon::parse($lead->last_follow_up_at)->format('d M Y') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-left">
                                @if($lead->kebutuhan)
                                    <span class="text-sm text-slate-700 whitespace-normal break-words max-w-[220px] inline-block align-middle">{{ $lead->kebutuhan }}</span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($lead->incoming_date)
                                    <span class="text-sm text-slate-700">{{ $lead->incoming_date->format('d M Y') }}</span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-left">
                                <form action="{{ route('manage-sales.update', $lead) }}" method="POST" data-loading-text="{{ __('Updating…') }}">
                                    @csrf
                                    @method('PUT')
                                    <x-glide-select name="pt_group" size="sm" class="w-24" :label="__('Lead from PT')" empty-label="—"
                                        :options="collect(\App\Models\Lead::PT_GROUPS)->map(fn ($g) => ['value' => $g, 'label' => $g])->all()"
                                        :value="$lead->pt_group" autosubmit />
                                </form>
                            </td>
                            <td class="px-6 py-4 text-left">
                                <form action="{{ route('manage-sales.assign', $lead) }}" method="POST" class="flex items-center gap-2" data-loading-text="{{ __('Assigning…') }}">
                                    @csrf
                                    <x-glide-select name="assigned_to" size="sm" class="w-40" :label="__('Assign to Sales')"
                                        :placeholder="__('— Select Sales —')"
                                        :options="$salesUsers->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all()"
                                        :value="$lead->assigned_to" required />
                                    <x-icon-button type="submit" title="{{ __('Assign to Sales') }}" label="{{ __('Assign') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                                        </svg>
                                    </x-icon-button>
                                </form>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <x-icon-button as="a" href="{{ route('manage-sales.edit', $lead) }}" title="{{ __('Manage lead (solution, progress, notes)') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.142-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.149-.894Z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                    </svg>
                                </x-icon-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-500">
                                {{ __('No leads from marketing yet.') }}
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