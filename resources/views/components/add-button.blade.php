<a href="{{ $href }}" {{ $attributes->merge(['class' => 'group/btn relative inline-flex items-center justify-center gap-2 overflow-hidden rounded-lg bg-accent-50 px-4 py-2 text-sm font-medium text-accent-700 transition-all duration-300 hover:scale-105 hover:bg-accent-100 hover:shadow-md active:scale-95 w-full sm:w-auto dark:bg-accent-500/15 dark:text-accent-300 dark:hover:bg-accent-500/25']) }}>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
    {{ $slot }}
    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover/btn:translate-x-full" aria-hidden="true"></span>
</a>
