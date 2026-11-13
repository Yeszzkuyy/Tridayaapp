<table class="min-w-[1280px] w-full divide-y divide-slate-200 dark:divide-slate-600">
    <thead class="bg-slate-50 dark:bg-slate-700">
        <tr>
            <th class="px-3 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">#</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Company / Customer') }}</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('PIC') }}</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Contact') }}</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Project Name') }}</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Pipeline Stage') }}</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Latest Note') }}</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Last Activity') }}</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Next Follow Up') }}</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Action') }}</th>
        </tr>
    </thead>
    <tbody class="bg-[var(--card-bg)] dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-600">
        @forelse($leads as $lead)
            @php
                $cfg = \App\Support\LeadWorkspacePresenter::row($lead);
                $contact = \App\Support\LeadWorkspacePresenter::contactLinks($lead);
                $last = $lead->latestActivityFollowUp;
                $next = $lead->activeFollowUp;
                $reasonOptions = collect(\App\Models\Lead::LOST_REASONS)
                    ->map(fn ($reason) => ['value' => $reason, 'label' => \App\Models\Lead::lostReasonLabel($reason)]);
            @endphp
            <tr x-data="myLeadsRow({{ json_encode($cfg) }})" class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                <td class="relative px-3 py-3 text-xs text-slate-400 whitespace-nowrap">
                    <span class="absolute inset-y-0 left-0 w-1" :class="colorDotClass()" aria-hidden="true"></span>
                    {{ $leads->firstItem() + $loop->index }}
                </td>

                <td class="px-4 py-3">
                    <div class="min-w-[170px]">
                        <a href="{{ route('leads.show', $lead) }}" class="font-semibold text-slate-900 dark:text-slate-100 hover:text-accent-600 dark:hover:text-accent-400">
                            {{ $lead->customer?->name ?? '-' }}
                        </a>
                        <div class="mt-1 flex flex-wrap items-center gap-1">
                            @include('sales.my-leads._color-picker')
                            @if($lead->pt_group)
                                <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold {{ \App\Models\Lead::PT_COLORS[$lead->pt_group] ?? 'bg-indigo-50 text-indigo-700' }}">{{ $lead->pt_group }}</span>
                            @endif
                            @if($lead->acknowledged_at === null)
                                <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-accent-100 text-accent-700 dark:bg-accent-900/40 dark:text-accent-300">{{ __('New') }}</span>
                            @endif
                            @if($lead->isAssignmentOverdue())
                                <span class="inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-[10px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                    <x-icon name="clock" class="h-3 w-3" />
                                    {{ __('Overdue') }}
                                </span>
                            @endif
                            <span class="text-[10px] text-slate-400">{{ \App\Http\Controllers\LeadController::label((string) $lead->segment) }}</span>
                        </div>
                    </div>
                </td>

                <td class="px-4 py-3">
                    <div class="min-w-[120px] max-w-[170px]">
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate">{{ $lead->customer?->picName() ?? '-' }}</p>
                        <p class="text-[11px] text-slate-400 truncate">{{ $lead->customer?->picEmail() ?? '' }}</p>
                    </div>
                </td>

                <td class="px-4 py-3">
                    <div class="min-w-[130px] text-xs text-slate-600 dark:text-slate-300">
                        @if($lead->customer?->picWhatsapp())
                            <p class="font-medium">{{ $lead->customer->picWhatsapp() }}</p>
                        @endif
                        @if($lead->customer?->picPhone() && $lead->customer->picPhone() !== $lead->customer->picWhatsapp())
                            <p class="text-slate-400">{{ $lead->customer->picPhone() }}</p>
                        @endif
                        @if(!$lead->customer?->picWhatsapp() && !$lead->customer?->picPhone())
                            <span class="text-slate-400">-</span>
                        @endif
                    </div>
                </td>

                <td class="px-4 py-3">
                    <div class="min-w-[150px] max-w-[220px]">
                        <p class="text-sm text-slate-700 dark:text-slate-200 truncate" title="{{ $lead->project_name }}">
                            {{ $lead->project_name ?: ($lead->kebutuhan ?: '-') }}
                        </p>
                        @if($lead->need_type)
                            <p class="text-[11px] text-slate-400">{{ \App\Models\Lead::needTypeLabel($lead->need_type) }}</p>
                        @endif
                    </div>
                </td>

                <td class="px-4 py-3">
                    <div class="relative min-w-[130px]">
                        <select x-model="stage" @change="stageChanged($event.target.value)" :disabled="stageSaving"
                                class="h-9 w-full rounded-xl border border-slate-300 bg-[var(--card-bg)] px-2 text-xs font-semibold text-slate-700 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                            @foreach($statuses as $status)
                                <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                        <p x-show="stageSaving" x-cloak class="mt-1 text-[10px] text-slate-400">{{ __('Saving...') }}</p>

                        <div x-show="lostOpen" x-cloak class="absolute left-0 top-11 z-30 w-64 rounded-xl border border-slate-200 bg-[var(--card-bg)] p-3 shadow-xl dark:border-slate-600 dark:bg-slate-800">
                            <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ __('Loss reason') }}</p>
                            <select x-model="lostReason" class="mt-1 w-full rounded-xl border-slate-300 text-xs dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                                <option value="">{{ __('Choose reason') }}</option>
                                @foreach($reasonOptions as $reason)
                                    <option value="{{ $reason['value'] }}">{{ $reason['label'] }}</option>
                                @endforeach
                            </select>
                            <input type="text" x-model="lostNote" maxlength="2000" placeholder="{{ __('Short note') }}"
                                   class="mt-2 w-full rounded-xl border-slate-300 text-xs dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                            <div class="mt-2 flex gap-2">
                                <button type="button" @click="confirmLost()" class="h-8 flex-1 rounded-xl bg-red-600 text-xs font-semibold text-white transition hover:bg-red-700 active:scale-95">{{ __('Confirm') }}</button>
                                <button type="button" @click="cancelLost()" class="h-8 flex-1 rounded-xl border border-slate-200 text-xs font-medium text-slate-600 transition hover:bg-slate-50 active:scale-95 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">{{ __('Cancel') }}</button>
                            </div>
                        </div>
                    </div>
                </td>

                <td class="px-4 py-3">
                    @include('sales.my-leads._note-cell')
                </td>

                <td class="px-4 py-3 whitespace-nowrap">
                    @if($last)
                        <p class="text-xs font-medium text-slate-700 dark:text-slate-200">{{ $last->follow_up_date?->format('d M Y') ?? '-' }}</p>
                        <p class="text-[11px] text-slate-400">{{ \App\Models\FollowUp::typeLabel($last->type) }}</p>
                    @else
                        <span class="text-xs text-slate-400">{{ __('No activity') }}</span>
                    @endif
                </td>

                <td class="px-4 py-3">
                    @include('sales.my-leads._next-cell')
                </td>

                <td class="px-4 py-3">
                    <div class="flex items-center justify-end">
                        @include('sales.my-leads._contact-actions', ['lead' => $lead, 'contact' => $contact, 'followUp' => null, 'first' => $loop->first])
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="10" class="px-6 py-14 text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-300">
                        <x-icon name="briefcase" class="h-6 w-6" />
                    </span>
                    <p class="mt-4 text-sm font-medium text-slate-500 dark:text-slate-300">{{ __('No leads match this filter.') }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ __('New leads from Management appear here. Clear the filter to see all your leads.') }}</p>
                    @if(request()->anyFilled(['search', 'company', 'pic', 'project', 'status', 'last_activity', 'next_follow_up', 'overdue', 'touched', 'active', 'won_month', 'sort']))
                        <a href="{{ route('sales.my-leads') }}" class="mt-3 inline-flex h-9 items-center rounded-xl border border-slate-200 px-4 text-xs font-semibold text-accent-600 transition hover:bg-slate-50 dark:border-slate-600 dark:text-accent-400 dark:hover:bg-slate-700">{{ __('Reset filter') }}</a>
                    @endif
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
