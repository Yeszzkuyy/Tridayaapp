<x-app-layout>
    @php
        $quantitySummary = $supplierPurchaseOrder->quantitySummary();
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-content-primary">{{ $supplierPurchaseOrder->po_number }}</h1>
            <p class="text-content-secondary mt-1">
                {{ $supplierPurchaseOrder->supplier?->name ?? '-' }} •
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ \App\Models\SupplierPurchaseOrder::statusBadgeClasses($supplierPurchaseOrder->status) }}">
                    {{ \App\Models\SupplierPurchaseOrder::statusLabel($supplierPurchaseOrder->status) }}
                </span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            @can('manage-admin')
                @if($supplierPurchaseOrder->status === 'draft')
                    <form action="{{ route('admin.supplier-pos.issue', $supplierPurchaseOrder) }}" method="POST" class="inline">
                        @csrf
                        <x-icon-button icon="add" type="submit" label="{{ __('Mark as Ordered') }}" title="{{ __('Mark as Ordered') }}" />
                    </form>
                @endif
                @if(in_array($supplierPurchaseOrder->status, ['draft', 'ordered']))
                    <form action="{{ route('admin.supplier-pos.cancel', $supplierPurchaseOrder) }}" method="POST" class="inline"
                          data-confirm="{{ __('Cancel this purchase order?') }}" data-confirm-tone="danger">
                        @csrf
                        <x-icon-button icon="delete" type="submit" label="{{ __('Cancel PO') }}" title="{{ __('Cancel PO') }}" />
                    </form>
                @endif
            @endcan
            <x-back-button href="{{ route('admin.supplier-pos.index') }}" />
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-4">
            <h2 class="font-semibold text-content-secondary">{{ __('Purchase Order Items') }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-content-secondary border-b border-slate-200 dark:border-slate-700">
                            <th class="py-2 pr-2">{{ __('Item') }}</th>
                            <th class="py-2 pr-2">{{ __('Part Number') }}</th>
                            <th class="py-2 pr-2 text-right">{{ __('Qty Ordered') }}</th>
                            <th class="py-2 pr-2 text-right">{{ __('Qty Received') }}</th>
                            <th class="py-2 pr-2 text-right">{{ __('Qty Delivered') }}</th>
                            <th class="py-2 pr-2 text-right">{{ __('Available') }}</th>
                            <th class="py-2 pr-2">{{ __('Unit') }}</th>
                            <th class="py-2 pr-2 text-right">{{ __('Unit Price') }}</th>
                            <th class="py-2 text-right">{{ __('Subtotal') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($supplierPurchaseOrder->items as $item)
                            <tr class="border-b border-slate-100 dark:border-slate-700/60 align-top">
                                <td class="py-2 pr-2 text-content-primary">
                                    {{ $item->item_name }}
                                    @if($item->description && $item->description !== $item->item_name)
                                        <p class="text-xs text-content-secondary">{{ $item->description }}</p>
                                    @endif
                                    @if($item->proposalItem)
                                        <p class="text-xs text-content-tertiary">{{ __('From quotation') }}: {{ $item->proposalItem->description }}</p>
                                    @endif
                                </td>
                                <td class="py-2 pr-2 text-content-secondary">{{ $item->part_number ?? '-' }}</td>
                                <td class="py-2 pr-2 text-right text-content-secondary">{{ number_format($item->quantity_ordered, 2, ',', '.') }}</td>
                                <td class="py-2 pr-2 text-right text-content-secondary">{{ number_format($item->quantity_received, 2, ',', '.') }}</td>
                                <td class="py-2 pr-2 text-right text-content-secondary">{{ number_format($item->quantity_delivered, 2, ',', '.') }}</td>
                                <td class="py-2 pr-2 text-right font-medium {{ $item->quantityAvailable() > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-content-tertiary' }}">{{ number_format($item->quantityAvailable(), 2, ',', '.') }}</td>
                                <td class="py-2 pr-2 text-content-secondary">{{ $item->unit }}</td>
                                <td class="py-2 pr-2 text-right text-content-secondary">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                <td class="py-2 text-right font-medium text-content-primary">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-content-secondary">
                    {{ __('Received') }} {{ number_format($quantitySummary['received'], 2, ',', '.') }}
                    / {{ number_format($quantitySummary['ordered'], 2, ',', '.') }}
                    · {{ __('Delivered') }} {{ number_format($quantitySummary['delivered'], 2, ',', '.') }}
                    — {{ __('partial receipts are handled in the goods receipt stage.') }}
                </p>
                <div class="w-full md:w-64 text-sm space-y-1">
                    <p class="flex justify-between text-content-secondary"><span>{{ __('Subtotal') }}</span><span>Rp {{ number_format($supplierPurchaseOrder->subtotal, 0, ',', '.') }}</span></p>
                    <p class="flex justify-between text-content-secondary"><span>{{ __('Discount') }}</span><span>Rp {{ number_format($supplierPurchaseOrder->discount, 0, ',', '.') }}</span></p>
                    <p class="flex justify-between text-content-secondary"><span>{{ __('Tax') }}</span><span>Rp {{ number_format($supplierPurchaseOrder->tax, 0, ',', '.') }}</span></p>
                    <p class="flex justify-between font-bold text-content-primary border-t border-slate-200 dark:border-slate-700 pt-1"><span>{{ __('Grand Total') }}</span><span>Rp {{ number_format($supplierPurchaseOrder->grand_total, 0, ',', '.') }}</span></p>
                </div>
            </div>

            @if($supplierPurchaseOrder->notes)
                <div class="text-sm">
                    <p class="font-medium text-content-secondary mb-1">{{ __('Notes') }}</p>
                    <p class="text-content-secondary whitespace-pre-wrap">{{ $supplierPurchaseOrder->notes }}</p>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 text-sm space-y-1">
                <h2 class="font-semibold text-content-secondary mb-2">{{ __('Supplier') }}</h2>
                <p class="text-content-secondary">{{ __('Name') }}: <span class="font-medium text-content-primary">{{ $supplierPurchaseOrder->supplier?->name ?? '-' }}</span></p>
                <p class="text-content-secondary">{{ __('Code') }}: <span class="font-medium text-content-primary">{{ $supplierPurchaseOrder->supplier?->code ?? '-' }}</span></p>
                <p class="text-content-secondary">{{ __('Contact Person') }}: <span class="font-medium text-content-primary">{{ $supplierPurchaseOrder->supplier?->contact_person ?? '-' }}</span></p>
                <p class="text-content-secondary">{{ __('Phone') }}: <span class="font-medium text-content-primary">{{ $supplierPurchaseOrder->supplier?->phone ?? '-' }}</span></p>
                <p class="text-content-secondary">{{ __('Email') }}: <span class="font-medium text-content-primary">{{ $supplierPurchaseOrder->supplier?->email ?? '-' }}</span></p>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 text-sm space-y-1">
                <h2 class="font-semibold text-content-secondary mb-2">{{ __('Order Info') }}</h2>
                <p class="text-content-secondary">{{ __('Issue Date') }}: <span class="font-medium text-content-primary">{{ $supplierPurchaseOrder->issue_date?->format('d M Y') ?? '-' }}</span></p>
                <p class="text-content-secondary">{{ __('Expected Delivery') }}: <span class="font-medium text-content-primary">{{ $supplierPurchaseOrder->expected_delivery_date?->format('d M Y') ?? '-' }}</span></p>
                <p class="text-content-secondary">{{ __('Created By') }}: <span class="font-medium text-content-primary">{{ $supplierPurchaseOrder->creator?->name ?? '-' }}</span></p>
                <p class="text-content-secondary">{{ __('Quotation') }}:
                    @if($supplierPurchaseOrder->proposal)
                        <a href="{{ route('admin.purchasing.show', $supplierPurchaseOrder->proposal) }}" class="font-mono font-medium text-accent-600 dark:text-accent-400 hover:underline">{{ $supplierPurchaseOrder->proposal->proposal_number }}</a>
                    @else
                        <span class="font-medium text-content-primary">-</span>
                    @endif
                </p>
                <p class="text-content-secondary">{{ __('Project') }}:
                    @if($supplierPurchaseOrder->project)
                        <a href="{{ route('projects.show', $supplierPurchaseOrder->project) }}" class="font-medium text-accent-600 dark:text-accent-400 hover:underline">{{ $supplierPurchaseOrder->project->project_name }}</a>
                    @else
                        <span class="font-medium text-content-primary">-</span>
                    @endif
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
