<x-app-layout>
    @php
        $user = auth()->user();
        $canCreateInsideSales = $user && ($user->can('manage-sales') || $user->can('manage-sales-leads') || $user->can('manage-marketing'));
        $canCreatePrice = $user && ($user->can('manage-sales') || $user->can('manage-inside-sales') || $user->can('manage-technician'));
        $canCreateEngineer = $user && ($user->can('manage-sales') || $user->can('manage-inside-sales'));
        $tabs = [
            'inside-sales' => [
                'label' => __('Inside Sales'),
                'icon' => 'user',
                'desc' => __('Ask Inside Sales to prepare a quotation for your customer.'),
                'create' => route('lead-tasks.create'),
                'can_create' => $canCreateInsideSales,
            ],
            'price' => [
                'label' => __('Price'),
                'icon' => 'receipt',
                'desc' => __('Attach your quotation — Engineering replies with the installation price.'),
                'create' => route('sales.price-requests.create'),
                'can_create' => $canCreatePrice,
            ],
            'engineer' => [
                'label' => __('Engineer'),
                'icon' => 'tools',
                'desc' => __('Survey, installation, troubleshooting, demo, or technical consultation in the field.'),
                'create' => route('sales.technical-requests.create'),
                'can_create' => $canCreateEngineer,
            ],
        ];
        $taskColors = [
            'todo' => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
            'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            'review' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'done' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
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
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 sm:text-3xl">{{ __('Request') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('One place for all your requests — Inside Sales, Price, and Engineer.') }}</p>
        </div>
        @if($tabs[$tab]['can_create'])
            <x-icon-button as="a" icon="add" href="{{ $tabs[$tab]['create'] }}" label="{{ __('New Request') }}" title="{{ __('New Request') }}" class="shrink-0" />
        @endif
    </div>

    <x-request-hub-tabs :tabs="$tabs" :active="$tab" :counts="$counts" route-name="sales.requests.index" :show-zero="true" />

    <div class="mb-4 flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-600 dark:bg-slate-800">
        <x-icon name="{{ $tabs[$tab]['icon'] }}" class="mt-0.5 h-4 w-4 shrink-0 text-accent-600 dark:text-accent-400" />
        <p class="text-sm leading-5 text-slate-600 dark:text-slate-300">{{ $tabs[$tab]['desc'] }}</p>
    </div>

    @if($tab === 'inside-sales')
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-600 dark:bg-slate-800" aria-label="{{ __('Inside Sales') }}">
            @forelse($tasks as $task)
                <x-request-list-item
                    :title="$task->title"
                    :subtitle="__('Lead').': '.($task->lead?->customer?->name ?? '#'.$task->lead_id)"
                    :status="\App\Models\LeadTask::statusLabel($task->status)"
                    :status-class="$taskColors[$task->status] ?? 'bg-slate-200 text-slate-700'"
                    :href="route('lead-tasks.show', $task)"
                    :action-title="__('View details')">
                    <x-request-meta :label="__('Assigned to')">
                        @if($task->assignee)
                            <x-user-avatar :user="$task->assignee" size="w-7 h-7" text="text-[11px]" color="green" :clickable="false" />
                            <span class="truncate">{{ $task->assignee->name }}</span>
                        @else
                            <span>{{ __('Unassigned') }}</span>
                        @endif
                    </x-request-meta>
                    <x-request-meta :label="__('Due Date')">
                        <span>{{ $task->due_date?->format('d M Y') ?? '-' }}</span>
                    </x-request-meta>
                    <x-request-meta :label="__('Created')">
                        <span>{{ $task->created_at?->format('d M Y') ?? '-' }}</span>
                    </x-request-meta>
                </x-request-list-item>
            @empty
                <x-empty-state label="{{ __('inside sales requests') }}" description="{{ __('No quotation requests yet — ask Inside Sales to prepare one for your customer.') }}">
                    @if($tabs['inside-sales']['can_create'])
                        <x-icon-button as="a" icon="add" href="{{ $tabs['inside-sales']['create'] }}" label="{{ __('New Request') }}" title="{{ __('New Request') }}" />
                    @endif
                </x-empty-state>
            @endforelse

            @if($tasks->hasPages())
                <div class="border-t border-slate-200 p-4 dark:border-slate-600">{{ $tasks->links() }}</div>
            @endif
        </section>
    @elseif($tab === 'price')
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-600 dark:bg-slate-800" aria-label="{{ __('Price') }}">
            @forelse($priceRequests as $req)
                <x-request-list-item
                    :title="$req->customer?->name ?? '-'"
                    :subtitle="__('Price Request').' #'.$req->id"
                    :status="\App\Models\PriceRequest::statusLabel($req->status)"
                    :status-class="$requestColors[$req->status] ?? 'bg-slate-200 text-slate-700'"
                    :href="route('sales.price-requests.show', $req)"
                    :action-title="__('View details')">
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
                <x-empty-state label="{{ __('price requests') }}" description="{{ __('No price requests yet — attach your quotation and Engineering will reply with the price.') }}">
                    @if($tabs['price']['can_create'])
                        <x-icon-button as="a" icon="add" href="{{ $tabs['price']['create'] }}" label="{{ __('New Request') }}" title="{{ __('New Request') }}" />
                    @endif
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
                    :href="route('sales.technical-requests.show', $req)"
                    :action-title="__('View details')">
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
                <x-empty-state label="{{ __('engineer requests') }}" description="{{ __('No engineer requests yet — need a survey, installation, or troubleshooting visit? Start here.') }}">
                    @if($tabs['engineer']['can_create'])
                        <x-icon-button as="a" icon="add" href="{{ $tabs['engineer']['create'] }}" label="{{ __('New Request') }}" title="{{ __('New Request') }}" />
                    @endif
                </x-empty-state>
            @endforelse

            @if($engineerRequests->hasPages())
                <div class="border-t border-slate-200 p-4 dark:border-slate-600">{{ $engineerRequests->links() }}</div>
            @endif
        </section>
    @endif
</x-app-layout>
