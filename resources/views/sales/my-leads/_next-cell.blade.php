{{-- Sel Next Follow Up: badge status + atur tanggal/jam langsung. --}}
<div class="min-w-[160px]">
    <template x-if="nextLabel">
        <div>
            <p class="text-xs font-semibold text-slate-800 dark:text-slate-100" x-text="nextLabel"></p>
            <p x-show="nextTime" class="text-[11px] text-slate-400" x-text="nextTime"></p>
            <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="nextClass()" x-text="nextStateText()"></span>
        </div>
    </template>
    <template x-if="!nextLabel">
        <span class="text-xs text-slate-400">{{ __('Not scheduled') }}</span>
    </template>

    <button type="button" @click="openNext()"
            class="mt-1 inline-flex items-center gap-1 rounded-xl px-1.5 py-0.5 text-[11px] font-semibold text-accent-600 transition hover:bg-accent-50 active:scale-95 dark:text-accent-400 dark:hover:bg-slate-700">
        <x-icon name="calendar" class="h-3.5 w-3.5" />
        {{ __('Set') }}
    </button>

    <div x-show="nextOpen" x-cloak class="mt-2 w-56 space-y-1.5 rounded-xl border border-slate-200 bg-[var(--card-bg)] p-2 shadow-lg dark:border-slate-600 dark:bg-slate-800">
        <input type="date" x-model="nextDate" min="{{ date('Y-m-d') }}"
               class="w-full rounded-xl border-slate-300 text-xs dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
        <input type="time" x-model="nextTime"
               class="w-full rounded-xl border-slate-300 text-xs dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
        <div class="flex items-center gap-1.5">
            <button type="button" @click="saveNext()" :disabled="nextSaving"
                    class="inline-flex h-8 flex-1 items-center justify-center rounded-xl bg-accent-600 px-3 text-[11px] font-semibold text-white transition hover:bg-accent-700 active:scale-95 disabled:opacity-60">
                <span x-show="!nextSaving">{{ __('Save') }}</span>
                <span x-show="nextSaving" x-cloak>{{ __('Saving...') }}</span>
            </button>
            <button type="button" @click="cancelNext()"
                    class="inline-flex h-8 items-center rounded-xl border border-slate-200 px-3 text-[11px] font-medium text-slate-600 transition hover:bg-slate-50 active:scale-95 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">
                {{ __('Cancel') }}
            </button>
        </div>
    </div>
</div>
