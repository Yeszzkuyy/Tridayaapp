<x-app-layout>
    @php
        $statusColors = [
            'draft' => 'slate',
            'submitted' => 'blue',
            'approved' => 'green',
            'rejected' => 'red',
            'completed' => 'emerald',
        ];
    @endphp

    <div class="px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Request Harga') }}</h1>
                <p class="text-slate-500 dark:text-slate-400 mt-1">{{ __('Permintaan harga perangkat untuk project') }}</p>
            </div>
            <x-icon-button as="a" icon="add" href="{{ route('teknisi.request-hargas.create') }}" label="{{ __('+ Buat Request Harga') }}" title="{{ __('+ Buat Request Harga') }}" />
        </div>

        {{-- Filter --}}
        <form method="GET" action="{{ route('teknisi.request-hargas.index') }}"
              class="flex flex-wrap items-end gap-3 mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="w-full sm:w-56">
                <label class="block text-sm font-medium text-slate-700 mb-1 dark:text-slate-300">Status</label>
                <select name="status"
                        class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent bg-white dark:bg-slate-700 dark:border-slate-600 dark:text-slate-100">
                    <option value="">{{ __('Semua Status') }}</option>
                    @foreach($statusColors as $status => $color)
                        <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="w-full sm:w-64">
                <label class="block text-sm font-medium text-slate-700 mb-1 dark:text-slate-300">Project</label>
                <select name="project_id"
                        class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent bg-white dark:bg-slate-700 dark:border-slate-600 dark:text-slate-100">
                    <option value="">{{ __('Semua Project') }}</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }}>
                            {{ $project->project_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <x-icon-button icon="filter" type="submit" title="Filter" />
                <x-icon-button as="a" icon="reset" href="{{ route('teknisi.request-hargas.index') }}" title="Reset" />
            </div>
        </form>

        {{-- Tabel --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">Project</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">Customer</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Jumlah Item') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">Status</th>
                            <th class="px-6 py-4 text-right text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @forelse($requestHargas as $requestHarga)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition group">
                                <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-100">
                                    {{ $requestHarga->project?->project_name ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-400">
                                    {{ $requestHarga->project?->customer?->name ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-400">
                                    {{ count($requestHarga->items ?? []) }}
                                </td>
                                <td class="px-6 py-4">
                                    <x-status-badge :color="$statusColors[$requestHarga->status] ?? 'slate'">
                                        {{ ucfirst($requestHarga->status) }}
                                    </x-status-badge>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                                        <x-icon-button as="a" icon="view" href="{{ route('teknisi.request-hargas.show', $requestHarga) }}" title="{{ __('Lihat detail') }}" />
                                        <x-icon-button as="a" icon="edit" href="{{ route('teknisi.request-hargas.edit', $requestHarga) }}" title="Edit" />
                                        <form action="{{ route('teknisi.request-hargas.destroy', $requestHarga) }}" method="POST"
                                              onsubmit="return confirm('{{ __('Hapus request harga ini?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <x-icon-button icon="delete" type="submit" title="{{ __('Hapus') }}" />
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-16 text-center text-slate-400">
                                    {{ __('Belum ada request harga.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
        <div class="mt-4">{{ $requestHargas->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>