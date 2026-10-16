<header class="app-header sticky top-0 z-30 bg-white/85 dark:bg-zinc-900 backdrop-blur-xl border-b border-slate-200/70 dark:border-zinc-800 h-16 sm:h-20 flex items-center justify-between px-4 sm:px-8 lg:px-8">
    <div class="flex items-center gap-3">
        {{-- Hamburger, hanya tampil di mobile --}}
        <button id="hamburgerBtn" type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-600 transition-all duration-300 hover:scale-105 hover:bg-accent-500/10 hover:text-accent-600 active:scale-95 lg:hidden dark:text-slate-300 dark:hover:bg-accent-400/10 dark:hover:text-accent-400"
                aria-label="Toggle Sidebar">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        {{-- Breadcrumb dinamis: label mengikuti locale & menampilkan menu (grup + sub-menu) sesuai route --}}
        @php
            $currentRoute = request()->route()?->getName() ?? '';
            $dashboardUrl = \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : url('/');
            $crumbs = [['label' => __('Dashboard'), 'url' => $dashboardUrl]];

            // [prefix route, [label grup, label sub-menu, ...]]. Prefix terpanjang dicocokkan lebih dulu,
            // supaya mis. 'leads.pipeline' tidak tertelan 'leads'.
            $crumbMap = [
                ['manage-sales.activity-log',       ['Management', 'Activity Log']],
                ['manage-sales',                    ['Management', 'Manage Sales']],
                ['manage.marketing',                ['Management', 'Manage Marketing']],
                ['manage.technical',                ['Management', 'Manage Technical']],
                ['manage.admin',                    ['Management', 'Manage Admin']],
                ['teknisi.dashboard',               ['Teknisi', 'Dashboard Teknisi']],
                ['teknisi.jadwal',                  ['Teknisi', 'Jadwal']],
                ['teknisi.surveys',                 ['Teknisi', 'Survey']],
                ['teknisi.sizing-projects',         ['Teknisi', 'Sizing Project']],
                ['teknisi.request-hargas',          ['Teknisi', 'Request Harga']],
                ['teknisi.instalasis',              ['Teknisi', 'Instalasi']],
                ['teknisi.documents',               ['Teknisi', 'Document']],
                ['projects',                        ['Teknisi', 'Project']],
                ['marketing.dashboard',             ['Marketing', 'Marketing Dashboard']],
                ['whatsapp-center',                 ['Marketing', 'WhatsApp Center']],
                ['sales.dashboard',                 ['Sales', 'Sales Dashboard']],
                ['sales.my-leads',                  ['Sales', 'My Leads']],
                ['sales.follow-ups',                ['Sales', 'Follow Up']],
                ['sales.schedules',                 ['Sales', 'Schedule']],
                ['sales.proposals',                 ['Sales', 'Proposal']],
                ['products',                        ['Sales', 'Products']],
                ['sales.pocs',                      ['Sales', 'POC/Demo']],
                ['sales.requests',                  ['Sales', 'Request']],
                ['sales.technical-requests',        ['Sales', 'Technical Request']],
                ['sales',                           ['Sales']],
                ['leads.pipeline',                  ['Sales', 'Pipeline']],
                ['leads.activities',                ['Marketing', 'Log Aktivitas']],
                ['leads.monitoring',                ['Marketing', 'Monitoring']],
                ['leads',                           ['Marketing', 'Lead / Opportunity']],
                ['partners',                        ['Marketing', 'Data Partner']],
                ['marketing',                       ['Marketing']],
                ['monitoring',                      ['Monitoring']],
                ['lead-tasks',                      ['Sales', 'Inside Sales']],
                ['admin-panel.index',               ['Admin Panel', 'User Management']],
                ['admin-panel.account-managers',    ['Admin Panel', 'Account Manager']],
                ['admin-panel.work-types',          ['Admin Panel', 'Work Type']],
                ['admin-panel.document-categories', ['Admin Panel', 'Document Category']],
                ['admin-panel.project-statuses',    ['Admin Panel', 'Project Status']],
                ['admin-panel.audit-log',           ['Admin Panel', 'Audit Log']],
                ['admin-panel.users',               ['Admin Panel', 'User Management']],
                ['admin-panel.roles',               ['Admin Panel', 'User Management']],
                ['admin-panel',                     ['Admin Panel']],
                ['admin.invoices',                  ['Admin', 'Invoice']],
                ['admin.pos',                       ['Admin', 'Purchase Order']],
                ['admin.payments',                  ['Admin', 'Payment']],
                ['admin',                           ['Admin']],
                ['trash',                           ['Trash']],
                ['knowledge-base',                  ['Knowledge Base']],
                ['product-knowledge',               ['Product KB']],
                ['customers',                       ['Customer']],
                ['customer-contacts',               ['Customer', 'Contact']],
                ['ai.assistant',                    ['AI Assistant']],
                ['settings',                        ['Settings']],
                ['profile',                         ['Profile']],
            ];
            // Aksi yang tidak menambah crumb (halaman list/POST) atau bukan halaman detail
            $plainActions = ['index', 'store', 'update', 'destroy', 'login', 'logout'];
            $actionLabels = [
                'create' => 'Add', 'edit' => 'Edit', 'show' => 'Detail',
                'import' => 'Import', 'import.execute' => 'Import',
                'preview' => 'Preview', 'convert' => 'Convert', 'export' => 'Export',
            ];
            $groupIconMap = [
                'Management' => 'briefcase', 'Teknisi' => 'tools', 'Marketing' => 'chart-bar',
                'Sales' => 'calendar', 'Admin' => 'folder', 'Admin Panel' => 'settings',
                'Customer' => 'users', 'Monitoring' => 'chart-bar', 'Dashboard' => 'grid',
                'Trash' => 'trash', 'Knowledge Base' => 'book', 'Product KB' => 'book',
                'Settings' => 'settings', 'Profile' => 'user', 'AI Assistant' => 'message',
            ];

            $matched = null;
            foreach ($crumbMap as $entry) {
                if (str_starts_with($currentRoute, $entry[0])) { $matched = $entry; break; }
            }

            if ($matched !== null) {
                [$prefix, $labels] = $matched;
                foreach ($labels as $label) {
                    $crumbs[] = ['label' => __($label), 'url' => null];
                }
                $rest = ltrim(substr($currentRoute, strlen($prefix)), '.');
                $hasActionCrumb = false;
                if ($rest !== '' && !in_array($rest, $plainActions, true)) {
                    $action = $actionLabels[$rest] ?? \Illuminate\Support\Str::headline(str_replace(['-', '_'], ' ', $rest));
                    $crumbs[] = ['label' => __($action), 'url' => null];
                    $hasActionCrumb = true;
                }
                if ($hasActionCrumb) {
                    $indexRoute = $prefix.'.index';
                    $indexPos = count($crumbs) - 2;
                    if ($indexPos >= 1 && \Illuminate\Support\Facades\Route::has($indexRoute)) {
                        $crumbs[$indexPos]['url'] = route($indexRoute);
                    }
                }
                $sectionLabel = __($labels[0]);
                $sectionIcon = $groupIconMap[$labels[0]] ?? 'grid';
            } else {
                // Cadangan untuk route yang belum terpetakan di atas
                $routeParts = explode('.', $currentRoute);
                $section = $routeParts[0] ?? 'dashboard';
                if ($section !== 'dashboard') {
                    $sectionIndex = $section.'.index';
                    $sectionLabel = ucwords(str_replace(['-', '_'], ' ', $section));
                    $crumbs[] = [
                        'label' => $sectionLabel,
                        'url' => \Illuminate\Support\Facades\Route::has($sectionIndex) ? route($sectionIndex) : null,
                    ];
                    $action = end($routeParts);
                    if ($action !== '' && $action !== 'index' && $action !== $section) {
                        $crumbs[] = [
                            'label' => $actionLabels[$action] ?? ucwords(str_replace(['-', '_'], ' ', $action)),
                            'url' => null,
                        ];
                    }
                } else {
                    $sectionLabel = __('Dashboard');
                }
                $sectionIcon = 'grid';
            }
            $lastCrumb = count($crumbs) - 1;
        @endphp
        <span class="app-header-badge hidden shrink-0 items-center gap-1.5 rounded-full bg-accent-500/10 px-3 py-1.5 text-xs font-semibold text-accent-700 sm:inline-flex dark:bg-accent-400/10 dark:text-accent-300">
            <x-icon :name="$sectionIcon" class="h-3.5 w-3.5" />
            {{ $sectionLabel }}
        </span>
        <nav aria-label="Breadcrumb" class="min-w-0">
            <ol class="flex min-w-0 items-center gap-1.5 text-sm">
                @foreach($crumbs as $i => $crumb)
                    @if($i > 0)
                        <svg class="h-3.5 w-3.5 shrink-0 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    @endif
                    @if($crumb['url'] && $i !== $lastCrumb)
                        <a wire:navigate.hover href="{{ $crumb['url'] }}" class="truncate text-slate-500 transition hover:text-accent-600 dark:text-slate-400 dark:hover:text-accent-400">{{ $crumb['label'] }}</a>
                    @else
                        <span @if($i === $lastCrumb) aria-current="page" @endif class="{{ $i === $lastCrumb ? 'truncate font-bold text-slate-800 dark:text-slate-100' : 'truncate text-slate-500 dark:text-slate-400' }}">{{ $crumb['label'] }}</span>
                    @endif
                @endforeach
            </ol>
        </nav>
    </div>
    <!-- ...sisanya tetap sama... -->

    <div class="flex items-center gap-2 sm:gap-3">

        {{-- Notification Center — React island (bell + feed).
             Data awal & i18n dipasang di layouts/app.blade.php (window.notifCenter).
             Bell tampil untuk SEMUA role: notifikasi database bisa diterima
             siapa saja (teknisi, marketing, sales, admin, dll).

             `wire:ignore` = bell tidak ikut diganti saat pindah menu
             (wire:navigate), jadi selalu tampil di navbar. --}}
        <div data-notification-center wire:ignore class="relative shrink-0">
            {{-- Fallback statis: bell sudah tampil sejak render pertama,
                 sebelum island React selesai dimuat (React menggantinya
                 saat mount — lihat resources/js/notification-center.jsx). --}}
            <span data-notif-fallback aria-hidden="true"
                  class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-accent-600 dark:border-slate-700 dark:bg-slate-800 dark:text-accent-400">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-4-5.66V5a2 2 0 1 0-4 0v.34A6 6 0 0 0 6 11v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9" />
                </svg>
            </span>
        </div>

        <span class="hidden shrink-0 whitespace-nowrap text-[11px] tabular-nums text-slate-400 md:block dark:text-slate-300">{{ now()->translatedFormat('d M Y') }}</span>

        <span class="hidden h-8 w-px shrink-0 bg-slate-200/80 md:block dark:bg-zinc-700/80" aria-hidden="true"></span>

        <div class="hidden shrink-0 items-center whitespace-nowrap md:flex">
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                {{ \Illuminate\Support\Str::limit(auth()->user()->name, 16, '…') }}
            </p>
        </div>

        {{-- Dropdown Avatar --}}
        <div class="relative" x-data="{ open: false }">
            {{-- Avatar --}}
            <button @click="open = !open"
                    class="flex items-center justify-center rounded-full transition-all duration-300 hover:scale-105 hover:shadow-lg hover:shadow-accent-500/50 active:scale-95 shrink-0">
                <x-user-avatar :user="auth()->user()" size="w-10 h-10" text="text-sm" :clickable="false" />
            </button>

            {{-- Dropdown Menu --}}
            <div x-show="open" x-cloak
                 @click.away="open = false"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute right-0 mt-2 w-48 bg-white dark:bg-zinc-900 rounded-xl shadow-lg border border-slate-200 dark:border-zinc-800 py-2 z-50 origin-top-right">
                
                {{-- Profil --}}
                <a wire:navigate.hover href="{{ route('profile.edit') }}" 
                   class="flex items-center gap-3 px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-zinc-800 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    {{ __('Profil') }}
                </a>

                {{-- Setting --}}
                {{-- Setting SENGAJA full reload: script autosave di halaman ini
                     jalan sekali saat load; wire:navigate tidak re-run script. --}}
                <a href="{{ route('settings.edit') }}"
                   class="flex items-center gap-3 px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-zinc-800 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    {{ __('Setting') }}
                </a>

                <hr class="my-1 border-slate-200 dark:border-zinc-800">

                {{-- Logout --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" 
                            class="flex items-center gap-3 w-full px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition text-left">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        Logout
                    </button>
                </form>
            </div>
        </div>

    </div>

    <span class="pointer-events-none absolute inset-x-0 bottom-0 h-0.5 bg-gradient-to-r from-transparent via-accent-400 to-transparent" aria-hidden="true"></span>
    <span class="navline-run" aria-hidden="true"></span>

</header>
