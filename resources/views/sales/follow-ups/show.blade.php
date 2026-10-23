<x-app-layout>
    <div >
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">{{ __('Follow Up Detail') }}</h1>
                <p class="text-slate-500 mt-1">{{ $followUp->customer?->name ?? '-' }}</p>
            </div>
            <div class="flex gap-2">
                @can('manage-sales')
                    <x-icon-button as="a" icon="edit" href="{{ route('sales.follow-ups.edit', $followUp) }}" title="{{ __('Edit') }}" />
                @endcan
                <x-icon-button as="a" icon="back" href="{{ route('sales.follow-ups.index') }}" title="{{ __('Back') }}" />
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Customer') }}</dt>
                    <dd class="font-medium text-slate-800">{{ $followUp->customer?->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Jenis') }}</dt>
                    <dd class="font-medium text-slate-800">{{ $followUp->type ? \App\Models\FollowUp::typeLabel($followUp->type) : '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Follow Up Date') }}</dt>
                    <dd class="font-medium text-slate-800">{{ $followUp->follow_up_date ? $followUp->follow_up_date->format('d M Y') : '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Jam') }}</dt>
                    <dd class="font-medium text-slate-800">{{ $followUp->activity_time ? substr((string) $followUp->activity_time, 0, 5) : '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Location') }}</dt>
                    <dd class="font-medium text-slate-800"><x-location-link :location="$followUp->location" preview /></dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Next Follow Up') }}</dt>
                    <dd class="font-medium text-slate-800">{{ $followUp->next_follow_up_date ? $followUp->next_follow_up_date->format('d M Y') : '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Related Lead') }}</dt>
                    <dd class="font-medium text-slate-800">{{ $followUp->lead ? '#'.$followUp->lead->id.' — '.\App\Models\Lead::salesStatusLabel($followUp->lead->status) : '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">{{ __('Recorded by') }}</dt>
                    <dd class="font-medium text-slate-800">{{ $followUp->creator?->name ?? '-' }}</dd>
                </div>
            </dl>

            <div>
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">{{ __('Description') }}</h3>
                <p class="text-slate-700 whitespace-pre-wrap">{{ $followUp->description }}</p>
            </div>

            @if($followUp->type === 'meeting')
                <div class="mt-8 pt-6 border-t border-slate-200 dark:border-slate-700">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <span class="p-2 rounded-xl bg-accent-100 dark:bg-accent-900/40 text-accent-600 dark:text-accent-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            </span>
                            <div>
                                <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Meeting Report & Minutes of Meeting (MOM)') }}</h3>
                                <p class="text-xs text-slate-500">{{ __('Official record of the client meeting.') }}</p>
                            </div>
                        </div>
                        @if($followUp->meeting_outcome)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ \App\Models\FollowUp::outcomeBadgeClass($followUp->meeting_outcome) }}">
                                {{ \App\Models\FollowUp::outcomeLabel($followUp->meeting_outcome) }}
                            </span>
                        @endif
                    </div>

                    @if($followUp->hasMeetingReport())
                        <div class="space-y-4">
                            <div class="bg-slate-50 dark:bg-slate-900/50 rounded-xl p-4 border border-slate-200/80 dark:border-slate-700">
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">{{ __('Minutes of Meeting') }}</h4>
                                <div class="text-sm text-slate-800 dark:text-slate-200 whitespace-pre-line leading-relaxed">{{ $followUp->meeting_notes }}</div>
                            </div>

                            @if($followUp->meeting_next_action_date)
                                <div class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300 bg-accent-50/50 dark:bg-accent-950/20 p-3 rounded-xl border border-accent-200/50 dark:border-slate-700">
                                    <span class="font-semibold text-slate-800 dark:text-slate-200">{{ __('Next Action Date:') }}</span>
                                    <span>{{ $followUp->meeting_next_action_date->format('l, d F Y') }}</span>
                                </div>
                            @endif

                            @if(!empty($followUp->meeting_photos))
                                <div>
                                    <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2.5">{{ __('Meeting Documentation Photos') }}</h4>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                        @foreach($followUp->meeting_photos as $photo)
                                            <a href="{{ asset('storage/'.$photo) }}" target="_blank" rel="noopener noreferrer" class="group relative aspect-video rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 bg-slate-100 block shadow-sm hover:shadow-md transition-shadow">
                                                <img src="{{ asset('storage/'.$photo) }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" alt="Meeting Doc">
                                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                    <span class="text-xs text-white font-medium flex items-center gap-1">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                        {{ __('View') }}
                                                    </span>
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="rounded-xl border border-dashed border-amber-300 bg-amber-50/60 dark:border-amber-900/60 dark:bg-amber-950/20 p-5 text-center">
                            <p class="text-xs text-amber-800 dark:text-amber-300 mb-3">{{ __('Meeting report has not been submitted yet.') }}</p>
                            @can('manage-sales')
                                <a href="{{ route('sales.follow-ups.meeting-report', $followUp) }}"
                                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-xs font-semibold shadow-sm transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                    <span>{{ __('Input Meeting Report (MOM & Photos)') }}</span>
                                </a>
                            @endcan
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
