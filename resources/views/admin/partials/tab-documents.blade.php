<div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div>
            <h3 class="text-base font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-indigo-500"></span>
                {{ __('Checklist Kelengkapan Dokumen Proyek') }}
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ __('Verifikasi berkas legalitas, PO resmi klien dari Sales, dan BAST/dokumen teknis sebelum penerbitan invoice.') }}
            </p>
        </div>
        <div class="text-xs text-slate-400 dark:text-slate-500">
            {{ __('Menampilkan 15 proyek terkini') }}
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-700 text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Proyek & Klien') }}</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('NPWP Klien') }}</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('PO Klien (Sales)') }}</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('BAST / Teknis') }}</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Status Tagihan') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">{{ __('Aksi') }}</th>
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
                        $isFullyPaid = $hasInvoice && $invoices->every(fn($i) => $i->status === 'paid');
                    @endphp
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                        {{-- Proyek & Klien --}}
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-800 dark:text-white">
                                {{ $p->project_name }}
                            </div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $customer?->name ?? __('Tanpa Klien') }}
                                @if($customer?->pt_group)
                                    <span class="inline-block px-1.5 py-0.2 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 ml-1">
                                        {{ $customer->pt_group }}
                                    </span>
                                @endif
                            </div>
                        </td>

                        {{-- NPWP Klien --}}
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            @if($hasNpwpDoc)
                                <a href="{{ route('customers.npwp', $customer) }}" target="_blank"
                                   class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 hover:bg-emerald-200 transition"
                                   title="{{ $customer->npwp ? 'NPWP: ' . $customer->npwp : __('Lihat Berkas NPWP') }}">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {{ __('Ada Berkas') }}
                                </a>
                                @if($customer->npwp)
                                    <div class="text-[10px] font-mono text-slate-400 mt-0.5 truncate max-w-[120px] mx-auto">
                                        {{ $customer->npwp }}
                                    </div>
                                @endif
                            @elseif($hasNpwpNumber)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300" title="{{ __('Nomor ada, berkas scan belum diunggah') }}">
                                    {{ __('No. Ada') }}
                                </span>
                                <div class="text-[10px] font-mono text-slate-400 mt-0.5 truncate max-w-[120px] mx-auto">
                                    {{ $customer->npwp }}
                                </div>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400">
                                    {{ __('Belum Ada') }}
                                </span>
                            @endif
                        </td>

                        {{-- PO Klien (Sales) --}}
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            @if($hasPoDoc)
                                <a href="{{ route('sales.proposals.download-po', $proposal) }}" target="_blank"
                                   class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 hover:bg-emerald-200 transition"
                                   title="{{ __('Unduh PO Resmi Klien') }}">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {{ __('Ada PO') }}
                                </a>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400">
                                    {{ __('Belum Diunggah') }}
                                </span>
                            @endif
                        </td>

                        {{-- BAST / Dokumen Teknis --}}
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            @if($technicalDocsCount > 0)
                                <a href="{{ route('project-documents.index', $p) }}"
                                   class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300 hover:bg-sky-200 transition"
                                   title="{{ __('Lihat Dokumen Proyek') }}">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    {{ $technicalDocsCount }} {{ __('Berkas') }}
                                </a>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400">
                                    {{ __('Belum Ada Berkas') }}
                                </span>
                            @endif
                        </td>

                        {{-- Status Tagihan (Invoice) --}}
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            @if($isFullyPaid)
                                <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300">
                                    {{ __('Lunas') }} ({{ $latestInvoice->invoice_number }})
                                </span>
                            @elseif($hasInvoice)
                                <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">
                                    {{ __('Unpaid') }} ({{ $latestInvoice->invoice_number }})
                                </span>
                            @else
                                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300">
                                    {{ __('Belum Ditagih') }}
                                </span>
                            @endif
                        </td>

                        {{-- Aksi --}}
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-2">
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
                        <td colspan="6" class="py-8 text-center text-slate-400 dark:text-slate-500 text-sm italic">
                            {{ __('Belum ada data proyek aktif untuk diverifikasi.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
