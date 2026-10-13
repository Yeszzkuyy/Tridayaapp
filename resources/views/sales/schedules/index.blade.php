<x-app-layout>
    <div id="sales-schedule-root" x-data="scheduleManager()" x-init="init()" data-api-url="{{ route('sales.schedules.api') }}">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Jadwal Sales') }}</h1>
                <p class="text-slate-500 mt-1" x-text="monthLabel"></p>
            </div>
            <div class="flex items-center gap-2">
                <x-icon-button as="a" icon="back" href="{{ route('sales.dashboard') }}" title="{{ __('Back') }}" />
                <x-icon-button title="{{ __('Previous month') }}" @click="prevMonth()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                </x-icon-button>
                <x-icon-button title="{{ __('Today') }}" @click="goToday()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                </x-icon-button>
                <x-icon-button title="{{ __('Next month') }}" @click="nextMonth()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                </x-icon-button>
                <x-icon-button icon="add" title="{{ __('Add') }}" @click="openModal(activeDate)" />
            </div>
        </div>

        {{-- Month grid --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-4 mb-4">
            <div class="grid grid-cols-7 gap-1 text-center mb-1" id="sales-month-grid">
                <template x-for="name in ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']" :key="name">
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 py-1" x-text="name"></span>
                </template>
                <template x-for="cell in monthCells" :key="cell.date">
                    <button type="button" @click="selectDay(cell.date)"
                            :class="cell.date === activeDate
                                ? 'bg-slate-800 text-white dark:bg-accent-600 shadow-md'
                                : (cell.inMonth
                                    ? 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700/50'
                                    : 'text-slate-300 hover:bg-slate-50 dark:text-slate-600 dark:hover:bg-slate-700/30')"
                            class="flex min-h-14 sm:min-h-16 flex-col items-center justify-start rounded-xl py-1.5 px-1 transition">
                        <span :class="(cell.isToday && cell.date !== activeDate) ? 'bg-accent-100 text-accent-700 dark:bg-accent-500/20 dark:text-accent-300' : ''"
                              class="flex h-7 w-7 items-center justify-center rounded-full text-sm font-semibold" x-text="cell.num"></span>
                        <span class="mt-0.5 hidden sm:block w-full truncate px-1 text-[11px] leading-tight opacity-80"
                              x-text="dayEvents(cell.date).length ? dayEvents(cell.date)[0].activity_time + ' ' + dayEvents(cell.date)[0].description : ''"></span>
                        <span class="mt-auto flex items-center gap-0.5 h-2">
                            <template x-for="i in dayDots(cell.date)" :key="i">
                                <span class="h-1.5 w-1.5 rounded-full bg-accent-500"></span>
                            </template>
                            <span x-show="dayEvents(cell.date).length > 3" class="text-[10px] font-semibold text-slate-400"
                                  x-text="'+' + (dayEvents(cell.date).length - 3)"></span>
                        </span>
                    </button>
                </template>
            </div>
        </div>

        {{-- Weekly strip (quick nav) --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-4 mb-4">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200" x-text="weekLabel"></p>
                <div class="flex items-center gap-1">
                    <button type="button" @click="shiftWeek(-1)" title="{{ __('Previous week') }}" aria-label="{{ __('Previous week') }}"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700/50 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                    </button>
                    <button type="button" @click="shiftWeek(1)" title="{{ __('Next week') }}" aria-label="{{ __('Next week') }}"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700/50 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                    </button>
                </div>
            </div>
            <div class="grid grid-cols-7 gap-1 sm:gap-2" id="sales-strip">
                <template x-for="day in weekDays" :key="day.date">
                    <button type="button" @click="selectDay(day.date)"
                            :class="day.date === activeDate
                                ? 'bg-slate-800 text-white dark:bg-accent-600'
                                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700/50'"
                            class="flex flex-col items-center rounded-xl py-2 sm:py-3 transition">
                        <span class="text-[11px] font-semibold uppercase tracking-wide opacity-70" x-text="day.name"></span>
                        <span class="text-lg sm:text-xl font-bold leading-tight" x-text="day.num"></span>
                        <span class="mt-1 flex items-center gap-0.5 h-1.5">
                            <template x-for="i in dayDots(day.date)" :key="i">
                                <span :class="day.date === activeDate ? 'bg-white/90' : 'bg-accent-500'" class="h-1.5 w-1.5 rounded-full"></span>
                            </template>
                        </span>
                    </button>
                </template>
            </div>
        </div>

        {{-- Event cards --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-4">
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 mb-3" x-text="dayLabel"></p>
            <div x-show="loading" class="text-center text-sm text-slate-500 py-8">{{ __('Memuat jadwal...') }}</div>
            <div x-show="!loading && events.length === 0" x-cloak class="text-center py-8">
                <p class="text-slate-500 text-sm mb-3">{{ __('Belum ada jadwal kunjungan hari ini.') }}</p>
            </div>
            <div class="space-y-3" x-show="!loading && events.length > 0">
                <template x-for="event in events" :key="event.id">
                    <div class="rounded-xl border border-slate-200 dark:border-slate-600 p-4 hover:shadow-md transition">
                        <div class="flex items-start justify-between gap-3">
                            <a :href="event.url" class="text-base font-semibold text-slate-800 dark:text-slate-100 hover:text-accent-600 dark:hover:text-accent-400" x-text="event.description"></a>
                            <span class="inline-flex shrink-0 px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200" x-text="event.activity_time || '--:--'"></span>
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
                    </div>
                </template>
            </div>
        </div>

        {{-- Modal tambah jadwal --}}
        <div x-show="isModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50" @click="isModalOpen = false"></div>
            <div class="relative w-full sm:max-w-2xl bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-600 p-6 overflow-y-auto" style="max-height:90vh">
                <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100 mb-1">{{ __('Tambah Jadwal Kunjungan') }}</h3>
                <p class="text-sm text-slate-500 mb-4">{{ __('Tersimpan sebagai follow-up dan masuk Activity Timeline Lead.') }}</p>

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
                            <input id="su-activity_time" type="time" name="activity_time" value="{{ old('activity_time') }}"
                                   class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">
                        </div>
                    </div>

                    <div>
                        <label for="su-location" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Lokasi') }}</label>
                        <input id="su-location" type="text" name="location" value="{{ old('location') }}" maxlength="150" placeholder="{{ __('Alamat / titik kumpul') }}"
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

    <script>
        window.__scheduleLeads = @json($leadItems);

        function scheduleManager() {
            return {
                apiUrl: document.getElementById('sales-schedule-root')?.dataset.apiUrl || '',
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
                isModalOpen: {{ $errors->any() ? 'true' : 'false' }},
                modalDate: @json($activeDate),
                customerId: @json((string) old('customer_id', '')),
                leadId: @json((string) old('lead_id', '')),
                leads: window.__scheduleLeads || [],

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
                    const monday = this.mondayOf(new Date(this.activeDate + 'T12:00:00'));
                    this.weekDays = names.map((name, i) => {
                        const d = new Date(monday);
                        d.setDate(d.getDate() + i);
                        const date = this.fmt(d);
                        return { date, name, num: d.getDate() };
                    });
                    const opt = { day: 'numeric', month: 'short' };
                    const a = new Date(this.weekDays[0].date + 'T12:00:00').toLocaleDateString('en-GB', opt);
                    const b = new Date(this.weekDays[6].date + 'T12:00:00').toLocaleDateString('en-GB', opt);
                    this.weekLabel = a + ' – ' + b;
                    this.dayLabel = new Date(this.activeDate + 'T12:00:00')
                        .toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
                },

                prevMonth() {
                    if (this.viewMonth === 0) { this.viewMonth = 11; this.viewYear--; }
                    else { this.viewMonth--; }
                    this.buildMonth();
                    this.fetchMonth();
                },

                nextMonth() {
                    if (this.viewMonth === 11) { this.viewMonth = 0; this.viewYear++; }
                    else { this.viewMonth++; }
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
                    this.fetchEvents();
                },

                fetchEvents() {
                    this.loading = true;
                    fetch(this.apiUrl + '?date=' + encodeURIComponent(this.activeDate))
                        .then((r) => r.json())
                        .then((data) => {
                            this.events = Array.isArray(data) ? data : [];
                            this.monthEvents[this.activeDate] = this.events;
                        })
                        .catch(() => { this.events = []; })
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

                dayEvents(date) {
                    return this.monthEvents[date] || [];
                },

                dayDots(date) {
                    return [0, 1, 2].filter((i) => (this.monthEvents[date] || []).length > i);
                },

                openModal(date) {
                    this.modalDate = date || this.activeDate;
                    this.isModalOpen = true;
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
