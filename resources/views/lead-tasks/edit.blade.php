<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Edit Task') }}</h1>
            <p class="text-slate-500 mt-1">{{ $task->lead?->customer?->name ?? '-' }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('lead-tasks.show', $task) }}" title="{{ __('Kembali') }}" />
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
        <form action="{{ route('lead-tasks.update', $task) }}" method="POST" class="space-y-6">
            @csrf @method('PUT')

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Judul Task') }} <span class="text-red-500">*</span></label>
                <input type="text" name="title" required value="{{ old('title', $task->title) }}"
                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Deskripsi') }}</label>
                <textarea name="description" rows="4"
                          class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('description', $task->description) }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Assign ke Inside Sales') }}</label>
                    <select name="assigned_to"
                            class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        <option value="">{{ __('-- Belum ditentukan --') }}</option>
                        @foreach($insideSales as $user)
                            <option value="{{ $user->id }}" @selected(old('assigned_to', $task->assigned_to) == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Jatuh Tempo') }}</label>
                    <x-datepicker name="due_date" value="{{ old('due_date', $task->due_date?->format('Y-m-d')) }}"></x-datepicker>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Prioritas') }}</label>
                    <select name="priority"
                            class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        <option value="">{{ __('-- Normal --') }}</option>
                        @foreach(\App\Models\LeadTask::PRIORITIES as $priority)
                            <option value="{{ $priority }}" @selected(old('priority', $task->priority) === $priority)>{{ ucfirst($priority) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Status') }} <span class="text-red-500">*</span></label>
                <select name="status" required
                        class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    @foreach(\App\Models\LeadTask::STATUSES as $status)
                        <option value="{{ $status }}" @selected(old('status', $task->status) === $status)>
                            {{ \App\Models\LeadTask::statusLabel($status) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                    {{ __('Simpan') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
