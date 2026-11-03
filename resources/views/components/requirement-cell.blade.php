{{--
    Sel tabel Requirement: rata kiri, maksimal 2 baris, klik untuk membaca
    teks lengkap di modal. Dipakai di tabel Lead/Opportunity, Manage Sales,
    dan My Leads agar tampilannya seragam.
--}}
@props(['text' => null])

@php
    $value = trim((string) $text);
@endphp

@if($value === '')
    <span class="text-slate-400">-</span>
@else
    <div x-data="{ open: false }" class="relative text-left">
        <button type="button"
                @click="open = true"
                class="block w-full cursor-pointer text-left text-sm leading-snug text-slate-700 transition-colors hover:text-accent-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 dark:text-slate-300 dark:hover:text-accent-400"
                title="{{ __('View full requirement') }}">
            <span class="line-clamp-2 whitespace-pre-line">{{ $value }}</span>
        </button>

        <template x-teleport="body">
            <div x-show="open" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
                 @click.self="open = false" @keydown.escape.window="open = false">
                <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-800">
                    <div class="mb-2 flex items-start justify-between gap-3">
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('Requirement') }}</h3>
                        <button type="button" @click="open = false"
                                class="rounded-xl p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 active:scale-95 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                                title="{{ __('Close') }}">✕</button>
                    </div>
                    <p class="max-h-64 overflow-y-auto whitespace-pre-line text-sm leading-relaxed text-slate-700 dark:text-slate-200">{{ $value }}</p>
                </div>
            </div>
        </template>
    </div>
@endif
