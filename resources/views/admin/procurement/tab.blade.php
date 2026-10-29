<div class="space-y-6">
    {{-- KPI ringkas pengadaan & AP --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-600 p-4">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Supplier PO Outstanding') }}</p>
            <p class="mt-1 text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ $kpi['supplier_pos_outstanding_count'] ?? 0 }}</p>
            <a href="{{ route('admin.purchasing.reports') }}" class="mt-2 inline-block text-xs text-accent-600 dark:text-accent-400 hover:underline">{{ __('View report') }}</a>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-600 p-4">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Receipts Not Yet Invoiced') }}</p>
            <p class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $kpi['unbilled_receipts_count'] ?? 0 }}</p>
            <a href="{{ route('admin.purchasing.reports') }}" class="mt-2 inline-block text-xs text-accent-600 dark:text-accent-400 hover:underline">{{ __('View report') }}</a>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-600 p-4">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Supplier Invoice Outstanding') }}</p>
            <p class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ $kpi['supplier_invoices_outstanding_count'] ?? 0 }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">Rp {{ number_format($kpi['supplier_invoices_outstanding_amount'] ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-600 p-4">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('BAST Pending') }} / {{ __('Closed') }}</p>
            <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $kpi['bast_pending_count'] ?? 0 }} <span class="text-base font-medium text-green-600 dark:text-green-400">/ {{ $kpi['commercial_closed_count'] ?? 0 }}</span></p>
            <a href="{{ route('admin.bast.index') }}" class="mt-2 inline-block text-xs text-accent-600 dark:text-accent-400 hover:underline">{{ __('Open BAST') }}</a>
        </div>
    </div>

    {{-- Aksi cepat --}}
    <div class="flex flex-wrap gap-2">
        @can('manage-admin')
            <x-icon-button as="a" icon="add" href="{{ route('admin.goods-receipts.create') }}" label="{{ __('Receive Goods') }}" title="{{ __('Receive Goods') }}" />
            <x-icon-button as="a" icon="add" href="{{ route('admin.supplier-invoices.create') }}" label="{{ __('Record Supplier Invoice') }}" title="{{ __('Record Supplier Invoice') }}" />
        @endcan
        <x-icon-button as="a" icon="view" href="{{ route('admin.purchasing.reports') }}" label="{{ __('Procurement Reports') }}" title="{{ __('Procurement Reports') }}" />
        <x-icon-button as="a" icon="view" href="{{ route('admin.bast.index') }}" label="{{ __('BAST') }}" title="{{ __('BAST') }}" />
    </div>

    {{-- Anti-denda: tenggat pengiriman <= 7 hari / sudah lewat --}}
    @if(($kpi['deadline_risk_count'] ?? 0) > 0)
        <div class="rounded-2xl border border-amber-300 bg-amber-50 dark:bg-amber-950/40 dark:border-amber-800 overflow-hidden">
            <div class="px-5 py-4 border-b border-amber-200 dark:border-amber-800 flex items-center justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-amber-800 dark:text-amber-200 flex items-center gap-2">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                        {{ __('Delivery Deadline Risk') }} ({{ $kpi['deadline_risk_count'] }})
                    </h3>
                    <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">{{ __('Projects approaching or past the delivery deadline with goods not fully delivered.') }}</p>
                </div>
            </div>
            <div class="divide-y divide-amber-200/70 dark:divide-amber-800/70">
                @foreach($procurement['deadline_projects'] as $project)
                    @php
                        $days = (int) now()->startOfDay()->diffInDays($project->delivery_deadline, false);
                    @endphp
                    <div class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('projects.show', $project) }}" class="text-sm font-medium text-slate-800 dark:text-slate-100 hover:underline">{{ $project->project_name }}</a>
                            <p class="text-xs text-slate-600 dark:text-slate-400 truncate">
                                {{ $project->customer?->name }} · {{ __('Delivery Deadline') }} {{ $project->delivery_deadline?->format('d M Y') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $days < 0 ? 'bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200' }}">
                                {{ $days < 0 ? __('Overdue by :days days', ['days' => abs($days)]) : __(':days days left', ['days' => $days]) }}
                            </span>
                            @can('manage-admin')
                                <x-icon-button as="a" icon="add" href="{{ route('admin.goods-deliveries.create', ['project_id' => $project->id]) }}" title="{{ __('Create Delivery') }}" />
                            @endcan
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
        {{-- Quotation deal siap ditarik ke Supplier PO --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-600 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-semibold text-slate-800 dark:text-slate-100">{{ __('Quotation Deals Ready for Supplier PO') }} ({{ $kpi['quotation_deals_ready_count'] ?? 0 }})</h3>
                <a href="{{ route('admin.purchasing.index') }}" class="text-xs text-accent-600 dark:text-accent-400 hover:underline">{{ __('View all') }}</a>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($procurement['quotation_deals'] as $proposal)
                    <div class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('admin.purchasing.show', $proposal) }}" class="font-mono text-sm font-semibold text-accent-600 dark:text-accent-400 hover:underline">{{ $proposal->proposal_number }}</a>
                            <p class="text-xs text-slate-500 truncate">
                                {{ $proposal->lead?->customer?->name ?? __('No project') }}@if($proposal->project?->project_name) · {{ $proposal->project->project_name }}@endif
                            </p>
                        </div>
                        @can('manage-admin')
                            <x-icon-button as="a" icon="add" href="{{ route('admin.supplier-pos.create', ['proposal_id' => $proposal->id]) }}" title="{{ __('Create Supplier PO') }}" />
                        @endcan
                    </div>
                @empty
                    <p class="px-5 py-6 text-sm text-slate-400">{{ __('No quotation deals waiting for supplier PO.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Supplier PO outstanding --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-600 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-semibold text-slate-800 dark:text-slate-100">{{ __('Supplier PO Awaiting Delivery') }}</h3>
                <a href="{{ route('admin.supplier-pos.index') }}" class="text-xs text-accent-600 dark:text-accent-400 hover:underline">{{ __('View all') }}</a>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($procurement['supplier_pos'] as $order)
                    <div class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('admin.supplier-pos.show', $order) }}" class="font-mono text-sm font-semibold text-accent-600 dark:text-accent-400 hover:underline">{{ $order->po_number }}</a>
                            <p class="text-xs text-slate-500 truncate">{{ $order->supplier?->name }} · {{ $order->project?->project_name ?? __('No project') }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold {{ \App\Models\SupplierPurchaseOrder::statusBadgeClasses($order->status) }}">{{ \App\Models\SupplierPurchaseOrder::statusLabel($order->status) }}</span>
                            @can('manage-admin')
                                <x-icon-button as="a" icon="add" href="{{ route('admin.goods-receipts.create', ['supplier_purchase_order_id' => $order->id]) }}" title="{{ __('Receive Goods') }}" />
                            @endcan
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-6 text-sm text-slate-400">{{ __('No outstanding Supplier POs.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Unbilled receipts --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-600 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-semibold text-slate-800 dark:text-slate-100">{{ __('Verified Receipts Not Yet Invoiced') }}</h3>
                <a href="{{ route('admin.purchasing.reports') }}" class="text-xs text-accent-600 dark:text-accent-400 hover:underline">{{ __('View all') }}</a>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($procurement['unbilled_receipts'] as $receipt)
                    <div class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('admin.goods-receipts.show', $receipt) }}" class="font-mono text-sm font-semibold text-accent-600 dark:text-accent-400 hover:underline">{{ $receipt->receipt_number }}</a>
                            <p class="text-xs text-slate-500 truncate">{{ $receipt->purchaseOrder?->supplier?->name }} · {{ $receipt->received_date?->format('d M Y') }}</p>
                        </div>
                        @can('manage-admin')
                            <a href="{{ route('admin.supplier-invoices.create', ['supplier_purchase_order_id' => $receipt->supplier_purchase_order_id, 'goods_receipt_id' => $receipt->id]) }}" class="shrink-0 text-xs text-accent-600 dark:text-accent-400 hover:underline">{{ __('Invoice') }}</a>
                        @endcan
                    </div>
                @empty
                    <p class="px-5 py-6 text-sm text-slate-400">{{ __('All verified receipts have been invoiced.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Supplier invoices outstanding --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-600 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-semibold text-slate-800 dark:text-slate-100">{{ __('Supplier Invoices Outstanding') }}</h3>
                <a href="{{ route('admin.supplier-invoices.index') }}" class="text-xs text-accent-600 dark:text-accent-400 hover:underline">{{ __('View all') }}</a>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($procurement['supplier_invoices'] as $invoice)
                    <div class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('admin.supplier-invoices.show', $invoice) }}" class="font-mono text-sm font-semibold text-accent-600 dark:text-accent-400 hover:underline">{{ $invoice->internal_ref_number }}</a>
                            <p class="text-xs text-slate-500 truncate">{{ $invoice->supplier?->name }} · {{ __('Due') }} {{ $invoice->due_date?->format('d M Y') ?? __('Not filled') }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <div class="text-right">
                                <p class="font-mono text-sm text-slate-800 dark:text-slate-100">Rp {{ number_format($invoice->remainingAmount(), 0, ',', '.') }}</p>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold {{ \App\Models\SupplierInvoice::statusBadgeClasses($invoice->status) }}">{{ \App\Models\SupplierInvoice::statusLabel($invoice->status) }}</span>
                            </div>
                            @can('manage-admin')
                                <x-icon-button as="a" icon="add" href="{{ route('admin.supplier-invoices.show', $invoice) }}#payment-form" title="{{ __('Record Payment') }}" />
                            @endcan
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-6 text-sm text-slate-400">{{ __('No outstanding supplier invoices.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Projects waiting BAST --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-600 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-semibold text-slate-800 dark:text-slate-100">{{ __('Projects Waiting BAST') }}</h3>
                <a href="{{ route('admin.bast.index') }}" class="text-xs text-accent-600 dark:text-accent-400 hover:underline">{{ __('View all') }}</a>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($procurement['bast_projects'] as $project)
                    <div class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('projects.show', $project) }}" class="text-sm font-medium text-slate-800 dark:text-slate-100 hover:underline">{{ $project->project_name }}</a>
                            <p class="text-xs text-slate-500 truncate">
                                {{ $project->customer?->name }}@if($project->proposal?->proposal_number) · {{ $project->proposal->proposal_number }}@endif
                            </p>
                        </div>
                        @can('manage-admin')
                            <div class="flex items-center gap-2 shrink-0">
                                <x-icon-button as="a" icon="add" href="{{ route('admin.invoices.create', ['project_id' => $project->id]) }}" title="{{ __('Create Invoice') }}" />
                                <x-icon-button as="a" icon="edit" href="{{ route('admin.bast.edit', $project) }}" title="{{ __('Input BAST') }}" />
                            </div>
                        @endcan
                    </div>
                @empty
                    <p class="px-5 py-6 text-sm text-slate-400">{{ __('No projects waiting BAST.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Receipts waiting verification --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-600 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-semibold text-slate-800 dark:text-slate-100">{{ __('Receipts Waiting Verification') }} ({{ $kpi['pending_receipts_count'] ?? 0 }})</h3>
                <a href="{{ route('admin.goods-receipts.index') }}" class="text-xs text-accent-600 dark:text-accent-400 hover:underline">{{ __('View all') }}</a>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($procurement['pending_receipts'] as $receipt)
                    <div class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('admin.goods-receipts.show', $receipt) }}" class="font-mono text-sm font-semibold text-accent-600 dark:text-accent-400 hover:underline">{{ $receipt->receipt_number }}</a>
                            <p class="text-xs text-slate-500 truncate">{{ $receipt->purchaseOrder?->supplier?->name }} · {{ $receipt->received_date?->format('d M Y') }}</p>
                        </div>
                        <x-icon-button as="a" icon="view" href="{{ route('admin.goods-receipts.show', $receipt) }}" title="{{ __('View receipt') }}" />
                    </div>
                @empty
                    <p class="px-5 py-6 text-sm text-slate-400">{{ __('No receipts waiting verification.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Received goods not fully delivered --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-600 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-semibold text-slate-800 dark:text-slate-100">{{ __('Received Goods Not Fully Delivered') }} ({{ $kpi['outstanding_deliveries_count'] ?? 0 }})</h3>
                <a href="{{ route('admin.goods-deliveries.index') }}" class="text-xs text-accent-600 dark:text-accent-400 hover:underline">{{ __('View all') }}</a>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($procurement['outstanding_delivery_items'] as $item)
                    <div class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate">{{ $item->item_name }}</p>
                            <p class="text-xs text-slate-500 truncate">
                                {{ $item->purchaseOrder?->po_number }} ·
                                {{ $item->purchaseOrder?->project?->project_name ?? __('No project') }} ·
                                {{ __('Remaining') }} {{ number_format((float) $item->quantity_received - (float) $item->quantity_delivered, 2) }} {{ $item->unit }}
                            </p>
                        </div>
                        @can('manage-admin')
                            <x-icon-button as="a" icon="add" href="{{ route('admin.goods-deliveries.create', ['project_id' => $item->purchaseOrder?->project_id]) }}" title="{{ __('Create Delivery') }}" />
                        @endcan
                    </div>
                @empty
                    <p class="px-5 py-6 text-sm text-slate-400">{{ __('All received goods have been delivered.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Projects ready to close --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-600 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-semibold text-slate-800 dark:text-slate-100">{{ __('Projects Ready to Close') }} ({{ $kpi['ready_to_close_count'] ?? 0 }})</h3>
                <a href="{{ route('admin.bast.index') }}" class="text-xs text-accent-600 dark:text-accent-400 hover:underline">{{ __('View all') }}</a>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($procurement['ready_to_close_projects'] as $project)
                    <div class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('projects.show', $project) }}" class="text-sm font-medium text-slate-800 dark:text-slate-100 hover:underline">{{ $project->project_name }}</a>
                            <p class="text-xs text-slate-500 truncate">{{ $project->customer?->name }}</p>
                        </div>
                        <x-icon-button as="a" icon="view" href="{{ route('projects.show', $project) }}" title="{{ __('View project') }}" />
                    </div>
                @empty
                    <p class="px-5 py-6 text-sm text-slate-400">{{ __('No projects are ready to close.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
