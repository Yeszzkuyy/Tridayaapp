@php
    $subcategoryLabels = [
        'general' => __('General Document'),
        'invoice' => __('Customer Invoice'),
        'po' => __('Legacy Customer PO'),
        'payment' => __('Payment Proof'),
        'survey' => __('Survey Report'),
        'sizing' => __('Sizing'),
        'instalasi' => __('Installation Report'),
        'bast' => __('BAST'),
        'quotation' => __('Quotation File'),
        'customer_po' => __('Customer PO'),
        'supplier_invoice' => __('Supplier Invoice'),
        'supplier_payment' => __('Supplier Payment Proof'),
        'goods_receipt' => __('Goods Receipt'),
        'proposal' => __('Lead Proposal'),
        'company_profile' => __('Company Profile'),
        'technical' => __('Technical Documents'),
        'requirement' => __('Customer Requirements'),
        'supporting' => __('Supporting Documents'),
        'other' => __('Lead Attachment'),
        'npwp' => __('NPWP'),
    ];
    $statusLabels = [
        'draft' => __('Draft'),
        'unpaid' => __('Unpaid'),
        'partial' => __('Partial'),
        'paid' => __('Paid'),
        'cancelled' => __('Cancelled'),
        'diproses' => __('In Process'),
        'selesai' => __('Completed'),
        'dibatalkan' => __('Cancelled'),
        'accepted' => __('Accepted'),
    ];
    $hasFilters = filled($filters['q'] ?? null)
        || filled($filters['category'] ?? null)
        || filled($filters['subcategory'] ?? null)
        || filled($filters['customer_id'] ?? null)
        || filled($filters['project_id'] ?? null)
        || filled($filters['date_from'] ?? null)
        || filled($filters['date_to'] ?? null)
        || filled($filters['status'] ?? null);
@endphp

<x-app-layout>
    <div class="w-full space-y-6"
         x-data="{ showFilters: {{ $hasFilters ? 'true' : 'false' }}, panelOpen: false, doc: null }">

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Documents') }}</h1>
                <p class="mt-1 text-slate-500 dark:text-slate-400">
                    {{ __('Search and manage customer, project, and transaction documents in one place.') }}
                </p>
            </div>
        </div>

        @include('documents.partials.category-tabs', ['filters' => $filters])

        @include('documents.partials.filters', [
            'filters' => $filters,
            'options' => $options,
            'subcategoryLabels' => $subcategoryLabels,
            'statusLabels' => $statusLabels,
            'hasFilters' => $hasFilters,
        ])

        <p class="text-xs text-slate-400 dark:text-slate-500">
            {{ __('Search uses document metadata (file name, document number, customer, project) — not the content inside PDF files.') }}
        </p>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-600 dark:bg-slate-800">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-700">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ trans_choice(':count document found|:count documents found', $documents->total(), ['count' => $documents->total()]) }}
                </p>
                @if($hasFilters)
                    <a href="{{ route('documents.index') }}" class="text-xs font-medium text-accent-600 hover:text-accent-700 dark:text-accent-400">
                        {{ __('Clear filters') }}
                    </a>
                @endif
            </div>

            @include('documents.partials.table', [
                'documents' => $documents,
                'emptyTitle' => ! $hasAnySource
                    ? __('You do not have access to any documents yet.')
                    : ($hasFilters ? __('No documents match your search.') : __('No documents yet.')),
                'emptyHint' => ! $hasAnySource
                    ? __('Ask an administrator if you need access to a module document.')
                    : ($hasFilters ? __('Try a different keyword or clear the filters.') : __('Documents uploaded from Customer, Project, Quotation, Purchasing, or Finance will appear here.')),
            ])

            @if($documents->hasPages())
                <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-700">
                    {{ $documents->links() }}
                </div>
            @endif
        </div>

        @include('documents.partials.detail-panel')
    </div>
</x-app-layout>
