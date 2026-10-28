<x-app-layout>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('BAST & Commercial Closing') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Check installation completion, upload the signed BAST, and let Finance know the document is ready.') }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('admin.hub') }}" title="{{ __('Back') }}" />
    </div>

    <form method="GET" action="{{ route('admin.bast.index') }}" class="mb-4 flex flex-wrap items-end gap-2">
        <div>
            <label for="q" class="block text-xs text-slate-500 mb-1">{{ __('Search') }}</label>
            <input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Project / customer / BAST') }}"
                   class="w-64 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
        </div>
        <div>
            <label for="status" class="block text-xs text-slate-500 mb-1">{{ __('Status') }}</label>
            <select id="status" name="status" class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                <option value="">{{ __('All') }}</option>
                <option value="ready" @selected($status === 'ready')>{{ __('Ready for Finance') }}</option>
                <option value="waiting_signature" @selected($status === 'waiting_signature')>{{ __('Waiting for signed BAST') }}</option>
                <option value="bast" @selected($status === 'bast')>{{ __('BAST filled') }}</option>
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
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Project') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Customer') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Installation') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('BAST') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Signatures') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Finance') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Closing') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Actions') }}</th>
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
                                'scheduled' => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
                                'on_progress' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                                'waiting' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
                                'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
                                'cancelled' => 'bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-300',
                            ];
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition group">
                            <td class="px-4 py-3">
                                <a href="{{ route('projects.show', $project) }}" class="font-medium text-slate-800 dark:text-slate-100 hover:underline">{{ $project->project_name }}</a>
                                <span class="block text-xs text-slate-400">{{ $project->project_code ?? __('Not filled') }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $project->customer?->name ?? __('Not filled') }}</td>
                            <td class="px-4 py-3">
                                @if($latestInstallation)
                                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full {{ $installationBadges[$latestInstallation->status] ?? 'bg-slate-200 text-slate-700' }}">
                                        {{ __(ucfirst(str_replace('_', ' ', $latestInstallation->status))) }}
                                    </span>
                                    @if($latestInstallation->completed_at)
                                        <span class="block text-xs text-slate-400 mt-1">{{ $latestInstallation->completed_at->format('d M Y H:i') }}</span>
                                    @endif
                                @else
                                    <span class="text-xs text-slate-400">{{ $project->requires_installation ? __('Not started') : __('Not required') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($project->bast_number)
                                    <span class="font-mono text-sm text-slate-800 dark:text-slate-100">{{ $project->bast_number }}</span>
                                    <span class="block text-xs text-slate-400">{{ $project->bast_date?->format('d M Y') ?? __('Not filled') }}</span>
                                @else
                                    <span class="text-xs text-slate-400">{{ __('Not filled') }}</span>
                                @endif
                                @if($project->bastDocument)
                                    <a href="{{ route('admin.bast.document', $project) }}" target="_blank" rel="noopener"
                                       class="mt-1 inline-flex text-xs font-medium text-accent-600 hover:underline dark:text-accent-400">
                                        {{ __('Open document') }}
                                    </a>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-xs">
                                <span class="block {{ $project->bast_signed_3dy_at ? 'text-green-600 dark:text-green-400' : 'text-slate-400' }}">
                                    3DY: {{ $project->bast_signed_3dy_at ? $project->bast_signed_3dy_at->format('d M') : __('No') }}
                                </span>
                                <span class="block {{ $project->bast_signed_customer_at ? 'text-green-600 dark:text-green-400' : 'text-slate-400' }}">
                                    {{ __('Customer') }}: {{ $project->bast_signed_customer_at ? $project->bast_signed_customer_at->format('d M') : __('No') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($project->bast_ready_at)
                                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">{{ __('Ready') }}</span>
                                    <span class="block text-xs text-slate-400 mt-1">{{ $project->bast_ready_at->format('d M Y') }}</span>
                                @else
                                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">{{ __('Waiting') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($project->commercial_closed_at)
                                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">{{ __('Closed') }}</span>
                                    <span class="block text-xs text-slate-400 mt-1">{{ $project->commercial_closed_at->format('d M Y') }}</span>
                                @else
                                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200">{{ __('Open') }}</span>
                                    <span class="mt-1 block text-[11px] text-slate-400">
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
                        <tr><td colspan="8" class="py-8 text-center text-slate-400">{{ __('No projects found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($projects->hasPages())<div class="p-4 border-t">{{ $projects->links() }}</div>@endif
    </div>
</x-app-layout>
