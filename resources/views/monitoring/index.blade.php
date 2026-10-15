<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Dashboard Monitoring - Semua Divisi') }}</h1>
            <p class="text-slate-500 mt-1">
                {{ __('Overview lengkap aktivitas lintas divisi') }}
            </p>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 mb-6 flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
        <form method="GET" action="{{ route('monitoring.index') }}" class="flex flex-col sm:flex-row gap-4 w-full sm:w-auto">
            {{-- Search Customer --}}
            <div class="relative flex-1 max-w-md">
                <label for="search" class="sr-only">{{ __('Cari customer') }}</label>
                <input type="text"
                       id="search"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="{{ __('Cari customer...') }}"
                       class="w-full px-4 py-2 pl-10 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            {{-- Date Range --}}
            <div class="flex gap-2">
                <label for="date_from" class="sr-only">{{ __('Dari tanggal') }}</label>
                <x-datepicker id="date_from" name="date_from" value="{{ request('date_from') }}"></x-datepicker>
                <span class="flex items-center text-slate-400">{{ __('s/d') }}</span>
                <x-datepicker id="date_to" name="date_to" value="{{ request('date_to') }}"></x-datepicker>
            </div>

            <x-icon-button icon="filter" type="submit" title="Filter" />

            <x-icon-button as="a" icon="reset" href="{{ route('monitoring.index') }}" title="Reset" />
        </form>

        {{-- Divisi Filter (Checkbox) --}}
        <div class="flex flex-wrap gap-2 w-full sm:w-auto">
            @foreach($divisiOptions as $divisi)
                <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 cursor-pointer hover:bg-slate-50 transition">
                    <input type="checkbox"
                           name="divisi[]"
                           value="{{ $divisi }}"
                           {{ in_array($divisi, request('divisi', [])) ? 'checked' : '' }}
                           class="w-4 h-4 rounded border-slate-300 text-accent-600 focus:ring-accent-500">
                    <span class="text-sm font-medium text-slate-700 capitalize">{{ $divisi }}</span>
                </label>
            @endforeach
        </div>
    </div>

    {{-- Stats Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6 mb-8">
        {{-- Lead Masuk --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <div class="flex items-center gap-3">
                <div class="p-3 rounded-xl bg-blue-50">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-slate-500">{{ __('Lead Masuk') }}</p>
                    <p class="text-2xl font-bold text-slate-800">{{ $stats['lead_masuk'] ?? 0 }}</p>
                </div>
            </div>
        </div>

        {{-- Invoice Outstanding --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <div class="flex items-center gap-3">
                <div class="p-3 rounded-xl bg-orange-50">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Invoice Outstanding</p>
                    <p class="text-2xl font-bold text-slate-800">Rp {{ number_format($stats['invoice_outstanding'] ?? 0, 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        {{-- Instalasi Proses --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <div class="flex items-center gap-3">
                <div class="p-3 rounded-xl bg-purple-50">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-slate-500">{{ __('Instalasi Proses') }}</p>
                    <p class="text-2xl font-bold text-slate-800">{{ $stats['instalasi_proses'] ?? 0 }}</p>
                </div>
            </div>
        </div>

        {{-- Instalasi Selesai --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <div class="flex items-center gap-3">
                <div class="p-3 rounded-xl bg-emerald-50">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-slate-500">{{ __('Instalasi Selesai') }}</p>
                    <p class="text-2xl font-bold text-slate-800">{{ $stats['instalasi_selesai'] ?? 0 }}</p>
                </div>
            </div>
        </div>

        {{-- PO Menunggu Proses --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <div class="flex items-center gap-3">
                <div class="p-3 rounded-xl bg-amber-50">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-slate-500">{{ __('PO Menunggu Proses') }}</p>
                    <p class="text-2xl font-bold text-slate-800">{{ $stats['po_menunggu'] ?? 0 }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Customer Progress List --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="p-4 border-b border-slate-200 dark:border-slate-600">
            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-200">{{ __('Progress Tiap Customer') }}</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-200 uppercase tracking-wider">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-200 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-200 uppercase tracking-wider">{{ __('Divisi Terakhir Update') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-200 uppercase tracking-wider">{{ __('Waktu') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-200 uppercase tracking-wider">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-600">
                    @forelse($customers as $customer)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-4 py-3">
                                <div>
                                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ $customer->name }}</p>
                                    @if($customer->company)
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $customer->company }}</p>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium
                                    @php
                                        $statusColors = [
                                            'Baru' => 'bg-slate-100 text-slate-800',
                                            'Lead: cool' => 'bg-blue-100 text-blue-800',
                                            'Lead: warm' => 'bg-yellow-100 text-yellow-800',
                                            'Lead: hot' => 'bg-orange-100 text-orange-800',
                                            'Lead: won' => 'bg-green-100 text-green-800',
                                            'Lead: lost' => 'bg-red-100 text-red-800',
                                            'Invoice: unpaid' => 'bg-red-100 text-red-800',
                                            'Invoice: paid' => 'bg-green-100 text-green-800',
                                            'Invoice: cancelled' => 'bg-slate-100 text-slate-800',
                                            'PO: draft' => 'bg-slate-100 text-slate-800',
                                            'PO: diproses' => 'bg-amber-100 text-amber-800',
                                            'PO: selesai' => 'bg-green-100 text-green-800',
                                            'PO: dibatalkan' => 'bg-red-100 text-red-800',
                                            'Open' => 'bg-blue-100 text-blue-800',
                                            'On Progress' => 'bg-yellow-100 text-yellow-800',
                                            'Pending' => 'bg-orange-100 text-orange-800',
                                            'Hold' => 'bg-orange-100 text-orange-800',
                                            'Done' => 'bg-green-100 text-green-800',
                                            'Cancelled' => 'bg-red-100 text-red-800',
                                            'Warranty' => 'bg-purple-100 text-purple-800',
                                            'Maintenance' => 'bg-purple-100 text-purple-800',
                                        ];
                                    @endphp
                                    {{ $statusColors[$customer->overall_status] ?? 'bg-slate-100 text-slate-800' }}">
                                    {{ $customer->overall_status }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if($customer->latest_divisi)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium
                                        @php
                                            $divisiColors = [
                                                'marketing' => 'bg-blue-100 text-blue-800',
                                                'sales' => 'bg-green-100 text-green-800',
                                                'teknisi' => 'bg-purple-100 text-purple-800',
                                                'admin' => 'bg-orange-100 text-orange-800',
                                            ];
                                        @endphp
                                        {{ $divisiColors[$customer->latest_divisi] ?? 'bg-slate-100 text-slate-800' }}">
                                        <span class="w-1.5 h-1.5 rounded-full
                                            @php
                                                $divisiDots = [
                                                    'marketing' => 'bg-blue-500',
                                                    'sales' => 'bg-green-500',
                                                    'teknisi' => 'bg-purple-500',
                                                    'admin' => 'bg-orange-500',
                                                ];
                                            @endphp
                                            {{ $divisiDots[$customer->latest_divisi] ?? 'bg-slate-500' }}"></span>
                                        {{ ucfirst($customer->latest_divisi) }}
                                    </span>
                                @else
                                    <span class="text-slate-400 text-sm">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-500">
                                @if($customer->latest_activity && isset($customer->latest_activity['date']) && $customer->latest_activity['date'])
                                    {{ $customer->latest_activity['date']->diffForHumans() }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <x-icon-button as="a" icon="view" href="{{ route('customers.show', $customer) }}"
                                   label="{{ __('Lihat Detail') }}" title="{{ __('Lihat Detail') }}" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-slate-400">{{ __('Tidak ada data customer.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($customers->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $customers->links() }}
            </div>
        @endif
    </div>
</x-app-layout>