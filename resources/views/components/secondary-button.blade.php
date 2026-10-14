{{-- Tombol Cancel (batal di form/modal). Gaya solid accent + shine. --}}
<button {{ $attributes->merge(['type' => 'button', 'class' => 'group relative overflow-hidden rounded-xl bg-accent-500 px-6 py-2.5 text-sm font-medium text-white transition-all duration-300 hover:scale-105 hover:bg-accent-600 hover:shadow-lg hover:shadow-accent-500/30 active:scale-95']) }}>
    {{ $slot }}
    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
</button>
