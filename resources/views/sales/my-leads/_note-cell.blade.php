{{-- Latest Note: 3 catatan terbaru, urut lama ke baru; form tambah di bawah. --}}
<div class="min-w-[220px] max-w-[320px]">
    <ul x-ref="noteList" class="space-y-1">
        @forelse($cfg['notes'] ?? [] as $note)
            <li class="flex gap-1 text-xs leading-relaxed text-slate-600 dark:text-slate-300" title="{{ $note['time'] ?? '' }}">
                <span class="shrink-0 text-slate-400" aria-hidden="true">-</span>
                <span class="min-w-0 break-words">{{ $note['text'] }}</span>
            </li>
        @empty
            <li data-empty-note class="text-xs text-slate-400">{{ __('No note yet') }}</li>
        @endforelse
    </ul>

    <div class="mt-1.5 flex flex-wrap items-center gap-2">
        <button type="button" x-show="!editingNote" @click="startNote()"
                class="text-[11px] font-semibold text-accent-600 transition hover:text-accent-700 active:scale-95 dark:text-accent-400">
            + {{ __('Add note') }}
        </button>
        <a href="{{ $cfg['viewAllNotesUrl'] }}"
           class="text-[11px] font-medium text-slate-400 transition hover:text-slate-600 dark:hover:text-slate-200">
            {{ __('View all') }}
        </a>
    </div>

    <div x-show="editingNote" x-cloak class="mt-2 space-y-1.5">
        <textarea x-model="draftNote" rows="2" maxlength="2000" placeholder="{{ __('What happened with this lead?') }}"
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
