{{-- Dokumen Transaksi — tautan ke modul Invoice, PO, Payment.
-- Tidak ada salinan file di sini; semua berkas tetap di modul asal.
Props:
  $custInvoices    Collection invoice customer
  $custPos         Collection purchase order customer
  $custPayments    Collection payment customer
  $typedDocs       Collection dokumen transaksi dari ProjectDocument (groupBy doc_type)
--}}
@props(['custInvoices', 'custPos', 'custPayments', 'typedDocs', 'canUploadDocs' => false])

<div class="space-y-6">
    <div class="border-t border-slate-200 pt-6 dark:border-slate-700">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Transaction Documents') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Dokumen transaksi dikelola di modul asal. Tidak disalin ke Customer Documents.') }}</p>
        </div>

        {{-- Invoice --}}
        <div>
            <div class="flex items-center justify-between mb-2">
                <span class="font-medium text-slate-700 dark:text-slate-200">{{ __('Invoice') }}</span>
                <a href="{{ route('admin.invoices.index') }}"
                   class="text-sm text-accent-600 hover:text-accent-800 dark:text-accent-400 dark:hover:text-accent-300">
                    {{ __('Lihat Semua Invoice') }} →
                </a>
            </div>

            @if($custInvoices->isNotEmpty() || ($typedDocs->get('invoice', collect()))->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($custInvoices as $inv)
                        @php
                            $invBadgeColor = match($inv->status) {
                                'paid' => 'green',
                                'cancelled' => 'red',
                                default => 'yellow',
                            };
                            $invStatusLabel = $inv->status === 'paid' ? __('Paid') : ($inv->status === 'cancelled' ? __('Cancelled') : __('Unpaid'));
                        @endphp
                        <a href="{{ route('admin.invoices.show', $inv) }}" title="{{ $inv->invoice_number }}"
                           class="block overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-900/50">
                            <div class="relative flex h-36 items-center justify-center overflow-hidden bg-slate-100 dark:bg-slate-800">
                                <svg class="h-10 w-10 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span class="absolute left-2 top-2 rounded bg-teal-500 px-1.5 py-0.5 text-[11px] font-bold uppercase text-white">INV</span>
                            </div>
                            <div class="p-3">
                                <p class="flex flex-wrap items-center gap-2 font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    <span class="truncate">{{ $inv->invoice_number }}</span>
                                    <x-status-badge :color="$invBadgeColor">{{ $invStatusLabel }}</x-status-badge>
                                </p>
                                <p class="mt-0.5 font-mono text-sm text-slate-500 dark:text-slate-400">Rp {{ number_format($inv->amount, 0, ',', '.') }}</p>
                            </div>
                        </a>
                    @endforeach

                    @foreach(($typedDocs->get('invoice', collect())) as $doc)
                        <a href="{{ route('project-documents.download', $doc) }}"
                           class="block overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-900/50">
                            <div class="relative flex h-36 items-center justify-center overflow-hidden bg-slate-100 dark:bg-slate-800">
                                <svg class="h-10 w-10 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span class="absolute left-2 top-2 rounded bg-teal-500 px-1.5 py-0.5 text-[11px] font-bold uppercase text-white">INV</span>
                            </div>
                            <div class="p-3">
                                <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $doc->file_name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('File Invoice') }} · {{ $doc->created_at->format('d M Y') }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="rounded-xl border border-dashed border-slate-300 p-4 text-center dark:border-slate-700">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No invoices') }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ __('Upload the file so it appears here.') }}</p>
                    <a href="{{ route('admin.invoices.index') }}"
                       class="mt-2 inline-block text-sm text-accent-600 hover:text-accent-800 dark:text-accent-400 dark:hover:text-accent-300">
                        {{ __('Buka Modul Invoice') }} →
                    </a>
                </div>
            @endif
        </div>

        {{-- Purchase Order --}}
        <div class="mt-6">
            <div class="flex items-center justify-between mb-2">
                <span class="font-medium text-slate-700 dark:text-slate-200">{{ __('Purchase Order') }}</span>
                <a href="{{ route('admin.pos.index') }}"
                   class="text-sm text-accent-600 hover:text-accent-800 dark:text-accent-400 dark:hover:text-accent-300">
                    {{ __('Lihat Semua PO') }} →
                </a>
            </div>

            @if($custPos->isNotEmpty() || ($typedDocs->get('po', collect()))->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($custPos as $po)
                        @php
                            $poBadgeColor = match($po->status) {
                                'selesai' => 'green',
                                'dibatalkan' => 'red',
                                'diproses' => 'yellow',
                                default => 'slate',
                            };
                        @endphp
                        <a href="{{ route('admin.pos.show', $po) }}" title="{{ $po->po_number }}"
                           class="block overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-900/50">
                            <div class="relative flex h-36 items-center justify-center overflow-hidden bg-slate-100 dark:bg-slate-800">
                                <svg class="h-10 w-10 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span class="absolute left-2 top-2 rounded bg-violet-500 px-1.5 py-0.5 text-[11px] font-bold uppercase text-white">PO</span>
                            </div>
                            <div class="p-3">
                                <p class="flex flex-wrap items-center gap-2 font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    <span class="truncate">{{ $po->po_number }}</span>
                                    <x-status-badge :color="$poBadgeColor">{{ $po->statusLabel() }}</x-status-badge>
                                </p>
                                <p class="mt-0.5 truncate text-xs text-slate-400">{{ Str::limit($po->items, 60) }}</p>
                            </div>
                        </a>
                    @endforeach

                    @foreach(($typedDocs->get('po', collect())) as $doc)
                        <a href="{{ route('project-documents.download', $doc) }}"
                           class="block overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-900/50">
                            <div class="relative flex h-36 items-center justify-center overflow-hidden bg-slate-100 dark:bg-slate-800">
                                <svg class="h-10 w-10 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span class="absolute left-2 top-2 rounded bg-violet-500 px-1.5 py-0.5 text-[11px] font-bold uppercase text-white">PO</span>
                            </div>
                            <div class="p-3">
                                <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $doc->file_name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('File Purchase Order') }} · {{ $doc->created_at->format('d M Y') }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="rounded-xl border border-dashed border-slate-300 p-4 text-center dark:border-slate-700">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No purchase orders') }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ __('Upload the file so it appears here.') }}</p>
                    <a href="{{ route('admin.pos.index') }}"
                       class="mt-2 inline-block text-sm text-accent-600 hover:text-accent-800 dark:text-accent-400 dark:hover:text-accent-300">
                        {{ __('Buka Modul Purchase Order') }} →
                    </a>
                </div>
            @endif
        </div>

        {{-- Payment — bukti transfer hanya untuk role finance/management. --}}
        @can('view-payment-proofs')
        <div class="mt-6">
            <div class="flex items-center justify-between mb-2">
                <span class="font-medium text-slate-700 dark:text-slate-200">{{ __('Payment') }}</span>
                <a href="{{ route('admin.payments.index') }}"
                   class="text-sm text-accent-600 hover:text-accent-800 dark:text-accent-400 dark:hover:text-accent-300">
                    {{ __('Lihat Semua Payment') }} →
                </a>
            </div>

            @if($custPayments->isNotEmpty() || ($typedDocs->get('payment', collect()))->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($custPayments as $pm)
                        <a href="{{ route('admin.payments.show', $pm) }}" title="{{ $pm->invoice->invoice_number }}"
                           class="block overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-900/50">
                            <div class="relative flex h-36 items-center justify-center overflow-hidden bg-slate-100 dark:bg-slate-800">
                                <svg class="h-10 w-10 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M9 14l6-6m-5.5.5h.01m4.99 1H15a5 5 0 015 5v7H5v-7a5 5 0 015-5h2.5z"/>
                                </svg>
                                <span class="absolute left-2 top-2 rounded bg-emerald-500 px-1.5 py-0.5 text-[11px] font-bold uppercase text-white">PAY</span>
                            </div>
                            <div class="p-3">
                                <p class="truncate font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $pm->invoice->invoice_number }}</p>
                                <p class="mt-0.5 font-mono text-sm text-slate-500 dark:text-slate-400">Rp {{ number_format($pm->amount, 0, ',', '.') }}</p>
                                <p class="mt-0.5 truncate text-xs text-slate-400">{{ $pm->payment_date->format('d M Y') }}{{ $pm->proof_file ? ' · ' . __('Proof') : '' }}</p>
                            </div>
                        </a>
                    @endforeach

                    @foreach(($typedDocs->get('payment', collect())) as $doc)
                        <a href="{{ route('project-documents.download', $doc) }}"
                           class="block overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-900/50">
                            <div class="relative flex h-36 items-center justify-center overflow-hidden bg-slate-100 dark:bg-slate-800">
                                <svg class="h-10 w-10 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span class="absolute left-2 top-2 rounded bg-emerald-500 px-1.5 py-0.5 text-[11px] font-bold uppercase text-white">PAY</span>
                            </div>
                            <div class="p-3">
                                <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $doc->file_name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('File Payment') }} · {{ $doc->created_at->format('d M Y') }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="rounded-xl border border-dashed border-slate-300 p-4 text-center dark:border-slate-700">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No payments') }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ __('Upload the file so it appears here.') }}</p>
                    <a href="{{ route('admin.payments.index') }}"
                       class="mt-2 inline-block text-sm text-accent-600 hover:text-accent-800 dark:text-accent-400 dark:hover:text-accent-300">
                        {{ __('Buka Modul Payment') }} →
                    </a>
                </div>
            @endif
        </div>
        @endcan
    </div>
</div>