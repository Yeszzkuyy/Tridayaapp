<x-app-layout>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ $proposal->proposal_number }}</h1>
            <p class="text-slate-500 mt-1">
                {{ $proposal->customer }} •
                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">{{ $proposal->purchasingStatusLabel() }}</span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <x-icon-button as="a" icon="back" href="{{ route('admin.purchasing.index') }}" title="{{ __('Back') }}" />
            <x-icon-button as="a" icon="download" href="{{ route('sales.proposals.download-po', $proposal) }}" label="{{ __('Download PO') }}" title="{{ __('Download PO') }}" />
            @if($proposal->project)
                <x-icon-button as="a" icon="project" href="{{ route('projects.show', $proposal->project) }}" label="{{ __('View Project') }}" title="{{ __('View Project') }}" />
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-4">
            <h2 class="font-semibold text-slate-700 dark:text-slate-200">{{ __('Quotation Items') }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-500 border-b border-slate-200 dark:border-slate-700">
                            <th class="py-2 pr-2">{{ __('Description') }}</th>
                            <th class="py-2 pr-2 text-right">{{ __('Qty') }}</th>
                            <th class="py-2 pr-2">{{ __('Unit') }}</th>
                            <th class="py-2 pr-2 text-right">{{ __('Price') }}</th>
                            <th class="py-2 pr-2 text-right">{{ __('Discount') }}</th>
                            <th class="py-2 text-right">{{ __('Subtotal') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($proposal->items as $item)
                            <tr class="border-b border-slate-100 dark:border-slate-700/60">
                                <td class="py-2 pr-2 text-slate-800 dark:text-slate-100">{{ $item->description }}</td>
                                <td class="py-2 pr-2 text-right text-slate-600 dark:text-slate-300">{{ $item->quantity }}</td>
                                <td class="py-2 pr-2 text-slate-600 dark:text-slate-300">{{ $item->unit }}</td>
                                <td class="py-2 pr-2 text-right text-slate-600 dark:text-slate-300">{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                <td class="py-2 pr-2 text-right text-slate-600 dark:text-slate-300">{{ number_format($item->discount, 0, ',', '.') }}</td>
                                <td class="py-2 text-right font-medium text-slate-800 dark:text-slate-100">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end">
                <div class="w-full md:w-64 text-sm space-y-1">
                    <p class="flex justify-between text-slate-500"><span>{{ __('Subtotal') }}</span><span>{{ number_format($proposal->subtotal, 0, ',', '.') }}</span></p>
                    <p class="flex justify-between text-slate-500"><span>{{ __('Discount') }}</span><span>{{ number_format($proposal->discount, 0, ',', '.') }}</span></p>
                    <p class="flex justify-between text-slate-500"><span>{{ __('Tax') }}</span><span>{{ number_format($proposal->tax, 0, ',', '.') }}</span></p>
                    <p class="flex justify-between font-bold text-slate-800 dark:text-slate-100"><span>{{ __('Grand Total') }}</span><span>Rp {{ number_format($proposal->grand_total, 0, ',', '.') }}</span></p>
                </div>
            </div>

            @if($proposal->notes)
                <p class="text-sm text-slate-700 dark:text-slate-200 whitespace-pre-wrap">{{ $proposal->notes }}</p>
            @endif
        </div>

        <div class="space-y-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 text-sm space-y-1">
                <h2 class="font-semibold text-slate-700 dark:text-slate-200 mb-2">{{ __('Customer PO') }}</h2>
                <p class="text-slate-500">{{ __('PO Number') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->po_number ?: __('Not set yet') }}</span></p>
                <p class="text-slate-500">{{ __('PO Date') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->po_date?->format('d M Y') ?? __('Not set yet') }}</span></p>
                <p class="text-slate-500">{{ __('Delivery Deadline') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->delivery_deadline?->format('d M Y') ?? __('Not set yet') }}</span></p>
                <p class="text-slate-500">{{ __('Delivery Address') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->delivery_address ?: __('Not set yet') }}</span></p>
                <p class="text-slate-500">{{ __('Payment Terms') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->paymentTermsSummary() }}</span></p>
                @if($proposal->delivery_terms)
                    <p class="text-slate-500">{{ __('Delivery Terms') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->delivery_terms }}</span></p>
                @endif
                <p class="text-slate-500">{{ __('PO Status') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->purchasingStatusLabel() }}</span></p>
                @if($proposal->handoffWarnings())
                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        <p class="font-semibold mb-1">{{ __('Handoff data incomplete') }}</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($proposal->handoffWarnings() as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 text-sm space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-semibold text-slate-700 dark:text-slate-200">{{ __('Supplier Purchase Orders') }}</h2>
                    @can('manage-admin')
                        <x-icon-button as="a" icon="add" href="{{ route('admin.supplier-pos.create', ['proposal_id' => $proposal->id]) }}" title="{{ __('Create Supplier PO') }}" />
                    @endcan
                </div>
                @forelse($proposal->supplierPurchaseOrders as $supplierPo)
                    <a href="{{ route('admin.supplier-pos.show', $supplierPo) }}" class="block rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                        <span class="flex items-center justify-between gap-2">
                            <span class="font-mono font-medium text-accent-600 dark:text-accent-400">{{ $supplierPo->po_number }}</span>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ \App\Models\SupplierPurchaseOrder::statusBadgeClasses($supplierPo->status) }}">{{ \App\Models\SupplierPurchaseOrder::statusLabel($supplierPo->status) }}</span>
                        </span>
                        <span class="block text-xs text-slate-500 mt-0.5">{{ $supplierPo->supplier?->name ?? __('Not filled') }} • Rp {{ number_format($supplierPo->grand_total, 0, ',', '.') }}</span>
                    </a>
                @empty
                    <p class="text-xs text-slate-400">{{ __('No supplier purchase order yet.') }}</p>
                @endforelse
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 text-sm space-y-1">
                <h2 class="font-semibold text-slate-700 dark:text-slate-200 mb-2">{{ __('Quotation Info') }}</h2>
                <p class="text-slate-500">{{ __('Customer') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->customer }}</span></p>
                <p class="text-slate-500">{{ __('PT Group') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->lead?->pt_group ?? __('Not filled') }}</span></p>
                <p class="text-slate-500">{{ __('Sales Owner') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->lead?->assignee?->name ?? __('Not filled') }}</span></p>
                <p class="text-slate-500">{{ __('Prepared By') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->creator?->name ?? __('Not filled') }}</span></p>
                <p class="text-slate-500">{{ __('Quotation Date') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->proposal_date?->format('d M Y') ?? __('Not filled') }}</span></p>
                <p class="text-slate-500">{{ __('Valid Until') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->valid_until?->format('d M Y') ?? __('Not filled') }}</span></p>
                <p class="text-slate-500">{{ __('Project') }}:
                    @if($proposal->project)
                        <a href="{{ route('projects.show', $proposal->project) }}" class="font-medium text-accent-600 dark:text-accent-400 hover:underline">{{ $proposal->project->project_name }}</a>
                    @else
                        <span class="font-medium text-amber-600 dark:text-amber-400">{{ __('Not converted yet') }}</span>
                    @endif
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
