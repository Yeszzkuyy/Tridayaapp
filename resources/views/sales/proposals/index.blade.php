<x-app-layout>
    @php
        $query = request()->query();
        $activeStatus = (string) request('status', '');
        $activeFilter = (string) request('filter', '');
        $activeSearch = (string) request('search', '');
        $sortKey = (string) request('sort', '');
        $sortDir = strtolower((string) request('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = ['number', 'customer', 'amount', 'status', 'valid_until', 'created_at'];

        // Bangun URL daftar sambil mempertahankan filter lain. Halaman selalu
        // direset ke 1 saat filter/sort berubah.
        $listUrl = function (array $overrides = [], array $remove = []) use ($query) {
            $params = $query;
            unset($params['page']);
            foreach ($remove as $key) {
                unset($params[$key]);
            }
            foreach ($overrides as $key => $value) {
                if ($value === null || $value === '') {
                    unset($params[$key]);
                } else {
                    $params[$key] = $value;
                }
            }

            return route('sales.proposals.index', $params);
        };

        $sortUrl = function (string $key) use ($sortKey, $sortDir, $sortable, $listUrl) {
            if (! in_array($key, $sortable, true)) {
                return $listUrl();
            }
            if ($sortKey !== $key) {
                return $listUrl(['sort' => $key, 'direction' => 'asc']);
            }
            if ($sortDir === 'asc') {
                return $listUrl(['sort' => $key, 'direction' => 'desc']);
            }

            return $listUrl([], ['sort', 'direction']);
        };

        $ariaSort = function (string $key) use ($sortKey, $sortDir) {
            if ($sortKey !== $key) {
                return 'none';
            }

            return $sortDir === 'asc' ? 'ascending' : 'descending';
        };

        $kpiCards = [
            [
                'key' => 'draft',
                'label' => __('Draft'),
                'value' => (int) ($kpi['draft'] ?? 0),
                'status' => 'draft',
                'filter' => null,
                'color' => 'var(--text-tertiary)',
                'tooltip' => __('Quotations still in draft.'),
            ],
            [
                'key' => 'pending_technical',
                'label' => __('Pending Technical'),
                'value' => (int) ($kpi['pending_technical'] ?? 0),
                'status' => 'pending_technical',
                'filter' => null,
                'color' => 'var(--warning)',
                'tooltip' => __('Quotations waiting for the technical price.'),
            ],
            [
                'key' => 'sent',
                'label' => __('Sent'),
                'value' => (int) ($kpi['sent'] ?? 0),
                'status' => 'sent',
                'filter' => null,
                'color' => 'var(--info)',
                'tooltip' => __('Quotations already sent to the customer.'),
            ],
            [
                'key' => 'viewed',
                'label' => __('Viewed'),
                'value' => (int) ($kpi['viewed'] ?? 0),
                'status' => 'viewed',
                'filter' => null,
                'color' => 'var(--info)',
                'tooltip' => __('Quotations opened by the customer.'),
            ],
            [
                'key' => 'expiring',
                'label' => __('Expiring Soon'),
                'value' => (int) ($kpi['expiring'] ?? 0),
                'status' => null,
                'filter' => 'expiring',
                'color' => 'var(--warning)',
                'tooltip' => __('Quotations expiring within 7 days.'),
            ],
            [
                'key' => 'won_month',
                'label' => __('Won This Month'),
                'value' => (int) ($kpi['won_month'] ?? 0),
                'status' => null,
                'filter' => 'won_month',
                'color' => 'var(--success)',
                'tooltip' => __('Accepted quotations updated this month.'),
            ],
        ];

        $hasFilters = $activeStatus !== '' || $activeFilter !== '' || $activeSearch !== '';
        $total = $proposals->total();
        $summary = $total === 0
            ? __('No results')
            : __('Showing :from-:to of :total', [
                'from' => $proposals->firstItem(),
                'to' => $proposals->lastItem(),
                'total' => $total,
            ]);
    @endphp

    <div class="pb-24">
        {{-- Header: tombol kembali di kiri judul, CTA di kanan. --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3">
                <x-back-button :href="route('sales.dashboard')" :label="__('Back')" />
                <div class="min-w-0">
                    <h1 class="font-outfit text-2xl font-bold tracking-tight text-content-primary">{{ __('Quotations') }}</h1>
                    <p class="mt-1 text-sm text-content-secondary">{{ __('Sales quotation & pricing list.') }}</p>
                </div>
            </div>
            @canany(['manage-inside-sales', 'manage-sales-leads'])
                <x-icon-button as="a" icon="add" variant="solid" href="{{ route('sales.proposals.create') }}"
                               label="{{ __('New Quotation') }}" title="{{ __('New Quotation') }}" />
            @endcanany
        </div>

        {{-- KPI: satu baris 6 kartu, klik = filter status yang sama. --}}
        <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
            @foreach($kpiCards as $card)
                @php
                    $isActive = $card['status'] !== null
                        ? $activeStatus === $card['status']
                        : $activeFilter === $card['filter'];
                    $href = $isActive
                        ? $listUrl([], ['status', 'filter'])
                        : ($card['status'] !== null
                            ? $listUrl(['status' => $card['status']], ['filter'])
                            : $listUrl(['filter' => $card['filter']], ['status']));
                @endphp
                <a href="{{ $href }}" aria-pressed="{{ $isActive ? 'true' : 'false' }}" title="{{ $card['tooltip'] }}"
                   class="group flex h-24 flex-col justify-center gap-1.5 rounded-2xl border bg-surface px-4 py-3 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900 {{ $isActive ? 'border-accent-600 ring-1 ring-accent-600/20 dark:border-accent-400 dark:ring-accent-400/25' : 'border-line' }}">
                    <span class="flex items-center gap-2 text-xs font-semibold uppercase leading-tight tracking-wider text-content-secondary">
                        <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $card['color'] }};"></span>
                        {{ $card['label'] }}
                    </span>
                    <span class="text-3xl font-extrabold leading-none tabular-nums {{ $card['value'] === 0 ? 'text-content-tertiary' : 'text-content-primary' }}">
                        {{ $card['value'] }}
                    </span>
                </a>
            @endforeach
        </div>

        {{-- Toolbar: pencarian, status, refresh. --}}
        <form method="GET" action="{{ route('sales.proposals.index') }}" class="mb-3 flex flex-col gap-3 lg:flex-row lg:items-center">
            @if(request()->has('embed'))
                <input type="hidden" name="embed" value="1">
            @endif
            @if($sortKey !== '')
                <input type="hidden" name="sort" value="{{ $sortKey }}">
                <input type="hidden" name="direction" value="{{ $sortDir }}">
            @endif
            @if($activeFilter !== '')
                <input type="hidden" name="filter" value="{{ $activeFilter }}">
            @endif
            @if(request()->filled('per_page'))
                <input type="hidden" name="per_page" value="{{ request('per_page') }}">
            @endif

            <div class="relative min-w-80 flex-1">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-content-tertiary">
                    <x-icon name="search" class="h-4 w-4" />
                </span>
                <input id="quotation-search" type="search" name="search" value="{{ $activeSearch }}"
                       placeholder="{{ __('Search quotation number or customer…') }}"
                       aria-label="{{ __('Search quotation number or customer') }}"
                       class="h-10 w-full rounded-xl border border-line bg-surface-elevated pl-10 pr-10 text-sm text-content-primary placeholder:text-content-tertiary focus:border-accent-500 focus:ring-accent-500">
                @if($activeSearch !== '')
                    <a href="{{ $listUrl([], ['search']) }}" title="{{ __('Clear search') }}" aria-label="{{ __('Clear search') }}"
                       class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-xl text-content-tertiary transition-colors hover:text-content-primary">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                            <path d="M6 6l12 12M18 6 6 18" />
                        </svg>
                    </a>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <div class="min-w-44">
                    <x-glide-select name="status" size="field" :label="__('Status')"
                        :options="collect($statuses)->map(fn ($status) => ['value' => $status, 'label' => \App\Models\Proposal::statusLabel($status)])->all()"
                        :value="$activeStatus" :empty-label="__('Status: All')" autosubmit />
                </div>
                <x-icon-button as="a" icon="reset" href="{{ url()->full() }}" title="{{ __('Refresh') }}"
                               data-refresh-link onclick="this.classList.add('pointer-events-none');this.querySelector('svg')?.classList.add('animate-spin')" />
            </div>
        </form>

        {{-- Filter aktif + ringkasan hasil. --}}
        <div class="mb-4 flex flex-wrap items-center gap-2">
            @if($hasFilters)
                <span class="text-xs font-medium text-content-tertiary">{{ __('Active filters:') }}</span>
                @if($activeStatus !== '')
                    <a href="{{ $listUrl([], ['status']) }}"
                       class="inline-flex h-7 items-center gap-1.5 rounded-full border border-line bg-surface-elevated px-3 text-xs font-medium text-content-secondary transition-colors hover:border-accent-400 hover:text-content-primary">
                        {{ \App\Models\Proposal::statusLabel($activeStatus) }}
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                    </a>
                @endif
                @if($activeFilter !== '')
                    <a href="{{ $listUrl([], ['filter']) }}"
                       class="inline-flex h-7 items-center gap-1.5 rounded-full border border-line bg-surface-elevated px-3 text-xs font-medium text-content-secondary transition-colors hover:border-accent-400 hover:text-content-primary">
                        {{ $activeFilter === 'expiring' ? __('Expiring Soon') : __('Won This Month') }}
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                    </a>
                @endif
                @if($activeSearch !== '')
                    <a href="{{ $listUrl([], ['search']) }}"
                       class="inline-flex h-7 items-center gap-1.5 rounded-full border border-line bg-surface-elevated px-3 text-xs font-medium text-content-secondary transition-colors hover:border-accent-400 hover:text-content-primary">
                        {{ __('Search: ":term"', ['term' => $activeSearch]) }}
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                    </a>
                @endif
                <a href="{{ route('sales.proposals.index') }}" class="text-xs font-semibold text-accent-700 underline-offset-2 hover:underline dark:text-accent-300">{{ __('Clear all') }}</a>
            @endif
            <div class="ml-auto text-xs font-medium text-content-tertiary tabular-nums" aria-live="polite">{{ $summary }}</div>
        </div>

        <div class="rounded-2xl border border-line bg-surface shadow-sm">
            {{-- Tabel desktop/tablet. --}}
            <div class="hidden md:block">
                <div class="overflow-x-auto lg:overflow-x-visible lg:overflow-y-visible">
                    <table class="min-w-full">
                        <thead class="sticky top-0 z-10 bg-surface">
                            <tr class="border-b border-line">
                                <th scope="col" aria-sort="{{ $ariaSort('number') }}" class="border-b border-line bg-surface w-48 px-4 py-3 text-left text-xs font-semibold text-content-secondary">
                                    <a href="{{ $sortUrl('number') }}" class="inline-flex items-center gap-1 transition-colors hover:text-content-primary">
                                        {{ __('Quotation No.') }}
                                        @if($sortKey === 'number')
                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                                                <path d="{{ $sortDir === 'asc' ? 'M6 15l6-6 6 6' : 'M6 9l6 6 6-6' }}" />
                                            </svg>
                                        @endif
                                    </a>
                                </th>
                                <th scope="col" aria-sort="{{ $ariaSort('customer') }}" class="border-b border-line bg-surface min-w-56 px-4 py-3 text-left text-xs font-semibold text-content-secondary">
                                    <a href="{{ $sortUrl('customer') }}" class="inline-flex items-center gap-1 transition-colors hover:text-content-primary">
                                        {{ __('Customer') }}
                                        @if($sortKey === 'customer')
                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                                                <path d="{{ $sortDir === 'asc' ? 'M6 15l6-6 6 6' : 'M6 9l6 6 6-6' }}" />
                                            </svg>
                                        @endif
                                    </a>
                                </th>
                                <th scope="col" aria-sort="{{ $ariaSort('amount') }}" class="border-b border-line bg-surface w-36 px-4 py-3 text-right text-xs font-semibold text-content-secondary">
                                    <a href="{{ $sortUrl('amount') }}" class="inline-flex items-center gap-1 transition-colors hover:text-content-primary">
                                        {{ __('Amount') }}
                                        @if($sortKey === 'amount')
                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                                                <path d="{{ $sortDir === 'asc' ? 'M6 15l6-6 6 6' : 'M6 9l6 6 6-6' }}" />
                                            </svg>
                                        @endif
                                    </a>
                                </th>
                                <th scope="col" aria-sort="{{ $ariaSort('status') }}" class="border-b border-line bg-surface w-44 px-4 py-3 text-left text-xs font-semibold text-content-secondary">
                                    <a href="{{ $sortUrl('status') }}" class="inline-flex items-center gap-1 transition-colors hover:text-content-primary">
                                        {{ __('Status') }}
                                        @if($sortKey === 'status')
                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                                                <path d="{{ $sortDir === 'asc' ? 'M6 15l6-6 6 6' : 'M6 9l6 6 6-6' }}" />
                                            </svg>
                                        @endif
                                    </a>
                                </th>
                                <th scope="col" aria-sort="{{ $ariaSort('valid_until') }}" class="border-b border-line bg-surface w-36 px-4 py-3 text-left text-xs font-semibold text-content-secondary">
                                    <a href="{{ $sortUrl('valid_until') }}" class="inline-flex items-center gap-1 transition-colors hover:text-content-primary">
                                        {{ __('Valid Until') }}
                                        @if($sortKey === 'valid_until')
                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                                                <path d="{{ $sortDir === 'asc' ? 'M6 15l6-6 6 6' : 'M6 9l6 6 6-6' }}" />
                                            </svg>
                                        @endif
                                    </a>
                                </th>
                                <th scope="col" class="border-b border-line bg-surface hidden w-36 px-4 py-3 text-left text-xs font-semibold text-content-secondary lg:table-cell">{{ __('Sales PIC') }}</th>
                                <th scope="col" aria-sort="{{ $ariaSort('created_at') }}" class="border-b border-line bg-surface hidden w-32 px-4 py-3 text-left text-xs font-semibold text-content-secondary lg:table-cell">
                                    <a href="{{ $sortUrl('created_at') }}" class="inline-flex items-center gap-1 transition-colors hover:text-content-primary">
                                        {{ __('Created') }}
                                        @if($sortKey === 'created_at')
                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                                                <path d="{{ $sortDir === 'asc' ? 'M6 15l6-6 6 6' : 'M6 9l6 6 6-6' }}" />
                                            </svg>
                                        @endif
                                    </a>
                                </th>
                                <th scope="col" class="border-b border-line bg-surface w-12 px-2 py-3 text-center text-xs font-semibold text-content-secondary">
                                    <span class="sr-only">{{ __('Actions') }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($proposals as $proposal)
                                @php
                                    $showUrl = route('sales.proposals.show', $proposal);
                                    $customerName = $proposal->customer ?: $proposal->lead?->customer?->name;
                                    $amount = (float) $proposal->grand_total;
                                    $validUntil = $proposal->valid_until ? \Illuminate\Support\Carbon::parse($proposal->valid_until) : null;
                                    $isFinal = in_array($proposal->status, ['accepted', 'rejected', 'cancelled'], true);
                                    $daysLeft = $validUntil
                                        ? (int) now()->startOfDay()->diffInDays($validUntil->copy()->startOfDay(), false)
                                        : null;
                                    $isExpired = $proposal->status === 'expired'
                                        || (! $isFinal && $daysLeft !== null && $daysLeft < 0);
                                    $isExpiring = ! $isFinal && ! $isExpired && $daysLeft !== null && $daysLeft <= 7;
                                @endphp
                                <tr data-proposal-row data-href="{{ $showUrl }}" tabindex="0" role="link"
                                    class="h-14 cursor-pointer border-b border-line transition-colors last:border-0 hover:bg-surface-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent-500">
                                    <td class="px-4 py-3">
                                        <span class="block truncate text-sm font-semibold text-content-primary" title="{{ $proposal->proposal_number }}">{{ $proposal->proposal_number }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="block max-w-xs truncate text-sm text-content-secondary" title="{{ $customerName }}">{{ $customerName ?: '-' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if($amount > 0)
                                            <span class="whitespace-nowrap text-sm font-semibold tabular-nums text-content-primary">Rp {{ number_format($amount, 0, ',', '.') }}</span>
                                        @else
                                            <span class="text-sm font-semibold text-content-tertiary" title="{{ __('No items yet') }}">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-proposal-status-badge :status="$proposal->status" />
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm tabular-nums">
                                        @if($proposal->status === 'expired' || $validUntil)
                                            @if($isExpired)
                                                <span class="inline-flex items-center gap-1.5 font-semibold" style="color: var(--danger);">
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="8.5" /><path d="M12 7.5V12l3 2" /></svg>
                                                    @if($validUntil)
                                                        {{ $validUntil->translatedFormat('d M Y') }}
                                                    @endif
                                                    <span class="text-xs">{{ __('Expired') }}</span>
                                                </span>
                                            @elseif($isExpiring)
                                                <span class="inline-flex items-center gap-1.5 font-semibold" style="color: var(--warning);">
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4.5 21 20H3L12 4.5Z" /><path d="M12 10v4M12 17h.01" /></svg>
                                                    {{ $validUntil->translatedFormat('d M Y') }}
                                                    <span class="text-xs">{{ __('in :days d', ['days' => max(0, $daysLeft)]) }}</span>
                                                </span>
                                            @else
                                                <span class="text-content-secondary">{{ $validUntil->translatedFormat('d M Y') }}</span>
                                            @endif
                                        @else
                                            <span class="text-content-tertiary">-</span>
                                        @endif
                                    </td>
                                    <td class="hidden px-4 py-3 lg:table-cell">
                                        @if($proposal->creator)
                                            <span class="inline-flex items-center gap-2">
                                                <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-surface-elevated text-xs font-semibold uppercase text-content-secondary" aria-hidden="true">{{ mb_substr($proposal->creator->name, 0, 1) }}</span>
                                                <span class="max-w-24 truncate text-sm text-content-secondary" title="{{ $proposal->creator->name }}">{{ $proposal->creator->name }}</span>
                                            </span>
                                        @else
                                            <span class="text-sm text-content-tertiary">-</span>
                                        @endif
                                    </td>
                                    <td class="hidden whitespace-nowrap px-4 py-3 text-sm tabular-nums text-content-secondary lg:table-cell">
                                        {{ $proposal->created_at?->translatedFormat('d M Y') ?? '-' }}
                                    </td>
                                    <td class="px-2 py-3 text-center">
                                        <a href="{{ $showUrl }}" data-row-action title="{{ __('View details') }}" aria-label="{{ __('View details') }}"
                                           class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-content-tertiary transition-all duration-200 hover:border-line hover:bg-surface hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 dark:hover:text-accent-300">
                                            <x-icon name="eye" class="h-4 w-4" />
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-12">
                                        <x-proposal-empty-state :filtered="$hasFilters" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Kartu ringkas mobile (< 768px). --}}
            <div class="divide-y divide-line md:hidden">
                @forelse($proposals as $proposal)
                    @php
                        $validUntil = $proposal->valid_until ? \Illuminate\Support\Carbon::parse($proposal->valid_until) : null;
                        $isFinal = in_array($proposal->status, ['accepted', 'rejected', 'cancelled'], true);
                        $daysLeft = $validUntil ? (int) now()->startOfDay()->diffInDays($validUntil->copy()->startOfDay(), false) : null;
                        $isExpired = $proposal->status === 'expired' || (! $isFinal && $daysLeft !== null && $daysLeft < 0);
                        $isExpiring = ! $isFinal && ! $isExpired && $daysLeft !== null && $daysLeft <= 7;
                    @endphp
                    <a href="{{ route('sales.proposals.show', $proposal) }}" class="block px-4 py-3 transition-colors hover:bg-surface-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent-500">
                        <div class="flex items-center justify-between gap-3">
                            <span class="min-w-0 truncate text-sm font-semibold text-content-primary">{{ $proposal->proposal_number }}</span>
                            <x-proposal-status-badge :status="$proposal->status" />
                        </div>
                        <p class="mt-1 truncate text-sm text-content-secondary">{{ $proposal->customer ?: $proposal->lead?->customer?->name ?: '-' }}</p>
                        <div class="mt-2 flex items-end justify-between gap-3">
                            <span class="text-sm font-semibold tabular-nums text-content-primary">
                                @if((float) $proposal->grand_total > 0)
                                    Rp {{ number_format((float) $proposal->grand_total, 0, ',', '.') }}
                                @else
                                    <span class="text-content-tertiary" title="{{ __('No items yet') }}">-</span>
                                @endif
                            </span>
                            <span class="text-right text-xs tabular-nums">
                                @if($isExpired)
                                    <span class="font-semibold" style="color: var(--danger);">@if($validUntil){{ $validUntil->translatedFormat('d M Y') }} · @endif{{ __('Expired') }}</span>
                                @elseif($isExpiring)
                                    <span class="font-semibold" style="color: var(--warning);">{{ $validUntil?->translatedFormat('d M Y') }} · {{ __('in :days d', ['days' => max(0, $daysLeft)]) }}</span>
                                @elseif($validUntil)
                                    <span class="text-content-secondary">{{ $validUntil->translatedFormat('d M Y') }}</span>
                                @else
                                    <span class="text-content-tertiary">-</span>
                                @endif
                            </span>
                        </div>
                    </a>
                @empty
                    <div class="px-4 py-12">
                        <x-proposal-empty-state :filtered="$hasFilters" />
                    </div>
                @endforelse
            </div>

            {{-- Footer tabel: baris per halaman + hitungan + pagination. --}}
            <div class="flex flex-col gap-3 border-t border-line px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <form method="GET" action="{{ route('sales.proposals.index') }}" class="flex items-center gap-2">
                    @if(request()->has('embed'))
                        <input type="hidden" name="embed" value="1">
                    @endif
                    @if($activeSearch !== '')
                        <input type="hidden" name="search" value="{{ $activeSearch }}">
                    @endif
                    @if($activeStatus !== '')
                        <input type="hidden" name="status" value="{{ $activeStatus }}">
                    @endif
                    @if($activeFilter !== '')
                        <input type="hidden" name="filter" value="{{ $activeFilter }}">
                    @endif
                    @if($sortKey !== '')
                        <input type="hidden" name="sort" value="{{ $sortKey }}">
                        <input type="hidden" name="direction" value="{{ $sortDir }}">
                    @endif
                    <span class="text-xs font-medium text-content-secondary">{{ __('Rows per page') }}</span>
                    <div class="w-20">
                        <x-glide-select name="per_page" size="field" :label="__('Rows per page')"
                            :options="[['value' => '10', 'label' => '10'], ['value' => '25', 'label' => '25'], ['value' => '50', 'label' => '50']]"
                            :value="(string) request('per_page', '10')" autosubmit />
                    </div>
                </form>

                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-xs font-medium text-content-tertiary tabular-nums">{{ $summary }}</span>
                    @if($proposals->hasPages())
                        {{ $proposals->onEachSide(1)->links('vendor.pagination.quotations') }}
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            if (window.__quotationRowsBound) return;
            window.__quotationRowsBound = true;

            var openRow = function (row) {
                if (!row || !row.dataset.href) return;
                window.location.href = row.dataset.href;
            };

            document.addEventListener('click', function (event) {
                var action = event.target.closest('[data-row-action]');
                if (action) return;
                if (window.getSelection && String(window.getSelection()).length > 0) return;
                var row = event.target.closest('[data-proposal-row]');
                if (row) openRow(row);
            });

            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                var row = event.target.closest('[data-proposal-row]');
                if (!row) return;
                event.preventDefault();
                openRow(row);
            });
        })();
    </script>
</x-app-layout>
