<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Payment Management') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Manage payments from invoices.') }}</p>
        </div>
        <x-icon-button as="a" icon="add" href="{{ route('admin.payments.create') }}" label="{{ __('Record Payment') }}" title="{{ __('Record Payment') }}" />
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Related Invoice') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Customer') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Tgl Bayar') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Amount') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Proof') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                    @forelse($payments as $pm)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition group">
                            <td class="px-4 py-3 font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $pm->invoice->invoice_number }}</td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $pm->invoice->customer->name }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ $pm->payment_date->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-800 dark:text-slate-100">Rp {{ number_format($pm->amount, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($pm->proof_file)
                                    <x-icon-button as="a" icon="download" href="{{ route('admin.payments.proof', $pm) }}" target="_blank" title="{{ __('View') }}" />
                                @else
                                    <span class="text-slate-400 text-xs">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                                    <x-icon-button as="a" icon="view" href="{{ route('admin.payments.show', $pm) }}" title="{{ __('Payment detail') }}" />
                                    @can('manage-admin')
                                        <form action="{{ route('admin.payments.destroy', $pm) }}" method="POST" onsubmit="return confirm('{{ __('Delete this payment?') }}')" class="inline">
                                            @csrf @method('DELETE')
                                            <x-icon-button icon="delete" type="submit" title="{{ __('Delete payment') }}" />
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-slate-400">{{ __('No payments yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($payments->hasPages())<div class="p-4 border-t">{{ $payments->links() }}</div>@endif
    </div>
</x-app-layout>