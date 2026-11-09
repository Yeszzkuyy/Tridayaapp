<x-app-layout>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ $proposal->proposal_number }}</h1>
            <p class="text-slate-500 mt-1">
                {{ $proposal->customer }} •
                <x-handoff-status :proposal="$proposal" class="inline-flex align-middle" />
            </p>
        </div>
        <div class="flex items-center gap-2">
            <x-icon-button as="a" icon="back" href="{{ route('admin.purchasing.index') }}" title="{{ __('Back') }}" />
            <x-file-preview :url="route('sales.proposals.download-po', $proposal)"
                            :name="basename((string) $proposal->po_document_path)" />
            <x-icon-button as="a" icon="download" href="{{ route('sales.proposals.download-po', $proposal) }}" label="{{ __('Download PO') }}" title="{{ __('Download PO') }}" />
            @if($proposal->project)
                <x-icon-button as="a" icon="project" href="{{ route('projects.show', $proposal->project) }}" label="{{ __('View Project') }}" title="{{ __('View Project') }}" />
            @elseif($proposal->isPoVerified() && ! $proposal->isPoReturned())
                @canany(['manage-admin', 'manage-sales-leads'])
                    <x-icon-button icon="add" label="{{ __('Convert to Project') }}" title="{{ __('Convert to Project') }}"
                                   onclick="document.getElementById('convert-modal').classList.remove('hidden')" />
                @endcanany
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-4">
            <h2 class="font-semibold text-slate-700 dark:text-slate-200">{{ __('Quotation Items') }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-500 border-b border-slate-200 dark:border-slate-700">
                            <th class="py-2 pr-2">{{ __('Description') }}</th>
                            <th class="py-2 pr-2 text-right">{{ __('Qty') }}</th>
                            <th class="py-2 pr-2">{{ __('Unit') }}</th>
                            <th class="py-2 pr-2 text-right">{{ __('Price') }}</th>
                            <th class="py-2 pr-2 text-right">{{ __('Discount') }}</th>
                            <th class="py-2 text-right">{{ __('Subtotal') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($proposal->items as $item)
                            <tr class="border-b border-slate-100 dark:border-slate-700/60">
                                <td class="py-2 pr-2 text-slate-800 dark:text-slate-100">{{ $item->description }}</td>
                                <td class="py-2 pr-2 text-right text-slate-600 dark:text-slate-300">{{ $item->quantity }}</td>
                                <td class="py-2 pr-2 text-slate-600 dark:text-slate-300">{{ $item->unit }}</td>
                                <td class="py-2 pr-2 text-right text-slate-600 dark:text-slate-300">{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                <td class="py-2 pr-2 text-right text-slate-600 dark:text-slate-300">{{ number_format($item->discount, 0, ',', '.') }}</td>
                                <td class="py-2 text-right font-medium text-slate-800 dark:text-slate-100">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end">
                <div class="w-full md:w-64 text-sm space-y-1">
                    <p class="flex justify-between text-slate-500"><span>{{ __('Subtotal') }}</span><span>{{ number_format($proposal->subtotal, 0, ',', '.') }}</span></p>
                    <p class="flex justify-between text-slate-500"><span>{{ __('Discount') }}</span><span>{{ number_format($proposal->discount, 0, ',', '.') }}</span></p>
                    <p class="flex justify-between text-slate-500"><span>{{ __('Tax') }}</span><span>{{ number_format($proposal->tax, 0, ',', '.') }}</span></p>
                    <p class="flex justify-between font-bold text-slate-800 dark:text-slate-100"><span>{{ __('Grand Total') }}</span><span>Rp {{ number_format($proposal->grand_total, 0, ',', '.') }}</span></p>
                </div>
            </div>

            @if($proposal->notes)
                <p class="text-sm text-slate-700 dark:text-slate-200 whitespace-pre-wrap">{{ $proposal->notes }}</p>
            @endif

            @if($proposal->attachments->isNotEmpty())
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 space-y-2 mt-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Original Quotation Files') }}</p>
                    <ul class="space-y-2">
                        @foreach($proposal->attachments as $file)
                            <li class="flex flex-wrap items-center justify-between gap-2 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-700/50">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-700 dark:text-slate-200">{{ $file->original_name }}</p>
                                    <p class="text-xs text-slate-400">
                                        {{ $file->humanSize() }} · {{ $file->uploader?->name ?? __('System') }} · {{ $file->created_at?->translatedFormat('d M Y H:i') }}
                                    </p>
                                </div>
                                <x-file-preview :url="route('sales.proposals.attachments.preview', [$proposal, $file])"
                                                :download="route('sales.proposals.attachments.download', [$proposal, $file])"
                                                :name="$file->original_name" />
                                <x-icon-button as="a" icon="download" href="{{ route('sales.proposals.attachments.download', [$proposal, $file]) }}" label="{{ __('Download Quotation') }}" title="{{ __('Download Quotation') }}" />
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 text-sm space-y-1">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <h2 class="font-semibold text-slate-700 dark:text-slate-200">{{ __('Customer PO') }}</h2>
                    @if($proposal->isPoVerified())
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                            ✓ {{ __('Verified') }}
                        </span>
                    @elseif($proposal->isPoReturned())
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
                            ✕ {{ __('Returned to Sales') }}
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300">
                            {{ __('Waiting Verification') }}
                        </span>
                    @endif
                </div>
                <p class="text-slate-500">{{ __('PO Number') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->po_number ?: __('Not set yet') }}</span></p>
                <p class="text-slate-500">{{ __('PO Date') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->po_date?->format('d M Y') ?? __('Not set yet') }}</span></p>
                <p class="text-slate-500">{{ __('Delivery Deadline') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->delivery_deadline?->format('d M Y') ?? __('Not set yet') }}</span></p>
                <p class="text-slate-500">{{ __('Delivery Address') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->delivery_address ?: __('Not set yet') }}</span></p>
                <p class="text-slate-500">{{ __('Payment Terms') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->paymentTermsSummary() }}</span></p>
                @if($proposal->delivery_terms)
                    <p class="text-slate-500">{{ __('Delivery Terms') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->delivery_terms }}</span></p>
                @endif
                <p class="text-slate-500">{{ __('PO Status') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->purchasingStatusLabel() }}</span></p>

                @if($proposal->isPoVerified())
                    <p class="text-xs text-emerald-600 dark:text-emerald-400 pt-1">
                        {{ __('Verified by :name on :date', ['name' => $proposal->poVerifiedBy?->name ?? __('Admin'), 'date' => $proposal->po_verified_at?->format('d M Y H:i')]) }}
                    </p>
                @endif

                @if($proposal->isPoReturned())
                    <div class="mt-2 rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200">
                        <p class="font-semibold">{{ __('Returned by :name on :date', ['name' => $proposal->poReturnedBy?->name ?? __('Admin'), 'date' => $proposal->po_returned_at?->format('d M Y H:i')]) }}</p>
                        <p class="mt-1 whitespace-pre-wrap">{{ $proposal->po_return_reason }}</p>
                    </div>
                @endif

                @can('manage-admin')
                    <div class="pt-3 border-t border-slate-100 dark:border-slate-700 flex flex-wrap gap-2 mt-2">
                        @if(! $proposal->isPoVerified() && ! $proposal->isPoReturned())
                            <form action="{{ route('admin.purchasing.verify-po', $proposal) }}" method="POST" class="inline">
                                @csrf
                                <x-icon-button type="submit" icon="sparkles" label="{{ __('Verify PO') }}" title="{{ __('Verify PO') }}" />
                            </form>
                        @endif
                        <button type="button" onclick="document.getElementById('return-po-modal').classList.remove('hidden')"
                                class="group relative overflow-hidden px-4 py-2 rounded-xl border border-red-200 dark:border-red-800 text-red-600 hover:bg-red-600 hover:text-white text-xs font-medium transition-all duration-300 active:scale-95">
                            {{ __('Return to Sales') }}
                        </button>
                    </div>
                @endcan

                @if($proposal->handoffWarnings())
                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        <p class="font-semibold mb-1">{{ __('Handoff data incomplete') }}</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($proposal->handoffWarnings() as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 text-sm space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-semibold text-slate-700 dark:text-slate-200">{{ __('Supplier Purchase Orders') }}</h2>
                    @if($proposal->isReadyForSupplierPo())
                        @can('manage-admin')
                            <x-icon-button as="a" icon="add" href="{{ route('admin.supplier-pos.create', ['proposal_id' => $proposal->id]) }}" title="{{ __('Create Supplier PO') }}" />
                        @endcan
                    @endif
                </div>
                @forelse($proposal->supplierPurchaseOrders as $supplierPo)
                    <a href="{{ route('admin.supplier-pos.show', $supplierPo) }}" class="block rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                        <span class="flex items-center justify-between gap-2">
                            <span class="font-mono font-medium text-accent-600 dark:text-accent-400">{{ $supplierPo->po_number }}</span>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ \App\Models\SupplierPurchaseOrder::statusBadgeClasses($supplierPo->status) }}">{{ \App\Models\SupplierPurchaseOrder::statusLabel($supplierPo->status) }}</span>
                        </span>
                        <span class="block text-xs text-slate-500 mt-0.5">{{ $supplierPo->supplier?->name ?? __('Not filled') }} • Rp {{ number_format($supplierPo->grand_total, 0, ',', '.') }}</span>
                    </a>
                @empty
                    <p class="text-xs text-slate-400">{{ __('No supplier purchase order yet.') }}</p>
                @endforelse
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 text-sm space-y-1">
                <h2 class="font-semibold text-slate-700 dark:text-slate-200 mb-2">{{ __('Quotation Info') }}</h2>
                <p class="text-slate-500">{{ __('Customer') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->customer }}</span></p>
                <p class="text-slate-500">{{ __('PT Group') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->lead?->pt_group ?? __('Not filled') }}</span></p>
                <p class="text-slate-500">{{ __('Sales Owner') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->lead?->assignee?->name ?? __('Not filled') }}</span></p>
                <p class="text-slate-500">{{ __('Prepared By') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->creator?->name ?? __('Not filled') }}</span></p>
                <p class="text-slate-500">{{ __('Quotation Date') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->proposal_date?->format('d M Y') ?? __('Not filled') }}</span></p>
                <p class="text-slate-500">{{ __('Valid Until') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->valid_until?->format('d M Y') ?? __('Not filled') }}</span></p>
                <p class="text-slate-500">{{ __('Project') }}:
                    @if($proposal->project)
                        <a href="{{ route('projects.show', $proposal->project) }}" class="font-medium text-accent-600 dark:text-accent-400 hover:underline">{{ $proposal->project->project_name }}</a>
                    @else
                        <span class="font-medium text-amber-600 dark:text-amber-400">{{ __('Not converted yet') }}</span>
                    @endif
                </p>
            </div>
        </div>
    </div>

    {{-- Modal Return PO to Sales --}}
    <div id="return-po-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4 hidden">
        <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl border border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Return Customer PO to Sales') }}</h3>
            <p class="text-xs text-slate-500">{{ __('Provide revision notes explaining what needs to be fixed or updated by the Sales team.') }}</p>
            <form action="{{ route('admin.purchasing.return-po', $proposal) }}" method="POST" class="space-y-4">
                @csrf
                <textarea name="reason" rows="3" required placeholder="{{ __('e.g. Incomplete warehouse delivery address, missing client signature...') }}"
                          class="w-full rounded-xl border-slate-300 text-sm focus:border-red-500 focus:ring-red-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200"></textarea>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('return-po-modal').classList.add('hidden')"
                            class="px-4 py-2 rounded-xl text-xs font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">
                        {{ __('Cancel') }}
                    </button>
                    <x-danger-button type="submit">{{ __('Return with Notes') }}</x-danger-button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Convert to Project --}}
    @if(! $proposal->project && $proposal->isPoVerified() && ! $proposal->isPoReturned())
        <div id="convert-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4 hidden">
            <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 dark:border-slate-700 space-y-4">
                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Convert to Project') }}</h3>
                <p class="text-xs text-slate-500">{{ __('Create a technician project from this approved proposal.') }}</p>

                <form action="{{ route('sales.proposals.convert', $proposal) }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Jenis Pekerjaan') }} <span class="text-red-500">*</span></label>
                        <select name="work_type_id" required class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200">
                            <option value="">{{ __('Select...') }}</option>
                            @foreach($workTypes as $wt)
                                <option value="{{ $wt->id }}">{{ $wt->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Start Date') }}</label>
                        <input type="date" name="start_date" value="{{ now()->format('Y-m-d') }}"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200">
                    </div>
                    <div class="space-y-2 py-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="requires_delivery" value="1" checked class="rounded border-slate-300 text-accent-600 focus:ring-accent-500">
                            <span class="text-xs text-slate-700 dark:text-slate-300 font-medium">{{ __('Requires Physical Delivery (Goods Delivery Note)') }}</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="requires_installation" value="1" checked class="rounded border-slate-300 text-accent-600 focus:ring-accent-500">
                            <span class="text-xs text-slate-700 dark:text-slate-300 font-medium">{{ __('Requires Field Installation / Technician Work') }}</span>
                        </label>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Notes for Technician (optional)') }}</label>
                        <textarea name="notes" rows="2" placeholder="{{ __('Anything the technician should know before starting...') }}"
                                  class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200">{{ old('notes') }}</textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" onclick="document.getElementById('convert-modal').classList.add('hidden')"
                                class="px-4 py-2 rounded-xl text-xs font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">
                            {{ __('Cancel') }}
                        </button>
                        <x-primary-button type="submit">{{ __('Convert to Project') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</x-app-layout>
