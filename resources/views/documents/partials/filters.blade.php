{{-- Pencarian utama + filter lanjutan. Satu form GET supaya semua filter
     bisa digabung dan query string tetap terbawa saat submit. --}}
<form method="GET" action="{{ route('documents.index') }}" class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <label class="relative flex-1">
            <span class="sr-only">{{ __('Search documents') }}</span>
            <x-icon name="search" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" autocomplete="off"
                   placeholder="{{ __('Search by document name, number, customer, or project...') }}"
                   class="h-12 w-full rounded-2xl border border-slate-200 bg-white pl-10 pr-4 text-sm text-slate-800 placeholder:text-slate-400 focus:border-accent-500 focus:outline-none focus:ring-2 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500">
        </label>
        <div class="flex items-center gap-2">
            <button type="button" @click="showFilters = !showFilters"
                    class="inline-flex h-12 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-600 transition hover:border-accent-500 hover:text-accent-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-accent-400 dark:hover:text-accent-300">
                <x-icon name="filter" class="h-4 w-4" />
                <span>{{ __('Filters') }}</span>
            </button>
            <x-icon-button icon="search" type="submit" size="lg" title="{{ __('Search') }}" />
            @if($hasFilters ?? false)
                <x-icon-button as="a" icon="reset" size="lg" href="{{ route('documents.index') }}" title="{{ __('Reset') }}" />
            @endif
        </div>
    </div>

    @if($filters['category'] ?? null)
        <input type="hidden" name="category" value="{{ $filters['category'] }}">
    @endif

    <div x-show="showFilters" x-cloak x-transition
         class="grid grid-cols-1 gap-4 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-600 dark:bg-slate-800 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Customer') }}</label>
            <x-searchable-select name="customer_id" :options="$options['customers']"
                                 :selected="$filters['customer_id'] ?? null"
                                 placeholder="{{ __('All customers') }}" />
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Project') }}</label>
            <x-searchable-select name="project_id" :options="$options['projects']"
                                 :selected="$filters['project_id'] ?? null"
                                 placeholder="{{ __('All projects') }}" />
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Document type') }}</label>
            <select name="subcategory" class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900">
                <option value="">{{ __('All types') }}</option>
                @foreach($subcategoryLabels as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['subcategory'] ?? null) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Status') }}</label>
            <select name="status" class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900">
                <option value="">{{ __('All statuses') }}</option>
                @foreach($statusLabels as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['status'] ?? null) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Date based on') }}</label>
            <select name="date_field" class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900">
                <option value="document" @selected(($filters['date_field'] ?? 'document') === 'document')>{{ __('Document date') }}</option>
                <option value="upload" @selected(($filters['date_field'] ?? 'document') === 'upload')>{{ __('Upload date') }}</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('From') }}</label>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                   class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('To') }}</label>
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                   class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Sort by') }}</label>
            <select name="sort" class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900">
                <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>{{ __('Newest upload') }}</option>
                <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>{{ __('Oldest upload') }}</option>
                <option value="name" @selected(($filters['sort'] ?? '') === 'name')>{{ __('Name (A-Z)') }}</option>
            </select>
        </div>
    </div>
</form>
