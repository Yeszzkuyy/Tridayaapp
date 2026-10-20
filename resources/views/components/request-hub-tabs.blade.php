@props([
    'tabs' => [],
    'active' => '',
    'counts' => [],
    'routeName',
    'showZero' => false,
])

<div class="mb-4 overflow-x-auto pb-1">
    <nav aria-label="{{ __('Request types') }}"
         class="inline-flex min-w-full w-max gap-1 rounded-2xl bg-slate-100 p-1.5 dark:bg-slate-700 sm:min-w-0 sm:w-fit">
        @foreach($tabs as $key => $tab)
            @php($count = $counts[$key] ?? 0)
            <a href="{{ route($routeName, ['tab' => $key]) }}"
               @if($active === $key) aria-current="page" @endif
               class="flex shrink-0 items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 sm:px-4 {{ $active === $key
                    ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-800 dark:text-slate-100'
                    : 'text-slate-500 hover:bg-slate-200 hover:text-slate-800 dark:text-slate-300 dark:hover:bg-slate-600 dark:hover:text-slate-100' }}">
                <x-icon name="{{ $tab['icon'] }}" class="h-4 w-4 {{ $active === $key ? 'text-accent-600 dark:text-accent-400' : '' }}" />
                <span class="whitespace-nowrap">{{ $tab['label'] }}</span>
                @if($showZero || $count > 0)
                    <span class="inline-flex h-6 items-center justify-center rounded-full px-2 text-[11px] font-semibold tabular-nums {{ $active === $key
                        ? 'bg-accent-100 text-accent-800 dark:bg-accent-900 dark:text-accent-200'
                        : 'bg-slate-200 text-slate-600 dark:bg-slate-600 dark:text-slate-200' }}">{{ $count }}</span>
                @endif
            </a>
        @endforeach
    </nav>
</div>
