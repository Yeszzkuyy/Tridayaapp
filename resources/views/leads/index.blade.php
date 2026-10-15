<x-app-layout>
    @if(session('success') && session('success_card'))
        <x-status-card :message="session('success')" />
    @endif
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Daftar Lead / Opportunity') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Kelola lead marketing dan opportunity sales') }}</p>
        </div>
        @can('manage-marketing')
            <div class="flex items-center gap-2">
                <x-icon-button as="a" icon="import" href="{{ route('leads.import') }}" title="Import" />
                <x-icon-button as="a" icon="add" href="{{ route('leads.create') }}" title="Tambah Lead" />
            </div>
        @endcan
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600">
        <div class="p-5 border-b dark:border-slate-600 bg-slate-50 dark:bg-slate-700 rounded-t-2xl">
            <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
                <div>
                    <label class="text-sm font-medium text-slate-500">{{ __('Cari Customer') }}</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="{{ __('Nama customer...') }}"
                           class="mt-1 w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-500">Status</label>
                    <x-glide-select name="status" class="mt-1" label="Status"
                        :options="collect($statuses)->map(fn ($s) => ['value' => $s, 'label' => ucfirst($s)])->all()"
                        :value="request('status', '')" :empty-label="__('Semua Status')" autosubmit />
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-500">{{ __('Sumber') }}</label>
                    <x-glide-select name="source" class="mt-1" :label="__('Sumber')"
                        :options="collect($sources)->map(fn ($s) => ['value' => $s, 'label' => ucfirst(str_replace('_', ' ', $s))])->all()"
                        :value="request('source', '')" :empty-label="__('Semua Sumber')" autosubmit />
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-500">PT</label>
                    <x-glide-select name="pt_group" class="mt-1" label="PT"
                        :options="collect($ptGroups)->map(fn ($g) => ['value' => $g, 'label' => $g])->all()"
                        :value="request('pt_group', '')" :empty-label="__('Semua PT')" autosubmit />
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-500">{{ __('Tanggal Mulai') }}</label>
                    <x-datepicker name="date_from" value="{{ request('date_from') }}" class="mt-1"></x-datepicker>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-500">{{ __('Tanggal Akhir') }}</label>
                    <x-datepicker name="date_to" value="{{ request('date_to') }}" class="mt-1"></x-datepicker>
                </div>
                <div class="sm:col-span-2 lg:col-span-6 flex items-end gap-2">
                    <x-icon-button icon="filter" type="submit" title="Filter" />
                    <x-icon-button as="a" icon="reset" href="{{ route('leads.index') }}" title="Reset" />
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">Lead / Customer</th>
                        <th class="px-6 py-4 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Sumber') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">Partner</th>
                        <th class="px-6 py-4 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Kebutuhan') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Tanggal Masuk') }}</th>
                        <th class="px-6 py-4 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-600">
                    @forelse($leads as $lead)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition group">
                            <td class="px-6 py-4">
                                <div class="font-medium text-slate-900">{{ $lead->customer->name ?? 'N/A' }}</div>
                                <div class="text-sm text-slate-500">
                                    @if($lead->customer->company && $lead->customer->company !== $lead->customer->name)
                                        <span>{{ $lead->customer->company }}</span>
                                    @endif
                                    @if($lead->pt_group)<span class="inline-flex px-1.5 py-0.5 rounded {{ \App\Models\Lead::PT_COLORS[$lead->pt_group] ?? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' }} text-[11px] font-semibold mr-1">{{ $lead->pt_group }}</span>@endif
                                    {{ $lead->customer->contact_person ? 'PIC: '.$lead->customer->contact_person : '' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium
                                    @switch($lead->status)
                                        @case('cool') bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 @break
                                        @case('warm') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300 @break
                                        @case('hot') bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300 @break
                                        @case('won') bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300 @break
                                        @case('lost') bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 @break
                                        @default bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200
                                    @endswitch
                                ">
                                    {{ ucfirst($lead->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-left">
                                @if($lead->source)
                                    <span class="text-sm text-slate-700">{{ \App\Http\Controllers\LeadController::label($lead->source) }}</span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-left">
                                @if($lead->partner)
                                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ \App\Models\Partner::TYPE_DOTS[$lead->partner->type] ?? '#94a3b8' }}"></span>
                                        {{ $lead->partner->name }}
                                    </span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                @if($lead->kebutuhan)
                                    <span class="text-sm text-slate-700 whitespace-normal break-words min-w-[200px] inline-block align-bottom">{{ $lead->kebutuhan }}</span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($lead->incoming_date)
                                    <span class="text-sm text-slate-700">{{ $lead->incoming_date->format('d M Y') }}</span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex min-w-max items-center justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                                    @can('view-marketing')
                                        <x-icon-button as="a" icon="view" href="{{ route('leads.show', $lead) }}" title="{{ __('Lihat detail lead') }}" />
                                    @endcan
                                    @can('manage-marketing')
                                        <x-icon-button as="a" icon="edit" href="{{ route('leads.edit', $lead) }}" title="Edit lead" />
                                        <form action="{{ route('leads.destroy', $lead) }}" method="POST" onsubmit="return confirm('{{ __('Hapus lead ini?') }}')" class="inline">
                                            @csrf @method('DELETE')
                                            <x-icon-button icon="delete" type="submit" title="{{ __('Hapus lead') }}" />
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-500">
                                {{ __('Belum ada lead.') }} @can('manage-marketing')<a href="{{ route('leads.create') }}" class="text-accent-600 hover:underline">{{ __('Tambah lead pertama') }}</a>@endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t">
            {{ $leads->links() }}
        </div>
    </div>
</x-app-layout>