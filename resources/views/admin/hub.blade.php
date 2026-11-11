<x-app-layout>
    @include('partials.lynk-dashboard-motion')

    <div class="space-y-5">
        {{-- Header terbuka: judul + filter PT sebaris, tanpa tombol aksi --}}
        <div class="lynk-enter flex flex-col gap-3 pt-1 sm:flex-row sm:items-center sm:justify-between" style="--d: 0ms">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-3xl">
                {{ __('Admin Dashboard') }}
            </h1>

            @php
                $ptActive = 0;
                $ptItems = [['value' => '', 'label' => __('Semua PT'), 'url' => request()->url()]];
                foreach (['NTI', 'MGK', 'TPS', 'WANI'] as $pt) {
                    $ptItems[] = ['value' => $pt, 'label' => $pt, 'url' => request()->url().'?pt_group='.$pt];
                    if ($ptGroup === $pt) {
                        $ptActive = count($ptItems) - 1;
                    }
                }
            @endphp

            {{-- Filter PT memakai komponen segmen yang sama dengan Time Range Selector --}}
            <div class="flex max-w-full justify-start overflow-x-auto scrollbar-none sm:justify-end">
                <div id="admin-pt-segment"
                     data-rubber-segment
                     data-mode="navigate"
                     data-items='@json($ptItems)'
                     data-active="{{ $ptActive }}"
                     data-label="{{ __('Filter Berdasarkan PT') }}"
                     data-size="md"
                     data-radius="12"></div>
                @vite('resources/js/rubber-segment.jsx')

                <noscript>
                    <nav class="flex flex-wrap items-center gap-2" aria-label="{{ __('Filter Berdasarkan PT') }}">
                        @foreach ($ptItems as $i => $item)
                            <a href="{{ $item['url'] }}"
                               class="rounded-xl border px-4 py-2 text-xs font-semibold transition-all duration-200 {{ $i === $ptActive ? 'border-accent-600 bg-accent-600 text-white' : 'border-slate-200/80 bg-[var(--card-bg)] text-slate-600 dark:border-slate-700 dark:text-slate-300' }}">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </nav>
                </noscript>
            </div>
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

        {{-- 4 Tab Utama (Alpine.js + efek geser Time Range Selector) --}}
        @php
            $tabItems = [
                ['value' => 'purchasing', 'label' => __('Purchasing'), 'badge' => ($kpi['request_harga_count'] ?? 0) > 0 ? $kpi['request_harga_count'] : null],
                ['value' => 'finance', 'label' => __('Finance'), 'badge' => ($kpi['ready_to_invoice_count'] ?? 0) > 0 ? $kpi['ready_to_invoice_count'] : null],
                ['value' => 'documents', 'label' => __('Documents'), 'badge' => count($documents['projects'])],
                ['value' => 'procurement', 'label' => __('Procurement & AP'), 'badge' => ($kpi['unbilled_receipts_count'] ?? 0) > 0 ? $kpi['unbilled_receipts_count'] : null],
            ];
            $tabValues = array_column($tabItems, 'value');
            $tabIndex = array_search(request('tab', 'purchasing'), $tabValues, true);
            $tabIndex = $tabIndex === false ? 0 : $tabIndex;
        @endphp
        <div x-data="{
            activeTab: '{{ $tabItems[$tabIndex]['value'] }}',
            selectTab(tab) {
                this.activeTab = tab;
                try {
                    const url = new URL(window.location);
                    url.searchParams.set('tab', tab);
                    window.history.replaceState({}, '', url);
                } catch (e) {}
            }
        }"
        @admin-tab:change.window="selectTab($event.detail.value)"
        class="space-y-5">
            {{-- Navigasi tab: komponen geser yang sama dengan Time Range Selector --}}
            <div class="flex justify-center" data-tabs="centered">
                <div id="admin-tabs-segment"
                     data-rubber-segment
                     data-mode="event"
                     data-event-name="admin-tab:change"
                     data-items='@json($tabItems)'
                     data-active="{{ $tabIndex }}"
                     data-label="{{ __('Admin Sections') }}"
                     data-size="md"
                     data-radius="12"></div>
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
