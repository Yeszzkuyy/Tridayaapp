<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ $title }}</h1>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600">
        <div class="flex flex-col items-center justify-center py-20 px-6 text-center">
            <div class="w-16 h-16 rounded-2xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center mb-5">
                <svg class="w-8 h-8 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <x-status-badge color="yellow">{{ __('In Development') }}</x-status-badge>
            <h2 class="mt-4 text-lg font-semibold text-slate-800 dark:text-slate-100">{{ $title }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-md">{{ __('This feature is under development.') }}</p>
        </div>
    </div>
</x-app-layout>