@props(['type' => 'success', 'timeout' => 6000])

@php
$styles = [
    'success' => 'bg-green-100 dark:bg-green-900/30 border-green-300 dark:border-green-700 text-green-700 dark:text-green-400',
    'error' => 'bg-red-100 dark:bg-red-900/30 border-red-300 dark:border-red-700 text-red-700 dark:text-red-400',
];
$role = $type === 'error' ? 'alert' : 'status';
@endphp

<div x-data="{ show: true }" x-show="show"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 -translate-y-1"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     x-init="setTimeout(() => show = false, {{ (int) $timeout }})"
     role="{{ $role }}"
     {{ $attributes->merge(['class' => 'rounded-xl border px-5 py-3 mb-4 flex items-start justify-between gap-3 ' . ($styles[$type] ?? $styles['success'])]) }}>
    <div class="min-w-0 flex-1">{{ $slot }}</div>
    <button type="button" @click="show = false" aria-label="{{ __('Tutup') }}"
            class="shrink-0 rounded-lg p-1 opacity-70 transition hover:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
</div>
