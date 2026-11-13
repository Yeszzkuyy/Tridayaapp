{{-- Baris tab workspace di dalam navbar (menggantikan pill lokasi).
     Tab Dashboard selalu di paling kiri dan tidak bisa ditutup; tab lain
     tetap hidup di latar belakang agar ketikan dan scroll tidak hilang. --}}
<div id="workspace-tabs"
     x-data
     x-init="$store.workspace && $store.workspace.init()"
     class="flex min-w-0 flex-1 items-center gap-1.5">

    <div role="tablist"
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
                        :title="tab.title"
                        :class="$store.workspace.activeId === tab.id
                            ? 'border-accent-500/40 bg-accent-500/12 text-accent-700 shadow-xs dark:border-accent-400/40 dark:bg-accent-500/20 dark:text-accent-200'
                            : 'border-transparent bg-transparent text-slate-600 hover:bg-[var(--menu-hover-bg)] hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100'"
                        class="relative inline-flex h-9 max-w-[210px] items-center gap-2 rounded-xl border px-3 text-xs font-semibold transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/50"
                        :style="!tab.pinned ? 'padding-right: 1.85rem;' : ''">

                    {{-- Indikator garis bawah aktif --}}
                    <span x-show="$store.workspace.activeId === tab.id"
                          aria-hidden="true"
                          class="pointer-events-none absolute inset-x-2.5 bottom-0 h-0.5 rounded-full bg-accent-600 dark:bg-accent-400"></span>

                    {{-- Dot kecil penanda tab pinned (Dashboard) --}}
                    <span x-show="tab.pinned"
                          aria-hidden="true"
                          class="h-2 w-2 shrink-0 rounded-full bg-accent-600 dark:bg-accent-400"></span>

                    <span class="truncate" x-text="tab.title"></span>

                    {{-- Indikator ketikan belum disimpan --}}
                    <span x-show="tab.dirty"
                          x-cloak
                          title="{{ __('Unsaved changes') }}"
                          aria-label="{{ __('Unsaved changes') }}"
                          class="h-2 w-2 shrink-0 rounded-full bg-amber-500 ring-2 ring-amber-400/30"></span>
                </button>

                {{-- Tombol tutup tab (tidak ada untuk Dashboard yang dipin) --}}
                <button x-show="!tab.pinned"
                        type="button"
                        @click.stop="$store.workspace.close(tab.id)"
                        :aria-label="'{{ __('Close') }} ' + tab.title"
                        title="{{ __('Close tab') }}"
                        :class="$store.workspace.activeId === tab.id
                            ? 'opacity-100 text-accent-700 hover:bg-accent-500/20 dark:text-accent-200'
                            : 'opacity-70 group-hover:opacity-100 text-slate-400 hover:bg-slate-200/70 hover:text-slate-700 dark:hover:bg-slate-700 dark:hover:text-slate-100'"
                        class="absolute right-1.5 inline-flex h-5 w-5 items-center justify-center rounded-lg transition focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/50">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </template>
    </div>

    {{-- Menu tutup banyak tab di ujung kanan barisan tab --}}
    <div x-show="$store.workspace && $store.workspace.tabs.length > 1" x-cloak class="shrink-0">
        <x-dropdown align="right" width="w-48" content-classes="py-1 bg-[var(--card-bg)] border border-[var(--card-border)]">
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
