<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Request Tim Teknis') }}</h1>
                <p class="text-slate-500 mt-1">{{ $lead->customer?->name ?? 'Lead #'.$lead->id }} • {{ __('langsung ke Lead Teknisi') }}</p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('leads.show', $lead) }}" title="{{ __('Back') }}" />
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
            <form action="{{ route('sales.technical-requests.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <input type="hidden" name="lead_id" value="{{ $lead->id }}">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Judul') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="title" required value="{{ old('title') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                        @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Tipe Request') }} <span class="text-red-500">*</span></label>
                        <select name="request_type" required class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                            @foreach(\App\Models\TechnicalRequest::TYPES as $type)
                                <option value="{{ $type }}" @selected(old('request_type') === $type)>{{ \App\Models\TechnicalRequest::typeLabel($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Prioritas') }} <span class="text-red-500">*</span></label>
                        <select name="priority" required class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                            <option value="normal" @selected(old('priority') === 'normal')>{{ __('Normal') }}</option>
                            <option value="urgent" @selected(old('priority') === 'urgent')>{{ __('Urgent') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Target Tanggal') }}</label>
                        <input type="date" name="target_date" value="{{ old('target_date') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                        @error('target_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Kebutuhan') }}</label>
                    <textarea name="requirement" rows="2" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">{{ old('requirement') }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Deskripsi Masalah') }}</label>
                    <textarea name="problem_description" rows="3" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">{{ old('problem_description') }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Scope') }}</label>
                    <textarea name="scope" rows="2" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">{{ old('scope') }}</textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Lampiran (1 file, maks 10MB)') }}</label>
                        <input type="file" name="attachment"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                        @error('attachment') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Catatan') }}</label>
                        <input type="text" name="notes" value="{{ old('notes') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <a href="{{ route('leads.show', $lead) }}"
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
