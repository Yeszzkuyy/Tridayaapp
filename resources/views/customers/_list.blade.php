<div class="overflow-x-auto">
    <table class="min-w-[760px] w-full">
        <thead class="bg-slate-50/80 dark:bg-slate-700/40">
            <tr>
                <th scope="col" class="px-5 py-4 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-300 sm:px-6">Customer</th>
                <th scope="col" class="px-5 py-4 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-300 sm:px-6">Company</th>
                <th scope="col" class="px-5 py-4 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-300 sm:px-6">PIC</th>
                <th scope="col" class="px-5 py-4 text-center text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-300 sm:px-6">Project</th>
                <th scope="col" class="px-5 py-4 text-right text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-300 sm:px-6">{{ __('Aksi') }}</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-slate-200/70 dark:divide-slate-700/50">
            @forelse($customers as $customer)
                @php
                    $primaryContact = $customer->contacts->first();
                    $picName = $primaryContact?->name ?: $customer->contact_person;
                    $picWhatsapp = $primaryContact?->whatsapp;
                    $picEmail = $primaryContact?->email;
                @endphp
                <tr class="group transition-colors duration-200 hover:bg-slate-50 dark:hover:bg-white/5">
                    <td class="px-5 py-4 sm:px-6">
                        <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $customer->name }}</p>
                        <p class="mt-1 max-w-sm truncate text-sm text-slate-500 dark:text-slate-400">
                            {{ $customer->address ?: __('Alamat belum diisi') }}
                        </p>
                        @if($customer->whatsapp)
                            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ __('WA perusahaan:') }} {{ \App\Support\PhoneFormatter::format($customer->whatsapp) }}</p>
                        @endif
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        @if($customer->pt_group)
                            <span class="inline-flex px-1.5 py-0.5 rounded {{ \App\Models\Lead::PT_COLORS[$customer->pt_group] ?? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' }} text-[11px] font-semibold">{{ $customer->pt_group }}</span>
                        @else
                            <span class="text-slate-400">-</span>
                        @endif
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        @if($picName)
                            <p class="font-semibold text-slate-700 dark:text-slate-200">{{ $picName }}</p>
                            @if($picWhatsapp)
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('WA PIC:') }} {{ \App\Support\PhoneFormatter::format($picWhatsapp) }}</p>
                            @endif
                            @if($picEmail)
                                <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">{{ $picEmail }}</p>
                            @endif
                        @else
                            <x-status-badge color="yellow">{{ __('Belum ada PIC') }}</x-status-badge>
                        @endif
                    </td>

                    <td class="px-5 py-4 text-center sm:px-6">
                        @if($customer->projects_count > 0)
                            <span class="inline-flex min-h-8 min-w-8 items-center justify-center rounded-full bg-accent-100 px-2.5 text-sm font-bold tabular-nums text-accent-700 dark:bg-accent-500/10 dark:text-accent-300">
                                {{ $customer->projects_count }}
                            </span>
                        @else
                            <span class="text-sm font-semibold tabular-nums text-slate-400 dark:text-slate-500">0</span>
                        @endif
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex justify-end gap-2">
                            <x-icon-button as="a" icon="view" href="{{ route('customers.show', $customer) }}" title="{{ __('Lihat customer') }}" />

                            @can('update', $customer)
                                <x-icon-button as="a" icon="edit" href="{{ route('customers.edit', $customer) }}" title="{{ __('Edit customer') }}" />
                            @endcan

                            @can('delete', $customer)
                                <form action="{{ route('customers.destroy', $customer) }}" method="POST" class="inline-flex" onsubmit="return confirm('{{ __('Hapus customer ini?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <x-icon-button icon="delete" type="submit" title="{{ __('Hapus customer') }}" />
                                </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-6 py-16 text-center">
                        <div class="mx-auto flex max-w-sm flex-col items-center">
                            <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-300">
                                <x-icon name="users" class="h-6 w-6" />
                            </span>
                            @if(request()->filled('search') || request()->filled('pt_group'))
                                <p class="mt-4 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('Customer tidak ditemukan') }}</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Coba gunakan nama, perusahaan, atau email lain.') }}</p>
                            @else
                                <p class="mt-4 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('Belum ada customer') }}</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Data customer yang baru ditambahkan akan muncul di sini.') }}</p>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
