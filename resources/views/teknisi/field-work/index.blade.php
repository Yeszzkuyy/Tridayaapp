<x-app-layout>
<div class="w-full space-y-6">
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Field Work') }}</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1">{{ __('All installation and survey jobs, visible to every technician role.') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-icon-button as="a" href="{{ config('services.survey-report-template') }}" target="_blank" rel="noopener"
                           label="{{ __('Survey Template') }}" title="{{ __('Survey Template') }}">
                <x-icon name="file-text" class="h-5 w-5" />
            </x-icon-button>
            @include('teknisi.instalasis._bast-dropdown')
            @can('manage-technician')
                <x-icon-button as="a" icon="add" href="{{ route('teknisi.surveys.create') }}" label="{{ __('Add Survey') }}" title="{{ __('Add Survey') }}" />
                <x-icon-button as="a" icon="add" href="{{ route('teknisi.instalasis.create') }}" label="{{ __('Add Installation') }}" title="{{ __('Add Installation') }}" />
            @endcan
        </div>
    </div>

    {{-- Tabs --}}
    <div class="inline-flex flex-wrap gap-1 rounded-2xl bg-slate-100 p-1.5 dark:bg-slate-700">
        @foreach([
            'all' => __('All'),
            'installation' => __('Installation'),
            'survey' => __('Survey'),
        ] as $key => $label)
            <a href="{{ route('teknisi.field-work.index', array_filter(['tab' => $key, 'q' => $filters['q'], 'status' => $filters['status'], 'pic' => $filters['pic'], 'date_from' => $filters['date_from'], 'date_to' => $filters['date_to']])) }}"
               class="rounded-xl px-4 py-2 text-sm font-medium transition-colors {{ $tab === $key
                   ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-800 dark:text-slate-100'
                   : 'text-slate-500 hover:bg-slate-200 hover:text-slate-800 dark:text-slate-300 dark:hover:bg-slate-600 dark:hover:text-slate-100' }}">
                {{ $label }}
                <span class="ml-1 text-xs text-slate-400">{{ $tabCounts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['label' => __('Total'), 'value' => $stats['total'], 'hint' => __('All field work'), 'color' => 'text-slate-800 dark:text-slate-100'],
            ['label' => __('Active'), 'value' => $stats['active'], 'hint' => __('Planned, in progress, waiting'), 'color' => 'text-amber-600 dark:text-amber-400'],
            ['label' => __('Done This Month'), 'value' => $stats['done_month'], 'hint' => __('Completed this month'), 'color' => 'text-green-600 dark:text-green-400'],
            ['label' => __('Installation / Survey'), 'value' => $stats['installation'].' / '.$stats['survey'], 'hint' => __('By type'), 'color' => 'text-accent-600 dark:text-accent-400'],
        ] as $card)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                <p class="mt-2 text-2xl font-bold tabular-nums {{ $card['color'] }}">{{ $card['value'] }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $card['hint'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('teknisi.field-work.index') }}"
          class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-600 dark:bg-slate-800">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="min-w-48 flex-1">
            <label class="mb-1 block text-xs text-slate-500">{{ __('Search') }}</label>
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Title, customer, location...') }}"
                   class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">
        </div>
        <div>
            <label class="mb-1 block text-xs text-slate-500">{{ __('Status') }}</label>
            <select name="status" class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">
                <option value="">{{ __('All') }}</option>
                @foreach(['planned' => __('Planned'), 'in_progress' => __('In Progress'), 'waiting' => __('Waiting'), 'done' => __('Done'), 'cancelled' => __('Cancelled')] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-40">
            <label class="mb-1 block text-xs text-slate-500">{{ __('PIC') }}</label>
            <input type="text" name="pic" value="{{ $filters['pic'] }}" placeholder="{{ __('Technician name') }}"
                   class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">
        </div>
        <div>
            <label class="mb-1 block text-xs text-slate-500">{{ __('From') }}</label>
            <input type="date" name="date_from" value="{{ $filters['date_from'] }}"
                   class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">
        </div>
        <div>
            <label class="mb-1 block text-xs text-slate-500">{{ __('To') }}</label>
            <input type="date" name="date_to" value="{{ $filters['date_to'] }}"
                   class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">
        </div>
        <div class="flex gap-2">
            <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
            <x-icon-button as="a" icon="reset" href="{{ route('teknisi.field-work.index', ['tab' => $tab]) }}" title="{{ __('Reset') }}" />
        </div>
    </form>

    {{-- Table --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-600 dark:bg-slate-800">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Date') }}</th>
                        <th class="px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Job') }}</th>
                        <th class="px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Type') }}</th>
                        <th class="px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Customer') }}</th>
                        <th class="px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('PIC') }}</th>
                        <th class="px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Location') }}</th>
                        <th class="px-4 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Status') }}</th>
                        <th class="px-4 py-3.5 text-right text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                    @forelse($items as $item)
                        @php
                            $statusColor = [
                                'planned' => 'slate',
                                'in_progress' => 'yellow',
                                'waiting' => 'orange',
                                'done' => 'green',
                                'cancelled' => 'slate',
                            ][$item['status_key']] ?? 'slate';
                            $typeClass = $item['type'] === 'installation'
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300';
                        @endphp
                        <tr class="group transition hover:bg-slate-50 dark:hover:bg-slate-700/40">
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 dark:text-slate-300">
                                {{ $item['date'] ? \Illuminate\Support\Carbon::parse($item['date'])->format('d M Y') : '-' }}
                            </td>
                            <td class="px-4 py-4">
                                <a href="{{ $item['url'] }}" class="block max-w-[260px] truncate font-semibold text-slate-800 hover:underline dark:text-slate-100" title="{{ $item['title'] }}">
                                    {{ $item['title'] }}
                                </a>
                                @if($item['detail'])
                                    <span class="block max-w-[260px] truncate text-xs text-slate-500 dark:text-slate-400">{{ $item['detail'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $typeClass }}">{{ $item['type_label'] }}</span>
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-600 dark:text-slate-300">
                                <span class="block max-w-[180px] truncate">{{ $item['customer'] ?: '-' }}</span>
                                @if($item['project'])
                                    <span class="block max-w-[180px] truncate text-xs text-slate-400">{{ $item['project'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $item['pic'] ?: '-' }}</td>
                            <td class="px-4 py-4 text-sm text-slate-600 dark:text-slate-300">
                                <span class="block max-w-[160px] truncate">{{ $item['detail'] ?: '-' }}</span>
                            </td>
                            <td class="px-4 py-4">
                                <x-status-badge :color="$statusColor">{{ $item['status_label'] }}</x-status-badge>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex justify-end gap-1.5 opacity-60 transition-opacity group-hover:opacity-100">
                                    <x-icon-button as="a" icon="view" href="{{ $item['url'] }}" title="{{ __('View detail') }}" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-16">
                                <x-empty-state label="{{ __('field work') }}" description="{{ __('No installation or survey matches the filter.') }}" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($items->hasPages())
            <div class="border-t border-slate-100 p-4 dark:border-slate-700">{{ $items->links() }}</div>
        @endif
    </div>
</div>
</x-app-layout>
