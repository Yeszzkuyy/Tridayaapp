<x-app-layout>
    @php
        $followUp = $meeting ?? $followUp;
    @endphp
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <nav class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1">
                    <a href="{{ route('sales.schedules.index') }}" class="hover:text-accent-600 dark:hover:text-accent-400">{{ __('Sales Schedules') }}</a>
                    <span>/</span>
                    <a href="{{ route('sales.meetings.index') }}" class="hover:text-accent-600 dark:hover:text-accent-400">{{ __('Meeting') }}</a>
                    <span>/</span>
                    <span class="text-slate-800 dark:text-slate-200 font-medium">{{ __('Meeting Report') }}</span>
                </nav>
                <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                    <svg class="w-7 h-7 text-accent-600 dark:text-accent-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>{{ __('Meeting Report & MOM') }}</span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Complete the minutes of meeting (MOM) and attach photo documentation to close this meeting schedule.') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <x-icon-button as="a" icon="back" href="{{ $returnTo === 'schedule' ? route('sales.schedules.index', $returnDate ? ['date' => $returnDate] : []) : route('sales.meetings.index') }}" title="{{ __('Back') }}" />
            </div>
        </div>
    </x-slot>

    <div class="py-6 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Meeting Context Summary Card --}}
        <div class="mb-6 rounded-2xl bg-gradient-to-r from-accent-50 via-white to-sky-50 dark:from-slate-800/80 dark:via-slate-800/50 dark:to-slate-900 border border-accent-200/60 dark:border-slate-700/70 p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                            {{ __('Meeting') }}
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $followUp->follow_up_date?->format('l, d M Y') ?? '-' }}
                        </span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                        {{ $followUp->customer?->name ?? $followUp->lead?->customer?->name ?? '-' }}
                    </h3>
                    @if($followUp->lead)
                        <p class="text-xs text-slate-600 dark:text-slate-300">
                            <span class="font-medium text-slate-700 dark:text-slate-200">{{ __('Lead') }}:</span> {{ $followUp->lead->title ?? $followUp->lead->customer?->name }}
                        </p>
                    @endif
                    @if($followUp->description)
                        <div class="mt-2 text-xs bg-white/70 dark:bg-slate-900/60 p-3 rounded-xl border border-slate-200/60 dark:border-slate-700/60 text-slate-700 dark:text-slate-300">
                            <span class="font-semibold text-slate-900 dark:text-white">{{ __('Summary Meeting') }}:</span>
                            <p class="mt-0.5 whitespace-pre-line">{{ $followUp->description }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Form Meeting Report --}}
        <form action="{{ route('sales.meetings.report.store', $meeting) }}"
              method="POST"
              enctype="multipart/form-data"
              class="space-y-6">
            @csrf
            @if($returnTo)
                <input type="hidden" name="return" value="{{ $returnTo }}">
            @endif
            @if($returnDate)
                <input type="hidden" name="date" value="{{ $returnDate }}">
            @endif

            {{-- 1. Photo Documentation (foto bisa sudah diunggah saat meeting dibuat) --}}
            @include('sales.meetings._photo-uploader', [
                'existingPhotos' => $meeting->meeting_photos ?? [],
                'required' => empty($meeting->meeting_photos),
                'maxFiles' => max(0, 3 - count($meeting->meeting_photos ?? [])),
            ])

            {{-- 2. Meeting Notes / Minutes of Meeting (MOM) --}}
            <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <label for="meeting_notes" class="block text-sm font-semibold text-slate-900 dark:text-white">
                        {{ __('Minutes of Meeting (MOM) & Hasil Pertemuan') }} <span class="text-rose-500">*</span>
                    </label>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('Min. 10 characters') }}</span>
                </div>
                <textarea id="meeting_notes"
                          name="meeting_notes"
                          rows="6"
                          required
                          placeholder="{{ __('Summary of discussion, customer requirements, agreements reached, and pending action items...') }}"
                          class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-900/60 dark:text-white text-sm focus:border-accent-500 focus:ring-accent-500 p-3.5">{{ old('meeting_notes') }}</textarea>
                @error('meeting_notes')
                    <p class="mt-2 text-xs text-rose-600 dark:text-rose-400 font-medium">{{ $message }}</p>
                @enderror
            </div>

            {{-- 3. Meeting Outcome & Next Action Date --}}
            <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-6 shadow-sm space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-900 dark:text-white mb-2">
                        {{ __('Meeting Outcome / Status Akhir') }} <span class="text-rose-500">*</span>
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @php
                            $outcomes = [
                                'prospect' => ['label' => __('Prospect'), 'desc' => __('Interested, exploratory phase'), 'color' => 'peer-checked:border-blue-500 peer-checked:bg-blue-50 dark:peer-checked:bg-blue-900/20'],
                                'need_quotation' => ['label' => __('Need Quotation'), 'desc' => __('Customer requested official quotation'), 'color' => 'peer-checked:border-amber-500 peer-checked:bg-amber-50 dark:peer-checked:bg-amber-900/20'],
                                'follow_up' => ['label' => __('Needs Follow-up'), 'desc' => __('Requires another discussion/call'), 'color' => 'peer-checked:border-orange-500 peer-checked:bg-orange-50 dark:peer-checked:bg-orange-900/20'],
                                'negotiation' => ['label' => __('Negotiation'), 'desc' => __('Discussing pricing & terms'), 'color' => 'peer-checked:border-purple-500 peer-checked:bg-purple-50 dark:peer-checked:bg-purple-900/20'],
                                'closed_won' => ['label' => __('Closed Won'), 'desc' => __('Customer agreed to purchase!'), 'color' => 'peer-checked:border-green-500 peer-checked:bg-green-50 dark:peer-checked:bg-green-900/20'],
                                'closed_lost' => ['label' => __('Closed Lost'), 'desc' => __('Cancelled or selected competitor'), 'color' => 'peer-checked:border-rose-500 peer-checked:bg-rose-50 dark:peer-checked:bg-rose-900/20'],
                            ];
                        @endphp

                        @foreach($outcomes as $val => $info)
                            <label class="relative flex flex-col p-3.5 border border-slate-200 dark:border-slate-700 rounded-xl cursor-pointer hover:border-slate-400 dark:hover:border-slate-500 transition-all">
                                <input type="radio"
                                       name="meeting_outcome"
                                       value="{{ $val }}"
                                       {{ old('meeting_outcome') === $val ? 'checked' : '' }}
                                       required
                                       class="peer sr-only">
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-100 flex items-center justify-between">
                                    {{ $info['label'] }}
                                    <span class="w-3.5 h-3.5 rounded-full border border-slate-300 dark:border-slate-600 peer-checked:border-4 peer-checked:border-accent-600"></span>
                                </span>
                                <span class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">{{ $info['desc'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('meeting_outcome')
                        <p class="mt-2 text-xs text-rose-600 dark:text-rose-400 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60">
                    <label for="meeting_next_action_date" class="block text-sm font-semibold text-slate-900 dark:text-white mb-1">
                        {{ __('Next Agreed Action Date') }} <span class="text-xs font-normal text-slate-500">({{ __('Optional') }})</span>
                    </label>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">
                        {{ __('Set the target date for next deliverable (e.g. sending quotation, follow-up call, or demo presentation).') }}
                    </p>
                    <div class="max-w-xs">
                        <input type="date"
                               id="meeting_next_action_date"
                               name="meeting_next_action_date"
                               min="{{ date('Y-m-d') }}"
                               value="{{ old('meeting_next_action_date') }}"
                               class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-900/60 dark:text-white text-sm focus:border-accent-500 focus:ring-accent-500 p-2.5">
                    </div>
                    @error('meeting_next_action_date')
                        <p class="mt-2 text-xs text-rose-600 dark:text-rose-400 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Action Buttons following AGENTS.md §7 --}}
            <div class="flex items-center justify-end gap-3 pt-4">
                <a href="{{ $returnTo === 'schedule' ? route('sales.schedules.index', $returnDate ? ['date' => $returnDate] : []) : route('sales.meetings.index') }}"
                   class="group relative overflow-hidden rounded-xl bg-slate-500 hover:bg-slate-600 text-white px-6 py-2.5 text-sm font-medium transition-all duration-300 hover:shadow-lg active:scale-95 text-center">
                    <span class="relative z-10">{{ __('Cancel') }}</span>
                </a>
                
                <button type="submit"
                        class="group relative overflow-hidden rounded-xl bg-accent-600 hover:bg-accent-700 text-white px-6 py-2.5 text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full"></span>
                    <span class="relative z-10 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>{{ __('Submit Report & Complete Meeting') }}</span>
                    </span>
                </button>
            </div>
        </form>
    </div>

</x-app-layout>
