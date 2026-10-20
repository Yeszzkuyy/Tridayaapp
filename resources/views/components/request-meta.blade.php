@props(['label'])

<div class="min-w-0">
    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $label }}</p>
    <div class="mt-1 flex min-w-0 items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
        {{ $slot }}
    </div>
</div>
