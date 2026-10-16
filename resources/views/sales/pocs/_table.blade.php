{{-- Partial tabel: dipakai full view + refresh AJAX filter (tanpa reload). --}}
<div id="pocs-table" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
            <thead class="bg-slate-50 dark:bg-slate-700">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Customer') }}</th>
                    <th class="px-6 py-4 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Tipe') }}</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Jadwal') }}</th>
                    <th class="px-6 py-4 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Status') }}</th>
                    <th class="px-6 py-4 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Aksi') }}</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-600">
                @forelse($pocs as $poc)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                        <td class="px-6 py-4">
                            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $poc->customer?->name ?? '-' }}</span>
                            @if($poc->location)
                                <span class="block mt-0.5 text-xs text-slate-500 truncate max-w-xs">{{ $poc->location }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300">
                                {{ \App\Models\Poc::typeLabel($poc->type) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                            {{ $poc->scheduled_date ? $poc->scheduled_date->format('d M Y') : '-' }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap
                                @switch($poc->status)
                                    @case('done') bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300 @break
                                    @case('cancelled') bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 @break
                                    @default bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300
                                @endswitch
                            ">
                                {{ \App\Models\Poc::statusLabel($poc->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex justify-end gap-2">
                                <x-icon-button as="a" icon="view" href="{{ route('sales.pocs.show', $poc) }}" title="{{ __('View details') }}" />
                                @can('manage-sales')
                                    <x-icon-button as="a" icon="edit" href="{{ route('sales.pocs.edit', $poc) }}" title="{{ __('Edit POC/Demo') }}" />
                                    <form action="{{ route('sales.pocs.destroy', $poc) }}"
                                          method="POST" class="inline-flex" data-ajax data-ajax-remove="tr" onsubmit="return confirm('{{ __('Hapus POC/Demo ini?') }}')">
                                        @csrf @method('DELETE')
                                        <x-icon-button icon="delete" type="submit" title="{{ __('Delete POC/Demo') }}" />
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-300">
                                <x-icon name="file-text" class="h-6 w-6" />
                            </span>
                            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Belum ada POC/Demo.') }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ __('Jadwalkan POC/Demo pertama dari tombol di atas.') }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($pocs->hasPages())
        <div class="p-4 border-t border-slate-200 dark:border-slate-600">
            {{ $pocs->links() }}
        </div>
    @endif
</div>
