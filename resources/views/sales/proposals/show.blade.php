<x-app-layout>
    @php $isOwner = in_array(auth()->id(), [$proposal->created_by, $proposal->lead->assigned_to]); @endphp
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ $proposal->proposal_number }}</h1>
                <p class="text-slate-500 mt-1">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ \App\Models\Proposal::statusBadgeClasses($proposal->status) }}">{{ \App\Models\Proposal::statusLabel($proposal->status) }}</span> •
                    Rp {{ number_format($proposal->grand_total, 0, ',', '.') }}
                </p>
                <x-handoff-status :proposal="$proposal" class="mt-2" />
                @if($proposal->sent_at)
                    <p class="mt-2 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                        ✓ {{ __('Sent via :channel', ['channel' => $proposal->sentChannelLabel() ?? __('Manual')]) }}
                        · {{ $proposal->sent_at->translatedFormat('d M Y H:i') }}
                    </p>
                @endif
            </div>
            <div class="flex items-center gap-2">
                @can('create', \App\Models\EstimatorRequest::class)
                    <x-icon-button as="a" icon="tools" href="{{ route('estimator.requests.create', ['customer_id' => $proposal->lead?->customer_id, 'lead_id' => $proposal->lead_id, 'proposal_id' => $proposal->id]) }}" title="{{ __('Request Product Price') }}" />
                @endcan
                <x-icon-button as="a" icon="back" href="{{ route('sales.proposals.index') }}" title="{{ __('Back') }}" />
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <p class="text-slate-500">{{ __('Customer') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $proposal->customer }}</span></p>
                <p class="text-slate-500">{{ __('Date') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $proposal->proposal_date?->format('d M Y') ?? '-' }}</span></p>
                <p class="text-slate-500">{{ __('Valid Until') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $proposal->valid_until?->format('d M Y') ?? '-' }}</span></p>
                <p class="text-slate-500">{{ __('Sales Owner') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $proposal->lead->assignee?->name ?? '-' }}</span></p>
                <p class="text-slate-500">{{ __('Prepared By') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $proposal->creator?->name ?? '-' }}</span></p>
                @if($proposal->priceRequest)
                    <p class="text-slate-500">{{ __('Price Request') }}:
                        <a href="{{ route('sales.price-requests.show', $proposal->priceRequest) }}" class="font-medium text-accent-600 dark:text-accent-400 hover:underline">#{{ $proposal->priceRequest->id }}</a>
                    </p>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-500 border-b border-slate-200 dark:border-slate-700">
                            <th class="py-2 pr-2">{{ __('Description') }}</th>
                            <th class="py-2 pr-2 text-right">{{ __('Qty') }}</th>
                            <th class="py-2 pr-2 text-right">{{ __('Price') }}</th>
                            <th class="py-2 text-right">{{ __('Subtotal') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($proposal->items as $item)
                            <tr class="border-b border-slate-100 dark:border-slate-700">
                                <td class="py-2 pr-2">{{ $item->description }} <span class="text-xs text-slate-400">({{ $item->unit }})</span></td>
                                <td class="py-2 pr-2 text-right">{{ rtrim(rtrim(number_format($item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="py-2 pr-2 text-right">{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                <td class="py-2 text-right">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
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

            @can('viewCost', \App\Models\Proposal::class)
                <div class="rounded-xl border border-slate-200 dark:border-slate-600 p-4 text-sm space-y-1">
                    <p class="font-semibold text-slate-700 dark:text-slate-200">{{ __('Internal Financials') }}</p>
                    @if($proposal->hasCompleteCostData())
                        <p class="flex justify-between text-slate-500"><span>{{ __('Net Sales') }}</span><span>Rp {{ number_format($proposal->netSales(), 0, ',', '.') }}</span></p>
                        <p class="flex justify-between text-slate-500"><span>{{ __('Total Cost') }}</span><span>Rp {{ number_format($proposal->total_cost, 0, ',', '.') }}</span></p>
                        <p class="flex justify-between text-slate-500"><span>{{ __('Gross Profit') }}</span><span>Rp {{ number_format($proposal->gross_profit, 0, ',', '.') }}</span></p>
                        <p class="flex justify-between font-bold text-slate-800 dark:text-slate-100"><span>{{ __('Gross Margin') }}</span><span>{{ $proposal->gross_margin === null ? '-' : number_format($proposal->gross_margin, 2) . '%' }}</span></p>
                        @if($proposal->items->contains(fn ($item) => $item->unit_cost !== null))
                            <details class="pt-1">
                                <summary class="cursor-pointer text-xs text-slate-500">{{ __('Cost per item') }}</summary>
                                <ul class="mt-1 space-y-0.5 text-xs text-slate-500">
                                    @foreach($proposal->items as $item)
                                        <li>{{ $item->description }}: Rp {{ number_format($item->unit_cost, 0, ',', '.') }} × {{ $item->quantity }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    @else
                        <p class="text-amber-600 dark:text-amber-400">
                            {{ __('Profit calculation incomplete') }} — {{ __(':count item(s) do not have a cost price yet.', ['count' => $proposal->missingCostItemsCount()]) }}
                        </p>
                    @endif
                </div>
            @endcan

            @if($proposal->notes)
                <p class="text-sm text-slate-700 dark:text-slate-200 whitespace-pre-wrap">{{ $proposal->notes }}</p>
            @endif

            @if($proposal->internal_notes)
                <div class="rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-3">
                    <p class="text-xs font-semibold text-amber-700 dark:text-amber-300">{{ __('Internal Notes') }}</p>
                    <p class="text-sm text-slate-700 dark:text-slate-200 whitespace-pre-wrap">{{ $proposal->internal_notes }}</p>
                </div>
            @endif

            @if($proposal->attachments->isNotEmpty())
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Quotation File') }}</p>
                    <ul class="mt-2 space-y-2">
                        @foreach($proposal->attachments as $file)
                            <li class="flex flex-wrap items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-700 dark:text-slate-200">{{ $file->original_name }}</p>
                                    <p class="text-xs text-slate-400">
                                        {{ $file->humanSize() }} · {{ $file->uploader?->name ?? __('System') }}
                                        · {{ $file->created_at?->translatedFormat('d M Y H:i') }}
                                    </p>
                                </div>
                                <x-file-preview :url="route('sales.proposals.attachments.preview', [$proposal, $file])"
                                                :download="route('sales.proposals.attachments.download', [$proposal, $file])"
                                                :name="$file->original_name" />
                                <x-icon-button as="a" icon="download" href="{{ route('sales.proposals.attachments.download', [$proposal, $file]) }}" title="{{ __('Download') }}" />
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex flex-wrap gap-2 pt-2">
                <x-icon-button as="a" icon="view" href="{{ route('sales.proposals.preview', $proposal) }}" target="_blank" label="{{ __('Preview') }}" title="{{ __('Preview') }}" />
                @if($proposal->pdf_path)
                    <x-icon-button as="a" icon="download" href="{{ route('sales.proposals.pdf', $proposal) }}" label="{{ __('Download PDF') }}" title="{{ __('Download PDF') }}" />
                @endif
                @if($isOwner && (in_array($proposal->status, ['draft', 'revision'], true) || ($proposal->status === 'ready' && (float) $proposal->grand_total <= 0)))
                    <x-icon-button as="a" icon="edit" href="{{ route('sales.proposals.edit', $proposal) }}" label="{{ __('Edit') }}" title="{{ __('Edit') }}" />
                @endif
                @if($isOwner && $proposal->status === 'draft')
                    <form action="{{ route('sales.proposals.ready', $proposal) }}" method="POST" class="inline">
                        @csrf
                        <x-icon-button type="submit" icon="sparkles" label="{{ __('Ready') }}" title="{{ __('Ready') }}" />
                    </form>
                    <form action="{{ route('sales.proposals.submit-technical', $proposal) }}" method="POST" class="inline"
                          onsubmit="return confirm('{{ __('Submit this draft to the technical team?') }}')">
                        @csrf
                        <x-icon-button type="submit" icon="mail" label="{{ __('To Technical') }}" title="{{ __('To Technical') }}" />
                    </form>
                    <form action="{{ route('sales.proposals.cancel', $proposal) }}" method="POST" class="inline"
                          onsubmit="return confirm('{{ __('Cancel this proposal?') }}')">
                        @csrf
                        <x-icon-button type="submit" icon="delete" label="{{ __('Cancel') }}" title="{{ __('Cancel') }}" />
                    </form>
                @endif
                @if($proposal->status === 'pending_technical')
                    <p class="text-sm text-purple-700 dark:text-purple-300">{{ __('Waiting for technical team adjustment.') }}</p>
                    @canany(['manage-technician', 'manage-admin'])
                        <form action="{{ route('sales.proposals.complete-technical', $proposal) }}" method="POST" class="inline">
                            @csrf
                            <x-icon-button type="submit" icon="sparkles" label="{{ __('Done & Ready') }}" title="{{ __('Done & Ready') }}" />
                        </form>
                        <form action="{{ route('sales.proposals.return-technical', $proposal) }}" method="POST" class="inline">
                            @csrf
                            <x-icon-button type="submit" icon="back" label="{{ __('Return') }}" title="{{ __('Return') }}" />
                        </form>
                    @endcanany
                @endif
                @if($isOwner && $proposal->status === 'ready')
                    <form action="{{ route('sales.proposals.send', $proposal) }}" method="POST" class="inline"
                          onsubmit="return confirm('{{ __('Mark this proposal as sent?') }}')">
                        @csrf
                        <x-icon-button type="submit" icon="mail" label="{{ __('Mark as Sent') }}" title="{{ __('Mark as Sent (confirmation only, does not send email)') }}" />
                    </form>
                @endif
                @if($isOwner && $proposal->status === 'ready' && ($wa = $proposal->lead->customer?->whatsapp))
                    @php
                        $waNumber = preg_replace('/\D/', '', $wa);
                        if (str_starts_with($waNumber, '0')) $waNumber = '62' . substr($waNumber, 1);
                        $waText = __('Halo, berikut penawaran *{number}* senilai Rp {total} (berlaku sampai {until}). Mohon konfirmasinya, terima kasih.', [
                            'number' => $proposal->proposal_number,
                            'total' => number_format($proposal->grand_total, 0, ',', '.'),
                            'until' => $proposal->valid_until?->format('d M Y') ?? '-',
                        ]);
                    @endphp
                    <x-icon-button as="a" href="https://wa.me/{{ $waNumber }}?text={{ urlencode($waText) }}" target="_blank" rel="noopener"
                                   onclick="markProposalSent('{{ route('sales.proposals.sent-via', [$proposal, 'whatsapp']) }}')"
                                   label="{{ __('Send via WhatsApp') }}" title="{{ __('Send via WhatsApp') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="h-5 w-5" aria-hidden="true">
                            <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.87 9.87 0 0 0 4.74 1.21c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2m5.83 14.12c-.25.7-1.45 1.33-2.02 1.42-.52.08-1.17.12-1.89-.12-.44-.15-1-.39-1.71-.75-3.03-1.39-5-4.63-5.15-4.84-.15-.21-1.23-1.64-1.23-3.13 0-1.49.78-2.22 1.06-2.52.28-.3.61-.38.81-.38l.58.01c.19.01.44-.07.69.53.25.61.86 2.11.94 2.26.08.15.13.33.03.53-.1.2-.15.33-.3.51l-.45.53c-.15.15-.31.31-.13.61.18.3.8 1.32 1.71 2.14 1.18 1.06 2.17 1.39 2.48 1.55.3.15.48.13.66-.08l1.1-1.28c.2-.26.42-.22.71-.13.3.09 1.9.9 2.23 1.06.38.2.53.44.5.65-.27.69-.52.98-.73 1.23"/>
                        </svg>
                    </x-icon-button>
                @endif
                @if($isOwner && $proposal->status === 'ready' && ($emailTo = $proposal->lead->customer?->email))
                    @php
                        $mailSubject = __('Quotation :number', ['number' => $proposal->proposal_number]);
                        $mailBody = __('Halo, berikut penawaran :number senilai Rp :total (berlaku sampai :until). Mohon konfirmasinya, terima kasih.', [
                            'number' => $proposal->proposal_number,
                            'total' => number_format($proposal->grand_total, 0, ',', '.'),
                            'until' => $proposal->valid_until?->format('d M Y') ?? '-',
                        ]);
                    @endphp
                    <x-icon-button as="a" icon="mail" href="mailto:{{ $emailTo }}?subject={{ rawurlencode($mailSubject) }}&body={{ rawurlencode($mailBody) }}"
                                   label="{{ __('Send via Email') }}" title="{{ __('Open your email app (mailto)') }}" />
                @endif
                @if($isOwner && $proposal->status === 'sent')
                    <form action="{{ route('sales.proposals.viewed', $proposal) }}" method="POST" class="inline">
                        @csrf
                        <x-icon-button type="submit" icon="view" label="{{ __('Viewed') }}" title="{{ __('Viewed') }}" />
                    </form>
                @endif
                @if($isOwner && in_array($proposal->status, ['sent', 'viewed']))
                    <form action="{{ route('sales.proposals.revise', $proposal) }}" method="POST" class="inline">
                        @csrf
                        <x-icon-button type="submit" icon="edit" label="{{ __('Revision') }}" title="{{ __('Revision') }}" />
                    </form>
                    <form action="{{ route('sales.proposals.respond', $proposal) }}" method="POST" class="inline"
                          onsubmit="return confirm('{{ __('Mark this proposal as accepted by the customer?') }}')">
                        @csrf
                        <input type="hidden" name="decision" value="accepted">
                        <x-icon-button type="submit" icon="sparkles" label="{{ __('Accept') }}" title="{{ __('Accept') }}" />
                    </form>
                    <form action="{{ route('sales.proposals.respond', $proposal) }}" method="POST" class="inline"
                          onsubmit="return confirm('{{ __('Mark this proposal as rejected by the customer?') }}')">
                        @csrf
                        <input type="hidden" name="decision" value="rejected">
                        <x-icon-button type="submit" icon="delete" label="{{ __('Reject') }}" title="{{ __('Reject') }}" />
                    </form>
                @endif
                @if($proposal->status === 'accepted')
                    @if($proposal->po_document_path)
                        <x-file-preview :url="route('sales.proposals.download-po', $proposal)"
                                        :download="route('sales.proposals.download-po', $proposal)"
                                        :name="basename($proposal->po_document_path)" />
                        <x-icon-button as="a" icon="download" href="{{ route('sales.proposals.download-po', $proposal) }}" label="{{ __('Download PO') }}" title="{{ __('Download PO') }}" />
                    @endif
                    @if($proposal->project)
                        <x-icon-button as="a" icon="project" href="{{ route('projects.show', $proposal->project) }}" label="{{ __('View Project') }}: {{ $proposal->project->project_name }}" title="{{ __('View Project') }}: {{ $proposal->project->project_name }}" />
                    @elseif($isOwner || auth()->user()->can('manage-admin') || auth()->user()->can('manage-sales-leads'))
                        <x-icon-button icon="add" label="{{ __('Convert to Project') }}" title="{{ __('Convert to Project') }}"
                                       onclick="document.getElementById('convert-modal').classList.remove('hidden')" />
                    @endif
                @endif
            </div>

            {{-- Customer PO (pesanan pelanggan ke 3DY) — bukan Supplier PO --}}
            @if($proposal->status === 'accepted')
                @php
                    $poStatusClass = $proposal->isPoReturned()
                        ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'
                        : ($proposal->isPoVerified()
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                            : 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300');
                @endphp
                <div class="rounded-xl border border-slate-200 dark:border-slate-600 p-4 text-sm space-y-1">
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-semibold text-slate-700 dark:text-slate-200">{{ __('Customer PO') }}</p>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $poStatusClass }}">{{ $proposal->purchasingStatusLabel() }}</span>
                    </div>
                    <p class="text-slate-500">{{ __('PO Number') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $proposal->po_number ?: __('Not set yet') }}</span></p>
                    <p class="text-slate-500">{{ __('PO Date') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $proposal->po_date?->format('d M Y') ?? __('Not set yet') }}</span></p>
                    <p class="text-slate-500">{{ __('Delivery Deadline') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $proposal->delivery_deadline?->format('d M Y') ?? __('Not set yet') }}</span></p>
                    <p class="text-slate-500">{{ __('Delivery Address') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $proposal->delivery_address ?: __('Not set yet') }}</span></p>
                    <p class="text-slate-500">{{ __('Payment Terms') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $proposal->paymentTermsSummary() }}</span></p>
                    @if($proposal->delivery_terms)
                        <p class="text-slate-500">{{ __('Delivery Terms') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $proposal->delivery_terms }}</span></p>
                    @endif
                    @if($proposal->warranty_terms)
                        <p class="text-slate-500">{{ __('Warranty Terms') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $proposal->warranty_terms }}</span></p>
                    @endif

                    @if(session('success'))
                        <div class="rounded-xl border border-green-200 bg-green-50 p-3 text-xs text-green-800 dark:border-green-800 dark:bg-green-950/40 dark:text-green-200 my-2 flex items-center justify-between shadow-sm">
                            <span class="font-semibold flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                {{ session('success') }}
                            </span>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200 my-2 shadow-sm">
                            <span class="font-semibold flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                {{ session('error') }}
                            </span>
                        </div>
                    @endif

                    @if($proposal->isPoReturned())
                        <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200 my-2">
                            <p class="font-bold text-sm">⚠️ {{ __('Customer PO Returned by Admin') }}</p>
                            <p class="mt-1 font-medium">{{ __('Notes from Admin: ') }} <span class="font-normal">{{ $proposal->po_return_reason }}</span></p>
                            <p class="mt-1 text-slate-500 dark:text-slate-400">{{ __('Please revise or upload the corrected PO document below.') }}</p>
                        </div>
                    @endif

                    @if($isOwner)
                        <form action="{{ route('sales.proposals.upload-po', $proposal) }}" method="POST" enctype="multipart/form-data" class="mt-2 grid grid-cols-1 md:grid-cols-4 gap-2 items-end">
                            @csrf
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">{{ __('PO Number') }}</label>
                                <input type="text" name="po_number" value="{{ old('po_number', $proposal->po_number) }}" class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">{{ __('PO Date') }}</label>
                                <input type="date" name="po_date" value="{{ old('po_date', $proposal->po_date?->format('Y-m-d')) }}" class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">{{ __('Delivery Deadline') }}</label>
                                <input type="date" name="delivery_deadline" value="{{ old('delivery_deadline', $proposal->delivery_deadline?->format('Y-m-d')) }}" class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">{{ __('Delivery Address') }}</label>
                                <input type="text" name="delivery_address" value="{{ old('delivery_address', $proposal->delivery_address) }}" class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                            </div>

                            <div class="md:col-span-4 grid grid-cols-1 md:grid-cols-5 gap-2 rounded-xl border border-slate-200 dark:border-slate-600 p-3">
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">{{ __('Payment Scheme') }}</label>
                                    <select name="payment_scheme" class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                                        <option value="">{{ __('Not set yet') }}</option>
                                        @foreach(\App\Models\Proposal::PAYMENT_SCHEMES as $scheme)
                                            <option value="{{ $scheme }}" @selected(old('payment_scheme', $proposal->payment_scheme) === $scheme)>{{ \App\Models\Proposal::paymentSchemeLabel($scheme) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">{{ __('DP %') }}</label>
                                    <input type="number" name="dp_percent" min="1" max="99" step="0.01" value="{{ old('dp_percent', $proposal->dp_percent) }}" class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">{{ __('Term Days') }}</label>
                                    <input type="number" name="term_days" min="1" max="365" step="1" value="{{ old('term_days', $proposal->term_days) }}" class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">{{ __('Payment Terms Note') }}</label>
                                    <input type="text" name="payment_terms" maxlength="255" value="{{ old('payment_terms', $proposal->payment_terms) }}" class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">{{ __('Delivery Terms') }}</label>
                                    <input type="text" name="delivery_terms" maxlength="255" value="{{ old('delivery_terms', $proposal->delivery_terms) }}" class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                                    <input type="text" name="warranty_terms" maxlength="255" value="{{ old('warranty_terms', $proposal->warranty_terms) }}" placeholder="{{ __('Warranty Terms') }}" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                                </div>
                            </div>

                            <div class="md:col-span-4 rounded-xl border border-slate-200 dark:border-slate-700 p-3 space-y-2 bg-slate-50/50 dark:bg-slate-800/40">
                                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                        @if($proposal->isPoReturned())
                                            <span class="text-red-600 dark:text-red-400 font-bold">⚠️ {{ __('Upload Revised PO Document') }}</span>
                                        @elseif($proposal->po_document_path)
                                            {{ __('Replace / Upload New PO Document (Optional)') }}
                                        @else
                                            {{ __('Upload Customer PO Document') }} <span class="text-red-500">*</span>
                                        @endif
                                    </label>
                                    @if($proposal->po_document_path)
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-slate-400">{{ __('Current File:') }}</span>
                                            <a href="{{ route('sales.proposals.download-po', $proposal) }}" class="text-xs font-semibold text-accent-600 dark:text-accent-400 hover:underline flex items-center gap-1">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                {{ __('Download Current PO') }}
                                            </a>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                                    <input type="file" name="po_file" {{ $proposal->po_document_path ? '' : 'required' }} accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                           class="text-sm text-slate-500 file:mr-2 file:py-2 file:px-3 file:rounded-xl file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-950/60 dark:file:text-indigo-300">
                                    <x-icon-button type="submit" icon="upload"
                                        label="{{ $proposal->isPoReturned() ? __('Submit Revised PO') : ($proposal->po_document_path ? __('Save PO Changes') : __('Upload PO')) }}"
                                        title="{{ $proposal->isPoReturned() ? __('Submit Revised PO') : ($proposal->po_document_path ? __('Save PO Changes') : __('Upload PO')) }}" />
                                </div>
                                @if($proposal->isPoReturned())
                                    <p class="text-[11px] text-amber-600 dark:text-amber-400">{{ __('Uploading a revised document or updating the PO details will clear the return flag and send it back to Admin for verification.') }}</p>
                                @endif
                            </div>
                            @error('po_file') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
                            @error('po_number') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
                            @error('po_date') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
                            @error('delivery_deadline') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
                            @error('payment_scheme') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
                            @error('dp_percent') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
                            @error('term_days') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
                        </form>
                    @endif
                </div>
            @endif

            @if($isOwner && $proposal->status === 'accepted' && !$proposal->project)
                @php
                    $handoffItems = [
                        ['label' => __('Customer PO document'), 'done' => filled($proposal->po_document_path)],
                        ['label' => __('Customer PO number'), 'done' => filled($proposal->po_number)],
                        ['label' => __('Payment terms'), 'done' => filled($proposal->payment_scheme)],
                        ['label' => __('Delivery deadline'), 'done' => filled($proposal->delivery_deadline)],
                        ['label' => __('Customer PO verified'), 'done' => $proposal->isPoVerified()],
                    ];
                    $handoffComplete = collect($handoffItems)->every(fn ($item) => $item['done']);
                @endphp
                <div class="mt-3 rounded-xl border border-slate-200 p-3 dark:border-slate-600">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Handoff checklist') }}</p>
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach($handoffItems as $item)
                            <li class="flex items-center gap-2 {{ $item['done'] ? 'text-green-600 dark:text-green-400' : 'text-slate-500 dark:text-slate-300' }}">
                                @if($item['done'])
                                    <x-icon name="check-circle" class="h-4 w-4 shrink-0" />
                                @else
                                    <span class="inline-block h-2 w-2 shrink-0 rounded-full bg-slate-300 dark:bg-slate-500"></span>
                                @endif
                                {{ $item['label'] }}
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-xs text-slate-400">{{ __('All items must be complete before converting to a project.') }}</p>
                </div>

                <div id="convert-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
                    <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-xl">
                        <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Convert to Project') }}</h2>
                        <p class="text-sm text-slate-500 mt-1">{{ __('A new project will be created for the Technician division from this proposal.') }}</p>
                        <form action="{{ route('sales.proposals.convert', $proposal) }}" method="POST" class="mt-4 space-y-3">
                            @csrf
                            {{-- Ringkasan handover (FR-SALES-02) --}}
                            <div class="space-y-1 rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-700/40">
                                <p><span class="text-slate-500 dark:text-slate-400">{{ __('Customer') }}:</span> <span class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->lead?->customer?->name ?? $proposal->customer ?? '-' }}</span></p>
                                <p><span class="text-slate-500 dark:text-slate-400">{{ __('Scope') }}:</span> <span class="font-medium text-slate-800 dark:text-slate-100">{{ \Illuminate\Support\Str::limit($proposal->lead?->kebutuhan ?? '-', 90) }}</span></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Jenis Pekerjaan') }} <span class="text-red-500">*</span></label>
                                <select name="work_type_id" required class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                                    <option value="">{{ __('Select...') }}</option>
                                    @foreach($workTypes as $wt)
                                        <option value="{{ $wt->id }}">{{ $wt->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Start Date') }}</label>
                                <input type="date" name="start_date" value="{{ now()->format('Y-m-d') }}"
                                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('PIC Engineer') }}</label>
                                <select name="teknisi_user_id" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                                    <option value="">{{ __('No engineer yet (Unassigned)') }}</option>
                                    @foreach($technicians as $tech)
                                        <option value="{{ $tech->id }}" {{ (string) old('teknisi_user_id') === (string) $tech->id ? 'selected' : '' }}>{{ $tech->name }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-slate-400">{{ __('The lead engineer can assign this later.') }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Support Technicians') }}</label>
                                <div class="grid max-h-32 grid-cols-2 gap-1.5 overflow-y-auto rounded-xl border border-slate-200 p-2 dark:border-slate-600">
                                    @forelse($technicians as $tech)
                                        <label class="inline-flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                                            <input type="checkbox" name="support_user_ids[]" value="{{ $tech->id }}"
                                                   class="rounded border-slate-300 text-accent-600 focus:ring-accent-500"
                                                   {{ in_array((string) $tech->id, array_map('strval', (array) old('support_user_ids', [])), true) ? 'checked' : '' }}>
                                            {{ $tech->name }}
                                        </label>
                                    @empty
                                        <p class="text-xs text-slate-400">{{ __('No engineers available.') }}</p>
                                    @endforelse
                                </div>
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
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Notes for Technician (optional)') }}</label>
                                <textarea name="notes" rows="2" placeholder="{{ __('Anything the technician should know before starting...') }}"
                                          class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">{{ old('notes') }}</textarea>
                                @error('notes') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex justify-end gap-2 pt-2">
                                <button type="button" onclick="document.getElementById('convert-modal').classList.add('hidden')"
                                        class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                                    {{ __('Cancel') }}
                                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                                </button>
                                @if($handoffComplete)
                                    <x-icon-button type="submit" icon="add" label="{{ __('Convert to Project') }}" title="{{ __('Convert to Project') }}" />
                                @else
                                    <span class="inline-flex cursor-not-allowed items-center justify-center rounded-xl border border-slate-200 bg-white px-6 py-2.5 text-sm font-medium text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500"
                                          title="{{ __('Complete the handoff checklist first.') }}">
                                        {{ __('Convert to Project') }}
                                    </span>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
    @once
    <script>
        /**
         * Tandai quotation terkirim lewat kanal (WhatsApp/email) tanpa
         * menghalangi link yang sedang dibuka (WA/mailto).
         */
        function markProposalSent(url) {
            const token = document.querySelector('meta[name="csrf-token"]')?.content;

            fetch(url, {
                method: 'POST',
                keepalive: true,
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            }).then((response) => {
                if (response.ok) {
                    setTimeout(() => window.location.reload(), 600);
                }
            }).catch(() => {});
        }
    </script>
    @endonce
</x-app-layout>
