<x-app-layout>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Suppliers') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Procurement suppliers for supplier purchase orders. Separate from marketing partners.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <x-icon-button as="a" icon="back" href="{{ route('admin.supplier-pos.index') }}" title="{{ __('Back') }}" />
            @can('manage-admin')
                <x-icon-button as="a" icon="add" href="{{ route('admin.suppliers.create') }}" label="{{ __('New Supplier') }}" title="{{ __('New Supplier') }}" />
            @endcan
        </div>
    </div>

    <form method="GET" action="{{ route('admin.suppliers.index') }}" class="mb-4 flex flex-wrap items-end gap-2">
        <div>
            <label for="q" class="block text-xs text-slate-500 mb-1">{{ __('Search') }}</label>
            <input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Name / code / contact') }}"
                   class="w-64 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
        </div>
        <div>
            <label for="status" class="block text-xs text-slate-500 mb-1">{{ __('Status') }}</label>
            <select id="status" name="status" class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                <option value="">{{ __('All') }}</option>
                <option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
                <option value="inactive" @selected(request('status') === 'inactive')>{{ __('Inactive') }}</option>
            </select>
        </div>
        <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
        <x-icon-button as="a" icon="reset" href="{{ route('admin.suppliers.index') }}" title="{{ __('Reset') }}" />
    </form>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Code') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Supplier') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Contact') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                    @forelse($suppliers as $supplier)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition group">
                            <td class="px-4 py-3 font-mono text-sm text-slate-700 dark:text-slate-300">{{ $supplier->code ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-800 dark:text-slate-100">{{ $supplier->name }}</p>
                                @if($supplier->notes)
                                    <p class="text-xs text-slate-500 truncate max-w-xs">{{ $supplier->notes }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                <p>{{ $supplier->contact_person ?? '-' }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $supplier->phone ?? '-' }}@if($supplier->email) • {{ $supplier->email }}@endif
                                </p>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($supplier->is_active)
                                    <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">{{ __('Active') }}</span>
                                @else
                                    <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                                    @can('manage-admin')
                                        <x-icon-button as="a" icon="edit" href="{{ route('admin.suppliers.edit', $supplier) }}" title="{{ __('Edit supplier') }}" />
                                        @if($supplier->is_active)
                                            <form action="{{ route('admin.suppliers.destroy', $supplier) }}" method="POST" class="inline"
                                                  onsubmit="return confirm('{{ __('Deactivate this supplier?') }}')">
                                                @csrf @method('DELETE')
                                                <x-icon-button icon="delete" type="submit" title="{{ __('Deactivate') }}" />
                                            </form>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-slate-400">{{ __('No suppliers yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($suppliers->hasPages())<div class="p-4 border-t">{{ $suppliers->links() }}</div>@endif
    </div>
</x-app-layout>
