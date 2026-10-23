<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <nav class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1">
                    <a href="{{ route('sales.schedules.index') }}" class="hover:text-accent-600 dark:hover:text-accent-400">{{ __('Sales Schedules') }}</a>
                    <span>/</span>
                    <a href="{{ route('sales.follow-ups.index') }}" class="hover:text-accent-600 dark:hover:text-accent-400">{{ __('Follow Ups') }}</a>
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
                <x-icon-button as="a" icon="back" href="{{ $returnTo === 'schedule' ? route('sales.schedules.index', $returnDate ? ['date' => $returnDate] : []) : route('sales.follow-ups.index') }}" title="{{ __('Back') }}" />
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
                    @if($followUp->notes)
                        <div class="mt-2 text-xs bg-white/70 dark:bg-slate-900/60 p-3 rounded-xl border border-slate-200/60 dark:border-slate-700/60 text-slate-700 dark:text-slate-300">
                            <span class="font-semibold text-slate-900 dark:text-white">{{ __('Agenda / Initial Notes') }}:</span>
                            <p class="mt-0.5 whitespace-pre-line">{{ $followUp->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Form Meeting Report --}}
        <form action="{{ route('sales.follow-ups.meeting-report.store', $followUp) }}"
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

            {{-- 1. Photo Documentation --}}
            <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <label class="block text-sm font-semibold text-slate-900 dark:text-white">
                        {{ __('Photo Documentation') }} <span class="text-rose-500">*</span>
                    </label>
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Min. 1 photo, max. 3 photos (Max 5MB per photo)') }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                    {{ __('Upload photos taken during the meeting (e.g. discussion in person, presentation, or signed paper/document).') }}
                </p>

                <div class="space-y-4" x-data="photoUploader()">
                    {{-- Dropzone / File Picker --}}
                    <div class="relative border-2 border-dashed border-slate-300 dark:border-slate-600 hover:border-accent-500 dark:hover:border-accent-400 rounded-xl p-6 text-center cursor-pointer transition-colors bg-slate-50/50 dark:bg-slate-900/30"
                         @click="$refs.fileInput.click()">
                        <input type="file"
                               x-ref="fileInput"
                               name="meeting_photos[]"
                               accept="image/png,image/jpeg,image/jpg,image/webp"
                               multiple
                               class="hidden"
                               @change="handleFiles($event)">
                        
                        <div class="flex flex-col items-center justify-center space-y-2 pointer-events-none">
                            <div class="w-12 h-12 rounded-full bg-accent-100 dark:bg-accent-900/40 text-accent-600 dark:text-accent-300 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div class="text-sm text-slate-700 dark:text-slate-300">
                                <span class="font-semibold text-accent-600 dark:text-accent-400">{{ __('Click to upload') }}</span> {{ __('or take photo from camera') }}
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">JPG, JPEG, PNG, WEBP (Max 3 files)</p>
                        </div>
                    </div>

                    {{-- Image Previews --}}
                    <div x-show="previews.length > 0" class="grid grid-cols-3 gap-4 pt-2">
                        <template x-for="(preview, index) in previews" :key="index">
                            <div class="relative group rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 aspect-video bg-slate-100 dark:bg-slate-900 shadow-sm">
                                <img :src="preview" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                    <span class="text-xs text-white font-medium" x-text="'Foto ' + (index + 1)"></span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <p x-show="countError" class="text-xs text-rose-600 dark:text-rose-400 font-medium" x-text="countError"></p>
                </div>

                @error('meeting_photos')
                    <p class="mt-2 text-xs text-rose-600 dark:text-rose-400 font-medium">{{ $message }}</p>
                @enderror
                @error('meeting_photos.*')
                    <p class="mt-2 text-xs text-rose-600 dark:text-rose-400 font-medium">{{ $message }}</p>
                @enderror
            </div>

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
                <a href="{{ $returnTo === 'schedule' ? route('sales.schedules.index', $returnDate ? ['date' => $returnDate] : []) : route('sales.follow-ups.index') }}"
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

    <script>
        function photoUploader() {
            return {
                previews: [],
                countError: '',
                handleFiles(event) {
                    const files = event.target.files;
                    this.previews = [];
                    this.countError = '';

                    if (files.length > 3) {
                        this.countError = @js(__('Maximum 3 photos allowed.'));
                        event.target.value = '';
                        return;
                    }

                    Array.from(files).forEach(file => {
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = (e) => {
                                this.previews.push(e.target.result);
                            };
                            reader.readAsDataURL(file);
                        }
                    });
                }
            }
        }
    </script>
</x-app-layout>
