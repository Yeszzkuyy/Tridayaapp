@props([
    'title',
    'status',
    'statusClass' => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
    'href',
    'actionTitle' => __('View details'),
    'actionLabel' => null,
    'actionIcon' => 'view',
    'subtitle' => null,
])

<article class="px-4 py-4 transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/30 sm:px-5 sm:py-5">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0 flex-1">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h2 class="break-words text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $title }}</h2>
                    @if($subtitle)
                        <p class="mt-1 break-words text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
                    @endif
                </div>
                <span class="inline-flex w-fit shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $status }}</span>
            </div>

            @if($slot->isNotEmpty())
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {{ $slot }}
                </div>
            @endif
        </div>

        <div class="flex shrink-0 justify-end sm:pl-4">
            <x-icon-button as="a" icon="{{ $actionIcon }}" href="{{ $href }}" :label="$actionLabel" title="{{ $actionTitle }}" />
        </div>
    </div>
</article>
