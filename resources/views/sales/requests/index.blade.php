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
            'todo' => 'bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-700/60 dark:text-slate-200 dark:border-slate-600',
            'in_progress' => 'bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800/60',
            'review' => 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/60',
            'done' => 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/60',
        ];
        $requestColors = [
            'waiting_lead' => 'bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-700/60 dark:text-slate-200 dark:border-slate-600',
            'accepted' => 'bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800/60',
            'rejected' => 'bg-red-50 text-red-700 border border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-800/60',
            'assigned' => 'bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-800/60',
            'in_progress' => 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/60',
            'waiting_sales_review' => 'bg-cyan-50 text-cyan-800 border border-cyan-200 dark:bg-cyan-950/40 dark:text-cyan-300 dark:border-cyan-800/60',
            'revision' => 'bg-orange-50 text-orange-800 border border-orange-200 dark:bg-orange-950/40 dark:text-orange-300 dark:border-orange-800/60',
            'completed' => 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/60',
            'cancelled' => 'bg-slate-100 text-slate-500 border border-slate-200 dark:bg-slate-700/60 dark:text-slate-400 dark:border-slate-600',
        ];
    @endphp

        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 sm:text-3xl">{{ __('Request') }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('One place for all your requests — Inside Sales, Price, and Engineer.') }}</p>
            </div>
            @if($tabs[$tab]['can_create'])
                <a href="{{ $tabs[$tab]['create'] }}"
                   title="{{ __('New Request') }}"
                   class="group relative inline-flex h-10 items-center overflow-hidden rounded-xl bg-accent-600 px-2.5 text-white shadow-sm shadow-accent-500/20 transition-all duration-500 ease-in-out hover:bg-accent-700 hover:px-4 hover:shadow-md hover:shadow-accent-500/30 hover:brightness-105 active:scale-95 shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500">
                    <svg class="h-5 w-5 shrink-0 transition-transform duration-500 ease-in-out group-hover:rotate-90" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span class="max-w-0 opacity-0 whitespace-nowrap overflow-hidden text-sm font-semibold transition-all duration-500 ease-in-out group-hover:max-w-36 group-hover:opacity-100 group-hover:ml-2">
                        {{ __('New Request') }}
                    </span>
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/40 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </a>
            @endif
        </div>

        <x-request-hub-tabs :tabs="$tabs" :active="$tab" :counts="$counts" route-name="sales.requests.index" :show-zero="true" />

        <div class="mb-6 text-center">
            <p class="mx-auto max-w-lg text-center text-sm text-slate-500 dark:text-slate-400">
                {{ $tabs[$tab]['desc'] }}
            </p>
        </div>

        {{-- Deep link notifikasi forum diskusi: panel diskusi task langsung
             dibuka di halaman Request, bukan pindah ke halaman detail task. --}}
        @if(!empty($openTask))
            <div class="mb-6">
                <div class="mb-3 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Inside Sales Discussion') }}</p>
                        <h2 class="mt-0.5 truncate text-lg font-bold text-slate-800 dark:text-slate-100">{{ $openTask->title }}</h2>
                        <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                            {{ $openTask->lead?->customer?->name ?? '-' }}
                            · {{ $openTask->assignee?->name ?? __('Unassigned') }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <x-icon-button as="a" icon="view" href="{{ route('lead-tasks.show', $openTask) }}" title="{{ __('View details') }}" />
                        <x-icon-button as="a" icon="back" href="{{ route('sales.requests.index', ['tab' => 'inside-sales']) }}" title="{{ __('Close discussion') }}" />
                    </div>
                </div>
                <livewire:lead-task-chat :task="$openTask" />
            </div>
        @endif

        @if($tab === 'inside-sales')
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
                                    @if(($task->attachments_count ?? 0) > 0)
                                        <span class="ml-1 inline-flex items-center gap-1 rounded-full bg-teal-50 px-2 py-0.5 text-[10px] font-semibold text-teal-700 dark:bg-teal-900/40 dark:text-teal-300"
                                              title="{{ __('Quotation attachment') }}">
                                            📎 {{ $task->attachments_count }}
                                        </span>
                                    @endif
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
                                    {{ $task->due_date?->format('d M Y') ?? '-' }}
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
                                <td colspan="6" class="px-5 py-12 text-center">
                                    <x-empty-state label="{{ __('inside sales requests') }}" description="{{ __('No quotation requests yet — ask Inside Sales to prepare one for your customer.') }}">
                                        @if($tabs['inside-sales']['can_create'])
                                            <a href="{{ $tabs['inside-sales']['create'] }}"
                                               class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl bg-accent-600 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-accent-500/20 transition-all duration-300 hover:bg-accent-700 hover:shadow-md hover:brightness-105 active:scale-95">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                </svg>
                                                <span>{{ __('New Request') }}</span>
                                            </a>
                                        @endif
                                    </x-empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($tasks->hasPages())
                <div class="mt-4 p-4">{{ $tasks->links() }}</div>
            @endif
        @elseif($tab === 'price')
            <div class="overflow-x-auto">
                <table class="w-full border-separate border-spacing-y-2 text-left">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <th class="px-5 py-3 first:rounded-l-xl last:rounded-r-xl bg-slate-50/80 dark:bg-slate-800/50">{{ __('Customer') }}</th>
                            <th class="px-5 py-3 first:rounded-l-xl last:rounded-r-xl bg-slate-50/80 dark:bg-slate-800/50">{{ __('Requested by') }}</th>
                            <th class="px-5 py-3 first:rounded-l-xl last:rounded-r-xl bg-slate-50/80 dark:bg-slate-800/50">{{ __('Engineer') }}</th>
                            <th class="px-5 py-3 first:rounded-l-xl last:rounded-r-xl bg-slate-50/80 dark:bg-slate-800/50">{{ __('Requested') }}</th>
                            <th class="px-5 py-3 first:rounded-l-xl last:rounded-r-xl bg-slate-50/80 dark:bg-slate-800/50">{{ __('Status') }}</th>
                            <th class="px-5 py-3 first:rounded-l-xl last:rounded-r-xl bg-slate-50/80 dark:bg-slate-800/50 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($priceRequests as $req)
                            <tr class="group rounded-xl border border-slate-200/80 bg-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700/60 dark:bg-slate-800 dark:hover:border-slate-600">
                                <td class="px-5 py-4 first:rounded-l-xl last:rounded-r-xl font-medium text-slate-800 dark:text-slate-100">
                                    <a href="{{ route('sales.price-requests.show', $req) }}" class="hover:text-accent-600 dark:hover:text-accent-400 transition-colors">
                                        {{ $req->customer?->name ?? '-' }}
                                    </a>
                                    <p class="text-xs text-slate-400 font-normal mt-0.5">{{ __('Price Request') }} #{{ $req->id }}</p>
                                </td>
                                <td class="px-5 py-4 first:rounded-l-xl last:rounded-r-xl text-sm text-slate-600 dark:text-slate-300">
                                    <div class="flex items-center gap-2">
                                        @if($req->requester)
                                            <x-user-avatar :user="$req->requester" size="w-6 h-6" text="text-[10px]" :clickable="false" />
                                        @endif
                                        <span class="truncate max-w-[140px]">{{ $req->requester?->name ?? '-' }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 first:rounded-l-xl last:rounded-r-xl text-sm text-slate-600 dark:text-slate-300">
                                    @if($req->assignee)
                                        <div class="flex items-center gap-2">
                                            <x-user-avatar :user="$req->assignee" size="w-6 h-6" text="text-[10px]" color="green" :clickable="false" />
                                            <span class="truncate max-w-[140px]">{{ $req->assignee->name }}</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400 italic">{{ __('Waiting for engineer') }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 first:rounded-l-xl last:rounded-r-xl text-sm text-slate-600 dark:text-slate-300 tabular-nums">
                                    {{ $req->created_at?->format('d M Y') ?? '-' }}
                                </td>
                                <td class="px-5 py-4 first:rounded-l-xl last:rounded-r-xl">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $requestColors[$req->status] ?? 'bg-slate-100 text-slate-700' }}">
                                        {{ \App\Models\PriceRequest::statusLabel($req->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 first:rounded-l-xl last:rounded-r-xl text-right">
                                    <x-icon-button as="a" icon="view" href="{{ route('sales.price-requests.show', $req) }}" title="{{ __('View details') }}" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center">
                                    <x-empty-state label="{{ __('price requests') }}" description="{{ __('No price requests yet — attach your quotation and Engineering will reply with the price.') }}">
                                        @if($tabs['price']['can_create'])
                                            <a href="{{ $tabs['price']['create'] }}"
                                               class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl bg-accent-600 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-accent-500/20 transition-all duration-300 hover:bg-accent-700 hover:shadow-md hover:brightness-105 active:scale-95">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                </svg>
                                                <span>{{ __('New Request') }}</span>
                                            </a>
                                        @endif
                                    </x-empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($priceRequests->hasPages())
                <div class="mt-4 p-4">{{ $priceRequests->links() }}</div>
            @endif
        @else
            <div class="overflow-x-auto">
                <table class="w-full border-separate border-spacing-y-2 text-left">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <th class="px-5 py-3 first:rounded-l-xl last:rounded-r-xl bg-slate-50/80 dark:bg-slate-800/50">{{ __('Title') }}</th>
                            <th class="px-5 py-3 first:rounded-l-xl last:rounded-r-xl bg-slate-50/80 dark:bg-slate-800/50">{{ __('Type') }}</th>
                            <th class="px-5 py-3 first:rounded-l-xl last:rounded-r-xl bg-slate-50/80 dark:bg-slate-800/50">{{ __('Requested by') }}</th>
                            <th class="px-5 py-3 first:rounded-l-xl last:rounded-r-xl bg-slate-50/80 dark:bg-slate-800/50">{{ __('Technician') }}</th>
                            <th class="px-5 py-3 first:rounded-l-xl last:rounded-r-xl bg-slate-50/80 dark:bg-slate-800/50">{{ __('Created') }}</th>
                            <th class="px-5 py-3 first:rounded-l-xl last:rounded-r-xl bg-slate-50/80 dark:bg-slate-800/50">{{ __('Status') }}</th>
                            <th class="px-5 py-3 first:rounded-l-xl last:rounded-r-xl bg-slate-50/80 dark:bg-slate-800/50 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($engineerRequests as $req)
                            <tr class="group rounded-xl border border-slate-200/80 bg-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700/60 dark:bg-slate-800 dark:hover:border-slate-600">
                                <td class="px-5 py-4 first:rounded-l-xl last:rounded-r-xl font-medium text-slate-800 dark:text-slate-100">
                                    <a href="{{ route('sales.technical-requests.show', $req) }}" class="hover:text-accent-600 dark:hover:text-accent-400 transition-colors">
                                        {{ $req->title }}
                                    </a>
                                    <p class="text-xs text-slate-400 font-normal mt-0.5">{{ __('Lead') }}: {{ $req->lead?->customer?->name ?? '#'.$req->lead_id }}</p>
                                </td>
                                <td class="px-5 py-4 first:rounded-l-xl last:rounded-r-xl text-sm text-slate-600 dark:text-slate-300">
                                    <span class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                                        {{ \App\Models\TechnicalRequest::typeLabel($req->request_type) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 first:rounded-l-xl last:rounded-r-xl text-sm text-slate-600 dark:text-slate-300">
                                    <div class="flex items-center gap-2">
                                        @if($req->requester)
                                            <x-user-avatar :user="$req->requester" size="w-6 h-6" text="text-[10px]" :clickable="false" />
                                        @endif
                                        <span class="truncate max-w-[140px]">{{ $req->requester?->name ?? '-' }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 first:rounded-l-xl last:rounded-r-xl text-sm text-slate-600 dark:text-slate-300">
                                    @if($req->technician)
                                        <div class="flex items-center gap-2">
                                            <x-user-avatar :user="$req->technician" size="w-6 h-6" text="text-[10px]" color="green" :clickable="false" />
                                            <span class="truncate max-w-[140px]">{{ $req->technician->name }}</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400 italic">{{ __('Waiting for technician') }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 first:rounded-l-xl last:rounded-r-xl text-sm text-slate-600 dark:text-slate-300 tabular-nums">
                                    {{ $req->created_at?->format('d M Y') ?? '-' }}
                                </td>
                                <td class="px-5 py-4 first:rounded-l-xl last:rounded-r-xl">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $requestColors[$req->status] ?? 'bg-slate-100 text-slate-700' }}">
                                        {{ \App\Models\TechnicalRequest::statusLabel($req->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 first:rounded-l-xl last:rounded-r-xl text-right">
                                    <x-icon-button as="a" icon="view" href="{{ route('sales.technical-requests.show', $req) }}" title="{{ __('View details') }}" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center">
                                    <x-empty-state label="{{ __('engineer requests') }}" description="{{ __('No engineer requests yet — need a survey, installation, or troubleshooting visit? Start here.') }}">
                                        @if($tabs['engineer']['can_create'])
                                            <a href="{{ $tabs['engineer']['create'] }}"
                                               class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl bg-accent-600 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-accent-500/20 transition-all duration-300 hover:bg-accent-700 hover:shadow-md hover:brightness-105 active:scale-95">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                </svg>
                                                <span>{{ __('New Request') }}</span>
                                            </a>
                                        @endif
                                    </x-empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($engineerRequests->hasPages())
                <div class="mt-4 p-4">{{ $engineerRequests->links() }}</div>
            @endif
        @endif
</x-app-layout>
