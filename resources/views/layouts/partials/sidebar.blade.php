@php
    $navLink = 'group relative flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-400/50';
    $subNavLink = 'group relative flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-400/50';
    $navActive = 'bg-accent-500/15 text-white';
    $navInactive = 'text-slate-300 hover:bg-slate-900/[.04] hover:text-slate-900 dark:hover:bg-white/[.06] dark:hover:text-white';

    $dashboardActive = request()->routeIs('dashboard*');
    $customerActive = request()->routeIs('customers*');
    $monitoringActive = request()->routeIs('monitoring.index');
    $managementActive = request()->routeIs('manage-sales*') || request()->routeIs('manage.*');
    $technicianActive = request()->routeIs('projects*') || request()->routeIs('teknisi.*');
    $marketingActive = (request()->routeIs(['leads*', 'partners*', 'marketing.dashboard', 'whatsapp-center*']) && !request()->routeIs('leads.pipeline'));
    // Grup Sales tampil juga untuk role inside-sales; sub-menu dirakit per-item di bawah
    // (user only melihat item yang boleh ia akses).
    $salesActive = request()->routeIs('sales.*') || request()->routeIs('projects*') || request()->routeIs('leads.pipeline') || request()->routeIs('lead-tasks*') || request()->routeIs('products*');
    $adminActive = request()->routeIs('admin.invoices.*') || request()->routeIs('admin.pos.*') || request()->routeIs('admin.payments.*') || request()->routeIs('admin.purchasing.*') || request()->routeIs('admin.supplier-pos.*') || request()->routeIs('admin.suppliers.*') || request()->routeIs('admin.goods-receipts.*') || request()->routeIs('admin.goods-deliveries.*') || request()->routeIs('admin.supplier-invoices.*') || request()->routeIs('admin.bast.*');
    $adminPanelActive = request()->routeIs('admin-panel*') || request()->routeIs('dev.worklog*');

    $roleName = auth()->user()->roles->first()?->name ?? 'User';

    $hasMarketingMonitoring = auth()->user()->can('monitor-marketing');
    $hasSalesProject = auth()->user()->can('view-technician') || auth()->user()->can('view-sales');

    // Sub-menu Sales dirakit dari item yang benar-benar boleh diakses user ini.
    $canViewSales = auth()->user()->can('view-sales');
    $salesItems = array_values(array_filter([
        $canViewSales ? ['route' => route('sales.dashboard'), 'match' => 'sales.dashboard', 'label' => 'Dashboard', 'dot' => 'bg-violet-400'] : null,
        $canViewSales ? ['route' => route('sales.my-leads'), 'match' => 'sales.my-leads', 'label' => 'My Leads', 'dot' => 'bg-amber-400'] : null,
        $canViewSales ? ['route' => route('sales.follow-ups.index'), 'match' => 'sales.follow-ups.*', 'label' => 'Follow Up', 'dot' => 'bg-green-400'] : null,
        $canViewSales ? ['route' => route('sales.meetings.index'), 'match' => 'sales.meetings.*', 'label' => 'Meeting', 'dot' => 'bg-rose-400'] : null,
        (auth()->user()->can('manage-inside-sales') || $canViewSales) ? ['route' => route('sales.schedules.index'), 'match' => 'sales.schedules.*', 'label' => 'Schedule', 'dot' => 'bg-teal-400'] : null,
        (auth()->user()->can('manage-inside-sales') || $canViewSales) ? ['route' => route('sales.proposals.index'), 'match' => 'sales.proposals.*', 'label' => 'Quotation', 'dot' => 'bg-lime-400'] : null,
        $canViewSales ? ['route' => route('leads.pipeline'), 'match' => 'leads.pipeline', 'label' => 'Pipeline', 'dot' => 'bg-sky-400'] : null,
        $hasSalesProject ? ['route' => route('projects.index'), 'match' => 'projects*', 'label' => 'Project', 'dot' => 'bg-cyan-400'] : null,
        // Sales (dan admin read-only): satu pintu Request (3 tab). Inside-sales
        // murni tanpa view-sales tetap memakai menu kerja Inside Sales.
        $canViewSales ? ['route' => route('sales.requests.index'), 'match' => 'sales.requests*', 'label' => 'Request', 'dot' => 'bg-fuchsia-400'] : null,
        (!$canViewSales && auth()->user()->can('manage-inside-sales')) ? ['route' => route('lead-tasks.index'), 'match' => 'lead-tasks*', 'label' => 'Inside Sales', 'dot' => 'bg-fuchsia-400'] : null,
    ]));
    // Header grup harus menuju halaman pertama yang boleh dibuka user ini (mis. inside-sales).
    $salesLanding = $salesItems[0]['route'] ?? route('dashboard');
    // Hub management (punya manage-sales-leads): tiap grup divisi hanya
    // tampilkan item recap bila tidak pegang manage-* divisi itu.
    // Multi-role didukung: sales+management tetap kerja penuh di sales.
    $isMgmtHub = auth()->user()->can('manage-sales-leads');
    $tekRecap = $isMgmtHub && !auth()->user()->can('manage-technician');
    $mktRecap = $isMgmtHub && !auth()->user()->can('manage-marketing');
    $salesRecap = $isMgmtHub && !auth()->user()->can('manage-sales');
    if ($salesRecap) {
        $salesItems = array_values(array_filter($salesItems,
            fn ($i) => in_array($i['label'], ['Dashboard'], true)));
        $salesLanding = $salesItems[0]['route'] ?? route('dashboard');
    }
@endphp

<aside class="relative flex h-full w-full flex-col overflow-hidden">
@persist('sidebar')
    {{-- Aurora mesh background (dekoratif, di belakang konten) --}}
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute -right-20 -top-24 h-64 w-64 rounded-full bg-accent-500/15 blur-3xl"></div>
        <div class="absolute -left-24 top-1/3 h-72 w-72 rounded-full bg-indigo-500/10 blur-3xl"></div>
        <div class="absolute -bottom-24 right-0 h-56 w-56 rounded-full bg-cyan-400/10 blur-3xl"></div>
    </div>

    {{-- Logo --}}
    <div class="sidebar-logo relative z-10 flex h-16 sm:h-20 flex-shrink-0 items-center border-b border-white/10 px-4">
        <a wire:navigate.hover href="{{ route('dashboard') }}" class="group flex items-center gap-3">
            <picture class="shrink-0 dark:hidden">
                <source srcset="{{ asset('images/logo/Logo3dylight-256.webp') }}" type="image/webp">
                <img src="{{ asset('images/logo/Logo3dylight.png') }}" alt="Tridaya App" width="256" height="256"
                     fetchpriority="high" decoding="async"
                     class="h-9 sm:h-11 w-auto aspect-square object-contain transition-transform duration-300 group-hover:scale-[1.03]">
            </picture>
            <picture class="hidden shrink-0 dark:block">
                <source srcset="{{ asset('images/logo/Logo3dydark-256.webp') }}" type="image/webp">
                <img src="{{ asset('images/logo/Logo3dydark.png') }}" alt="Tridaya App" width="256" height="256"
                     fetchpriority="high" decoding="async"
                     class="h-9 sm:h-11 w-auto aspect-square object-contain transition-transform duration-300 group-hover:scale-[1.03]">
            </picture>
            <div class="sidebar-hide min-w-0">
                <h1 class="truncate font-display text-4xl font-bold leading-none text-accent-300">3DY App</h1>
            </div>
        </a>
    </div>

    {{-- Menu --}}
    <nav id="sidebar-navigation" aria-label="{{ __('Main navigation') }}" class="sidebar-nav relative z-10 flex-1 min-h-0 overflow-y-auto overscroll-contain px-3 py-4"
         data-nav-active="{{ $navActive }} sb-active" data-nav-inactive="{{ $navInactive }}">
        <div class="space-y-6">
            <section aria-labelledby="sidebar-main-label">
                <p id="sidebar-main-label" class="sidebar-hide px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">{{ __('Main') }}</p>
                <div class="space-y-1">
                    <a wire:navigate.hover href="{{ route('dashboard') }}"
                       aria-current="{{ $dashboardActive ? 'page' : 'false' }}"
                       class="{{ $navLink }} {{ $dashboardActive ? $navActive : $navInactive }}">
                        <x-icon name="grid" class="h-5 w-5 shrink-0" />
                        <span>{{ __('Dashboard') }}</span>
                    </a>
                    <a wire:navigate.hover href="{{ route('customers.index') }}"
                       aria-current="{{ $customerActive ? 'page' : 'false' }}"
                       class="{{ $navLink }} {{ $customerActive ? $navActive : $navInactive }}">
                        <x-icon name="users" class="h-5 w-5 shrink-0" />
                        <span>{{ __('Customer') }}</span>
                    </a>
                    {{-- Monitoring lintas divisi: management, ceo, super-admin (via Gate::before) --}}
                    @can('view-monitoring')
                        <a wire:navigate.hover href="{{ route('monitoring.index') }}"
                           aria-current="{{ $monitoringActive ? 'page' : 'false' }}"
                           class="{{ $navLink }} {{ $monitoringActive ? $navActive : $navInactive }}">
                            <x-icon name="chart-bar" class="h-5 w-5 shrink-0" />
                            <span>{{ __('Monitoring') }}</span>
                        </a>
                    @endcan
                    {{-- AI Assistant SENGAJA full reload (tanpa wire:navigate):
                         halaman chat stateful; morph berisiko merusak riwayat/ketikan. --}}
                    <a href="{{ route('ai.assistant.index') }}"
                       aria-current="{{ request()->routeIs('ai.assistant*') ? 'page' : 'false' }}"
                       class="{{ $navLink }} {{ request()->routeIs('ai.assistant*') ? $navActive : $navInactive }}">
                        <x-icon name="message" class="h-5 w-5 shrink-0" />
                        <span>{{ __('AI Assistant') }}</span>
                    </a>
                </div>
            </section>

            @canany(['manage-sales-leads', 'view-technician', 'view-marketing', 'view-sales', 'view-admin', 'manage-inside-sales'])
                <section aria-labelledby="sidebar-departments-label">
                    <p id="sidebar-departments-label" class="sidebar-hide px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">{{ __('Departments') }}</p>
                    <div class="space-y-1">
                        {{-- Management (Management Hub) --}}
                        @can('manage-sales-leads')
                            <div x-data="{ open: {{ $managementActive ? 'true' : 'false' }} }" class="sb-group"{{ $managementActive ? 'data-open' : '' }} :data-open="open ? '' : null" data-sb-group="management">
                                <div class="sb-head {{ $navLink }} group w-full {{ $managementActive ? $navActive.' sb-active' : $navInactive }}">
                                    <a wire:navigate.hover href="{{ route('manage-sales.index') }}"
                                            class="flex min-w-0 flex-1 items-center gap-3 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-400/50">
                                        <x-icon name="briefcase" class="h-5 w-5 shrink-0" />
                                        <span>{{ __('Management') }}</span>
                                        <span class="ml-auto flex shrink-0 items-center gap-1.5 sidebar-hide">
                                            <template x-if="$store.notif.unassigned > 0">
                                                <span class="h-2.5 w-2.5 rounded-full bg-red-500 ring-2 ring-white dark:ring-slate-800" title="{{ __('Unassigned leads') }}"></span>
                                            </template>
                                        </span>
                                    </a>
                                    <button type="button" class="sb-toggle sidebar-hide" @click="open = !open"
                                            :aria-expanded="open" aria-controls="sidebar-management-menu"
                                            aria-label="{{ __('Open/close submenu') }}">
                                        <svg class="h-4 w-4 transition-transform duration-300" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                </div>
                                <div id="sidebar-management-menu" class="sb-sub sidebar-hide">
                                    <div class="sb-sub-list">
                                    <a wire:navigate.hover href="{{ route('manage-sales.index') }}"
                                       aria-current="{{ request()->routeIs('manage-sales.index', 'manage-sales.edit') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('manage-sales.index', 'manage-sales.edit') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-green-400" aria-hidden="true"></span>
                                        <span>{{ __('Manage Sales') }}</span>
                                        <template x-if="$store.notif.unassigned > 0">
                                            <span class="ml-auto h-2.5 w-2.5 rounded-full bg-red-500 ring-2 ring-white dark:ring-slate-800" title="{{ __('Unassigned leads') }}"></span>
                                        </template>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('manage-sales.kpi') }}"
                                       aria-current="{{ request()->routeIs('manage-sales.kpi*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('manage-sales.kpi*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-blue-400" aria-hidden="true"></span>
                                        <span>{{ __('Sales KPI & Target') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('manage.marketing.index') }}"
                                       aria-current="{{ request()->routeIs('manage.marketing*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('manage.marketing*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400" aria-hidden="true"></span>
                                        <span>{{ __('Manage Marketing') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('manage.technical.index') }}"
                                       aria-current="{{ request()->routeIs('manage.technical*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('manage.technical*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-cyan-400" aria-hidden="true"></span>
                                        <span>{{ __('Manage Technical') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('manage.admin.index') }}"
                                       aria-current="{{ request()->routeIs('manage.admin*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('manage.admin*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-violet-400" aria-hidden="true"></span>
                                        <span>{{ __('Manage Admin') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('manage-sales.activity-log') }}"
                                       aria-current="{{ request()->routeIs('manage-sales.activity-log') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('manage-sales.activity-log') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-orange-400" aria-hidden="true"></span>
                                        <span>{{ __('Activity Log') }}</span>
                                    </a>
                                    </div>
</div>
</div>
                        @endcan

                        {{-- Teknisi --}}
                        @can('view-technician')
                            <div x-data="{ open: {{ $technicianActive ? 'true' : 'false' }} }" class="sb-group"{{ $technicianActive ? 'data-open' : '' }} :data-open="open ? '' : null" data-sb-group="teknisi">
                                <div class="sb-head {{ $navLink }} group w-full {{ $technicianActive ? $navActive.' sb-active' : $navInactive }}">
                                    <a wire:navigate.hover href="{{ route('teknisi.dashboard') }}"
                                            class="flex min-w-0 flex-1 items-center gap-3 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-400/50">
                                        <x-icon name="tools" class="h-5 w-5 shrink-0" />
                                        <span>{{ __('Technician') }}</span>
                                    </a>
                                    <button type="button" class="sb-toggle sidebar-hide" @click="open = !open"
                                            :aria-expanded="open" aria-controls="sidebar-technician-menu"
                                            aria-label="{{ __('Open/close submenu') }}">
                                        <svg class="h-4 w-4 transition-transform duration-300" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                </div>
                                <div id="sidebar-technician-menu" class="sb-sub sidebar-hide">
                                    <div class="sb-sub-list">
                                     <a wire:navigate.hover href="{{ route('teknisi.dashboard') }}"
                                        aria-current="{{ request()->routeIs('teknisi.dashboard*') ? 'page' : 'false' }}"
                                        class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('teknisi.dashboard*') ? $navActive : $navInactive }}">
                                         <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-blue-400" aria-hidden="true"></span>
                                         <span>{{ __('Technician Dashboard') }}</span>
                                     </a>
                                     @if(auth()->user()->hasRole('technician') || auth()->user()->hasRole('super-admin'))
                                     <a wire:navigate.hover href="{{ route('teknisi.tasks.index') }}"
                                        aria-current="{{ request()->routeIs('teknisi.tasks*') ? 'page' : 'false' }}"
                                        class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('teknisi.tasks*') ? $navActive : $navInactive }}">
                                         <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-lime-400" aria-hidden="true"></span>
                                         <span>{{ auth()->user()->hasRole('super-admin') ? __('Technician Tasks') : __('My Tasks') }}</span>
                                     </a>
                                     @endif
                                     <a wire:navigate.hover href="{{ route('teknisi.requests.index') }}"
                                        aria-current="{{ request()->routeIs(['teknisi.requests*', 'teknisi.price-requests*', 'teknisi.technical-requests*']) ? 'page' : 'false' }}"
                                        class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs(['teknisi.requests*', 'teknisi.price-requests*', 'teknisi.technical-requests*']) ? $navActive : $navInactive }}">
                                         <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-rose-400" aria-hidden="true"></span>
                                         <span>{{ __('Request Sales') }}</span>
                                     </a>
                                     <a wire:navigate.hover href="{{ route('projects.index') }}"
                                        aria-current="{{ request()->routeIs('projects*') ? 'page' : 'false' }}"
                                        class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('projects*') ? $navActive : $navInactive }}">
                                         <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-cyan-400" aria-hidden="true"></span>
                                         <span>{{ __('Project') }}</span>
                                     </a>
                                     <a wire:navigate.hover href="{{ route('teknisi.jadwal') }}"
                                        aria-current="{{ request()->routeIs('teknisi.jadwal*') ? 'page' : 'false' }}"
                                        class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('teknisi.jadwal*') ? $navActive : $navInactive }}">
                                         <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-violet-400" aria-hidden="true"></span>
                                         <span>{{ __('Jadwal') }}</span>
                                     </a>
                                     @unless($tekRecap)
                                     <a wire:navigate.hover href="{{ route('teknisi.surveys.index') }}"
                                       aria-current="{{ request()->routeIs('teknisi.surveys*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('teknisi.surveys*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-yellow-400" aria-hidden="true"></span>
                                        <span>{{ __('Survey') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('teknisi.sizing-projects.index') }}"
                                       aria-current="{{ request()->routeIs('teknisi.sizing-projects*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('teknisi.sizing-projects*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-orange-400" aria-hidden="true"></span>
                                        <span>{{ __('Sizing Project') }}</span>
                                    </a>
                                     @endunless
                                     <a wire:navigate.hover href="{{ route('teknisi.instalasis.index') }}"
                                       aria-current="{{ request()->routeIs('teknisi.instalasis*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('teknisi.instalasis*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-400" aria-hidden="true"></span>
                                         <span>{{ __('Installation') }}</span>
                                     </a>
                                     @unless($tekRecap)
                                     <a wire:navigate.hover href="{{ route('teknisi.documents.index') }}"
                                       aria-current="{{ request()->routeIs('teknisi.documents*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('teknisi.documents*') ? $navActive : $navInactive }}">
                                         <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-400" aria-hidden="true"></span>
                                         <span>{{ __('Document') }}</span>
                                     </a>
                                     @endunless
                                     </div>
</div>
</div>
                        @endcan

                        {{-- Marketing --}}
                        @can('view-marketing')
                            <div x-data="{ open: {{ $marketingActive ? 'true' : 'false' }} }" class="sb-group"{{ $marketingActive ? 'data-open' : '' }} :data-open="open ? '' : null" data-sb-group="marketing">
                                <div class="sb-head {{ $navLink }} group w-full {{ $marketingActive ? $navActive.' sb-active' : $navInactive }}">
                                    <a wire:navigate.hover href="{{ route('marketing.dashboard') }}"
                                            class="flex min-w-0 flex-1 items-center gap-3 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-400/50">
                                        <x-icon name="chart-bar" class="h-5 w-5 shrink-0" />
                                        <span>{{ __('Marketing') }}</span>
                                    </a>
                                    <button type="button" class="sb-toggle sidebar-hide" @click="open = !open"
                                            :aria-expanded="open" aria-controls="sidebar-marketing-menu"
                                            aria-label="{{ __('Open/close submenu') }}">
                                        <svg class="h-4 w-4 transition-transform duration-300" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                </div>
                                <div id="sidebar-marketing-menu" class="sb-sub sidebar-hide">
                                    <div class="sb-sub-list">
                                    <a wire:navigate.hover href="{{ route('marketing.dashboard') }}"
                                       aria-current="{{ request()->routeIs('marketing.dashboard') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('marketing.dashboard') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-400" aria-hidden="true"></span>
                                         <span>{{ __('Dashboard') }}</span>
                                     </a>
                                     @unless($mktRecap)
                                     {{-- WhatsApp Center SENGAJA full reload (tanpa wire:navigate):
                                         aplikasi Alpine raksasa + chat state; morph berisiko merusak. --}}
                                    <a href="{{ route('whatsapp-center.index') }}"
                                       aria-current="{{ request()->routeIs('whatsapp-center*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('whatsapp-center*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-green-400" aria-hidden="true"></span>
                                        <span>WhatsApp Center</span>
                                    </a>
                                    @endunless
                                    <a wire:navigate.hover href="{{ route('leads.index') }}"
                                       aria-current="{{ request()->routeIs(['leads.index', 'leads.show', 'leads.edit']) ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs(['leads.index', 'leads.show', 'leads.edit']) ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400" aria-hidden="true"></span>
                                        <span>{{ __('Lead / Opportunity') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('partners.index') }}"
                                       aria-current="{{ request()->routeIs('partners*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('partners*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-teal-400" aria-hidden="true"></span>
                                         <span>{{ __('Partners') }}</span>
                                    </a>
                                    @unless($mktRecap)
                                    <a wire:navigate.hover href="{{ route('leads.activities') }}"
                                       aria-current="{{ request()->routeIs('leads.activities') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('leads.activities') ? $navActive : $navInactive }}">
                                         <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-rose-400" aria-hidden="true"></span>
                                         <span>{{ __('Activity Log') }}</span>
                                     </a>
                                     @endunless
                                     @can('monitor-marketing')
                                     @unless($mktRecap)
                                         <a wire:navigate.hover href="{{ route('leads.monitoring') }}"
                                           aria-current="{{ request()->routeIs('leads.monitoring') ? 'page' : 'false' }}"
                                           class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('leads.monitoring') ? $navActive : $navInactive }}">
                                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-violet-400" aria-hidden="true"></span>
                                            <span>{{ __('Monitoring') }}</span>
                                        </a>
                                     @endunless
                                    @endcan
                                    </div>
</div>
</div>
                        @endcan

                        {{-- Sales --}}
                        @canany(['view-sales', 'manage-inside-sales'])
                            <div x-data="{ open: {{ $salesActive ? 'true' : 'false' }} }" class="sb-group"{{ $salesActive ? 'data-open' : '' }} :data-open="open ? '' : null" data-sb-group="sales">
                                <div class="sb-head {{ $navLink }} group w-full {{ $salesActive ? $navActive.' sb-active' : $navInactive }}">
                                    <a wire:navigate.hover href="{{ $salesLanding }}"
                                            class="flex min-w-0 flex-1 items-center gap-3 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-400/50">
                                        <x-icon name="calendar" class="h-5 w-5 shrink-0" />
                                        <span>{{ __('Sales') }}</span>
                                    </a>
                                    <button type="button" class="sb-toggle sidebar-hide" @click="open = !open"
                                            :aria-expanded="open" aria-controls="sidebar-sales-menu"
                                            aria-label="{{ __('Open/close submenu') }}">
                                        <svg class="h-4 w-4 transition-transform duration-300" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                </div>
                                <div id="sidebar-sales-menu" class="sb-sub sidebar-hide">
                                    <div class="sb-sub-list">
                                    @foreach($salesItems as $item)
                                        <a wire:navigate.hover href="{{ $item['route'] }}"
                                           aria-current="{{ request()->routeIs($item['match']) ? 'page' : 'false' }}"
                                           class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs($item['match']) ? $navActive : $navInactive }}">
                                            <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $item['dot'] }}" aria-hidden="true"></span>
                                            <span>{{ __($item['label']) }}</span>
                                        </a>
                                    @endforeach
                                    </div>
</div>
</div>
                        @endcanany

                        {{-- Admin: Invoice, PO, Payment --}}
                        @can('view-admin')
                            <div x-data="{ open: {{ $adminActive ? 'true' : 'false' }} }" class="sb-group"{{ $adminActive ? 'data-open' : '' }} :data-open="open ? '' : null" data-sb-group="admin">
                                <div class="sb-head {{ $navLink }} group w-full {{ $adminActive ? $navActive.' sb-active' : $navInactive }}">
                                    <a wire:navigate.hover href="{{ route('admin.invoices.index') }}"
                                            class="flex min-w-0 flex-1 items-center gap-3 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-400/50">
                                        <x-icon name="folder" class="h-5 w-5 shrink-0" />
                                        <span>{{ __('Admin') }}</span>
                                    </a>
                                    <button type="button" class="sb-toggle sidebar-hide" @click="open = !open"
                                            :aria-expanded="open" aria-controls="sidebar-admin-menu"
                                            aria-label="{{ __('Open/close submenu') }}">
                                        <svg class="h-4 w-4 transition-transform duration-300" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                </div>
                                <div id="sidebar-admin-menu" class="sb-sub sidebar-hide">
                                    <div class="sb-sub-list">
                                    <a wire:navigate.hover href="{{ route('admin.invoices.index') }}"
                                       aria-current="{{ request()->routeIs('admin.invoices.*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('admin.invoices.*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-400" aria-hidden="true"></span>
                                        <span>{{ __('Invoice') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('admin.pos.index') }}"
                                       aria-current="{{ request()->routeIs('admin.pos.*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('admin.pos.*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-orange-400" aria-hidden="true"></span>
                                        <span>{{ __('Purchase Order') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('admin.payments.index') }}"
                                       aria-current="{{ request()->routeIs('admin.payments.*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('admin.payments.*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-green-400" aria-hidden="true"></span>
                                        <span>{{ __('Payment') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('admin.purchasing.index') }}"
                                       aria-current="{{ request()->routeIs('admin.purchasing.*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('admin.purchasing.*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-sky-400" aria-hidden="true"></span>
                                        <span>{{ __('Purchasing') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('admin.supplier-pos.index') }}"
                                       aria-current="{{ request()->routeIs('admin.supplier-pos.*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('admin.supplier-pos.*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-violet-400" aria-hidden="true"></span>
                                        <span>{{ __('Supplier PO') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('admin.suppliers.index') }}"
                                       aria-current="{{ request()->routeIs('admin.suppliers.*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('admin.suppliers.*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-teal-400" aria-hidden="true"></span>
                                        <span>{{ __('Suppliers') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('admin.goods-receipts.index') }}"
                                       aria-current="{{ request()->routeIs('admin.goods-receipts.*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('admin.goods-receipts.*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-lime-400" aria-hidden="true"></span>
                                        <span>{{ __('Goods Receipts') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('admin.goods-deliveries.index') }}"
                                       aria-current="{{ request()->routeIs('admin.goods-deliveries.*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('admin.goods-deliveries.*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-400" aria-hidden="true"></span>
                                        <span>{{ __('Deliveries') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('admin.supplier-invoices.index') }}"
                                       aria-current="{{ request()->routeIs('admin.supplier-invoices.*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('admin.supplier-invoices.*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-rose-400" aria-hidden="true"></span>
                                        <span>{{ __('Supplier Invoices') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('admin.bast.index') }}"
                                       aria-current="{{ request()->routeIs('admin.bast.*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('admin.bast.*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400" aria-hidden="true"></span>
                                        <span>{{ __('BAST') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('admin.purchasing.reports') }}"
                                       aria-current="{{ request()->routeIs('admin.purchasing.reports') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('admin.purchasing.reports') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-cyan-400" aria-hidden="true"></span>
                                        <span>{{ __('Procurement Reports') }}</span>
                                    </a>
                                    </div>
</div>
</div>
                        @endcan

                    </div>
                </section>
            @endcanany

            @canany(['view-trash', 'manage-monitoring'])
                <section aria-labelledby="sidebar-system-label">
                    <p id="sidebar-system-label" class="sidebar-hide px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">{{ __('System') }}</p>
                    <div class="space-y-1">
                        {{-- Trash --}}
                        @can('view-trash')
                            <a wire:navigate.hover href="{{ route('trash.index') }}"
                               aria-current="{{ request()->routeIs('trash*') ? 'page' : 'false' }}"
                               class="{{ $navLink }} {{ request()->routeIs('trash*') ? $navActive : $navInactive }}">
                                <x-icon name="trash" class="h-5 w-5 shrink-0" />
                                <span>{{ __('Trash') }}</span>
                            </a>
                        @endcan

                        {{-- Knowledge Base (Admin only) --}}
                        @can('manage-admin')
                            <a wire:navigate.hover href="{{ route('knowledge-base.index') }}"
                               aria-current="{{ request()->routeIs('knowledge-base*') ? 'page' : 'false' }}"
                               class="{{ $navLink }} {{ request()->routeIs('knowledge-base*') ? $navActive : $navInactive }}">
                                <x-icon name="book" class="h-5 w-5 shrink-0" />
                                <span>{{ __('Knowledge Base') }}</span>
                            </a>
                            <a wire:navigate.hover href="{{ route('product-knowledge.index') }}"
                               aria-current="{{ request()->routeIs('product-knowledge*') ? 'page' : 'false' }}"
                               class="{{ $navLink }} {{ request()->routeIs('product-knowledge*') ? $navActive : $navInactive }}">
                                <x-icon name="book" class="h-5 w-5 shrink-0" />
                                <span>{{ __('Product KB') }}</span>
                            </a>
                        @endcan

                        {{-- Admin Panel (Super Admin only) --}}
                        @can('manage-monitoring')
                            <div x-data="{ open: {{ $adminPanelActive ? 'true' : 'false' }} }" class="sb-group"{{ $adminPanelActive ? 'data-open' : '' }} :data-open="open ? '' : null" data-sb-group="admin-panel">
                                <div class="sb-head {{ $navLink }} group w-full {{ $adminPanelActive ? $navActive.' sb-active' : $navInactive }}">
                                    <a wire:navigate.hover href="{{ route('admin-panel.index') }}"
                                            class="flex min-w-0 flex-1 items-center gap-3 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-400/50">
                                        <x-icon name="settings" class="h-5 w-5 shrink-0" />
                                        <span>{{ __('Admin Panel') }}</span>
                                    </a>
                                    <button type="button" class="sb-toggle sidebar-hide" @click="open = !open"
                                            :aria-expanded="open" aria-controls="sidebar-admin-panel-menu"
                                            aria-label="{{ __('Open/close submenu') }}">
                                        <svg class="h-4 w-4 transition-transform duration-300" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                </div>
                                <div id="sidebar-admin-panel-menu" class="sb-sub sidebar-hide">
                                    <div class="sb-sub-list">
                                    <a wire:navigate.hover href="{{ route('admin-panel.index') }}"
                                       aria-current="{{ request()->routeIs('admin-panel.index') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('admin-panel.index') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-blue-400" aria-hidden="true"></span>
                                        <span>{{ __('User Management') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('admin-panel.account-managers.index') }}"
                                       aria-current="{{ request()->routeIs('admin-panel.account-managers.*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('admin-panel.account-managers.*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-400" aria-hidden="true"></span>
                                        <span>{{ __('Account Manager') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('admin-panel.work-types.index') }}"
                                       aria-current="{{ request()->routeIs('admin-panel.work-types.*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('admin-panel.work-types.*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-400" aria-hidden="true"></span>
                                        <span>{{ __('Work Type') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('admin-panel.document-categories.index') }}"
                                       aria-current="{{ request()->routeIs('admin-panel.document-categories.*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('admin-panel.document-categories.*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-400" aria-hidden="true"></span>
                                        <span>{{ __('Document Category') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('admin-panel.project-statuses.index') }}"
                                       aria-current="{{ request()->routeIs('admin-panel.project-statuses.*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('admin-panel.project-statuses.*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-400" aria-hidden="true"></span>
                                        <span>{{ __('Project Status') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('admin-panel.audit-log') }}"
                                       aria-current="{{ request()->routeIs('admin-panel.audit-log') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('admin-panel.audit-log') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-400" aria-hidden="true"></span>
                                        <span>{{ __('Audit Log') }}</span>
                                    </a>
                                    <a wire:navigate.hover href="{{ route('dev.worklog.index') }}"
                                       aria-current="{{ request()->routeIs('dev.worklog*') ? 'page' : 'false' }}"
                                       class="sb-sub-item {{ $subNavLink }} {{ request()->routeIs('dev.worklog*') ? $navActive : $navInactive }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-400" aria-hidden="true"></span>
                                        <span>{{ __('Rekap Progres') }}</span>
                                    </a>
                                    </div>
</div>
</div>
                        @endcan
                    </div>
                </section>
            @endcanany
        </div>
    </nav>

    {{-- User widget --}}
    <div class="relative z-10 flex-shrink-0 border-t border-white/10 p-3">
        <a wire:navigate.hover href="{{ route('profile.edit') }}"
           class="sidebar-user group flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-3 py-3 transition-all duration-300 hover:border-accent-400/30 hover:bg-white/10">
            <x-user-avatar :user="auth()->user()" size="w-9 h-9" text="text-xs" :clickable="false" />
            <span class="sidebar-hide min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold text-slate-200">{{ auth()->user()->name }}</span>
                <span class="mt-0.5 block truncate text-[11px] text-slate-400">{{ \Illuminate\Support\Str::headline($roleName) }}</span>
            </span>
                        <x-icon name="chevron-right" class="sidebar-hide h-4 w-4 shrink-0 text-slate-400 transition-colors duration-300 group-hover:text-accent-300" />
        </a>
    </div>

    {{-- Toggle collapse / expand (posisi bawah, dulu logout) --}}
    <div class="relative z-10 flex-shrink-0 px-3 pb-3">
        <button id="sidebarCollapseBtn" type="button"
                class="sidebar-collapse group flex w-full items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium text-slate-300 transition-all duration-300 hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-400/50"
                aria-label="{{ __('Collapse sidebar') }}" aria-controls="sidebar" aria-expanded="true">
            <svg class="icon-collapse h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5 5-5M18 17l-5-5 5-5"/>
            </svg>
            <svg class="icon-expand h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h10M4 12h10M4 17h10M13 12h7M17 9l3 3-3 3"/>
            </svg>
            <span class="sidebar-hide">{{ __('Collapse') }}</span>
        </button>
    </div>
@endpersist
</aside>
