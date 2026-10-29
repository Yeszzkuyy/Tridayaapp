{{-- Dokumen customer terpadu: satu baris per file, ditarik dari sumber aslinya
     (project documents, quotation, purchasing, finance, lead, NPWP).
     File tetap dikelola di modulnya masing-masing. --}}
@php
    $customerCategoryLabels = [
        'customer' => __('Customer'),
        'quotation' => __('Quotation'),
        'purchasing' => __('Purchasing'),
        'project' => __('Project'),
        'technical' => __('Survey & Installation'),
        'finance' => __('Finance'),
    ];
    $hasCustomerDocFilters = filled($customerDocumentFilters['q'] ?? null)
        || filled($customerDocumentFilters['category'] ?? null)
        || filled($customerDocumentFilters['subcategory'] ?? null);
@endphp

<section class="space-y-4" id="customer-documents-all">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('All Documents') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ __('Every document related to this customer, linked to its source record without duplicating the file.') }}
            </p>
        </div>

        <form method="GET" action="{{ route('customers.show', $customer) }}" class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <label class="relative w-full sm:w-64">
                <span class="sr-only">{{ __('Search documents') }}</span>
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input type="search" name="documents_q" value="{{ $customerDocumentFilters['q'] ?? '' }}" autocomplete="off"
                       placeholder="{{ __('Search documents...') }}"
                       class="h-10 w-full rounded-xl border border-slate-200 bg-white pl-9 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-accent-500 focus:outline-none focus:ring-2 focus:ring-accent-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            </label>
            <select name="documents_category" onchange="this.form.submit()" aria-label="{{ __('Filter category') }}"
                    class="h-10 w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 sm:w-48">
                <option value="">{{ __('All categories') }}</option>
                @foreach($customerCategoryLabels as $key => $label)
                    <option value="{{ $key }}" @selected(($customerDocumentFilters['category'] ?? null) === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <input type="hidden" name="documents_sort" value="{{ $customerDocumentFilters['sort'] ?? 'newest' }}">
            <div class="flex items-center gap-2">
                <x-icon-button icon="search" type="submit" title="{{ __('Search') }}" />
                @if($hasCustomerDocFilters)
                    <x-icon-button as="a" icon="reset" href="{{ route('customers.show', $customer) }}" title="{{ __('Reset') }}" />
                @endif
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-600 dark:bg-slate-800">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-700">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ trans_choice(':count document found|:count documents found', $customerDocuments->total(), ['count' => $customerDocuments->total()]) }}
            </p>
            @if($hasCustomerDocFilters)
                <a href="{{ route('customers.show', $customer) }}" class="text-xs font-medium text-accent-600 hover:text-accent-700 dark:text-accent-400">
                    {{ __('Clear filters') }}
                </a>
            @endif
        </div>

        @include('documents.partials.table', [
            'documents' => $customerDocuments,
            'emptyTitle' => $hasCustomerDocFilters
                ? __('No documents match your search.')
                : __('No documents yet.'),
            'emptyHint' => $hasCustomerDocFilters
                ? __('Try a different keyword or clear the filters.')
                : __('Upload a general document or add files from the related project, quotation, or transaction.'),
            'documentCategories' => $documentCategories ?? [],
            'documentLinkOptions' => $documentLinkOptions ?? [],
            'canReclassifyDocs' => $canReclassifyDocs ?? false,
            'canEditDocCategory' => $canEditDocCategory ?? ($canUploadDocs ?? false),
        ])

        @if($customerDocuments->hasPages())
            <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-700">
                {{ $customerDocuments->links() }}
            </div>
        @endif
    </div>
</section>
