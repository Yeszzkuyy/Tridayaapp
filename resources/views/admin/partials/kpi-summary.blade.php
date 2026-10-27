{{-- Filter PT Group --}}
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Filter Berdasarkan PT') }}</h2>
        <div class="flex flex-wrap gap-2 mt-2">
            <a href="{{ route('manage.admin.index') }}"
               class="px-3.5 py-1.5 text-xs font-semibold rounded-xl transition-all duration-200 {{ empty($ptGroup) ? 'bg-accent-600 text-white shadow-md shadow-accent-600/30' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:border-accent-400' }}">
                {{ __('Semua PT') }}
            </a>
            @foreach(['NTI', 'MGK', 'TPS', 'WANI'] as $pt)
                <a href="{{ route('manage.admin.index', ['pt_group' => $pt]) }}"
                   class="px-3.5 py-1.5 text-xs font-semibold rounded-xl transition-all duration-200 {{ $ptGroup === $pt ? 'bg-accent-600 text-white shadow-md shadow-accent-600/30' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:border-accent-400' }}">
                    {{ $pt }}
                </a>
            @endforeach
        </div>
    </div>
    @if($ptGroup)
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-accent-50 dark:bg-accent-950/40 text-accent-700 dark:text-accent-300 text-xs font-medium border border-accent-200 dark:border-accent-800">
            <span>{{ __('Menampilkan data untuk:') }} <strong>PT {{ $ptGroup }}</strong></span>
            <a href="{{ route('manage.admin.index') }}" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 ml-1">×</a>
        </div>
    @endif
</div>

{{-- 4 Kartu KPI Ringkas --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    {{-- Card 1: Siap Ditagih (Won Deals) --}}
    <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm transition hover:shadow-md">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Siap Ditagih') }}</span>
            <div class="h-9 w-9 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
        <div class="mt-3">
            <div class="text-2xl font-bold font-mono text-slate-800 dark:text-white">
                {{ number_format($kpi['ready_to_invoice_count'] ?? 0) }}
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Proyek deal belum ada invoice') }}
            </p>
        </div>
    </div>

    {{-- Card 2: Tagihan Belum Lunas --}}
    <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm transition hover:shadow-md">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Tagihan Unpaid') }}</span>
            <div class="h-9 w-9 rounded-xl bg-amber-50 dark:bg-amber-950/50 flex items-center justify-center text-amber-600 dark:text-amber-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
        <div class="mt-3">
            <div class="text-xl font-bold font-mono text-slate-800 dark:text-white truncate">
                Rp {{ number_format($kpi['unpaid_invoices_amount'] ?? 0, 0, ',', '.') }}
            </div>
            <div class="flex items-center gap-1.5 mt-1 text-xs">
                <span class="text-slate-500 dark:text-slate-400 font-mono">{{ $kpi['unpaid_invoices_count'] ?? 0 }} invoice</span>
                @if(($kpi['overdue_invoices_count'] ?? 0) > 0)
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-semibold bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-400" title="{{ __('Overdue Amount: Rp ') . number_format($kpi['overdue_invoices_amount'] ?? 0, 0, ',', '.') }}">
                        {{ $kpi['overdue_invoices_count'] }} {{ __('jatuh tempo') }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Card 3: Antrean Request Harga --}}
    <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm transition hover:shadow-md">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Antrean Req. Harga') }}</span>
            <div class="h-9 w-9 rounded-xl bg-sky-50 dark:bg-sky-950/50 flex items-center justify-center text-sky-600 dark:text-sky-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
            </div>
        </div>
        <div class="mt-3">
            <div class="text-2xl font-bold font-mono text-slate-800 dark:text-white">
                {{ number_format($kpi['request_harga_count'] ?? 0) }}
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Kebutuhan pengadaan teknisi') }}
            </p>
        </div>
    </div>

    {{-- Card 4: Purchase Order (PO) Aktif --}}
    <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm transition hover:shadow-md">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('PO Aktif') }}</span>
            <div class="h-9 w-9 rounded-xl bg-violet-50 dark:bg-violet-950/50 flex items-center justify-center text-violet-600 dark:text-violet-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
            </div>
        </div>
        <div class="mt-3">
            <div class="text-2xl font-bold font-mono text-slate-800 dark:text-white">
                {{ number_format($kpi['active_pos_count'] ?? 0) }}
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ __('PO status draft & diproses') }}
            </p>
        </div>
    </div>
</div>
