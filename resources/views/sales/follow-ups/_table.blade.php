{{-- Partial tabel: dipakai full view + refresh AJAX filter (tanpa reload). --}}
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
            <thead class="bg-slate-50 dark:bg-slate-700">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">Customer</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Deskripsi') }}</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Tanggal Follow Up') }}</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Lokasi') }}</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Terkait Lead') }}</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Oleh') }}</th>
                    <th class="px-6 py-4 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Aksi') }}</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-600">
                @forelse($followUps as $fu)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition {{ $fu->completed_at ? 'opacity-60' : '' }}">
                        <td class="px-6 py-4">
                            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $fu->customer?->name ?? '-' }}</span>
                            @if($fu->type)
                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-[11px] font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">{{ \App\Models\FollowUp::typeLabel($fu->type) }}</span>
                            @endif
                            @if($fu->completed_at)
                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-[11px] font-semibold bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">{{ __('Done') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-300 max-w-xs truncate">
                            {{ Str::limit($fu->description, 100) }}
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                            {{ $fu->follow_up_date ? $fu->follow_up_date->format('d M Y') : '-' }}
                            @if($fu->activity_time)
                                <span class="block mt-0.5 text-[11px] text-slate-500">{{ substr((string) $fu->activity_time, 0, 5) }}</span>
                            @endif
                            @if(!$fu->completed_at && $fu->follow_up_date && $fu->follow_up_date->isBefore(today()))
                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-[11px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">{{ __('Terlambat') }}</span>
                            @endif
                            @if($fu->next_follow_up_date)
                                <span class="block mt-1 text-[11px] text-slate-500">{{ __('Berikutnya:') }} {{ $fu->next_follow_up_date->format('d M Y') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                            {{ $fu->location ?: '-' }}
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                            @if($fu->lead)
                                <span class="text-slate-800 dark:text-slate-100">#{{ $fu->lead->id }}</span>
                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-[11px] font-semibold {{ \App\Models\Lead::statusBadgeClasses($fu->lead->status) }}">{{ ucfirst($fu->lead->status) }}</span>
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $fu->creator?->name ?? '-' }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-2">
                                @php
                                    $fuWaMessage = 'Halo ' . ($fu->customer?->contact_person ?: ($fu->customer?->name ?? ''))
                                        . ', izin follow up "' . Str::limit($fu->description, 60) . '".';
                                    $fuWaLink = $fu->customer?->waLink($fuWaMessage);
                                    $fuMailLink = $fu->customer?->email
                                        ? 'mailto:'.$fu->customer->email.'?subject='.rawurlencode(__('Follow up: :name', ['name' => $fu->customer?->name ?? ''])).'&body='.rawurlencode(Str::limit($fu->description, 200))
                                        : null;
                                @endphp
                                @if($fuWaLink)
                                    <a href="{{ $fuWaLink }}" target="_blank"
                                       title="{{ __('Follow Up via WA') }}"
                                       aria-label="{{ __('Follow up via WhatsApp') }} {{ $fu->customer?->name }}"
                                       class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-green-100 text-green-700 transition hover:bg-green-200 dark:bg-green-500/10 dark:text-green-300 dark:hover:bg-green-500/20">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="h-4 w-4" aria-hidden="true">
                                            <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.87 9.87 0 0 0 4.74 1.21c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2m5.83 14.12c-.25.7-1.45 1.33-2.02 1.42-.52.08-1.17.12-1.89-.12-.44-.15-1-.39-1.71-.75-3.03-1.39-5-4.63-5.15-4.84-.15-.21-1.23-1.64-1.23-3.13 0-1.49.78-2.22 1.06-2.52.28-.3.61-.38.81-.38l.58.01c.19.01.44-.07.69.53.25.61.86 2.11.94 2.26.08.15.13.33.03.53-.1.2-.15.33-.3.51l-.45.53c-.15.15-.31.31-.13.61.18.3.8 1.32 1.71 2.14 1.18 1.06 2.17 1.39 2.48 1.55.3.15.48.13.66-.08l1.1-1.28c.2-.26.42-.22.71-.13.3.09 1.9.9 2.23 1.06.38.2.53.44.5.65-.27.69-.52.98-.73 1.23"/>
                                        </svg>
                                    </a>
                                @endif
                                @if($fuMailLink)
                                    <a href="{{ $fuMailLink }}"
                                       title="{{ __('Send Email') }}"
                                       aria-label="{{ __('Send Email') }} {{ $fu->customer?->name }}"
                                       class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-sky-100 text-sky-700 transition hover:bg-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:hover:bg-sky-500/20">
                                        <x-icon name="mail" class="h-4 w-4" />
                                    </a>
                                @endif
                                <x-dropdown align="right" width="w-52">
                                    <x-slot name="trigger">
                                        <button type="button"
                                                title="{{ __('Aksi') }}"
                                                aria-label="{{ __('Aksi') }} {{ $fu->customer?->name }}"
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-700 transition hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">
                                            <x-icon name="chevron-down" class="h-4 w-4" />
                                        </button>
                                    </x-slot>
                                    <x-slot name="content">
                                        <a href="{{ route('sales.follow-ups.show', $fu) }}"
                                           class="flex w-full items-center gap-3 px-4 py-1.5 text-start text-sm text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700">
                                            <x-icon name="eye" class="h-4 w-4 shrink-0 text-indigo-600 dark:text-indigo-300" />
                                            {{ __('Lihat detail follow up') }}
                                        </a>
                                        @can('manage-sales')
                                            <a href="{{ route('sales.follow-ups.edit', $fu) }}"
                                               class="flex w-full items-center gap-3 px-4 py-1.5 text-start text-sm text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700">
                                                <x-icon name="edit" class="h-4 w-4 shrink-0 text-blue-600 dark:text-blue-300" />
                                                {{ __('Edit follow up') }}
                                            </a>
                                        @endcan
                                        <div class="my-1 border-t border-slate-200 dark:border-slate-600"></div>
                                        @can('manage-sales')
                                            @if($fu->completed_at)
                                                <form action="{{ route('sales.follow-ups.reopen', $fu) }}"
                                                      method="POST" data-ajax>
                                                    @csrf
                                                    <button type="submit"
                                                            class="flex w-full items-center gap-3 px-4 py-1.5 text-start text-sm text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700">
                                                        <x-icon name="restore" class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-300" />
                                                        {{ __('Reopen follow up') }}
                                                    </button>
                                                </form>
                                            @else
                                                <form action="{{ route('sales.follow-ups.complete', $fu) }}"
                                                      method="POST" data-ajax>
                                                    @csrf
                                                    <button type="submit"
                                                            class="flex w-full items-center gap-3 px-4 py-1.5 text-start text-sm text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700">
                                                        <x-icon name="check-circle" class="h-4 w-4 shrink-0 text-green-600 dark:text-green-300" />
                                                        {{ __('Mark as done') }}
                                                    </button>
                                                </form>
                                                <div class="my-1 border-t border-slate-200 dark:border-slate-600"></div>
                                                <div @click.stop x-data="{ openResched: false }">
                                                    <button type="button" @click="openResched = !openResched"
                                                            class="flex w-full items-center gap-3 px-4 py-1.5 text-start text-sm text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0 text-yellow-600 dark:text-yellow-300" aria-hidden="true"><circle cx="12" cy="12" r="8.5" /><path stroke-linecap="round" d="M12 7.5V12l3 2" /></svg>
                                                        <span class="flex-1">{{ __('Reschedule') }}</span>
                                                        <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200" ::class="openResched && 'rotate-90'" />
                                                    </button>
                                                    <div x-show="openResched" class="space-y-2 px-4 pb-2 pt-1">
                                                        <form action="{{ route('sales.follow-ups.snooze', $fu) }}" method="POST" class="flex items-center gap-2">
                                                            @csrf
                                                            <span class="text-sm text-slate-500 dark:text-slate-400">+</span>
                                                            <input type="number" name="days" min="1" max="60" value="3" required
                                                                   aria-label="{{ __('Reschedule') }}"
                                                                   class="h-8 w-16 rounded-lg border-slate-300 bg-white text-center text-sm text-slate-800 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                                                            <span class="text-xs text-slate-500 dark:text-slate-400">days</span>
                                                            <button type="submit"
                                                                    class="inline-flex h-8 items-center rounded-lg bg-accent-600 px-3 text-xs font-semibold text-white transition hover:bg-accent-500">OK</button>
                                                        </form>
                                                        <form action="{{ route('sales.follow-ups.snooze', $fu) }}" method="POST" class="flex items-center gap-2">
                                                            @csrf
                                                            <input type="date" name="date" min="{{ date('Y-m-d') }}" required
                                                                   aria-label="{{ __('Reschedule') }}"
                                                                   class="h-8 min-w-0 flex-1 rounded-lg border-slate-300 bg-white text-sm text-slate-800 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                                                            <button type="submit"
                                                                    class="inline-flex h-8 shrink-0 items-center rounded-lg bg-accent-600 px-3 text-xs font-semibold text-white transition hover:bg-accent-500">OK</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="my-1 border-t border-slate-200 dark:border-slate-600"></div>
                                            <form action="{{ route('sales.follow-ups.destroy', $fu) }}"
                                                  method="POST" data-ajax data-ajax-remove="tr" onsubmit="return confirm('{{ __('Hapus follow up ini?') }}')">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                        class="flex w-full items-center gap-3 px-4 py-1.5 text-start text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">
                                                    <x-icon name="trash" class="h-4 w-4 shrink-0" />
                                                    {{ __('Hapus follow up') }}
                                                </button>
                                            </form>
                                        @endcan
                                    </x-slot>
                                </x-dropdown>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-300">
                                <x-icon name="chat" class="h-6 w-6" />
                            </span>
                            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Belum ada follow up.') }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ __('Catat follow up pertama dari tombol di atas.') }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($followUps->hasPages())
        <div class="p-4 border-t border-slate-200 dark:border-slate-600">
            {{ $followUps->links() }}
        </div>
    @endif
