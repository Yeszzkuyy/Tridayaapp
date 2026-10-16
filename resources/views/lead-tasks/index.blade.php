<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Inside Sales Task') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Request for help with a lead.') }}</p>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-5 mb-6">
        <form method="GET" action="{{ route('lead-tasks.index') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Status') }}</label>
                <select name="status" onchange="this.form.submit()"
                        class="rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    <option value="">{{ __('All') }}</option>
                    @foreach(\App\Models\LeadTask::STATUSES as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>
                            {{ \App\Models\LeadTask::statusLabel($status) }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if(request()->filled('status'))
                <x-icon-button as="a" icon="reset" href="{{ route('lead-tasks.index') }}" title="{{ __('Reset') }}" />
            @endif
        </form>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Task') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Lead') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Status') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Due Date') }}</th>
                        <th class="px-6 py-4 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                    @forelse($tasks as $task)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                            <td class="px-6 py-4">
                                <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $task->title }}</span>
                                <span class="block text-xs text-slate-500">{{ $task->assignee?->name ?? __('Not assigned yet') }}</span>
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $task->lead?->customer?->name ?? '-' }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700">
                                    {{ \App\Models\LeadTask::statusLabel($task->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                {{ $task->due_date ? $task->due_date->format('d M Y') : '-' }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <x-icon-button as="a" icon="view" href="{{ route('lead-tasks.show', $task) }}" title="{{ __('View') }}" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-16 text-center text-slate-400">{{ __('No tasks yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tasks->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-600">
                {{ $tasks->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
