<x-app-layout>
    @php
        $backUser = auth()->user();
        $backUrl = $backUser?->can('manage-inside-sales') && (int) $task->assigned_to === (int) $backUser->id
            ? route('inside-sales.dashboard')
            : (($backUser?->can('view-sales') || $backUser?->can('manage-sales-leads'))
                ? route('sales.requests.index', ['tab' => 'inside-sales'])
                : route('lead-tasks.index'));
        $canManageTask = $backUser && (
            (int) $task->assigned_to === (int) $backUser->id
            || (int) $task->created_by === (int) $backUser->id
            || $backUser->can('manage-sales-leads')
        );
    @endphp
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ $task->title }}</h1>
            <p class="text-slate-500 mt-1">
                {{ $task->lead?->customer?->name ?? '-' }}
                · {{ __('Requested by') }} {{ $task->creator?->name ?? '-' }}
                · {{ $task->attachments->count() }} {{ __('Attachments') }}
            </p>
        </div>
        <div class="flex gap-2">
            @if($task->proposal || $task->items->isEmpty())
                <x-task-complete-button :task="$task" />
            @endif
            @if($canManageTask)
                <x-icon-button as="a" icon="edit" href="{{ route('lead-tasks.edit', $task) }}" title="{{ __('Edit') }}" />
            @endif
            <x-back-button :href="$backUrl" />
        </div>
    </div>

    @php
        $hasItems = $task->items->isNotEmpty();
        $hasProposal = (bool) $task->proposal;
        $isDone = $task->status === 'done';
        $stepTone = fn (bool $done, bool $active) => $done
            ? 'bg-emerald-500/10 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
            : ($active
                ? 'bg-amber-500/10 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'
                : 'bg-slate-100 text-slate-500 dark:bg-slate-700/60 dark:text-slate-300');
    @endphp
    <div class="mb-6 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90">
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
            <span class="rounded-full px-3 py-1.5 {{ $stepTone(true, false) }}">1. {{ __('Read the Sales request') }}</span>
            <span class="rounded-full px-3 py-1.5 {{ $stepTone($hasItems, ! $hasItems) }}">2. {{ __('Add quotation items') }}</span>
            <span class="rounded-full px-3 py-1.5 {{ $stepTone($hasProposal, $hasItems && ! $hasProposal) }}">3. {{ __('Create quotation') }}</span>
            <span class="rounded-full px-3 py-1.5 {{ $stepTone($isDone, $hasProposal && ! $isDone) }}">4. {{ __('Done') }}</span>
        </div>
        <p class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
            {{ __('Sales brief, attachments, and discussion are on this page.') }}
        </p>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 mb-6">
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <dt class="text-xs text-slate-400">{{ __('Status') }}</dt>
                <dd class="font-medium text-slate-800 dark:text-slate-100">{{ \App\Models\LeadTask::statusLabel($task->status) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">{{ __('Priority') }}</dt>
                <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $task->priority ? ucfirst($task->priority) : '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">{{ __('Assign to') }}</dt>
                <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $task->assignee?->name ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">{{ __('Due Date') }}</dt>
                <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $task->due_date ? $task->due_date->format('d M Y') : '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">{{ __('Created by') }}</dt>
                <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $task->creator?->name ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">{{ __('Lead') }}</dt>
                <dd class="font-medium text-slate-800 dark:text-slate-100">
                    <a href="{{ route('leads.show', $task->lead) }}" class="text-accent-600 hover:underline">
                        {{ $task->lead?->customer?->name ?? '-' }}
                    </a>
                </dd>
            </div>
        </dl>

        <div>
            <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">{{ __('Description') }}</h3>
            <p class="text-slate-700 dark:text-slate-200 whitespace-pre-wrap">{{ $task->description ?: '-' }}</p>
        </div>

        @if($canManageTask)
            <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-700">
                <form action="{{ route('lead-tasks.update', $task) }}" method="POST" class="flex flex-wrap items-end gap-3">
                    @csrf @method('PUT')
                    <input type="hidden" name="title" value="{{ $task->title }}">
                    <input type="hidden" name="assigned_to" value="{{ $task->assigned_to }}">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Change Status') }}</label>
                        <select name="status"
                                class="rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                            @foreach(\App\Models\LeadTask::STATUSES as $status)
                                <option value="{{ $status }}" @selected($task->status === $status)>
                                    {{ \App\Models\LeadTask::statusLabel($status) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit"
                            class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Save Status') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                </form>
            </div>
        @endif
    </div>

    {{-- Hak akses: item & file quotation hanya untuk assignee Inside Sales / management. --}}
    @php
        $attachmentUser = auth()->user();
        // Item hanya boleh dikerjakan assignee Inside Sales (bebas batas PT)
        // atau management. Sales pembuat request hanya membaca.
        $isTaskWorker = $attachmentUser && (int) $task->assigned_to === (int) $attachmentUser->id;
        $canManageAttachment = $attachmentUser && (
            $isTaskWorker
            || $attachmentUser->can('manage-sales-leads')
        );
        $attachmentTotal = $task->attachments->count();
        $canGenerateQuotation = $attachmentUser?->can('manage-inside-sales')
            && $isTaskWorker;
    @endphp

    @if(! $task->assigned_to && $attachmentUser && (
        (int) $task->created_by === (int) $attachmentUser->id
        || $attachmentUser->can('manage-sales-leads')
    ))
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50/70 p-4 shadow-sm dark:border-amber-800/60 dark:bg-amber-950/20">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-300">
                    <x-icon name="user" class="h-4 w-4" />
                </span>
                <div>
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('This request has no Inside Sales assignee yet.') }}</p>
                    <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-300">{{ __('Assign an Inside Sales user so the task can be worked on.') }}</p>
                </div>
            </div>
            <x-icon-button as="a" icon="edit" label="{{ __('Assign') }}" href="{{ route('lead-tasks.edit', $task) }}" title="{{ __('Assign') }}" />
        </div>
    @endif

    {{-- Hasil quotation dari Inside Sales: nomor quotation (bila sudah dibuat
         dari item) + file penawaran yang diunggah, versi terbaru di atas. --}}
    <div class="mb-6 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800/60">
            <div class="flex min-w-0 items-center gap-3.5">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $task->proposal ? 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-accent-500/10 text-accent-600 dark:bg-accent-500/15 dark:text-accent-300' }}">
                    <x-icon name="receipt" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('Quotation') }}</h3>
                    @if($task->proposal)
                        <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $task->proposal->proposal_number }}</span>
                            <span class="mx-1 text-slate-300 dark:text-slate-600">·</span>{{ ucfirst($task->proposal->status) }}
                            <span class="mx-1 text-slate-300 dark:text-slate-600">·</span>{{ $task->proposal->created_at?->format('d M Y H:i') }}
                        </p>
                    @elseif($task->items->isEmpty())
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Upload the quotation file, or add items first to create it from the system.') }}</p>
                    @else
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Items are ready. Create the quotation and PDF now.') }}</p>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($task->proposal?->pdf_path)
                    <x-icon-button as="a" icon="download" label="{{ __('Download PDF') }}"
                                   href="{{ auth()->user()->can('manage-inside-sales') ? route('inside-sales.quotations.pdf', $task) : route('sales.proposals.pdf', $task->proposal) }}"
                                   title="{{ __('Download PDF') }}" />
                @endif
                @if($canGenerateQuotation && ! $task->proposal && $task->items->isNotEmpty())
                    {{-- Mode embed + tab baru: halaman create bukan landing menu, jadi shell workspace akan mengembalikannya ke daftar Inside Sales. --}}
                    <x-icon-button as="a" variant="solid" icon="sparkles" label="{{ __('Create Quotation') }}"
                                   href="{{ route('inside-sales.quotations.create', $task) }}?embed=1" target="_blank" rel="noopener"
                                   title="{{ __('Create Quotation') }}" />
                @endif
            </div>
        </div>

        @if($attachmentTotal > 0)
            <ul class="divide-y divide-slate-100 dark:divide-slate-800/60">
                @foreach($task->attachments as $attachment)
                    <li class="flex flex-wrap items-center gap-3 px-4 py-3 transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-700/35">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
                            <x-icon name="file-text" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100" title="{{ $attachment->original_name }}">{{ $attachment->original_name }}</p>
                            <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                                <span class="font-semibold">v{{ $attachmentTotal - $loop->index }}</span>
                                <span class="mx-1 text-slate-300 dark:text-slate-600">·</span>{{ $attachment->humanSize() }}
                                <span class="mx-1 text-slate-300 dark:text-slate-600">·</span>{{ $attachment->uploader?->name ?? __('System') }}
                                <span class="mx-1 text-slate-300 dark:text-slate-600">·</span>{{ $attachment->created_at?->translatedFormat('d M Y H:i') }}
                            </p>
                            @if($attachment->accepted_at && $attachment->proposal)
                                <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-400">
                                    {{ __('Accepted') }} {{ $attachment->accepted_at->translatedFormat('d M Y H:i') }} ·
                                    <a href="{{ route('sales.proposals.show', $attachment->proposal) }}" class="font-semibold underline">
                                        {{ $attachment->proposal->proposal_number }}
                                    </a>
                                </p>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <x-file-preview :url="route('lead-tasks.attachments.preview', [$task, $attachment])"
                                            :download="route('lead-tasks.attachments.download', [$task, $attachment])"
                                            :name="$attachment->original_name" />
                            <x-icon-button as="a" icon="download" href="{{ route('lead-tasks.attachments.download', [$task, $attachment]) }}" title="{{ __('Download') }}" />
                            @if($canManageAttachment)
                                <form action="{{ route('lead-tasks.attachments.destroy', [$task, $attachment]) }}" method="POST"
                                      data-confirm="{{ __('Delete this quotation file?') }}" data-confirm-tone="danger">
                                    @csrf
                                    @method('DELETE')
                                    <x-icon-button icon="delete" type="submit" title="{{ __('Delete') }}" />
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400">{{ __('No quotation file uploaded yet.') }}</p>
        @endif

        @if($canManageAttachment)
            <form action="{{ route('lead-tasks.attachments.store', $task) }}" method="POST" enctype="multipart/form-data"
                  class="space-y-3 border-t border-slate-100 px-5 py-5 dark:border-slate-800/60">
                @csrf
                <x-file-drop id="quotation-file" name="attachment" :max="1" :multiple="false"
                             accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv" />
                @error('attachment') <p class="text-xs text-rose-700 dark:text-rose-400">{{ $message }}</p> @enderror
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Each upload becomes the latest version, older files stay in history.') }}</p>
                    <x-icon-button type="submit" icon="upload" label="{{ __('Upload Quotation File') }}" title="{{ __('Upload Quotation File') }}" />
                </div>
            </form>
        @endif
    </div>

    @if($canManageAttachment && auth()->user()->can('manage-inside-sales') && $estimatorRequest && $estimatorRequest->items->isNotEmpty())
        <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-700/60">
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-100">{{ __('Estimator Items') }}</h3>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Request') }} #{{ $estimatorRequest->id }}
                        &middot; {{ \App\Models\EstimatorRequest::statusLabel($estimatorRequest->status) }}
                        &middot; {{ $estimatorRequest->requester?->name ?? '-' }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-icon-button type="button" id="use-all-estimator-items" icon="add" label="{{ __('Use All') }}" title="{{ __('Use All') }}" />
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[12px] font-semibold text-slate-500 dark:bg-slate-700 dark:text-slate-300">
                        {{ $estimatorRequest->items->count() }} {{ __('item(s)') }}
                    </span>
                </div>
            </div>
            <ul class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @foreach($estimatorRequest->items as $estimatorItem)
                    @php
                        $priceLog = $estimatorItem->priceLog;
                        $netCost = $priceLog?->price ?? $estimatorItem->price_before_tax ?? $estimatorItem->price;
                        $canUse = $netCost !== null && $estimatorItem->product_id;
                        $isAdded = in_array($estimatorItem->id, $estimatorRowByItem, true);
                        $payload = [
                            'product_id' => $estimatorItem->product_id,
                            'product_price_log_id' => $priceLog?->id,
                            'description' => $estimatorItem->description,
                            'sku' => $estimatorItem->product?->sku,
                            'quantity' => (float) $estimatorItem->quantity,
                            'unit' => $estimatorItem->unit ?: 'pcs',
                            'unit_cost' => $netCost !== null ? (float) $netCost : null,
                            'target_margin_percent' => (float) $defaultMargin,
                        ];
                    @endphp
                    <li data-estimator-item-row="{{ $estimatorItem->id }}" data-added="{{ $isAdded ? '1' : '0' }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 transition-colors duration-200 {{ $isAdded ? 'bg-accent-500/10 ring-1 ring-accent-500/40' : '' }}">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $estimatorItem->description }}</p>
                            <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                {{ rtrim(rtrim(number_format((float) $estimatorItem->quantity, 2, ',', '.'), '0'), ',') }} {{ $estimatorItem->unit }}
                                &middot; {{ __('Cost') }}:
                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ $netCost !== null ? 'Rp '.number_format((float) $netCost, 0, ',', '.') : __('Price not available yet') }}</span>
                                @if($priceLog?->supplier)
                                    &middot; {{ $priceLog->supplier->name }}
                                @endif
                                @if($priceLog?->valid_until)
                                    &middot; {{ __('Valid until') }} {{ $priceLog->valid_until->format('d M Y') }}
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span data-estimator-item-badge class="{{ $isAdded ? '' : 'hidden' }} rounded-full bg-accent-500/15 px-2 py-0.5 text-[10px] font-semibold text-accent-700 dark:text-accent-300">{{ __('Added') }}</span>
                            @if($canUse)
                                <x-icon-button type="button" icon="add" label="{{ __('Use') }}" title="{{ __('Use') }}"
                                               data-use-estimator-item
                                               data-payload="{{ json_encode($payload, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }}" />
                            @else
                                <span class="text-xs font-medium text-amber-600 dark:text-amber-400">{{ __('Price not available yet') }}</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Item quotation dari Inside Sales: tabel ringkas dengan baris detail. --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-600 dark:bg-slate-800">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-100">{{ __('Quotation Items') }}</h3>
                <span id="quotation-items-count" class="rounded-full bg-slate-100 px-2 py-0.5 text-[12px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $task->items->count() }} {{ __('item(s)') }}</span>
            </div>
            @if($canManageAttachment && auth()->user()->can('manage-inside-sales'))
                <x-icon-button type="button" icon="add" label="{{ __('Add Item') }}" title="{{ __('Add Item') }}" id="add-quotation-item" />
            @endif
        </div>

        @if($canManageAttachment && auth()->user()->can('manage-inside-sales'))
            <div class="hidden" aria-hidden="true">
                <x-glide-select name="_quotation_glide_helper" :options="[]" />
                <x-rupiah-input name="_quotation_price_helper" :value="0" />
            </div>

            @if($errors->any())
                <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-800 dark:bg-rose-950/30 dark:text-rose-300">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('lead-tasks.items.batch', $task) }}" method="POST" id="items-batch-form">
                @csrf
                @method('PUT')

                <div class="rounded-xl border border-slate-200 dark:border-slate-700/60 lg:max-h-[65vh] lg:overflow-auto">
                    <div class="lg:min-w-[1120px]">
                        <div class="sticky top-0 z-10 hidden grid-cols-[28px_28px_minmax(180px,1fr)_110px_142px_80px_136px_108px_176px_56px] items-center gap-x-2 border-b border-slate-200 bg-slate-50 px-3 py-2 text-[12px] font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400 lg:grid">
                            <span aria-hidden="true"></span>
                            <span aria-hidden="true">#</span>
                            <span>{{ __('Product') }}</span>
                            <span class="text-left">{{ __('Qty') }}</span>
                            <span class="text-right" title="{{ __('Cost Price (Estimator)') }}">{{ __('Cost') }}</span>
                            <span class="text-right">{{ __('Margin %') }}</span>
                            <span class="text-right">{{ __('Selling Price') }}</span>
                            <span class="text-right">{{ __('Disc. (Rp)') }}</span>
                            <span class="text-right">{{ __('Subtotal') }}</span>
                            <span aria-hidden="true"></span>
                        </div>

                        <div id="quotation-items-list">
                            @forelse($task->items as $index => $item)
                                @include('lead-tasks.partials.quotation-item-card', [
                                    'index' => $index,
                                    'item' => $item,
                                    'rowNumber' => $loop->iteration,
                                    'estimatorRowId' => $estimatorRowByItem[$item->id] ?? null,
                                    'productOptions' => $productOptions,
                                    'productData' => $productData,
                                    'defaultMargin' => $defaultMargin,
                                ])
                            @empty
                                <div id="quotation-items-empty" class="px-4 py-10 text-center">
                                    <p class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ __('No quotation items yet.') }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Use an estimator item above or add a blank item.') }}</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <template id="quotation-item-template">
                    @include('lead-tasks.partials.quotation-item-card', [
                        'index' => '__INDEX__',
                        'item' => null,
                        'rowNumber' => 1,
                        'estimatorRowId' => null,
                        'productOptions' => $productOptions,
                        'productData' => $productData,
                        'defaultMargin' => $defaultMargin,
                    ])
                </template>

                <div class="sticky bottom-0 z-10 mt-5 -mx-6 -mb-6 flex flex-col gap-3 rounded-b-2xl border-t border-slate-200 bg-white/95 px-6 py-4 backdrop-blur dark:border-slate-700/60 dark:bg-slate-800/95 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                        <p class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Subtotal') }} <span id="items-total-subtotal" class="tabular-nums">Rp 0</span></p>
                        <p id="items-total-margin" class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Margin') }}: -</p>
                        <p id="items-unsaved" class="hidden text-xs font-semibold text-amber-600 dark:text-amber-400">● {{ __('Unsaved changes') }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-primary-button type="submit">{{ __('Save Items') }}</x-primary-button>
                        @if($canGenerateQuotation && ! $task->proposal)
                            <a id="generate-quotation-btn" href="{{ route('inside-sales.quotations.create', $task) }}?embed=1" target="_blank" rel="noopener"
                               class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-accent-500 px-5 text-sm font-medium text-accent-600 transition-all duration-300 hover:bg-accent-600 hover:text-white active:scale-95 {{ $task->items->isEmpty() ? 'pointer-events-none opacity-50' : '' }}"
                               aria-disabled="{{ $task->items->isEmpty() ? 'true' : 'false' }}">
                                <x-icon name="file-text" class="h-4 w-4" stroke-width="3.5" />
                                {{ __('Generate Quotation') }}
                            </a>
                        @endif
                    </div>
                </div>
            </form>

            {{-- Price History modal --}}
            <div id="price-history-modal" class="fixed inset-0 z-[1200] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="price-history-title">
                <div class="absolute inset-0 bg-slate-950/60" data-history-close></div>
                <div class="relative w-full max-w-3xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                        <div class="min-w-0">
                            <h3 id="price-history-title" class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('Price History') }}</h3>
                            <p id="price-history-subtitle" class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400"></p>
                        </div>
                        <button type="button" data-history-close aria-label="{{ __('Close') }}"
                                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl text-slate-400 transition-colors duration-200 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                            <span aria-hidden="true" class="text-lg leading-none">&times;</span>
                        </button>
                    </div>
                    <div id="price-history-body" class="max-h-[60vh] overflow-auto p-4"></div>
                </div>
            </div>

            <script>
                (function () {
                    const byId = (id) => document.getElementById(id);
                    const list = byId('quotation-items-list');
                    if (!list) return;

                    const productOptions = @js($productOptions);
                    const productData = @js($productData);
                    const defaultMargin = @js((float) $defaultMargin);
                    const num = (value) => new Intl.NumberFormat('id-ID').format(Math.round(Number(value) || 0));
                    const fmt = (value) => 'Rp ' + num(value);
                    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
                    }[char]));

                    let itemIndex = {{ $task->items->count() }};
                    let hasUnsaved = false;
                    let activeHistoryCard = null;
                    let historyLogs = {};

                    const cardInput = (card, suffix) => card.querySelector('[name$="[' + suffix + ']"]');
                    const cardQty = (card) => parseFloat(cardInput(card, 'quantity')?.value || 0) || 0;
                    const cardPrice = (card) => window.parseRupiah(cardInput(card, 'unit_price')?.value || 0);
                    const cardCost = (card) => window.parseRupiah(cardInput(card, 'unit_cost')?.value || 0);
                    const cardDiscount = (card) => window.parseRupiah(cardInput(card, 'discount')?.value || 0);
                    const cardTarget = (card) => parseFloat(cardInput(card, 'target_margin_percent')?.value || 0) || 0;

                    const formatPercent = (value) => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 1 });
                    const renumber = () => {
                        list.querySelectorAll('[data-item-card]').forEach((card, index) => {
                            const number = card.querySelector('[data-item-number]');
                            if (number) number.textContent = index + 1;
                        });
                    };
                    const setCost = (card, value) => {
                        const display = card.querySelector('[data-cost-display]');
                        if (!display) return;
                        display.textContent = value === null || value === undefined || value === '' ? '' : fmt(value);
                    };
                    const setDetailOpen = (card, open) => {
                        const detail = card.querySelector('[data-item-detail]');
                        const toggle = card.querySelector('[data-item-toggle]');
                        const chevron = card.querySelector('[data-item-chevron]');
                        if (!detail) return;
                        detail.classList.toggle('hidden', !open);
                        toggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
                        chevron?.classList.toggle('rotate-90', open);
                    };
                    const ensureEmptyState = () => {
                        if (list.querySelectorAll('[data-item-card]').length > 0) {
                            byId('quotation-items-empty')?.remove();
                            return;
                        }
                        if (byId('quotation-items-empty')) return;
                        const empty = document.createElement('div');
                        empty.id = 'quotation-items-empty';
                        empty.className = 'px-4 py-10 text-center';
                        empty.innerHTML = '<p class="text-sm font-medium text-slate-600 dark:text-slate-300">' + @js(__('No quotation items yet.')) + '</p>'
                            + '<p class="mt-1 text-xs text-slate-500 dark:text-slate-400">' + @js(__('Use an estimator item above or add a blank item.')) + '</p>';
                        list.appendChild(empty);
                    };

                    function applyTargetMargin(card) {
                        const cost = cardCost(card);
                        const qty = cardQty(card);
                        const discount = cardDiscount(card);
                        const margin = cardTarget(card);
                        if (cost <= 0 || qty <= 0 || margin <= 0 || margin >= 100) return;

                        const price = Math.ceil((cost / (1 - margin / 100)) + (discount / qty));
                        window.setRupiahValue(cardInput(card, 'unit_price'), price);
                    }

                    function recalcCard(card) {
                        const qty = cardQty(card);
                        const price = cardPrice(card);
                        const cost = cardCost(card);
                        const discount = cardDiscount(card);
                        const subtotal = Math.max(0, (qty * price) - discount);

                        const subtotalEl = card.querySelector('[data-item-subtotal]');
                        if (subtotalEl) subtotalEl.textContent = fmt(subtotal);

                        const marginEl = card.querySelector('[data-item-margin]');
                        if (!marginEl) return;

                        if (cost <= 0 || qty <= 0 || subtotal <= 0) {
                            marginEl.className = 'block text-[12px] font-medium tabular-nums text-slate-500 dark:text-slate-400';
                            marginEl.textContent = @js(__('Margin')) + ': -';
                            return;
                        }

                        const amount = subtotal - (cost * qty);
                        const percent = (amount / subtotal) * 100;
                        marginEl.className = 'block text-[12px] font-medium tabular-nums ' + (amount < 0
                            ? 'text-rose-700 dark:text-rose-400'
                            : 'text-emerald-700 dark:text-emerald-400');
                        marginEl.textContent = @js(__('Margin')) + ': ' + fmt(amount) + ' (' + formatPercent(percent) + '%)';
                    }

                    function recalcTotals() {
                        let subtotal = 0;
                        let cost = 0;
                        let allCostKnown = true;
                        let count = 0;

                        list.querySelectorAll('[data-item-card]').forEach((card) => {
                            count++;
                            const qty = cardQty(card);
                            const price = cardPrice(card);
                            const discount = cardDiscount(card);
                            const unitCost = cardCost(card);

                            subtotal += Math.max(0, (qty * price) - discount);
                            if (unitCost > 0) {
                                cost += qty * unitCost;
                            } else {
                                allCostKnown = false;
                            }
                            recalcCard(card);
                        });

                        const totalEl = byId('items-total-subtotal');
                        if (totalEl) totalEl.textContent = fmt(subtotal);

                        const marginEl = byId('items-total-margin');
                        if (marginEl) {
                            if (!allCostKnown || count === 0 || subtotal <= 0) {
                                marginEl.className = 'text-xs font-medium text-slate-500 dark:text-slate-400';
                                marginEl.textContent = @js(__('Margin')) + ': -';
                            } else {
                                const amount = subtotal - cost;
                                const percent = (amount / subtotal) * 100;
                                marginEl.className = 'text-xs font-semibold ' + (amount < 0
                                    ? 'text-rose-700 dark:text-rose-400'
                                    : 'text-emerald-700 dark:text-emerald-400');
                                marginEl.textContent = @js(__('Margin')) + ': ' + fmt(amount) + ' (' + formatPercent(percent) + '%)';
                            }
                        }

                        const countEl = byId('quotation-items-count');
                        if (countEl) countEl.textContent = count + ' ' + @js(__('item(s)'));

                        const generate = byId('generate-quotation-btn');
                        if (generate) {
                            const disabled = count === 0 || hasUnsaved;
                            generate.classList.toggle('pointer-events-none', disabled);
                            generate.classList.toggle('opacity-50', disabled);
                            generate.setAttribute('aria-disabled', disabled ? 'true' : 'false');
                            if (disabled) {
                                generate.setAttribute('tabindex', '-1');
                            } else {
                                generate.removeAttribute('tabindex');
                            }
                        }
                    }

                    function markDirty() {
                        hasUnsaved = true;
                        byId('items-unsaved')?.classList.remove('hidden');
                        recalcTotals();
                    }

                    function mountProduct(card, value = '') {
                        const mount = card.querySelector('.glide-select-root [data-glide-mount]');
                        if (mount && window.__glideRemount) window.__glideRemount(mount, productOptions, value);
                    }

                    function bindCard(card) {
                        card.querySelectorAll('input, select, textarea').forEach((input) => {
                            input.addEventListener('input', () => {
                                card.classList.remove('bg-rose-50/70', 'dark:bg-rose-950/20');
                                if (input.hasAttribute('data-autofill')) input.removeAttribute('data-autofill');
                                if (input.matches('[name$="[target_margin_percent]"], [name$="[quantity]"], [name$="[discount]"]')) {
                                    applyTargetMargin(card);
                                }
                                markDirty();
                                recalcCard(card);
                                recalcTotals();
                            });
                            input.addEventListener('change', () => markDirty());
                        });

                        const toggle = card.querySelector('[data-item-toggle]');
                        toggle?.addEventListener('click', () => {
                            const detail = card.querySelector('[data-item-detail]');
                            setDetailOpen(card, detail?.classList.contains('hidden') ?? false);
                        });

                        const product = cardInput(card, 'product_id');
                        product?.addEventListener('change', () => {
                            const data = productData[product.value];
                            if (data) {
                                const description = cardInput(card, 'description');
                                if (description && (!description.value.trim() || description.dataset.autofill === '1')) {
                                    description.value = data.description || '';
                                    description.dataset.autofill = '1';
                                }
                                const scope = cardInput(card, 'scope_of_supply');
                                if (scope && (!scope.value.trim() || scope.dataset.autofill === '1')) {
                                    scope.value = data.sku || '';
                                    scope.dataset.autofill = '1';
                                }
                                const unit = cardInput(card, 'unit');
                                if (unit) unit.value = data.unit || 'pcs';
                                loadLatestLog(card, product.value);
                            }
                            markDirty();
                        });
                    }

                    async function loadLatestLog(card, productId) {
                        if (!productId) return;
                        try {
                            const response = await fetch('{{ url('products') }}/' + productId + '/cost-logs', {
                                headers: { 'Accept': 'application/json' },
                            });
                            const logs = await response.json();
                            const log = logs[0] || null;
                            const logInput = cardInput(card, 'product_price_log_id');
                            const costInput = cardInput(card, 'unit_cost');

                            if (logInput) logInput.value = log ? log.id : '';
                            if (costInput) costInput.value = log ? Math.round(Number(log.price) || 0) : '';
                            setCost(card, log ? log.price : '');
                            if (log) applyTargetMargin(card);
                            recalcTotals();
                        } catch {
                            // Diamkan: validasi server tetap menjadi jaring pengaman.
                        }
                    }

                    function addCard(prefill = {}) {
                        const template = byId('quotation-item-template');
                        if (!template) return null;

                        const index = itemIndex++;
                        const wrapper = document.createElement('div');
                        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index)).trim();
                        const card = wrapper.firstElementChild;
                        if (!card) return null;

                        list.appendChild(card);
                        mountProduct(card, prefill.product_id ? String(prefill.product_id) : '');

                        if (prefill.product_id) {
                            const product = cardInput(card, 'product_id');
                            if (product) product.value = String(prefill.product_id);
                        }
                        if (prefill.description) {
                            cardInput(card, 'description').value = prefill.description;
                            cardInput(card, 'description').dataset.autofill = '1';
                        }
                        if (prefill.sku) {
                            cardInput(card, 'scope_of_supply').value = prefill.sku;
                            cardInput(card, 'scope_of_supply').dataset.autofill = '1';
                        }
                        if (prefill.quantity) cardInput(card, 'quantity').value = String(Number(prefill.quantity));
                        if (prefill.unit) cardInput(card, 'unit').value = prefill.unit;
                        if (prefill.target_margin_percent) cardInput(card, 'target_margin_percent').value = String(Number(prefill.target_margin_percent));
                        if (prefill.unit_cost !== null && prefill.unit_cost !== undefined) {
                            cardInput(card, 'unit_cost').value = Math.round(Number(prefill.unit_cost) || 0);
                            setCost(card, prefill.unit_cost);
                        }
                        if (prefill.product_price_log_id) cardInput(card, 'product_price_log_id').value = prefill.product_price_log_id;

                        applyTargetMargin(card);
                        bindCard(card);
                        setDetailOpen(card, true);
                        renumber();
                        markDirty();
                        recalcTotals();
                        ensureEmptyState();
                        card.scrollIntoView({ behavior: 'smooth', block: 'center' });

                        return card;
                    }

                    list.querySelectorAll('[data-item-card]').forEach(bindCard);

                    byId('add-quotation-item')?.addEventListener('click', () => addCard());

                    list.addEventListener('click', (event) => {
                        const remove = event.target.closest('[data-remove-item]');
                        if (remove) {
                            const card = remove.closest('[data-item-card]');
                            const rowId = card?.dataset.estimatorRowId;
                            if (rowId) {
                                const row = document.querySelector('[data-estimator-item-row="' + rowId + '"]');
                                if (row) {
                                    row.dataset.added = '0';
                                    row.classList.remove('ring-1', 'ring-accent-500/40', 'bg-accent-500/10');
                                    row.querySelector('[data-estimator-item-badge]')?.classList.add('hidden');
                                }
                            }
                            card?.remove();
                            renumber();
                            markDirty();
                            recalcTotals();
                            ensureEmptyState();
                            return;
                        }

                        const trigger = event.target.closest('[data-history-trigger]');
                        if (trigger) openHistory(trigger.closest('[data-item-card]'));
                    });

                    document.querySelectorAll('[data-use-estimator-item]').forEach((button) => {
                        button.addEventListener('click', () => {
                            const row = button.closest('[data-estimator-item-row]');
                            if (row?.dataset.added === '1') {
                                const message = @js(__('This item is already in the list.'));
                                if (typeof window.toastVariant === 'function') {
                                    window.toastVariant(message, 'warning');
                                } else {
                                    window.toast?.(message, false);
                                }
                                return;
                            }

                            try {
                                const payload = JSON.parse(button.dataset.payload || '{}');
                                const card = addCard(payload);
                                if (row) {
                                    row.dataset.added = '1';
                                    row.classList.add('ring-1', 'ring-accent-500/40', 'bg-accent-500/10');
                                    row.querySelector('[data-estimator-item-badge]')?.classList.remove('hidden');
                                }
                                if (card && row) card.dataset.estimatorRowId = row.dataset.estimatorItemRow || '';
                            } catch {
                                // Diamkan: payload rusak tidak boleh mematikan form.
                            }
                        });
                    });

                    byId('use-all-estimator-items')?.addEventListener('click', () => {
                        document.querySelectorAll('[data-use-estimator-item]').forEach((button) => button.click());
                    });

                    byId('generate-quotation-btn')?.addEventListener('click', (event) => {
                        if (event.currentTarget.getAttribute('aria-disabled') === 'true') {
                            event.preventDefault();
                        }
                    });

                    const modal = byId('price-history-modal');
                    const modalBody = byId('price-history-body');
                    const modalSubtitle = byId('price-history-subtitle');

                    function closeHistory() {
                        if (!modal) return;
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                        activeHistoryCard = null;
                    }

                    async function openHistory(card) {
                        if (!card) return;
                        const productId = cardInput(card, 'product_id')?.value;
                        if (!productId) {
                            window.toast?.(@js(__('Select a product first.')), false);
                            return;
                        }

                        activeHistoryCard = card;
                        if (modalSubtitle) modalSubtitle.textContent = cardInput(card, 'description')?.value || '';
                        if (modalBody) modalBody.innerHTML = '<p class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">' + @js(__('Loading...')) + '</p>';
                        modal?.classList.remove('hidden');
                        modal?.classList.add('flex');

                        try {
                            const response = await fetch('{{ url('products') }}/' + productId + '/cost-logs', {
                                headers: { 'Accept': 'application/json' },
                            });
                            const logs = await response.json();
                            historyLogs = Object.fromEntries(logs.map((log) => [String(log.id), log]));

                            if (!modalBody) return;
                            if (!logs.length) {
                                modalBody.innerHTML = '<p class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">' + @js(__('No cost price for this product yet.')) + '</p>';
                                return;
                            }

                            const rows = logs.map((log) => {
                                const incl = log.price_entered !== null
                                    ? (log.price_includes_tax
                                        ? log.price_entered
                                        : log.price_entered * (1 + ((log.tax_rate || 0) / 100)))
                                    : null;
                                return '<tr class="border-b border-slate-100 last:border-0 dark:border-slate-700/60">' +
                                    '<td class="whitespace-nowrap px-3 py-2 text-xs text-slate-500 dark:text-slate-400">' + esc(log.date) + '</td>' +
                                    '<td class="px-3 py-2 text-right text-sm font-semibold tabular-nums text-slate-800 dark:text-slate-100">' + fmt(log.price) + '</td>' +
                                    '<td class="px-3 py-2 text-right text-xs tabular-nums text-slate-500 dark:text-slate-400">' + (incl === null ? '-' : fmt(incl)) + '</td>' +
                                    '<td class="px-3 py-2 text-xs text-slate-500 dark:text-slate-400">' + esc(log.supplier || '-') + '</td>' +
                                    '<td class="whitespace-nowrap px-3 py-2 text-xs text-slate-500 dark:text-slate-400">' + esc(log.valid_until || '-') + '</td>' +
                                    '<td class="px-3 py-2 text-right"><button type="button" data-history-select="' + esc(log.id) + '" class="inline-flex h-8 items-center rounded-xl bg-accent-600 px-3 text-xs font-semibold text-white transition hover:bg-accent-500 active:scale-95">' + @js(__('Select')) + '</button></td>' +
                                    '</tr>';
                            }).join('');

                            modalBody.innerHTML = '<div class="overflow-x-auto"><table class="w-full min-w-[640px] text-left"><thead><tr class="border-b border-slate-200 text-[12px] font-semibold uppercase tracking-wider text-slate-400 dark:border-slate-700"><th class="px-3 py-2">' + @js(__('Date')) + '</th><th class="px-3 py-2 text-right">' + @js(__('Cost before VAT')) + '</th><th class="px-3 py-2 text-right">' + @js(__('Supplier price (incl. VAT)')) + '</th><th class="px-3 py-2">' + @js(__('Supplier')) + '</th><th class="px-3 py-2">' + @js(__('Valid Until')) + '</th><th class="px-3 py-2"></th></tr></thead><tbody>' + rows + '</tbody></table></div>';
                        } catch {
                            if (modalBody) modalBody.innerHTML = '<p class="py-8 text-center text-sm text-rose-700 dark:text-rose-400">' + @js(__('Failed to load price history.')) + '</p>';
                        }
                    }

                    modalBody?.addEventListener('click', (event) => {
                        const button = event.target.closest('[data-history-select]');
                        if (!button || !activeHistoryCard) return;
                        const log = historyLogs[String(button.dataset.historySelect)];
                        if (!log) return;

                        cardInput(activeHistoryCard, 'product_price_log_id').value = log.id;
                        cardInput(activeHistoryCard, 'unit_cost').value = Math.round(Number(log.price) || 0);
                        setCost(activeHistoryCard, log.price);
                        applyTargetMargin(activeHistoryCard);
                        markDirty();
                        recalcTotals();
                        closeHistory();
                    });

                    document.querySelectorAll('[data-history-close]').forEach((button) => {
                        button.addEventListener('click', closeHistory);
                    });

                    document.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) closeHistory();
                    });

                    byId('items-batch-form')?.addEventListener('submit', (event) => {
                        let firstInvalid = null;

                        list.querySelectorAll('[data-item-card]').forEach((card) => {
                            const cost = cardCost(card);
                            const qty = cardQty(card);
                            const price = cardPrice(card);
                            const discount = cardDiscount(card);
                            const description = cardInput(card, 'description');
                            const descriptionMissing = description && !description.value.trim();
                            const invalid = (cost > 0 && qty > 0 && (price * qty - discount) < cost * qty)
                                || descriptionMissing;

                            if (invalid) {
                                event.preventDefault();
                                card.classList.add('bg-rose-50/70', 'dark:bg-rose-950/20');
                                if (descriptionMissing) setDetailOpen(card, true);
                                if (!firstInvalid) firstInvalid = card;
                            }
                        });

                        if (firstInvalid) {
                            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            window.toast?.(@js(__('Fix the highlighted items before saving.')), false);
                            return;
                        }

                        byId('items-unsaved')?.classList.add('hidden');
                    });

                    recalcTotals();
                })();
            </script>
        @elseif($task->items->isNotEmpty())
            <ul class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @foreach($task->items as $item)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $item->description }}</p>
                            <p class="text-xs text-slate-400">
                                {{ $item->scope_of_supply ?: '-' }} &middot; {{ $item->lead_time ?: '-' }} &middot;
                                {{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }} {{ $item->unit }}
                                &times; Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}
                                @if((float) $item->discount > 0)
                                    - Rp {{ number_format((float) $item->discount, 0, ',', '.') }}
                                @endif
                            </p>
                        </div>
                        <span class="text-sm font-semibold tabular-nums text-slate-800 dark:text-slate-100">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</span>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No quotation items yet.') }}</p>
        @endif
    </div>

    @include('lead-tasks.partials.sales-attachments')

    <livewire:lead-task-chat :task="$task" />
</x-app-layout>
