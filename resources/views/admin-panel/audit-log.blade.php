<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Audit Log') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Riwayat aktivitas user di sistem') }}</p>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="p-4 border-b border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700">
            <form method="GET" action="{{ route('admin-panel.audit-log') }}" class="flex flex-wrap gap-4">
                <div>
                    <label for="date_from" class="sr-only">{{ __('Dari Tanggal') }}</label>
                    <x-datepicker id="date_from" name="date_from" value="{{ request('date_from') }}"></x-datepicker>
                </div>
                <div>
                    <label for="date_to" class="sr-only">{{ __('Sampai Tanggal') }}</label>
                    <x-datepicker id="date_to" name="date_to" value="{{ request('date_to') }}"></x-datepicker>
                </div>
                <div>
                    <label for="action" class="sr-only">{{ __('Action') }}</label>
                    <select id="action" name="action" class="px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent">
                        <option value="">{{ __('Semua Action') }}</option>
                        <option value="created" {{ request('action') === 'created' ? 'selected' : '' }}>{{ __('Created') }}</option>
                        <option value="updated" {{ request('action') === 'updated' ? 'selected' : '' }}>{{ __('Updated') }}</option>
                        <option value="deleted" {{ request('action') === 'deleted' ? 'selected' : '' }}>{{ __('Deleted') }}</option>
                    </select>
                </div>
                <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
                <x-icon-button as="a" icon="reset" href="{{ route('admin-panel.audit-log') }}" title="{{ __('Reset') }}" />
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-200 uppercase tracking-wider">{{ __('User') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-200 uppercase tracking-wider">{{ __('Action') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-200 uppercase tracking-wider">{{ __('Model') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-200 uppercase tracking-wider">{{ __('Description') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-200 uppercase tracking-wider">{{ __('Properties') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-200 uppercase tracking-wider">{{ __('IP Address') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-200 uppercase tracking-wider">{{ __('Waktu') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-600">
                    @forelse($logs as $log)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $log->causer->name ?? __('System') }}</div>
                                <div class="text-sm text-slate-500">{{ $log->causer->email ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                                    {{ $log->action === 'created' ? 'bg-green-100 text-green-800' : ($log->action === 'updated' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800') }}">
                                    {{ ucfirst($log->action) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ class_basename($log->subject_type) ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $log->description ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <div class="text-xs text-slate-500 max-w-xs truncate">
                                    {{ $log->properties ? json_encode($log->properties) : '-' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ $log->ip ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ $log->created_at->format('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-slate-400">
                                <svg class="w-12 h-12 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p>{{ __('Belum ada audit log') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="px-4 py-3 border-t border-slate-200">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</x-app-layout>