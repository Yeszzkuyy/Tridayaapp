{{-- Form tambah/edit follow-up: customer searchable + lead ikut customer (tanpa reload). --}}
@php($followUp = $followUp ?? null)
@php($initialCustomer = (string) old('customer_id', $customerId ?? null))
@php($initialLeadOptions = collect($leadItems)->where('customer_id', $initialCustomer)->map(fn ($l) => ['value' => $l['id'], 'label' => $l['label']])->values()->all())
<form action="{{ $action }}" method="POST" data-ajax data-submit-guarded="ajax" class="space-y-6"
      x-data="{
          customerId: {{ json_encode(old('customer_id', $customerId ?? null)) }},
          initialCustomerId: {{ json_encode((string) ($customerId ?? '')) }},
          leads: {{ json_encode($leadItems) }},
          str(v) { return (v === null || v === undefined || v === '') ? null : String(v); },
          init() { this.customerId = this.str(this.customerId); },
          toOpts(list) {
              const cid = this.customerId ? String(this.customerId) : null;
              return list.filter((x) => String(x.customer_id) === String(cid)).map((x) => ({ value: x.id, label: x.label }));
          },
          refreshGliders() {
              if (typeof window.__glideRemount !== 'function') return;
              const mountOf = (id) => document.querySelector('#' + id + ' [data-glide-mount]');
              window.__glideRemount(mountOf('fu-lead-glider'), this.toOpts(this.leads));
          }
      }"
      @customer-picked.window="if ($event.detail.field === 'customer_id') {
          if (String($event.detail.id ?? '') !== String(initialCustomerId ?? '') && $refs.firstTimeLead) $refs.firstTimeLead.value = '';
          customerId = $event.detail.id; refreshGliders();
      }">
    @csrf
    @if(!empty($method)) @method($method) @endif

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Customer') }} <span class="text-red-500">*</span></label>
        @include('sales._customer-picker', [
            'name' => 'customer_id',
            'options' => $customerOptions,
            'selected' => old('customer_id', $customerId ?? null),
            'placeholder' => __('Search customer...'),
            'required' => true,
            'hint' => ($isFirstTime ?? false) ? null : __('Pick a customer first — changing it resets Lead below.'),
        ])
        @error('customer_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    @if($isFirstTime ?? false)
    {{-- First-time: lead asal tombol dibawa diam-diam, biar follow-up pertama tetap tertaut. --}}
    <input type="hidden" name="lead_id" value="{{ old('lead_id', $leadId ?? '') }}" x-ref="firstTimeLead">
    @else
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Related Lead (optional)') }}</label>
        <div x-show="customerId">
            <div id="fu-lead-glider">
                <x-glide-select name="lead_id" label="{{ __('Related lead') }}"
                    :options="$initialLeadOptions"
                    :value="old('lead_id', $leadId ?? null)"
                    :empty-label="__('No specific opportunity')"
                    :error="$errors->first('lead_id')" />
            </div>
        </div>
        <p x-show="!customerId" class="text-xs text-slate-400">{{ __('Pilih customer terlebih dahulu.') }}</p>
        <p class="mt-1 text-xs text-slate-400">{{ __('Links this follow-up to an opportunity. Auto-linked when the customer has exactly one of your active leads.') }}</p>
    </div>
    @endif

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Type Follow Up') }}</label>
        <x-glide-select name="type" label="{{ __('Type Follow Up') }}"
            :options="collect(\App\Models\FollowUp::LOG_TYPES)->map(fn ($t) => ['value' => $t, 'label' => \App\Models\FollowUp::typeLabel($t)])->all()"
            :value="old('type', $followUp?->type)"
            :empty-label="__('Select type')"
            :error="$errors->first('type')" />
        <p class="mt-1 text-xs text-slate-400">{{ __('How to follow up with the customer.') }}</p>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Follow-up Notes') }} <span class="text-red-500">*</span></label>
        <textarea name="description" rows="4" required
                  placeholder="{{ __('What did you do or learn? Keep it short...') }}"
                  class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('description', $followUp?->description ?? '') }}</textarea>
        @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Follow-up Date') }}</label>
        <x-datepicker name="follow_up_date" value="{{ old('follow_up_date', isset($followUp) ? $followUp->follow_up_date?->format('Y-m-d') : date('Y-m-d')) }}"></x-datepicker>
        @error('follow_up_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Time (WIB, optional)') }}</label>
        <input type="time" name="activity_time" lang="id-ID" value="{{ old('activity_time', $followUp?->activity_time ? substr((string) $followUp->activity_time, 0, 5) : '') }}"
               @dblclick="try { $el.showPicker() } catch (e) {}"
               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
        @error('activity_time') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- Lokasi & Next Follow Up: hanya untuk repeat (first-time tetap simpel). --}}
    @if(!($isFirstTime ?? false))
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Location (optional)') }}</label>
        <div class="flex items-center gap-2">
            <input id="fu-location" type="text" name="location" maxlength="150" value="{{ old('location', $followUp?->location) }}"
                   placeholder="{{ __('Address / meeting point or meeting link') }}"
                   data-location-autocomplete
                   class="flex-1 rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
            <x-maps-open-button input="fu-location" />
            <x-location-autocomplete />
        </div>
        <p class="mt-1 text-xs text-slate-400">{{ __('Address opens in Google Maps; meeting links open directly.') }}</p>
        @error('location') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Next Follow-up (optional)') }}</label>
        <x-datepicker name="next_follow_up_date" value="{{ old('next_follow_up_date', isset($followUp) && $followUp->next_follow_up_date ? $followUp->next_follow_up_date->format('Y-m-d') : '') }}"></x-datepicker>
        @error('next_follow_up_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
    @endif

    <div class="flex gap-3 pt-2">
        <button type="submit"
                class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
            {{ $submitLabel }}
            <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
        </button>
        <a href="{{ route('sales.follow-ups.index') }}"
           class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
            {{ __('Cancel') }}
            <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
        </a>
    </div>
</form>
