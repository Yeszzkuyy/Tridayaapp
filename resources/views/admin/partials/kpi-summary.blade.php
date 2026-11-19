{{-- 4 kartu ringkasan gaya Lynk: ikon di kiri, angka besar, tren bulan ini,
     dan grafik mini 6 bulan dari data riil. --}}
@php
    $spark = $spark ?? [];
    $overdueCount = (int) ($kpi['overdue_invoices_count'] ?? 0);

    $trend = function (string $key) use ($spark): array {
        $series = array_map('intval', $spark[$key] ?? []);
        $last = $series ? $series[count($series) - 1] : 0;
        $prev = count($series) > 1 ? $series[count($series) - 2] : 0;
        $delta = $last - $prev;
        $arrow = $delta > 0 ? '↗' : ($delta < 0 ? '↘' : '→');

        return [
            'arrow' => $arrow,
            'value' => $delta > 0 ? '+'.$delta : (string) $delta,
            'color' => $delta > 0
                ? 'text-emerald-600 dark:text-emerald-400'
                : ($delta < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500 dark:text-slate-400'),
        ];
    };

    $cards = [
        [
            'label' => __('Siap Ditagih'),
            'value' => (int) ($kpi['ready_to_invoice_count'] ?? 0),
            'display' => number_format((int) ($kpi['ready_to_invoice_count'] ?? 0)),
            'countup' => true,
            'icon' => 'receipt',
            'ambient' => 'bg-emerald-500/15 dark:bg-emerald-500/20',
            'iconBg' => 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
            'color' => '#10b981',
            'gradId' => 'admin-spark-1',
            'spark' => \App\Support\SparklineBuilder::fromSeries($spark['ready_to_invoice'] ?? [], (int) ($kpi['ready_to_invoice_count'] ?? 0)),
            'trend' => $trend('ready_to_invoice'),
            'unit' => __('proyek bulan ini'),
        ],
        [
            'label' => __('Tagihan Belum Lunas'),
            'value' => null,
            'display' => 'Rp '.number_format((float) ($kpi['unpaid_invoices_amount'] ?? 0), 0, ',', '.'),
            'countup' => false,
            'icon' => 'credit-card',
            'ambient' => 'bg-amber-500/15 dark:bg-amber-500/20',
            'iconBg' => 'bg-amber-500/10 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300',
            'color' => '#f59e0b',
            'gradId' => 'admin-spark-2',
            'spark' => \App\Support\SparklineBuilder::fromSeries($spark['unpaid_invoices'] ?? [], (int) ($kpi['unpaid_invoices_count'] ?? 0)),
            'trend' => $trend('unpaid_invoices'),
            'unit' => __('invoice bulan ini'),
        ],
        [
            'label' => __('Antrean Request Harga'),
            'value' => (int) ($kpi['request_harga_count'] ?? 0),
            'display' => number_format((int) ($kpi['request_harga_count'] ?? 0)),
            'countup' => true,
            'icon' => 'clipboard-check',
            'ambient' => 'bg-sky-500/15 dark:bg-sky-500/20',
            'iconBg' => 'bg-sky-500/10 text-sky-600 dark:bg-sky-500/15 dark:text-sky-300',
            'color' => '#0ea5e9',
            'gradId' => 'admin-spark-3',
            'spark' => \App\Support\SparklineBuilder::fromSeries($spark['request_harga'] ?? [], (int) ($kpi['request_harga_count'] ?? 0)),
            'trend' => $trend('request_harga'),
            'unit' => __('request bulan ini'),
        ],
        [
            'label' => __('Supplier PO Aktif'),
            'value' => (int) ($kpi['supplier_pos_outstanding_count'] ?? 0),
            'display' => number_format((int) ($kpi['supplier_pos_outstanding_count'] ?? 0)),
            'countup' => true,
            'icon' => 'cube',
            'ambient' => 'bg-violet-500/15 dark:bg-violet-500/20',
            'iconBg' => 'bg-violet-500/10 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300',
            'color' => '#8b5cf6',
            'gradId' => 'admin-spark-4',
            'spark' => \App\Support\SparklineBuilder::fromSeries($spark['supplier_pos'] ?? [], (int) ($kpi['supplier_pos_outstanding_count'] ?? 0)),
            'trend' => $trend('supplier_pos'),
            'unit' => __('PO bulan ini'),
        ],
    ];
@endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($cards as $idx => $card)
        @php $delay = 100 + ($idx * 80); @endphp
        <div style="--d: {{ $delay }}ms"
             class="lynk-enter lynk-kpi-card group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg dark:border-slate-800/60 dark:bg-slate-800/90">
            <span aria-hidden="true"
                  class="pointer-events-none absolute -right-10 -top-10 h-28 w-28 rounded-full blur-2xl transition-opacity duration-500 {{ $card['ambient'] }}"></span>

            <div class="relative z-10 flex items-center gap-3.5">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition-transform duration-300 group-hover:scale-105 {{ $card['iconBg'] }}">
                    <x-icon name="{{ $card['icon'] }}" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                    <p class="mt-0.5 break-words text-2xl font-extrabold leading-tight tracking-tight text-slate-900 tabular-nums dark:text-white sm:text-3xl"
                       @if ($card['countup'])
                           data-countup="{{ $card['value'] }}" data-countup-delay="{{ $delay + 20 }}"
                       @endif>{{ $card['display'] }}</p>
                </div>
            </div>

            <div class="relative z-10 mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-medium">
                <span class="inline-flex items-center gap-1 {{ $card['trend']['color'] }}">
                    <span aria-hidden="true">{{ $card['trend']['arrow'] }}</span>
                    <span>{{ $card['trend']['value'] }} {{ $card['unit'] }}</span>
                </span>
                @if ($idx === 1 && $overdueCount > 0)
                    <span class="inline-flex items-center gap-1 text-rose-600 dark:text-rose-400">
                        <span class="h-1 w-1 rounded-full bg-rose-500"></span>
                        {{ $overdueCount }} {{ __('jatuh tempo') }}
                    </span>
                @endif
            </div>

            <div class="relative z-10 -mx-5 -mb-5 mt-2 pt-1">
                <svg viewBox="0 0 240 52" class="h-12 w-full overflow-visible" preserveAspectRatio="none" aria-hidden="true">
                    <defs>
                        <linearGradient id="{{ $card['gradId'] }}" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="{{ $card['color'] }}" stop-opacity="{{ $card['spark']['is_flat'] ? '0.10' : '0.32' }}" />
                            <stop offset="100%" stop-color="{{ $card['color'] }}" stop-opacity="0.0" />
                        </linearGradient>
                    </defs>
                    <path class="lynk-spark-area" d="{{ $card['spark']['area'] }}" fill="url(#{{ $card['gradId'] }})" />
                    <path class="lynk-spark-line" pathLength="100" d="{{ $card['spark']['line'] }}" fill="none" stroke="{{ $card['color'] }}" stroke-width="2.2" stroke-linecap="round" />
                </svg>
            </div>
        </div>
    @endforeach
</div>
