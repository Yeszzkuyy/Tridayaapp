<div class="space-y-4">
    <input type="hidden" name="lead_id" value="{{ $lead->id }}">

    <div>
        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Judul') }} <span class="text-red-500">*</span></label>
        <input type="text" name="title" required value="{{ old('title', $schedule->title ?? '') }}"
               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
        @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Tipe') }} <span class="text-red-500">*</span></label>
            <select name="type" required class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                @foreach(\App\Models\SalesSchedule::TYPES as $type)
                    <option value="{{ $type }}" @selected(old('type', $schedule->type ?? 'meeting') === $type)>{{ \App\Models\SalesSchedule::typeLabel($type) }}</option>
                @endforeach
            </select>
            @error('type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('PIC') }} <span class="text-red-500">*</span></label>
            <select name="assigned_to" required class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                @foreach($pics as $pic)
                    <option value="{{ $pic->id }}" @selected((int) old('assigned_to', $schedule->assigned_to ?? auth()->id()) === (int) $pic->id)>{{ $pic->name }}</option>
                @endforeach
            </select>
            @error('assigned_to') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Mulai') }} <span class="text-red-500">*</span></label>
            <input type="datetime-local" name="start_at" required
                   value="{{ old('start_at', isset($schedule) ? $schedule->start_at?->format('Y-m-d\TH:i') : now()->addHour()->format('Y-m-d\TH:i')) }}"
                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
            @error('start_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Selesai') }}</label>
            <input type="datetime-local" name="end_at"
                   value="{{ old('end_at', isset($schedule) ? $schedule->end_at?->format('Y-m-d\TH:i') : '') }}"
                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
            @error('end_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Lokasi') }}</label>
            <div class="flex items-center gap-2">
                <input id="ss-location" type="text" name="location" value="{{ old('location', $schedule->location ?? '') }}"
                       placeholder="{{ __('Address / meeting point or meeting link') }}"
                       class="flex-1 rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                <x-maps-open-button input="ss-location" />
            </div>
            <p class="mt-1 text-xs text-slate-400">{{ __('Address opens in Google Maps; meeting links open directly.') }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Pengingat') }}</label>
            <input type="datetime-local" name="reminder_at"
                   value="{{ old('reminder_at', isset($schedule) ? $schedule->reminder_at?->format('Y-m-d\TH:i') : '') }}"
                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
            @error('reminder_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Deskripsi') }}</label>
        <textarea name="description" rows="3"
                  class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">{{ old('description', $schedule->description ?? '') }}</textarea>
    </div>
</div>
