<x-app-layout>
<div class="px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Daftar Survey') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Semua data survey lapangan') }}</p>
        </div>
        <div class="flex items-center gap-2">
            {{-- Template laporan survey (Google Docs) — selalu kelihatan di daftar --}}
            <x-icon-button as="a" href="{{ config('services.survey-report-template') }}" target="_blank" rel="noopener"
                           title="{{ __('Template') }}" aria-label="{{ __('Template') }}" label="{{ __('Template') }}">
                <x-icon name="file-text" class="h-5 w-5" />
            </x-icon-button>
            <x-icon-button as="a" icon="add" href="{{ route('teknisi.surveys.create') }}" title="{{ __('Tambah Survey') }}" aria-label="{{ __('Tambah Survey') }}" label="{{ __('Tambah Survey') }}" />
        </div>
    </div>

    {{-- Filter --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-4 mb-4 flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('teknisi.surveys.index') }}" class="flex flex-wrap items-center gap-3 w-full">
            <div class="flex items-center gap-2 min-w-48 flex-1">
                <label class="text-sm text-slate-500 whitespace-nowrap">{{ __('Sales') }}:</label>
                <select name="sales_user_id" class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500 text-sm">
                    <option value="">{{ __('Semua Sales') }}</option>
                    @foreach($salesUsers as $sales)
                        <option value="{{ $sales->id }}" {{ request('sales_user_id') == $sales->id ? 'selected' : '' }}>
                            {{ $sales->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2 min-w-48 flex-1">
                <label class="text-sm text-slate-500 whitespace-nowrap">{{ __('Cari') }}:</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Cari judul...') }}"
                       class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500 text-sm">
            </div>
            <div class="flex items-center gap-2 min-w-48 flex-1">
                <label class="text-sm text-slate-500 whitespace-nowrap">{{ __('Status') }}:</label>
                <select name="status" class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500 text-sm">
                    <option value="">{{ __('Semua Status') }}</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>{{ __('Draft') }}</option>
                    <option value="scheduled" {{ request('status') === 'scheduled' ? 'selected' : '' }}>{{ __('Scheduled') }}</option>
                    <option value="on_survey" {{ request('status') === 'on_survey' ? 'selected' : '' }}>{{ __('On Survey') }}</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>{{ __('Completed') }}</option>
                </select>
            </div>
            <div class="flex gap-2">
                <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
                <x-icon-button as="a" icon="reset" href="{{ route('teknisi.surveys.index') }}" title="{{ __('Reset') }}" />
            </div>
        </form>
    </div>

    {{-- Tabel --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Judul') }}</th>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Customer') }}</th>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Sales') }}</th>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Tanggal Survey') }}</th>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Lokasi') }}</th>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('PIC') }}</th>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Status') }}</th>
                        <th class="px-6 py-4 text-right text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                    @forelse($surveys as $survey)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition group">
                            <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-100">
                                <span class="block max-w-[220px] truncate" title="{{ $survey->title ?? '' }}">{{ $survey->title ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                <span class="block max-w-[180px] truncate" title="{{ $survey->customer?->name ?? '' }}">{{ $survey->customer?->name ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                <span class="block max-w-[180px] truncate" title="{{ $survey->sales?->name ?? '' }}">{{ $survey->sales?->name ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $survey->survey_date ? \Carbon\Carbon::parse($survey->survey_date)->format('d M Y') : '-' }}</td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                <span class="block max-w-[180px] truncate" title="{{ $survey->location ?? '' }}">{{ $survey->location ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $survey->pic ?? '-' }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $statusColors = ['draft' => 'slate', 'scheduled' => 'blue', 'on_survey' => 'yellow', 'completed' => 'green'];
                                    $statusLabels = ['draft' => __('Draft'), 'scheduled' => __('Scheduled'), 'on_survey' => __('On Survey'), 'completed' => __('Completed')];
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($survey->status === 'completed') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                                    @elseif($survey->status === 'on_survey') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300
                                    @elseif($survey->status === 'scheduled') bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300
                                    @else bg-slate-100 text-slate-800 dark:bg-slate-600 dark:text-slate-300 @endif">
                                    {{ $statusLabels[$survey->status] ?? $survey->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end">
                                    <x-dropdown align="right" width="56">
                                        <x-slot name="trigger">
                                            <x-icon-button icon="more" title="{{ __('Actions') }}" />
                                        </x-slot>
                                        <x-slot name="content">
                                            <div class="py-1">
                                                <x-dropdown-link :href="route('teknisi.surveys.show', $survey)">{{ __('View details') }}</x-dropdown-link>
                                                <x-dropdown-link :href="route('teknisi.surveys.edit', $survey)">{{ __('Edit') }}</x-dropdown-link>
                                                <hr class="my-1 border-slate-200 dark:border-slate-600">
                                                <form action="{{ route('teknisi.surveys.destroy', $survey) }}" method="POST" x-data
                                                      @submit.prevent="if(confirm('{{ __('Yakin ingin menghapus survey ini?') }}')) $el.submit()">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-red-600 transition duration-150 ease-in-out hover:bg-red-50 focus:bg-red-50 focus:outline-none dark:text-red-400 dark:hover:bg-red-500/10 dark:focus:bg-red-500/10">
                                                        {{ __('Hapus') }}
                                                    </button>
                                                </form>
                                            </div>
                                        </x-slot>
                                    </x-dropdown>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-16 text-center text-slate-400">
                                <p>{{ __('Belum ada data survey.') }}</p>
                                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('Buat survey dulu, lalu unggah laporan PDF dari barisnya.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        <div class="mt-4">{{ $surveys->links() }}</div>
        </div>
    </div>

    @include('teknisi.surveys._report-modal')
</div>
</x-app-layout>
