<x-app-layout>
    <div id="sales-schedule-root" x-data="scheduleManager()" x-init="init()" data-api-url="{{ route('sales.schedules.api') }}">
        {{-- Top bar --}}
        <div class="flex items-center justify-between gap-3 mb-4">
            <div class="flex items-center gap-3 min-w-0">
                <x-icon-button as="a" icon="back" href="{{ route('sales.dashboard') }}" title="{{ __('Back') }}" />
                <div class="min-w-0">
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-800 dark:text-slate-100">{{ __('Sales Schedule') }}</h1>
                    <p class="text-sm text-slate-500 truncate" x-text="dayLabel"></p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <x-icon-button title="{{ __('Today') }}" @click="goToday()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                </x-icon-button>
                @can('manage-sales')
                    <x-icon-button icon="add" title="{{ __('Add') }}" @click="openModal(activeDate)" />
                @endcan
            </div>
        </div>

        {{-- Centered month + year flanked by plain arrows --}}
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
        <div class="flex justify-center mb-4" id="sales-view-switcher">
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
                <div class="grid grid-cols-7 gap-1 text-center mb-1" id="sales-month-grid">
                    <template x-for="name in ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']" :key="name">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 py-1" x-text="name"></span>
                    </template>
                    <template x-for="cell in monthCells" :key="cell.date">
                        <div @click="createOn(cell.date)" @keydown.enter="createOn(cell.date)" tabindex="0" role="button"
                                :aria-label="dayEvents(cell.date).length + ' {{ __('visits') }}'"
                                :class="cell.date === activeDate
                                    ? 'bg-accent-500/10 text-slate-800 ring-2 ring-inset ring-accent-500 shadow-sm dark:bg-accent-500/20 dark:text-slate-100'"
                                            : (cell.inMonth
                                        ? 'text-slate-700 hover:bg-slate-100 hover:shadow-sm dark:text-slate-200 dark:hover:bg-slate-700/50'
                                        : 'text-slate-300 hover:bg-slate-50 dark:text-slate-600 dark:hover:bg-slate-700/30')"
                                class="group flex min-h-20 sm:min-h-28 cursor-pointer flex-col items-center justify-start rounded-xl py-1.5 px-1 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500">
                            <span :class="cell.isToday ? 'bg-accent-500/20 text-accent-700 dark:bg-accent-500/25 dark:text-accent-200' : ''"
                                  class="flex h-9 w-9 items-center justify-center rounded-full text-sm font-semibold tabular-nums" x-text="cell.num"></span>
                            <span class="mt-1 hidden sm:block w-full space-y-0.5 px-1">
                                <template x-for="ev in dayEvents(cell.date).slice(0, 2)" :key="ev.id">
                                    <button type="button" @click.stop="openDrawer(ev)"
                                            class="block w-full truncate px-1.5 py-0.5 text-left text-[11px] leading-tight transition hover:ring-1 hover:ring-accent-500"
                                            :class="cardColor(ev.id).wrap + ' ' + cardColor(ev.id).time"
                                            x-text="(ev.activity_time || '') + ' ' + ev.description"></button>
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
                    <button type="button" @click="openDrawer(event)" class="mb-3 block w-full text-left p-4 shadow-sm transition hover:shadow-md" :class="cardColor(event.id).wrap">
                        <div class="flex items-start justify-between gap-3">
                            <span class="text-base font-semibold text-slate-800 dark:text-slate-100" x-text="event.description"></span>
                            <span class="flex shrink-0 items-center gap-1.5">
                                <span x-show="event.completed" class="inline-flex px-2 py-1 rounded-full text-[11px] font-bold bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200">{{ __('Done') }}</span>
                                <span class="font-semibold text-sm tabular-nums" :class="cardColor(event.id).time" x-text="event.activity_time || '--:--'"></span>
                            </span>
                        </div>
                        <p class="text-sm text-slate-500 mt-1">
                            <span x-text="event.customer"></span>
                            <template x-if="event.creator">
                                <span> • <span x-text="event.creator"></span></span>
                            </template>
                        </p>
                        <template x-if="event.location">
                            <div class="mt-2 rounded-lg border border-dashed border-slate-300 dark:border-slate-600 px-3 py-2 text-sm text-slate-600 dark:text-slate-300" x-text="event.location"></div>
                        </template>
                    </button>
                </template>
            </div>
        </div>

        {{-- Week view (reference design) --}}
        <div x-show="view === 'week'" x-transition.opacity x-cloak>
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-4 sm:p-5" id="sales-week-list">
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
                        <button x-show="canWrite" type="button" @click="openModal(activeDate)" title="{{ __('Add') }}" aria-label="{{ __('Add') }}"
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

                <template x-for="event in events" :key="event.id">
                    <div class="p-5 mb-4 last:mb-0 shadow-sm bg-white dark:bg-slate-800" :class="cardColor(event.id).wrap">
                        <div class="relative flex items-start justify-between gap-3">
                            <button type="button" @click="openDrawer(event)" class="min-w-0 flex-1 text-left text-lg sm:text-xl font-bold tracking-tight text-slate-800 hover:text-accent-600 dark:text-slate-100 dark:hover:text-accent-400" x-text="event.description"></button>
                            <div class="flex shrink-0 items-center gap-1">
                                <a x-show="canWrite && !event.completed" :href="followUpBase + '/' + event.id + '/edit'" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}"
                                   class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700/60 dark:hover:text-slate-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
                                </a>
                                <button type="button" @click="openMenuId = openMenuId === event.id ? null : event.id" title="{{ __('Options') }}" aria-label="{{ __('Options') }}"
                                        class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700/60 dark:hover:text-slate-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="h-4 w-4" aria-hidden="true"><circle cx="12" cy="5" r="1.8" /><circle cx="12" cy="12" r="1.8" /><circle cx="12" cy="19" r="1.8" /></svg>
                                </button>
                            </div>
                            <div x-show="openMenuId === event.id" @click.outside="openMenuId = null" x-cloak
                                 class="absolute right-0 top-10 z-20 w-44 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl dark:border-slate-600 dark:bg-slate-800">
                                <button type="button" @click="openMenuId = null; openDrawer(event)"
                                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-600 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700/60">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4 shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                    {{ __('View detail') }}
                                </button>
                                <a :href="event.url"
                                   class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-600 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700/60">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4 shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                    {{ __('View lead') }}
                                </a>
                                <form x-show="canWrite && !event.completed" method="POST" :action="followUpBase + '/' + event.id + '/complete'">
                                    @csrf
                                    <input type="hidden" name="return" value="schedule">
                                    <input type="hidden" name="date" :value="activeDate">
                                    <button type="submit"
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-600 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700/60">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                        {{ __('Mark as done') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                        <p class="mt-1 text-sm font-semibold" :class="cardColor(event.id).time" x-text="fmtTime(event.activity_time)"></p>
                        <div class="mt-2 flex items-center gap-2">
                            <span class="inline-flex min-w-0 items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-200">
                                <span class="truncate" x-text="event.customer"></span>
                            </span>
                            <button x-show="canWrite" type="button" @click="quickAdd(event.customer_id)" title="{{ __('Add for this customer') }}" aria-label="{{ __('Add for this customer') }}"
                                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-600 transition hover:bg-slate-300 hover:text-slate-800 dark:bg-slate-600 dark:text-slate-200 dark:hover:bg-slate-500">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke="currentColor" class="h-3 w-3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            </button>
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
                    <button type="button" @click="openDrawer(event)" class="mb-3 block w-full text-left p-4 shadow-sm transition hover:shadow-md" :class="cardColor(event.id).wrap">
                        <div class="flex items-start justify-between gap-3">
                            <span class="text-base font-semibold text-slate-800 dark:text-slate-100" x-text="event.description"></span>
                            <span class="flex shrink-0 items-center gap-1.5">
                                <span x-show="event.completed" class="inline-flex px-2 py-1 rounded-full text-[11px] font-bold bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200">{{ __('Done') }}</span>
                                <span class="font-semibold text-sm tabular-nums" :class="cardColor(event.id).time" x-text="event.activity_time || '--:--'"></span>
                            </span>
                        </div>
                        <p class="text-sm text-slate-500 mt-1">
                            <span x-text="event.customer"></span>
                            <template x-if="event.creator">
                                <span> • <span x-text="event.creator"></span></span>
                            </template>
                        </p>
                        <template x-if="event.location">
                            <div class="mt-2 rounded-lg border border-dashed border-slate-300 dark:border-slate-600 px-3 py-2 text-sm text-slate-600 dark:text-slate-300" x-text="event.location"></div>
                        </template>
                    </button>
                </template>
            </div>
        </div>

        {{-- Shared states --}}
        <div x-show="loading && view !== 'week'" class="mt-4 space-y-3" aria-hidden="true">
            <div class="animate-pulse rounded-xl border border-slate-200 dark:border-slate-600 p-4">
                <div class="h-4 w-2/3 rounded bg-slate-200 dark:bg-slate-600"></div>
                <div class="mt-2 h-3 w-1/3 rounded bg-slate-200 dark:bg-slate-600"></div>
            </div>
            <div class="animate-pulse rounded-xl border border-slate-200 dark:border-slate-600 p-4">
                <div class="h-4 w-1/2 rounded bg-slate-200 dark:bg-slate-600"></div>
                <div class="mt-2 h-3 w-1/4 rounded bg-slate-200 dark:bg-slate-600"></div>
            </div>
        </div>
        <div x-show="loadError && !loading" x-cloak class="mt-4 flex items-center justify-between gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-500/10 dark:text-red-300">
            <span>{{ __('Gagal memuat jadwal.') }}</span>
            <x-icon-button icon="reset" title="{{ __('Retry') }}" @click="loadError = false; fetchEvents(); if (view === 'week') fetchWeek(); else fetchMonth();" />
        </div>
        <div x-show="!loading && !loadError && view !== 'week' && events.length === 0" x-cloak class="mt-4 rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 px-4 py-8 text-center">
            <p class="text-sm text-slate-500 mb-3">{{ __('Belum ada jadwal kunjungan.') }}</p>
            @can('manage-sales')
                <x-icon-button icon="add" title="{{ __('Add') }}" @click="openModal(activeDate)" />
            @endcan
        </div>

        {{-- Detail drawer --}}
        <div x-show="drawerOpen" x-cloak class="fixed inset-0 z-50" id="sales-drawer" @keydown.escape.window="closeDrawer()">
            <div class="absolute inset-0 bg-slate-900/40" @click="closeDrawer()"
                 x-show="drawerOpen" x-transition.opacity></div>
            <aside class="absolute right-0 top-0 flex h-full w-full flex-col bg-white shadow-2xl dark:bg-slate-800 sm:max-w-md sm:border-l sm:border-slate-200 dark:sm:border-slate-600"
                   x-show="drawerOpen"
                   x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                   x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                   role="dialog" aria-modal="true" aria-label="{{ __('Schedule detail') }}">
                <template x-if="selectedEvent">
                    <div class="flex h-full flex-col">
                        <div class="flex items-center justify-between gap-2 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold tabular-nums bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200" x-text="selectedEvent.activity_time || '--:--'"></span>
                                <span x-show="selectedEvent.completed" class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200">{{ __('Done') }}</span>
                                <span x-show="!selectedEvent.completed" class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200">{{ __('Scheduled') }}</span>
                            </div>
                            <button type="button" @click="closeDrawer()" title="{{ __('Close') }}" aria-label="{{ __('Close') }}"
                                    class="flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-200/70 hover:text-slate-800 dark:text-slate-300 dark:hover:bg-slate-700/60 dark:hover:text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" /></svg>
                            </button>
                        </div>
                        <div class="flex-1 overflow-y-auto px-5 py-4">
                            <h3 class="text-xl font-bold tracking-tight text-slate-800 dark:text-slate-100" x-text="selectedEvent.description"></h3>
                            <p class="mt-1 text-sm text-slate-500" x-text="selectedEvent.customer"></p>
                            <template x-if="selectedEvent.location">
                                <div class="mt-3 rounded-xl border border-dashed border-slate-300 dark:border-slate-600 px-3 py-2.5 text-sm text-slate-600 dark:text-slate-300" x-text="selectedEvent.location"></div>
                            </template>
                            <dl class="mt-4 space-y-2 text-sm">
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-400">{{ __('Date') }}</dt>
                                    <dd class="font-semibold tabular-nums text-slate-700 dark:text-slate-200" x-text="selectedEvent.date"></dd>
                                </div>
                                <template x-if="selectedEvent.creator">
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-slate-400">{{ __('Created by') }}</dt>
                                        <dd class="font-semibold text-slate-700 dark:text-slate-200" x-text="selectedEvent.creator"></dd>
                                    </div>
                                </template>
                            </dl>
                            <a :href="selectedEvent.url" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-accent-600 hover:text-accent-500 dark:text-accent-400">{{ __('View lead') }} →</a>
                        </div>
                        <div x-show="canWrite && !selectedEvent.completed" class="flex items-center justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-700">
                            <form method="GET" :action="followUpUrl('edit')" class="inline">
                                <x-icon-button type="submit" title="{{ __('Edit') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
                                </x-icon-button>
                            </form>
                            <form method="POST" :action="followUpUrl('complete')" class="inline">
                                @csrf
                                <input type="hidden" name="return" value="schedule">
                                <input type="hidden" name="date" :value="selectedEvent.date">
                                <x-icon-button type="submit" title="{{ __('Mark as done') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                </x-icon-button>
                            </form>
                        </div>
                    </div>
                </template>
            </aside>
        </div>

        {{-- Modal tambah jadwal (writers only) --}}
        @can('manage-sales')
        <div x-show="isModalOpen" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4" id="sales-add-modal">
            <div class="absolute inset-0 bg-slate-900/50" @click="isModalOpen = false"
                 x-show="isModalOpen" x-transition.opacity></div>
            <div x-show="isModalOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 class="relative w-full sm:max-w-2xl bg-white dark:bg-slate-800 rounded-t-3xl sm:rounded-2xl shadow-xl border border-slate-200 dark:border-slate-600 overflow-y-auto" style="max-height:92vh">
                <div class="pt-3 sm:hidden" aria-hidden="true"><div class="mx-auto h-1 w-10 rounded-full bg-slate-300 dark:bg-slate-600"></div></div>
                <div class="p-6 pt-3 sm:pt-6">
                <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100 mb-1">{{ __('Tambah Jadwal Kunjungan') }}</h3>
                <p class="text-sm text-slate-500 mb-1">{{ __('Tersimpan sebagai follow-up dan masuk Activity Timeline Lead.') }}</p>
                <p class="text-sm font-semibold text-accent-600 dark:text-accent-400 mb-4" x-text="modalSummary()"></p>

                @if($errors->any())
                    <div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm p-3">
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('sales.follow-ups.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="type" value="meeting">
                    <input type="hidden" name="return" value="schedule">
                    <input type="hidden" name="date" :value="modalDate">

                    <div>
                        <label for="su-customer_id" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Customer') }}</label>
                        <select id="su-customer_id" name="customer_id" x-model="customerId" @change="filterLeads()" required
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">
                            <option value="">{{ __('Pilih customer') }}</option>
                            @foreach($customers as $id => $label)
                                <option value="{{ $id }}" @selected(old('customer_id') == $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="su-lead_id" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Lead') }}</label>
                        <select id="su-lead_id" name="lead_id" x-model="leadId" @change="syncCustomerFromLead()"
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">
                            <option value="">{{ __('Pilih lead (opsional)') }}</option>
                            <template x-for="lead in filteredLeads()" :key="lead.id">
                                <option :value="lead.id" x-text="lead.label"></option>
                            </template>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="su-follow_up_date" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Tanggal') }}</label>
                            <input id="su-follow_up_date" type="date" name="follow_up_date" :value="modalDate" @change="modalDate = $event.target.value" required
                                   class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">
                        </div>
                        <div>
                            <label for="su-activity_time" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Jam') }}</label>
                            <input id="su-activity_time" type="time" name="activity_time" x-model="modalTime"
                                   class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <template x-for="t in ['09:00','10:00','13:00','15:00','16:00']" :key="t">
                                    <button type="button" @click="modalTime = t"
                                            :class="modalTime === t ? 'bg-slate-800 text-white dark:bg-accent-600' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600'"
                                            class="rounded-full px-3 py-1 text-xs font-semibold tabular-nums transition" x-text="t"></button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="su-location" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Lokasi') }}</label>
                        <input id="su-location" type="text" name="location" x-model="modalLocation" maxlength="150" placeholder="{{ __('Alamat / titik kumpul') }}"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">
                    </div>

                    <div>
                        <label for="su-description" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Agenda') }}</label>
                        <textarea id="su-description" name="description" rows="3" required placeholder="{{ __('Contoh: Presentasi BOQ') }}"
                                  class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">{{ old('description') }}</textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" @click="isModalOpen = false"
                                class="px-4 py-2 rounded-xl text-sm font-medium border border-slate-300 text-slate-700 hover:bg-white dark:border-slate-600 dark:text-slate-200 transition-all duration-300 hover:scale-105 active:scale-95">{{ __('Cancel') }}</button>
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
    </div>

    <script>
        window.__scheduleLeads = @json($leadItems);

        function scheduleManager() {
            return {
                apiUrl: document.getElementById('sales-schedule-root')?.dataset.apiUrl || '',
                canWrite: {{ auth()->user()->can('manage-sales') ? 'true' : 'false' }},
                followUpBase: @json(route('sales.follow-ups.index')),
                view: 'month',
                activeDate: @json($activeDate),
                viewYear: null,
                viewMonth: null,
                monthCells: [],
                monthLabel: '',
                monthEvents: {},
                weekDays: [],
                weekLabel: '',
                dayLabel: '',
                events: [],
                loading: false,
                loadError: false,
                drawerOpen: false,
                selectedEvent: null,
                openMenuId: null,
                isModalOpen: {{ $errors->any() && auth()->user()->can('manage-sales') ? 'true' : 'false' }},
                modalDate: @json($activeDate),
                modalTime: @json((string) old('activity_time', '')),
                modalLocation: @json((string) old('location', '')),
                customerId: @json((string) old('customer_id', '')),
                leadId: @json((string) old('lead_id', '')),
                leads: window.__scheduleLeads || [],
                cardPalette: {
                    blue:   { wrap: 'border-l-[3px] border-blue-500 bg-blue-50 dark:bg-blue-500/20 rounded-r shadow-sm',
                              time: 'text-blue-600 dark:text-blue-300 text-xs leading-tight font-semibold' },
                    violet: { wrap: 'border-l-[3px] border-violet-500 bg-violet-50 dark:bg-violet-500/20 rounded-r shadow-sm',
                              time: 'text-violet-600 dark:text-violet-300 text-xs leading-tight font-semibold' },
                    emerald:{ wrap: 'border-l-[3px] border-emerald-500 bg-emerald-50 dark:bg-emerald-500/20 rounded-r shadow-sm',
                              time: 'text-emerald-600 dark:text-emerald-300 text-xs leading-tight font-semibold' },
                    amber:  { wrap: 'border-l-[3px] border-amber-500 bg-amber-50 dark:bg-amber-500/20 rounded-r shadow-sm',
                              time: 'text-amber-600 dark:text-amber-300 text-xs leading-tight font-semibold' },
                },

                cardColor(id) {
                    const keys = Object.keys(this.cardPalette);
                    return this.cardPalette[keys[(id || 0) % keys.length]];
                },

                init() {
                    const urlDate = new URLSearchParams(window.location.search).get('date');
                    if (urlDate && /^\d{4}-\d{2}-\d{2}$/.test(urlDate)) {
                        this.activeDate = urlDate;
                    }
                    this.modalDate = this.activeDate;
                    const active = new Date(this.activeDate + 'T12:00:00');
                    this.viewYear = active.getFullYear();
                    this.viewMonth = active.getMonth();
                    this.buildMonth();
                    this.buildWeek();
                    this.fetchMonth();
                    this.fetchEvents();
                },

                fmt(date) {
                    const y = date.getFullYear();
                    const m = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    return y + '-' + m + '-' + day;
                },

                mondayOf(date) {
                    const d = new Date(date);
                    d.setHours(12, 0, 0, 0);
                    const dow = (d.getDay() + 6) % 7;
                    d.setDate(d.getDate() - dow);
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
                        this.monthCells.push({
                            date,
                            num: d.getDate(),
                            inMonth: d.getMonth() === this.viewMonth,
                            isToday: date === todayStr,
                        });
                    }
                    this.monthLabel = first.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
                },

                buildWeek() {
                    const names = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                    const todayStr = this.fmt(new Date());
                    const monday = this.mondayOf(new Date(this.activeDate + 'T12:00:00'));
                    this.weekDays = names.map((name, i) => {
                        const d = new Date(monday);
                        d.setDate(d.getDate() + i);
                        const date = this.fmt(d);
                        return {
                            date,
                            name,
                            num: d.getDate(),
                            isToday: date === todayStr,
                            full: d.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'short' }),
                        };
                    });
                    const opt = { day: 'numeric', month: 'short' };
                    const a = new Date(this.weekDays[0].date + 'T12:00:00').toLocaleDateString('en-GB', opt);
                    const b = new Date(this.weekDays[6].date + 'T12:00:00').toLocaleDateString('en-GB', opt);
                    this.weekLabel = a + ' – ' + b;
                    this.dayLabel = new Date(this.activeDate + 'T12:00:00')
                        .toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
                },

                setView(v) {
                    this.view = v;
                    if (v === 'week') this.fetchWeek();
                    else if (v === 'day') this.fetchEvents();
                    else { this.fetchMonth(); this.fetchEvents(); }
                },

                panMonth(dir) {
                    let m = this.viewMonth + dir;
                    let y = this.viewYear;
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

                fmtTime(hhmm) {
                    if (!hhmm) return '--:--';
                    const parts = String(hhmm).split(':');
                    let h = parseInt(parts[0], 10);
                    const m = (parts[1] || '00').padStart(2, '0');
                    if (isNaN(h)) return hhmm;
                    const suffix = h >= 12 ? 'PM' : 'AM';
                    h = h % 12 || 12;
                    return h + ':' + m + ' ' + suffix;
                },

                quickAdd(customerId) {
                    if (!this.canWrite) return;
                    this.customerId = customerId ? String(customerId) : '';
                    this.leadId = '';
                    this.openModal(this.activeDate);
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
                    this.fetchEvents();
                    if (this.view === 'week') this.fetchWeek();
                },

                createOn(date) {
                    this.selectDay(date);
                    if (this.canWrite) this.openModal(date);
                    else {
                        const evts = this.dayEvents(date);
                        if (evts.length === 1) this.openDrawer(evts[0]);
                        else this.setView('day');
                    }
                },

                openDrawer(event) {
                    this.selectedEvent = { ...event };
                    this.drawerOpen = true;
                },

                closeDrawer() {
                    this.drawerOpen = false;
                },

                followUpUrl(action) {
                    if (!this.selectedEvent) return '#';
                    return String(this.followUpBase).replace(/\/follow-ups\/?$/, '/follow-ups/' + this.selectedEvent.id + '/' + action);
                },

                fetchEvents() {
                    this.loading = true;
                    this.loadError = false;
                    fetch(this.apiUrl + '?date=' + encodeURIComponent(this.activeDate))
                        .then((r) => r.json())
                        .then((data) => {
                            this.events = Array.isArray(data) ? data : [];
                            this.monthEvents[this.activeDate] = this.events;
                        })
                        .catch(() => { this.events = []; this.loadError = true; })
                        .finally(() => { this.loading = false; });
                },

                fetchMonth() {
                    if (!this.monthCells.length) return;
                    const start = this.monthCells[0].date;
                    const end = this.monthCells[this.monthCells.length - 1].date;
                    fetch(this.apiUrl + '?start=' + start + '&end=' + end)
                        .then((r) => r.json())
                        .then((data) => {
                            const grouped = {};
                            (Array.isArray(data) ? data : []).forEach((e) => {
                                (grouped[e.date] = grouped[e.date] || []).push(e);
                            });
                            this.monthEvents = { ...this.monthEvents, ...grouped };
                        })
                        .catch(() => {});
                },

                fetchWeek() {
                    const monday = this.mondayOf(new Date(this.activeDate + 'T12:00:00'));
                    const sunday = new Date(monday);
                    sunday.setDate(sunday.getDate() + 6);
                    const start = this.fmt(monday);
                    const end = this.fmt(sunday);
                    fetch(this.apiUrl + '?start=' + start + '&end=' + end)
                        .then((r) => r.json())
                        .then((data) => {
                            const grouped = {};
                            (Array.isArray(data) ? data : []).forEach((e) => {
                                (grouped[e.date] = grouped[e.date] || []).push(e);
                            });
                            this.monthEvents = { ...this.monthEvents, ...grouped };
                        })
                        .catch(() => {});
                },

                dayEvents(date) {
                    return this.monthEvents[date] || [];
                },

                dayDots(date) {
                    return [0, 1, 2].filter((i) => (this.monthEvents[date] || []).length > i);
                },

                openModal(date) {
                    if (!this.canWrite) return;
                    this.modalDate = date || this.activeDate;
                    this.isModalOpen = true;
                },

                modalSummary() {
                    const d = new Date((this.modalDate || this.activeDate) + 'T12:00:00');
                    const dateStr = isNaN(d) ? this.modalDate : d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
                    return dateStr + (this.modalTime ? ', ' + this.modalTime : '');
                },

                filteredLeads() {
                    if (!this.customerId) return this.leads;
                    return this.leads.filter((l) => String(l.customer_id) === String(this.customerId));
                },

                filterLeads() {
                    const visible = this.filteredLeads().map((l) => String(l.id));
                    if (this.leadId && !visible.includes(String(this.leadId))) {
                        this.leadId = '';
                    }
                },

                syncCustomerFromLead() {
                    if (!this.leadId) return;
                    const lead = this.leads.find((l) => String(l.id) === String(this.leadId));
                    if (lead && !this.customerId) {
                        this.customerId = String(lead.customer_id);
                    }
                },
            };
        }
    </script>
</x-app-layout>
