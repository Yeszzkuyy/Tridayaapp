<x-app-layout>
    @php
        $statusColors = [
            'draft' => 'slate',
            'submitted' => 'blue',
            'approved' => 'green',
            'rejected' => 'red',
            'completed' => 'emerald',
        ];
        $items = $requestHarga->items ?? [];
    @endphp

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Price Request Detail') }}</h1>
                    <p class="text-slate-500 dark:text-slate-400 mt-1">
                        {{ $requestHarga->project?->project_name ?? __('No project') }}
                    </p>
                </div>
                <x-status-badge :color="$statusColors[$requestHarga->status] ?? 'slate'">
                    {{ ucfirst($requestHarga->status) }}
                </x-status-badge>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('teknisi.request-hargas.index') }}" title="{{ __('Back') }}" />
        </div>

        <div class="space-y-6">
            {{-- Info project & customer --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Project') }}</p>
                    <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">
                        {{ $requestHarga->project?->project_name ?? '-' }}
                    </p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Customer') }}</p>
                    <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">
                        {{ $requestHarga->project?->customer?->name ?? '-' }}
                    </p>
                </div>
            </div>

            {{-- Items --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-4">
                    {{ __('Item List') }} ({{ count($items) }})
                </h2>
                @if(count($items) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-slate-50 dark:bg-slate-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">#</th>
                                    <th class="px-4 py-3 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Device') }}</th>
                                    <th class="px-4 py-3 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Qty') }}</th>
                                    <th class="px-4 py-3 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Specification') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                @foreach($items as $index => $item)
                                    <tr>
                                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $index + 1 }}</td>
                                        <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-100">{{ $item['device'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ $item['quantity'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ $item['specification'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-slate-400">{{ __('No items yet.') }}</p>
                @endif
            </div>

            {{-- Notes --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-2">{{ __('Notes') }}</h2>
                <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                    {{ $requestHarga->notes ?: __('No notes.') }}
                </p>
            </div>

            {{-- Aksi --}}
            <div class="flex justify-end gap-3">
                <x-icon-button as="a" icon="edit" href="{{ route('teknisi.request-hargas.edit', $requestHarga) }}" title="{{ __('Edit') }}" />
                <x-icon-button as="a" icon="back" href="{{ route('teknisi.request-hargas.index') }}" title="{{ __('Back') }}" />
            </div>
        </div>
    </div>
</x-app-layout>