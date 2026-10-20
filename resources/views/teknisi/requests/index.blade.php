<x-app-layout>
    @php
        $tabs = [
            'price' => [
                'label' => __('Price'),
                'icon' => 'receipt',
                'desc' => __('Price quotations (PDF/file) shared with Sales — same table, connected.'),
            ],
            'engineer' => [
                'label' => __('Engineer'),
                'icon' => 'tools',
                'desc' => __('Survey, installation, troubleshooting, demo, and consultation requests from Sales.'),
            ],
        ];
        $requestColors = [
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
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 sm:text-3xl">{{ __('Request Sales') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Incoming requests — take one to process or reply. You can also create a price request with the same file form.') }}</p>
        </div>
        <x-icon-button as="a" icon="add" href="{{ route('sales.price-requests.create') }}" label="{{ __('New Price Request') }}" title="{{ __('New Price Request') }}" class="shrink-0" />
    </div>

    <x-request-hub-tabs :tabs="$tabs" :active="$tab" :counts="$openCounts" route-name="teknisi.requests.index" />

    <div class="mb-4 flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-600 dark:bg-slate-800">
        <x-icon name="{{ $tabs[$tab]['icon'] }}" class="mt-0.5 h-4 w-4 shrink-0 text-accent-600 dark:text-accent-400" />
        <p class="text-sm leading-5 text-slate-600 dark:text-slate-300">{{ $tabs[$tab]['desc'] }}</p>
    </div>

    @if($tab === 'price')
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-600 dark:bg-slate-800" aria-label="{{ __('Price') }}">
            @forelse($priceRequests as $req)
                <x-request-list-item
                    :title="$req->customer?->name ?? '-'"
                    :subtitle="__('Price Request').' #'.$req->id"
                    :status="\App\Models\PriceRequest::statusLabel($req->status)"
                    :status-class="$requestColors[$req->status] ?? 'bg-slate-200 text-slate-700'"
                    :href="route('teknisi.price-requests.show', $req)"
                    :action-title="__('Process')"
                    :action-label="__('Process')">
                    <x-request-meta :label="__('Requested by')">
                        @if($req->requester)
                            <x-user-avatar :user="$req->requester" size="w-7 h-7" text="text-[11px]" :clickable="false" />
                        @endif
                        <span class="truncate">{{ $req->requester?->name ?? '-' }}</span>
                    </x-request-meta>
                    <x-request-meta :label="__('Engineer')">
                        @if($req->assignee)
                            <x-user-avatar :user="$req->assignee" size="w-7 h-7" text="text-[11px]" color="green" :clickable="false" />
                            <span class="truncate">{{ $req->assignee->name }}</span>
                        @else
                            <span>{{ __('Waiting for engineer') }}</span>
                        @endif
                    </x-request-meta>
                    <x-request-meta :label="__('Requested')">
                        <span>{{ $req->created_at?->format('d M Y') ?? '-' }}</span>
                    </x-request-meta>
                </x-request-list-item>
            @empty
                <x-empty-state label="{{ __('price requests') }}" description="{{ __('No price requests yet — attach a quotation PDF and Engineering will reply with the price.') }}">
                    <x-icon-button as="a" icon="add" href="{{ route('sales.price-requests.create') }}" label="{{ __('New Price Request') }}" title="{{ __('New Price Request') }}" />
                </x-empty-state>
            @endforelse

            @if($priceRequests->hasPages())
                <div class="border-t border-slate-200 p-4 dark:border-slate-600">{{ $priceRequests->links() }}</div>
            @endif
        </section>
    @else
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-600 dark:bg-slate-800" aria-label="{{ __('Engineer') }}">
            @forelse($engineerRequests as $req)
                <x-request-list-item
                    :title="$req->title"
                    :subtitle="__('Lead').': '.($req->lead?->customer?->name ?? '#'.$req->lead_id)"
                    :status="\App\Models\TechnicalRequest::statusLabel($req->status)"
                    :status-class="$requestColors[$req->status] ?? 'bg-slate-200 text-slate-700'"
                    :href="route('teknisi.technical-requests.show', $req)"
                    :action-title="__('Process')"
                    :action-label="__('Process')">
                    <x-request-meta :label="__('Type')">
                        <span>{{ \App\Models\TechnicalRequest::typeLabel($req->request_type) }}</span>
                    </x-request-meta>
                    <x-request-meta :label="__('Requested by')">
                        @if($req->requester)
                            <x-user-avatar :user="$req->requester" size="w-7 h-7" text="text-[11px]" :clickable="false" />
                        @endif
                        <span class="truncate">{{ $req->requester?->name ?? '-' }}</span>
                    </x-request-meta>
                    <x-request-meta :label="__('Technician')">
                        @if($req->technician)
                            <x-user-avatar :user="$req->technician" size="w-7 h-7" text="text-[11px]" color="green" :clickable="false" />
                            <span class="truncate">{{ $req->technician->name }}</span>
                        @else
                            <span>{{ __('Waiting for technician') }}</span>
                        @endif
                    </x-request-meta>
                    <x-request-meta :label="__('Created')">
                        <span>{{ $req->created_at?->format('d M Y') ?? '-' }}</span>
                    </x-request-meta>
                </x-request-list-item>
            @empty
                <x-empty-state label="{{ __('engineer requests') }}" description="{{ __('No engineer requests from Sales yet.') }}" />
            @endforelse

            @if($engineerRequests->hasPages())
                <div class="border-t border-slate-200 p-4 dark:border-slate-600">{{ $engineerRequests->links() }}</div>
            @endif
        </section>
    @endif
</x-app-layout>
