@forelse($leads as $lead)
    @php
        $cfg = \App\Support\LeadWorkspacePresenter::row($lead, $notesByLead[$lead->id] ?? []);
        $contact = \App\Support\LeadWorkspacePresenter::contactLinks($lead);
        $last = $lead->latestActivityFollowUp;
        $reasonOptions = collect(\App\Models\Lead::LOST_REASONS)
            ->map(fn ($reason) => ['value' => $reason, 'label' => \App\Models\Lead::lostReasonLabel($reason)]);
    @endphp
    <div x-data="myLeadsRow({{ json_encode($cfg) }})" class="relative overflow-hidden rounded-2xl border border-slate-200 bg-[var(--card-bg)] p-4 dark:border-slate-600 dark:bg-slate-800">
        <span class="absolute inset-y-0 left-0 w-1" :class="colorDotClass()" aria-hidden="true"></span>
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <a href="{{ route('leads.show', $lead) }}" class="font-semibold text-slate-900 dark:text-slate-100">{{ $lead->customer?->name ?? '-' }}</a>
                <div class="mt-1 flex flex-wrap items-center gap-1">
                    @include('sales.my-leads._color-picker')
                    @if($lead->pt_group)
                        <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold {{ \App\Models\Lead::PT_COLORS[$lead->pt_group] ?? 'bg-indigo-50 text-indigo-700' }}">{{ $lead->pt_group }}</span>
                    @endif
                    @if($lead->isAssignmentOverdue())
                        <span class="inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-[10px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                            <x-icon name="clock" class="h-3 w-3" />
                            {{ __('Overdue') }}
                        </span>
                    @endif
                    <span class="text-[10px] text-slate-400">{{ $lead->project_name ?: '-' }}</span>
                </div>
            </div>
            <div class="relative shrink-0">
                <select x-model="stage" @change="stageChanged($event.target.value)" :disabled="stageSaving"
                        class="h-8 rounded-xl border border-slate-300 bg-[var(--card-bg)] px-2 text-[11px] font-semibold text-slate-700 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                    @foreach($statuses as $status)
                        <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <div x-show="lostOpen" x-cloak class="absolute right-0 top-9 z-30 w-64 rounded-xl border border-slate-200 bg-[var(--card-bg)] p-3 shadow-xl dark:border-slate-600 dark:bg-slate-800">
                    <select x-model="lostReason" class="w-full rounded-xl border-slate-300 text-xs dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                        <option value="">{{ __('Loss reason') }}</option>
                        @foreach($reasonOptions as $reason)
                            <option value="{{ $reason['value'] }}">{{ $reason['label'] }}</option>
                        @endforeach
                    </select>
                    <input type="text" x-model="lostNote" maxlength="2000" placeholder="{{ __('Short note') }}"
                           class="mt-2 w-full rounded-xl border-slate-300 text-xs dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                    <div class="mt-2 flex gap-2">
                        <button type="button" @click="confirmLost()" class="h-8 flex-1 rounded-xl bg-red-600 text-xs font-semibold text-white active:scale-95">{{ __('Confirm') }}</button>
                        <button type="button" @click="cancelLost()" class="h-8 flex-1 rounded-xl border border-slate-200 text-xs font-medium text-slate-600 active:scale-95 dark:border-slate-600 dark:text-slate-200">{{ __('Cancel') }}</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3 grid grid-cols-2 gap-2 text-[11px] text-slate-500 dark:text-slate-400">
            <div>
                <p class="font-semibold text-slate-600 dark:text-slate-300">{{ __('PIC') }}</p>
                <p>{{ $lead->customer?->picName() ?? '-' }}</p>
            </div>
            <div>
                <p class="font-semibold text-slate-600 dark:text-slate-300">{{ __('Contact') }}</p>
                <p>{{ $lead->customer?->picWhatsapp() ?? $lead->customer?->picPhone() ?? '-' }}</p>
            </div>
        </div>

        <div class="mt-3">
            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Latest Note') }}</p>
            @include('sales.my-leads._note-cell')
        </div>

        <div class="mt-3 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Last Activity') }}</p>
                <p class="text-xs text-slate-600 dark:text-slate-300">
                    {{ $last?->follow_up_date?->format('d M Y') ?? __('No activity') }}
                    @if($last) <span class="text-slate-400">- {{ \App\Models\FollowUp::typeLabel($last->type) }}</span> @endif
                </p>
            </div>
            <div>
                <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Next Follow Up') }}</p>
                @include('sales.my-leads._next-cell')
            </div>
        </div>

        <div class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-700">
            @include('sales.my-leads._contact-actions', ['lead' => $lead, 'contact' => $contact, 'followUp' => null, 'first' => $loop->first])
        </div>
    </div>
@empty
    <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-10 text-center dark:border-slate-600">
        <p class="text-sm font-medium text-slate-500 dark:text-slate-300">{{ __('No leads match this filter.') }}</p>
        <p class="mt-1 text-xs text-slate-400">{{ __('Clear the filter to see all your leads.') }}</p>
    </div>
@endforelse
