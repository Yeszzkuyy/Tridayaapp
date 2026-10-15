<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Edit Jadwal') }}</h1>
                <p class="text-slate-500 mt-1">{{ $schedule->title }}</p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('sales.schedules.show', $schedule) }}" title="{{ __('Back') }}" />
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
            <form action="{{ route('sales.schedules.update', $schedule) }}" method="POST" class="space-y-4">
                @csrf @method('PUT')
                @include('sales.schedules._form', ['lead' => $schedule->lead])
                <div class="flex justify-end gap-2 pt-2">
                    <a href="{{ route('sales.schedules.show', $schedule) }}"
                       class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-white text-sm font-medium transition dark:border-slate-600 dark:text-slate-200">{{ __('Cancel') }}</a>
                    <button type="submit"
                            class="group relative overflow-hidden px-4 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-500 text-white text-sm font-medium transition-all duration-300 hover:scale-105 hover:shadow-lg hover:shadow-accent-500/40 active:scale-95">
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full"></span>
                        {{ __('Save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
