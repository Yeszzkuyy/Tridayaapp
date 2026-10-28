<x-app-layout>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Lead Detail') }}:
                @canany(['view-customer', 'view-sales', 'manage-sales'])
                    @if($lead->customer)
                        <a href="{{ route('customers.show', $lead->customer) }}" class="text-accent-600 hover:text-accent-500 hover:underline">{{ $lead->customer->name }}</a>
                    @else
                        -
                    @endif
                @else
                    {{ $lead->customer?->name ?? '-' }}
                @endcanany
            </h1>
            <p class="text-slate-500 mt-1">{{ __('Complete lead / opportunity information') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @php
                // Satu pintu: Sales wajib convert dari quotation accepted.
                // Admin/teknisi tetap punya jalur legacy untuk project non-quotation.
                $canConvert = auth()->user()?->can('manage-technician')
                    || auth()->user()?->can('manage-admin');
            @endphp
            @if($canConvert && !in_array($lead->status, ['won', 'lost']))
                <x-icon-button type="button"
                        onclick="openConvertModal()"
                        title="{{ __('Convert to Project') }}">
                    <x-icon name="briefcase" class="h-5 w-5" />
                </x-icon-button>
            @endif
            @can('manage-marketing')
                <x-icon-button as="a" icon="edit" href="{{ route('leads.edit', $lead) }}"
                    title="{{ __('Edit lead') }}" />
                <form action="{{ route('leads.destroy', $lead) }}" method="POST" class="inline-flex">
                    @csrf @method('DELETE')
                    <x-icon-button icon="delete" type="submit"
                            onclick="return confirm('{{ __('Delete this lead?') }}')"
                            title="{{ __('Delete lead') }}" />
                </form>
            @endcan
            @if(auth()->user()->can('view-sales') || auth()->user()->can('manage-sales'))
                <x-icon-button as="a" href="{{ route('leads.pipeline') }}"
                   title="{{ __('Back to Pipeline') }}">
                    <x-icon name="view-columns" class="h-5 w-5" />
                </x-icon-button>
            @endif
            <x-icon-button as="a" icon="back"
                href="{{ (auth()->user()->can('view-marketing') || auth()->user()->can('manage-marketing')) ? route('leads.index') : route('sales.my-leads') }}"
                title="{{ __('Back') }}" />
        </div>
    </div>

    @php
        $canSalesWrite = auth()->user()?->can('manage-sales') || auth()->user()?->can('manage-inside-sales');
        $techRequests = $techRequests ?? collect();
        $activeTechRequest = $activeTechRequest ?? null;
        $proposalsList = $proposalsList ?? collect();
        $sentProposal = $proposalsList->first(fn ($p) => in_array($p->status, ['sent', 'viewed']));
        $upcomingSchedules = $upcomingSchedules ?? collect();
        $pastSchedules = $pastSchedules ?? collect();
    @endphp
    @if($canSalesWrite && ($activeTechRequest || $sentProposal))
        <div class="flex flex-wrap items-center gap-2 mb-4">
            @if($activeTechRequest)
                <x-icon-button as="a" href="{{ route('sales.technical-requests.show', $activeTechRequest) }}" title="{{ __('View technical request') }}"
                   label="{{ \App\Models\TechnicalRequest::statusLabel($activeTechRequest->status) }} • {{ $activeTechRequest->title }}">
                    <x-icon name="tools" class="h-5 w-5 shrink-0" />
                </x-icon-button>
            @endif
            @if($sentProposal)
                <x-icon-button as="a" href="{{ route('sales.proposals.show', $sentProposal) }}" title="{{ __('View quotation') }}"
                   label="{{ $sentProposal->proposal_number }} • {{ \App\Models\Proposal::statusLabel($sentProposal->status) }}">
                    <x-icon name="receipt" class="h-5 w-5 shrink-0" />
                </x-icon-button>
            @endif
        </div>
    @endif

    @php
        $nextSchedule = $upcomingSchedules->first();
        $lastTimeline = ($timeline ?? collect())->first();
    @endphp

    {{-- Hero ringkasan: status + info sekilas + langkah berikutnya --}}
    <section aria-label="{{ __('Lead Summary') }}" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold {{ \App\Models\Lead::statusBadgeClasses($lead->status) }}">
                {{ ucfirst($lead->status) }}
            </span>
            @if($lead->segment)
                <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ \App\Http\Controllers\LeadController::label($lead->segment) }}</span>
            @endif
            @if($lead->partner)
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                    <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ \App\Models\Partner::TYPE_DOTS[$lead->partner->type] ?? '#94a3b8' }}"></span>
                    {{ $lead->partner->name }}
                </span>
            @endif
            @php
                $doneFollowUps = $lead->followUps->whereNotNull('completed_at');
                $nextFollowUp = $lead->followUps->whereNull('completed_at')->whereNotNull('next_follow_up_date')->sortBy('next_follow_up_date')->first();
                $isFuOverdue = $nextFollowUp && $nextFollowUp->next_follow_up_date->lt(today());
            @endphp
            @if($doneFollowUps->isNotEmpty())
                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">{{ __('Done') }}</span>
            @endif
            @if($nextFollowUp)
                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold {{ $isFuOverdue ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' }}">{{ __('FU') }}: {{ $nextFollowUp->next_follow_up_date->format('d M Y') }}{{ $isFuOverdue ? ' • ' . __('Overdue') : '' }}</span>
            @endif
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
            <span>{{ $lead->source ? \App\Http\Controllers\LeadController::label($lead->source) : '-' }} • {{ $lead->incoming_date ? $lead->incoming_date->format('d M Y') : '-' }}</span>
            <span>{{ __('Sales') }}: {{ $lead->assignee?->name ?? '-' }}</span>
        </div>
        <div class="mt-4 flex items-center gap-3 rounded-xl bg-accent-500/10 dark:bg-accent-400/10 p-3">
            <x-icon name="calendar" class="h-5 w-5 shrink-0 text-accent-600 dark:text-accent-400" />
            <div class="min-w-0 flex-1">
                @if($nextSchedule)
                    <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ \App\Models\SalesSchedule::typeLabel($nextSchedule->type) }} — {{ $nextSchedule->title }}</p>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $nextSchedule->start_at?->format('d M Y H:i') ?? '-' }} • {{ __('PIC') }}: {{ $nextSchedule->assignee?->name ?? '-' }} • {{ \App\Models\SalesSchedule::statusLabel($nextSchedule->status) }}</p>
                @elseif($lastTimeline)
                    <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $lastTimeline['title'] }} — {{ $lastTimeline['summary'] ?: '-' }}</p>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $lastTimeline['date'] ? \Carbon\Carbon::parse($lastTimeline['date'])->format('d M Y') : '-' }}@if($lastTimeline['by']) • {{ $lastTimeline['by'] }} @endif</p>
                @else
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('No schedules yet. Create the next step.') }}</p>
                @endif
            </div>
            @if($nextSchedule)
                <x-icon-button as="a" icon="view" href="{{ route('sales.schedules.show', $nextSchedule) }}" title="{{ __('View details') }}" class="shrink-0" />
            @elseif($lastTimeline && $lastTimeline['url'])
                <x-icon-button as="a" icon="view" href="{{ $lastTimeline['url'] }}" title="{{ __('View details') }}" class="shrink-0" />
            @elseif($canSalesWrite)
                <x-icon-button as="a" href="{{ route('sales.schedules.create', ['lead_id' => $lead->id]) }}" title="{{ __('Add schedule') }}" class="shrink-0">
                    <x-icon name="calendar" class="h-5 w-5" />
                </x-icon-button>
            @endif
        </div>
    </section>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @if(in_array($lead->status, ['won', 'lost']))
                <section aria-labelledby="card-hasil" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
                    <h2 id="card-hasil" class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                        <x-icon name="check-circle" class="h-5 w-5 text-slate-400" />
                        {{ __('Final Result') }}
                    </h2>
                    <div class="mt-3">
                        @if($lead->status === 'won')
                            <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-xl text-sm text-slate-700 dark:text-slate-200">
                                <p class="font-semibold text-green-700 dark:text-green-300">{{ __('Won') }}
                                    @if($lead->closed_at)
                                        <span class="font-normal text-slate-500">• {{ $lead->closed_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}</span>
                                    @endif
                                </p>
                                @if($lead->closing_note)
                                    <p class="mt-1 whitespace-pre-wrap">{{ $lead->closing_note }}</p>
                                @endif
                            </div>
                        @else
                            <div class="p-3 bg-red-50 dark:bg-red-900/20 rounded-xl text-sm text-slate-700 dark:text-slate-200">
                                <p class="font-semibold text-red-700 dark:text-red-300">{{ __('Lost') }}
                                    @if($lead->lost_reason)
                                        <span class="font-normal">• {{ \App\Models\Lead::lostReasonLabel($lead->lost_reason) }}</span>
                                    @endif
                                </p>
                                @if($lead->lost_note)
                                    <p class="mt-1 whitespace-pre-wrap">{{ $lead->lost_note }}</p>
                                @endif
                            </div>
                            @php
                                $canOutcome = auth()->user()?->can('manage-marketing')
                                    || ((int) $lead->assigned_to === (int) auth()->id() && auth()->user()?->can('manage-sales'))
                                    || auth()->user()?->can('manage-technician')
                                    || auth()->user()?->can('manage-admin');
                            @endphp
                            @if($canOutcome)
                                <form action="{{ route('leads.outcome', $lead) }}" method="POST" class="mt-3 flex flex-wrap items-end gap-3">
                                    @csrf @method('PATCH')
                                    <div>
                                        <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Lost Reason') }}</label>
                                        <select name="lost_reason"
                                                class="rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 text-sm">
                                            <option value="">{{ __('-- Select a reason --') }}</option>
                                            @foreach(\App\Models\Lead::LOST_REASONS as $reason)
                                                <option value="{{ $reason }}" @selected(old('lost_reason', $lead->lost_reason) === $reason)>
                                                    {{ \App\Models\Lead::lostReasonLabel($reason) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="flex-1 min-w-52">
                                        <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Notes') }}</label>
                                        <input type="text" name="lost_note" value="{{ old('lost_note', $lead->lost_note) }}"
                                               placeholder="{{ __('Additional notes (optional)') }}"
                                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 text-sm">
                                    </div>
                                    <button type="submit"
                                            class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                                        {{ __('Save') }}
                                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                                    </button>
                                </form>
                            @endif
                        @endif
                    </div>
                </section>
            @endif

            @if($lead->notes || $lead->kebutuhan || $lead->solusi || $lead->progress_notes)
                <section aria-labelledby="card-kebutuhan" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
                    <h2 id="card-kebutuhan" class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                        <x-icon name="file-text" class="h-5 w-5 text-slate-400" />
                        {{ __('Requirements & Notes') }}
                    </h2>
                    <div class="mt-3 space-y-3">
                        @if($lead->kebutuhan)
                            <div>
                                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('Requirement') }}</p>
                                <div class="mt-1 p-3 bg-slate-50 dark:bg-slate-700 rounded-xl text-slate-900 dark:text-slate-100 whitespace-pre-wrap">{{ $lead->kebutuhan }}</div>
                            </div>
                        @endif
                        @if($lead->solusi)
                            <div>
                                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('Solution') }}</p>
                                <div class="mt-1 p-3 bg-slate-50 dark:bg-slate-700 rounded-xl text-slate-900 dark:text-slate-100 whitespace-pre-wrap">{{ $lead->solusi }}</div>
                            </div>
                        @endif
                        @if($lead->progress_notes)
                            <div>
                                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('Follow-Up Progress / Notes') }}</p>
                                <div class="mt-1 p-3 bg-slate-50 dark:bg-slate-700 rounded-xl text-slate-900 dark:text-slate-100 whitespace-pre-wrap">{{ $lead->progress_notes }}</div>
                            </div>
                        @endif
                        @if($lead->notes)
                            <div>
                                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('Notes') }}</p>
                                <div class="mt-1 p-3 bg-slate-50 dark:bg-slate-700 rounded-xl text-slate-900 dark:text-slate-100 whitespace-pre-wrap">{{ $lead->notes }}</div>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            <section aria-labelledby="card-jadwal" id="section-schedule" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="card-jadwal" class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                        <x-icon name="calendar" class="h-5 w-5 text-slate-400" />
                        {{ __('Sales Schedule') }}
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ ($upcomingSchedules->concat($pastSchedules))->count() }}</span>
                    </h2>
                    @if($canSalesWrite)
                        <x-icon-button as="a" href="{{ route('sales.schedules.create', ['lead_id' => $lead->id]) }}" title="{{ __('Add schedule') }}" class="shrink-0">
                            <x-icon name="calendar" class="h-5 w-5" />
                        </x-icon-button>
                    @endif
                </div>
                <div class="mt-3 space-y-2">
                    @php $allSchedules = $upcomingSchedules->concat($pastSchedules); @endphp
                    @forelse($allSchedules as $schedule)
                        <div class="flex items-start gap-3 p-3 bg-slate-50 dark:bg-slate-700 rounded-xl">
                            <span class="mt-0.5 inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold shrink-0 bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                {{ \App\Models\SalesSchedule::typeLabel($schedule->type) }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-slate-800 dark:text-slate-100 line-clamp-2">{{ $schedule->title }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $schedule->start_at?->format('d M Y H:i') ?? '-' }}
                                    • {{ __('PIC') }}: {{ $schedule->assignee?->name ?? '-' }}
                                    • {{ \App\Models\SalesSchedule::statusLabel($schedule->status) }}
                                </p>
                            </div>
                            <x-icon-button as="a" icon="view" href="{{ route('sales.schedules.show', $schedule) }}" title="{{ __('View details') }}" class="shrink-0" />
                        </div>
                    @empty
                        <p class="text-slate-500">{{ __('No schedules for this lead yet.') }}</p>
                    @endforelse
                </div>
            </section>

            <section aria-labelledby="card-penawaran" id="section-proposal" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="card-penawaran" class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                        <x-icon name="receipt" class="h-5 w-5 text-slate-400" />
                        {{ __('Offer') }}
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $proposalsList->count() }}</span>
                        @if($proposalsList->isNotEmpty())
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Rp {{ number_format($proposalsList->sum('grand_total'), 0, ',', '.') }}</span>
                        @endif
                    </h2>
                    @if($canSalesWrite)
                        <x-icon-button as="a" href="{{ route('sales.proposals.create', ['lead_id' => $lead->id]) }}" title="{{ __('Add quotation') }}" class="shrink-0">
                            <x-icon name="receipt" class="h-5 w-5" />
                        </x-icon-button>
                    @endif
                </div>
                <div class="mt-3 space-y-2">
                    @forelse($proposalsList as $proposal)
                        <div class="flex items-start gap-3 p-3 bg-slate-50 dark:bg-slate-700 rounded-xl">
                            <span class="mt-0.5 inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold shrink-0 bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200">
                                {{ \App\Models\Proposal::statusLabel($proposal->status) }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-slate-800 dark:text-slate-100 line-clamp-2">{{ $proposal->proposal_number }}</p>
                                <p class="mt-1 text-xs text-slate-500">Rp {{ number_format($proposal->grand_total, 0, ',', '.') }}</p>
                            </div>
                            <x-icon-button as="a" icon="view" href="{{ route('sales.proposals.show', $proposal) }}" title="{{ __('View details') }}" class="shrink-0" />
                        </div>
                    @empty
                        <p class="text-slate-500">{{ __('No quotations for this lead yet.') }}</p>
                    @endforelse
                </div>
            </section>

            <section x-data="{ open: {{ $errors->any() ? 'true' : 'false' }}, activity: @js(old('type', 'whatsapp')) }" aria-labelledby="card-riwayat" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="card-riwayat" class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                        <x-icon name="chat" class="h-5 w-5 text-slate-400" />
                        {{ __('Log Follow Up') }}
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ ($timeline ?? collect())->count() }}</span>
                    </h2>
                    @can('manage-sales')
                        <x-icon-button icon="add" :title="__('Log follow up')" x-on:click="open = !open" />
                    @endcan
                </div>
                @can('manage-sales')
                    <form x-show="open" x-cloak method="POST" action="{{ route('sales.follow-ups.store') }}"
                          class="mt-4 space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-700/40">
                        @csrf
                        <input type="hidden" name="return" value="lead">
                        <input type="hidden" name="customer_id" value="{{ $lead->customer_id }}">
                        <input type="hidden" name="lead_id" value="{{ $lead->id }}">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="log-activity-type" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('Activity Type') }}</label>
                                <select id="log-activity-type" name="type" x-model="activity" required
                                        class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 focus:border-accent-500 focus:ring-accent-500">
                                    @foreach(\App\Models\FollowUp::LOG_TYPES as $logType)
                                        <option value="{{ $logType }}" @selected(old('type') === $logType)>{{ \App\Models\FollowUp::typeLabel($logType) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="log-activity-date" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('Date') }}</label>
                                <input type="date" id="log-activity-date" name="follow_up_date" required
                                       value="{{ old('follow_up_date', date('Y-m-d')) }}"
                                       class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 focus:border-accent-500 focus:ring-accent-500">
                            </div>
                            <div x-show="activity === 'meeting'" x-cloak>
                                <label for="log-activity-time" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('Time (optional)') }}</label>
                                <input type="time" id="log-activity-time" name="activity_time" value="{{ old('activity_time') }}"
                                       class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 focus:border-accent-500 focus:ring-accent-500">
                            </div>
                            <div x-show="activity === 'meeting'" x-cloak>
                                <label for="log-activity-location" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('Location (optional)') }}</label>
                                <input type="text" id="log-activity-location" name="location" maxlength="150" value="{{ old('location') }}"
                                       class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 focus:border-accent-500 focus:ring-accent-500">
                            </div>
                        </div>
                        <div>
                            <label for="log-activity-detail" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('Detail') }} <span class="text-red-500">*</span></label>
                            <textarea id="log-activity-detail" name="description" rows="3" required
                                      placeholder="{{ __('What did you do or learn? Keep it short...') }}"
                                      class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 focus:border-accent-500 focus:ring-accent-500">{{ old('description') }}</textarea>
                            @error('description') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex gap-3">
                            <button type="submit"
                                    class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                                {{ __('Save') }}
                                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                            </button>
                            <button type="button" x-on:click="open = false"
                                    class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                                {{ __('Cancel') }}
                                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                            </button>
                        </div>
                    </form>
                @endcan
                <div class="mt-3 space-y-2">
                    @forelse($timeline ?? [] as $item)
                        <div class="flex items-start gap-3 p-3 bg-slate-50 dark:bg-slate-700 rounded-xl">
                            <span class="mt-0.5 inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold shrink-0 {{ $item['type'] === 'meeting' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' }}">
                                {{ $item['title'] }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-slate-800 dark:text-slate-100 line-clamp-2">{{ $item['summary'] ?: '-' }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $item['date'] ? \Carbon\Carbon::parse($item['date'])->format('d M Y') : '-' }}
                                    @if(!empty($item['time'])) • {{ substr((string) $item['time'], 0, 5) }} @endif
                                    @if(!empty($item['location'])) • {{ $item['location'] }} @endif
                                    @if($item['by']) • {{ $item['by'] }} @endif
                                </p>
                            </div>
                            @if($item['url'])
                                <x-icon-button as="a" icon="view" href="{{ $item['url'] }}" title="{{ __('View details') }}" class="shrink-0" />
                            @endif
                        </div>
                    @empty
                        <p class="text-slate-500">{{ __('No follow up logs for this lead yet.') }}</p>
                    @endforelse
                </div>
            </section>

            <section aria-labelledby="card-aktivitas" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
                <h2 id="card-aktivitas" class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    <x-icon name="activity" class="h-5 w-5 text-slate-400" />
                    {{ __('Lead History') }}
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $lead->activities->count() }}</span>
                </h2>
                <div class="mt-3 space-y-2">
                    @forelse($lead->activities->sortByDesc('created_at') as $activity)
                        <div class="flex items-start gap-3 p-3 bg-slate-50 dark:bg-slate-700 rounded-xl">
                            <span class="mt-0.5 inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold shrink-0 bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200">
                                {{ $activity->actionLabel() }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-slate-800 dark:text-slate-100">
                                    {{ $activity->user?->name ?? __('System') }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $activity->created_at?->setTimezone('Asia/Jakarta')->format('d M Y H:i') ?? '-' }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <p class="text-slate-500">{{ __('No history for this lead yet.') }}</p>
                    @endforelse
                </div>
            </section>

            <section aria-labelledby="card-task" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="card-task" class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                        <x-icon name="user" class="h-5 w-5 text-slate-400" />
                        {{ __('Inside Sales Task') }}
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $lead->tasks->count() }}</span>
                    </h2>
                    @php
                        $canRequestTask = auth()->user()?->can('manage-sales-leads')
                            || auth()->user()?->can('manage-marketing')
                            || ((int) $lead->assigned_to === (int) auth()->id() && auth()->user()?->can('manage-sales'));
                    @endphp
                    @if($canRequestTask)
                        <x-icon-button as="a" href="{{ route('lead-tasks.create', ['lead_id' => $lead->id]) }}" title="{{ __('Request inside sales') }}" class="shrink-0">
                            <x-icon name="user" class="h-5 w-5" />
                        </x-icon-button>
                    @endif
                </div>
                <div class="mt-3 space-y-2">
                    @forelse($lead->tasks->sortByDesc('created_at') as $task)
                        <div class="flex items-start gap-3 p-3 bg-slate-50 dark:bg-slate-700 rounded-xl">
                            <span class="mt-0.5 inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold shrink-0 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                {{ \App\Models\LeadTask::statusLabel($task->status) }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('lead-tasks.show', $task) }}"
                                   class="text-sm font-medium text-slate-800 dark:text-slate-100 hover:underline line-clamp-2">{{ $task->title }}</a>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $task->assignee?->name ?? __('Not assigned yet') }}
                                    @if($task->due_date) • {{ $task->due_date->format('d M Y') }} @endif
                                </p>
                            </div>
                        </div>
                    @empty
                        <p class="text-slate-500">{{ __('No inside sales tasks for this lead yet.') }}</p>
                    @endforelse
                </div>
            </section>

            <section aria-labelledby="card-teknis" id="section-technical" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="card-teknis" class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                        <x-icon name="tools" class="h-5 w-5 text-slate-400" />
                        {{ __('Technical Request') }}
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $techRequests->count() }}</span>
                    </h2>
                    @if($canSalesWrite && !$activeTechRequest)
                        <x-icon-button as="a" href="{{ route('sales.technical-requests.create', ['lead_id' => $lead->id]) }}" title="{{ __('Request technical team') }}" class="shrink-0">
                            <x-icon name="tools" class="h-5 w-5" />
                        </x-icon-button>
                    @endif
                </div>
                <div class="mt-3 space-y-2">
                    @forelse($techRequests as $tech)
                        <div class="flex items-start gap-3 p-3 bg-slate-50 dark:bg-slate-700 rounded-xl">
                            <span class="mt-0.5 inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold shrink-0 bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                {{ \App\Models\TechnicalRequest::statusLabel($tech->status) }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-slate-800 dark:text-slate-100 line-clamp-2">{{ $tech->title }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ \App\Models\TechnicalRequest::typeLabel($tech->request_type) }}
                                    @if($tech->technician) • {{ $tech->technician->name }} @endif
                                </p>
                                @if($tech->status === 'completed' && $tech->technical_result)
                                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-300 line-clamp-2">{{ $tech->technical_result }}</p>
                                @endif
                            </div>
                            <x-icon-button as="a" icon="view" href="{{ route('sales.technical-requests.show', $tech) }}" title="{{ __('View details') }}" class="shrink-0" />
                        </div>
                    @empty
                        <p class="text-slate-500">{{ __('No technical requests for this lead yet.') }}</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-4 lg:sticky lg:top-24 self-start">
            <section aria-labelledby="card-info" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
                <h2 id="card-info" class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    <x-icon name="bolt" class="h-5 w-5 text-slate-400" />
                    {{ __('Lead Information') }}
                </h2>
                <dl class="mt-3 space-y-3">
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('PT') }}</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $lead->pt_group ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('Company') }}</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $lead->customer?->company ?? $lead->customer?->name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('Created on') }}</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ $lead->created_at?->format('d M Y H:i') ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('Last Updated') }}</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ $lead->updated_at?->format('d M Y H:i') ?? '-' }}</dd>
                    </div>
                </dl>
            </section>

            <section aria-labelledby="card-kontak" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
                <h2 id="card-kontak" class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    <x-icon name="phone" class="h-5 w-5 text-slate-400" />
                    {{ __('Customer Contact') }}
                </h2>
                <dl class="mt-3 space-y-3">
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('PIC') }}</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ $lead->customer?->contact_person ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('Office Phone') }}</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ $lead->customer?->phone ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('WhatsApp No.') }}</dt>
                        @php($waLink = $lead->customer?->waLink() ?? null)
                        <dd class="mt-1 flex items-center gap-2 text-sm text-slate-900 dark:text-slate-100">
                            @if($waLink)
                                <a href="{{ $waLink }}" target="_blank" rel="noopener" class="hover:underline">{{ $lead->customer->whatsapp }}</a>
                                <x-icon-button as="a" href="{{ $waLink }}" target="_blank" rel="noopener" title="WhatsApp" class="shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="h-5 w-5" aria-hidden="true">
                                        <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.87 9.87 0 0 0 4.74 1.21c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2m5.83 14.12c-.25.7-1.45 1.33-2.02 1.42-.52.08-1.17.12-1.89-.12-.44-.15-1-.39-1.71-.75-3.03-1.39-5-4.63-5.15-4.84-.15-.21-1.23-1.64-1.23-3.13 0-1.49.78-2.22 1.06-2.52.28-.3.61-.38.81-.38l.58.01c.19.01.44-.07.69.53.25.61.86 2.11.94 2.26.08.15.13.33.03.53-.1.2-.15.33-.3.51l-.45.53c-.15.15-.31.31-.13.61.18.3.8 1.32 1.71 2.14 1.18 1.06 2.17 1.39 2.48 1.55.3.15.48.13.66-.08l1.1-1.28c.2-.26.42-.22.71-.13.3.09 1.9.9 2.23 1.06.38.2.53.44.5.65-.27.69-.52.98-.73 1.23"/>
                                    </svg>
                                </x-icon-button>
                            @else
                                {{ $lead->customer?->whatsapp ?? '-' }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('Email') }}</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100 break-all">{{ $lead->customer?->email ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('Address') }}</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ $lead->customer?->address ?? '-' }}</dd>
                    </div>
                </dl>
            </section>

            <section aria-labelledby="card-lampiran" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
                <h2 id="card-lampiran" class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    <x-icon name="folder" class="h-5 w-5 text-slate-400" />
                    {{ __('Attachments (BOQ / User Requirements)') }}
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $lead->documents->count() }}</span>
                </h2>
                <div class="mt-3 space-y-2">
                    @if($lead->documents->isEmpty())
                        <p class="text-slate-500">{{ __('No attachments yet.') }}</p>
                    @else
                        @foreach($lead->documents as $doc)
                            <div class="flex items-center justify-between gap-3 p-3 bg-slate-50 dark:bg-slate-800 rounded-xl">
                                <span class="text-sm text-slate-800 dark:text-slate-100 truncate">{{ $doc->file_name }}</span>
                                <span class="shrink-0 inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200">
                                    {{ \App\Models\LeadDocument::categoryLabel($doc->category) }}
                                </span>
                                <div class="flex items-center gap-2 shrink-0">
                                    <x-icon-button icon="view" type="button" title="{{ __('View') }}"
                                            data-url="{{ route('leads.attachments.show', [$lead, $doc]) }}"
                                            data-filename="{{ $doc->file_name }}"
                                            data-mime="{{ $doc->mime_type }}"
                                            onclick="openPreviewModal(this.dataset.url, this.dataset.filename, this.dataset.mime)" />
                                    <x-icon-button as="a" icon="download" href="{{ route('leads.attachments.download', [$lead, $doc]) }}" title="{{ __('Download') }}" />
                                    @can('manage-marketing')
                                        <form action="{{ route('leads.attachments.destroy', [$lead, $doc]) }}" method="POST"
                                              onsubmit="return confirm('{{ __('Delete this attachment?') }}')" class="inline-flex">
                                            @csrf
                                            @method('DELETE')
                                            <x-icon-button icon="delete" type="submit" title="{{ __('Delete') }}" />
                                        </form>
                                    @endcan
                                    @can('manage-marketing')
                                        <form action="{{ route('leads.attachments.category', [$lead, $doc]) }}" method="POST" class="inline-flex">
                                            @csrf @method('PATCH')
                                            <select name="category" onchange="this.form.submit()" title="{{ __('Document category') }}"
                                                    class="rounded-lg border-slate-300 text-xs text-slate-600 focus:border-accent-500 focus:ring-accent-500">
                                                <option value="">{{ __('Tanpa kategori') }}</option>
                                                @foreach(\App\Models\LeadDocument::CATEGORIES as $category)
                                                    <option value="{{ $category }}" @selected($doc->category === $category)>
                                                        {{ \App\Models\LeadDocument::categoryLabel($category) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </section>

            @if($documents->isNotEmpty())
            <section aria-labelledby="card-instalasi" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
                <h2 id="card-instalasi" class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    <x-icon name="building" class="h-5 w-5 text-slate-400" />
                    {{ __('Installation Documentation (Read-Only)') }}
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $documents->count() }}</span>
                </h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Dokumen dari project terkait customer ini (diunggah Tim Teknisi)') }}</p>
                <div class="mt-3">
                        <div class="grid grid-cols-1 gap-3">
                            @foreach($documents as $doc)
                                <div class="border border-slate-200 dark:border-slate-600 rounded-xl p-4 hover:shadow-md transition">
                                    <div class="flex items-center gap-3">
                                        <div class="p-2 bg-slate-100 dark:bg-slate-700 rounded-lg">
                                            @if($doc->is_image)
                                                <svg class="w-6 h-6 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            @elseif($doc->is_pdf)
                                                <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                            @else
                                                <svg class="w-6 h-6 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                            @endif
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate">{{ $doc->file_name }}</p>
                                            <p class="text-xs text-slate-500">
                                                {{ $doc->category->name ?? __('Tanpa Kategori') }} •
                                                {{ $doc->uploader->name ?? __('Unknown') }} •
                                                {{ $doc->created_at->format('d M Y') }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="mt-3 flex gap-2">
                                        <x-icon-button as="a" icon="download" href="{{ route('leads.documents.download', ['lead' => $lead->id, 'document' => $doc->id]) }}"
                                           label="{{ __('Download') }}" title="{{ __('Download') }}" />
                                        @if($doc->is_image || $doc->is_pdf)
                                            <x-icon-button as="a" icon="view"
                                               href="{{ route('leads.documents.preview', ['lead' => $lead->id, 'document' => $doc->id]) }}"
                                               target="_blank"
                                               label="{{ __('Preview') }}" title="{{ __('Preview') }}" />
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                </div>
            </section>
            @endif
        </aside>
    </div>
</x-app-layout>

@if($canConvert ?? false)
@if(!in_array($lead->status, ['won', 'lost']))
<div id="convertModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/50" onclick="closeConvertModal()"></div>
        <div class="relative w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-600">
            <div class="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-600">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ __('Convert Lead to Project') }}</h3>
                <button type="button" onclick="closeConvertModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <form action="{{ route('leads.convert', $lead) }}" method="POST">
                @csrf @method('PATCH')
                <div class="p-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Project Name') }}</label>
                        <input type="text" name="project_name" required
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent"
                               placeholder="{{ __('New project name') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Status Project') }}</label>
                        <select name="project_status_id" required
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent">
                            @foreach($projectStatuses ?? [] as $status)
                                <option value="{{ $status->id }}">{{ __($status->name) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Job Type') }} <span class="text-red-500">*</span></label>
                        <select name="work_type_id" required
                                 class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent">
                            <option value="">{{ __('-- Select Work Type --') }}</option>
                            @foreach($workTypes ?? [] as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Closing Note (Optional)') }}</label>
                        <textarea name="closing_note" rows="3"
                                  placeholder="{{ __('Closing notes: agreement, deal value, follow up...') }}"
                                  class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-3 p-4 border-t border-slate-200 dark:border-slate-600">
                    <button type="button" onclick="closeConvertModal()"
                            class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                        {{ __('Cancel') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                    <button type="submit"
                            class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Convert') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endif

<script>
    function openConvertModal() {
        document.getElementById('convertModal').classList.remove('hidden');
    }
    function closeConvertModal() {
        document.getElementById('convertModal').classList.add('hidden');
    }

    function openPreviewModal(url, filename, mimeType) {
        var modal = document.getElementById('previewModal');
        var content = document.getElementById('previewContent');
        var title = document.getElementById('previewTitle');

        title.textContent = filename;
        content.innerHTML = '';

        var imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        var pdfTypes = ['application/pdf'];

        if (imageTypes.includes(mimeType)) {
            var img = document.createElement('img');
            img.src = url;
            img.className = 'max-h-[80vh] max-w-full mx-auto rounded-lg object-contain';
            img.alt = filename;
            content.appendChild(img);
        } else if (pdfTypes.includes(mimeType)) {
            var iframe = document.createElement('iframe');
            iframe.src = url;
            iframe.className = 'w-full h-[80vh] rounded-lg border-0';
            content.appendChild(iframe);
        } else {
            content.innerHTML = '<div class="text-center py-10">' +
                '<svg class="w-16 h-16 mx-auto text-slate-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"></path></svg>' +
                '<p class="text-slate-600 font-medium mb-2">{{ __('This file needs to be downloaded') }}</p>' +
                '<a href="' + url.replace('/show', '/download') + '" ' +
                'class="inline-flex items-center gap-2 px-4 py-2 bg-accent-600 hover:bg-accent-700 text-white rounded-lg text-sm font-medium transition">' +
                '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"></path></svg>' +
                '{{ __('Download') }}</a>' +
                '</div>';
        }

        modal.classList.remove('hidden');
    }

    function closePreviewModal() {
        var modal = document.getElementById('previewModal');
        modal.classList.add('hidden');
        document.getElementById('previewContent').innerHTML = '';
    }
</script>

<!-- Preview Modal -->
<div id="previewModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/70" onclick="closePreviewModal()"></div>
        <div class="relative w-full max-w-4xl bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-600">
            <div class="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-600">
                <h3 id="previewTitle" class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate max-w-[80%]"></h3>
                <button type="button" onclick="closePreviewModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div id="previewContent" class="p-4"></div>
        </div>
    </div>
</div>
