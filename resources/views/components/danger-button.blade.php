{{-- Tombol hapus/destructive (teks) — putih + border, hover merah. --}}
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'group relative overflow-hidden rounded-lg border border-red-200 bg-white px-6 py-2.5 text-sm font-medium text-red-600 transition-all duration-300 hover:scale-105 hover:border-red-500 hover:bg-red-50 hover:shadow-md active:scale-95 dark:border-red-500/30 dark:bg-slate-800 dark:text-red-400 dark:hover:border-red-400 dark:hover:bg-red-500/15']) }}>
    {{ $slot }}
    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
</button>
