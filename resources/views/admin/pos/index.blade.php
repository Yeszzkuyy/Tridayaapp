<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Purchase Order Management') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Manage purchase orders for customers.') }}</p>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('PO No.') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Customer') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Item') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                    @forelse($pos as $po)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition group">
                            <td class="px-4 py-3 font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $po->po_number }}</td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $po->customer->name }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400 max-w-xs truncate">{{ Str::limit($po->items, 60) }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full
                                    @if($po->status === 'selesai') bg-green-100 text-green-700
                                    @elseif($po->status === 'dibatalkan') bg-red-100 text-red-700
                                    @elseif($po->status === 'diproses') bg-yellow-100 text-yellow-700
                                    @else bg-slate-100 text-slate-600 @endif">
                                    {{ $po->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                                    <x-icon-button as="a" icon="view" href="{{ route('admin.pos.show', $po) }}" title="{{ __('PO Detail') }}" />
                                    @can('manage-admin')
                                        <x-icon-button as="a" icon="edit" href="{{ route('admin.pos.edit', $po) }}" title="{{ __('Edit PO') }}" />
                                        <form action="{{ route('admin.pos.destroy', $po) }}" method="POST" onsubmit="return confirm('{{ __('Delete this PO?') }}')" class="inline">
                                            @csrf @method('DELETE')
                                            <x-icon-button icon="delete" type="submit" title="{{ __('Delete PO') }}" />
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-slate-400">{{ __('No POs yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($pos->hasPages())<div class="p-4 border-t">{{ $pos->links() }}</div>@endif
    </div>
</x-app-layout>