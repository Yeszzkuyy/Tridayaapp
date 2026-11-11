<div class="space-y-5">
    {{-- Aksi cepat --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            @can('manage-admin')
                <x-icon-button as="a" icon="add" href="{{ route('admin.goods-receipts.create') }}" label="{{ __('Receive Goods') }}" title="{{ __('Receive Goods') }}" />
                <x-icon-button as="a" icon="add" href="{{ route('admin.supplier-invoices.create') }}" label="{{ __('Record Supplier Invoice') }}" title="{{ __('Record Supplier Invoice') }}" />
            @endcan
            <x-icon-button as="a" icon="view" href="{{ route('admin.purchasing.reports') }}" label="{{ __('Procurement Reports') }}" title="{{ __('Procurement Reports') }}" />
            <x-icon-button as="a" icon="view" href="{{ route('admin.bast.index') }}" label="{{ __('BAST') }}" title="{{ __('BAST') }}" />
        </div>
    </div>

    {{-- Anti-denda: tenggat pengiriman <= 7 hari / sudah lewat --}}
    @if(($kpi['deadline_risk_count'] ?? 0) > 0)
        <div class="overflow-hidden rounded-2xl border border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/40">
            <div class="flex items-center justify-between gap-3 border-b border-amber-200 px-4 py-3 dark:border-amber-800">
                <div>
                    <h3 class="flex items-center gap-2 font-semibold text-amber-800 dark:text-amber-200">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                        {{ __('Delivery Deadline Risk') }} ({{ $kpi['deadline_risk_count'] }})
                    </h3>
                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">{{ __('Projects approaching or past the delivery deadline with goods not fully delivered.') }}</p>
                </div>
            </div>
            <div class="divide-y divide-amber-200/70 dark:divide-amber-800/70">
                @foreach($procurement['deadline_projects'] as $project)
                    @php
                        $days = (int) now()->startOfDay()->diffInDays($project->delivery_deadline, false);
                    @endphp
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <a href="{{ route('projects.show', $project) }}" class="text-sm font-medium text-slate-800 hover:underline dark:text-slate-100">{{ $project->project_name }}</a>
                            <p class="truncate text-xs text-slate-600 dark:text-slate-400">
                                {{ $project->customer?->name }} · {{ __('Delivery Deadline') }} {{ $project->delivery_deadline?->format('d M Y') }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $days < 0 ? 'bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200' }}">
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

    {{-- Tiga tahap pengadaan: pesan, terima/kirim, tagihan/tutup --}}
    <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
        {{-- Tahap 1: Pesan ke Supplier --}}
        <section class="space-y-4">
            <div class="flex items-center gap-2 px-1">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-500/10 text-xs font-bold text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300">1</span>
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ __('Order to Supplier') }}</h2>
            </div>

            <x-queue-card :title="__('Quotation Deals Ready for Supplier PO')" count="{{ $kpi['quotation_deals_ready_count'] ?? 0 }}" :href="route('admin.purchasing.index')">
                @forelse(collect($procurement['quotation_deals'])->take(3) as $proposal)
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <a href="{{ route('admin.purchasing.show', $proposal) }}" class="block truncate text-sm font-semibold tabular-nums text-accent-600 hover:underline dark:text-accent-400">{{ $proposal->proposal_number }}</a>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                {{ $proposal->lead?->customer?->name ?? __('No project') }}@if($proposal->project?->project_name) · {{ $proposal->project->project_name }}@endif
                            </p>
                        </div>
                        @can('manage-admin')
                            <x-icon-button as="a" icon="add" href="{{ route('admin.supplier-pos.create', ['proposal_id' => $proposal->id]) }}" title="{{ __('Create Supplier PO') }}" />
                        @endcan
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-xs italic text-slate-400 dark:text-slate-500">{{ __('No quotation deals waiting for supplier PO.') }}</p>
                @endforelse
            </x-queue-card>

            <x-queue-card :title="__('Supplier PO Awaiting Delivery')" count="{{ $kpi['supplier_pos_outstanding_count'] ?? 0 }}" :href="route('admin.supplier-pos.index')">
                @forelse(collect($procurement['supplier_pos'])->take(3) as $order)
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <a href="{{ route('admin.supplier-pos.show', $order) }}" class="block truncate text-sm font-semibold tabular-nums text-accent-600 hover:underline dark:text-accent-400">{{ $order->po_number }}</a>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $order->supplier?->name }} · {{ $order->project?->project_name ?? __('No project') }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ \App\Models\SupplierPurchaseOrder::statusBadgeClasses($order->status) }}">{{ \App\Models\SupplierPurchaseOrder::statusLabel($order->status) }}</span>
                            @can('manage-admin')
                                <x-icon-button as="a" icon="add" href="{{ route('admin.goods-receipts.create', ['supplier_purchase_order_id' => $order->id]) }}" title="{{ __('Receive Goods') }}" />
                            @endcan
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-xs italic text-slate-400 dark:text-slate-500">{{ __('No outstanding Supplier POs.') }}</p>
                @endforelse
            </x-queue-card>
        </section>

        {{-- Tahap 2: Terima & Kirim --}}
        <section class="space-y-4">
            <div class="flex items-center gap-2 px-1">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-500/10 text-xs font-bold text-amber-600 dark:bg-amber-500/15 dark:text-amber-300">2</span>
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ __('Receive & Deliver') }}</h2>
            </div>

            <x-queue-card :title="__('Receipts Waiting Verification')" count="{{ $kpi['pending_receipts_count'] ?? 0 }}" :href="route('admin.goods-receipts.index')">
                @forelse(collect($procurement['pending_receipts'])->take(3) as $receipt)
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <a href="{{ route('admin.goods-receipts.show', $receipt) }}" class="block truncate text-sm font-semibold tabular-nums text-accent-600 hover:underline dark:text-accent-400">{{ $receipt->receipt_number }}</a>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $receipt->purchaseOrder?->supplier?->name }} · {{ $receipt->received_date?->format('d M Y') }}</p>
                        </div>
                        <x-icon-button as="a" icon="view" href="{{ route('admin.goods-receipts.show', $receipt) }}" title="{{ __('View receipt') }}" />
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-xs italic text-slate-400 dark:text-slate-500">{{ __('No receipts waiting verification.') }}</p>
                @endforelse
            </x-queue-card>

            <x-queue-card :title="__('Received Goods Not Fully Delivered')" count="{{ $kpi['outstanding_deliveries_count'] ?? 0 }}" :href="route('admin.goods-deliveries.index')">
                @forelse(collect($procurement['outstanding_delivery_items'])->take(3) as $item)
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $item->item_name }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">
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
                    <p class="px-4 py-6 text-center text-xs italic text-slate-400 dark:text-slate-500">{{ __('All received goods have been delivered.') }}</p>
                @endforelse
            </x-queue-card>
        </section>

        {{-- Tahap 3: Tagihan & Tutup --}}
        <section class="space-y-4">
            <div class="flex items-center gap-2 px-1">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500/10 text-xs font-bold text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300">3</span>
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ __('Invoice & Close') }}</h2>
            </div>

            <x-queue-card :title="__('Verified Receipts Not Yet Invoiced')" count="{{ $kpi['unbilled_receipts_count'] ?? 0 }}" :href="route('admin.purchasing.reports')">
                @forelse(collect($procurement['unbilled_receipts'])->take(3) as $receipt)
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <a href="{{ route('admin.goods-receipts.show', $receipt) }}" class="block truncate text-sm font-semibold tabular-nums text-accent-600 hover:underline dark:text-accent-400">{{ $receipt->receipt_number }}</a>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $receipt->purchaseOrder?->supplier?->name }} · {{ $receipt->received_date?->format('d M Y') }}</p>
                        </div>
                        @can('manage-admin')
                            <a href="{{ route('admin.supplier-invoices.create', ['supplier_purchase_order_id' => $receipt->supplier_purchase_order_id, 'goods_receipt_id' => $receipt->id]) }}" class="shrink-0 text-xs font-semibold text-accent-600 hover:underline dark:text-accent-400">{{ __('Invoice') }}</a>
                        @endcan
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-xs italic text-slate-400 dark:text-slate-500">{{ __('All verified receipts have been invoiced.') }}</p>
                @endforelse
            </x-queue-card>

            <x-queue-card :title="__('Supplier Invoices Outstanding')" count="{{ $kpi['supplier_invoices_outstanding_count'] ?? 0 }}" :href="route('admin.supplier-invoices.index')">
                @forelse(collect($procurement['supplier_invoices'])->take(3) as $invoice)
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <a href="{{ route('admin.supplier-invoices.show', $invoice) }}" class="block truncate text-sm font-semibold tabular-nums text-accent-600 hover:underline dark:text-accent-400">{{ $invoice->internal_ref_number }}</a>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $invoice->supplier?->name }} · {{ __('Due') }} {{ $invoice->due_date?->format('d M Y') ?? __('Not filled') }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <div class="text-right">
                                <p class="text-sm tabular-nums text-slate-800 dark:text-slate-100">Rp {{ number_format($invoice->remainingAmount(), 0, ',', '.') }}</p>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ \App\Models\SupplierInvoice::statusBadgeClasses($invoice->status) }}">{{ \App\Models\SupplierInvoice::statusLabel($invoice->status) }}</span>
                            </div>
                            @can('manage-admin')
                                <x-icon-button as="a" icon="add" href="{{ route('admin.supplier-invoices.show', $invoice) }}#payment-form" title="{{ __('Record Payment') }}" />
                            @endcan
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-xs italic text-slate-400 dark:text-slate-500">{{ __('No outstanding supplier invoices.') }}</p>
                @endforelse
            </x-queue-card>

            <x-queue-card :title="__('Projects Waiting BAST')" count="{{ $kpi['bast_pending_count'] ?? 0 }}" :href="route('admin.bast.index')">
                @forelse(collect($procurement['bast_projects'])->take(3) as $project)
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <a href="{{ route('projects.show', $project) }}" class="block truncate text-sm font-medium text-slate-800 hover:underline dark:text-slate-100">{{ $project->project_name }}</a>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                {{ $project->customer?->name }}@if($project->proposal?->proposal_number) · {{ $project->proposal->proposal_number }}@endif
                            </p>
                        </div>
                        @can('manage-admin')
                            <div class="flex shrink-0 items-center gap-2">
                                <x-icon-button as="a" icon="add" href="{{ route('admin.invoices.create', ['project_id' => $project->id]) }}" title="{{ __('Create Invoice') }}" />
                                <x-icon-button as="a" icon="edit" href="{{ route('admin.bast.edit', $project) }}" title="{{ __('Input BAST') }}" />
                            </div>
                        @endcan
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-xs italic text-slate-400 dark:text-slate-500">{{ __('No projects waiting BAST.') }}</p>
                @endforelse
            </x-queue-card>

            <x-queue-card :title="__('Projects Ready to Close')" count="{{ $kpi['ready_to_close_count'] ?? 0 }}" :href="route('admin.bast.index')">
                @forelse(collect($procurement['ready_to_close_projects'])->take(3) as $project)
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <a href="{{ route('projects.show', $project) }}" class="block truncate text-sm font-medium text-slate-800 hover:underline dark:text-slate-100">{{ $project->project_name }}</a>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $project->customer?->name }}</p>
                        </div>
                        <x-icon-button as="a" icon="view" href="{{ route('projects.show', $project) }}" title="{{ __('View project') }}" />
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-xs italic text-slate-400 dark:text-slate-500">{{ __('No projects are ready to close.') }}</p>
                @endforelse
            </x-queue-card>
        </section>
    </div>
</div>
