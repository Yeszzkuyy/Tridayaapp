{{-- Antrean Follow Up: satu baris = satu pekerjaan yang butuh tindakan. --}}
<div class="overflow-x-auto">
    <table class="min-w-[1280px] w-full divide-y divide-slate-200 dark:divide-slate-600">
        <thead class="bg-slate-50 dark:bg-slate-700">
            <tr>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Customer') }}</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('PIC') }}</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('WhatsApp') }}</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Project') }}</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Pipeline Stage') }}</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Latest Note') }}</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Last Activity') }}</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Next Follow Up') }}</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Follow Up Status') }}</th>
                <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody class="bg-[var(--card-bg)] dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-600">
            @forelse($followUps as $fu)
                @php
                    $lead = $fu->lead;
                    $customer = $fu->customer;
                    $status = $fu->status ?? ($fu->isCompleted() ? 'completed' : 'pending');
                    $schedule = $fu->scheduleState();
                    $note = $fu->description ?: ($lead?->latestNoteFollowUp?->description ?? '');
                    $leadNote = $lead?->latestNoteFollowUp?->description;
                    $lastActivity = $lead?->latestActivityFollowUp?->follow_up_date ?? $fu->follow_up_date;
                    $waMessage = __('Follow up on :project', ['project' => $lead?->project_name ?: ($customer?->name ?? '')]);
                    $waLink = $customer?->waLink($waMessage);
                    $email = $customer?->picEmail();
                    $phone = $customer?->picPhone() ?: $customer?->phone;
                    $contactUrl = $lead ? route('sales.my-leads.contact', $lead) : null;
                @endphp
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition {{ $fu->isCompleted() ? 'bg-slate-50/40 dark:bg-slate-800/40' : '' }}">
                    <td class="px-4 py-3">
                        <div class="min-w-[170px]">
                            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $customer?->name ?? '-' }}</span>
                            <div class="mt-1 flex items-center gap-1">
                                @if($lead?->pt_group)
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold {{ \App\Models\Lead::PT_COLORS[$lead->pt_group] ?? 'bg-indigo-50 text-indigo-700' }}">{{ $lead->pt_group }}</span>
                                @endif
                                @if($fu->type)
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">{{ \App\Models\FollowUp::typeLabel($fu->type) }}</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                        <div class="min-w-[110px]">{{ $customer?->picName() ?? '-' }}</div>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300 whitespace-nowrap">
                        {{ $customer?->picWhatsapp() ?? $customer?->picPhone() ?? '-' }}
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                        <div class="min-w-[130px] max-w-[200px] truncate" title="{{ $lead?->project_name }}">{{ $lead?->project_name ?: '-' }}</div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        @if($lead)
                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ \App\Models\Lead::workspaceStageClasses($lead->status) }}">{{ ucfirst($lead->status) }}</span>
                        @else
                            <span class="text-xs text-slate-400">-</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="min-w-[190px] max-w-[280px]">
                            <p class="text-xs leading-relaxed text-slate-600 dark:text-slate-300 line-clamp-3" title="{{ $note }}">{{ $note ?: __('No note yet') }}</p>
                            @if($leadNote && $leadNote !== $note)
                                <p class="mt-1 text-[11px] text-slate-400 line-clamp-2" title="{{ $leadNote }}">{{ __('Latest note') }}: {{ $leadNote }}</p>
                            @endif
                            @if($fu->isCompleted() && $fu->outcome)
                                <p class="mt-1 text-[11px] font-medium text-green-700 dark:text-green-400">{{ __('Outcome') }}: {{ \Illuminate\Support\Str::limit($fu->outcome, 90) }}</p>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-xs text-slate-600 dark:text-slate-300">
                        {{ $lastActivity?->format('d M Y') ?? '-' }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <p class="text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $fu->follow_up_date?->format('d M Y') ?? __('Not scheduled') }}</p>
                        @if($fu->activity_time)
                            <p class="text-[11px] text-slate-400">{{ substr((string) $fu->activity_time, 0, 5) }}</p>
                        @endif
                        <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ \App\Models\FollowUp::scheduleStateClasses($schedule) }}">{{ \App\Models\FollowUp::scheduleStateLabel($schedule) }}</span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ \App\Models\FollowUp::statusBadgeClasses($status) }}">{{ \App\Models\FollowUp::statusLabel($status) }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap items-center justify-end gap-1.5">
                            @if($waLink)
                                <x-icon-button as="a" icon="whatsapp" href="{{ $waLink }}" target="_blank" rel="noopener" title="{{ __('WhatsApp') }}"
                                               data-contact-channel="whatsapp" data-contact-url="{{ $contactUrl }}" data-contact-follow-up="{{ $fu->id }}" />
                            @endif
                            @if($email)
                                <x-icon-button as="a" icon="mail" href="mailto:{{ $email }}?subject={{ rawurlencode(__('Follow up')) }}" title="{{ __('Email') }}"
                                               data-contact-channel="email" data-contact-url="{{ $contactUrl }}" data-contact-follow-up="{{ $fu->id }}" />
                            @endif
                            @if($phone)
                                <x-icon-button as="a" icon="phone" href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" title="{{ __('Call') }}"
                                               data-contact-channel="call" data-contact-url="{{ $contactUrl }}" data-contact-follow-up="{{ $fu->id }}" />
                            @endif
                            @if($lead)
                                <x-icon-button as="a" icon="view" href="{{ route('leads.show', $lead) }}" title="{{ __('View Lead') }}" />
                            @endif
                            @if(!$fu->isCompleted())
                                @can('manage-sales')
                                    @php($completeData = json_encode([
                                        'action' => route('sales.follow-ups.complete', $fu),
                                        'customer' => $customer?->name ?? '',
                                    ]))
                                    <x-icon-button
                                        icon="check-circle"
                                        title="{{ __('Complete') }}"
                                        x-on:click="openComplete({{ $completeData }})" />
                                @endcan
                            @else
                                <span class="text-[10px] font-semibold text-slate-400">{{ $fu->completed_at?->format('d M Y') }}</span>
                            @endif

                            <x-dropdown align="right" width="w-60">
                                <x-slot name="trigger">
                                    <x-icon-button icon="more" title="{{ __('More actions') }}" />
                                </x-slot>
                                <x-slot name="content">
                                    <a href="{{ route('sales.follow-ups.show', $fu) }}"
                                       class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm text-slate-700 transition hover:bg-[var(--menu-hover-bg)] dark:text-slate-200">
                                        <x-icon name="eye" class="h-4 w-4 shrink-0 text-indigo-600 dark:text-indigo-300" />
                                        {{ __('View follow up detail') }}
                                    </a>
                                    @can('manage-sales')
                                        <a href="{{ route('sales.follow-ups.edit', $fu) }}"
                                           class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm text-slate-700 transition hover:bg-[var(--menu-hover-bg)] dark:text-slate-200">
                                            <x-icon name="edit" class="h-4 w-4 shrink-0 text-blue-600 dark:text-blue-300" />
                                            {{ __('Edit follow up') }}
                                        </a>
                                        @if($fu->isCompleted())
                                            <form action="{{ route('sales.follow-ups.reopen', $fu) }}" method="POST" data-ajax>
                                                @csrf
                                                <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm text-slate-700 transition hover:bg-[var(--menu-hover-bg)] dark:text-slate-200">
                                                    <x-icon name="restore" class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-300" />
                                                    {{ __('Reopen follow up') }}
                                                </button>
                                            </form>
                                        @else
                                            @foreach([3, 7] as $days)
                                                <form action="{{ route('sales.follow-ups.snooze', $fu) }}" method="POST" data-ajax>
                                                    @csrf
                                                    <input type="hidden" name="days" value="{{ $days }}">
                                                    <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm text-slate-700 transition hover:bg-[var(--menu-hover-bg)] dark:text-slate-200">
                                                        <x-icon name="clock" class="h-4 w-4 shrink-0 text-yellow-600 dark:text-yellow-300" />
                                                        {{ __('Postpone :days days', ['days' => $days]) }}
                                                    </button>
                                                </form>
                                            @endforeach
                                        @endif
                                        <div class="my-1 border-t border-slate-200 dark:border-slate-600"></div>
                                        <form action="{{ route('sales.follow-ups.destroy', $fu) }}" method="POST" data-ajax data-ajax-remove="tr"
                                              data-confirm="{{ __('Delete this follow up?') }}"
                                              data-confirm-title="{{ __('Delete follow up') }}"
                                              data-confirm-tone="danger"
                                              data-confirm-label="{{ __('Delete') }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">
                                                <x-icon name="trash" class="h-4 w-4 shrink-0" />
                                                {{ __('Delete follow up') }}
                                            </button>
                                        </form>
                                    @endcan
                                </x-slot>
                            </x-dropdown>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="px-6 py-14 text-center">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-300">
                            <x-icon name="chat" class="h-6 w-6" />
                        </span>
                        <p class="mt-4 text-sm font-medium text-slate-500 dark:text-slate-300">{{ __('Nothing in this tab.') }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ __('New follow ups appear here when a lead is given a schedule.') }}</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@if($followUps->hasPages())
    <div class="p-4 border-t border-slate-200 dark:border-slate-600">
        {{ $followUps->links() }}
    </div>
@endif
