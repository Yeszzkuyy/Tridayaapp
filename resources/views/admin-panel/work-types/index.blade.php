<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col gap-3 pt-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-content-primary sm:text-3xl">{{ __('Work Type') }}</h1>
                <p class="mt-1 text-sm text-content-secondary">{{ __('Work Types used by projects can only be deactivated.') }}</p>
            </div>
            <x-icon-button as="a" icon="add" href="{{ route('admin-panel.work-types.create') }}" label="{{ __('Add Work Type') }}" title="{{ __('Add Work Type') }}" />
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-600 dark:bg-slate-800">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                    <thead class="bg-slate-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider text-content-secondary">{{ __('Name') }}</th>
                            <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider text-content-secondary">{{ __('Status') }}</th>
                            <th class="px-6 py-4 text-center text-xs font-medium uppercase tracking-wider text-content-secondary">{{ __('Projects') }}</th>
                            <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider text-content-secondary">{{ __('Created At') }}</th>
                            <th class="px-6 py-4 text-right text-xs font-medium uppercase tracking-wider text-content-secondary">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                        @forelse($workTypes as $workType)
                            <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                                <td class="px-6 py-4 font-medium text-content-primary">{{ __($workType->name) }}</td>
                                <td class="px-6 py-4">
                                    @if($workType->isActive())
                                        <x-status-badge color="green">{{ __('Active') }}</x-status-badge>
                                    @else
                                        <x-status-badge color="slate">{{ __('Inactive') }}</x-status-badge>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center text-sm font-semibold tabular-nums text-content-secondary">
                                    {{ $workType->projects_count }}
                                </td>
                                <td class="px-6 py-4 text-sm text-content-secondary">{{ $workType->created_at->format('d M Y H:i') }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        <x-icon-button as="a" icon="edit" href="{{ route('admin-panel.work-types.edit', $workType) }}" title="{{ __('Edit') }}" />

                                        @if($workType->isActive())
                                            <form action="{{ route('admin-panel.work-types.toggle-active', $workType) }}" method="POST" class="inline-flex"
                                                  data-confirm="{{ __('This Work Type will no longer be available for new projects. Existing projects keep using it.') }}"
                                                  data-confirm-title="{{ __('Deactivate this Work Type?') }}"
                                                  data-confirm-tone="warning"
                                                  data-confirm-label="{{ __('Yes, deactivate') }}">
                                                @csrf
                                                @method('PATCH')
                                                <x-icon-button icon="archive" type="submit" title="{{ __('Deactivate') }}" />
                                            </form>
                                        @else
                                            <form action="{{ route('admin-panel.work-types.toggle-active', $workType) }}" method="POST" class="inline-flex">
                                                @csrf
                                                @method('PATCH')
                                                <x-icon-button icon="restore" type="submit" title="{{ __('Activate') }}" />
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-16 text-center text-content-tertiary">
                                    {{ __('No Work Type yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
