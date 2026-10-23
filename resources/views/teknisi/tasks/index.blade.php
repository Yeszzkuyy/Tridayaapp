<x-app-layout>
<div class="px-4 sm:px-6 lg:px-8">
    @php
        $statusColors = [
            'waiting_lead' => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
            'accepted' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            'assigned' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
            'in_progress' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            'cancelled' => 'bg-slate-200 text-slate-500 dark:bg-slate-600 dark:text-slate-300',
        ];
    @endphp

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 sm:text-3xl">
                {{ $isSuperAdmin ? __('Technician Tasks') : __('My Tasks') }}
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ $isSuperAdmin ? __('All technician tasks') : __('Assigned to me') }} &middot; {{ $tasks->total() }} {{ __('tasks') }}
            </p>
        </div>
    </div>

    {{-- Filter --}}
    <div class="mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-600 dark:bg-slate-800">
        <form method="GET" action="{{ route('teknisi.tasks.index') }}" class="flex w-full flex-wrap items-center gap-3">
            <div class="flex min-w-48 flex-1 items-center gap-2">
                <label for="task-search" class="whitespace-nowrap text-sm text-slate-500 dark:text-slate-300">{{ __('Search') }}:</label>
                <input id="task-search" type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search title, customer, or location...') }}"
                       class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">
            </div>
            <div class="flex min-w-48 flex-1 items-center gap-2">
                <label for="task-status" class="whitespace-nowrap text-sm text-slate-500 dark:text-slate-300">{{ __('Status') }}:</label>
                <select id="task-status" name="status" class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">
                    <option value="">{{ __('All Statuses') }}</option>
                    @foreach(\App\Models\TechnicalRequest::STATUSES as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>
                            {{ \App\Models\TechnicalRequest::statusLabel($status) }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if($isSuperAdmin)
                <div class="flex min-w-48 flex-1 items-center gap-2">
                    <label for="task-technician" class="whitespace-nowrap text-sm text-slate-500 dark:text-slate-300">{{ __('Technician') }}:</label>
                    <select id="task-technician" name="technician_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">
                        <option value="">{{ __('All technicians') }}</option>
                        <option value="unassigned" @selected(request('technician_id') === 'unassigned')>{{ __('Unassigned') }}</option>
                        @foreach($technicians as $technician)
                            <option value="{{ $technician->id }}" @selected((string) request('technician_id') === (string) $technician->id)>
                                {{ $technician->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="flex gap-2">
                <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
                <x-icon-button as="a" icon="reset" href="{{ route('teknisi.tasks.index') }}" title="{{ __('Reset') }}" />
            </div>
        </form>
    </div>

    {{-- Task list --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-600 dark:bg-slate-800">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Customer') }}</th>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Task') }}</th>
                        @if($isSuperAdmin)
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Technician') }}</th>
                        @endif
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Location') }}</th>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Schedule') }}</th>
                        <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Status') }}</th>
                        <th class="px-6 py-4 text-right text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                    @forelse($tasks as $task)
                        @php
                            $customer = $task->lead?->customer;
                            $displayLocation = $task->location ?: $customer?->address;
                        @endphp
                        <tr class="group transition hover:bg-slate-50 dark:hover:bg-slate-700/40">
                            <td class="px-6 py-4">
                                <span class="block max-w-[200px] truncate font-semibold text-slate-800 dark:text-slate-100" title="{{ $customer?->name ?? '' }}">
                                    {{ $customer?->name ?? '-' }}
                                </span>
                                @if($customer?->company)
                                    <span class="mt-0.5 block max-w-[200px] truncate text-xs text-slate-500 dark:text-slate-400">{{ $customer->company }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <a href="{{ route('teknisi.tasks.show', $task) }}" class="block max-w-[220px] truncate font-medium text-accent-600 hover:underline dark:text-accent-400" title="{{ $task->title }}">
                                    {{ $task->title }}
                                </a>
                                <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">
                                    {{ \App\Models\TechnicalRequest::typeLabel($task->request_type) }}
                                </span>
                            </td>
                            @if($isSuperAdmin)
                                <td class="px-6 py-4">
                                    @if($task->technician)
                                        <div class="flex items-center gap-2">
                                            <x-user-avatar :user="$task->technician" size="h-7 w-7" />
                                            <span class="block max-w-[160px] truncate text-sm text-slate-700 dark:text-slate-200" title="{{ $task->technician->name }}">
                                                {{ $task->technician->name }}
                                            </span>
                                        </div>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500 dark:bg-slate-700 dark:text-slate-300">
                                            {{ __('Unassigned') }}
                                        </span>
                                    @endif
                                </td>
                            @endif
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                <span class="block max-w-[200px] truncate" title="{{ $displayLocation ?? '' }}">{{ $displayLocation ?: '-' }}</span>
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                @if($task->target_date)
                                    <span class="block">{{ $task->target_date->format('d M Y') }}</span>
                                    @if($task->target_time)
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ substr((string) $task->target_time, 0, 5) }}</span>
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusColors[$task->status] ?? 'bg-slate-200 text-slate-700' }}">
                                    {{ \App\Models\TechnicalRequest::statusLabel($task->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end">
                                    <x-icon-button as="a" icon="view" href="{{ route('teknisi.tasks.show', $task) }}" title="{{ __('View details') }}" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isSuperAdmin ? 7 : 6 }}" class="px-6 py-16 text-center">
                                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-700">
                                    <x-icon name="tools" class="h-6 w-6 text-slate-400" />
                                </div>
                                @if($isSuperAdmin)
                                    <p class="text-sm font-medium text-slate-500 dark:text-slate-300">{{ __('No technician tasks yet.') }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ __('Technical requests from Sales will appear here.') }}</p>
                                @else
                                    <p class="text-sm font-medium text-slate-500 dark:text-slate-300">{{ __('No tasks assigned to you yet.') }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ __('Tasks assigned by your Lead Technician will appear here.') }}</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tasks->hasPages())
            <div class="border-t border-slate-200 p-4 dark:border-slate-600">{{ $tasks->links() }}</div>
        @endif
    </div>
</div>
</x-app-layout>
