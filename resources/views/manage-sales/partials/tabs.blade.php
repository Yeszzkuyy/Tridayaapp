{{-- Tab tunggal Manage Sales: kelola lead, KPI & target, dan aktivitas tim. --}}
@php
    $managementTabs = [
        [
            'label' => __('Lead Assignment'),
            'route' => route('manage-sales.index'),
            'active' => request()->routeIs('manage-sales.index', 'manage-sales.edit', 'manage-sales.sales-rep'),
        ],
        [
            'label' => __('Sales KPI & Target'),
            'route' => route('manage-sales.kpi'),
            'active' => request()->routeIs('manage-sales.kpi*'),
        ],
        [
            'label' => __('Team Activity'),
            'route' => route('manage-sales.team-activity'),
            'active' => request()->routeIs('manage-sales.team-activity'),
        ],
    ];
@endphp

<nav class="mb-6 flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800"
     aria-label="{{ __('Management') }}">
    @foreach($managementTabs as $tab)
        <a wire:navigate.hover href="{{ $tab['route'] }}"
           aria-current="{{ $tab['active'] ? 'page' : 'false' }}"
           class="inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-xl px-4 text-sm font-semibold transition-all duration-300 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-800 {{ $tab['active'] ? 'bg-accent-600 text-white shadow-sm shadow-accent-500/30' : 'text-slate-600 hover:bg-white hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
