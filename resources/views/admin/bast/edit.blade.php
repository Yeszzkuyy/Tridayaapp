<x-app-layout>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('BAST') }} · {{ $project->project_name }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Fill BAST number/date and link the BAST document uploaded by the technician.') }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('admin.bast.index') }}" title="{{ __('Back') }}" />
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 text-green-700 px-4 py-3 text-sm dark:bg-green-900/30 dark:border-green-800 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm dark:bg-red-900/30 dark:border-red-800 dark:text-red-200">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-5">
            <form action="{{ route('admin.bast.update', $project) }}" method="POST"
                  class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-5">
                @csrf
                @method('PUT')

                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-slate-800 dark:text-slate-100">{{ __('BAST Details') }}</h2>
                    @if($project->commercial_closed_at)
                        <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                            {{ __('Closed') }} · {{ $project->commercial_closed_at->format('d M Y H:i') }}
                        </span>
                    @else
                        <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200">{{ __('Open') }}</span>
                    @endif
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('BAST Number') }}</label>
                        <input type="text" name="bast_number" value="{{ old('bast_number', $project->bast_number) }}" maxlength="100"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('BAST Date') }}</label>
                        <x-datepicker name="bast_date" value="{{ old('bast_date', $project->bast_date?->format('Y-m-d')) }}" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('BAST Document') }}</label>
                        <select name="bast_document_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                            <option value="">{{ __('-- No linked document --') }}</option>
                            @foreach($project->documents as $document)
                                <option value="{{ $document->id }}" @selected((int) old('bast_document_id', $project->bast_document_id) === $document->id)>
                                    {{ $document->file_name }} · {{ $document->document_type }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-400">{{ __('Documents uploaded by the technician for this project.') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Delivery Deadline') }}</label>
                        <x-datepicker name="delivery_deadline" value="{{ old('delivery_deadline', $project->delivery_deadline?->format('Y-m-d')) }}" />
                    </div>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="checkbox" name="requires_installation" value="1" @checked(old('requires_installation', $project->requires_installation))
                           class="rounded border-slate-300 text-accent-600 focus:ring-accent-500">
                    <span>{{ __('This project requires installation / BAST before closing') }}</span>
                </label>

                <div class="flex gap-3">
                    <button type="submit"
                            class="group relative overflow-hidden rounded-xl bg-accent-600 hover:bg-accent-700 text-white px-6 py-2.5 text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Save') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                    <a href="{{ route('admin.bast.index') }}"
                       class="group relative overflow-hidden rounded-xl bg-accent-500 hover:bg-accent-600 text-white px-6 py-2.5 text-sm font-medium text-center transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Cancel') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </a>
                </div>
            </form>

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
                <h2 class="font-semibold text-slate-800 dark:text-slate-100 mb-4">{{ __('Customer Invoices') }}</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Invoice') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Type') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Amount') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Paid') }}</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @forelse($project->invoices->where('status', '!=', 'cancelled') as $invoice)
                                <tr>
                                    <td class="px-3 py-3">
                                        <a href="{{ route('admin.invoices.show', $invoice) }}" class="font-mono text-sm text-accent-600 dark:text-accent-400 hover:underline">{{ $invoice->invoice_number }}</a>
                                    </td>
                                    <td class="px-3 py-3 text-sm text-slate-700 dark:text-slate-300">{{ \App\Models\Invoice::typeLabel($invoice->invoice_type) }}</td>
                                    <td class="px-3 py-3 text-right font-mono text-sm text-slate-800 dark:text-slate-100">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-right font-mono text-sm text-slate-600 dark:text-slate-400">Rp {{ number_format($invoice->paidAmount(), 0, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-center">
                                        <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full {{ \App\Models\Invoice::statusBadgeClasses($invoice->status) }}">
                                            {{ \App\Models\Invoice::statusLabel($invoice->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-5 text-center text-slate-400">{{ __('No customer invoice yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @can('manage-admin')
                    <div class="mt-3">
                        <x-icon-button as="a" icon="add" href="{{ route('admin.invoices.create', ['project_id' => $project->id]) }}" label="{{ __('Create Invoice') }}" title="{{ __('Create customer invoice from this project') }}" />
                    </div>
                @endcan
            </div>
        </div>

        <div class="space-y-5">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 text-sm space-y-3">
                <h2 class="font-semibold text-slate-800 dark:text-slate-100">{{ __('Closing Checklist') }}</h2>
                @php
                    $deliveryDone = $project->goodsDeliveries->isNotEmpty() && $project->goodsDeliveries->every(fn ($d) => $d->status === 'delivered');
                    $invoiceActive = $project->invoices->where('status', '!=', 'cancelled');
                    $invoiceDone = $invoiceActive->isNotEmpty() && $invoiceActive->every(fn ($i) => $i->status === 'paid');
                    $installationDone = ! $project->requires_installation || ($project->bast_number && ((int) $project->progress >= 100 || strcasecmp((string) $project->status?->name, 'Done') === 0));
                @endphp
                <div class="flex items-center justify-between">
                    <span>{{ __('Goods delivered') }}</span>
                    <span class="{{ $deliveryDone ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">{{ $deliveryDone ? __('Yes') : __('No') }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>{{ __('Installation / BAST') }}</span>
                    <span class="{{ $installationDone ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">{{ $installationDone ? __('Yes') : __('No') }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>{{ __('Invoices paid') }}</span>
                    <span class="{{ $invoiceDone ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">{{ $invoiceDone ? __('Yes') : __('No') }}</span>
                </div>
                <p class="text-xs text-slate-400 pt-2 border-t border-slate-200 dark:border-slate-700">{{ __('Closing is set automatically when all checklist items are complete.') }}</p>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 text-sm space-y-3">
                <h2 class="font-semibold text-slate-800 dark:text-slate-100">{{ __('Delivery Notes') }}</h2>
                @forelse($project->goodsDeliveries as $delivery)
                    <div class="flex items-center justify-between gap-2">
                        <a href="{{ route('admin.goods-deliveries.show', $delivery) }}" class="font-mono text-xs text-accent-600 dark:text-accent-400 hover:underline">{{ $delivery->delivery_number }}</a>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ \App\Models\GoodsDelivery::statusBadgeClasses($delivery->status) }}">{{ \App\Models\GoodsDelivery::statusLabel($delivery->status) }}</span>
                    </div>
                @empty
                    <p class="text-slate-400">{{ __('No delivery notes yet.') }}</p>
                @endforelse
                @can('manage-admin')
                    <x-icon-button as="a" icon="add" href="{{ route('admin.goods-deliveries.create', ['project_id' => $project->id]) }}" label="{{ __('Create Delivery') }}" title="{{ __('Create delivery note for this project') }}" />
                @endcan
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 text-sm space-y-3">
                <h2 class="font-semibold text-slate-800 dark:text-slate-100">{{ __('Project Documents') }}</h2>
                @forelse($project->documents as $document)
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate text-slate-700 dark:text-slate-300" title="{{ $document->file_name }}">{{ $document->file_name }}</span>
                        <x-icon-button as="a" icon="download" href="{{ route('project-documents.download', $document) }}" title="{{ __('Download') }}" />
                    </div>
                @empty
                    <p class="text-slate-400">{{ __('No documents uploaded yet.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
