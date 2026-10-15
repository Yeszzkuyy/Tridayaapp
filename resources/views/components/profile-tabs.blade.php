@php
    $tabs = [
        ['route' => 'profile.edit', 'label' => __('Profil'), 'icon' => 'user'],
        ['route' => 'settings.edit', 'label' => __('Setting'), 'icon' => 'settings'],
    ];
@endphp

<div class="border-b border-slate-200 dark:border-slate-700">
    <nav class="flex gap-6" aria-label="{{ __('Profil dan Setting') }}">
        @foreach($tabs as $tab)
            @php $active = request()->routeIs($tab['route']); @endphp
            <a href="{{ route($tab['route']) }}"
               aria-current="{{ $active ? 'page' : 'false' }}"
               class="inline-flex items-center gap-2 border-b-2 px-1 pb-3 pt-1 text-sm font-semibold transition
                      {{ $active
                            ? 'border-accent-600 text-accent-600 dark:border-accent-400 dark:text-accent-300'
                            : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}">
                <x-icon name="{{ $tab['icon'] }}" class="h-4 w-4" />
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
</div>
