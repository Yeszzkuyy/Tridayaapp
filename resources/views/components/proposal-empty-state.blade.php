@props(['filtered' => false])

<div class="text-center">
    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-surface-elevated">
        <svg class="h-6 w-6 text-content-tertiary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 3h9l4 4v14H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />
            <path d="M15 3v4h4" />
            <path d="M9 12h6M9 16h4" />
        </svg>
    </div>
    @if($filtered)
        <p class="text-sm font-semibold text-content-primary">{{ __('No quotations match your filters.') }}</p>
        <p class="mt-1 text-xs text-content-tertiary">{{ __('Try a different search or clear the filters.') }}</p>
        <a href="{{ route('sales.proposals.index') }}"
           class="mt-4 inline-flex h-9 items-center rounded-xl bg-accent-600 px-4 text-sm font-semibold text-white transition-all duration-300 hover:bg-accent-700 active:scale-95">
            {{ __('Clear filters') }}
        </a>
    @else
        <p class="text-sm font-semibold text-content-primary">{{ __('No quotations yet.') }}</p>
        <p class="mt-1 text-xs text-content-tertiary">{{ __('Quotations created from leads will appear here.') }}</p>
    @endif
</div>
