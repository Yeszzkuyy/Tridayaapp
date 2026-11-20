<x-app-layout>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-3xl font-bold text-content-primary">{{ __('Goods Receipt') }} {{ $goodsReceipt->receipt_number }}</h1>
                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full {{ \App\Models\GoodsReceipt::statusBadgeClasses($goodsReceipt->status) }}">
                    {{ \App\Models\GoodsReceipt::statusLabel($goodsReceipt->status) }}
                </span>
            </div>
            <p class="text-content-secondary mt-1">{{ __('Received from supplier against a Supplier PO.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            @if($goodsReceipt->attachment_path)
                <x-file-preview :url="route('admin.goods-receipts.attachment', $goodsReceipt)"
                                :name="basename((string) $goodsReceipt->attachment_path)" />
                <x-icon-button as="a" icon="download" href="{{ route('admin.goods-receipts.attachment', $goodsReceipt) }}" title="{{ __('Download attachment') }}" />
            @endif
            @can('manage-admin')
                @if($goodsReceipt->status === 'draft')
                    <form method="POST" action="{{ route('admin.goods-receipts.verify', $goodsReceipt) }}">
                        @csrf
                        <x-icon-button icon="add" type="submit" label="{{ __('Verify Receipt') }}" title="{{ __('Verify receipt and update PO quantities') }}" />
                    </form>
                @endif
            @endcan
            <x-back-button href="{{ route('admin.goods-receipts.index') }}" />
        </div>
    </div>

    @if($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm dark:bg-red-900/30 dark:border-red-800 dark:text-red-200">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
            <h2 class="font-semibold text-content-primary mb-4">{{ __('Receipt Items') }}</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-content-secondary uppercase">{{ __('Item') }}</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-content-secondary uppercase">{{ __('Received') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-content-secondary uppercase">{{ __('Serial Numbers') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-content-secondary uppercase">{{ __('Condition') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($goodsReceipt->items as $item)
                            <tr>
                                <td class="px-3 py-3">
                                    <div class="text-sm font-medium text-content-primary">{{ $item->purchaseOrderItem?->item_name }}</div>
                                    <div class="text-xs text-content-secondary">{{ $item->product?->part_number ?? $item->purchaseOrderItem?->part_number ?? __('Not filled') }}</div>
                                </td>
                                <td class="px-3 py-3 text-center text-sm text-content-secondary">{{ number_format($item->quantity_received, 2) }} {{ $item->purchaseOrderItem?->unit }}</td>
                                <td class="px-3 py-3">
                                    @forelse($item->serial_numbers ?? [] as $serial)
                                        <span class="inline-flex mb-1 mr-1 px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-700 font-mono text-xs text-content-secondary">{{ $serial }}</span>
                                    @empty
                                        <span class="text-xs text-content-tertiary italic">{{ __('No serial numbers') }}</span>
                                    @endforelse
                                </td>
                                <td class="px-3 py-3 text-sm text-content-secondary">{{ $item->condition_notes ?? __('Not filled') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-4 text-sm">
            <div>
                <p class="text-xs uppercase tracking-wide text-content-tertiary">{{ __('Supplier PO') }}</p>
                @if($goodsReceipt->purchaseOrder)
                    <a href="{{ route('admin.supplier-pos.show', $goodsReceipt->purchaseOrder) }}" class="font-mono font-semibold text-accent-600 dark:text-accent-400 hover:underline">{{ $goodsReceipt->purchaseOrder->po_number }}</a>
                @else
                    <p class="text-content-tertiary italic">{{ __('Not filled') }}</p>
                @endif
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-content-tertiary">{{ __('Supplier') }}</p>
                <p class="text-content-primary">{{ $goodsReceipt->supplier?->name ?? __('Not filled') }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-content-tertiary">{{ __('Project') }}</p>
                @if($goodsReceipt->project)
                    <a href="{{ route('projects.show', $goodsReceipt->project) }}" class="text-content-primary hover:underline">{{ $goodsReceipt->project->project_name }}</a>
                @else
                    <p class="text-content-tertiary italic">{{ __('Not filled') }}</p>
                @endif
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-content-tertiary">{{ __('Received Date') }}</p>
                    <p class="text-content-primary">{{ $goodsReceipt->received_date?->format('d M Y') }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-content-tertiary">{{ __('Received By') }}</p>
                    <p class="text-content-primary">{{ $goodsReceipt->receiver?->name ?? __('Not filled') }}</p>
                </div>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-content-tertiary">{{ __('Supplier Delivery Note No.') }}</p>
                <p class="text-content-primary">{{ $goodsReceipt->supplier_delivery_note_no ?? __('Not filled') }}</p>
            </div>
            @if($goodsReceipt->notes)
                <div>
                    <p class="text-xs uppercase tracking-wide text-content-tertiary">{{ __('Notes') }}</p>
                    <p class="text-content-secondary whitespace-pre-line">{{ $goodsReceipt->notes }}</p>
                </div>
            @endif
            @if($goodsReceipt->supplierInvoices->isNotEmpty())
                <div>
                    <p class="text-xs uppercase tracking-wide text-content-tertiary">{{ __('Supplier Invoices') }}</p>
                    @foreach($goodsReceipt->supplierInvoices as $invoice)
                        <a href="{{ route('admin.supplier-invoices.show', $invoice) }}" class="block font-mono text-xs text-accent-600 dark:text-accent-400 hover:underline">{{ $invoice->internal_ref_number }}</a>
                    @endforeach
                </div>
            @endif
            @if($goodsReceipt->status === 'draft')
                <div class="rounded-xl border border-amber-200 bg-amber-50 text-amber-800 px-3 py-2 text-xs dark:bg-amber-900/30 dark:border-amber-800 dark:text-amber-200">
                    {{ __('Draft receipt does not change PO received quantities yet.') }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
