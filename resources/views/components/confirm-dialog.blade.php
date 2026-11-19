@props(['id' => 'gs-confirm-dialog'])

{{--
    Kartu konfirmasi standar (satu untuk seluruh aplikasi).
    Dipasang sekali di layouts/app.blade.php; halaman memicu lewat atribut
    `data-confirm` pada form/tautan/tombol (lihat resources/js/confirm-dialog.js).

    Gaya mengikuti panel lonceng: kartu rounded-2xl, ikon dalam kotak kecil
    berwarna, judul tebal, rincian data, lalu dua tombol rounded-xl.
--}}
<div id="{{ $id }}"
     data-cancel-label="{{ __('Cancel') }}"
     data-confirm-label="{{ __('Yes, continue') }}"
     data-title-danger="{{ __('Confirm deletion') }}"
     data-title-success="{{ __('Confirm action') }}"
     data-title-warning="{{ __('Please review') }}"
     data-title-info="{{ __('Please confirm') }}"
     x-data
     x-cloak
     x-show="$store.confirm.open"
     x-effect="document.body.classList.toggle('overflow-y-hidden', $store.confirm.open)"
     x-init="$watch('$store.confirm.open', (open) => { if (open) $nextTick(() => $refs.cancelButton?.focus()) })"
     @keydown.escape.window="$store.confirm.answer(false)"
     {{ $attributes->merge(['class' => 'fixed inset-0 z-[1300] flex items-center justify-center p-4']) }}>

    {{-- Latar gelap tipis; klik = batal (aksi destruktif tetap butuh pilihan eksplisit). --}}
    <div x-show="$store.confirm.open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="absolute inset-0 bg-slate-950/55"
         aria-hidden="true"
         @click="$store.confirm.answer(false)"></div>

    <div x-show="$store.confirm.open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-1 scale-95"
         @keydown.tab.prevent="$refs.cancelButton === document.activeElement ? $refs.confirmButton.focus() : $refs.cancelButton.focus()"
         role="alertdialog"
         aria-modal="true"
         aria-labelledby="gs-confirm-title"
         aria-describedby="gs-confirm-message"
         class="relative w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl shadow-slate-900/15 motion-reduce:transition-none sm:p-6 dark:border-slate-700 dark:bg-slate-900 dark:shadow-black/40">

        <div class="flex items-start gap-3.5">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                  :class="($store.confirm.tones[$store.confirm.options.tone] || $store.confirm.tones.info).chip">
                <svg x-show="$store.confirm.options.tone === 'danger'" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v5M14 11v5" />
                </svg>
                <svg x-show="$store.confirm.options.tone === 'success'" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" /><path d="m8.5 12.3 2.4 2.4 4.8-5" />
                </svg>
                <svg x-show="$store.confirm.options.tone === 'warning'" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M10.3 3.9 1.9 18a2 2 0 0 0 1.7 3h16.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /><path d="M12 9v4M12 17h.01" />
                </svg>
                <svg x-show="$store.confirm.options.tone === 'info'" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" /><path d="M12 16v-4M12 8h.01" />
                </svg>
            </span>

            <div class="min-w-0 flex-1">
                <h2 id="gs-confirm-title"
                    class="text-base font-semibold leading-snug text-slate-900 dark:text-slate-100"
                    x-text="$store.confirm.options.title"></h2>
                <p id="gs-confirm-message"
                   class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400"
                   x-text="$store.confirm.options.message"></p>
            </div>
        </div>

        {{-- Rincian data penting (customer, nomor request, nominal) --}}
        <template x-if="$store.confirm.options.details.length > 0">
            <dl class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-100 bg-slate-50/80 px-4 dark:divide-slate-700/60 dark:border-slate-700/60 dark:bg-slate-800/60">
                <template x-for="row in $store.confirm.options.details" :key="row.label">
                    <div class="flex items-start justify-between gap-4 py-2.5">
                        <dt class="text-xs font-medium text-slate-500 dark:text-slate-400" x-text="row.label"></dt>
                        <dd class="text-right text-sm font-semibold text-slate-800 dark:text-slate-100" x-text="row.value"></dd>
                    </div>
                </template>
            </dl>
        </template>

        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button"
                    x-ref="cancelButton"
                    @click="$store.confirm.answer(false)"
                    class="group relative inline-flex h-10 items-center justify-center overflow-hidden rounded-xl bg-accent-500 px-5 text-sm font-medium text-white transition-all duration-300 hover:bg-accent-600 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">
                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                <span x-text="$store.confirm.options.cancelLabel"></span>
            </button>

            <button type="button"
                    x-ref="confirmButton"
                    @click="$store.confirm.answer(true)"
                    class="group relative inline-flex h-10 items-center justify-center overflow-hidden rounded-xl px-5 text-sm font-semibold text-white shadow-lg transition-all duration-300 hover:brightness-110 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
                    :class="[($store.confirm.tones[$store.confirm.options.tone] || $store.confirm.tones.info).button, ($store.confirm.tones[$store.confirm.options.tone] || $store.confirm.tones.info).ring]">
                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                <span class="relative" x-text="$store.confirm.options.confirmLabel"></span>
            </button>
        </div>
    </div>
</div>
