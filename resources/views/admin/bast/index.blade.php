<x-app-layout>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-content-primary dark:text-white">{{ __('BAST & Commercial Closing') }}</h1>
        </div>
        <x-back-button href="{{ route('admin.hub') }}" />
    </div>

    <form method="GET" action="{{ route('admin.bast.index') }}" class="mb-4 flex flex-wrap items-end gap-2">
        <div>
            <label for="q" class="block text-xs text-content-secondary mb-1">{{ __('Search') }}</label>
            <input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Project / customer / BAST') }}"
                   class="w-64 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
        </div>
        <div>
            <label for="status" class="block text-xs text-content-secondary mb-1">{{ __('Status') }}</label>
            <select id="status" name="status" class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                <option value="">{{ __('All') }}</option>
                <option value="ready" @selected($status === 'ready')>{{ __('Ready for Finance') }}</option>
                <option value="waiting_signature" @selected($status === 'waiting_signature')>{{ __('Waiting for BAST file') }}</option>
                <option value="bast" @selected($status === 'bast')>{{ __('BAST file uploaded') }}</option>
                <option value="open" @selected($status === 'open')>{{ __('Not closed') }}</option>
                <option value="closed" @selected($status === 'closed')>{{ __('Closed') }}</option>
            </select>
        </div>
        <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
        <x-icon-button as="a" icon="reset" href="{{ route('admin.bast.index') }}" title="{{ __('Reset') }}" />
    </form>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-content-secondary uppercase">{{ __('Project') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-content-secondary uppercase">{{ __('Customer') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-content-secondary uppercase">{{ __('Installation') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-content-secondary uppercase">{{ __('BAST') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-content-secondary uppercase">{{ __('BAST File') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-content-secondary uppercase">{{ __('Finance') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-content-secondary uppercase">{{ __('Closing') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-content-secondary uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                    @forelse($projects as $project)
                        @php
                            $invoiceActive = $project->invoices->where('status', '!=', 'cancelled');
                            $invoicePaid = $invoiceActive->isNotEmpty() && $invoiceActive->every(fn ($invoice) => $invoice->status === 'paid');
                            $deliveryDone = $project->goodsDeliveries->isNotEmpty() && $project->goodsDeliveries->every(fn ($delivery) => $delivery->status === 'delivered');
                            $latestInstallation = $project->instalasis->first();
                            $installationBadges = [
                                'scheduled' => 'bg-slate-200 text-content-secondary dark:bg-slate-600 dark:text-content-primary',
                                'on_progress' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                                'waiting' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
                                'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
                                'cancelled' => 'bg-slate-200 text-content-secondary dark:bg-slate-700 dark:text-content-secondary',
                            ];
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition group">
                            <td class="px-4 py-3">
                                <a href="{{ route('projects.show', $project) }}" class="font-medium text-content-primary hover:underline">{{ $project->project_name }}</a>
                                <span class="block text-xs text-content-tertiary">{{ $project->project_code ?? __('Not filled') }}</span>
                            </td>
                            <td class="px-4 py-3 text-content-secondary">{{ $project->customer?->name ?? __('Not filled') }}</td>
                            <td class="px-4 py-3">
                                @if($latestInstallation)
                                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full {{ $installationBadges[$latestInstallation->status] ?? 'bg-slate-200 text-content-secondary' }}">
                                        {{ __(ucfirst(str_replace('_', ' ', $latestInstallation->status))) }}
                                    </span>
                                    @if($latestInstallation->completed_at)
                                        <span class="block text-xs text-content-tertiary mt-1">{{ $latestInstallation->completed_at->format('d M Y H:i') }}</span>
                                    @endif
                                @else
                                    <span class="text-xs text-content-tertiary">{{ $project->requires_installation ? __('Not started') : __('Not required') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($project->bastDocument)
                                    <span class="font-mono text-sm text-content-primary">{{ $project->bast_number ?: __('BAST file') }}</span>
                                    <span class="block text-xs text-content-tertiary">{{ $project->bast_date?->format('d M Y') ?? __('Uploaded') }}</span>
                                    <a href="{{ route('admin.bast.document', $project) }}" target="_blank" rel="noopener"
                                       class="mt-1 inline-flex text-xs font-medium text-accent-600 hover:underline dark:text-accent-400">
                                        {{ __('Open document') }}
                                    </a>
                                @else
                                    <span class="text-xs text-content-tertiary">{{ __('Not filled') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-xs">
                                @if($project->isBastReady())
                                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">{{ __('Uploaded') }}</span>
                                @else
                                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">{{ __('Not yet') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($project->bast_ready_at)
                                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">{{ __('Ready') }}</span>
                                    <span class="block text-xs text-content-tertiary mt-1">{{ $project->bast_ready_at->format('d M Y') }}</span>
                                @else
                                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">{{ __('Waiting') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($project->commercial_closed_at)
                                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">{{ __('Closed') }}</span>
                                    <span class="block text-xs text-content-tertiary mt-1">{{ $project->commercial_closed_at->format('d M Y') }}</span>
                                @else
                                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-slate-200 text-content-secondary dark:bg-slate-600">{{ __('Open') }}</span>
                                    <span class="mt-1 block text-xs text-content-tertiary">
                                        {{ $deliveryDone ? __('Delivered') : __('Delivery pending') }} ·
                                        {{ $invoicePaid ? __('Paid') : __('Outstanding') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                                    @can('manage-admin')
                                        <x-icon-button as="a" icon="add" href="{{ route('admin.invoices.create', ['project_id' => $project->id]) }}" title="{{ __('Create Invoice') }}" />
                                        <x-icon-button as="a" icon="edit" href="{{ route('admin.bast.edit', $project) }}" title="{{ __('Edit BAST') }}" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-8 text-center text-content-tertiary">{{ __('No projects found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($projects->hasPages())<div class="p-4 border-t">{{ $projects->links() }}</div>@endif
    </div>

    {{-- BAST pekerjaan instalasi non-project (Request Sales) --}}
    <div class="mt-6">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-lg font-bold text-content-primary">{{ __('Non-project Installation BAST') }}</h2>
                <p class="text-sm text-content-secondary">{{ __('Installation jobs from Request Sales that are not tied to a project.') }}</p>
            </div>
            @if($installationJobs->total() > 0)
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                    {{ $installationJobs->total() }} {{ __('jobs') }}
                </span>
            @endif
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-600 dark:bg-slate-800">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                    <thead class="bg-slate-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-content-secondary">{{ __('Job') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-content-secondary">{{ __('Customer') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-content-secondary">{{ __('Engineer') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-content-secondary">{{ __('Report') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-content-secondary">{{ __('BAST') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase text-content-secondary">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                        @forelse($installationJobs as $job)
                            <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-700/40">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-content-primary">{{ $job->technicalRequest?->title ?? __('Installation #:id', ['id' => $job->id]) }}</p>
                                    <span class="block text-xs text-content-tertiary">{{ $job->schedule_date?->format('d M Y') ?? '-' }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-content-secondary">{{ $job->customer?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-content-secondary">{{ $job->technician?->name ?? $job->technician_pic ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $job->hasReport() ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-slate-200 text-content-secondary dark:bg-slate-600 dark:text-content-primary' }}">
                                        {{ $job->hasReport() ? __('Uploaded') : __('Not yet') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $job->isBastReady() ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' }}">
                                        {{ $job->isBastReady() ? __('Uploaded') : __('Waiting') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @can('manage-admin')
                                        <x-icon-button as="a" icon="edit" href="{{ route('admin.instalasi-bast.show', $job) }}" title="{{ __('Edit BAST') }}" />
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-content-tertiary">{{ __('No non-project installations waiting for BAST.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($installationJobs->hasPages())<div class="border-t p-4">{{ $installationJobs->links() }}</div>@endif
        </div>
    </div>
</x-app-layout>
