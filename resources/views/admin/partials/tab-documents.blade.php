<div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-700/60">
        <h3 class="text-base font-semibold text-slate-700 dark:text-slate-200">
            {{ __('Checklist Kelengkapan Dokumen Proyek') }}
        </h3>
    </div>

    <div class="min-w-0">
        <table class="w-full table-fixed divide-y divide-slate-100 text-sm dark:divide-slate-700">
            <thead class="bg-slate-50/80 dark:bg-slate-900/30">
                <tr>
                    <th class="px-3 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Proyek & Klien') }}</th>
                    <th class="w-[42%] px-3 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Kelengkapan Dokumen') }}</th>
                    <th class="w-[20%] px-3 py-3.5 text-center text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Status Tagihan') }}</th>
                    <th class="w-[11%] px-3 py-3.5 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Aksi') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @forelse($documents['projects'] as $p)
                    @php
                        $customer = $p->customer;
                        $proposal = $p->proposal;
                        $hasNpwpDoc = !empty($customer?->npwp_document_path);
                        $hasNpwpNumber = !empty($customer?->npwp);
                        $hasPoDoc = !empty($proposal?->po_document_path);
                        $technicalDocsCount = $p->documents ? $p->documents->count() : 0;
                        $invoices = $p->invoices ?? collect();
                        $hasInvoice = $invoices->isNotEmpty();
                        $latestInvoice = $invoices->first();
                        $isFullyPaid = $hasInvoice && $invoices->every(fn ($i) => $i->status === 'paid');

                        $docPill = 'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold transition-colors duration-200';
                        $docReady = 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-300';
                        $docWarn = 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-300';
                        $docMissing = 'border-slate-200 bg-slate-50 text-slate-400 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-500';
                    @endphp
                    <tr class="transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-700/35">
                        <td class="px-3 py-3.5">
                            <div class="truncate font-semibold text-slate-800 dark:text-white" title="{{ $p->project_name }}">
                                {{ $p->project_name }}
                            </div>
                            <div class="truncate text-xs text-slate-500 dark:text-slate-400">
                                {{ $customer?->name ?? __('Tanpa Klien') }}
                                @if($customer?->pt_group)
                                    <span class="ml-1 inline-block rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                        {{ $customer->pt_group }}
                                    </span>
                                @endif
                            </div>
                        </td>

                        {{-- NPWP, PO Klien, dan dokumen teknis/BAST jadi satu kolom ringkas --}}
                        <td class="px-3 py-3.5">
                            <div class="flex flex-wrap items-center gap-1.5">
                                @if($hasNpwpDoc)
                                    <a href="{{ route('customers.npwp', $customer) }}" target="_blank"
                                       class="{{ $docPill }} {{ $docReady }}"
                                       title="{{ $customer->npwp ? 'NPWP: '.$customer->npwp : __('Lihat Berkas NPWP') }}">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg>
                                        NPWP
                                    </a>
                                @elseif($hasNpwpNumber)
                                    <span class="{{ $docPill }} {{ $docWarn }}"
                                          title="{{ __('Nomor ada, berkas scan belum diunggah') }}: {{ $customer->npwp }}">
                                        NPWP
                                    </span>
                                @else
                                    <span class="{{ $docPill }} {{ $docMissing }}" title="{{ __('Belum Ada') }}">NPWP</span>
                                @endif

                                @if($hasPoDoc)
                                    <a href="{{ route('sales.proposals.download-po', $proposal) }}" target="_blank"
                                       class="{{ $docPill }} {{ $docReady }}"
                                       title="{{ __('Unduh PO Resmi Klien') }}">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg>
                                        PO
                                    </a>
                                @else
                                    <span class="{{ $docPill }} {{ $docMissing }}" title="{{ __('Belum Diunggah') }}">PO</span>
                                @endif

                                @if($technicalDocsCount > 0)
                                    <a href="{{ route('project-documents.index', $p) }}"
                                       class="{{ $docPill }} border-sky-200 bg-sky-50 text-sky-700 hover:bg-sky-100 dark:border-sky-800/60 dark:bg-sky-950/40 dark:text-sky-300"
                                       title="{{ __('Lihat Dokumen Proyek') }}">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        {{ $technicalDocsCount }} {{ __('Berkas') }}
                                    </a>
                                @else
                                    <span class="{{ $docPill }} {{ $docMissing }}" title="{{ __('Belum Ada Berkas') }}">0 {{ __('Berkas') }}</span>
                                @endif
                            </div>
                        </td>

                        {{-- Status Tagihan (Invoice) --}}
                        <td class="px-3 py-3.5 text-center">
                            @if($isFullyPaid)
                                <span class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700 dark:bg-green-950/50 dark:text-green-300">
                                    {{ __('Lunas') }}
                                </span>
                            @elseif($hasInvoice)
                                <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">
                                    {{ __('Unpaid') }}
                                </span>
                            @else
                                <span class="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700 dark:bg-red-950/50 dark:text-red-300">
                                    {{ __('Belum Ditagih') }}
                                </span>
                            @endif
                            @if($hasInvoice)
                                <div class="mt-1 truncate text-xs tabular-nums text-slate-400" title="{{ $latestInvoice->invoice_number }}">
                                    {{ $latestInvoice->invoice_number }}
                                </div>
                            @endif
                        </td>

                        {{-- Aksi --}}
                        <td class="px-3 py-3.5 text-right">
                            <div class="flex flex-wrap items-center justify-end gap-1.5">
                                <x-icon-button as="a" icon="project" href="{{ route('projects.show', $p) }}" title="{{ __('Detail Proyek') }}" />
                                @can('manage-admin')
                                    @if(! $hasInvoice)
                                        <x-icon-button as="a" icon="add"
                                            href="{{ route('admin.invoices.create', [
                                                'project_id' => $p->id,
                                                'customer_id' => $p->customer_id,
                                                'amount' => $proposal?->grand_total
                                            ]) }}"
                                            title="{{ __('Buat Invoice') }}" />
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-10 text-center text-sm italic text-slate-400 dark:text-slate-500">
                            {{ __('Belum ada data proyek aktif untuk diverifikasi.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
