<x-app-layout>
    <div id="teknisi-schedule-root" x-data="teknisiScheduleManager()" x-init="init()" data-api-url="{{ route('teknisi.schedules.api') }}" data-status-url="{{ route('teknisi.kalender.status') }}" data-connected="{{ $connected ? '1' : '0' }}">
        {{-- Top bar --}}
        <div class="flex items-center justify-between gap-3 mb-4">
            <div class="flex items-center gap-3 min-w-0">
                <x-icon-button as="a" icon="back" href="{{ route('teknisi.dashboard') }}" title="{{ __('Back') }}" />
                <div class="min-w-0">
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-800 dark:text-slate-100">{{ __('Technician Schedule') }}</h1>
                    <p class="text-sm text-slate-500 truncate" x-text="dayLabel"></p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span id="google-status-badge"
                      class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold transition-colors"
                      :class="googleConnected
                          ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                          : 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300'">
                    <svg x-show="googleConnected" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <svg x-show="!googleConnected" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    <span x-text="googleConnected ? '{{ __('Connected') }}' : '{{ __('Disconnected') }}'"></span>
                </span>
                @if(auth()->user()?->hasAnyRole(['super-admin', 'lead-technician']))
                    <x-icon-button title="{{ __('Google Calendar API') }}" @click="apiOpen = true">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17l-5.384 3.18A1.5 1.5 0 014.06 17.1V6.9a1.5 1.5 0 011.976-1.424l5.384 3.18m0 0l5.384-3.18A1.5 1.5 0 0118.776 6.9v10.2a1.5 1.5 0 01-1.976 1.424l-5.384-3.18z"/></svg>
                    </x-icon-button>
                @endif
                <x-icon-button title="{{ __('Today') }}" @click="goToday()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                </x-icon-button>
                @can('manage-technician')
                    <x-icon-button icon="add" title="{{ __('Add') }}" @click="openModal(activeDate)" />
                @endcan
            </div>
        </div>

        {{-- Centered month + year with arrows --}}
        <div class="flex items-center justify-center gap-1 sm:gap-2 mb-4">
            <button type="button" @click="panMonth(-1)" title="{{ __('Previous month') }}" aria-label="{{ __('Previous month') }}"
                    class="flex h-10 w-10 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-200/70 hover:text-slate-800 active:scale-95 dark:text-slate-300 dark:hover:bg-slate-700/60 dark:hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
            </button>
            <h2 class="min-w-44 sm:min-w-56 text-center text-xl sm:text-2xl font-bold tracking-tight tabular-nums text-slate-800 dark:text-slate-100" x-text="monthLabel"></h2>
            <button type="button" @click="panMonth(1)" title="{{ __('Next month') }}" aria-label="{{ __('Next month') }}"
                    class="flex h-10 w-10 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-200/70 hover:text-slate-800 active:scale-95 dark:text-slate-300 dark:hover:bg-slate-700/60 dark:hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            </button>
        </div>

        {{-- View switcher --}}
        <div class="flex justify-center mb-4" id="teknisi-view-switcher">
            <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-600 dark:bg-slate-800" role="tablist" aria-label="{{ __('Calendar view') }}">
                <template x-for="v in [{id:'month',label:'{{ __('Month') }}'},{id:'week',label:'{{ __('Week') }}'},{id:'day',label:'{{ __('Day') }}'}]" :key="v.id">
                    <button type="button" role="tab" :aria-selected="view === v.id" @click="setView(v.id)"
                            :class="view === v.id
                                ? 'bg-slate-800 text-white shadow dark:bg-accent-600'
                                : 'text-slate-500 hover:text-slate-800 dark:text-slate-300 dark:hover:text-white'"
                            class="rounded-lg px-4 sm:px-6 py-1.5 text-sm font-semibold transition" x-text="v.label"></button>
                </template>
            </div>
        </div>

        {{-- Month view --}}
        <div x-show="view === 'month'" x-transition.opacity>
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-3 sm:p-4 mb-4">
                <div class="grid grid-cols-7 gap-1 text-center mb-1" id="teknisi-month-grid">
                    <template x-for="name in ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']" :key="name">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 py-1" x-text="name"></span>
                    </template>
                    <template x-for="cell in monthCells" :key="cell.date">
                        <div @click="createOn(cell.date)" @keydown.enter="createOn(cell.date)" tabindex="0" role="button"
                                :aria-label="dayEvents(cell.date).length + ' {{ __('jobs') }}'"
                                :class="cell.date === activeDate
                                    ? 'bg-accent-500/10 text-slate-800 ring-2 ring-inset ring-accent-500 shadow-sm dark:bg-accent-500/20 dark:text-slate-100'
                                    : (cell.inMonth
                                        ? 'text-slate-700 hover:bg-slate-100 hover:shadow-sm dark:text-slate-200 dark:hover:bg-slate-700/50'
                                        : 'text-slate-300 hover:bg-slate-50 dark:text-slate-600 dark:hover:bg-slate-700/30')"
                                class="group flex min-h-20 sm:min-h-28 cursor-pointer flex-col items-center justify-start rounded-xl py-1.5 px-1 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500">
                            <span @click.stop="selectDay(cell.date)" :class="cell.date === activeDate ? 'bg-accent-500/20 text-accent-700 dark:bg-accent-500/25 dark:text-accent-200' : (cell.isToday ? 'underline decoration-accent-500 decoration-2 underline-offset-4' : '')"
                                  class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-full text-sm font-semibold tabular-nums" x-text="cell.num"></span>
                            <span class="mt-1 hidden sm:block w-full space-y-0.5 px-1">
                                <template x-for="ev in dayEvents(cell.date).slice(0, 2)" :key="ev.id">
                                    <span class="block truncate rounded-md px-1.5 py-0.5 text-left text-[11px] leading-tight"
                                          :class="cell.date === activeDate ? 'bg-white/20 text-white' : 'text-slate-600 dark:text-slate-300'"
                                          :style="'border-left:3px solid ' + colorFor(ev.technician_user_id) + ';background:' + colorFor(ev.technician_user_id) + '22'"
                                          x-text="(ev.start_time || '') + ' ' + ev.title"></span>
                                </template>
                            </span>
                            <span class="mt-auto flex items-center gap-1 h-2.5 pt-0.5">
                                <template x-for="i in dayDots(cell.date)" :key="i">
                                    <span class="h-1.5 w-1.5 rounded-full bg-accent-500"></span>
                                </template>
                                <span x-show="dayEvents(cell.date).length > 3" class="text-[10px] font-semibold tabular-nums opacity-70"
                                      x-text="'+' + (dayEvents(cell.date).length - 3)"></span>
                            </span>
                        </div>
                    </template>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-4">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200" x-text="dayLabel"></p>
                    <button type="button" @click="setView('day')" class="text-xs font-semibold text-accent-600 hover:text-accent-500 dark:text-accent-400">{{ __('View day') }} →</button>
                </div>
                <template x-for="event in events" :key="event.id">
                    <div @click="openDrawer(event)" class="mb-4 flex w-full cursor-pointer items-center gap-4 rounded-xl bg-slate-800 p-4 transition hover:bg-slate-700/70">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white"
                              :style="'background:' + colorFor(event.technician_user_id)" x-text="initials(event.technician || event.title)"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-lg font-bold text-slate-100 truncate" x-text="event.title"></p>
                            <div class="mt-1 flex items-center gap-1.5 text-sm text-slate-400">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4 shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                                <span x-text="event.date"></span>
                                <span class="mx-1 opacity-50">•</span>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4 shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span x-text="(event.start_time || '') + ' – ' + (event.end_time || '')"></span>
                            </div>
                        </div>
                        <div class="ml-auto flex shrink-0 items-center gap-1.5 text-sm font-medium text-emerald-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4 shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" /></svg>
                            <span x-text="event.location || event.technician || '—'"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Week view --}}
        <div x-show="view === 'week'" x-transition.opacity x-cloak>
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-4 sm:p-5" id="teknisi-week-list">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-800 dark:text-slate-100">{{ __('Weekly Schedule') }}</h2>
                    <div class="inline-flex items-center gap-0.5 rounded-xl border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                        <button type="button" @click="shiftWeek(-1)" title="{{ __('Previous week') }}" aria-label="{{ __('Previous week') }}"
                                class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 dark:text-slate-300 dark:hover:bg-slate-700/60 dark:hover:text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                        </button>
                        <button type="button" @click="shiftWeek(1)" title="{{ __('Next week') }}" aria-label="{{ __('Next week') }}"
                                class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 dark:text-slate-300 dark:hover:bg-slate-700/60 dark:hover:text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                        </button>
                        <button type="button" @click="goToday()" title="{{ __('Today') }}" aria-label="{{ __('Today') }}"
                                class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 dark:text-slate-300 dark:hover:bg-slate-700/60 dark:hover:text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        </button>
                        <button x-show="canManage" type="button" @click="openModal(activeDate)" title="{{ __('Add') }}" aria-label="{{ __('Add') }}"
                                class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 dark:text-slate-300 dark:hover:bg-slate-700/60 dark:hover:text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-7 gap-1 sm:gap-2 mb-1">
                    <template x-for="day in weekDays" :key="day.date">
                        <button type="button" @click="selectDay(day.date)"
                                :class="day.date === activeDate
                                    ? 'bg-accent-500/10 text-slate-800 ring-2 ring-inset ring-accent-500 shadow-sm dark:bg-accent-500/20 dark:text-slate-100'
                                    : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700/50'"
                                class="flex flex-col items-center rounded-xl py-2 sm:py-3 transition">
                            <span class="text-[11px] font-semibold uppercase tracking-wide opacity-70" x-text="day.name"></span>
                            <span class="text-lg sm:text-xl font-bold leading-tight tabular-nums" x-text="day.num"></span>
                        </button>
                    </template>
                </div>
                <p class="mb-4 text-center text-xs text-slate-400" x-text="weekLabel"></p>
                <template x-for="event in weekEvents" :key="event.id">
                    <div @click="openDrawer(event)" class="border border-gray-200 dark:border-slate-600 rounded-xl p-5 mb-4 last:mb-0 bg-white dark:bg-slate-800 cursor-pointer hover:shadow-md transition" :style="'border-left:4px solid ' + colorFor(event.technician_user_id)">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-lg sm:text-xl font-bold tracking-tight text-slate-800 dark:text-slate-100 truncate" x-text="event.title"></p>
                                <p class="mt-1 text-sm text-slate-400" x-text="(event.start_time || '') + ' – ' + (event.end_time || '')"></p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold" :class="statusClass(event.status)" x-text="statusLabel(event.status)"></span>
                            </div>
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <span class="inline-flex min-w-0 items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-200">
                                <span class="truncate" x-text="event.technician || event.customer || '—'"></span>
                            </span>
                        </div>
                        <template x-if="event.location">
                            <div class="mt-3 rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2.5">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Location / Meeting Link') }}</p>
                                <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-300" x-text="event.location"></p>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- Day view --}}
        <div x-show="view === 'day'" x-transition.opacity x-cloak>
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-4">
                <div class="flex items-center justify-center gap-1 mb-3">
                    <button type="button" @click="shiftDay(-1)" title="{{ __('Previous day') }}" aria-label="{{ __('Previous day') }}"
                            class="flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-200/70 hover:text-slate-800 active:scale-95 dark:text-slate-300 dark:hover:bg-slate-700/60 dark:hover:text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                    </button>
                    <p class="min-w-52 text-center text-sm font-semibold tabular-nums text-slate-700 dark:text-slate-200" x-text="dayLabel"></p>
                    <button type="button" @click="shiftDay(1)" title="{{ __('Next day') }}" aria-label="{{ __('Next day') }}"
                            class="flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-200/70 hover:text-slate-800 active:scale-95 dark:text-slate-300 dark:hover:bg-slate-700/60 dark:hover:text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                    </button>
                </div>
                <template x-for="event in events" :key="event.id">
                    <div @click="openDrawer(event)" class="mb-4 flex w-full cursor-pointer items-center gap-4 rounded-xl bg-slate-800 p-4 transition hover:bg-slate-700/70">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white"
                              :style="'background:' + colorFor(event.technician_user_id)" x-text="initials(event.technician || event.title)"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-lg font-bold text-slate-100 truncate" x-text="event.title"></p>
                            <div class="mt-1 flex items-center gap-1.5 text-sm text-slate-400">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4 shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                                <span x-text="event.date"></span>
                                <span class="mx-1 opacity-50">•</span>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4 shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span x-text="(event.start_time || '') + ' – ' + (event.end_time || '')"></span>
                            </div>
                        </div>
                        <div class="ml-auto flex shrink-0 items-center gap-1.5 text-sm font-medium text-emerald-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4 shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" /></svg>
                            <span x-text="event.location || event.technician || '—'"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Shared states --}}
        <div x-show="loading && view !== 'week'" class="mt-4 space-y-3" aria-hidden="true">
            <div class="animate-pulse rounded-xl border border-slate-200 dark:border-slate-600 p-4">
                <div class="h-4 w-2/3 rounded bg-slate-200 dark:bg-slate-600"></div>
                <div class="mt-2 h-3 w-1/3 rounded bg-slate-200 dark:bg-slate-600"></div>
            </div>
        </div>
        <div x-show="!loading && !loadError && view !== 'week' && events.length === 0" x-cloak class="mt-4 rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 px-4 py-8 text-center">
            <p class="text-sm text-slate-500 mb-3">{{ __('No jobs scheduled for this day.') }}</p>
            @can('manage-technician')
                <x-icon-button icon="add" title="{{ __('Add') }}" @click="openModal(activeDate)" />
            @endcan
        </div>

        {{-- Detail drawer --}}
        <div x-show="drawerOpen" x-cloak class="fixed inset-0 z-50" @keydown.escape.window="closeDrawer()">
            <div class="absolute inset-0 bg-slate-900/40" @click="closeDrawer()" x-show="drawerOpen" x-transition.opacity></div>
            <aside class="absolute right-0 top-0 flex h-full w-full flex-col bg-white shadow-2xl dark:bg-slate-800 sm:max-w-md sm:border-l sm:border-slate-200 dark:sm:border-slate-600"
                   x-show="drawerOpen"
                   x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                   x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                   role="dialog" aria-modal="true" aria-label="{{ __('Schedule detail') }}">
                <template x-if="selected">
                    <div class="flex h-full flex-col">
                        <div class="flex items-center justify-between gap-2 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold" :class="statusClass(selected.status)" x-text="statusLabel(selected.status)"></span>
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold" :class="syncClass(selected.sync_status)" x-text="syncLabel(selected.sync_status)"></span>
                            </div>
                            <button type="button" @click="closeDrawer()" title="{{ __('Close') }}" aria-label="{{ __('Close') }}"
                                    class="flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-200/70 hover:text-slate-800 dark:text-slate-300 dark:hover:bg-slate-700/60 dark:hover:text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" /></svg>
                            </button>
                        </div>
                        <div class="flex-1 overflow-y-auto px-5 py-4">
                            <h3 class="text-xl font-bold tracking-tight text-slate-800 dark:text-slate-100" x-text="selected.title"></h3>
                            <p class="mt-0.5 text-sm text-slate-500" x-text="(selected.date || '') + ', ' + ((selected.start_time || '') + ' – ' + (selected.end_time || ''))"></p>

                            <template x-if="selected.technician">
                                <div class="mt-3 flex items-center gap-2.5 rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-slate-700/50">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                                          :style="'background:' + colorFor(selected.technician_user_id)" x-text="initials(selected.technician)"></span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-700 dark:text-slate-200" x-text="selected.technician"></p>
                                        <p class="truncate text-xs text-slate-400">{{ __('Technician') }}</p>
                                    </div>
                                </div>
                            </template>

                            <template x-if="selected.location">
                                <div class="mt-3 flex items-start gap-2 rounded-xl border border-dashed border-slate-300 px-3 py-2.5 text-sm text-slate-600 dark:border-slate-600 dark:text-slate-300">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                                    <span x-text="selected.location"></span>
                                </div>
                            </template>

                            <div class="mt-4 rounded-lg bg-slate-50 p-3 dark:bg-slate-800/50">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Description / Notes') }}</p>
                                <p class="mt-1 text-sm leading-relaxed text-slate-700 dark:text-slate-200" x-text="selected.description || '—'"></p>
                            </div>

                            <dl class="mt-4 space-y-2 text-sm">
                                <template x-if="selected.project">
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-slate-400">{{ __('Project') }}</dt>
                                        <dd class="font-semibold text-slate-700 dark:text-slate-200" x-text="selected.project"></dd>
                                    </div>
                                </template>
                                <template x-if="selected.customer">
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-slate-400">{{ __('Customer') }}</dt>
                                        <dd class="font-semibold text-slate-700 dark:text-slate-200" x-text="selected.customer"></dd>
                                    </div>
                                </template>
                            </dl>

                            <a x-show="selected.url" :href="selected.url" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-accent-600 hover:text-accent-500 dark:text-accent-400">{{ __('View project') }} →</a>
                        </div>
                        <div x-show="canManage" class="flex items-center justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-700">
                            <form method="POST" :action="scheduleUrl(selected.id)" class="inline">
                                @csrf
                                @method('DELETE')
                                <x-icon-button type="submit" title="{{ __('Delete') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" /></svg>
                                </x-icon-button>
                            </form>
                            <button type="button" @click="openModal(selected.date, selected)"
                                    class="group relative overflow-hidden px-4 py-2 rounded-xl text-sm font-medium bg-accent-600 hover:bg-accent-500 text-white transition-all duration-300 hover:scale-105 hover:shadow-lg hover:shadow-accent-500/40 hover:brightness-110 active:scale-95">
                                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full"></span>
                                {{ __('Edit') }}</button>
                        </div>
                    </div>
                </template>
            </aside>
        </div>

        {{-- Modal tambah/edit jadwal --}}
        @can('manage-technician')
        <div x-show="isModalOpen" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4" id="teknisi-add-modal">
            <div class="absolute inset-0 bg-slate-900/50" @click="isModalOpen = false" x-show="isModalOpen" x-transition.opacity></div>
            <div x-show="isModalOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 class="relative w-full sm:max-w-2xl bg-white dark:bg-slate-800 rounded-t-3xl sm:rounded-2xl shadow-xl border border-slate-200 dark:border-slate-600 overflow-y-auto" style="max-height:92vh">
                <div class="pt-3 sm:hidden" aria-hidden="true"><div class="mx-auto h-1 w-10 rounded-full bg-slate-300 dark:bg-slate-600"></div></div>
                <div class="p-6 pt-3 sm:pt-6">
                    <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100 mb-4" x-text="editingId ? '{{ __('Edit Schedule') }}' : '{{ __('Add Schedule') }}'"></h3>
                    @if($errors->any())
                        <div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm p-3">
                            <ul class="list-disc list-inside">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <form method="POST" :action="editingId ? scheduleUrl(editingId) : '{{ route('teknisi.schedules.store') }}'" class="space-y-4">
                        @csrf
                        <template x-if="editingId"><input type="hidden" name="_method" value="PUT"></template>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Title') }}</label>
                            <input type="text" name="title" x-model="form.title" required maxlength="255"
                                   class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Project') }}</label>
                                <select name="project_id" x-model="form.project_id"
                                        class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">
                                    <option value="">{{ __('Choose a project (optional)') }}</option>
                                    @foreach($projects as $p)
                                        <option value="{{ $p->id }}">{{ $p->project_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Technician') }}</label>
                                <select name="technician_user_id" x-model="form.technician_user_id"
                                        class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">
                                    <option value="">{{ __('Choose a technician (optional)') }}</option>
                                    @foreach($technicians as $t)
                                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Date') }}</label>
                                <input type="date" name="date" x-model="form.date" required
                                       class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Start time') }} <span class="text-xs font-normal text-slate-400">(WIB)</span></label>
                                <input type="time" name="start_time" x-model="form.start_time" required
                                       class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('End time') }} <span class="text-xs font-normal text-slate-400">(WIB)</span></label>
                                <input type="time" name="end_time" x-model="form.end_time" required
                                       class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Location') }}</label>
                            <input type="text" name="location" x-model="form.location" maxlength="255"
                                   class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Description / Notes') }}</label>
                            <textarea name="description" x-model="form.description" rows="3"
                                      class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600"></textarea>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Status') }}</label>
                                <select name="status" x-model="form.status"
                                        class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">
                                    @foreach(\App\Models\TechnicianSchedule::STATUSES as $st)
                                        <option value="{{ $st }}">{{ \Illuminate\Support\Str::headline($st) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Reminder') }}</label>
                                <select name="reminder_minutes" x-model="form.reminder_minutes"
                                        class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">
                                    <option value="">{{ __('None') }}</option>
                                    <option value="30">30 {{ __('minutes') }}</option>
                                    <option value="60">60 {{ __('minutes') }}</option>
                                    <option value="120">120 {{ __('minutes') }}</option>
                                    <option value="1440">1440 {{ __('minutes') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" @click="isModalOpen = false"
                                    class="px-4 py-2 rounded-xl text-sm font-medium border border-slate-300 text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700 dark:hover:text-white transition-all duration-300 hover:scale-105 active:scale-95">{{ __('Cancel') }}</button>
                            <button type="submit"
                                    class="group relative overflow-hidden px-4 py-2 rounded-xl text-sm font-medium bg-accent-600 hover:bg-accent-500 text-white transition-all duration-300 hover:scale-105 hover:shadow-lg hover:shadow-accent-500/40 hover:brightness-110 active:scale-95">
                                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full"></span>
                                {{ __('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endcan

        {{-- Modal kredensial Google (super-admin / lead-technician) --}}
        @if(auth()->user()?->hasAnyRole(['super-admin', 'lead-technician']))
        <div x-show="apiOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50" @click="apiOpen = false" x-show="apiOpen" x-transition.opacity></div>
            <div x-show="apiOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 class="relative w-full sm:max-w-lg bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-600 overflow-y-auto" style="max-height:92vh">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100 mb-1">{{ __('Google Calendar API') }}</h3>
                    <p class="text-sm text-slate-500 mb-4">{{ __('Enter your Google Cloud credentials to enable two-way sync.') }}</p>
                    <form method="POST" action="{{ route('teknisi.kalender.sync') }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Client ID') }}</label>
                            <input type="text" name="client_id" value="{{ $credential->client_id }}" autocomplete="off"
                                   class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Client Secret') }}</label>
                            <input type="password" name="client_secret" value="{{ $credential->client_secret }}" autocomplete="off"
                                   class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('API Key') }}</label>
                            <input type="password" name="api_key" value="{{ $credential->api_key }}" autocomplete="off"
                                   class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Calendar ID') }}</label>
                            <input type="text" name="calendar_id" value="{{ $credential->calendar_id }}" autocomplete="off"
                                   class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">
                        </div>
                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" @click="apiOpen = false"
                                    class="px-4 py-2 rounded-xl text-sm font-medium border border-slate-300 text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700 dark:hover:text-white transition-all duration-300 hover:scale-105 active:scale-95">{{ __('Close') }}</button>
                            <button type="submit"
                                    class="group relative overflow-hidden flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium bg-emerald-600 hover:bg-emerald-500 text-white transition-all duration-300 hover:scale-105 hover:shadow-lg hover:brightness-110 active:scale-95">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"/></svg>
                                {{ __('Sync Now') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif
    </div>

    <script>
        window.__teknisiProjects = @json($projects->map(fn ($p) => ['id' => $p->id, 'name' => $p->project_name]));

        function teknisiScheduleManager() {
            return {
                apiUrl: document.getElementById('teknisi-schedule-root')?.dataset.apiUrl || '',
                statusUrl: document.getElementById('teknisi-schedule-root')?.dataset.statusUrl || '',
                googleConnected: document.getElementById('teknisi-schedule-root')?.dataset.connected === '1',
                canManage: {{ auth()->user()->can('manage-technician') ? 'true' : 'false' }},
                scheduleBase: @json(route('teknisi.schedules.store')),
                view: 'month',
                activeDate: @json(today()->toDateString()),
                viewYear: null,
                viewMonth: null,
                monthCells: [],
                monthLabel: '',
                monthEvents: {},
                weekDays: [],
                weekLabel: '',
                dayLabel: '',
                events: [],
                weekEvents: [],
                loading: false,
                loadError: false,
                fetchSeq: 0,
                rangeSeq: 0,
                drawerOpen: false,
                selected: null,
                isModalOpen: false,
                apiOpen: false,
                editingId: null,
                form: { title: '', project_id: '', technician_user_id: '', date: '', start_time: '', end_time: '', location: '', description: '', status: 'scheduled', reminder_minutes: '' },

                init() {
                    const active = new Date(this.activeDate + 'T12:00:00');
                    this.viewYear = active.getFullYear();
                    this.viewMonth = active.getMonth();
                    this.buildMonth();
                    this.buildWeek();
                    this.fetchMonth();
                    this.fetchEvents();
                    this.fetchStatus();
                    // Poll status koneksi tiap 30 detik agar badge realtime.
                    setInterval(() => this.fetchStatus(), 30000);
                },

                fetchStatus() {
                    if (!this.statusUrl) return;
                    // Cache-buster: cegah browser memakai respons status basi.
                    const sep = this.statusUrl.includes('?') ? '&' : '?';
                    fetch(this.statusUrl + sep + '_=' + Date.now())
                        .then((r) => r.json())
                        .then((data) => {
                            if (typeof data?.connected === 'boolean') {
                                this.googleConnected = data.connected;
                            }
                        })
                        .catch(() => {});
                },

                fmt(d) {
                    const y = d.getFullYear(), m = String(d.getMonth() + 1).padStart(2, '0'), dd = String(d.getDate()).padStart(2, '0');
                    return y + '-' + m + '-' + dd;
                },

                mondayOf(date) {
                    const d = new Date(date);
                    d.setHours(12, 0, 0, 0);
                    d.setDate(d.getDate() - ((d.getDay() + 6) % 7));
                    return d;
                },

                buildMonth() {
                    const first = new Date(this.viewYear, this.viewMonth, 1);
                    const start = this.mondayOf(first);
                    const todayStr = this.fmt(new Date());
                    this.monthCells = [];
                    for (let i = 0; i < 42; i++) {
                        const d = new Date(start);
                        d.setDate(d.getDate() + i);
                        const date = this.fmt(d);
                        this.monthCells.push({ date, num: d.getDate(), inMonth: d.getMonth() === this.viewMonth, isToday: date === todayStr });
                    }
                    this.monthLabel = first.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
                },

                buildWeek() {
                    const names = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                    const monday = this.mondayOf(new Date(this.activeDate + 'T12:00:00'));
                    this.weekDays = names.map((name, i) => {
                        const d = new Date(monday);
                        d.setDate(d.getDate() + i);
                        return { date: this.fmt(d), name, num: d.getDate() };
                    });
                    const opt = { day: 'numeric', month: 'short' };
                    const a = new Date(this.weekDays[0].date + 'T12:00:00').toLocaleDateString('en-GB', opt);
                    const b = new Date(this.weekDays[6].date + 'T12:00:00').toLocaleDateString('en-GB', opt);
                    this.weekLabel = a + ' – ' + b;
                    this.dayLabel = new Date(this.activeDate + 'T12:00:00').toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
                },

                setView(v) {
                    this.view = v;
                    if (v === 'week') this.fetchRange(this.weekDays[0].date, this.weekDays[6].date);
                    else if (v === 'day') this.fetchEvents();
                    else { this.fetchMonth(); this.fetchEvents(); }
                },

                panMonth(dir) {
                    let m = this.viewMonth + dir, y = this.viewYear;
                    if (m < 0) { m = 11; y--; }
                    if (m > 11) { m = 0; y++; }
                    this.viewMonth = m;
                    this.viewYear = y;
                    this.buildMonth();
                    this.fetchMonth();
                },

                goToday() {
                    const now = new Date();
                    this.viewYear = now.getFullYear();
                    this.viewMonth = now.getMonth();
                    this.buildMonth();
                    this.fetchMonth();
                    this.selectDay(this.fmt(now));
                },

                shiftDay(dir) {
                    const d = new Date(this.activeDate + 'T12:00:00');
                    d.setDate(d.getDate() + dir);
                    this.selectDay(this.fmt(d));
                },

                shiftWeek(dir) {
                    const d = new Date(this.activeDate + 'T12:00:00');
                    d.setDate(d.getDate() + dir * 7);
                    this.selectDay(this.fmt(d));
                },

                selectDay(date) {
                    this.activeDate = date;
                    const d = new Date(date + 'T12:00:00');
                    if (d.getFullYear() !== this.viewYear || d.getMonth() !== this.viewMonth) {
                        this.viewYear = d.getFullYear();
                        this.viewMonth = d.getMonth();
                        this.buildMonth();
                        this.fetchMonth();
                    }
                    this.buildWeek();
                    if (this.view === 'week') this.fetchRange(this.weekDays[0].date, this.weekDays[6].date);
                    else this.fetchEvents();
                },

                createOn(date) {
                    this.selectDay(date);
                    if (this.canManage) this.openModal(date);
                    else {
                        const evts = this.dayEvents(date);
                        if (evts.length === 1) this.openDrawer(evts[0]);
                        else this.setView('day');
                    }
                },

                openDrawer(event) {
                    this.selected = { ...event };
                    this.drawerOpen = true;
                },

                closeDrawer() {
                    this.drawerOpen = false;
                },

                scheduleUrl(id) {
                    return String(this.scheduleBase).replace(/\/schedules\/?$/, '/schedules/' + id);
                },

                openModal(date, event = null) {
                    if (!this.canManage) return;
                    this.isModalOpen = true;
                    if (event) {
                        this.editingId = event.id;
                        this.form = {
                            title: event.title || '',
                            project_id: event.project_id || '',
                            technician_user_id: event.technician_user_id || '',
                            date: event.date || date,
                            start_time: event.start_time || '',
                            end_time: event.end_time || '',
                            location: event.location || '',
                            description: event.description || '',
                            status: event.status || 'scheduled',
                            reminder_minutes: event.reminder_minutes ? String(event.reminder_minutes) : '',
                        };
                    } else {
                        this.editingId = null;
                        this.form = { title: '', project_id: '', technician_user_id: '', date: date, start_time: '', end_time: '', location: '', description: '', status: 'scheduled', reminder_minutes: '' };
                    }
                },

                fetchEvents() {
                    const date = this.activeDate;
                    const seq = ++this.fetchSeq;
                    this.loading = true;
                    this.loadError = false;
                    fetch(this.apiUrl + '?date=' + encodeURIComponent(date))
                        .then((r) => r.json())
                        .then((data) => {
                            if (seq !== this.fetchSeq) return;
                            this.events = Array.isArray(data) ? data : [];
                            this.monthEvents[date] = this.events;
                        })
                        .catch(() => {
                            if (seq !== this.fetchSeq) return;
                            this.events = [];
                            this.loadError = true;
                        })
                        .finally(() => {
                            if (seq !== this.fetchSeq) return;
                            this.loading = false;
                        });
                },

                fetchRange(start, end) {
                    // Isi weekEvents (kartu Weekly) + monthEvents (dots/preview).
                    // JANGAN sentuh this.events (agenda hari terpilih) — itu
                    // tugas fetchEvents. Sequence terpisah agar tak saling buang.
                    const seq = ++this.rangeSeq;
                    fetch(this.apiUrl + '?start=' + encodeURIComponent(start) + '&end=' + encodeURIComponent(end))
                        .then((r) => r.json())
                        .then((data) => {
                            if (seq !== this.rangeSeq) return;
                            const list = Array.isArray(data) ? data : [];
                            this.weekEvents = list;
                            this.groupByDate(list);
                        })
                        .catch(() => {});
                },

                fetchMonth() {
                    if (!this.monthCells.length) return;
                    this.fetchRange(this.monthCells[0].date, this.monthCells[this.monthCells.length - 1].date);
                },

                groupByDate(list) {
                    const grouped = {};
                    list.forEach((e) => {
                        (grouped[e.date] = grouped[e.date] || []).push(e);
                    });
                    this.monthEvents = { ...this.monthEvents, ...grouped };
                },

                dayEvents(date) {
                    return this.monthEvents[date] || [];
                },

                dayDots(date) {
                    return [0, 1, 2].filter((i) => (this.monthEvents[date] || []).length > i);
                },

                colorFor(id) {
                    const palette = ['#2563eb', '#059669', '#d97706', '#dc2626', '#7c3aed', '#0d9488', '#db2777', '#4f46e5', '#ea580c', '#0891b2'];
                    if (!id) return '#475569';
                    let h = 0;
                    for (const c of String(id)) h = (h * 31 + c.charCodeAt(0)) >>> 0;
                    return palette[h % palette.length];
                },

                initials(name) {
                    return String(name || '?').trim().split(/\s+/).slice(0, 2).map((w) => w[0].toUpperCase()).join('');
                },

                statusClass(status) {
                    return {
                        scheduled: 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200',
                        on_progress: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200',
                        completed: 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200',
                        cancelled: 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200',
                    }[status] || 'bg-slate-100 text-slate-600';
                },

                statusLabel(status) {
                    return {
                        scheduled: '{{ __('Scheduled') }}',
                        on_progress: '{{ __('On Progress') }}',
                        completed: '{{ __('Completed') }}',
                        cancelled: '{{ __('Cancelled') }}',
                    }[status] || status;
                },

                syncClass(sync) {
                    return {
                        synced: 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200',
                        syncing: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200',
                        error: 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200',
                        not_connected: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                    }[sync] || 'bg-slate-100 text-slate-600';
                },

                syncLabel(sync) {
                    return {
                        synced: '{{ __('Synced') }}',
                        syncing: '{{ __('Syncing') }}',
                        error: '{{ __('Sync Error') }}',
                        not_connected: '{{ __('Not Connected') }}',
                    }[sync] || sync;
                },
            };
        }
    </script>
</x-app-layout>
