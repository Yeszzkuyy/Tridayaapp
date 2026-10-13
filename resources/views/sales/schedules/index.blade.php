<x-app-layout>
    <div x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }" @open-schedule-modal.window="open = true">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Sales Schedule') }}</h1>
                <p class="text-slate-500 mt-1">{{ __('Agenda sales & inside sales.') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <x-icon-button as="a" icon="back" href="{{ route('sales.dashboard') }}" title="{{ __('Back') }}" />
                @can('manage-sales')
                    <x-icon-button as="a" icon="add" href="{{ route('sales.schedules.create') }}" title="{{ __('Add') }}" />
                @endcan
                @if(auth()->user()?->can('manage-inside-sales'))
                    <x-icon-button as="a" icon="add" href="{{ route('sales.schedules.create') }}" title="{{ __('Add') }}" />
                @endif
            </div>
        </div>

        {{-- Kalender interaktif kunjungan (sumber: follow_ups) --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-4 mb-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">{{ __('Visit Calendar') }}</h2>
                <p class="text-xs text-slate-500">{{ __('Klik tanggal untuk tambah jadwal kunjungan.') }}</p>
            </div>
            <div id="sales-calendar" style="min-height:480px" data-events-url="{{ route('sales.schedules.events') }}"></div>
        </div>

        <div class="flex flex-wrap items-center gap-2 mb-4">
            <a href="{{ route('sales.schedules.index', ['tab' => 'upcoming'] + request()->only('type')) }}"
               class="px-4 py-2 rounded-xl text-sm font-medium transition {{ $tab === 'upcoming' ? 'bg-accent-600 text-white' : 'bg-white text-slate-600 border border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600' }}">{{ __('Upcoming') }}</a>
            <a href="{{ route('sales.schedules.index', ['tab' => 'past'] + request()->only('type')) }}"
               class="px-4 py-2 rounded-xl text-sm font-medium transition {{ $tab === 'past' ? 'bg-accent-600 text-white' : 'bg-white text-slate-600 border border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600' }}">{{ __('Past') }}</a>
            <form method="GET" action="{{ route('sales.schedules.index') }}" class="flex items-center gap-2 ml-auto">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <select name="type" onchange="this.form.submit()"
                        class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                    <option value="">{{ __('Semua tipe') }}</option>
                    @foreach(\App\Models\SalesSchedule::TYPES as $type)
                        <option value="{{ $type }}" @selected(request('type') === $type)>{{ \App\Models\SalesSchedule::typeLabel($type) }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-4">
            @forelse($schedules as $schedule)
                <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700/50 {{ !$loop->last ? 'border-b border-slate-100 dark:border-slate-700' : '' }}">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold shrink-0 bg-blue-100 text-blue-800">
                        {{ \App\Models\SalesSchedule::typeLabel($schedule->type) }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate">{{ $schedule->title }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $schedule->start_at?->format('d M Y H:i') ?? '-' }}
                            • {{ $schedule->lead->customer?->name ?? 'Lead #'.$schedule->lead_id }}
                            • {{ __('PIC') }}: {{ $schedule->assignee?->name ?? '-' }}
                        </p>
                    </div>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold shrink-0 bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200">
                        {{ \App\Models\SalesSchedule::statusLabel($schedule->status) }}
                    </span>
                    <a href="{{ route('sales.schedules.show', $schedule) }}" title="{{ __('View details') }}" aria-label="{{ __('View details') }}"
                       class="h-10 w-10 inline-flex items-center justify-center rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                    </a>
                </div>
            @empty
                <p class="text-slate-500 text-center py-8">{{ __('Belum ada jadwal.') }}</p>
            @endforelse
            <div class="mt-4">{{ $schedules->links() }}</div>
        </div>

        {{-- Modal tambah jadwal kunjungan (self-service, simpan ke follow_ups) --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50" @click="open = false"></div>
            <div class="relative w-full sm:max-w-2xl bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-600 p-6 max-h-[90vh] overflow-y-auto">
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

                    <div>
                        <label for="su-customer_id" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Customer') }}</label>
                        <select id="su-customer_id" name="customer_id" required
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">
                            <option value="">{{ __('Pilih customer') }}</option>
                            @foreach($customers as $id => $label)
                                <option value="{{ $id }}" @selected(old('customer_id') == $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="su-lead_id" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Lead') }}</label>
                        <select id="su-lead_id" name="lead_id"
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-700 dark:border-slate-600">
                            <option value="">{{ __('Pilih lead (opsional)') }}</option>
                            @foreach($leadItems as $lead)
                                <option value="{{ $lead['id'] }}" data-customer="{{ $lead['customer_id'] }}" @selected(old('lead_id') == $lead['id'])>{{ $lead['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="su-follow_up_date" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Tanggal') }}</label>
                            <input id="su-follow_up_date" type="date" name="follow_up_date" value="{{ old('follow_up_date') }}" required
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
                        <button type="button" @click="open = false"
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

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script>
        function initSalesCalendar() {
            var el = document.getElementById('sales-calendar');
            if (!el || typeof FullCalendar === 'undefined') return;
            if (el.dataset.rendered === '1') return;
            el.dataset.rendered = '1';
            var calendar = new FullCalendar.Calendar(el, {
                initialView: 'dayGridMonth',
                firstDay: 1,
                height: 'auto',
                timeZone: 'Asia/Jakarta',
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
                events: el.dataset.eventsUrl,
                dateClick: function (info) {
                    var input = document.getElementById('su-follow_up_date');
                    if (input) input.value = info.dateStr;
                    window.dispatchEvent(new CustomEvent('open-schedule-modal'));
                },
                eventClick: function (info) {
                    if (info.event.url) {
                        info.jsEvent.preventDefault();
                        window.location.href = info.event.url;
                    }
                },
                dayMaxEvents: 3
            });
            calendar.render();
        }

        function filterScheduleLeads() {
            var customerId = document.getElementById('su-customer_id')?.value || '';
            var leadSelect = document.getElementById('su-lead_id');
            if (!leadSelect) return;
            leadSelect.querySelectorAll('option[data-customer]').forEach(function (opt) {
                opt.hidden = customerId !== '' && opt.dataset.customer !== customerId;
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            initSalesCalendar();
            document.getElementById('su-customer_id')?.addEventListener('change', filterScheduleLeads);
            document.getElementById('su-lead_id')?.addEventListener('change', function () {
                var selected = this.querySelector('option:checked');
                var customerSelect = document.getElementById('su-customer_id');
                if (selected && selected.dataset.customer && customerSelect && !customerSelect.value) {
                    customerSelect.value = selected.dataset.customer;
                    filterScheduleLeads();
                }
            });
            filterScheduleLeads();
        });
        document.addEventListener('livewire:navigated', initSalesCalendar);
    </script>
</x-app-layout>
