<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Create Quotation') }}</h1>
                <p class="text-slate-500 mt-1">{{ __('Choose the lead you want to create a quotation for.') }}</p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('sales.proposals.index') }}" title="{{ __('Back') }}" />
        </div>

        <form method="GET" action="{{ route('sales.proposals.create') }}" class="mb-4">
            <div class="relative max-w-md">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input type="search" name="q" value="{{ $q ?? '' }}" autocomplete="off"
                       placeholder="{{ __('Search customer or lead #...') }}"
                       class="h-11 w-full rounded-xl border border-slate-300 pl-10 pr-4 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-600 dark:bg-slate-800">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                    <thead class="bg-slate-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Customer') }}</th>
                            <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Lead') }}</th>
                            <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Requirement') }}</th>
                            <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                        @forelse($leads as $lead)
                            <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                                <td class="px-5 py-4">
                                    <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $lead->customer?->name ?? '-' }}</span>
                                    @if($lead->pt_group)
                                        <span class="mt-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $lead->pt_group }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <span class="block text-sm text-slate-600 dark:text-slate-300">Lead #{{ $lead->id }}</span>
                                    <span class="text-xs text-slate-400">{{ \App\Models\Lead::salesStatusLabel($lead->status) }}</span>
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-600 dark:text-slate-300">
                                    {{ \App\Models\Lead::kebutuhanLabel($lead->kebutuhan) ?: '-' }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <x-icon-button as="a" icon="add"
                                                   href="{{ route('sales.proposals.create', array_filter(['lead_id' => $lead->id, 'price_request_id' => $priceRequest->id ?? null])) }}"
                                                   label="{{ __('Create Quotation') }}"
                                                   title="{{ __('Create Quotation') }}" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <x-empty-state label="{{ __('leads') }}" description="{{ __('No leads yet — create a lead first.') }}">
                                        @can('manage-marketing')
                                            <x-icon-button as="a" icon="add" href="{{ route('leads.create') }}" label="{{ __('Add Lead') }}" title="{{ __('Add Lead') }}" />
                                        @endcan
                                    </x-empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
