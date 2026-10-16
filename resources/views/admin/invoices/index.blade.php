<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Manajemen Invoice') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Kelola invoice untuk customer.') }}</p>
        </div>
        @can('manage-admin')
            <x-icon-button as="a" icon="add" href="{{ route('admin.invoices.create') }}" label="{{ __('Buat Invoice') }}" title="{{ __('Buat Invoice') }}" />
        @endcan
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('No Invoice') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">Customer</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Nominal') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                    @forelse($invoices as $inv)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition group">
                            <td class="px-4 py-3 font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $inv->invoice_number }}</td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $inv->customer->name }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-800 dark:text-slate-100">Rp {{ number_format($inv->amount, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full
                                    @if($inv->status === 'paid') bg-green-100 text-green-700
                                    @elseif($inv->status === 'cancelled') bg-red-100 text-red-700
                                    @else bg-yellow-100 text-yellow-700 @endif">
                                    {{ $inv->status === 'paid' ? __('Lunas') : ($inv->status === 'cancelled' ? __('Dibatalkan') : __('Belum Bayar')) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                                    <x-icon-button as="a" icon="view" href="{{ route('admin.invoices.show', $inv) }}" title="Detail invoice" />
                                    @can('manage-admin')
                                        <x-icon-button as="a" icon="edit" href="{{ route('admin.invoices.edit', $inv) }}" title="Edit invoice" />
                                        <form action="{{ route('admin.invoices.destroy', $inv) }}" method="POST" onsubmit="return confirm('{{ __('Hapus invoice ini?') }}')" class="inline">
                                            @csrf @method('DELETE')
                                            <x-icon-button icon="delete" type="submit" title="{{ __('Hapus invoice') }}" />
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-slate-400">{{ __('Belum ada invoice.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($invoices->hasPages())<div class="p-4 border-t">{{ $invoices->links() }}</div>@endif
    </div>
</x-app-layout>