{{-- Panel detail dokumen. Data berasal dari baris hasil pencarian yang sudah
     melewati filter otorisasi; tidak ada endpoint lookup tambahan. --}}
<div x-show="panelOpen" x-cloak class="fixed inset-0 z-[90]" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-slate-950/60" @click="panelOpen = false" aria-hidden="true"></div>

    <aside class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col overflow-y-auto bg-white shadow-2xl dark:bg-slate-900">
        <div class="flex items-start justify-between gap-3 border-b border-slate-200 p-5 dark:border-slate-700">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-accent-600 dark:text-accent-400">{{ __('Document Details') }}</p>
                <h2 class="mt-1 break-words text-lg font-bold text-slate-800 dark:text-slate-100" x-text="doc?.name"></h2>
            </div>
            <button type="button" @click="panelOpen = false" aria-label="{{ __('Close') }}"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="flex-1 space-y-5 p-5">
            <template x-if="doc && !doc.available">
                <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-950/50 dark:text-red-300">
                    {{ __('The file is no longer available in storage. The document record is kept for reference.') }}
                </div>
            </template>

            <dl class="grid grid-cols-1 gap-3 text-sm">
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Category') }}</dt>
                    <dd class="text-right font-medium text-slate-800 dark:text-slate-100" x-text="doc ? doc.category + ' · ' + doc.subcategory : ''"></dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Source') }}</dt>
                    <dd class="text-right font-medium text-slate-800 dark:text-slate-100" x-text="doc?.source"></dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Customer') }}</dt>
                    <dd class="text-right font-medium text-slate-800 dark:text-slate-100" x-text="doc?.customer || '-'"></dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Project') }}</dt>
                    <dd class="text-right font-medium text-slate-800 dark:text-slate-100" x-text="doc?.project || '-'"></dd>
                </div>
                <div class="flex items-start justify-between gap-3" x-show="doc?.number">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Document Number') }}</dt>
                    <dd class="text-right font-medium text-slate-800 dark:text-slate-100" x-text="doc?.number"></dd>
                </div>
                <div class="flex items-start justify-between gap-3" x-show="doc?.ref_number">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Reference Number') }}</dt>
                    <dd class="text-right font-medium text-slate-800 dark:text-slate-100" x-text="doc?.ref_number"></dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Document Date') }}</dt>
                    <dd class="text-right font-medium text-slate-800 dark:text-slate-100" x-text="doc?.doc_date || '-'"></dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Uploaded') }}</dt>
                    <dd class="text-right font-medium text-slate-800 dark:text-slate-100" x-text="doc?.uploaded_at || '-'"></dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Uploaded By') }}</dt>
                    <dd class="text-right font-medium text-slate-800 dark:text-slate-100" x-text="doc?.uploader || '-'"></dd>
                </div>
                <div class="flex items-start justify-between gap-3" x-show="doc?.status">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Status') }}</dt>
                    <dd class="text-right font-medium text-slate-800 dark:text-slate-100" x-text="doc?.status"></dd>
                </div>
            </dl>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 p-5 dark:border-slate-700">
            <a x-show="doc?.can_preview && doc?.preview_url" :href="doc?.preview_url" target="_blank" rel="noopener"
               class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-indigo-600 transition hover:border-indigo-600 hover:bg-indigo-600 hover:text-white dark:border-slate-700 dark:bg-slate-800 dark:text-indigo-400">
                <x-icon name="eye" class="h-4 w-4" />
                <span>{{ __('Preview') }}</span>
            </a>
            <a x-show="doc?.download_url" :href="doc?.download_url"
               class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-teal-600 transition hover:border-teal-600 hover:bg-teal-600 hover:text-white dark:border-slate-700 dark:bg-slate-800 dark:text-teal-400">
                <x-icon name="folder" class="h-4 w-4" />
                <span>{{ __('Download') }}</span>
            </a>
            <a x-show="doc?.source_url" :href="doc?.source_url"
               class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-600 transition hover:border-slate-600 hover:bg-slate-600 hover:text-white dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                <x-icon name="project" class="h-4 w-4" />
                <span>{{ __('Open Source') }}</span>
            </a>
        </div>
    </aside>
</div>
