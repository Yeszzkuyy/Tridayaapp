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
                    <dd class="text-right font-medium text-slate-800 dark:text-slate-100" x-text="doc?.source_label"></dd>
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


            {{-- Ubah jenis dokumen (general/invoice/PO). Hanya manage-admin,
                 hanya untuk dokumen registry project yang bukan modul/BAST. --}}
            @if($canReclassifyDocs ?? false)
            <template x-if="doc?.manage">
                <form method="POST" :action="'{{ url('/documents') }}/' + doc.manage.id + '/type'"
                      class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                    @csrf
                    @method('PATCH')
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('Change document type') }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Document type controls who can access this file.') }}</p>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Document type') }}</label>
                        <select name="doc_type" x-model="doc.manage.doc_type"
                                @change="doc.manage.transaction_id = null"
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900">
                            <option value="general">{{ __('General Document') }}</option>
                            <option value="invoice">{{ __('Customer Invoice') }}</option>
                            <option value="po">{{ __('Legacy Customer PO') }}</option>
                        </select>
                    </div>

                    <div x-show="doc.manage.doc_type === 'invoice'">
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Related Invoice') }}</label>
                        <select name="transaction_id" x-model="doc.manage.transaction_id" :disabled="doc.manage.doc_type !== 'invoice'"
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900">
                            <option value="">{{ __('Choose invoice') }}</option>
                            <template x-for="opt in doc.manage.invoices" :key="opt.id">
                                <option :value="opt.id" x-text="opt.label"></option>
                            </template>
                        </select>
                    </div>

                    <div x-show="doc.manage.doc_type === 'po'">
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Related Purchase Order') }}</label>
                        <select name="transaction_id" x-model="doc.manage.transaction_id" :disabled="doc.manage.doc_type !== 'po'"
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900">
                            <option value="">{{ __('Choose purchase order') }}</option>
                            <template x-for="opt in doc.manage.purchase_orders" :key="opt.id">
                                <option :value="opt.id" x-text="opt.label"></option>
                            </template>
                        </select>
                    </div>

                    <p x-show="doc.manage.doc_type !== 'general'" class="text-xs text-amber-600 dark:text-amber-400">
                        {{ __('The transaction must belong to the same customer.') }}
                    </p>

                    <div class="flex justify-end">
                        <button type="submit"
                                class="group relative overflow-hidden rounded-xl bg-accent-600 px-5 py-2 text-sm font-medium text-white transition-all duration-300 hover:bg-accent-700 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                            {{ __('Save') }}
                            <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </template>
            @endif

            {{-- Ubah label kategori (metadata, tidak mengubah akses). --}}
            @if(($canReclassifyDocs ?? false) || ($canUploadDocs ?? false) || ($canEditDocCategory ?? false))
            <template x-if="doc?.category_manage">
                <form method="POST" :action="'{{ url('/documents') }}/' + doc.category_manage.id + '/category'"
                      class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                    @csrf
                    @method('PATCH')
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('Change category label') }}</p>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Category') }}</label>
                        <select name="document_category_id" x-model="doc.category_manage.category_id"
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900">
                            <option value="">{{ __('No category') }}</option>
                            @foreach(($documentCategories ?? []) as $categoryId => $categoryName)
                                <option value="{{ $categoryId }}">{{ $categoryName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit"
                                class="group relative overflow-hidden rounded-xl bg-accent-600 px-5 py-2 text-sm font-medium text-white transition-all duration-300 hover:bg-accent-700 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                            {{ __('Save') }}
                            <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </template>
            @endif
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
