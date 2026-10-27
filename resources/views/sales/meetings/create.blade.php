<x-app-layout>
    <div>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('New Meeting') }}</h1>
                <p class="mt-1 text-slate-500 dark:text-slate-400">{{ __('Schedule a meeting and fill the report (photos + MOM) afterwards.') }}</p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('sales.meetings.index') }}" title="{{ __('Back') }}" />
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-600 dark:bg-slate-800">
            <form action="{{ route('sales.meetings.store') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Customer') }} <span class="text-red-500">*</span></label>
                    @include('sales._customer-picker', [
                        'name' => 'customer_id',
                        'options' => $customers,
                        'selected' => old('customer_id'),
                        'placeholder' => __('Search customer...'),
                        'required' => true,
                        'hint' => __('Choose the customer you met.'),
                    ])
                    @error('customer_id') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Related Lead (optional)') }}</label>
                        <select name="lead_id" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                            <option value="">{{ __('No specific opportunity') }}</option>
                            @foreach($leads as $lead)
                                <option value="{{ $lead->id }}" @selected((string) old('lead_id') === (string) $lead->id)>
                                    {{ $lead->customer?->name ?? 'Lead #'.$lead->id }} — {{ \App\Models\Lead::salesStatusLabel($lead->status) }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-400">{{ __('Only leads that belong to the selected customer are accepted.') }}</p>
                        @error('lead_id') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Meeting Mode') }} <span class="text-red-500">*</span></label>
                        <select name="meeting_mode" required class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                            <option value="">{{ __('Select mode') }}</option>
                            @foreach($modes as $mode)
                                <option value="{{ $mode }}" @selected(old('meeting_mode') === $mode)>{{ \App\Models\FollowUp::meetingModeLabel($mode) }}</option>
                            @endforeach
                        </select>
                        @error('meeting_mode') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Meeting Date') }} <span class="text-red-500">*</span></label>
                        <input type="date" name="follow_up_date" required value="{{ old('follow_up_date', date('Y-m-d')) }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                        @error('follow_up_date') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Time (optional)') }}</label>
                        <input type="time" name="activity_time" value="{{ old('activity_time') }}"
                               ondblclick="try { this.showPicker() } catch (e) {}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                        @error('activity_time') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Next Follow-up (optional)') }}</label>
                        <x-datepicker name="next_follow_up_date" value="{{ old('next_follow_up_date') }}"></x-datepicker>
                        @error('next_follow_up_date') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Location (optional)') }}</label>
                    <div class="flex items-center gap-2">
                        <input id="mtg-location" type="text" name="location" maxlength="255" value="{{ old('location') }}"
                               placeholder="{{ __('Address / meeting point or meeting link') }}"
                               data-location-autocomplete
                               class="flex-1 rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                        <x-maps-open-button input="mtg-location" />
                        <x-location-autocomplete />
                    </div>
                    <p class="mt-1 text-xs text-slate-400">{{ __('Address opens in Google Maps; meeting links open directly.') }}</p>
                    @error('location') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Agenda / Initial Notes') }} <span class="text-red-500">*</span></label>
                    <textarea name="description" rows="3" required placeholder="{{ __('What will be discussed in this meeting?') }}"
                              class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">{{ old('description') }}</textarea>
                    @error('description') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <a href="{{ route('sales.meetings.index') }}"
                       class="group relative overflow-hidden rounded-xl bg-accent-500 px-6 py-2.5 text-center text-sm font-medium text-white transition-all duration-300 hover:bg-accent-600 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Cancel') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </a>
                    <button type="submit"
                            class="group relative overflow-hidden rounded-xl bg-accent-600 px-6 py-2.5 text-sm font-medium text-white transition-all duration-300 hover:bg-accent-700 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Save') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
