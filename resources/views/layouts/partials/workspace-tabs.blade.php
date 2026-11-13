{{-- Baris tab workspace di dalam navbar (menggantikan pill lokasi).
     Tab Dashboard selalu di paling kiri dan tidak bisa ditutup; tab lain
     tetap hidup di latar belakang agar ketikan dan scroll tidak hilang.
     Nama tab diambil dari label menu sidebar, bukan nama route. --}}
<div id="workspace-tabs"
     x-data
     x-init="$store.workspace && $store.workspace.init()"
     class="flex min-w-0 flex-1 items-center gap-1.5">

    <div id="workspace-tabbar"
         data-overflow="none"
         class="relative flex min-w-0 flex-1 items-center gap-1">

        {{-- Panah kiri: hanya tampil saat masih ada tab tersembunyi di kiri --}}
        <button type="button"
                data-ws-scroll="left"
                aria-label="{{ __('Scroll tabs left') }}"
                title="{{ __('Scroll tabs left') }}"
                class="ws-scroll-btn ws-scroll-btn-left hidden h-6 w-6 shrink-0 items-center justify-center rounded-lg border border-slate-200/80 bg-[var(--card-bg)] text-slate-500 shadow-sm transition hover:bg-[var(--menu-hover-bg)] hover:text-slate-800 sm:inline-flex dark:border-slate-700 dark:text-slate-300">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m15 6-6 6 6 6" />
            </svg>
        </button>

        <div id="workspace-tablist"
             role="tablist"
             aria-label="{{ __('Workspace tabs') }}"
             class="flex min-w-0 flex-1 items-center gap-1.5 overflow-x-auto no-scrollbar py-1">

            <template x-for="tab in ($store.workspace ? $store.workspace.tabs : [])" :key="tab.id">
                <div role="presentation" class="group relative flex shrink-0 items-center">
                    <button type="button"
                            role="tab"
                            :data-workspace-tab="tab.id"
                            :aria-selected="$store.workspace.activeId === tab.id ? 'true' : 'false'"
                            :tabindex="$store.workspace.activeId === tab.id ? 0 : -1"
                            @click="$store.workspace.activate(tab.id)"
                            @keydown="$store.workspace.onTabKeydown($event, tab.id)"
                            :title="$store.workspace.fullTitle(tab)"
                            :class="$store.workspace.activeId === tab.id
                                ? 'border-accent-500/50 bg-accent-500/12 text-accent-700 shadow-sm shadow-accent-500/10 dark:border-accent-400/40 dark:bg-accent-500/20 dark:text-accent-50'
                                : 'border-slate-200/80 bg-[var(--card-bg)] text-slate-600 hover:bg-[var(--menu-hover-bg)] hover:text-slate-900 dark:border-slate-700/60 dark:bg-slate-800/45 dark:text-slate-400 dark:hover:bg-slate-800/90 dark:hover:text-slate-200'"
                            class="relative inline-flex h-9 max-w-[190px] items-center gap-1.5 rounded-xl border pl-2.5 text-xs font-semibold transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/50 sm:max-w-[220px]"
                            :style="!tab.pinned ? 'padding-right: 1.9rem;' : 'padding-right: 0.65rem;'">

                        {{-- Garis bawah penanda tab aktif --}}
                        <span x-show="$store.workspace.activeId === tab.id"
                              aria-hidden="true"
                              class="pointer-events-none absolute inset-x-3 bottom-0.5 h-0.5 rounded-full bg-accent-600 dark:bg-accent-400"></span>

                        {{-- Ikon menu utama sebagai ciri khas tab --}}
                        <span :class="$store.workspace.activeId === tab.id
                                ? 'text-accent-600 dark:text-accent-300'
                                : 'text-slate-400 dark:text-slate-500'"
                              class="shrink-0"
                              x-html="$store.workspace.iconSvg(tab.icon)"></span>

                        {{-- Badge divisi: warna per divisi, hanya tag kecil --}}
                        <span x-show="tab.dept"
                              x-text="tab.dept"
                              :class="$store.workspace.deptClass(tab.dept)"
                              class="shrink-0 rounded px-1 py-0.5 text-[9px] font-bold uppercase tracking-wider"></span>

                        <span class="truncate" x-text="tab.title"></span>

                        {{-- Titik penanda isian belum disimpan --}}
                        <span x-show="tab.dirty"
                              x-cloak
                              title="{{ __('Unsaved changes') }}"
                              aria-label="{{ __('Unsaved changes') }}"
                              class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500 ring-2 ring-amber-400/30"></span>
                    </button>

                    {{-- Tombol tutup tab (tidak ada untuk Dashboard yang dipin) --}}
                    <button x-show="!tab.pinned"
                            type="button"
                            @click.stop="$store.workspace.close(tab.id)"
                            :aria-label="'{{ __('Close') }} ' + $store.workspace.fullTitle(tab)"
                            title="{{ __('Close tab') }}"
                            :class="$store.workspace.activeId === tab.id
                                ? 'opacity-90 text-accent-600/80 hover:bg-rose-500/15 hover:text-rose-600 dark:text-accent-200/80 dark:hover:bg-rose-500/20 dark:hover:text-rose-300'
                                : 'opacity-60 text-slate-400 hover:bg-rose-500/15 hover:text-rose-600 group-hover:opacity-100 dark:text-slate-500 dark:hover:bg-rose-500/20 dark:hover:text-rose-300'"
                            class="absolute right-1.5 z-10 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-md transition focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/50">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </template>
        </div>

        {{-- Panah kanan: hanya tampil saat masih ada tab tersembunyi di kanan --}}
        <button type="button"
                data-ws-scroll="right"
                aria-label="{{ __('Scroll tabs right') }}"
                title="{{ __('Scroll tabs right') }}"
                class="ws-scroll-btn ws-scroll-btn-right hidden h-6 w-6 shrink-0 items-center justify-center rounded-lg border border-slate-200/80 bg-[var(--card-bg)] text-slate-500 shadow-sm transition hover:bg-[var(--menu-hover-bg)] hover:text-slate-800 sm:inline-flex dark:border-slate-700 dark:text-slate-300">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6" />
            </svg>
        </button>
    </div>

    {{-- Menu daftar tab + aksi tutup banyak tab --}}
    <div x-show="$store.workspace && $store.workspace.tabs.length > 1" x-cloak class="shrink-0">
        <x-dropdown align="right" width="w-72" content-classes="py-1 bg-[var(--card-bg)] border border-[var(--card-border)]">
            <x-slot name="trigger">
                <button type="button"
                        title="{{ __('Tab options') }}"
                        aria-label="{{ __('Tab options') }}"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200/80 bg-[var(--card-bg)] text-slate-500 transition hover:bg-[var(--menu-hover-bg)] hover:text-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/50 dark:border-slate-700 dark:text-slate-300">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                    </svg>
                </button>
            </x-slot>
            <x-slot name="content">
                <p class="px-3.5 pb-1 pt-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    {{ __('Open tabs') }}
                </p>
                <div class="max-h-64 overflow-y-auto"
                     x-effect="if (open) { $nextTick(() => $el.querySelector('[data-tab-active=&quot;1&quot;]')?.scrollIntoView({ block: 'nearest' })) }">
                    <template x-for="tab in $store.workspace.tabs" :key="'menu-' + tab.id">
                        <button type="button"
                                :data-tab-active="$store.workspace.activeId === tab.id ? '1' : '0'"
                                @click="$store.workspace.activate(tab.id)"
                                :class="$store.workspace.activeId === tab.id
                                    ? 'bg-[var(--menu-hover-bg)] text-slate-900 dark:text-slate-100'
                                    : 'text-slate-600 dark:text-slate-300'"
                                class="flex w-full items-center gap-2 px-3.5 py-2 text-left text-xs transition hover:bg-[var(--menu-hover-bg)]">
                            <span x-show="tab.dept"
                                  x-text="tab.dept"
                                  :class="$store.workspace.deptClass(tab.dept)"
                                  class="shrink-0 rounded px-1 py-0.5 text-[9px] font-bold uppercase tracking-wider"></span>
                            <span class="min-w-0 flex-1 truncate" x-text="tab.title"></span>
                            <span x-show="tab.dirty"
                                  x-cloak
                                  class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"
                                  aria-hidden="true"></span>
                            <svg x-show="$store.workspace.activeId === tab.id"
                                 x-cloak
                                 class="h-3.5 w-3.5 shrink-0 text-accent-600 dark:text-accent-400"
                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                            </svg>
                        </button>
                    </template>
                </div>
                <div class="my-1 border-t border-slate-100 dark:border-slate-700"></div>
                <button type="button"
                        @click="$store.workspace.closeOthers()"
                        class="block w-full px-3.5 py-2 text-left text-xs font-medium text-slate-700 transition hover:bg-[var(--menu-hover-bg)] dark:text-slate-200">
                    {{ __('Close Other Tabs') }}
                </button>
                <button type="button"
                        @click="$store.workspace.closeAll()"
                        class="block w-full px-3.5 py-2 text-left text-xs font-medium text-red-600 transition hover:bg-[var(--menu-hover-bg)] dark:text-red-400">
                    {{ __('Close All Tabs') }}
                </button>
            </x-slot>
        </x-dropdown>
    </div>
</div>
