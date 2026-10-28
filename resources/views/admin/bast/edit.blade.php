<x-app-layout>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('BAST') }} · {{ $project->project_name }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Check the installation, create the BAST from a company template, then upload the signed document.') }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                @if($project->bast_ready_at)
                    <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                        {{ __('Ready for Finance') }} · {{ $project->bast_ready_at->format('d M Y') }}
                    </span>
                @else
                    <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                        {{ __('Waiting for signed BAST') }}
                    </span>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-2">
            @include('teknisi.instalasis._bast-dropdown')
            <x-icon-button as="a" icon="back" href="{{ route('admin.bast.index') }}" title="{{ __('Back') }}" />
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 text-green-700 px-4 py-3 text-sm dark:bg-green-900/30 dark:border-green-800 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm dark:bg-red-900/30 dark:border-red-800 dark:text-red-200">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-5">
            <form action="{{ route('admin.bast.update', $project) }}" method="POST" enctype="multipart/form-data"
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

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Upload Signed BAST') }}</label>
                    @if($project->bastDocument)
                        <div class="mb-2 flex items-center gap-2">
                            <x-icon-button as="a" icon="view" href="{{ route('admin.bast.document', $project) }}" target="_blank" rel="noopener" title="{{ __('View BAST') }}" />
                            <span class="truncate text-sm text-slate-600 dark:text-slate-300">{{ $project->bastDocument->file_name }}</span>
                        </div>
                    @endif
                    <input type="file" name="bast_file" accept=".pdf,.doc,.docx"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-accent-50 file:text-accent-700 file:text-sm file:font-medium">
                    <p class="mt-2 text-xs text-slate-400">{{ __('PDF or DOC, max 10 MB. Upload the BAST that has been signed by 3DY and the customer.') }}</p>
                    @error('bast_file') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="rounded-xl border border-slate-200 dark:border-slate-600 p-4 space-y-4">
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('Signatures') }}</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('Signed by 3DY') }}</label>
                            <input type="text" name="bast_signed_3dy_name" value="{{ old('bast_signed_3dy_name', $project->bast_signed_3dy_name) }}" maxlength="150"
                                   placeholder="{{ __('Name') }}"
                                   class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                            <x-datepicker name="bast_signed_3dy_at" value="{{ old('bast_signed_3dy_at', $project->bast_signed_3dy_at?->format('Y-m-d')) }}" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('Signed by Customer') }}</label>
                            <input type="text" name="bast_signed_customer_name" value="{{ old('bast_signed_customer_name', $project->bast_signed_customer_name) }}" maxlength="150"
                                   placeholder="{{ __('Name') }}"
                                   class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                            <x-datepicker name="bast_signed_customer_at" value="{{ old('bast_signed_customer_at', $project->bast_signed_customer_at?->format('Y-m-d')) }}" />
                        </div>
                    </div>
                    <p class="text-xs text-slate-400">{{ __('BAST is marked ready for Finance after the document is uploaded and both signature dates are filled.') }}</p>
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
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-semibold text-slate-800 dark:text-slate-100">{{ __('Installation Completeness') }}</h2>
                    @if($project->instalasis->isNotEmpty() && $project->instalasis->every(fn ($i) => $i->isTechnicallyComplete()))
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">{{ __('All complete') }}</span>
                    @endif
                </div>
                @php
                    $installationBadges = [
                        'scheduled' => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
                        'on_progress' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                        'waiting' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
                        'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
                        'cancelled' => 'bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-300',
                    ];
                @endphp
                @forelse($project->instalasis as $instalasi)
                    <div class="rounded-lg border border-slate-200 dark:border-slate-600 p-3 space-y-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium text-slate-800 dark:text-slate-100">{{ $instalasi->location ?: __('Installation') }} #{{ $instalasi->id }}</span>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $installationBadges[$instalasi->status] ?? 'bg-slate-200 text-slate-700' }}">
                                {{ __(ucfirst(str_replace('_', ' ', $instalasi->status))) }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500">
                            {{ $instalasi->technician_pic ?: '-' }}
                            @if($instalasi->completed_at)
                                · {{ __('Completed at') }} {{ $instalasi->completed_at->format('d M Y H:i') }}
                            @endif
                        </p>
                        @if($instalasi->installation_report)
                            <p class="text-xs text-slate-600 dark:text-slate-300 line-clamp-3">{{ $instalasi->installation_report }}</p>
                        @endif
                        <div class="flex items-center gap-1.5">
                            @if($instalasi->hasReport())
                                <x-icon-button as="a" icon="view" href="{{ route('teknisi.instalasis.report.view', $instalasi) }}" target="_blank" rel="noopener" title="{{ __('View report') }}" />
                                <x-icon-button as="a" icon="download" href="{{ route('teknisi.instalasis.report.download', $instalasi) }}" title="{{ __('Download report') }}" />
                            @endif
                            <x-icon-button as="a" icon="view" href="{{ route('teknisi.instalasis.show', $instalasi) }}" title="{{ __('Installation detail') }}" />
                        </div>
                    </div>
                @empty
                    <p class="text-slate-400">{{ __('No installation record yet.') }}</p>
                @endforelse
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 text-sm space-y-3">
                <h2 class="font-semibold text-slate-800 dark:text-slate-100">{{ __('Closing Checklist') }}</h2>
                @php
                    $deliveryDone = $project->goodsDeliveries->isNotEmpty() && $project->goodsDeliveries->every(fn ($d) => $d->status === 'delivered');
                    $invoiceActive = $project->invoices->where('status', '!=', 'cancelled');
                    $invoiceDone = $invoiceActive->isNotEmpty() && $invoiceActive->every(fn ($i) => $i->status === 'paid');
                    $installationDone = ! $project->requires_installation
                        || (filled($project->bast_number) && filled($project->bast_ready_at)
                            && ((int) $project->progress >= 100 || strcasecmp((string) $project->status?->name, 'Done') === 0));
                @endphp
                <div class="flex items-center justify-between">
                    <span>{{ __('Goods delivered') }}</span>
                    <span class="{{ $deliveryDone ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">{{ $deliveryDone ? __('Yes') : __('No') }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>{{ __('BAST ready (document + signatures)') }}</span>
                    <span class="{{ $project->bast_ready_at ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">{{ $project->bast_ready_at ? __('Yes') : __('No') }}</span>
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
