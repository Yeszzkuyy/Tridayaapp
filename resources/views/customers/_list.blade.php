@php
    $hasFilter = request()->filled('search') || request()->filled('pt_group') || request()->filled('pic');
@endphp

@if($customers->isEmpty())
    <div class="px-6 py-16 text-center">
        <div class="mx-auto flex max-w-sm flex-col items-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-300">
                <x-icon name="users" class="h-6 w-6" />
            </span>
            @if($hasFilter)
                <p class="mt-4 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('Customer not found') }}</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Try another name, company, or email.') }}</p>
                <a href="{{ route('customers.index') }}" class="mt-4 text-sm font-semibold text-accent-600 hover:text-accent-700 dark:text-accent-400">
                    {{ __('Reset filter') }}
                </a>
            @else
                <p class="mt-4 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('No customers yet') }}</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Newly added customer data will appear here.') }}</p>
                @can('manage-sales')
                    <x-icon-button as="a" variant="solid" icon="add" href="{{ route('customers.create') }}"
                                   label="{{ __('Add Customer') }}" title="{{ __('Add Customer') }}" class="mt-4" />
                @endcan
            @endif
        </div>
    </div>
@else
    {{-- Desktop: tabel --}}
    <div class="hidden overflow-x-auto sm:block">
        <table class="w-full min-w-[720px]">
            <thead class="sticky top-0 z-10 bg-slate-50/95 backdrop-blur dark:bg-slate-800/95">
                <tr>
                    <th scope="col" class="px-5 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 sm:px-6">{{ __('Customer') }}</th>
                    <th scope="col" class="px-5 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 sm:px-6">{{ __('Company') }}</th>
                    <th scope="col" class="px-5 py-3 text-center text-xs font-semibold text-slate-500 dark:text-slate-400 sm:px-6">{{ __('PIC') }}</th>
                    <th scope="col" class="px-5 py-3 text-center text-xs font-semibold text-slate-500 dark:text-slate-400 sm:px-6">{{ __('Projects') }}</th>
                    <th scope="col" class="px-5 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 sm:px-6">{{ __('Actions') }}</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @foreach($customers as $customer)
                    @php
                        $picName = $customer->picName();
                        $picWhatsapp = $customer->picWhatsapp();
                        $picEmail = $customer->picEmail();
                    @endphp
                    <tr class="transition-colors duration-200 hover:bg-slate-50/80 dark:hover:bg-slate-700/25">
                        <td class="px-5 py-4 sm:px-6">
                            <a href="{{ route('customers.show', $customer) }}" class="font-semibold text-slate-800 hover:text-accent-600 dark:text-slate-100 dark:hover:text-accent-400">
                                {{ $customer->name }}
                            </a>
                            <p class="mt-1 max-w-sm truncate text-xs text-slate-500 dark:text-slate-400">
                                {{ $customer->address ?: __('Address not filled') }}
                            </p>
                        </td>

                        <td class="px-5 py-4 sm:px-6">
                            @if($customer->pt_group)
                                <span class="inline-flex rounded-lg px-2 py-0.5 text-[11px] font-semibold {{ \App\Models\Lead::PT_COLORS[$customer->pt_group] ?? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' }}">{{ $customer->pt_group }}</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>

                        <td class="px-5 py-4 text-center sm:px-6">
                            @if($picName)
                                <p class="truncate text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $picName }}</p>
                                <p class="mt-0.5 truncate text-xs text-slate-500 tabular-nums dark:text-slate-400">
                                    {{ $picWhatsapp ? \App\Support\PhoneFormatter::format($picWhatsapp) : ($picEmail ?: '—') }}
                                </p>
                            @else
                                <x-status-badge color="yellow">{{ __('No PIC yet') }}</x-status-badge>
                            @endif
                        </td>

                        <td class="px-5 py-4 text-center sm:px-6">
                            <span class="inline-flex min-h-8 min-w-8 items-center justify-center rounded-full bg-accent-100 px-2.5 text-sm font-bold tabular-nums text-accent-700 dark:bg-accent-500/10 dark:text-accent-300">
                                {{ $customer->projects_count }}
                            </span>
                        </td>

                        <td class="px-5 py-4 sm:px-6">
                            <div class="flex justify-end gap-2">
                                <x-icon-button as="a" icon="view" href="{{ route('customers.show', $customer) }}" title="{{ __('View customer') }}" />

                                @can('update', $customer)
                                    <x-icon-button as="a" icon="edit" href="{{ route('customers.edit', $customer) }}" title="{{ __('Edit customer') }}" />
                                @endcan

                                @can('delete', $customer)
                                    <form action="{{ route('customers.destroy', $customer) }}" method="POST" class="inline-flex" onsubmit="return confirm('{{ __('Delete this customer?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-button icon="delete" type="submit" title="{{ __('Delete customer') }}" />
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Mobile: kartu ringkas --}}
    <div class="divide-y divide-slate-100 sm:hidden dark:divide-slate-700/60">
        @foreach($customers as $customer)
            @php
                $picName = $customer->picName();
            @endphp
            <div class="p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <a href="{{ route('customers.show', $customer) }}" class="block truncate font-semibold text-slate-800 dark:text-slate-100">
                            {{ $customer->name }}
                        </a>
                        <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                            {{ $customer->address ?: __('Address not filled') }}
                        </p>
                    </div>
                    <div class="flex shrink-0 gap-1.5">
                        <x-icon-button as="a" icon="view" href="{{ route('customers.show', $customer) }}" title="{{ __('View customer') }}" />
                        @can('update', $customer)
                            <x-icon-button as="a" icon="edit" href="{{ route('customers.edit', $customer) }}" title="{{ __('Edit customer') }}" />
                        @endcan
                    </div>
                </div>

                <div class="mt-2.5 flex flex-wrap items-center gap-2 text-xs">
                    @if($customer->pt_group)
                        <span class="inline-flex rounded-lg px-2 py-0.5 text-[11px] font-semibold {{ \App\Models\Lead::PT_COLORS[$customer->pt_group] ?? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' }}">{{ $customer->pt_group }}</span>
                    @endif

                    <span class="inline-flex items-center gap-1 text-slate-500 dark:text-slate-400">
                        <x-icon name="users" class="h-3.5 w-3.5" />
                        {{ $picName ?: __('No PIC yet') }}
                    </span>

                    <span class="inline-flex items-center gap-1 font-semibold text-accent-700 tabular-nums dark:text-accent-300">
                        <x-icon name="folder" class="h-3.5 w-3.5" />
                        {{ $customer->projects_count }}
                    </span>
                </div>
            </div>
        @endforeach
    </div>
@endif
