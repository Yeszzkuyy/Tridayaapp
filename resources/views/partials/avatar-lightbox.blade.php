{{-- Lightbox foto profil: dipicu event 'view-avatar' dari <x-user-avatar clickable> --}}
<div
    x-data="{ open: false, src: null, name: '' }"
    x-on:view-avatar.window="src = $event.detail.src; name = $event.detail.name; open = true"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
    style="display: none"
>
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" x-on:click="open = false" x-transition.opacity></div>

    <div
        class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 p-4 max-w-sm w-full"
        x-on:keydown.escape.window="open = false"
        x-transition
    >
        <button type="button" x-on:click="open = false"
                class="absolute -top-3 -right-3 w-8 h-8 rounded-full bg-slate-800 text-white flex items-center justify-center shadow-lg hover:bg-slate-700 transition"
                aria-label="{{ __('Close') }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>

        <img :src="src" :alt="name" class="w-full aspect-square object-cover rounded-xl bg-slate-100 dark:bg-slate-700">
        <p class="mt-3 text-center font-semibold text-slate-800 dark:text-slate-100" x-text="name"></p>
    </div>
</div>
