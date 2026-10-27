<div class="space-y-8">
    {{-- Panel 1: Proyek / Proposal Deal Siap Ditagih (Won Deals) --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    {{ __('Proyek / Deal Siap Ditagih') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Proyek hasil konversi Won Deal Sales yang belum memiliki invoice penagihan.') }}
                </p>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">
                {{ count($finance['ready_projects']) }} {{ __('perlu ditagih') }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-700 text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Nama Proyek') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Klien & PT') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Ref. Proposal') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Nilai Kontrak (Rp)') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Aksi Cepat') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($finance['ready_projects'] as $p)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800 dark:text-white">
                                    {{ $p->project_name }}
                                </div>
                                <div class="text-[11px] text-slate-400">
                                    {{ $p->project_code ?? '-' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800 dark:text-white">
                                    {{ $p->customer?->name ?? __('Tanpa Klien') }}
                                </div>
                                @if($p->customer?->pt_group)
                                    <span class="inline-block px-1.5 py-0.2 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 mt-0.5">
                                        PT {{ $p->customer->pt_group }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">
                                @if($p->proposal)
                                    <span class="font-semibold text-accent-600 dark:text-accent-400">{{ $p->proposal->proposal_number }}</span>
                                @else
                                    <span class="italic text-slate-400">{{ __('Tanpa Proposal') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-medium text-slate-800 dark:text-white">
                                @if($p->proposal && $p->proposal->grand_total > 0)
                                    Rp {{ number_format($p->proposal->grand_total, 0, ',', '.') }}
                                @else
                                    <span class="italic text-slate-400 text-xs">{{ __('Belum ditetapkan') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    @can('manage-admin')
                                        <x-icon-button as="a" icon="add"
                                            href="{{ route('admin.invoices.create', [
                                                'project_id' => $p->id,
                                                'customer_id' => $p->customer_id,
                                                'amount' => $p->proposal?->grand_total
                                            ]) }}"
                                            title="{{ __('Buat Invoice untuk Proyek Ini') }}"
                                            label="{{ __('Buat Invoice') }}" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-400 dark:text-slate-500 text-sm italic">
                                {{ __('Semua proyek deal yang ada sudah memiliki invoice penagihan.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Panel 2: Tagihan (Invoices) Terbaru & Jatuh Tempo --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                    {{ __('Daftar Tagihan (Invoices) Terbaru') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Status piutang klien, progress pembayaran, dan peringatan jatuh tempo.') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                @can('manage-admin')
                    <x-icon-button as="a" icon="add" href="{{ route('admin.invoices.create') }}" title="{{ __('Buat Invoice Baru') }}" label="{{ __('Invoice Baru') }}" />
                @endcan
                <a href="{{ route('admin.invoices.index') }}" class="text-xs font-semibold text-accent-600 dark:text-accent-400 hover:underline ml-2">
                    {{ __('Lihat Semua Tagihan') }} →
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-700 text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Nomor Invoice') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Klien & Proyek') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Tagihan vs Terbayar') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Jatuh Tempo') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($finance['invoices'] as $inv)
                        @php
                            $paidAmount = (float) ($inv->payments_sum_amount ?? 0);
                            $remaining = max(0, (float) $inv->amount - $paidAmount);
                            $isOverdue = $inv->status === 'unpaid' && $inv->due_date && $inv->due_date->isPast();
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3 font-mono font-semibold text-slate-800 dark:text-white">
                                {{ $inv->invoice_number }}
                                <div class="text-[11px] font-sans text-slate-400 font-normal">
                                    {{ $inv->issue_date ? $inv->issue_date->format('d M Y') : '-' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800 dark:text-white">
                                    {{ $inv->customer?->name ?? __('Tanpa Klien') }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $inv->project?->project_name ?? __('Tanpa Proyek') }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right font-mono">
                                <div class="font-semibold text-slate-800 dark:text-white">
                                    Rp {{ number_format($inv->amount, 0, ',', '.') }}
                                </div>
                                @if($paidAmount > 0 && $remaining > 0)
                                    <div class="text-xs text-amber-600 dark:text-amber-400">
                                        {{ __('Dibayar:') }} Rp {{ number_format($paidAmount, 0, ',', '.') }}
                                    </div>
                                @elseif($inv->status === 'paid')
                                    <div class="text-xs text-emerald-600 dark:text-emerald-400">
                                        {{ __('Lunas') }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if($inv->due_date)
                                    <div class="{{ $isOverdue ? 'text-red-600 font-semibold' : 'text-slate-600 dark:text-slate-300' }}">
                                        {{ $inv->due_date->format('d M Y') }}
                                    </div>
                                    @if($isOverdue)
                                        <span class="inline-block px-1.5 py-0.2 rounded text-[10px] font-bold bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300">
                                            {{ __('Lewat ') . $inv->due_date->diffForHumans(['parts' => 1]) }}
                                        </span>
                                    @endif
                                @else
                                    <span class="italic text-slate-400 text-xs">{{ __('Tidak ada batas') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full
                                    @if($inv->status === 'paid') bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300
                                    @elseif($inv->status === 'cancelled') bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300
                                    @else bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 @endif">
                                    {{ $inv->status === 'paid' ? __('Paid') : ($inv->status === 'cancelled' ? __('Cancelled') : __('Unpaid')) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <x-icon-button as="a" icon="view" href="{{ route('admin.invoices.show', $inv) }}" title="{{ __('Detail Invoice') }}" />
                                    @can('manage-admin')
                                        @if($inv->status !== 'cancelled' && $remaining > 0)
                                            <x-icon-button as="a" icon="add" href="{{ route('admin.payments.create', ['invoice_id' => $inv->id]) }}" title="{{ __('Catat Pembayaran') }}" />
                                        @endif
                                        <x-icon-button as="a" icon="edit" href="{{ route('admin.invoices.edit', $inv) }}" title="{{ __('Edit Invoice') }}" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400 dark:text-slate-500 text-sm italic">
                                {{ __('Belum ada tagihan invoice yang dicatat.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Panel 3: Riwayat Pembayaran Masuk (Payments) --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-green-500"></span>
                    {{ __('Riwayat Pembayaran Masuk (Payments)') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Penerimaan pembayaran dari klien beserta akses bukti transfer rekening.') }}
                </p>
            </div>
            <a href="{{ route('admin.payments.index') }}" class="text-xs font-semibold text-accent-600 dark:text-accent-400 hover:underline">
                {{ __('Lihat Semua Pembayaran') }} →
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-700 text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Tanggal Bayar') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Invoice & Klien') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Nominal Masuk (Rp)') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Metode') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Bukti Transfer') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($finance['payments'] as $pay)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3 whitespace-nowrap font-mono text-slate-800 dark:text-white">
                                {{ $pay->payment_date ? $pay->payment_date->format('d M Y') : '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800 dark:text-white">
                                    {{ $pay->invoice?->invoice_number ?? '-' }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $pay->invoice?->customer?->name ?? __('Tanpa Klien') }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                Rp {{ number_format($pay->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-center text-xs text-slate-600 dark:text-slate-300">
                                {{ $pay->payment_method ?: '-' }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if($pay->proof_file)
                                    @can('manage-admin')
                                        <a href="{{ route('admin.payments.proof', $pay) }}" target="_blank"
                                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-accent-100 dark:hover:bg-accent-950 transition">
                                            <svg class="h-3.5 w-3.5 text-accent-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                            </svg>
                                            {{ __('Lihat Bukti') }}
                                        </a>
                                    @else
                                        <span class="text-xs text-slate-400 italic">{{ __('Ada Bukti') }}</span>
                                    @endcan
                                @else
                                    <span class="text-xs text-slate-400 italic">{{ __('Tanpa File') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <x-icon-button as="a" icon="view" href="{{ route('admin.payments.show', $pay) }}" title="{{ __('Detail Payment') }}" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400 dark:text-slate-500 text-sm italic">
                                {{ __('Belum ada riwayat pembayaran yang dicatat.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
