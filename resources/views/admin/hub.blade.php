<x-app-layout>
    <div class="space-y-6">
        {{-- Header Command Center --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center justify-center p-2 rounded-xl bg-accent-100 dark:bg-accent-950/60 text-accent-600 dark:text-accent-400">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </span>
                    <h1 class="text-2xl sm:text-3xl font-bold text-slate-800 dark:text-white">
                        {{ __('Admin Command Center') }}
                    </h1>
                </div>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Modular Hub: Koordinasi Purchasing pengadaan barang, Finance & penagihan klien, serta verifikasi berkas proyek.') }}
                </p>
            </div>

            @can('manage-admin')
                <div class="flex flex-wrap items-center gap-2.5">
                    <a href="{{ route('admin.pos.create') }}"
                       class="group relative overflow-hidden rounded-xl bg-accent-500 hover:bg-accent-600 text-white px-4 py-2 text-xs font-semibold shadow-sm transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>{{ __('+ Buat PO') }}</span>
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/40 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </a>
                    <a href="{{ route('admin.invoices.create') }}"
                       class="group relative overflow-hidden rounded-xl bg-accent-600 hover:bg-accent-700 text-white px-4 py-2 text-xs font-semibold shadow-sm transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>{{ __('+ Buat Invoice') }}</span>
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/40 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </a>
                </div>
            @endcan
        </div>

        {{-- Ringkasan KPI & Filter PT --}}
        @include('admin.partials.kpi-summary')

        {{-- 4 Tab Utama (Alpine.js) --}}
        <div x-data="{ activeTab: '{{ request('tab', 'purchasing') }}' }" class="space-y-6">
            {{-- Tab Navigation Bar --}}
            <div class="border-b border-slate-200 dark:border-slate-700">
                <nav class="-mb-px flex space-x-6 sm:space-x-8 overflow-x-auto" aria-label="Tabs">
                    {{-- Tab 1: Purchasing --}}
                    <button type="button"
                            @click="activeTab = 'purchasing'"
                            :class="activeTab === 'purchasing'
                                ? 'border-accent-600 text-accent-600 dark:border-accent-400 dark:text-accent-400 font-bold'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-200'"
                            class="whitespace-nowrap py-3.5 px-1 border-b-2 font-medium text-sm transition-all flex items-center gap-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span>{{ __('1. Purchasing & Pengadaan') }}</span>
                        @if(($kpi['request_harga_count'] ?? 0) > 0)
                            <span class="ml-1.5 py-0.5 px-2 rounded-full text-xs font-semibold bg-sky-100 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300">
                                {{ $kpi['request_harga_count'] }}
                            </span>
                        @endif
                    </button>

                    {{-- Tab 2: Finance --}}
                    <button type="button"
                            @click="activeTab = 'finance'"
                            :class="activeTab === 'finance'
                                ? 'border-accent-600 text-accent-600 dark:border-accent-400 dark:text-accent-400 font-bold'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-200'"
                            class="whitespace-nowrap py-3.5 px-1 border-b-2 font-medium text-sm transition-all flex items-center gap-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span>{{ __('2. Finance & Penagihan') }}</span>
                        @if(($kpi['ready_to_invoice_count'] ?? 0) > 0)
                            <span class="ml-1.5 py-0.5 px-2 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                                {{ $kpi['ready_to_invoice_count'] }}
                            </span>
                        @endif
                    </button>

                    {{-- Tab 3: Documents --}}
                    <button type="button"
                            @click="activeTab = 'documents'"
                            :class="activeTab === 'documents'
                                ? 'border-accent-600 text-accent-600 dark:border-accent-400 dark:text-accent-400 font-bold'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-200'"
                            class="whitespace-nowrap py-3.5 px-1 border-b-2 font-medium text-sm transition-all flex items-center gap-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>{{ __('3. Kelengkapan Dokumen Proyek') }}</span>
                        <span class="ml-1.5 py-0.5 px-2 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                            {{ count($documents['projects']) }}
                        </span>
                    </button>

                    {{-- Tab 4: Procurement & AP (Tahap 3–5) --}}
                    <button type="button"
                            @click="activeTab = 'procurement'"
                            :class="activeTab === 'procurement'
                                ? 'border-accent-600 text-accent-600 dark:border-accent-400 dark:text-accent-400 font-bold'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-200'"
                            class="whitespace-nowrap py-3.5 px-1 border-b-2 font-medium text-sm transition-all flex items-center gap-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <span>{{ __('4. Procurement & AP') }}</span>
                        @if(($kpi['unbilled_receipts_count'] ?? 0) > 0)
                            <span class="ml-1.5 py-0.5 px-2 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300">
                                {{ $kpi['unbilled_receipts_count'] }}
                            </span>
                        @endif
                    </button>
                </nav>
            </div>

            {{-- Tab 1 Content: Purchasing --}}
            <div x-show="activeTab === 'purchasing'" x-cloak>
                @include('admin.partials.tab-purchasing')
            </div>

            {{-- Tab 2 Content: Finance --}}
            <div x-show="activeTab === 'finance'" x-cloak>
                @include('admin.partials.tab-finance')
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
