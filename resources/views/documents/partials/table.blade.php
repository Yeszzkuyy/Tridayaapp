{{-- Tabel dokumen lintas modul.
Props: $documents (paginator DocumentCenterRow), $lockedCustomer (Customer|null) --}}
@php
    $categoryColors = [
        'customer' => 'blue',
        'quotation' => 'purple',
        'purchasing' => 'orange',
        'project' => 'cyan',
        'technical' => 'emerald',
        'finance' => 'green',
    ];
@endphp

<div class="overflow-x-auto">
    <table class="min-w-full">
        <thead class="bg-slate-50 dark:bg-slate-700">
            <tr>
                <th class="px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Document') }}</th>
                <th class="px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Category') }}</th>
                @unless($hideCustomerColumn ?? false)
                    <th class="hidden px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200 md:table-cell">{{ __('Customer') }}</th>
                @endunless
                <th class="hidden px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200 lg:table-cell">{{ __('Project / Transaction') }}</th>
                <th class="hidden px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200 xl:table-cell">{{ __('Document Date') }}</th>
                <th class="px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Uploaded') }}</th>
                <th class="hidden px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200 xl:table-cell">{{ __('Uploaded By') }}</th>
                <th class="px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Status') }}</th>
                <th class="px-4 py-3.5 text-right text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse($documents as $doc)
                @php
                    $panel = $doc->toPanel();
                    $panel['manage'] = null;

                    if (($canReclassifyDocs ?? false) && $doc->isReclassifiable()) {
                        $links = ($documentLinkOptions ?? [])[$doc->customerId] ?? [];
                        $panel['manage'] = [
                            'id' => $doc->sourceId,
                            'doc_type' => $doc->rawType ?: 'general',
                            'transaction_id' => in_array($doc->refType, [\App\Models\Invoice::class, \App\Models\PurchaseOrder::class], true)
                                ? $doc->refId : null,
                            'invoices' => $links['invoice'] ?? [],
                            'purchase_orders' => $links['po'] ?? [],
                        ];
                    }

                    $isModuleOrBast = in_array($doc->rawType, ['survey', 'sizing', 'instalasi', 'bast'], true);
                    $panel['category_manage'] = ($doc->source === 'project' && (
                        ($canReclassifyDocs ?? false)
                        || (($canEditDocCategory ?? false) && ! $isModuleOrBast)
                    )) ? [
                        'id' => $doc->sourceId,
                        'category_id' => $doc->rawCategoryId !== null ? (string) $doc->rawCategoryId : '',
                    ] : null;

                    $panel['categories'] = $documentCategories ?? [];
                @endphp
                <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-700/40">
                    <td class="px-4 py-4">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-[10px] font-bold uppercase text-slate-500 dark:bg-slate-700 dark:text-slate-300">
                                {{ $doc->extension() ?: __('FILE') }}
                            </span>
                            <div class="min-w-0">
                                <button type="button"
                                        @click='doc = {{ Illuminate\Support\Js::from($panel) }}; panelOpen = true'
                                        class="block max-w-[14rem] truncate text-left text-sm font-medium text-slate-800 transition hover:text-accent-600 dark:text-slate-100 dark:hover:text-accent-300 lg:max-w-xs"
                                        title="{{ $doc->name }}">
                                    {{ $doc->name }}
                                </button>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $doc->subcategoryLabel() }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <x-status-badge :color="$categoryColors[$doc->category] ?? 'slate'">{{ $doc->categoryLabel() }}</x-status-badge>
                    </td>
                    @unless($hideCustomerColumn ?? false)
                        <td class="hidden px-4 py-4 text-sm text-slate-600 dark:text-slate-300 md:table-cell">
                            {{ $doc->customerName ?: '-' }}
                        </td>
                    @endunless                    <td class="hidden px-4 py-4 text-sm text-slate-600 dark:text-slate-300 lg:table-cell">
                        <span class="block max-w-[14rem] truncate">
                            {{ $doc->projectName ?: ($doc->number ?: '-') }}
                        </span>
                        @if($doc->projectName && $doc->number)
                            <span class="block font-mono text-xs text-slate-400">{{ $doc->number }}</span>
                        @endif
                    </td>
                    <td class="hidden px-4 py-4 text-sm text-slate-600 dark:text-slate-300 xl:table-cell">{{ $doc->docDateLabel() }}</td>
                    <td class="px-4 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $doc->uploadedAtLabel() }}</td>
                    <td class="hidden px-4 py-4 text-sm text-slate-600 dark:text-slate-300 xl:table-cell">{{ $doc->uploaderName ?: '-' }}</td>
                    <td class="px-4 py-4">
                        @if(! $doc->available)
                            <x-status-badge color="red">{{ __('Unavailable') }}</x-status-badge>
                        @elseif($doc->statusLabel())
                            <x-status-badge :color="$doc->statusColor()">{{ $doc->statusLabel() }}</x-status-badge>
                        @else
                            <span class="text-xs text-slate-400">-</span>
                        @endif
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex items-center justify-end gap-1.5">
                            @if($doc->canPreview && $doc->previewUrl)
                                <x-icon-button as="a" icon="view" href="{{ $doc->previewUrl }}" target="_blank" rel="noopener" title="{{ __('Preview') }}" />
                            @endif
                            @if($doc->downloadUrl)
                                <x-icon-button as="a" icon="download" href="{{ $doc->downloadUrl }}" title="{{ __('Download') }}" />
                            @endif
                            <x-icon-button icon="more" title="{{ __('Details') }}"
                                           @click='doc = {{ Illuminate\Support\Js::from($panel) }}; panelOpen = true' />
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-6 py-16 text-center">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $emptyTitle ?? __('No documents yet.') }}</p>
                        @if(! empty($emptyHint))
                            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ $emptyHint }}</p>
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
