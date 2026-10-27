<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Request Engineering Team') }}</h1>
                @if($lead)
                <p class="text-slate-500 mt-1">{{ $lead->customer?->name ?? 'Lead #'.$lead->id }} • {{ __('directly to the Lead Technician') }}</p>
                @else
                <p class="text-slate-500 mt-1">{{ __('Choose a lead first — the request goes directly to the Lead Technician.') }}</p>
                @endif
            </div>
            <x-icon-button as="a" icon="back" href="{{ $backUrl ?? ($lead ? route('leads.show', $lead) : route('sales.requests.index')) }}" title="{{ __('Back') }}" />
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
            <form action="{{ route('sales.technical-requests.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4"
                  data-loading-text="{{ __('Saving…') }}"
                  x-data="{ type: '{{ old('request_type', 'survey') }}' }">
                @csrf
                @if($lead)
                <input type="hidden" name="lead_id" value="{{ $lead->id }}">
                @else
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Lead') }} <span class="text-red-500">*</span></label>
                    <select name="lead_id" required class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                        <option value="">{{ __('Select a lead...') }}</option>
                        @foreach(($leads ?? collect()) as $option)
                            <option value="{{ $option->id }}" @selected((string) old('lead_id') === (string) $option->id)>{{ $option->customer?->name ?? 'Lead #'.$option->id }} — {{ \App\Models\Lead::salesStatusLabel($option->status) }}</option>
                        @endforeach
                    </select>
                    @error('lead_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Title') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="title" required value="{{ old('title') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                        @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Request Type') }} <span class="text-red-500">*</span></label>
                        <select name="request_type" x-model="type" required class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                            @foreach(\App\Models\TechnicalRequest::FORM_TYPES as $type)
                                <option value="{{ $type }}" @selected(old('request_type') === $type)>{{ \App\Models\TechnicalRequest::typeLabel($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- PIC Contact hanya relevan untuk pekerjaan Instalasi. --}}
                <div x-show="type === 'installation'" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('PIC Contact Name') }}</label>
                        <input type="text" name="pic_contact_name" value="{{ old('pic_contact_name') }}" maxlength="150"
                               placeholder="{{ __('On-site contact person') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                        @error('pic_contact_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('PIC Contact Phone') }}</label>
                        <input type="text" name="pic_contact_phone" value="{{ old('pic_contact_phone') }}" maxlength="40"
                               placeholder="{{ __('Phone / WhatsApp number') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                        @error('pic_contact_phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Target Date') }}</label>
                        <div class="flex items-center gap-2">
                            <input type="date" name="target_date" value="{{ old('target_date') }}"
                                   class="flex-1 rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                            <input type="time" name="target_time" value="{{ old('target_time') }}"
                                   ondblclick="try { this.showPicker() } catch (e) {}"
                                   title="{{ __('Double-click to open the time picker') }}"
                                   class="w-32 rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                        </div>
                        @error('target_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        @error('target_time') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Location (optional)') }}</label>
                    <div class="flex items-center gap-2">
                        <input id="tr-location" type="text" name="location" maxlength="255" value="{{ old('location') }}"
                               placeholder="{{ __('Address / meeting point or meeting link') }}"
                               data-location-autocomplete
                               class="flex-1 rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                        <x-maps-open-button input="tr-location" />
                        <x-location-autocomplete />
                    </div>
                    <p class="mt-1 text-xs text-slate-400">{{ __('Address opens in Google Maps; meeting links open directly.') }}</p>
                    @error('location') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Purpose') }}</label>
                    <textarea name="purpose" rows="3" placeholder="{{ __('What does the customer need? Keep it short...') }}"
                              class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">{{ old('purpose') }}</textarea>
                    @error('purpose') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Attachment (1 file, max 10MB)') }}</label>
                        <input type="file" name="attachment"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                        @error('attachment') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Notes') }}</label>
                        <input type="text" name="notes" value="{{ old('notes') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <a href="{{ $backUrl ?? ($lead ? route('leads.show', $lead) : route('sales.requests.index')) }}"
                       class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                        {{ __('Cancel') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </a>
                    <button type="submit"
                            class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Save') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
