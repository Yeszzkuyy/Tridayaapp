{{-- Modal ringkas: hasil komunikasi + jadwal berikutnya. --}}
<div x-show="open" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="{{ __('Complete follow up') }}">
    <div class="absolute inset-0 bg-slate-900/60" @click="close()"></div>

    <div class="relative w-full max-w-lg rounded-2xl border border-slate-200 bg-[var(--card-bg)] p-5 shadow-2xl dark:border-slate-600 dark:bg-slate-800">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">{{ __('Complete follow up') }}</h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400" x-text="customer"></p>
            </div>
            <button type="button" @click="close()" class="rounded-xl p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 active:scale-95 dark:hover:bg-slate-700" title="{{ __('Close') }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                <span class="sr-only">{{ __('Close') }}</span>
            </button>
        </div>

        <form :action="action" method="POST" data-ajax class="mt-4 space-y-3">
            @csrf
            <div>
                <label for="complete-outcome" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Result / Outcome') }} <span class="text-red-500">*</span></label>
                <textarea id="complete-outcome" name="outcome" rows="3" required x-model="outcome"
                          placeholder="{{ __('What did the customer say?') }}"
                          class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"></textarea>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="complete-next-date" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Next Follow Up (optional)') }}</label>
                    <input id="complete-next-date" type="date" name="next_follow_up_date" x-model="nextDate" min="{{ date('Y-m-d') }}"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                </div>
                <div>
                    <label for="complete-next-time" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Time (optional)') }}</label>
                    <input id="complete-next-time" type="time" name="next_follow_up_time" x-model="nextTime"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                </div>
            </div>

            <div class="flex items-center gap-2 pt-1">
                <button type="submit"
                        class="group relative inline-flex h-10 items-center overflow-hidden rounded-xl bg-accent-600 px-6 text-sm font-medium text-white transition-all duration-300 hover:bg-accent-700 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                    {{ __('Save') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </button>
                <button type="button" @click="close()"
                        class="inline-flex h-10 items-center rounded-xl border border-slate-200 px-6 text-sm font-medium text-slate-600 transition hover:bg-slate-50 active:scale-95 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">
                    {{ __('Cancel') }}
                </button>
            </div>
        </form>
    </div>
</div>

@include('sales.partials.contact-tracking')
