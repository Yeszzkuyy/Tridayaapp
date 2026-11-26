<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col gap-3 pt-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-content-primary sm:text-3xl">{{ __('Audit Log') }}</h1>
                <p class="mt-1 text-sm text-content-secondary">{{ __('Read-only history of important actions in the system.') }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-600 dark:bg-slate-800">
            <div class="border-b border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-700">
                <form method="GET" action="{{ route('admin-panel.audit-log') }}" class="flex flex-wrap items-end gap-3">
                    <div class="w-full sm:w-40">
                        <x-datepicker id="date_from" name="date_from" :value="$filters['date_from'] ?? ''" />
                    </div>
                    <div class="w-full sm:w-40">
                        <x-datepicker id="date_to" name="date_to" :value="$filters['date_to'] ?? ''" />
                    </div>
                    <div class="w-full sm:w-48">
                        <x-glide-select name="module" size="field" :label="__('Module')"
                            :options="$modules->map(fn ($module) => ['value' => $module, 'label' => ucfirst(str_replace('_', ' ', $module))])->all()"
                            :value="$filters['module'] ?? ''" :empty-label="__('All Modules')" />
                    </div>
                    <div class="w-full sm:w-44">
                        <x-glide-select name="action" size="field" :label="__('Action')"
                            :options="$actions->map(fn ($action) => ['value' => $action, 'label' => ucfirst(str_replace('_', ' ', $action))])->all()"
                            :value="$filters['action'] ?? ''" :empty-label="__('All Actions')" />
                    </div>
                    <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
                    <x-icon-button as="a" icon="reset" href="{{ route('admin-panel.audit-log') }}" title="{{ __('Reset') }}" />
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px]">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-600 dark:bg-slate-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-content-secondary">{{ __('User') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-content-secondary">{{ __('Module') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-content-secondary">{{ __('Action') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-content-secondary">{{ __('Record') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-content-secondary">{{ __('Changes') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-content-secondary">{{ __('IP Address') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-content-secondary">{{ __('Time') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-600">
                        @forelse($logs as $log)
                            @php
                                $old = $log->old_values ?? [];
                                $new = $log->new_values ?? [];
                                $keys = array_values(array_unique(array_merge(array_keys($old), array_keys($new))));
                                $tone = match ($log->action) {
                                    'created', 'activated', 'restored' => 'green',
                                    'updated', 'archived' => 'blue',
                                    'deleted', 'deactivated' => 'red',
                                    default => 'slate',
                                };
                            @endphp
                            <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-content-primary">{{ $log->causer_name ?: ($log->causer?->name ?? __('System')) }}</div>
                                    <div class="text-xs text-content-secondary">{{ $log->causer_email ?: ($log->causer?->email ?? '-') }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-content-secondary">{{ ucfirst(str_replace('_', ' ', $log->module)) }}</td>
                                <td class="px-4 py-3">
                                    <x-status-badge :color="$tone">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</x-status-badge>
                                </td>
                                <td class="px-4 py-3 text-sm text-content-secondary">
                                    {{ $log->subject_type ? class_basename($log->subject_type) : '-' }}@if($log->subject_id) #{{ $log->subject_id }}@endif
                                    @if($log->description)
                                        <div class="mt-0.5 text-xs text-content-tertiary">{{ $log->description }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($keys === [])
                                        <span class="text-xs text-content-tertiary">-</span>
                                    @else
                                        <div class="max-w-sm space-y-0.5 text-xs text-content-secondary">
                                            @foreach($keys as $key)
                                                <div class="truncate">
                                                    <span class="font-medium">{{ $key }}</span>:
                                                    {{ is_scalar($old[$key] ?? null) ? ($old[$key] ?? '-') : json_encode($old[$key] ?? null) }}
                                                    <span class="text-content-tertiary">→</span>
                                                    {{ is_scalar($new[$key] ?? null) ? ($new[$key] ?? '-') : json_encode($new[$key] ?? null) }}
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-content-secondary">{{ $log->ip_address ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-content-secondary">{{ $log->created_at?->format('d M Y H:i:s') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-content-tertiary">
                                    <x-icon name="activity" class="mx-auto mb-2 h-10 w-10" />
                                    <p>{{ __('No audit log yet') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($logs->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-600">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
