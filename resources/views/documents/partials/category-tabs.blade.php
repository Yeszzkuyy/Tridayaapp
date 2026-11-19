{{-- Filter kategori (tab) + panel filter lanjutan.
     Semua tautan mempertahankan query string yang sedang aktif. --}}
@php
    $currentCategory = $filters['category'] ?? null;
    $hasFilters = collect($filters)->except(['sort', 'date_field'])->filter()->isNotEmpty();
    $tabUrl = function (?string $category) {
        return route('documents.index', array_filter(array_merge(
            request()->query(),
            ['category' => $category, 'page' => null]
        ), fn ($value) => $value !== null && $value !== ''));
    };
    $categoryLabels = [
        'customer' => __('Customer'),
        'quotation' => __('Quotation'),
        'purchasing' => __('Purchasing'),
        'project' => __('Project'),
        'technical' => __('Survey & Installation'),
        'finance' => __('Finance'),
    ];
@endphp

<div class="flex flex-wrap items-center gap-1 rounded-2xl bg-slate-100 p-1.5 dark:bg-slate-700">
    <a href="{{ $tabUrl(null) }}"
       class="rounded-xl px-4 py-2 text-sm font-medium transition-colors {{ $currentCategory === null
           ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-800 dark:text-slate-100'
           : 'text-slate-500 hover:bg-slate-200 hover:text-slate-800 dark:text-slate-300 dark:hover:bg-slate-600 dark:hover:text-slate-100' }}">
        {{ __('All Documents') }}
    </a>
    @foreach($categoryLabels as $key => $label)
        <a href="{{ $tabUrl($key) }}"
           class="rounded-xl px-4 py-2 text-sm font-medium transition-colors {{ $currentCategory === $key
               ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-800 dark:text-slate-100'
               : 'text-slate-500 hover:bg-slate-200 hover:text-slate-800 dark:text-slate-300 dark:hover:bg-slate-600 dark:hover:text-slate-100' }}">
            {{ $label }}
        </a>
    @endforeach
</div>
