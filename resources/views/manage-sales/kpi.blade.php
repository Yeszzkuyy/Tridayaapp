<x-app-layout>
    @if(session('success'))
        <div class="mb-6 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 p-4 text-emerald-800 dark:text-emerald-200 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500 text-white font-bold">✓</span>
                <p class="font-medium text-sm">{{ session('success') }}</p>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800 text-lg font-bold px-2">&times;</button>
        </div>
    @endif

    <div x-data="{
        editModalOpen: false,
        modalUser: '',
        modalUserId: '',
        modalMonth: {{ $month }},
        modalYear: {{ $year }},
        modalRevenue: 50000000,
        modalDeals: 3,
        modalNotes: '',
        openEditTarget(user) {
            this.modalUserId = user.user.id;
            this.modalUser = user.user.name;
            this.modalRevenue = user.target_revenue;
            this.modalDeals = user.target_deals;
            this.modalNotes = user.target_notes || '';
            this.editModalOpen = true;
        }
    }">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-accent-600 dark:text-accent-300">
                    {{ __('Management Evaluation') }}
                </p>
                <h1 class="mt-1 text-3xl font-bold text-slate-800 dark:text-white">{{ __('Sales KPI & Target Performance') }}</h1>
                <p class="text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Evaluasi pencapaian target bulanan, omset, dan closing project per anggota tim sales.') }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <form method="GET" action="{{ route('manage-sales.kpi') }}" class="flex items-center gap-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-xl p-1.5 shadow-sm">
                    <select name="month" onchange="this.form.submit()" class="text-xs font-medium bg-transparent border-0 focus:ring-0 text-slate-700 dark:text-slate-200 cursor-pointer">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create(null, $m, 1)->locale(app()->getLocale())->translatedFormat('F') }}
                            </option>
                        @endfor
                    </select>
                    <span class="text-slate-300 dark:text-slate-600">/</span>
                    <select name="year" onchange="this.form.submit()" class="text-xs font-medium bg-transparent border-0 focus:ring-0 text-slate-700 dark:text-slate-200 cursor-pointer">
                        @for($y = now()->year - 1; $y <= now()->year + 1; $y++)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </form>
                <x-icon-button as="a" icon="leads" href="{{ route('manage-sales.index') }}" title="{{ __('Manage Sales') }}" />
            </div>
        </div>

        {{-- Ringkasan Tim (Executive Summary) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Total Omset Tim') }}</p>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $teamSummary['team_revenue_pct'] >= 100 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' }}">
                        {{ $teamSummary['team_revenue_pct'] }}%
                    </span>
                </div>
                <p class="mt-2 text-2xl font-extrabold text-slate-800 dark:text-slate-100 tabular-nums">
                    Rp {{ number_format($teamSummary['total_actual_revenue'], 0, ',', '.') }}
                </p>
                <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                    <span>{{ __('Target:') }} Rp {{ number_format($teamSummary['total_target_revenue'], 0, ',', '.') }}</span>
                </div>
                <div class="mt-2 w-full bg-slate-100 dark:bg-slate-700 h-2 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500 {{ $teamSummary['team_revenue_pct'] >= 100 ? 'bg-emerald-500' : 'bg-accent-600' }}" style="width: {{ min(100, $teamSummary['team_revenue_pct']) }}%"></div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Closing Project Tim') }}</p>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $teamSummary['team_deals_pct'] >= 100 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300' }}">
                        {{ $teamSummary['team_deals_pct'] }}%
                    </span>
                </div>
                <p class="mt-2 text-2xl font-extrabold text-slate-800 dark:text-slate-100 tabular-nums">
                    {{ $teamSummary['total_actual_deals'] }} <span class="text-sm font-normal text-slate-400">/ {{ $teamSummary['total_target_deals'] }} Deals</span>
                </p>
                <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                    <span>{{ __('Tercapai:') }} {{ $teamSummary['total_actual_deals'] }} PO/Deals</span>
                </div>
                <div class="mt-2 w-full bg-slate-100 dark:bg-slate-700 h-2 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500 {{ $teamSummary['team_deals_pct'] >= 100 ? 'bg-emerald-500' : 'bg-indigo-600' }}" style="width: {{ min(100, $teamSummary['team_deals_pct']) }}%"></div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Performa Keseluruhan') }}</p>
                    <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300">
                        {{ $teamSummary['total_sales_count'] }} Sales
                    </span>
                </div>
                <p class="mt-2 text-2xl font-extrabold text-slate-800 dark:text-slate-100 tabular-nums">
                    {{ $teamSummary['team_overall_pct'] }}%
                </p>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Indeks rata-rata pencapaian omset dan closing deal tim.') }}
                </p>
            </div>

            <div class="bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-transparent bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-amber-500/30 dark:border-amber-500/30 p-5 relative overflow-hidden">
                <div class="absolute -right-4 -bottom-4 opacity-10 text-6xl">🏆</div>
                <p class="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                    🥇 {{ __('Top Performer Bulan Ini') }}
                </p>
                @if($teamSummary['top_performer'])
                    <p class="mt-2 text-lg font-bold text-slate-800 dark:text-slate-100 truncate">
                        {{ $teamSummary['top_performer']['user']->name }}
                    </p>
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-300 font-semibold tabular-nums">
                        Rp {{ number_format($teamSummary['top_performer']['actual_revenue'], 0, ',', '.') }} · {{ $teamSummary['top_performer']['actual_deals'] }} Deals
                    </p>
                    <p class="mt-2 text-xs font-bold text-emerald-600 dark:text-emerald-400">
                        {{ $teamSummary['top_performer']['revenue_pct'] }}% Pencapaian
                    </p>
                @else
                    <p class="mt-2 text-sm text-slate-400">{{ __('Belum ada data closing') }}</p>
                @endif
            </div>
        </div>

        {{-- Tabel Evaluasi & Penilaian Kinerja Sales --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
            <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold text-slate-800 dark:text-slate-100 text-lg">{{ __('Matriks KPI Per Sales') }}</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        {{ __('Periode :month :year — Klik ikon edit untuk mengatur target khusus sales.', ['month' => \Carbon\Carbon::create(null, $month, 1)->locale(app()->getLocale())->translatedFormat('F'), 'year' => $year]) }}
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900/40 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="py-3.5 px-4 w-12 text-center">{{ __('Peringkat') }}</th>
                            <th class="py-3.5 px-4">{{ __('Nama Sales') }}</th>
                            <th class="py-3.5 px-4 min-w-[220px]">{{ __('Target vs Realisasi Omset') }}</th>
                            <th class="py-3.5 px-4 min-w-[180px]">{{ __('Target vs Closing PO/Deal') }}</th>
                            <th class="py-3.5 px-4">{{ __('Status Penilaian') }}</th>
                            <th class="py-3.5 px-4 text-center w-24">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @forelse($leaderboard as $row)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition">
                                <td class="py-4 px-4 text-center font-bold text-slate-700 dark:text-slate-300">
                                    @if($row['rank'] === 1)
                                        <span class="inline-flex items-center justify-center h-7 w-7 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 font-extrabold text-sm shadow-sm ring-2 ring-amber-400/40">1🥇</span>
                                    @elseif($row['rank'] === 2)
                                        <span class="inline-flex items-center justify-center h-7 w-7 rounded-full bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 font-extrabold text-sm shadow-sm ring-2 ring-slate-300">2🥈</span>
                                    @elseif($row['rank'] === 3)
                                        <span class="inline-flex items-center justify-center h-7 w-7 rounded-full bg-amber-900/10 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 font-extrabold text-sm shadow-sm ring-2 ring-amber-700/30">3🥉</span>
                                    @else
                                        <span class="text-slate-400 text-xs">#{{ $row['rank'] }}</span>
                                    @endif
                                </td>
                                <td class="py-4 px-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-xl bg-accent-100 text-accent-700 dark:bg-accent-900/40 dark:text-accent-300 font-bold flex items-center justify-center text-sm shrink-0">
                                            {{ strtoupper(substr($row['user']->name, 0, 2)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-bold text-slate-800 dark:text-slate-100 truncate">{{ $row['user']->name }}</p>
                                            <p class="text-xs text-slate-400 truncate">{{ $row['user']->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="font-bold text-slate-800 dark:text-slate-100 tabular-nums">
                                            Rp {{ number_format($row['actual_revenue'], 0, ',', '.') }}
                                        </span>
                                        <span class="font-bold {{ $row['revenue_pct'] >= 100 ? 'text-emerald-600 dark:text-emerald-400' : ($row['revenue_pct'] >= 50 ? 'text-blue-600 dark:text-blue-400' : 'text-amber-600 dark:text-amber-400') }}">
                                            {{ $row['revenue_pct'] }}%
                                        </span>
                                    </div>
                                    <div class="w-full bg-slate-100 dark:bg-slate-700 h-2 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-500 {{ $row['revenue_pct'] >= 100 ? 'bg-emerald-500' : ($row['revenue_pct'] >= 50 ? 'bg-blue-500' : 'bg-amber-500') }}" style="width: {{ min(100, $row['revenue_pct']) }}%"></div>
                                    </div>
                                    <p class="text-[11px] text-slate-400 mt-1">
                                        {{ __('Target:') }} Rp {{ number_format($row['target_revenue'], 0, ',', '.') }}
                                        @if($row['is_custom_target'])
                                            <span class="text-accent-600 dark:text-accent-400 font-semibold">(Custom)</span>
                                        @endif
                                    </p>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="font-bold text-slate-800 dark:text-slate-100 tabular-nums">
                                            {{ $row['actual_deals'] }} <span class="text-slate-400 font-normal">/ {{ $row['target_deals'] }} PO</span>
                                        </span>
                                        <span class="font-bold {{ $row['deals_pct'] >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-400' }}">
                                            {{ $row['deals_pct'] }}%
                                        </span>
                                    </div>
                                    <div class="w-full bg-slate-100 dark:bg-slate-700 h-2 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-500 {{ $row['deals_pct'] >= 100 ? 'bg-emerald-500' : 'bg-indigo-500' }}" style="width: {{ min(100, $row['deals_pct']) }}%"></div>
                                    </div>
                                    <p class="text-[11px] text-slate-400 mt-1">
                                        {{ $row['active_leads'] }} {{ __('Active Leads in Pipeline') }}
                                    </p>
                                </td>
                                <td class="py-4 px-4">
                                    @if($row['overall_pct'] >= 100)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                            <span>🌟</span> Star Performer
                                        </span>
                                    @elseif($row['overall_pct'] >= 60)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                            <span>🚀</span> On Track
                                        </span>
                                    @elseif($row['actual_deals'] > 0 || $row['actual_revenue'] > 0)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                            <span>⚡️</span> Progressing
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                                            <span>⏳</span> Needs Push
                                        </span>
                                    @endif
                                </td>
                                <td class="py-4 px-4 text-center">
                                    <x-icon-button icon="edit" type="button" @click="openEditTarget({{ json_encode($row) }})" title="{{ __('Atur Target Sales') }}" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400">
                                    {{ __('Belum ada pengguna dengan role Sales terdaftar.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Modal Pengaturan Target Sales --}}
        <div x-cloak x-show="editModalOpen" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="editModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="editModalOpen = false" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div x-show="editModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white dark:bg-slate-800 rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-200 dark:border-slate-700">
                    <form method="POST" action="{{ route('manage-sales.targets.update') }}">
                        @csrf
                        <input type="hidden" name="user_id" :value="modalUserId">
                        <input type="hidden" name="period_month" :value="modalMonth">
                        <input type="hidden" name="period_year" :value="modalYear">

                        <div class="p-6">
                            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-4 mb-5">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800 dark:text-white" id="modal-title">
                                        {{ __('Atur Target Bulanan Sales') }}
                                    </h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        <span class="font-bold text-accent-600 dark:text-accent-400" x-text="modalUser"></span> — Periode {{ \Carbon\Carbon::create(null, $month, 1)->locale(app()->getLocale())->translatedFormat('F') }} {{ $year }}
                                    </p>
                                </div>
                                <button type="button" @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">
                                        {{ __('Target Nominal Omset (Rp)') }}
                                    </label>
                                    <input type="number" step="1000000" name="target_revenue" x-model="modalRevenue" required class="w-full rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:border-accent-500 focus:ring-accent-500 font-bold tabular-nums">
                                    <p class="text-[11px] text-slate-400 mt-1">{{ __('Contoh: 50000000 untuk 50 Juta Rupiah.') }}</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">
                                        {{ __('Target Jumlah Closing (PO/Deal)') }}
                                    </label>
                                    <input type="number" min="0" max="100" name="target_deals" x-model="modalDeals" required class="w-full rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:border-accent-500 focus:ring-accent-500 font-bold tabular-nums">
                                    <p class="text-[11px] text-slate-400 mt-1">{{ __('Jumlah PO atau project yang harus ditutup dalam bulan ini.') }}</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">
                                        {{ __('Catatan / Instruksi Khusus (Opsional)') }}
                                    </label>
                                    <textarea name="notes" x-model="modalNotes" rows="2" placeholder="{{ __('Misal: Fokus pada penetrasi produk baru atau follow up PT tertentu.') }}" class="w-full rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:border-accent-500 focus:ring-accent-500 text-sm"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="px-6 py-4 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-3">
                            <button type="button" @click="editModalOpen = false" class="group relative overflow-hidden rounded-xl bg-accent-500 hover:bg-accent-600 text-white px-5 py-2.5 text-sm font-medium transition-all duration-300 text-center">
                                {{ __('Batal') }}
                            </button>
                            <button type="submit" class="group relative overflow-hidden rounded-xl bg-accent-600 hover:bg-accent-700 text-white px-6 py-2.5 text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full"></span>
                                {{ __('Simpan Target') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
