<x-app-layout>
    @php
        $user = auth()->user();
        $canViewSales = $user && ($user->can('view-sales') || $user->can('manage-sales-leads'));
        $canCreateInsideSales = $user && ($user->can('manage-sales') || $user->can('manage-sales-leads') || $user->can('manage-marketing'));
        $taskColors = [
            'todo' => 'bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-700/60 dark:text-slate-200 dark:border-slate-600',
            'in_progress' => 'bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800/60',
            'review' => 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/60',
            'done' => 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/60',
        ];
    @endphp

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 sm:text-3xl">{{ __('Request') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('One place for all your requests — Inside Sales, Price, and Engineer.') }}</p>
        </div>
        @if($canCreateInsideSales)
            <a href="{{ route('lead-tasks.create') }}"
               class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl bg-accent-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-accent-500/20 transition-all duration-300 hover:bg-accent-700 hover:shadow-md hover:shadow-accent-500/30 hover:brightness-105 active:scale-95 shrink-0">
                <svg class="h-4 w-4 transition-transform duration-200 group-hover:rotate-90" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span>{{ __('New Request') }}</span>
                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/40 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
            </a>
        @endif
    </div>

    @if($canViewSales)
        @php
            $tabs = [
                'inside-sales' => [
                    'label' => __('Inside Sales'),
                    'icon' => 'user',
                    'desc' => __('Ask Inside Sales to prepare a quotation for your customer.'),
                ],
                'price' => [
                    'label' => __('Price'),
                    'icon' => 'receipt',
                    'desc' => __('Attach your quotation — Engineering replies with the installation price.'),
                ],
                'engineer' => [
                    'label' => __('Engineer'),
                    'icon' => 'tools',
                    'desc' => __('Survey, installation, troubleshooting, demo, or technical consultation in the field.'),
                ],
            ];
        @endphp
        <x-request-hub-tabs :tabs="$tabs" active="inside-sales" :counts="['inside-sales' => $tasks->total()]" route-name="sales.requests.index" :show-zero="true" />
    @endif

    <div class="mb-6 text-center">
        <p class="mx-auto max-w-lg text-center text-sm text-slate-500 dark:text-slate-400">
            {{ __('Ask Inside Sales to prepare a quotation for your customer.') }}
        </p>
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
        <form method="GET" action="{{ route('lead-tasks.index') }}" class="flex flex-wrap items-center gap-3">
            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Status') }}:</label>
            <select name="status" onchange="this.form.submit()"
                    class="rounded-xl border-slate-300 py-1.5 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                <option value="">{{ __('All') }}</option>
                @foreach(\App\Models\LeadTask::STATUSES as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>
                        {{ \App\Models\LeadTask::statusLabel($status) }}
                    </option>
                @endforeach
            </select>
            @if(request()->filled('status'))
                <x-icon-button as="a" icon="reset" href="{{ route('lead-tasks.index') }}" title="{{ __('Reset') }}" />
            @endif
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full border-separate border-spacing-y-2 text-left">
            <thead>
                <tr class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    <th class="px-5 py-3 first:rounded-l-xl last:rounded-r-xl bg-slate-50/80 dark:bg-slate-800/50">{{ __('Task / Title') }}</th>
                    <th class="px-5 py-3 first:rounded-l-xl last:rounded-r-xl bg-slate-50/80 dark:bg-slate-800/50">{{ __('Lead') }}</th>
                    <th class="px-5 py-3 first:rounded-l-xl last:rounded-r-xl bg-slate-50/80 dark:bg-slate-800/50">{{ __('Assigned To') }}</th>
                    <th class="px-5 py-3 first:rounded-l-xl last:rounded-r-xl bg-slate-50/80 dark:bg-slate-800/50">{{ __('Due Date') }}</th>
                    <th class="px-5 py-3 first:rounded-l-xl last:rounded-r-xl bg-slate-50/80 dark:bg-slate-800/50">{{ __('Status') }}</th>
                    <th class="px-5 py-3 first:rounded-l-xl last:rounded-r-xl bg-slate-50/80 dark:bg-slate-800/50 text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tasks as $task)
                    <tr class="group rounded-xl border border-slate-200/80 bg-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700/60 dark:bg-slate-800 dark:hover:border-slate-600">
                        <td class="px-5 py-4 first:rounded-l-xl last:rounded-r-xl font-medium text-slate-800 dark:text-slate-100">
                            <a href="{{ route('lead-tasks.show', $task) }}" class="hover:text-accent-600 dark:hover:text-accent-400 transition-colors">
                                {{ $task->title }}
                            </a>
                            @if($task->description)
                                <p class="text-xs text-slate-400 line-clamp-1 mt-0.5 font-normal">{{ $task->description }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-4 first:rounded-l-xl last:rounded-r-xl text-sm text-slate-600 dark:text-slate-300">
                            {{ $task->lead?->customer?->name ?? '#'.$task->lead_id }}
                        </td>
                        <td class="px-5 py-4 first:rounded-l-xl last:rounded-r-xl text-sm text-slate-600 dark:text-slate-300">
                            @if($task->assignee)
                                <div class="flex items-center gap-2">
                                    <x-user-avatar :user="$task->assignee" size="w-6 h-6" text="text-[10px]" color="green" :clickable="false" />
                                    <span class="truncate max-w-[140px]">{{ $task->assignee->name }}</span>
                                </div>
                            @else
                                <span class="text-xs text-slate-400 italic">{{ __('Unassigned') }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 first:rounded-l-xl last:rounded-r-xl text-sm text-slate-600 dark:text-slate-300 tabular-nums">
                            {{ $task->due_date ? $task->due_date->format('d M Y') : '-' }}
                        </td>
                        <td class="px-5 py-4 first:rounded-l-xl last:rounded-r-xl">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $taskColors[$task->status] ?? 'bg-slate-100 text-slate-700' }}">
                                {{ \App\Models\LeadTask::statusLabel($task->status) }}
                            </span>
                        </td>
                        <td class="px-5 py-4 first:rounded-l-xl last:rounded-r-xl text-right">
                            <x-icon-button as="a" icon="view" href="{{ route('lead-tasks.show', $task) }}" title="{{ __('View details') }}" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-slate-400">
                            {{ __('No tasks yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($tasks->hasPages())
        <div class="mt-4 p-4">
            {{ $tasks->links() }}
        </div>
    @endif
</x-app-layout>
