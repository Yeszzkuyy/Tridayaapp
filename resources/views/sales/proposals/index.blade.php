<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Proposal') }}</h1>
                <p class="text-slate-500 mt-1">{{ __('Daftar penawaran sales.') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <x-icon-button as="a" icon="back" href="{{ route('sales.dashboard') }}" title="{{ __('Back') }}" />
                <x-icon-button as="a" icon="add" href="{{ route('sales.proposals.create') }}" title="{{ __('Add') }}" />
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-4">
            @php
                $cards = [
                    ['label' => __('Draft'), 'value' => $kpi['draft'] ?? 0, 'href' => route('sales.proposals.index', ['status' => 'draft']), 'style' => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200'],
                    ['label' => __('Pending Technical'), 'value' => $kpi['pending_technical'] ?? 0, 'href' => route('sales.proposals.index', ['status' => 'pending_technical']), 'style' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300'],
                    ['label' => __('Sent'), 'value' => $kpi['sent'] ?? 0, 'href' => route('sales.proposals.index', ['status' => 'sent']), 'style' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300'],
                    ['label' => __('Viewed'), 'value' => $kpi['viewed'] ?? 0, 'href' => route('sales.proposals.index', ['status' => 'viewed']), 'style' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300'],
                    ['label' => __('Expiring Soon'), 'value' => $kpi['expiring'] ?? 0, 'href' => route('sales.proposals.index'), 'style' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300'],
                    ['label' => __('Won This Month'), 'value' => $kpi['won_month'] ?? 0, 'href' => route('sales.proposals.index', ['status' => 'accepted']), 'style' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'],
                ];
            @endphp
            @foreach($cards as $card)
                <a href="{{ $card['href'] }}" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-4 hover:shadow transition">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $card['label'] }}</p>
                    <p class="mt-1 inline-flex px-2.5 py-0.5 rounded-full text-xl font-extrabold {{ $card['style'] }}">{{ $card['value'] }}</p>
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('sales.proposals.index') }}" class="flex flex-wrap items-center gap-2 mb-4">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Cari nomor / customer...') }}"
                   class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
            <select name="status" class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                <option value="">{{ __('Semua status') }}</option>
                @foreach($statuses as $st)
                    <option value="{{ $st }}" @selected(request('status') === $st)>{{ \App\Models\Proposal::statusLabel($st) }}</option>
                @endforeach
            </select>
            <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
            <x-icon-button as="a" icon="reset" href="{{ route('sales.proposals.index') }}" title="{{ __('Reset') }}" />
        </form>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-4">
            @forelse($proposals as $proposal)
                <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700/50 {{ !$loop->last ? 'border-b border-slate-100 dark:border-slate-700' : '' }}">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate">{{ $proposal->proposal_number }} • {{ $proposal->customer }}</p>
                        <p class="text-xs text-slate-500">
                            Rp {{ number_format($proposal->grand_total, 0, ',', '.') }}
                            • {{ $proposal->lead->customer?->name ?? '' }}
                            @if($proposal->valid_until)
                                @php
                                    $daysLeft = (int) now()->startOfDay()->diffInDays($proposal->valid_until, false);
                                    $expClass = $daysLeft < 0 ? 'text-red-500 font-semibold' : ($daysLeft <= 7 ? 'text-orange-500 font-semibold' : '');
                                @endphp
                                • <span class="{{ $expClass }}">{{ $daysLeft < 0 ? __('Kedaluarsa') : __(':days hari lagi', ['days' => $daysLeft]) }}</span>
                            @endif
                        </p>
                    </div>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold shrink-0 {{ \App\Models\Proposal::statusBadgeClasses($proposal->status) }}">
                        {{ \App\Models\Proposal::statusLabel($proposal->status) }}
                    </span>
                    <x-icon-button as="a" icon="view" href="{{ route('sales.proposals.show', $proposal) }}" title="{{ __('View details') }}" />
                </div>
            @empty
                <p class="text-slate-500 text-center py-8">{{ __('Belum ada proposal.') }}</p>
            @endforelse
            <div class="mt-4">{{ $proposals->links() }}</div>
        </div>
    </div>
</x-app-layout>
