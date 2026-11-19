{{--
    Jendela pratinjau file bersama (dipakai semua modul).
    Dipasang sekali di layout; tombol dengan atribut data-doc-open
    membuka file tanpa perlu mengunduh lebih dulu.
--}}
<div id="doc-lightbox" class="fixed inset-0 z-[90] hidden items-center justify-center bg-slate-950/80 p-4" data-doc-modal>
    <div class="flex max-h-full w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-900">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 p-4 dark:border-slate-700">
            <p id="doc-lightbox-name" class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-800 dark:text-slate-100"></p>
            <div class="flex shrink-0 gap-2">
                <a id="doc-lightbox-open" href="#" target="_blank" rel="noopener"
                   class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-indigo-600 transition hover:bg-indigo-600 hover:text-white dark:border-slate-600 dark:text-indigo-400"
                   title="{{ __('Open in new tab') }}" aria-label="{{ __('Open in new tab') }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                </a>
                <a id="doc-lightbox-download" href="#"
                   class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-emerald-600 transition hover:bg-emerald-600 hover:text-white dark:border-slate-600 dark:text-emerald-400"
                   title="{{ __('Download') }}" aria-label="{{ __('Download') }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                </a>
                <button type="button" data-doc-modal-close aria-label="{{ __('Close') }}"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
        <div id="doc-lightbox-body" class="flex min-h-[40vh] min-w-0 items-center justify-center overflow-auto bg-slate-100 p-4 dark:bg-slate-950/60"></div>
    </div>
</div>
