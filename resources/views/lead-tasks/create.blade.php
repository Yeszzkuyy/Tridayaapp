<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Request Inside Sales') }}</h1>
            <p class="text-slate-500 mt-1">
                {{ isset($lead) && $lead ? ($lead->customer?->name ?? 'Lead #'.$lead->id) : __('Pilih lead terlebih dahulu.') }}
            </p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ url()->previous() }}" title="{{ __('Kembali') }}" />
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
        <form action="{{ route('lead-tasks.store') }}" method="POST" class="space-y-6">
            @csrf
            @if(isset($lead) && $lead)
                <input type="hidden" name="lead_id" value="{{ $lead->id }}">
            @else
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Lead') }} <span class="text-red-500">*</span></label>
                    <select name="lead_id" required
                            class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        <option value="">{{ __('-- Pilih Lead --') }}</option>
                        @foreach($leads ?? [] as $option)
                            <option value="{{ $option->id }}" @selected(old('lead_id') == $option->id)>
                                {{ $option->customer?->name ?? 'Lead #'.$option->id }} — {{ ucfirst($option->status) }}
                            </option>
                        @endforeach
                    </select>
                    @error('lead_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Judul Task') }} <span class="text-red-500">*</span></label>
                <input type="text" name="title" required value="{{ old('title') }}"
                       placeholder="{{ __('cth: Buatkan proposal teknis untuk customer') }}"
                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Deskripsi') }}</label>
                <textarea name="description" rows="4"
                          placeholder="{{ __('Jelaskan kebutuhan / requirement customer...') }}"
                          class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('description') }}</textarea>
                @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Assign ke Inside Sales') }}</label>
                    <select name="assigned_to"
                            class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        <option value="">{{ __('-- Belum ditentukan --') }}</option>
                        @foreach($insideSales as $user)
                            <option value="{{ $user->id }}" @selected(old('assigned_to') == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('assigned_to') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Jatuh Tempo') }}</label>
                    <x-datepicker name="due_date" value="{{ old('due_date') }}"></x-datepicker>
                    @error('due_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Prioritas') }}</label>
                    <select name="priority"
                            class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        <option value="">{{ __('-- Normal --') }}</option>
                        @foreach(\App\Models\LeadTask::PRIORITIES as $priority)
                            <option value="{{ $priority }}" @selected(old('priority') === $priority)>{{ ucfirst($priority) }}</option>
                        @endforeach
                    </select>
                    @error('priority') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-medium transition">
                    {{ __('Kirim Request') }}
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
