<x-app-layout>
    @php
        $backUrl ??= route('estimator.requests.index');
        $itemStatusColors = [
            'pending' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
            'answered' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300',
            'revision' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
            'accepted' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        ];
        $canAnswer = auth()->user()->can('answer', $estimatorRequest);
        $isEstimator = auth()->user()->can('manage-price-requests');
        $formOpen = $canAnswer && ! in_array($estimatorRequest->status, ['answered', 'completed'], true);
        $contactSuppliers = $isEstimator
            ? $estimatorRequest->items->pluck('supplier')->filter()->unique('id')->values()
            : collect();
        $ptGroup = $estimatorRequest->customer?->pt_group;
        $ptColor = $ptGroup
            ? (\App\Models\Lead::PT_COLORS[$ptGroup] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200')
            : null;
        $supplierOptions = $suppliers->mapWithKeys(fn ($supplier) => [
            $supplier->id => $supplier->name.($supplier->contact_person ? ' · '.$supplier->contact_person : ''),
        ])->all();
        $keepDates = [
            '3d' => now()->timezone('Asia/Jakarta')->addDays(3)->format('Y-m-d'),
            '7d' => now()->timezone('Asia/Jakarta')->addDays(7)->format('Y-m-d'),
            '14d' => now()->timezone('Asia/Jakarta')->addDays(14)->format('Y-m-d'),
            '1m' => now()->timezone('Asia/Jakarta')->addMonth()->format('Y-m-d'),
        ];
        $waItemLines = $estimatorRequest->items->values()->map(function ($it, $idx) {
            $qty = rtrim(rtrim(number_format((float) $it->quantity, 2, '.', ''), '0'), '.');

            return ($idx + 1).'. '.$it->description.' - '.$qty.' '.$it->unit;
        })->implode("\n");
        $estimatorName = auth()->user()->name;
        $waSuppliers = $suppliers->map(fn ($s) => [
            'id' => (string) $s->id,
            'name' => $s->name,
            'contact' => $s->contact_person ?: $s->name,
            'phone' => preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', (string) $s->phone)),
        ])->values()->all();
        $firstPhoneSupplier = collect($waSuppliers)->first(fn ($supplier) => $supplier['phone'] !== '');
        $defaultWaSupplierId = (string) (
            $estimatorRequest->items->pluck('supplier_id')->filter()->first()
            ?? ($firstPhoneSupplier['id'] ?? null)
            ?? ($waSuppliers[0]['id'] ?? '')
        );
    @endphp

    <div>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ __('Product Price Request') }}</h1>
                    <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">#{{ $estimatorRequest->id }}</span>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ \App\Models\EstimatorRequest::statusColor($estimatorRequest->status) }}">
                        {{ \App\Models\EstimatorRequest::statusLabel($estimatorRequest->status) }}
                    </span>
                </div>
                <p class="mt-2 flex flex-wrap items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Customer') }}:
                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $estimatorRequest->customer?->name ?? '-' }}</span>
                    @if($ptGroup)
                        <span class="inline-flex rounded-lg px-2 py-0.5 text-[11px] font-semibold {{ $ptColor }}">{{ $ptGroup }}</span>
                    @endif
                </p>
            </div>
            <x-back-button :href="$backUrl" />
        </div>

        @if($estimatorRequest->status === 'revision')
            <section class="mb-4 overflow-hidden rounded-2xl border-2 border-amber-300 bg-amber-50 p-5 shadow-sm dark:border-amber-700/70 dark:bg-amber-950/40" aria-labelledby="estimator-revision-title">
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300" aria-hidden="true">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM10.29 3.86L1.82 18a2.25 2.25 0 002.01 3h16.34a2.25 2.25 0 002.01-3L13.71 3.86a2.25 2.25 0 00-3.42 0z"/>
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 id="estimator-revision-title" class="text-base font-bold text-amber-900 dark:text-amber-200">
                            {{ $canAnswer ? __('Revision Required') : __('Revision Sent') }}
                        </h2>
                        <p class="mt-1 text-sm text-amber-800 dark:text-amber-200/90">
                            {{ $canAnswer
                                ? __('Sales asked for a revision. Update the prices below, then send them again.')
                                : __('Waiting for Estimator to update the prices.') }}
                        </p>
                        @if($estimatorRequest->revision_reason)
                            <blockquote class="mt-3 rounded-xl border border-amber-200 bg-white/70 px-4 py-3 text-sm leading-6 text-amber-900 dark:border-amber-800/60 dark:bg-slate-900/40 dark:text-amber-100">
                                {{ $estimatorRequest->revision_reason }}
                            </blockquote>
                        @endif
                        @if($estimatorRequest->revision_requested_at)
                            <p class="mt-2 text-xs text-amber-700 dark:text-amber-300/80">
                                {{ __('Requested :time', ['time' => $estimatorRequest->revision_requested_at->timezone('Asia/Jakarta')->format('d M Y, H:i')]) }}
                            </p>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6" aria-labelledby="estimator-request-details">
            <h2 id="estimator-request-details" class="mb-4 text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Request Details') }}</h2>

            <div class="grid grid-cols-1 items-start gap-3 md:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-700/50">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('Requested by') }}</p>
                    <div class="mt-2 flex items-center gap-3">
                        @if($estimatorRequest->requester)
                            <x-user-avatar :user="$estimatorRequest->requester" size="w-11 h-11" text="text-sm" :clickable="false" />
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-800 dark:text-slate-100">{{ $estimatorRequest->requester->name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $estimatorRequest->created_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') ?? '-' }}</p>
                            </div>
                        @else
                            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-200 text-slate-500 dark:bg-slate-600 dark:text-slate-300" aria-hidden="true">?</span>
                            <p class="font-semibold text-slate-700 dark:text-slate-200">-</p>
                        @endif
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-700/50">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('Estimator') }}</p>
                    <div class="mt-2 flex items-center gap-3">
                        @if($estimatorRequest->handler)
                            <x-user-avatar :user="$estimatorRequest->handler" size="w-11 h-11" text="text-sm" color="green" :clickable="false" />
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-800 dark:text-slate-100">{{ $estimatorRequest->handler->name }}</p>
                                @if($estimatorRequest->completed_at)
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Completed') }}: {{ $estimatorRequest->completed_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }}</p>
                                @else
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Handling this request') }}</p>
                                @endif
                            </div>
                        @else
                            <span class="flex h-9 w-9 items-center justify-center rounded-full border border-dashed border-slate-300 bg-white text-slate-400 dark:border-slate-500 dark:bg-slate-800" aria-hidden="true">
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                </svg>
                            </span>
                            <p class="text-xs italic text-slate-500 dark:text-slate-400">{{ __('Not assigned yet') }}</p>
                        @endif
                    </div>
                </div>
            </div>

            @if($estimatorRequest->project || $estimatorRequest->proposal)
                <div class="mt-4 flex flex-wrap gap-4 text-sm">
                    @if($estimatorRequest->project)
                        <span class="text-slate-500 dark:text-slate-400">{{ __('Project') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $estimatorRequest->project->project_name }}</span></span>
                    @endif
                    @if($estimatorRequest->proposal)
                        <span class="text-slate-500 dark:text-slate-400">{{ __('Quotation') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $estimatorRequest->proposal->proposal_number }}</span></span>
                    @endif
                </div>
            @endif

            @if($estimatorRequest->notes)
                <div class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-600">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Notes') }}</p>
                    <p class="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $estimatorRequest->notes }}</p>
                </div>
            @endif

            @if($estimatorRequest->attachment_path)
                @php
                    $attachmentExtension = strtoupper(pathinfo($estimatorRequest->attachment_path, PATHINFO_EXTENSION));
                @endphp
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-600">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300" aria-hidden="true">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5A3.375 3.375 0 0010.125 2.25H8.25m0 11.25h7.5m-7.5 3h4.5m-9 3h13.5a1.5 1.5 0 001.5-1.5V6.75a1.5 1.5 0 00-1.5-1.5H8.25a1.5 1.5 0 00-1.5 1.5v13.5a1.5 1.5 0 001.5 1.5z"/>
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <p class="min-w-0 break-all text-sm font-medium text-slate-800 dark:text-slate-100">
                                {{ $estimatorRequest->attachment_name ?: __('Old file name not recorded') }}
                            </p>
                            <p class="mt-0.5 text-[11px] text-slate-400 dark:text-slate-500">
                                {{ $attachmentExtension ?: __('File') }}
                                @if($estimatorRequest->attachment_size)
                                    &middot; {{ \Illuminate\Support\Number::fileSize($estimatorRequest->attachment_size) }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <x-icon-button as="a" icon="download" href="{{ route('estimator.requests.attachment', $estimatorRequest) }}" label="{{ __('Download Attachment') }}" title="{{ __('Download Attachment') }}" />
                </div>
            @endif
        </section>

        <section class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-600 dark:bg-slate-800"
                 x-data="{
                     editing: {{ $formOpen ? 'true' : 'false' }},
                     waSuppliers: @js($waSuppliers),
                     waSupplierId: @js($defaultWaSupplierId),
                     waItems: @js($waItemLines),
                     estimatorName: @js($estimatorName),
                     waUrl() {
                         const sup = this.waSuppliers.find(s => String(s.id) === String(this.waSupplierId));
                         const name = sup ? sup.contact : 'Supplier';
                         const phone = sup && sup.phone ? sup.phone : '';
                         const msg = `Halo ${name}, saya ${this.estimatorName} dari 3DY. Mau cek stok dan harga modal untuk barang berikut ya:\n${this.waItems}\nApakah ready stok atau indent? Dan harga ini bisa di-keep sampai kapan ya? Terima kasih.`;
                         return `https://wa.me/${phone}?text=${encodeURIComponent(msg)}`;
                     }
                 }" aria-labelledby="estimator-requested-items">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-700/60">
                <h2 id="estimator-requested-items" class="font-semibold text-slate-700 dark:text-slate-200">{{ __('Requested Items') }}</h2>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ trans_choice(':count item|:count items', $estimatorRequest->items->count(), ['count' => $estimatorRequest->items->count()]) }}</span>
                    @if($canAnswer && $estimatorRequest->status === 'answered')
                        <x-icon-button type="button" icon="edit" label="{{ __('Edit Prices') }}" title="{{ __('Edit Prices') }}" x-on:click="editing = true" />
                    @endif
                </div>
            </div>

            @if($canAnswer)
                <div x-show="editing" x-cloak style="{{ $formOpen ? '' : 'display: none' }}"
                     class="flex flex-wrap items-center justify-between gap-3 border-b border-emerald-100 bg-emerald-50/60 px-5 py-3 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-emerald-900 dark:text-emerald-200">{{ __('Ask Supplier via WhatsApp') }}</p>
                        <p class="text-[11px] text-emerald-700 dark:text-emerald-300/80">{{ __('Choose a distributor to open a ready-to-send WhatsApp message with all requested items. This only opens WhatsApp and does not fill the table.') }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <select x-model="waSupplierId" aria-label="{{ __('Select supplier') }}"
                                class="h-10 rounded-xl border-emerald-200 bg-white text-xs font-medium text-slate-700 focus:border-emerald-500 focus:ring-emerald-500 dark:border-emerald-800 dark:bg-slate-800 dark:text-slate-200">
                            <option value="">{{ __('Select supplier') }}</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}{{ $supplier->phone ? ' ('.$supplier->phone.')' : '' }}</option>
                            @endforeach
                        </select>
                        <x-icon-button as="a" icon="whatsapp" x-bind:href="waUrl()" target="_blank" rel="noopener" label="{{ __('Ask Supplier via WhatsApp') }}" title="{{ __('Ask Supplier via WhatsApp') }}" />
                    </div>
                </div>

                <form action="{{ route('estimator.requests.answer', $estimatorRequest) }}" method="POST"
                      x-show="editing" x-cloak
                      style="{{ $formOpen ? '' : 'display: none' }}">
                    @csrf
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-xs">
                            <thead class="bg-slate-50 uppercase tracking-wider text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
                                <tr>
                                    <th class="px-5 py-3 text-center">{{ __('Item') }}</th>
                                    <th class="px-5 py-3 text-center">{{ __('Supplier') }}</th>
                                    <th class="border-l border-slate-200 px-5 py-3 text-center dark:border-slate-700/70">{{ __('Cost') }}</th>
                                    <th class="px-5 py-3 text-center">{{ __('Stock') }}</th>
                                    <th class="px-5 py-3 text-center">{{ __('Warranty') }}</th>
                                    <th class="px-5 py-3 text-center">{{ __('Valid Until') }}</th>
                                    <th class="px-5 py-3 text-center">{{ __('Note') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                                @foreach($estimatorRequest->items as $index => $item)
                                    @php
                                        $lastLog = $item->product?->latestCostLog;
                                        $lastSupplierLabel = $lastLog?->supplier
                                            ? $lastLog->supplier->name.($lastLog->supplier->contact_person ? ' · '.$lastLog->supplier->contact_person : '')
                                            : '';
                                        $lastLogPayload = $lastLog ? [
                                            'supplier_id' => $lastLog->supplier_id ? (string) $lastLog->supplier_id : '',
                                            'supplier_name' => $lastSupplierLabel,
                                            'price' => (float) $lastLog->price,
                                            'stock_status' => $lastLog->stock_status ?: 'ready',
                                            'stock_eta' => $lastLog->stock_eta ?: '',
                                            'warranty_info' => $lastLog->warranty_info ?: '',
                                            'valid_until' => $lastLog->valid_until && ! $lastLog->valid_until->isPast() ? $lastLog->valid_until->format('Y-m-d') : '',
                                        ] : null;
                                        $supplierValue = old("items.{$index}.supplier_id", $item->supplier_id);
                                        $priceValue = old("items.{$index}.price", $item->price);
                                        $priceDisplay = ($priceValue !== null && $priceValue !== '')
                                            ? number_format((float) $priceValue, 0, ',', '.')
                                            : '';
                                        $stockValue = old("items.{$index}.stock_status", $item->stock_status) ?: 'ready';
                                        $etaValue = old("items.{$index}.stock_eta", $item->stock_eta);
                                    @endphp
                                    <tr class="align-top" data-estimator-row data-qty="{{ $item->quantity }}">
                                        <td class="px-5 py-4 text-left">
                                            <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ $item->description }}</p>
                                            <p class="mt-0.5 text-[11px] text-slate-400">{{ $item->product?->sku ?: '-' }} &middot; {{ $item->quantity }} {{ $item->unit }}</p>
                                            @if($item->notes)<p class="mt-0.5 text-[11px] text-slate-400">{{ $item->notes }}</p>@endif
                                            @if($lastLog)
                                                <div class="mt-2 rounded-xl border border-teal-200 bg-teal-50/70 p-2 text-[11px] text-teal-900 dark:border-teal-800/60 dark:bg-teal-950/30 dark:text-teal-200">
                                                    <p class="font-semibold">
                                                        {{ __('Last price') }}: Rp {{ number_format((float) $lastLog->price, 0, ',', '.') }}
                                                        @if($lastLog->supplier)
                                                            &middot; {{ $lastLog->supplier->name }}
                                                        @endif
                                                    </p>
                                                    @if($lastLog->valid_until)
                                                        <p class="mt-0.5 text-[10px] text-teal-700 dark:text-teal-300">
                                                            {{ __('Valid until :date', ['date' => $lastLog->valid_until->format('d M Y')]) }}
                                                        </p>
                                                    @endif
                                                    <button type="button" onclick="useLastPrice({{ $index }}, @js($lastLogPayload))"
                                                            class="mt-1.5 inline-flex items-center gap-1 rounded-lg bg-teal-600 px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-teal-700 active:scale-95">
                                                        {{ __('Use Last Price') }}
                                                    </button>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4 text-left">
                                            <div class="min-w-[190px]">
                                                <x-searchable-select
                                                    :name="'items['.$index.'][supplier_id]'"
                                                    :options="$supplierOptions"
                                                    :selected="$supplierValue"
                                                    :placeholder="__('Search supplier...')"
                                                    input-class="h-10 w-full rounded-xl border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500" />
                                            </div>
                                        </td>
                                        <td class="border-l border-slate-100 px-5 py-4 text-right dark:border-slate-700/60">
                                            <div class="relative min-w-[220px]">
                                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-500 dark:text-slate-300">Rp</span>
                                                <input type="text" inputmode="numeric" name="items[{{ $index }}][price]" value="{{ $priceDisplay }}" oninput="formatRupiahInput(this)" aria-label="{{ __('Cost') }}"
                                                       class="h-10 w-full rounded-xl border-slate-300 pl-9 pr-3 text-right text-sm font-semibold tabular-nums text-slate-900 placeholder:text-slate-400 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500">
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 text-center">
                                            <select name="items[{{ $index }}][stock_status]" onchange="toggleStockEta(this)"
                                                    class="mx-auto h-10 w-full max-w-[130px] rounded-xl border-slate-300 text-sm text-slate-900 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                                                <option value="ready" @selected($stockValue === 'ready')>{{ __('Ready') }}</option>
                                                <option value="indent" @selected($stockValue === 'indent')>{{ __('Indent') }}</option>
                                            </select>
                                            <div data-stock-eta class="{{ $stockValue === 'indent' ? '' : 'hidden' }} mt-1.5">
                                                <input type="text" name="items[{{ $index }}][stock_eta]" maxlength="100" value="{{ $etaValue }}" placeholder="{{ __('e.g. 2-3 weeks') }}" @disabled($stockValue !== 'indent')
                                                       class="h-10 w-full max-w-[150px] rounded-xl border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:border-accent-500 focus:ring-accent-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500">
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 text-left">
                                            <input type="text" name="items[{{ $index }}][warranty_info]" maxlength="255" value="{{ old("items.{$index}.warranty_info", $item->warranty_info) }}" placeholder="{{ __('e.g. 1 year distributor') }}"
                                                   class="h-10 w-full min-w-[170px] rounded-xl border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500">
                                        </td>
                                        <td class="px-5 py-4 text-center">
                                            <input type="date" name="items[{{ $index }}][valid_until]" value="{{ old("items.{$index}.valid_until", $item->valid_until?->format('Y-m-d')) }}"
                                                   class="mx-auto h-10 w-full max-w-[170px] rounded-xl border-slate-300 text-sm text-slate-900 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                                            <div class="mt-1.5 flex flex-wrap justify-center gap-1">
                                                <button type="button" onclick="setKeepDate({{ $index }}, '{{ $keepDates['3d'] }}')" class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-semibold text-slate-600 transition hover:border-accent-500 hover:bg-accent-50 hover:text-accent-700 active:scale-95 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">{{ __('+3 Days') }}</button>
                                                <button type="button" onclick="setKeepDate({{ $index }}, '{{ $keepDates['7d'] }}')" class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-semibold text-slate-600 transition hover:border-accent-500 hover:bg-accent-50 hover:text-accent-700 active:scale-95 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">{{ __('+1 Week') }}</button>
                                                <button type="button" onclick="setKeepDate({{ $index }}, '{{ $keepDates['14d'] }}')" class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-semibold text-slate-600 transition hover:border-accent-500 hover:bg-accent-50 hover:text-accent-700 active:scale-95 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">{{ __('+2 Weeks') }}</button>
                                                <button type="button" onclick="setKeepDate({{ $index }}, '{{ $keepDates['1m'] }}')" class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-semibold text-slate-600 transition hover:border-accent-500 hover:bg-accent-50 hover:text-accent-700 active:scale-95 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">{{ __('+1 Month') }}</button>
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 text-left">
                                            <input type="text" name="items[{{ $index }}][notes]" maxlength="255" value="{{ old("items.{$index}.notes", $item->notes) }}" placeholder="{{ __('Vendor / MOQ note') }}"
                                                   class="h-10 w-full min-w-[160px] rounded-xl border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @error('items') <p class="px-5 pb-2 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    @foreach($errors->keys() as $errorKey)
                        @if(str_starts_with($errorKey, 'items.'))
                            <p class="px-5 pb-2 text-xs text-rose-600 dark:text-rose-400">{{ $errors->first($errorKey) }}</p>
                        @endif
                    @endforeach
                    <div class="flex flex-col gap-2 border-t border-slate-100 px-5 py-4 dark:border-slate-700/60 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('Estimated Total Cost') }}</p>
                        <p id="estimator-total-cost" aria-live="polite" class="text-lg font-extrabold tabular-nums text-slate-900 dark:text-slate-100">Rp 0</p>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4 dark:border-slate-700/60">
                        @if($estimatorRequest->status === 'answered')
                            <x-secondary-button type="button" x-on:click="editing = false">{{ __('Cancel') }}</x-secondary-button>
                        @endif
                        <x-icon-button variant="solid" type="submit" icon="upload" label="{{ __('Send Prices') }}" title="{{ __('Send Prices') }}" />
                    </div>
                </form>
            @endif

            <div x-show="!editing" x-cloak class="overflow-x-auto">
                <table class="min-w-full text-left text-xs">
                    <thead class="bg-slate-50 uppercase tracking-wider text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3 text-center">{{ __('Item') }}</th>
                            <th class="px-5 py-3 text-center">{{ __('Supplier') }}</th>
                            <th class="border-l border-slate-200 px-5 py-3 text-center dark:border-slate-700/70">{{ __('Cost') }}</th>
                            <th class="px-5 py-3 text-center">{{ __('Stock') }}</th>
                            <th class="px-5 py-3 text-center">{{ __('Warranty') }}</th>
                            <th class="px-5 py-3 text-center">{{ __('Valid Until') }}</th>
                            <th class="px-5 py-3 text-center">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($estimatorRequest->items as $item)
                            <tr class="align-top">
                                <td class="px-5 py-4 text-left">
                                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $item->description }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-400">{{ $item->product?->sku ?: '-' }} &middot; {{ $item->quantity }} {{ $item->unit }}</p>
                                    @if($item->product_id && (auth()->user()->can('manage-price-requests') || auth()->user()->can('manage-sales-leads') || auth()->user()->can('manage-admin') || auth()->user()->hasRole('ceo')))
                                        <a href="{{ route('estimator.price-history', ['product_id' => $item->product_id]) }}" class="mt-1 inline-block text-[11px] font-semibold text-accent-600 hover:underline dark:text-accent-400">{{ __('Price History') }}</a>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-left text-slate-600 dark:text-slate-300">{{ $item->supplier?->name ?? '-' }}</td>
                                <td class="border-l border-slate-100 px-5 py-4 text-right font-semibold tabular-nums text-slate-800 dark:border-slate-700/60 dark:text-slate-100">{{ $item->price !== null ? 'Rp '.number_format((float) $item->price, 0, ',', '.') : '-' }}</td>
                                <td class="px-5 py-4 text-center">
                                    @if($item->stock_status)
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $item->stock_status === 'indent' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' }}">
                                            {{ $item->stock_status === 'indent' ? __('Indent') : __('Ready') }}
                                        </span>
                                        @if($item->stock_eta)<p class="mt-0.5 text-[11px] text-slate-400">{{ $item->stock_eta }}</p>@endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-left text-slate-600 dark:text-slate-300">{{ $item->warranty_info ?: '-' }}</td>
                                <td class="px-5 py-4 whitespace-nowrap text-center tabular-nums text-slate-600 dark:text-slate-300">{{ $item->valid_until?->format('d M Y') ?? '-' }}</td>
                                <td class="px-5 py-4 text-center">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $itemStatusColors[$item->status] ?? 'bg-slate-100 text-slate-600' }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="mt-4 grid grid-cols-1 items-start gap-4 lg:grid-cols-2">
            @can('decide', $estimatorRequest)
                @if($estimatorRequest->status === 'answered')
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                        <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Review Prices') }}</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Accept the prices or ask Estimator for a revision.') }}</p>
                        <form action="{{ route('estimator.requests.accept', $estimatorRequest) }}" method="POST" class="mt-4"
                              data-confirm="{{ __('The prices are locked and used for the customer quotation.') }}"
                              data-confirm-title="{{ __('Accept these prices as final?') }}"
                              data-confirm-tone="success"
                              data-confirm-label="{{ __('Yes, accept prices') }}"
                              data-confirm-details="{{ json_encode([
                                  ['label' => __('Customer'), 'value' => $estimatorRequest->customer?->name ?? '-'],
                                  ['label' => __('Request'), 'value' => '#'.$estimatorRequest->id],
                              ], JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }}">
                            @csrf
                            <x-icon-button type="submit" icon="sparkles" label="{{ __('Accept Prices') }}" title="{{ __('Accept Prices') }}" />
                        </form>
                        <form action="{{ route('estimator.requests.revision', $estimatorRequest) }}" method="POST" class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-600">
                            @csrf
                            <label for="estimator-revision-reason" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Revision reason') }}</label>
                            <textarea id="estimator-revision-reason" name="reason" rows="3" required maxlength="2000" class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800" placeholder="{{ __('Explain what must be revised (price, product, stock, or warranty).') }}">{{ old('reason') }}</textarea>
                            <div class="mt-2">
                                <x-icon-button type="submit" icon="edit" label="{{ __('Request Revision') }}" title="{{ __('Request Revision') }}" />
                            </div>
                        </form>
                    </section>
                @endif
            @endcan

            @if($isEstimator && $contactSuppliers->isNotEmpty())
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                    <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Supplier Contacts') }}</h2>
                    <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($contactSuppliers as $supplier)
                            @php
                                $supPhone = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', (string) $supplier->phone));
                                $supContact = $supplier->contact_person ?: $supplier->name;
                                $supWaMsg = "Halo {$supContact}, saya {$estimatorName} dari 3DY. Mau cek stok dan harga modal untuk barang berikut ya:\n{$waItemLines}\nApakah ready stok atau indent? Dan harga ini bisa di-keep sampai kapan ya? Terima kasih.";
                            @endphp
                            <div class="flex flex-wrap items-center justify-between gap-2 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $supplier->name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $supplier->contact_person ?: '-' }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($supplier->phone)
                                        <x-icon-button as="a" icon="whatsapp" href="https://wa.me/{{ $supPhone }}?text={{ rawurlencode($supWaMsg) }}" target="_blank" rel="noopener" title="{{ __('WhatsApp') }}" />
                                    @endif
                                    @if($supplier->email)
                                        <x-icon-button as="a" icon="mail" href="mailto:{{ $supplier->email }}" title="{{ __('Email') }}" />
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @can('cancel', $estimatorRequest)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                    <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Cancel Request') }}</h2>
                    <form action="{{ route('estimator.requests.cancel', $estimatorRequest) }}" method="POST" class="mt-3"
                          data-confirm="{{ __('This request is closed and cannot be resumed.') }}"
                          data-confirm-title="{{ __('Cancel this request?') }}"
                          data-confirm-tone="danger"
                          data-confirm-label="{{ __('Yes, cancel request') }}"
                          data-confirm-details="{{ json_encode([
                              ['label' => __('Customer'), 'value' => $estimatorRequest->customer?->name ?? '-'],
                              ['label' => __('Request'), 'value' => '#'.$estimatorRequest->id],
                          ], JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }}">
                        @csrf
                        <x-icon-button type="submit" icon="delete" label="{{ __('Cancel Request') }}" title="{{ __('Cancel Request') }}" />
                    </form>
                </section>
            @endcan
        </div>
    </div>

    @once
        <script>
            function parseRupiah(value) {
                return Number(String(value || '').replace(/[^0-9]/g, '')) || 0;
            }

            function formatRupiah(value) {
                const number = parseRupiah(value);
                return number ? number.toLocaleString('id-ID') : '';
            }

            function formatRupiahInput(input) {
                const caretFromEnd = input.value.length - (input.selectionStart ?? input.value.length);
                const digits = input.value.replace(/[^0-9]/g, '');
                input.value = digits ? Number(digits).toLocaleString('id-ID') : '';
                const caret = Math.max(0, input.value.length - caretFromEnd);
                input.setSelectionRange(caret, caret);
                recalcEstimatorTotal();
            }

            function setRupiahValue(input, value) {
                if (!input) return;
                input.value = formatRupiah(value);
                recalcEstimatorTotal();
            }

            function recalcEstimatorTotal() {
                const totalEl = document.getElementById('estimator-total-cost');
                if (!totalEl) return;

                let total = 0;
                document.querySelectorAll('[data-estimator-row]').forEach((row) => {
                    const qty = parseFloat(row.dataset.qty || '0') || 0;
                    const priceInput = row.querySelector('input[name$="[price]"]');
                    total += qty * parseRupiah(priceInput?.value);
                });

                totalEl.textContent = 'Rp ' + (total ? total.toLocaleString('id-ID') : '0');
            }

            function toggleStockEta(select) {
                const row = select.closest('[data-estimator-row]');
                if (!row) return;

                const wrapper = row.querySelector('[data-stock-eta]');
                const input = wrapper?.querySelector('input[name$="[stock_eta]"]');
                if (!wrapper || !input) return;

                const isIndent = select.value === 'indent';
                wrapper.classList.toggle('hidden', !isIndent);
                input.disabled = !isIndent;
                if (!isIndent) input.value = '';
            }

            function fillRowField(index, field, value) {
                const el = document.querySelector(`[name="items[${index}][${field}]"]`);
                if (!el) return false;
                el.value = value ?? '';
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
                return true;
            }

            function useLastPrice(index, log) {
                const priceInput = document.querySelector(`[name="items[${index}][price]"]`);
                setRupiahValue(priceInput, log.price);

                if (log.supplier_id) {
                    window.dispatchEvent(new CustomEvent('searchable-select-set', {
                        detail: {
                            name: `items[${index}][supplier_id]`,
                            id: String(log.supplier_id),
                            label: log.supplier_name || '',
                        },
                    }));
                }

                fillRowField(index, 'stock_status', log.stock_status || 'ready');
                const stockSelect = document.querySelector(`[name="items[${index}][stock_status]"]`);
                if (stockSelect) toggleStockEta(stockSelect);

                fillRowField(index, 'stock_eta', log.stock_eta || '');
                fillRowField(index, 'warranty_info', log.warranty_info || '');
                fillRowField(index, 'valid_until', log.valid_until || '');
                recalcEstimatorTotal();

                const row = priceInput?.closest('[data-estimator-row]');
                if (row && row.animate) {
                    row.animate(
                        [
                            { backgroundColor: 'rgba(16, 185, 129, 0.14)' },
                            { backgroundColor: 'transparent' },
                        ],
                        { duration: 800, easing: 'ease-out' },
                    );
                }
            }

            function setKeepDate(index, dateStr) {
                fillRowField(index, 'valid_until', dateStr);
            }

            document.addEventListener('DOMContentLoaded', recalcEstimatorTotal);
            recalcEstimatorTotal();
        </script>
    @endonce
</x-app-layout>
