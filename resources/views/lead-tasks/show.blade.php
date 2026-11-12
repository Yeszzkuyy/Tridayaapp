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
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ $task->title }}</h1>
            <p class="text-slate-500 mt-1">{{ $task->lead?->customer?->name ?? '-' }}</p>
        </div>
        <div class="flex gap-2">
            @if($canManageTask)
                <x-icon-button as="a" icon="edit" href="{{ route('lead-tasks.edit', $task) }}" title="{{ __('Edit') }}" />
            @endif
            <x-back-button :href="$backUrl" />
        </div>
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

    {{-- Lampiran quotation dari Inside Sales — riwayat versi (tidak menimpa). --}}
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

    @if($task->proposal)
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-5 shadow-sm dark:border-emerald-800/60 dark:bg-emerald-950/20">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">{{ __('Quotation') }}</p>
                    <p class="mt-1 text-lg font-bold text-slate-900 dark:text-white">{{ $task->proposal->proposal_number }}</p>
                    <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-300">
                        {{ __('Status') }}: <span class="font-semibold">{{ ucfirst($task->proposal->status) }}</span>
                        · {{ $task->proposal->created_at?->format('d M Y H:i') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    @if($task->proposal->pdf_path)
                        <x-icon-button as="a" icon="download" label="{{ __('Download PDF') }}"
                                       href="{{ auth()->user()->can('manage-inside-sales') ? route('inside-sales.quotations.pdf', $task) : route('sales.proposals.pdf', $task->proposal) }}"
                                       title="{{ __('Download PDF') }}" />
                    @endif
                </div>
            </div>
        </div>
    @elseif($canGenerateQuotation)
        <div class="mb-6 rounded-2xl border border-accent-200 bg-accent-50/60 p-5 shadow-sm dark:border-accent-800/60 dark:bg-accent-950/20">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('Quotation is ready to generate') }}</p>
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                        {{ $task->items->isEmpty() ? __('Add at least one item first.') : __('All items are filled. Create the quotation and PDF now.') }}
                    </p>
                </div>
                @if($task->items->isNotEmpty())
                    <x-icon-button as="a" variant="solid" icon="sparkles" label="{{ __('Create Quotation') }}" href="{{ route('inside-sales.quotations.create', $task) }}" title="{{ __('Create Quotation') }}" />
                @endif
            </div>
        </div>
    @endif

    {{-- Item quotation dari Inside Sales (dipakai saat file di-Accept). --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 mb-6">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">{{ __('Quotation Items') }}</h3>
            <span class="text-xs text-slate-400">{{ $task->items->count() }} {{ __('item(s)') }}</span>
        </div>

        @if($task->items->isNotEmpty())
            <ul class="mb-4 divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($task->items as $item)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $item->description }}</p>
                            <p class="text-xs text-slate-400">
                                {{ $item->scope_of_supply ?: '-' }} · {{ $item->lead_time ?: '-' }} ·
                                {{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }} {{ $item->unit }}
                                × Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}
                                @if((float) $item->discount > 0)
                                    - Rp {{ number_format((float) $item->discount, 0, ',', '.') }}
                                @endif
                            </p>
                            @if($item->unit_cost !== null && auth()->user()->can('manage-inside-sales'))
                                <p class="text-xs text-slate-400">
                                    {{ __('Cost') }}: Rp {{ number_format((float) $item->unit_cost, 0, ',', '.') }} ·
                                    {{ __('Margin') }}: Rp {{ number_format((float) $item->subtotal - ((float) $item->quantity * (float) $item->unit_cost), 0, ',', '.') }}
                                </p>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="text-sm font-semibold text-slate-800 dark:text-slate-100 tabular-nums">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</span>
                            @if($canManageAttachment)
                                <form action="{{ route('lead-tasks.items.destroy', [$task, $item]) }}" method="POST"
                                      data-confirm="{{ __('Delete this quotation item?') }}" data-confirm-tone="danger">
                                    @csrf
                                    @method('DELETE')
                                    <x-icon-button icon="delete" type="submit" title="{{ __('Delete') }}" />
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            <p class="mb-4 text-right text-sm font-bold text-slate-800 dark:text-slate-100">
                {{ __('Total') }}: Rp {{ number_format((float) $task->items->sum('subtotal'), 0, ',', '.') }}
                @php
                    $itemsWithCost = $task->items->filter(fn ($item) => $item->unit_cost !== null);
                    $totalCost = $itemsWithCost->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_cost);
                    $totalMargin = (float) $task->items->sum('subtotal') - $totalCost;
                @endphp
                @if($itemsWithCost->isNotEmpty() && $itemsWithCost->count() === $task->items->count() && auth()->user()->can('manage-inside-sales'))
                    <span class="block text-xs font-medium text-emerald-600 dark:text-emerald-400">
                        {{ __('Margin') }}: Rp {{ number_format($totalMargin, 0, ',', '.') }}
                    </span>
                @endif
            </p>
        @else
            <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
                {{ __('No quotation items yet. Add at least one item before accepting the file.') }}
            </p>
        @endif

        @if($canManageAttachment)
            <form action="{{ route('lead-tasks.items.store', $task) }}" method="POST"
                  class="grid grid-cols-1 gap-3 border-t border-slate-100 pt-4 dark:border-slate-700 md:grid-cols-12">
                @csrf
                @if(auth()->user()->can('manage-inside-sales'))
                <div class="md:col-span-4">
                    <label for="item-product" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Product') }}</label>
                    <select id="item-product" name="product_id"
                            class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                        <option value="">{{ __('Select product (optional)') }}</option>
                        @foreach(($products ?? collect()) as $product)
                            @php($log = $product->latestPriceLog)
                            <option value="{{ $product->id }}"
                                    data-description="{{ $product->name }}"
                                    data-sku="{{ $product->sku }}"
                                    data-unit="{{ $product->unit ?: 'pcs' }}"
                                    data-price="{{ (float) $product->default_price }}"
                                    data-cost="{{ $log ? (float) $log->price : '' }}"
                                    data-supplier="{{ $log?->supplier?->name }}"
                                    data-date="{{ $log?->created_at?->format('d M Y') }}">
                                {{ $product->name }}{{ $product->sku ? ' · '.$product->sku : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="md:col-span-2">
                    <label for="item-scope" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Scope of Supply') }}</label>
                    <input id="item-scope" type="text" name="scope_of_supply" maxlength="100" placeholder="WAN-RS-..."
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                </div>
                <div class="md:col-span-2">
                    <label for="item-lead" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Estimated Lead Time') }}</label>
                    <input id="item-lead" type="text" name="lead_time" maxlength="100" value="Ready Stock"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                </div>
                <div class="md:col-span-2">
                    <label for="item-section" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Section') }}</label>
                    <input id="item-section" type="text" name="section" maxlength="100" value="MATERIAL"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                </div>
                <div class="md:col-span-1">
                    <label for="item-quantity" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Qty') }}</label>
                    <input id="item-quantity" type="number" name="quantity" required min="0.01" step="0.01" value="1"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                </div>
                <div class="md:col-span-1">
                    <label for="item-unit" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Unit') }}</label>
                    <input id="item-unit" type="text" name="unit" value="pcs" maxlength="16"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                </div>

                <div class="md:col-span-4">
                    <label for="item-description" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Description') }}</label>
                    <input id="item-description" type="text" name="description" required maxlength="255"
                           placeholder="{{ __('Item / product description') }}"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                    @error('description') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="item-price" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Selling Price') }}</label>
                    <input id="item-price" type="number" name="unit_price" required min="0" step="0.01" value="0"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                    @error('unit_price') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                @if(auth()->user()->can('manage-inside-sales'))
                <div class="md:col-span-2">
                    <label for="item-cost" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Cost Price (Estimator)') }}</label>
                    <input id="item-cost" type="number" name="unit_cost" min="0" step="0.01" readonly
                           placeholder="{{ __('From estimator') }}"
                           class="w-full rounded-xl border-slate-300 bg-slate-100 text-sm text-slate-600 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300">
                </div>
                @endif
                <div class="md:col-span-2">
                    <label for="item-discount" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Disc.') }}</label>
                    <input id="item-discount" type="number" name="discount" min="0" step="0.01" value="0"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                </div>
                <div class="flex items-end md:col-span-2">
                    <x-primary-button type="submit" class="w-full justify-center">{{ __('Add') }}</x-primary-button>
                </div>
            </form>

            <p id="item-price-info" class="mt-2 text-xs text-slate-500 dark:text-slate-400"></p>
            <p id="item-margin-preview" class="mt-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400"></p>

            <script>
                (function () {
                    const product = document.getElementById('item-product');
                    if (!product) return;
                    const byId = (id) => document.getElementById(id);
                    const fmt = (value) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(value || 0));
                    const labelMargin = @js(__('Item margin'));
                    const labelNoCost = @js(__('Margin cannot be calculated yet: estimator cost is not available.'));

                    function refreshMargin() {
                        const qty = parseFloat(byId('item-quantity')?.value || 0) || 0;
                        const price = parseFloat(byId('item-price')?.value || 0) || 0;
                        const cost = parseFloat(byId('item-cost')?.value || 0) || 0;
                        const discount = parseFloat(byId('item-discount')?.value || 0) || 0;
                        const target = byId('item-margin-preview');
                        if (!target) return;
                        target.textContent = cost > 0
                            ? labelMargin + ': ' + fmt((qty * price - discount) - (qty * cost))
                            : labelNoCost;
                    }

                    product.addEventListener('change', function () {
                        const option = product.selectedOptions[0];
                        if (!option || !option.value) return;

                        byId('item-description').value = option.dataset.description || '';
                        byId('item-scope').value = option.dataset.sku || '';
                        byId('item-unit').value = option.dataset.unit || 'pcs';
                        byId('item-price').value = option.dataset.price || 0;
                        byId('item-cost').value = option.dataset.cost || '';
                        byId('item-lead').value = 'Ready Stock';
                        byId('item-section').value = 'MATERIAL';

                        const info = byId('item-price-info');
                        if (info) {
                            info.textContent = option.dataset.cost
                                ? @js(__('Latest estimator price')) + ': ' + fmt(option.dataset.cost)
                                    + (option.dataset.supplier ? ' · ' + option.dataset.supplier : '')
                                    + (option.dataset.date ? ' · ' + option.dataset.date : '')
                                : @js(__('No estimator price for this product yet.'));
                        }
                        refreshMargin();
                    });

                    ['item-quantity', 'item-price', 'item-cost', 'item-discount'].forEach(function (id) {
                        byId(id)?.addEventListener('input', refreshMargin);
                    });
                    refreshMargin();
                })();
            </script>
        @endif

    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 mb-6">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">{{ __('Supporting Attachments (Optional)') }}</h3>
            <span class="text-xs text-slate-400">{{ $attachmentTotal }} {{ __('file(s)') }} · {{ __('Latest first') }}</span>
        </div>

        @if($attachmentTotal > 0)
            <ul class="mb-4 space-y-2">
                @foreach($task->attachments as $attachment)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-600 dark:bg-slate-700/40">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $attachment->original_name }}</p>
                            <p class="text-xs text-slate-400">
                                v{{ $attachmentTotal - $loop->index }} · {{ $attachment->humanSize() }}
                                · {{ $attachment->uploader?->name ?? __('System') }}
                                · {{ $attachment->created_at?->translatedFormat('d M Y H:i') }}
                            </p>
                            @if($attachment->accepted_at && $attachment->proposal)
                                <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">
                                    ✓ {{ __('Accepted') }} {{ $attachment->accepted_at->translatedFormat('d M Y H:i') }} ·
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
            <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ __('No quotation file uploaded yet.') }}</p>
        @endif

        @if($canManageAttachment)
            <form action="{{ route('lead-tasks.attachments.store', $task) }}" method="POST" enctype="multipart/form-data"
                  class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-700">
                @csrf
                <div class="min-w-0 flex-1">
                    <label for="quotation-attachment" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">
                        {{ __('Upload supporting file (optional, PDF/Word/Excel/image, max 10MB)') }}
                    </label>
                    <input id="quotation-attachment" type="file" name="attachment" required
                           accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                    @error('attachment') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <button type="submit"
                        class="group relative overflow-hidden rounded-xl bg-accent-600 px-6 py-2.5 text-sm font-medium text-white transition-all duration-300 hover:bg-accent-700 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                    {{ __('Save') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </button>
            </form>
        @endif
    </div>

    <livewire:lead-task-chat :task="$task" />
</x-app-layout>
