<table class="min-w-[980px] w-full divide-y divide-slate-200 dark:divide-slate-600">
    <thead class="bg-slate-50 dark:bg-slate-700">
        <tr>
            <th class="px-3 py-3 text-left text-xs font-semibold text-content-secondary uppercase tracking-wider">#</th>
            <th class="px-2 py-3 text-left text-xs font-semibold text-content-secondary uppercase tracking-wider">{{ __('Company / Customer') }}</th>
            <th class="px-2 py-3 text-left text-xs font-semibold text-content-secondary uppercase tracking-wider">{{ __('PIC') }}</th>
            <th class="px-2 py-3 text-left text-xs font-semibold text-content-secondary uppercase tracking-wider">{{ __('Contact') }}</th>
            <th class="px-2 py-3 text-left text-xs font-semibold text-content-secondary uppercase tracking-wider">{{ __('Project Name') }}</th>
            <th class="px-2 py-3 text-left text-xs font-semibold text-content-secondary uppercase tracking-wider">{{ __('Pipeline Stage') }}</th>
            <th class="px-2 py-3 text-left text-xs font-semibold text-content-secondary uppercase tracking-wider">{{ __('Latest Note') }}</th>
            <th class="px-2 py-3 text-left text-xs font-semibold text-content-secondary uppercase tracking-wider">{{ __('Last Activity') }}</th>
            <th class="px-2 py-3 text-left text-xs font-semibold text-content-secondary uppercase tracking-wider">{{ __('Next Follow Up') }}</th>
            {{-- Kolom Aksi dikunci di pinggir kanan: latar wajib menutup sel,
                 supaya isi tabel yang digeser tidak tembus di belakang tombol. --}}
            <th class="sticky right-0 z-20 border-l border-slate-200 bg-slate-50 px-2 py-3 text-center text-xs font-semibold text-content-secondary uppercase tracking-wider dark:border-slate-600 dark:bg-slate-700">{{ __('Action') }}</th>
        </tr>
    </thead>
    <tbody class="bg-[var(--card-bg)] dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-600">
        @forelse($leads as $lead)
            @php
                $cfg = \App\Support\LeadWorkspacePresenter::row($lead);
                $last = $lead->latestActivityFollowUp;
                $next = $lead->activeFollowUp;
                $reasonOptions = collect(\App\Models\Lead::LOST_REASONS)
                    ->map(fn ($reason) => ['value' => $reason, 'label' => \App\Models\Lead::lostReasonLabel($reason)]);
            @endphp
            <tr x-data="myLeadsRow({{ json_encode($cfg) }})" class="group transition hover:bg-slate-50 dark:hover:bg-slate-700/40">
                <td class="relative px-3 py-3 text-xs text-content-tertiary whitespace-nowrap">
                    <span class="absolute inset-y-0 left-0 w-1" :class="stageDotClass()" aria-hidden="true"></span>
                    {{ $leads->firstItem() + $loop->index }}
                </td>

                <td class="px-2 py-3">
                    <div class="min-w-[112px] max-w-[136px]">
                        <a href="{{ route('leads.show', $lead) }}" title="{{ $lead->customer?->name }}" class="block truncate font-semibold text-xs text-content-primary hover:text-accent-600 dark:hover:text-accent-400">
                            {{ $lead->customer?->name ?? '-' }}
                        </a>
                        <div class="mt-1 flex flex-wrap items-center gap-1">
                            @if($lead->pt_group)
                                <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-semibold {{ \App\Models\Lead::PT_COLORS[$lead->pt_group] ?? 'bg-indigo-50 text-indigo-700' }}">{{ $lead->pt_group }}</span>
                            @endif
                            @if($lead->acknowledged_at === null)
                                <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-semibold bg-accent-100 text-accent-700 dark:bg-accent-900/40 dark:text-accent-300">{{ __('New') }}</span>
                            @endif
                            @if($lead->isAssignmentOverdue())
                                <span class="inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-xs font-semibold bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                    <x-icon name="clock" class="h-3 w-3" />
                                    {{ __('Overdue') }}
                                </span>
                            @endif
                            <span class="text-xs text-content-tertiary">{{ \App\Http\Controllers\LeadController::label((string) $lead->segment) }}</span>
                        </div>
                    </div>
                </td>

                <td class="px-2 py-3">
                    <div class="min-w-[92px] max-w-[106px]">
                        <p class="truncate text-xs font-medium text-content-primary">{{ $lead->customer?->picName() ?? '-' }}</p>
                        <p class="text-xs text-content-tertiary truncate">{{ $lead->customer?->picEmail() ?? '' }}</p>
                    </div>
                </td>

                <td class="px-2 py-3">
                    <div class="min-w-[80px] text-xs text-content-secondary">
                        @if($lead->customer?->picWhatsapp())
                            <p class="font-medium">{{ $lead->customer->picWhatsapp() }}</p>
                        @endif
                        @if($lead->customer?->picPhone() && $lead->customer->picPhone() !== $lead->customer->picWhatsapp())
                            <p class="text-content-tertiary">{{ $lead->customer->picPhone() }}</p>
                        @endif
                        @if(!$lead->customer?->picWhatsapp() && !$lead->customer?->picPhone())
                            <span class="text-content-tertiary">-</span>
                        @endif
                    </div>
                </td>

                <td class="px-2 py-3">
                    <div class="min-w-[112px] max-w-[124px]">
                        <p class="truncate text-xs text-content-secondary" title="{{ $lead->project_name }}">
                            {{ $lead->project_name ?: ($lead->kebutuhan ?: '-') }}
                        </p>
                        @if($lead->need_type)
                            <p class="text-xs text-content-tertiary">{{ \App\Models\Lead::needTypeLabel($lead->need_type) }}</p>
                        @endif
                    </div>
                </td>

                <td class="px-2 py-3">
                    <div class="relative min-w-[112px]" :class="stageSaving ? 'pointer-events-none opacity-60' : ''">
                        @can('manage-sales')
                            <x-glide-select name="pipeline_stage" size="md" :empty="false"
                                            :options="collect($statuses)->map(fn ($status) => ['value' => $status, 'label' => ucfirst($status)])->all()"
                                            :value="$lead->status"
                                            x-on:change="stageChanged($event.target.value)" />
                            <p x-show="stageSaving" x-cloak class="mt-1 text-xs text-content-tertiary">{{ __('Saving...') }}</p>

                            <div x-show="lostOpen" x-cloak class="absolute left-0 top-11 z-30 w-64 rounded-xl border border-slate-200 bg-[var(--card-bg)] p-3 shadow-xl dark:border-slate-600 dark:bg-slate-800">
                                <p class="text-xs font-semibold text-content-secondary">{{ __('Loss reason') }}</p>
                                <select x-model="lostReason" class="mt-1 w-full rounded-xl border-slate-300 text-xs dark:border-slate-600 dark:bg-slate-900 text-content-primary">
                                    <option value="">{{ __('Choose reason') }}</option>
                                    @foreach($reasonOptions as $reason)
                                        <option value="{{ $reason['value'] }}">{{ $reason['label'] }}</option>
                                    @endforeach
                                </select>
                                <input type="text" x-model="lostNote" maxlength="2000" placeholder="{{ __('Short note') }}"
                                       class="mt-2 w-full rounded-xl border-slate-300 text-xs dark:border-slate-600 dark:bg-slate-900 text-content-primary">
                                <div class="mt-2 flex gap-2">
                                    <button type="button" @click="confirmLost()" class="h-8 flex-1 rounded-xl bg-red-600 text-xs font-semibold text-white transition hover:bg-red-700 active:scale-95">{{ __('Confirm') }}</button>
                                    <button type="button" @click="cancelLost()" class="h-8 flex-1 rounded-xl border border-slate-200 text-xs font-medium text-content-secondary transition hover:bg-slate-50 active:scale-95 dark:border-slate-600 dark:hover:bg-slate-700">{{ __('Cancel') }}</button>
                                </div>
                            </div>
                        @else
                            <span class="inline-flex h-9 items-center rounded-xl border border-slate-200 bg-[var(--card-bg)] px-2 text-xs font-semibold text-content-secondary dark:border-slate-600">
                                {{ ucfirst($lead->status) }}
                            </span>
                        @endcan
                    </div>
                </td>

                <td class="px-2 py-3">
                    @include('sales.my-leads._note-cell')
                </td>

                <td class="px-2 py-3">
                    @if($last)
                        <p class="text-xs font-medium text-content-secondary">{{ $last->follow_up_date?->format('d M Y') ?? '-' }}</p>
                        <p class="text-xs text-content-tertiary">{{ \App\Models\FollowUp::typeLabel($last->type) }}</p>
                    @else
                        <span class="text-xs text-content-tertiary">{{ __('No activity') }}</span>
                    @endif
                </td>

                <td class="px-2 py-3">
                    @include('sales.my-leads._next-cell')
                </td>

                <td class="sticky right-0 z-10 border-l border-slate-200 bg-[var(--card-bg)] px-2 py-3 transition-colors group-hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:group-hover:bg-slate-700/40">
                    <div class="flex items-center justify-center">
                        @include('sales.my-leads._contact-actions', ['lead' => $lead, 'first' => $loop->first])
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="10" class="px-6 py-14 text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-content-tertiary dark:bg-slate-700">
                        <x-icon name="briefcase" class="h-6 w-6" />
                    </span>
                    <p class="mt-4 text-sm font-medium text-content-secondary">{{ __('No leads match this filter.') }}</p>
                    <p class="mt-1 text-xs text-content-tertiary">{{ __('New leads from Management appear here. Clear the filter to see all your leads.') }}</p>
                    @if(request()->anyFilled(['search', 'company', 'pic', 'project', 'status', 'last_activity', 'next_follow_up', 'overdue', 'touched', 'active', 'won_month', 'sort']))
                        <a href="{{ route('sales.my-leads') }}" class="mt-3 inline-flex h-9 items-center rounded-xl border border-slate-200 px-4 text-xs font-semibold text-accent-600 transition hover:bg-slate-50 dark:border-slate-600 dark:text-accent-400 dark:hover:bg-slate-700">{{ __('Reset filter') }}</a>
                    @endif
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
