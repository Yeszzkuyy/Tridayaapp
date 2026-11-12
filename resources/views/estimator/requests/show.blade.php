<x-app-layout>
    @if(session('price_sent'))
        <div x-data="{ show: true }" x-show="show" x-cloak x-init="setTimeout(() => show = false, 1600)"
             class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/55 p-4" role="status" aria-live="polite">
            <div class="flex h-24 w-24 items-center justify-center rounded-2xl bg-white shadow-xl dark:bg-slate-800">
                <svg class="h-14 w-14 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" aria-hidden="true">
                    <path class="estimator-check" d="M4.5 12.75l6 6 9-13.5" pathLength="40"/>
                </svg>
            </div>
            <style>
                @keyframes estimator-check-draw { to { stroke-dashoffset: 0; } }
                .estimator-check { stroke-dasharray: 40; stroke-dashoffset: 40; animation: estimator-check-draw .5s ease-out forwards; }
                @media (prefers-reduced-motion: reduce) { .estimator-check { animation: none; stroke-dashoffset: 0; } }
            </style>
        </div>
    @endif

    @php
        $itemStatusColors = [
            'pending' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
            'answered' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300',
            'revision' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
            'accepted' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        ];
        $canAnswer = auth()->user()->can('answer', $estimatorRequest);
        $isEstimator = auth()->user()->can('manage-price-requests');
        $formOpen = $canAnswer && ! in_array($estimatorRequest->status, ['answered', 'completed'], true);
        $contactSuppliers = $isEstimator
            ? $estimatorRequest->items->pluck('supplier')->filter()->unique('id')->values()
            : collect();
    @endphp

    <div>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ __('Product Price Request') }}</h1>
                    <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">#{{ $estimatorRequest->id }}</span>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ \App\Models\EstimatorRequest::statusColor($estimatorRequest->status) }}">
                        {{ \App\Models\EstimatorRequest::statusLabel($estimatorRequest->status) }}
                    </span>
                </div>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Customer') }}:
                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $estimatorRequest->customer?->name ?? '-' }}</span>
                    @if($estimatorRequest->customer?->pt_group)
                        &middot; {{ $estimatorRequest->customer->pt_group }}
                    @endif
                </p>
            </div>
            <x-back-button :href="route('estimator.requests.index')" />
        </div>

        @if($estimatorRequest->status === 'revision')
            <section class="mb-4 overflow-hidden rounded-2xl border-2 border-amber-300 bg-amber-50 p-5 shadow-sm dark:border-amber-700/70 dark:bg-amber-950/40" aria-labelledby="estimator-revision-title">
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300" aria-hidden="true">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM10.29 3.86L1.82 18a2.25 2.25 0 002.01 3h16.34a2.25 2.25 0 002.01-3L13.71 3.86a2.25 2.25 0 00-3.42 0z"/>
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 id="estimator-revision-title" class="text-base font-bold text-amber-900 dark:text-amber-200">
                            {{ $canAnswer ? __('Revision Required') : __('Revision Sent') }}
                        </h2>
                        <p class="mt-1 text-sm text-amber-800 dark:text-amber-200/90">
                            {{ $canAnswer
                                ? __('Sales asked for a revision. Update the prices below, then send them again.')
                                : __('Waiting for Estimator to update the prices.') }}
                        </p>
                        @if($estimatorRequest->revision_reason)
                            <blockquote class="mt-3 rounded-xl border border-amber-200 bg-white/70 px-4 py-3 text-sm leading-6 text-amber-900 dark:border-amber-800/60 dark:bg-slate-900/40 dark:text-amber-100">
                                {{ $estimatorRequest->revision_reason }}
                            </blockquote>
                        @endif
                        @if($estimatorRequest->revision_requested_at)
                            <p class="mt-2 text-xs text-amber-700 dark:text-amber-300/80">
                                {{ __('Requested :time', ['time' => $estimatorRequest->revision_requested_at->timezone('Asia/Jakarta')->format('d M Y, H:i')]) }}
                            </p>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6" aria-labelledby="estimator-request-details">
            <h2 id="estimator-request-details" class="mb-4 text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Request Details') }}</h2>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-700/50">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('Requested by') }}</p>
                    <div class="mt-2 flex items-center gap-3">
                        @if($estimatorRequest->requester)
                            <x-user-avatar :user="$estimatorRequest->requester" size="w-11 h-11" text="text-sm" :clickable="false" />
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-800 dark:text-slate-100">{{ $estimatorRequest->requester->name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $estimatorRequest->created_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') ?? '-' }}</p>
                            </div>
                        @else
                            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-200 text-slate-500 dark:bg-slate-600 dark:text-slate-300" aria-hidden="true">?</span>
                            <p class="font-semibold text-slate-700 dark:text-slate-200">-</p>
                        @endif
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-700/50">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('Estimator') }}</p>
                    <div class="mt-2 flex items-center gap-3">
                        @if($estimatorRequest->handler)
                            <x-user-avatar :user="$estimatorRequest->handler" size="w-11 h-11" text="text-sm" color="green" :clickable="false" />
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-800 dark:text-slate-100">{{ $estimatorRequest->handler->name }}</p>
                                @if($estimatorRequest->completed_at)
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Completed') }}: {{ $estimatorRequest->completed_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }}</p>
                                @else
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Handling this request') }}</p>
                                @endif
                            </div>
                        @else
                            <span class="flex h-11 w-11 items-center justify-center rounded-full border border-dashed border-slate-300 bg-white text-slate-400 dark:border-slate-500 dark:bg-slate-800" aria-hidden="true">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                </svg>
                            </span>
                            <p class="text-sm italic text-slate-500 dark:text-slate-400">{{ __('Waiting for Estimator') }}</p>
                        @endif
                    </div>
                </div>
            </div>

            @if($estimatorRequest->project || $estimatorRequest->proposal)
                <div class="mt-4 flex flex-wrap gap-4 text-sm">
                    @if($estimatorRequest->project)
                        <span class="text-slate-500 dark:text-slate-400">{{ __('Project') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $estimatorRequest->project->project_name }}</span></span>
                    @endif
                    @if($estimatorRequest->proposal)
                        <span class="text-slate-500 dark:text-slate-400">{{ __('Quotation') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $estimatorRequest->proposal->proposal_number }}</span></span>
                    @endif
                </div>
            @endif

            @if($estimatorRequest->notes)
                <div class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-600">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Notes') }}</p>
                    <p class="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $estimatorRequest->notes }}</p>
                </div>
            @endif

            @if($estimatorRequest->attachment_path)
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-600">
                    <p class="min-w-0 break-all text-sm font-medium text-slate-800 dark:text-slate-100">{{ basename($estimatorRequest->attachment_path) }}</p>
                    <x-icon-button as="a" icon="download" href="{{ route('estimator.requests.attachment', $estimatorRequest) }}" label="{{ __('Download Attachment') }}" title="{{ __('Download Attachment') }}" />
                </div>
            @endif
        </section>

        <section class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-600 dark:bg-slate-800"
                 x-data="{ editing: {{ $formOpen ? 'true' : 'false' }}, sending: false }" aria-labelledby="estimator-requested-items">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-700/60">
                <h2 id="estimator-requested-items" class="font-semibold text-slate-700 dark:text-slate-200">{{ __('Requested Items') }}</h2>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ trans_choice(':count item|:count items', $estimatorRequest->items->count(), ['count' => $estimatorRequest->items->count()]) }}</span>
                    @if($canAnswer && $estimatorRequest->status === 'answered')
                        <x-icon-button type="button" icon="edit" label="{{ __('Edit Prices') }}" title="{{ __('Edit Prices') }}" x-on:click="editing = true" />
                    @endif
                </div>
            </div>

            @if($canAnswer)
                <form action="{{ route('estimator.requests.answer', $estimatorRequest) }}" method="POST"
                      x-show="editing" x-cloak x-on:submit="sending = true"
                      style="{{ $formOpen ? '' : 'display: none' }}">
                    @csrf
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-xs">
                            <thead class="bg-slate-50 uppercase tracking-wider text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
                                <tr>
                                    <th class="px-4 py-3">{{ __('Item') }}</th>
                                    <th class="px-4 py-3">{{ __('Supplier') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Cost') }}</th>
                                    <th class="px-4 py-3">{{ __('Stock') }}</th>
                                    <th class="px-4 py-3">{{ __('Warranty') }}</th>
                                    <th class="px-4 py-3">{{ __('Valid Until') }}</th>
                                    <th class="px-4 py-3">{{ __('Note') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                                @foreach($estimatorRequest->items as $index => $item)
                                    <tr class="align-top">
                                        <td class="px-4 py-3">
                                            <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ $item->description }}</p>
                                            <p class="mt-0.5 text-[11px] text-slate-400">{{ $item->product?->sku ?: '-' }} &middot; {{ $item->quantity }} {{ $item->unit }}</p>
                                            @if($item->notes)<p class="mt-0.5 text-[11px] text-slate-400">{{ $item->notes }}</p>@endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <select name="items[{{ $index }}][supplier_id]" class="w-full min-w-[150px] rounded-lg border-slate-300 text-xs focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                                                <option value="">{{ __('Select supplier') }}</option>
                                                @foreach($suppliers as $supplier)
                                                    <option value="{{ $supplier->id }}" @selected((int) old("items.{$index}.supplier_id", $item->supplier_id) === $supplier->id)>{{ $supplier->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="number" name="items[{{ $index }}][price]" min="0.01" step="0.01" value="{{ old("items.{$index}.price", $item->price) }}" class="w-32 rounded-lg border-slate-300 text-right text-xs focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                                        </td>
                                        <td class="px-4 py-3">
                                            <select name="items[{{ $index }}][stock_status]" class="w-24 rounded-lg border-slate-300 text-xs focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                                                <option value="ready" @selected(old("items.{$index}.stock_status", $item->stock_status) === 'ready')>{{ __('Ready') }}</option>
                                                <option value="indent" @selected(old("items.{$index}.stock_status", $item->stock_status) === 'indent')>{{ __('Indent') }}</option>
                                            </select>
                                            <input type="text" name="items[{{ $index }}][stock_eta]" maxlength="100" value="{{ old("items.{$index}.stock_eta", $item->stock_eta) }}" placeholder="{{ __('e.g. 2-3 weeks') }}" class="mt-1 w-28 rounded-lg border-slate-300 text-xs focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="text" name="items[{{ $index }}][warranty_info]" maxlength="255" value="{{ old("items.{$index}.warranty_info", $item->warranty_info) }}" placeholder="{{ __('e.g. 1 year distributor') }}" class="w-36 rounded-lg border-slate-300 text-xs focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="date" name="items[{{ $index }}][valid_until]" value="{{ old("items.{$index}.valid_until", $item->valid_until?->format('Y-m-d')) }}" class="w-36 rounded-lg border-slate-300 text-xs focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="text" name="items[{{ $index }}][notes]" maxlength="255" value="{{ old("items.{$index}.notes", $item->notes) }}" placeholder="{{ __('Vendor / MOQ note') }}" class="w-36 rounded-lg border-slate-300 text-xs focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @error('items') <p class="px-5 pb-2 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    @foreach($errors->keys() as $errorKey)
                        @if(str_starts_with($errorKey, 'items.'))
                            <p class="px-5 pb-2 text-xs text-rose-600 dark:text-rose-400">{{ $errors->first($errorKey) }}</p>
                        @endif
                    @endforeach
                    <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4 dark:border-slate-700/60">
                        @if($estimatorRequest->status === 'answered')
                            <x-secondary-button type="button" x-on:click="editing = false">{{ __('Cancel') }}</x-secondary-button>
                        @endif
                        <x-icon-button variant="solid" type="submit" icon="upload" label="{{ __('Send Prices') }}" title="{{ __('Send Prices') }}" />
                    </div>
                </form>

                <div x-show="sending" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/55 p-4" role="status" aria-live="polite">
                    <div class="flex h-24 w-24 items-center justify-center rounded-2xl bg-white shadow-xl dark:bg-slate-800">
                        <svg class="h-10 w-10 animate-spin text-accent-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </div>
                </div>
            @endif

            <div x-show="!editing" x-cloak class="overflow-x-auto">
                <table class="min-w-full text-left text-xs">
                    <thead class="bg-slate-50 uppercase tracking-wider text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3">{{ __('Item') }}</th>
                            <th class="px-4 py-3">{{ __('Supplier') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Cost') }}</th>
                            <th class="px-4 py-3">{{ __('Stock') }}</th>
                            <th class="px-4 py-3">{{ __('Warranty') }}</th>
                            <th class="px-4 py-3">{{ __('Valid Until') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($estimatorRequest->items as $item)
                            <tr class="align-top">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $item->description }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-400">{{ $item->product?->sku ?: '-' }} &middot; {{ $item->quantity }} {{ $item->unit }}</p>
                                    @if($item->product_id && (auth()->user()->can('manage-price-requests') || auth()->user()->can('manage-sales-leads') || auth()->user()->can('manage-admin') || auth()->user()->hasRole('ceo')))
                                        <a href="{{ route('estimator.price-history', ['product_id' => $item->product_id]) }}" class="mt-1 inline-block text-[11px] font-semibold text-accent-600 hover:underline dark:text-accent-400">{{ __('Price History') }}</a>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $item->supplier?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums text-slate-800 dark:text-slate-100">{{ $item->price !== null ? 'Rp '.number_format((float) $item->price, 0, ',', '.') : '-' }}</td>
                                <td class="px-4 py-3">
                                    @if($item->stock_status)
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $item->stock_status === 'indent' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' }}">
                                            {{ $item->stock_status === 'indent' ? __('Indent') : __('Ready') }}
                                        </span>
                                        @if($item->stock_eta)<p class="mt-0.5 text-[11px] text-slate-400">{{ $item->stock_eta }}</p>@endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $item->warranty_info ?: '-' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap tabular-nums text-slate-600 dark:text-slate-300">{{ $item->valid_until?->format('d M Y') ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $itemStatusColors[$item->status] ?? 'bg-slate-100 text-slate-600' }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="mt-4 grid grid-cols-1 items-start gap-4 lg:grid-cols-2">
            @can('decide', $estimatorRequest)
                @if($estimatorRequest->status === 'answered')
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                        <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Review Prices') }}</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Accept the prices or ask Estimator for a revision.') }}</p>
                        <form action="{{ route('estimator.requests.accept', $estimatorRequest) }}" method="POST" class="mt-4"
                              data-confirm="{{ __('The prices are locked and used for the customer quotation.') }}"
                              data-confirm-title="{{ __('Accept these prices as final?') }}"
                              data-confirm-tone="success"
                              data-confirm-label="{{ __('Yes, accept prices') }}"
                              data-confirm-details="{{ json_encode([
                                  ['label' => __('Customer'), 'value' => $estimatorRequest->customer?->name ?? '-'],
                                  ['label' => __('Request'), 'value' => '#'.$estimatorRequest->id],
                              ], JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }}">
                            @csrf
                            <x-icon-button type="submit" icon="sparkles" label="{{ __('Accept Prices') }}" title="{{ __('Accept Prices') }}" />
                        </form>
                        <form action="{{ route('estimator.requests.revision', $estimatorRequest) }}" method="POST" class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-600">
                            @csrf
                            <label for="estimator-revision-reason" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Revision reason') }}</label>
                            <textarea id="estimator-revision-reason" name="reason" rows="3" required maxlength="2000" class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800" placeholder="{{ __('Explain what must be revised (price, product, stock, or warranty).') }}">{{ old('reason') }}</textarea>
                            <div class="mt-2">
                                <x-icon-button type="submit" icon="edit" label="{{ __('Request Revision') }}" title="{{ __('Request Revision') }}" />
                            </div>
                        </form>
                    </section>
                @endif
            @endcan

            @if($isEstimator && $contactSuppliers->isNotEmpty())
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                    <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Supplier Contacts') }}</h2>
                    <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($contactSuppliers as $supplier)
                            <div class="flex flex-wrap items-center justify-between gap-2 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $supplier->name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $supplier->contact_person ?: '-' }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($supplier->phone)
                                        <x-icon-button as="a" icon="whatsapp" href="https://wa.me/{{ preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $supplier->phone)) }}" target="_blank" rel="noopener" title="{{ __('WhatsApp') }}" />
                                    @endif
                                    @if($supplier->email)
                                        <x-icon-button as="a" icon="mail" href="mailto:{{ $supplier->email }}" title="{{ __('Email') }}" />
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @can('cancel', $estimatorRequest)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                    <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Cancel Request') }}</h2>
                    <form action="{{ route('estimator.requests.cancel', $estimatorRequest) }}" method="POST" class="mt-3"
                          data-confirm="{{ __('This request is closed and cannot be resumed.') }}"
                          data-confirm-title="{{ __('Cancel this request?') }}"
                          data-confirm-tone="danger"
                          data-confirm-label="{{ __('Yes, cancel request') }}"
                          data-confirm-details="{{ json_encode([
                              ['label' => __('Customer'), 'value' => $estimatorRequest->customer?->name ?? '-'],
                              ['label' => __('Request'), 'value' => '#'.$estimatorRequest->id],
                          ], JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }}">
                        @csrf
                        <x-icon-button type="submit" icon="delete" label="{{ __('Cancel Request') }}" title="{{ __('Cancel Request') }}" />
                    </form>
                </section>
            @endcan
        </div>
    </div>
</x-app-layout>
