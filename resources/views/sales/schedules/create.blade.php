<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Buat Jadwal') }}</h1>
                <p class="text-slate-500 mt-1">{{ $lead->customer?->name ?? 'Lead #'.$lead->id }}</p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('leads.show', $lead) }}" title="{{ __('Back') }}" />
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
            <form action="{{ route('sales.schedules.store') }}" method="POST" class="space-y-4">
                @csrf
                @include('sales.schedules._form')
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
