<x-app-layout>
    @php
        $tabs = [
            'price' => [
                'label' => __('Price'),
                'icon' => 'receipt',
                'desc' => __('Quotations from Sales that need an installation price.'),
            ],
            'engineer' => [
                'label' => __('Engineer'),
                'icon' => 'tools',
                'desc' => __('Survey, installation, troubleshooting, demo, and consultation requests from Sales.'),
            ],
        ];
        $priceColors = [
            'waiting_lead' => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
            'in_progress' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            'cancelled' => 'bg-slate-200 text-slate-500 dark:bg-slate-600 dark:text-slate-300',
        ];
        $techColors = $priceColors + [
            'accepted' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            'assigned' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
        ];
    @endphp
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Request Sales') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Incoming requests from Sales — take one to process or reply.') }}</p>
        </div>
    </div>

    <div class="mb-4 overflow-x-auto">
        <nav aria-label="{{ __('Request types') }}"
             class="inline-flex w-full sm:w-auto gap-1 rounded-2xl bg-slate-100 dark:bg-slate-700/60 p-1.5">
            @foreach($tabs as $key => $t)
                <a href="{{ route('teknisi.requests.index', ['tab' => $key]) }}"
                   @if($tab === $key) aria-current="page" @endif
                   class="flex flex-1 sm:flex-none items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 {{ $tab === $key
                       ? 'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 shadow-sm'
                       : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-100 hover:bg-white/60 dark:hover:bg-slate-800/50' }}">
                    <x-icon name="{{ $t['icon'] }}" class="h-5 w-5 {{ $tab === $key ? 'text-accent-600 dark:text-accent-400' : '' }}" />
                    <span>{{ $t['label'] }}</span>
                    @if(($openCounts[$key] ?? 0) > 0)
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold tabular-nums {{ $tab === $key ? 'bg-accent-500/10 text-accent-700 dark:bg-accent-500/20 dark:text-accent-200' : 'bg-slate-200/70 text-slate-500 dark:bg-slate-600/60 dark:text-slate-300' }}">{{ $openCounts[$key] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>
    </div>
    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ $tabs[$tab]['desc'] }}</p>

    @if($tab === 'price')
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Customer') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Requested by') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Status') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Requested') }}</th>
                        <th class="px-6 py-4 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                    @forelse($priceRequests as $req)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                            <td class="px-6 py-4">
                                <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $req->customer?->name ?? '-' }}</span>
                                <span class="block text-xs text-slate-500">{{ $req->assignee?->name ?? __('Unassigned') }}</span>
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $req->requester?->name ?? '-' }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold {{ $priceColors[$req->status] ?? 'bg-slate-200 text-slate-700' }}">
                                    {{ \App\Models\PriceRequest::statusLabel($req->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $req->created_at?->format('d M Y') ?? '-' }}</td>
                            <td class="px-6 py-4 text-right">
                                <x-icon-button as="a" icon="view" href="{{ route('sales.price-requests.show', $req) }}" label="{{ __('Process') }}" title="{{ __('Process') }}" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-empty-state label="{{ __('price requests') }}" description="{{ __('No price requests from Sales yet.') }}" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($priceRequests->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-600">{{ $priceRequests->links() }}</div>
        @endif
    </div>
    @else
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Request') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Type') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Lead') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Status') }}</th>
                        <th class="px-6 py-4 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                    @forelse($engineerRequests as $req)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                            <td class="px-6 py-4">
                                <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $req->title }}</span>
                                <span class="block text-xs text-slate-500">{{ $req->requester?->name ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ \App\Models\TechnicalRequest::typeLabel($req->request_type) }}</td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $req->lead?->customer?->name ?? '-' }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold {{ $techColors[$req->status] ?? 'bg-slate-200 text-slate-700' }}">
                                    {{ \App\Models\TechnicalRequest::statusLabel($req->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <x-icon-button as="a" icon="view" href="{{ route('sales.technical-requests.show', $req) }}" label="{{ __('Process') }}" title="{{ __('Process') }}" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-empty-state label="{{ __('engineer requests') }}" description="{{ __('No engineer requests from Sales yet.') }}" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($engineerRequests->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-600">{{ $engineerRequests->links() }}</div>
        @endif
    </div>
    @endif
</x-app-layout>
