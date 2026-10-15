<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Buat Penawaran') }}</h1>
                <p class="text-slate-500 mt-1">{{ $lead->customer?->name ?? 'Lead #'.$lead->id }}</p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('leads.show', $lead) }}" title="{{ __('Back') }}" />
        </div>

        @if($completedTech)
            <div class="mb-4 p-4 rounded-2xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                <p class="text-xs font-semibold text-green-700 dark:text-green-300 uppercase">{{ __('Referensi Hasil Teknis') }}</p>
                <p class="text-sm mt-1 whitespace-pre-wrap">{{ $completedTech->technical_result }}</p>
                <p class="text-xs text-slate-500 mt-1">{{ __('Harga ditentukan manual oleh Sales pada item di bawah.') }}</p>
            </div>
        @endif

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
            <form action="{{ route('sales.proposals.store') }}" method="POST" class="space-y-4">
                @csrf
                @include('sales.proposals._form')
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
