{{-- Override lokal: tab finance tim belum bisa ditulis; label partial/overdue diperbaiki di sini. --}}
<div class="space-y-5">
    {{-- Panel 1: Proyek / Proposal Deal Siap Ditagih (Won Deals) --}}
    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-700/60">
            <h3 class="text-base font-semibold text-slate-700 dark:text-slate-200">
                {{ __('Proyek / Deal Siap Ditagih') }}
            </h3>
            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">
                {{ count($finance['ready_projects']) }} {{ __('perlu ditagih') }}
            </span>
        </div>

        <div class="min-w-0">
            <table class="w-full table-fixed divide-y divide-slate-100 text-sm dark:divide-slate-700">
                <thead class="bg-slate-50/80 dark:bg-slate-900/30">
                    <tr>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Nama Proyek') }}</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Klien & PT') }}</th>
                        <th class="hidden w-[16%] px-3 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 md:table-cell">{{ __('Ref. Proposal') }}</th>
                        <th class="w-[18%] px-3 py-3.5 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Nilai Kontrak (Rp)') }}</th>
                        <th class="w-[9%] px-3 py-3.5 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Aksi Cepat') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($finance['ready_projects'] as $p)
                        <tr class="transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-700/35">
                            <td class="px-3 py-3.5">
                                <div class="truncate font-semibold text-slate-800 dark:text-white" title="{{ $p->project_name }}">
                                    {{ $p->project_name }}
                                </div>
                                <div class="truncate text-xs text-slate-400">
                                    {{ $p->project_code ?? '-' }}
                                </div>
                            </td>
                            <td class="px-3 py-3.5">
                                <div class="truncate font-medium text-slate-800 dark:text-white" title="{{ $p->customer?->name ?? __('Tanpa Klien') }}">
                                    {{ $p->customer?->name ?? __('Tanpa Klien') }}
                                </div>
                                @if($p->customer?->pt_group)
                                    <span class="mt-0.5 inline-block rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                        PT {{ $p->customer->pt_group }}
                                    </span>
                                @endif
                            </td>
                            <td class="hidden truncate px-3 py-3.5 text-xs tabular-nums text-slate-600 dark:text-slate-300 md:table-cell">
                                @if($p->proposal)
                                    <span class="font-semibold text-accent-600 dark:text-accent-400">{{ $p->proposal->proposal_number }}</span>
                                @else
                                    <span class="italic text-slate-400">{{ __('Tanpa Proposal') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5 text-right font-medium tabular-nums text-slate-800 dark:text-white">
                                @if($p->proposal && $p->proposal->grand_total > 0)
                                    <span class="break-words">Rp {{ number_format($p->proposal->grand_total, 0, ',', '.') }}</span>
                                @else
                                    <span class="text-xs italic text-slate-400">{{ __('Belum ditetapkan') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5 text-right">
                                <div class="flex flex-wrap items-center justify-end gap-1.5">
                                    @can('manage-admin')
                                        <x-icon-button as="a" icon="add"
                                            href="{{ route('admin.invoices.create', [
                                                'project_id' => $p->id,
                                                'customer_id' => $p->customer_id,
                                                'amount' => $p->proposal?->grand_total
                                            ]) }}"
                                            title="{{ __('Buat Invoice untuk Proyek Ini') }}" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-sm italic text-slate-400 dark:text-slate-500">
                                {{ __('Semua proyek deal yang ada sudah memiliki invoice penagihan.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Panel 2: Tagihan (Invoices) Terbaru & Jatuh Tempo --}}
    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-700/60">
            <h3 class="text-base font-semibold text-slate-700 dark:text-slate-200">
                {{ __('Daftar Tagihan (Invoices) Terbaru') }}
            </h3>
            <div class="flex items-center gap-2">
                @can('manage-admin')
                    <x-icon-button as="a" icon="add" href="{{ route('admin.invoices.create') }}" title="{{ __('Buat Invoice Baru') }}" />
                @endcan
                <a href="{{ route('admin.invoices.index') }}" class="text-xs font-semibold text-accent-600 dark:text-accent-400 hover:underline">
                    {{ __('Lihat Semua Tagihan') }} →
                </a>
            </div>
        </div>

        <div class="min-w-0">
            <table class="w-full table-fixed divide-y divide-slate-100 text-sm dark:divide-slate-700">
                <thead class="bg-slate-50/80 dark:bg-slate-900/30">
                    <tr>
                        <th class="w-[22%] px-3 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Nomor Invoice') }}</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Klien & Proyek') }}</th>
                        <th class="w-[22%] px-3 py-3.5 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Tagihan vs Terbayar') }}</th>
                        <th class="w-[14%] px-3 py-3.5 text-center text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Status') }}</th>
                        <th class="w-[16%] px-3 py-3.5 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($finance['invoices'] as $inv)
                        @php
                            $paidAmount = (float) ($inv->payments_sum_amount ?? 0);
                            $remaining = max(0, (float) $inv->amount - $paidAmount);
                            $isOverdue = in_array($inv->status, ['unpaid', 'partial'], true) && $inv->due_date && $inv->due_date->isPast();
                        @endphp
                        <tr class="transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-700/35">
                            <td class="px-3 py-3.5">
                                <div class="truncate font-semibold tabular-nums text-slate-800 dark:text-white" title="{{ $inv->invoice_number }}">
                                    {{ $inv->invoice_number }}
                                </div>
                                <div class="truncate text-xs text-slate-400">
                                    {{ $inv->issue_date ? $inv->issue_date->format('d M Y') : '-' }}
                                    @if($inv->due_date)
                                        · {{ __('Jatuh Tempo') }} {{ $inv->due_date->format('d M Y') }}
                                    @else
                                        · {{ __('Tidak ada batas') }}
                                    @endif
                                </div>
                                @if($isOverdue)
                                    <span class="mt-1 inline-block rounded bg-red-100 px-1.5 py-0.5 text-[11px] font-bold text-red-700 dark:bg-red-950/50 dark:text-red-300">
                                        {{ __('Lewat ') . $inv->due_date->diffForHumans(['parts' => 1]) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5">
                                <div class="truncate font-medium text-slate-800 dark:text-white" title="{{ $inv->customer?->name ?? __('Tanpa Klien') }}">
                                    {{ $inv->customer?->name ?? __('Tanpa Klien') }}
                                </div>
                                <div class="truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{ $inv->project?->project_name ?? __('Tanpa Proyek') }}
                                </div>
                            </td>
                            <td class="px-3 py-3.5 text-right tabular-nums">
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
                            <td class="px-3 py-3.5 text-center">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ \App\Models\Invoice::statusBadgeClasses($inv->status) }}">
                                    {{ \App\Models\Invoice::statusLabel($inv->status) }}
                                </span>
                            </td>
                            <td class="px-3 py-3.5 text-right">
                                <div class="flex flex-wrap items-center justify-end gap-1.5">
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
                            <td colspan="5" class="py-10 text-center text-sm italic text-slate-400 dark:text-slate-500">
                                {{ __('Belum ada tagihan invoice yang dicatat.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Panel 3: Riwayat Pembayaran Masuk (Payments) --}}
    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-700/60">
            <h3 class="text-base font-semibold text-slate-700 dark:text-slate-200">
                {{ __('Riwayat Pembayaran Masuk (Payments)') }}
            </h3>
            <a href="{{ route('admin.payments.index') }}" class="text-xs font-semibold text-accent-600 dark:text-accent-400 hover:underline">
                {{ __('Lihat Semua Pembayaran') }} →
            </a>
        </div>

        <div class="min-w-0">
            <table class="w-full table-fixed divide-y divide-slate-100 text-sm dark:divide-slate-700">
                <thead class="bg-slate-50/80 dark:bg-slate-900/30">
                    <tr>
                        <th class="w-[16%] px-3 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Tanggal Bayar') }}</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Invoice & Klien') }}</th>
                        <th class="w-[18%] px-3 py-3.5 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Nominal Masuk (Rp)') }}</th>
                        <th class="w-[14%] px-3 py-3.5 text-center text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Bukti Transfer') }}</th>
                        <th class="w-[9%] px-3 py-3.5 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($finance['payments'] as $pay)
                        <tr class="transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-700/35">
                            <td class="px-3 py-3.5 tabular-nums text-slate-800 dark:text-white">
                                <div class="whitespace-nowrap">
                                    {{ $pay->payment_date ? $pay->payment_date->format('d M Y') : '-' }}
                                </div>
                                <div class="truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{ $pay->payment_method ?: '-' }}
                                </div>
                            </td>
                            <td class="px-3 py-3.5">
                                <div class="truncate font-semibold tabular-nums text-slate-800 dark:text-white" title="{{ $pay->invoice?->invoice_number ?? '-' }}">
                                    {{ $pay->invoice?->invoice_number ?? '-' }}
                                </div>
                                <div class="truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{ $pay->invoice?->customer?->name ?? __('Tanpa Klien') }}
                                </div>
                            </td>
                            <td class="px-3 py-3.5 text-right font-bold tabular-nums text-emerald-600 dark:text-emerald-400">
                                Rp {{ number_format($pay->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-3 py-3.5 text-center">
                                @if($pay->proof_file)
                                    @can('manage-admin')
                                        <a href="{{ route('admin.payments.proof', $pay) }}" target="_blank"
                                           class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 transition hover:bg-accent-100 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-accent-950">
                                            <svg class="h-3.5 w-3.5 text-accent-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                            </svg>
                                            {{ __('Lihat Bukti') }}
                                        </a>
                                    @else
                                        <span class="text-xs italic text-slate-400">{{ __('Ada Bukti') }}</span>
                                    @endcan
                                @else
                                    <span class="text-xs italic text-slate-400">{{ __('Tanpa File') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5 text-right">
                                <div class="flex flex-wrap items-center justify-end gap-1.5">
                                    <x-icon-button as="a" icon="view" href="{{ route('admin.payments.show', $pay) }}" title="{{ __('Detail Payment') }}" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-sm italic text-slate-400 dark:text-slate-500">
                                {{ __('Belum ada riwayat pembayaran yang dicatat.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
