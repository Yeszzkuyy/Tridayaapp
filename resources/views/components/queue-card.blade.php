@props(['title', 'count' => null, 'href' => null])

<div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90">
    <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-700/60">
        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $title }}</h3>
        @if ($count !== null)
            <span class="inline-flex min-w-[20px] items-center justify-center rounded-full bg-accent-600 px-1.5 py-0.5 text-[11px] font-bold leading-none tabular-nums text-white">
                {{ $count }}
            </span>
        @endif
    </div>

    <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
        {{ $slot }}
    </div>

    @if ($href)
        <div class="border-t border-slate-100 px-4 py-2.5 dark:border-slate-700/60">
            <a href="{{ $href }}" class="text-xs font-semibold text-accent-600 hover:underline dark:text-accent-400">
                {{ __('View all') }} →
            </a>
        </div>
    @endif
</div>
