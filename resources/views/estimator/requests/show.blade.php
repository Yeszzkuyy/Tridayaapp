<x-app-layout>
    @if(session('success') && session('success_card'))
        <x-status-card :message="session('success')" />
    @endif

    @php
        $itemStatusColors = [
            'pending' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
            'answered' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300',
            'revision' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
            'accepted' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        ];
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
                        · {{ $estimatorRequest->customer->pt_group }}
                    @endif
                </p>
            </div>
            <x-back-button :href="route('estimator.requests.index')" />
        </div>

        <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-3">
            <div class="space-y-4 xl:col-span-2">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6">
                    <h2 class="mb-4 text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Request Details') }}</h2>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-700/50">
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('Requested by') }}</p>
                            <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ $estimatorRequest->requester?->name ?? '-' }}</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $estimatorRequest->created_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') ?? '-' }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-700/50">
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('Estimator') }}</p>
                            <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ $estimatorRequest->handler?->name ?? __('Waiting for Estimator') }}</p>
                            @if($estimatorRequest->completed_at)
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Completed') }}: {{ $estimatorRequest->completed_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }}</p>
                            @endif
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

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-600 dark:bg-slate-800">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-700/60">
                        <h2 class="font-semibold text-slate-700 dark:text-slate-200">{{ __('Requested Items') }}</h2>
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ trans_choice(':count item|:count items', $estimatorRequest->items->count(), ['count' => $estimatorRequest->items->count()]) }}</span>
                    </div>

                    @can('answer', $estimatorRequest)
                        <form action="{{ route('estimator.requests.answer', $estimatorRequest) }}" method="POST" data-loading-text="{{ __('Sending…') }}">
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
                                            <th class="px-4 py-3">{{ __('Note') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                                        @foreach($estimatorRequest->items as $index => $item)
                                            <tr class="align-top">
                                                <td class="px-4 py-3">
                                                    <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                                                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $item->description }}</p>
                                                    <p class="mt-0.5 text-[11px] text-slate-400">{{ $item->product?->sku ?: '-' }} · {{ $item->quantity }} {{ $item->unit }}</p>
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
                            <div class="flex justify-end border-t border-slate-100 px-5 py-4 dark:border-slate-700/60">
                                <x-icon-button type="submit" icon="upload" label="{{ __('Send Prices') }}" title="{{ __('Send Prices') }}" />
                            </div>
                        </form>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-xs">
                                <thead class="bg-slate-50 uppercase tracking-wider text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
                                    <tr>
                                        <th class="px-4 py-3">{{ __('Item') }}</th>
                                        <th class="px-4 py-3">{{ __('Supplier') }}</th>
                                        <th class="px-4 py-3 text-right">{{ __('Cost') }}</th>
                                        <th class="px-4 py-3">{{ __('Stock') }}</th>
                                        <th class="px-4 py-3">{{ __('Warranty') }}</th>
                                        <th class="px-4 py-3">{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                                    @foreach($estimatorRequest->items as $item)
                                        <tr class="align-top">
                                            <td class="px-4 py-3">
                                                <p class="font-medium text-slate-800 dark:text-slate-100">{{ $item->description }}</p>
                                                <p class="mt-0.5 text-[11px] text-slate-400">{{ $item->product?->sku ?: '-' }} · {{ $item->quantity }} {{ $item->unit }}</p>
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
                    @endcan
                </section>
            </div>

            <aside class="space-y-4">
                @can('decide', $estimatorRequest)
                    @if($estimatorRequest->status === 'answered')
                        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                            <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Review Prices') }}</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Accept the prices or ask Estimator for a revision.') }}</p>
                            <form action="{{ route('estimator.requests.accept', $estimatorRequest) }}" method="POST" class="mt-4" onsubmit="return confirm('{{ __('Accept these prices as final?') }}')">
                                @csrf
                                <x-icon-button type="submit" icon="sparkles" label="{{ __('Accept Prices') }}" title="{{ __('Accept Prices') }}" />
                            </form>
                            <form action="{{ route('estimator.requests.revision', $estimatorRequest) }}" method="POST" class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-600">
                                @csrf
                                <label for="estimator-revision-reason" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Revision reason') }}</label>
                                <textarea id="estimator-revision-reason" name="reason" rows="3" required maxlength="2000" class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">{{ old('reason') }}</textarea>
                                <div class="mt-2">
                                    <x-icon-button type="submit" icon="edit" label="{{ __('Request Revision') }}" title="{{ __('Request Revision') }}" />
                                </div>
                            </form>
                        </section>
                    @endif
                @endcan

                @can('cancel', $estimatorRequest)
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                        <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Cancel Request') }}</h2>
                        <form action="{{ route('estimator.requests.cancel', $estimatorRequest) }}" method="POST" class="mt-3" onsubmit="return confirm('{{ __('Cancel this request?') }}')">
                            @csrf
                            <x-icon-button type="submit" icon="delete" label="{{ __('Cancel Request') }}" title="{{ __('Cancel Request') }}" />
                        </form>
                    </section>
                @endcan
            </aside>
        </div>
    </div>
</x-app-layout>
