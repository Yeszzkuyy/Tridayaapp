<div class="space-y-8">
    {{-- Panel 1: Antrean Request Harga Teknisi (Read-Only Connector) --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-sky-500"></span>
                    {{ __('Antrean Request Harga Teknisi') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Kebutuhan pengadaan barang/perangkat dari teknisi untuk diproses menjadi PO ke vendor/supplier.') }}
                </p>
            </div>
            <a href="{{ route('teknisi.request-hargas.index') }}" class="text-xs font-semibold text-accent-600 dark:text-accent-400 hover:underline">
                {{ __('Lihat Semua Request Harga') }} →
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-700 text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Proyek & Klien') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Daftar Barang / Kebutuhan') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Tanggal') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Aksi Cepat') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($purchasing['request_hargas'] as $rh)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800 dark:text-white">
                                    {{ $rh->project?->project_name ?? __('Proyek Khusus') }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $rh->project?->customer?->name ?? __('Tanpa Klien') }}
                                    @if($rh->project?->customer?->pt_group)
                                        <span class="inline-block px-1.5 py-0.2 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 ml-1">
                                            {{ $rh->project->customer->pt_group }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                @if(is_array($rh->items) && count($rh->items) > 0)
                                    <div class="space-y-1">
                                        @foreach(array_slice($rh->items, 0, 3) as $it)
                                            <div class="text-xs text-slate-700 dark:text-slate-300 truncate">
                                                • <span class="font-mono font-medium">{{ $it['quantity'] ?? 1 }}x</span> {{ $it['device'] ?? '-' }}
                                                @if(!empty($it['specification']))
                                                    <span class="text-slate-400 dark:text-slate-500 text-[11px]">({{ $it['specification'] }})</span>
                                                @endif
                                            </div>
                                        @endforeach
                                        @if(count($rh->items) > 3)
                                            <div class="text-[11px] text-slate-400 dark:text-slate-500 italic">
                                                +{{ count($rh->items) - 3 }} {{ __('item lainnya') }}
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <span class="italic text-xs text-slate-400">{{ __('Tidak ada rincian item') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full
                                    @if($rh->status === 'completed') bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300
                                    @elseif($rh->status === 'approved') bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300
                                    @elseif($rh->status === 'rejected') bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300
                                    @elseif($rh->status === 'submitted') bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300
                                    @else bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 @endif">
                                    {{ ucfirst((string) $rh->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                {{ $rh->created_at ? $rh->created_at->format('d M Y') : '-' }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <x-icon-button as="a" icon="view" href="{{ route('teknisi.request-hargas.show', $rh) }}" title="{{ __('Lihat Detail Request') }}" />
                                    @can('manage-admin')
                                        <x-icon-button as="a" icon="add" href="{{ route('admin.pos.create', ['project_id' => $rh->project_id, 'request_harga_id' => $rh->id]) }}" title="{{ __('Buat PO dari Request Ini') }}" label="{{ __('Buat PO') }}" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-400 dark:text-slate-500 text-sm italic">
                                {{ __('Tidak ada antrean request harga teknisi saat ini.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Panel 2: Purchase Orders (PO) Terbaru --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-violet-500"></span>
                    {{ __('Purchase Orders (PO) Terbaru') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Daftar purchase order pengadaan yang telah diterbitkan untuk supplier/vendor.') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                @can('manage-admin')
                    <x-icon-button as="a" icon="add" href="{{ route('admin.pos.create') }}" title="{{ __('Buat PO Baru') }}" label="{{ __('PO Baru') }}" />
                @endcan
                <a href="{{ route('admin.pos.index') }}" class="text-xs font-semibold text-accent-600 dark:text-accent-400 hover:underline ml-2">
                    {{ __('Lihat Semua PO') }} →
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-700 text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Nomor PO') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Klien & Proyek') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Rincian Barang') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Total Nilai (Rp)') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($purchasing['purchase_orders'] as $po)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3 font-mono font-semibold text-slate-800 dark:text-white">
                                {{ $po->po_number }}
                                <div class="text-[11px] font-sans text-slate-400 font-normal">
                                    {{ $po->issue_date ? $po->issue_date->format('d M Y') : '-' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800 dark:text-white">
                                    {{ $po->customer?->name ?? __('Tanpa Klien') }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $po->project?->project_name ?? __('Tanpa Proyek') }}
                                </div>
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                <div class="text-xs text-slate-600 dark:text-slate-300 line-clamp-2">
                                    {{ $po->items }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-medium text-slate-800 dark:text-white">
                                Rp {{ number_format($po->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full
                                    @if($po->status === 'selesai') bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300
                                    @elseif($po->status === 'diproses') bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300
                                    @elseif($po->status === 'dibatalkan') bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300
                                    @else bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 @endif">
                                    {{ $po->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <x-icon-button as="a" icon="view" href="{{ route('admin.pos.show', $po) }}" title="{{ __('Lihat Detail PO') }}" />
                                    @can('manage-admin')
                                        <x-icon-button as="a" icon="edit" href="{{ route('admin.pos.edit', $po) }}" title="{{ __('Edit PO') }}" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400 dark:text-slate-500 text-sm italic">
                                {{ __('Belum ada purchase order yang diterbitkan.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
