<x-app-layout>
    <div id="sales-schedule-root" x-data="scheduleManager()" x-init="init()" data-api-url="{{ route('sales.schedules.api') }}">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Jadwal Sales') }}</h1>
                <p class="text-slate-500 mt-1" x-text="weekLabel"></p>
            </div>
            <div class="flex items-center gap-2">
                <x-icon-button as="a" icon="back" href="{{ route('sales.dashboard') }}" title="{{ __('Back') }}" />
                <x-icon-button title="{{ __('Previous week') }}" @click="prevWeek()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                </x-icon-button>
                <x-icon-button title="{{ __('Today') }}" @click="goToday()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                </x-icon-button>
                <x-icon-button title="{{ __('Next week') }}" @click="nextWeek()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                </x-icon-button>
                <x-icon-button icon="add" title="{{ __('Add') }}" @click="openModal(activeDate)" />
            </div>
        </div>

        {{-- Weekly strip --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-4 mb-4">
            <div class="grid grid-cols-7 gap-1 sm:gap-2" id="sales-strip">
                <template x-for="day in weekDays" :key="day.date">
                    <button type="button" @click="selectDay(day.date)"
                            :class="day.date === activeDate
                                ? 'bg-slate-800 text-white dark:bg-accent-600'
                                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700/50'"
                            class="flex flex-col items-center rounded-xl py-2 sm:py-3 transition">
                        <span class="text-[11px] font-semibold uppercase tracking-wide opacity-70" x-text="day.name"></span>
                        <span class="text-lg sm:text-xl font-bold leading-tight" x-text="day.num"></span>
                        <span x-show="day.isToday" class="mt-1 h-1.5 w-1.5 rounded-full bg-accent-500"></span>
                    </button>
                </template>
            </div>
        </div>

        {{-- Event cards --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-4">
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
                weekOffset: 0,
                weekDays: [],
                weekLabel: '',
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
                    this.buildWeek();
                    this.fetchEvents();
                },

                mondayOf(date) {
                    const d = new Date(date + 'T12:00:00');
                    const dow = (d.getDay() + 6) % 7;
                    d.setDate(d.getDate() - dow);
                    return d;
                },

                fmt(date) {
                    const y = date.getFullYear();
                    const m = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    return y + '-' + m + '-' + day;
                },

                buildWeek() {
                    const names = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                    const todayStr = this.fmt(new Date());
                    const monday = this.mondayOf(new Date(Date.now() + this.weekOffset * 7 * 86400000));
                    this.weekDays = names.map((name, i) => {
                        const d = new Date(monday);
                        d.setDate(d.getDate() + i);
                        const date = this.fmt(d);
                        return { date, name, num: d.getDate(), isToday: date === todayStr };
                    });
                    const first = this.weekDays[0];
                    const last = this.weekDays[6];
                    const opt = { day: 'numeric', month: 'short' };
                    const a = new Date(first.date + 'T12:00:00').toLocaleDateString('en-GB', opt);
                    const b = new Date(last.date + 'T12:00:00').toLocaleDateString('en-GB', opt);
                    const year = new Date(last.date + 'T12:00:00').getFullYear();
                    this.weekLabel = a + ' – ' + b + ' ' + year;
                },

                prevWeek() { this.weekOffset--; this.buildWeek(); },
                nextWeek() { this.weekOffset++; this.buildWeek(); },
                goToday() {
                    this.weekOffset = 0;
                    this.buildWeek();
                    this.selectDay(this.fmt(new Date()));
                },
                selectDay(date) {
                    this.activeDate = date;
                    this.fetchEvents();
                },

                fetchEvents() {
                    this.loading = true;
                    fetch(this.apiUrl + '?date=' + encodeURIComponent(this.activeDate))
                        .then((r) => r.json())
                        .then((data) => { this.events = Array.isArray(data) ? data : []; })
                        .catch(() => { this.events = []; })
                        .finally(() => { this.loading = false; });
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
