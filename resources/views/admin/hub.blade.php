<x-app-layout>
    @include('partials.lynk-dashboard-motion')

    <div class="space-y-5">
        {{-- Header terbuka: judul + filter PT sebaris, tanpa tombol aksi --}}
        <div class="lynk-enter flex flex-col gap-3 pt-1 sm:flex-row sm:items-center sm:justify-between" style="--d: 0ms">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-3xl">
                {{ __('Admin Dashboard') }}
            </h1>

            <nav class="flex flex-wrap items-center gap-2" aria-label="{{ __('Filter Berdasarkan PT') }}">
                <a href="{{ request()->url() }}"
                   class="rounded-full border px-4 py-2 text-xs font-semibold transition-all duration-200 {{ empty($ptGroup) ? 'border-accent-600 bg-accent-600 text-white shadow-sm shadow-accent-600/30' : 'border-slate-200/80 bg-[var(--card-bg)] text-slate-600 hover:border-accent-400 dark:border-slate-700 dark:text-slate-300' }}">
                    {{ __('Semua PT') }}
                </a>
                @foreach (['NTI', 'MGK', 'TPS', 'WANI'] as $pt)
                    <a href="{{ request()->url().'?pt_group='.$pt }}"
                       class="rounded-full border px-4 py-2 text-xs font-semibold transition-all duration-200 {{ $ptGroup === $pt ? 'border-accent-600 bg-accent-600 text-white shadow-sm shadow-accent-600/30' : 'border-slate-200/80 bg-[var(--card-bg)] text-slate-600 hover:border-accent-400 dark:border-slate-700 dark:text-slate-300' }}">
                        {{ $pt }}
                    </a>
                @endforeach
            </nav>
        </div>

        {{-- 4 kartu ringkasan dengan grafik mini 6 bulan --}}
        @include('admin.partials.kpi-summary')

        {{-- Alur kerja admin versi tipis: satu baris, bisa digeser di layar kecil --}}
        @php
            $steps = [
                [
                    'label' => __('Customer PO'),
                    'route' => route('admin.purchasing.index'),
                    'count' => (int) ($kpi['quotation_deals_ready_count'] ?? 0),
                    'meta' => __('siap Supplier PO'),
                    'tone' => 'bg-sky-500/10 text-sky-600 dark:bg-sky-500/15 dark:text-sky-300',
                ],
                [
                    'label' => __('Supplier PO'),
                    'route' => route('admin.supplier-pos.index'),
                    'count' => (int) ($kpi['supplier_pos_outstanding_count'] ?? 0),
                    'meta' => __('belum selesai'),
                    'tone' => 'bg-violet-500/10 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300',
                ],
                [
                    'label' => __('Goods Receipts'),
                    'route' => route('admin.goods-receipts.index'),
                    'count' => (int) ($kpi['pending_receipts_count'] ?? 0),
                    'meta' => __('menunggu verifikasi'),
                    'tone' => 'bg-amber-500/10 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300',
                ],
                [
                    'label' => __('Deliveries'),
                    'route' => route('admin.goods-deliveries.index'),
                    'count' => (int) ($kpi['outstanding_deliveries_count'] ?? 0),
                    'meta' => __('belum terkirim'),
                    'tone' => 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
                ],
                [
                    'label' => __('Invoice'),
                    'route' => route('admin.invoices.index'),
                    'count' => (int) ($kpi['unpaid_invoices_count'] ?? 0),
                    'meta' => __('belum lunas'),
                    'tone' => 'bg-indigo-500/10 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300',
                ],
            ];
        @endphp

        <div class="lynk-enter overflow-hidden rounded-2xl border border-slate-100 bg-slate-100 shadow-sm dark:border-slate-800/60 dark:bg-slate-700/60" style="--d: 340ms">
            <div class="grid grid-cols-2 gap-px lg:grid-cols-3 xl:grid-cols-5">
                @foreach ($steps as $index => $step)
                    <a href="{{ $step['route'] }}" data-step="{{ $index + 1 }}"
                       class="group flex items-center gap-2.5 bg-white px-4 py-3.5 transition-colors duration-200 last:col-span-2 hover:bg-slate-50/90 dark:bg-slate-800/90 dark:hover:bg-slate-700/35 xl:last:col-span-1">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-[11px] font-bold {{ $step['tone'] }}">{{ $index + 1 }}</span>
                        <span class="min-w-0">
                            <span class="block truncate text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $step['label'] }}</span>
                            <span class="block truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $step['count'] }} {{ $step['meta'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- 4 Tab Utama (Alpine.js) --}}
        <div x-data="{
            activeTab: '{{ request('tab', 'purchasing') }}',
            selectTab(tab) {
                this.activeTab = tab;
                try {
                    const url = new URL(window.location);
                    url.searchParams.set('tab', tab);
                    window.history.replaceState({}, '', url);
                } catch (e) {}
            }
        }"
        class="space-y-5">
            {{-- Navigasi tab: 4 kotak segmen sejajar, tanpa garis bawah --}}
            <div role="tablist"
                 aria-label="{{ __('Admin Sections') }}"
                 class="grid grid-cols-2 gap-1.5 rounded-2xl border border-slate-100 bg-white p-1.5 shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90 lg:grid-cols-4">
                {{-- Tab 1: Purchasing --}}
                <button type="button"
                        @click="selectTab('purchasing')"
                        role="tab"
                        :aria-selected="activeTab === 'purchasing'"
                        :class="activeTab === 'purchasing'
                            ? 'bg-accent-600 text-white shadow-sm shadow-accent-600/30'
                            : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700/50'"
                        class="group flex min-w-0 items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-xs font-semibold transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/50 sm:text-sm">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span class="truncate">{{ __('Purchasing') }}</span>
                    @if(($kpi['request_harga_count'] ?? 0) > 0)
                        <span class="inline-flex min-w-[20px] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-bold leading-none tabular-nums"
                              :class="activeTab === 'purchasing' ? 'bg-white text-accent-700' : 'bg-accent-600 text-white'">
                            {{ $kpi['request_harga_count'] }}
                        </span>
                    @endif
                </button>

                {{-- Tab 2: Finance --}}
                <button type="button"
                        @click="selectTab('finance')"
                        role="tab"
                        :aria-selected="activeTab === 'finance'"
                        :class="activeTab === 'finance'
                            ? 'bg-accent-600 text-white shadow-sm shadow-accent-600/30'
                            : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700/50'"
                        class="group flex min-w-0 items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-xs font-semibold transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/50 sm:text-sm">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span class="truncate">{{ __('Finance') }}</span>
                    @if(($kpi['ready_to_invoice_count'] ?? 0) > 0)
                        <span class="inline-flex min-w-[20px] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-bold leading-none tabular-nums"
                              :class="activeTab === 'finance' ? 'bg-white text-accent-700' : 'bg-accent-600 text-white'">
                            {{ $kpi['ready_to_invoice_count'] }}
                        </span>
                    @endif
                </button>

                {{-- Tab 3: Documents --}}
                <button type="button"
                        @click="selectTab('documents')"
                        role="tab"
                        :aria-selected="activeTab === 'documents'"
                        :class="activeTab === 'documents'
                            ? 'bg-accent-600 text-white shadow-sm shadow-accent-600/30'
                            : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700/50'"
                        class="group flex min-w-0 items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-xs font-semibold transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/50 sm:text-sm">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="truncate">{{ __('Documents') }}</span>
                    <span class="inline-flex min-w-[20px] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-bold leading-none tabular-nums"
                          :class="activeTab === 'documents' ? 'bg-white text-accent-700' : 'bg-accent-600 text-white'">
                        {{ count($documents['projects']) }}
                    </span>
                </button>

                {{-- Tab 4: Procurement & AP --}}
                <button type="button"
                        @click="selectTab('procurement')"
                        role="tab"
                        :aria-selected="activeTab === 'procurement'"
                        :class="activeTab === 'procurement'
                            ? 'bg-accent-600 text-white shadow-sm shadow-accent-600/30'
                            : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700/50'"
                        class="group flex min-w-0 items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-xs font-semibold transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/50 sm:text-sm">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    <span class="truncate">{{ __('Procurement & AP') }}</span>
                    @if(($kpi['unbilled_receipts_count'] ?? 0) > 0)
                        <span class="inline-flex min-w-[20px] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-bold leading-none tabular-nums"
                              :class="activeTab === 'procurement' ? 'bg-white text-accent-700' : 'bg-accent-600 text-white'">
                            {{ $kpi['unbilled_receipts_count'] }}
                        </span>
                    @endif
                </button>
            </div>
            {{-- Tab 1 Content: Purchasing --}}
            <div x-show="activeTab === 'purchasing'" x-cloak>
                @include('admin.partials.tab-purchasing')
            </div>

            {{-- Tab 2 Content: Finance --}}
            <div x-show="activeTab === 'finance'" x-cloak>
                @include('admin.procurement.tab-finance')
            </div>

            {{-- Tab 3 Content: Documents --}}
            <div x-show="activeTab === 'documents'" x-cloak>
                @include('admin.partials.tab-documents')
            </div>

            {{-- Tab 4 Content: Procurement & AP (partial baru, additive) --}}
            <div x-show="activeTab === 'procurement'" x-cloak>
                @include('admin.procurement.tab')
            </div>
        </div>
    </div>
</x-app-layout>
