{{-- Tombol submit utama (Save). Gaya solid accent + shine — jangan dipakai untuk
     aksi non-utama; aksi lain pakai <x-icon-button>. --}}
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'group relative overflow-hidden rounded-xl bg-accent-600 px-6 py-2.5 text-sm font-medium text-white transition-all duration-300 hover:scale-105 hover:bg-accent-500 hover:shadow-lg hover:shadow-accent-500/40 hover:brightness-110 active:scale-95']) }}>
    {{ $slot }}
    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
</button>
