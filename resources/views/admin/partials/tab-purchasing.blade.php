<div class="space-y-5">
    {{-- Panel 1: Antrean Request Harga Teknisi (Read-Only Connector) --}}
    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-700/60">
            <h3 class="text-base font-semibold text-slate-700 dark:text-slate-200">
                {{ __('Antrean Request Harga Teknisi') }}
            </h3>
            <a href="{{ route('teknisi.request-hargas.index') }}" class="text-xs font-semibold text-accent-600 dark:text-accent-400 hover:underline">
                {{ __('Lihat Semua Request Harga') }} →
            </a>
        </div>

        <div class="min-w-0">
            <table class="w-full table-fixed divide-y divide-slate-100 text-sm dark:divide-slate-700">
                <thead class="bg-slate-50/80 dark:bg-slate-900/30">
                    <tr>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Proyek & Klien') }}</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Daftar Barang / Kebutuhan') }}</th>
                        <th class="w-[12%] px-3 py-3.5 text-center text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Status') }}</th>
                        <th class="hidden w-[12%] px-3 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 md:table-cell">{{ __('Tanggal') }}</th>
                        <th class="w-[14%] px-3 py-3.5 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Aksi Cepat') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($purchasing['request_hargas'] as $rh)
                        <tr class="transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-700/35">
                            <td class="px-3 py-3.5">
                                <div class="truncate font-semibold text-slate-800 dark:text-white"
                                     title="{{ $rh->project?->project_name ?? __('Proyek Khusus') }}">
                                    {{ $rh->project?->project_name ?? __('Proyek Khusus') }}
                                </div>
                                <div class="truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{ $rh->project?->customer?->name ?? __('Tanpa Klien') }}
                                    @if($rh->project?->customer?->pt_group)
                                        <span class="ml-1 inline-block rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                            {{ $rh->project->customer->pt_group }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-3.5">
                                @if(is_array($rh->items) && count($rh->items) > 0)
                                    <div class="space-y-1">
                                        @foreach(array_slice($rh->items, 0, 3) as $it)
                                            <div class="truncate text-xs text-slate-700 dark:text-slate-300">
                                                • <span class="font-medium tabular-nums">{{ $it['quantity'] ?? 1 }}x</span> {{ $it['device'] ?? '-' }}
                                                @if(!empty($it['specification']))
                                                    <span class="text-xs text-slate-400 dark:text-slate-500">({{ $it['specification'] }})</span>
                                                @endif
                                            </div>
                                        @endforeach
                                        @if(count($rh->items) > 3)
                                            <div class="truncate text-xs italic text-slate-400 dark:text-slate-500">
                                                +{{ count($rh->items) - 3 }} {{ __('item lainnya') }}
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-xs italic text-slate-400">{{ __('Tidak ada rincian item') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5 text-center">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                    @if($rh->status === 'completed') bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300
                                    @elseif($rh->status === 'approved') bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300
                                    @elseif($rh->status === 'rejected') bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300
                                    @elseif($rh->status === 'submitted') bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300
                                    @else bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 @endif">
                                    {{ ucfirst((string) $rh->status) }}
                                </span>
                            </td>
                            <td class="hidden px-3 py-3.5 text-xs text-slate-500 dark:text-slate-400 md:table-cell">
                                <span>{{ $rh->created_at ? $rh->created_at->format('d M Y') : '-' }}</span>
                            </td>
                            <td class="px-3 py-3.5 text-right">
                                <div class="flex flex-wrap items-center justify-end gap-1.5">
                                    <x-icon-button as="a" icon="view" href="{{ route('teknisi.request-hargas.show', $rh) }}" title="{{ __('Lihat Detail Request') }}" />
                                    @can('manage-admin')
                                        <x-icon-button as="a" icon="add" href="{{ route('admin.supplier-pos.create', ['project_id' => $rh->project_id]) }}" title="{{ __('Buat PO dari Request Ini') }}" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-sm italic text-slate-400 dark:text-slate-500">
                                {{ __('Tidak ada antrean request harga teknisi saat ini.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Panel 2: Supplier Purchase Orders (PO) Terbaru --}}
    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-700/60">
            <h3 class="text-base font-semibold text-slate-700 dark:text-slate-200">
                {{ __('Supplier Purchase Orders (PO) Terbaru') }}
            </h3>
            <div class="flex items-center gap-2">
                @can('manage-admin')
                    <x-icon-button as="a" icon="add" href="{{ route('admin.supplier-pos.create') }}" title="{{ __('Buat Supplier PO Baru') }}" />
                @endcan
                <a href="{{ route('admin.supplier-pos.index') }}" class="text-xs font-semibold text-accent-600 dark:text-accent-400 hover:underline">
                    {{ __('Lihat Semua Supplier PO') }} →
                </a>
            </div>
        </div>

        <div class="min-w-0">
            <table class="w-full table-fixed divide-y divide-slate-100 text-sm dark:divide-slate-700">
                <thead class="bg-slate-50/80 dark:bg-slate-900/30">
                    <tr>
                        <th class="w-[18%] px-3 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Nomor PO') }}</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Supplier & Proyek') }}</th>
                        <th class="w-[16%] px-3 py-3.5 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Total Nilai (Rp)') }}</th>
                        <th class="w-[14%] px-3 py-3.5 text-center text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Status') }}</th>
                        <th class="w-[14%] px-3 py-3.5 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($purchasing['supplier_pos'] ?? $purchasing['purchase_orders'] as $po)
                        <tr class="transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-700/35">
                            <td class="px-3 py-3.5">
                                <div class="truncate font-semibold tabular-nums text-slate-800 dark:text-white" title="{{ $po->po_number }}">
                                    {{ $po->po_number }}
                                </div>
                                <div class="text-xs font-normal text-slate-400">
                                    {{ $po->issue_date ? $po->issue_date->format('d M Y') : '-' }}
                                </div>
                            </td>
                            <td class="px-3 py-3.5">
                                <div class="truncate font-medium text-slate-800 dark:text-white"
                                     title="{{ $po->supplier?->name ?? $po->customer?->name ?? __('Tanpa Pemasok') }}">
                                    {{ $po->supplier?->name ?? $po->customer?->name ?? __('Tanpa Pemasok') }}
                                </div>
                                <div class="truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{ $po->project?->project_name ?? __('Tanpa Proyek') }}
                                    @if(isset($po->items_count))
                                        · {{ $po->items_count }} {{ __('item pengadaan') }}
                                    @endif
                                </div>
                            </td>
                            <td class="break-words px-3 py-3.5 text-right font-medium tabular-nums text-slate-800 dark:text-white">
                                Rp {{ number_format($po->grand_total ?? $po->amount ?? 0, 0, ',', '.') }}
                            </td>
                            <td class="px-3 py-3.5 text-center">
                                @if($po instanceof \App\Models\SupplierPurchaseOrder)
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ \App\Models\SupplierPurchaseOrder::statusBadgeClasses($po->status) }}">
                                        {{ \App\Models\SupplierPurchaseOrder::statusLabel($po->status) }}
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                        @if($po->status === 'selesai') bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300
                                        @elseif($po->status === 'diproses') bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300
                                        @elseif($po->status === 'dibatalkan') bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300
                                        @else bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 @endif">
                                        {{ method_exists($po, 'statusLabel') ? $po->statusLabel() : ucfirst((string) $po->status) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5 text-right">
                                <div class="flex flex-wrap items-center justify-end gap-1.5">
                                    @if($po instanceof \App\Models\SupplierPurchaseOrder)
                                        <x-icon-button as="a" icon="view" href="{{ route('admin.supplier-pos.show', $po) }}" title="{{ __('Lihat Detail PO') }}" />
                                    @else
                                        <x-icon-button as="a" icon="view" href="{{ route('admin.pos.show', $po) }}" title="{{ __('Lihat Detail PO') }}" />
                                        @can('manage-admin')
                                            <x-icon-button as="a" icon="edit" href="{{ route('admin.pos.edit', $po) }}" title="{{ __('Edit PO') }}" />
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-sm italic text-slate-400 dark:text-slate-500">
                                {{ __('Belum ada supplier purchase order yang diterbitkan.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
