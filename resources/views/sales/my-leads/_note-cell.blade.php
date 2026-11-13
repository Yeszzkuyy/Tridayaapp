{{-- Latest Note: satu catatan terakhir, teks multi-baris seperti catatan Excel. --}}
<div class="min-w-[220px] max-w-[320px]">
    <div class="flex items-start gap-1.5">
        <p class="whitespace-pre-line text-xs leading-relaxed text-slate-600 dark:text-slate-300"
           :title="note"
           :class="note ? '' : 'text-slate-400'"
           x-text="note || {{ json_encode(__('No note yet')) }}">{{ ($cfg['note'] ?? '') !== '' ? $cfg['note'] : __('No note yet') }}</p>
        <button type="button" @click="startNote()" title="{{ __('Edit note') }}"
                class="mt-0.5 shrink-0 rounded-xl p-1 text-slate-400 opacity-70 transition hover:bg-slate-100 hover:text-accent-600 hover:opacity-100 focus-visible:opacity-100 dark:hover:bg-slate-700">
            <x-icon name="edit" class="h-3.5 w-3.5" />
            <span class="sr-only">{{ __('Edit note') }}</span>
        </button>
    </div>

    <div x-show="editingNote" x-cloak class="mt-2 space-y-1.5">
        <textarea x-model="draftNote" rows="4" maxlength="2000" placeholder="{{ __('What happened with this lead?') }}"
                  class="w-full rounded-xl border-slate-300 text-xs focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"></textarea>
        <p x-show="noteError" x-cloak x-text="noteError" class="text-[11px] text-red-500"></p>
        <div class="flex items-center gap-1.5">
            <button type="button" @click="saveNote()" :disabled="savingNote"
                    class="inline-flex h-8 items-center rounded-xl bg-accent-600 px-3 text-[11px] font-semibold text-white transition hover:bg-accent-700 active:scale-95 disabled:opacity-60">
                <span x-show="!savingNote">{{ __('Save') }}</span>
                <span x-show="savingNote" x-cloak>{{ __('Saving...') }}</span>
            </button>
            <button type="button" @click="cancelNote()"
                    class="inline-flex h-8 items-center rounded-xl border border-slate-200 px-3 text-[11px] font-medium text-slate-600 transition hover:bg-slate-50 active:scale-95 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">
                {{ __('Cancel') }}
            </button>
        </div>
    </div>
</div>
