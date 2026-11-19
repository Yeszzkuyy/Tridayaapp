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
                    <x-icon-button as="a" variant="solid" icon="sparkles" label="{{ __('Create Quotation') }}"
                                   href="{{ route('inside-sales.quotations.create', $task) }}" title="{{ __('Create Quotation') }}" />
                @endif
            </div>
        </div>

        @if($attachmentTotal > 0)
            <ul class="divide-y divide-slate-100 dark:divide-slate-800/60">
                @foreach($task->attachments as $attachment)
                    <li class="flex flex-wrap items-center gap-3 px-4 py-3 transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-700/35">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
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
                                <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">
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
                @error('attachment') <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Each upload becomes the latest version, older files stay in history.') }}</p>
                    <x-icon-button type="submit" icon="upload" label="{{ __('Upload Quotation File') }}" title="{{ __('Upload Quotation File') }}" />
                </div>
            </form>
        @endif
    </div>

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
                                @php
                                    $marginPercent = \App\Support\Pricing::marginPercent(
                                        (float) $item->unit_price,
                                        (float) $item->unit_cost,
                                        (float) $item->quantity,
                                        (float) $item->discount,
                                    );
                                @endphp
                                <p class="text-xs text-slate-400">
                                    {{ __('Cost') }}: Rp {{ number_format((float) $item->unit_cost, 0, ',', '.') }} ·
                                    {{ __('Margin') }}: Rp {{ number_format((float) $item->subtotal - ((float) $item->quantity * (float) $item->unit_cost), 0, ',', '.') }}
                                    ({{ $marginPercent === null ? '-' : number_format($marginPercent, 2).'%' }})
                                    @if($item->target_margin_percent !== null)
                                        · {{ __('Target') }}: {{ number_format((float) $item->target_margin_percent, 2) }}%
                                    @endif
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
                    @php
                        $netSales = (float) $task->items->sum('subtotal');
                        $totalMarginPercent = $netSales > 0 ? ($totalMargin / $netSales) * 100 : null;
                    @endphp
                    <span class="block text-xs font-medium text-emerald-600 dark:text-emerald-400">
                        {{ __('Margin') }}: Rp {{ number_format($totalMargin, 0, ',', '.') }}
                        ({{ $totalMarginPercent === null ? '-' : number_format($totalMarginPercent, 2).'%' }})
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
                    <label for="item-product" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Product') }}</label>
                    <x-glide-select id="item-product" name="product_id" :options="$productOptions"
                                    :label="__('Product')" :placeholder="__('Select product (optional)')"
                                    size="field" searchable :search-placeholder="__('Search product...')" />
                </div>
                @endif
                <div class="md:col-span-2">
                    <label for="item-scope" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Scope of Supply') }}</label>
                    <input id="item-scope" type="text" name="scope_of_supply" maxlength="100" placeholder="WAN-RS-..."
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                </div>
                <div class="md:col-span-2">
                    <label for="item-lead" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Estimated Lead Time') }}</label>
                    <input id="item-lead" type="text" name="lead_time" maxlength="100" value="Ready Stock"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                </div>
                <div class="md:col-span-2">
                    <label for="item-section" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Section') }}</label>
                    <input id="item-section" type="text" name="section" maxlength="100" value="MATERIAL"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                </div>
                <div class="md:col-span-1">
                    <label for="item-quantity" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Qty') }}</label>
                    <input id="item-quantity" type="number" name="quantity" required min="0.01" step="0.01" value="1"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                </div>
                <div class="md:col-span-1">
                    <label for="item-unit" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Unit') }}</label>
                    <input id="item-unit" type="text" name="unit" value="pcs" maxlength="16"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                </div>

                <div class="md:col-span-4">
                    <label for="item-description" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Description') }}</label>
                    <input id="item-description" type="text" name="description" required maxlength="255"
                           placeholder="{{ __('Item / product description') }}"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                    @error('description') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="item-price" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Selling Price') }}</label>
                    <x-rupiah-input id="item-price" name="unit_price" :value="old('unit_price', 0)" :aria-label="__('Selling Price')" required />
                    @error('unit_price') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                @if(auth()->user()->can('manage-inside-sales'))
                <div class="md:col-span-2">
                    <label for="item-target-margin" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Target Margin (%)') }}</label>
                    <input id="item-target-margin" type="number" name="target_margin_percent" min="0" max="99.99" step="0.01"
                           value="{{ $defaultMargin }}"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ __('Margin is calculated from the selling price before VAT.') }}</p>
                </div>
                <div class="md:col-span-2">
                    <label for="item-cost-display" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Cost Price (Estimator)') }}</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-500 dark:text-slate-300" aria-hidden="true">Rp</span>
                        <input id="item-cost-display" type="text" readonly value="{{ old('unit_cost') !== null && old('unit_cost') !== '' ? number_format((float) old('unit_cost'), 0, ',', '.') : '' }}"
                               placeholder="{{ __('From estimator') }}"
                               class="h-10 w-full rounded-xl border-slate-300 bg-slate-100 pl-9 pr-3 text-right text-sm font-semibold tabular-nums text-slate-600 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300">
                        <input id="item-cost" type="hidden" name="unit_cost" value="{{ old('unit_cost') }}">
                    </div>
                </div>
                @endif
                <div class="md:col-span-2">
                    <label for="item-discount" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Disc.') }}</label>
                    <input id="item-discount" type="number" name="discount" min="0" step="0.01" value="0"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                </div>
                @if(auth()->user()->can('manage-inside-sales'))
                <div class="md:col-span-12">
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Price History') }}</label>
                    <div id="item-cost-history-wrap" class="w-full">
                        <x-glide-select id="item-cost-history" name="product_price_log_id" :options="[]"
                                        :label="__('Price History')" :placeholder="__('Select a product first')" size="field" />
                    </div>
                    <p id="item-cost-history-info" class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400"></p>
                </div>
                <div class="md:col-span-12 flex justify-end">
                    <x-primary-button type="submit">{{ __('Add') }}</x-primary-button>
                </div>
                @else
                <div class="md:col-span-12 flex justify-end">
                    <x-primary-button type="submit">{{ __('Add') }}</x-primary-button>
                </div>
                @endif
            </form>

            @if(auth()->user()->can('manage-inside-sales'))
            <p id="item-price-info" class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400"></p>
            <p id="item-margin-preview" class="mt-1 text-sm font-semibold text-slate-500 dark:text-slate-400"></p>

            <script>
                (function () {
                    const byId = (id) => document.getElementById(id);
                    const product = byId('item-product');
                    if (!product) return;

                    const productData = @js($productData);
                    const fmt = (value) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(value || 0));
                    const labelMargin = @js(__('Item margin'));
                    const labelNoCost = @js(__('Margin cannot be calculated yet: estimator cost is not available.'));

                    let historyLogs = {};
                    const historyMount = document.querySelector('#item-cost-history-wrap [data-glide-mount]');
                    const historyInput = byId('item-cost-history');

                    function marginPercent(price, cost, qty, discount) {
                        const netSales = (price * qty) - (discount || 0);
                        if (qty <= 0 || netSales <= 0) return null;
                        return ((netSales - (cost * qty)) / netSales) * 100;
                    }

                    function refreshMargin() {
                        const qty = parseFloat(byId('item-quantity')?.value || 0) || 0;
                        const price = window.parseRupiah(byId('item-price')?.value || 0);
                        const cost = window.parseRupiah(byId('item-cost')?.value || 0);
                        const discount = window.parseRupiah(byId('item-discount')?.value || 0);
                        const target = byId('item-margin-preview');
                        if (!target) return;

                        if (cost <= 0) {
                            target.className = 'mt-1 text-sm font-semibold text-slate-500 dark:text-slate-400';
                            target.textContent = labelNoCost;
                            return;
                        }

                        const amount = (qty * price - discount) - (qty * cost);
                        const pct = marginPercent(price, cost, qty, discount);
                        const targetMargin = parseFloat(byId('item-target-margin')?.value || 0) || 0;
                        const tone = amount < 0
                            ? 'text-rose-600 dark:text-rose-400'
                            : (targetMargin > 0 && pct !== null && pct < targetMargin
                                ? 'text-amber-600 dark:text-amber-400'
                                : 'text-emerald-600 dark:text-emerald-400');

                        target.className = 'mt-1 text-sm font-semibold ' + tone;
                        target.textContent = labelMargin + ': ' + fmt(amount)
                            + (pct === null ? '' : ' (' + pct.toFixed(2) + '%)');
                    }

                    function applyTargetMargin() {
                        const margin = parseFloat(byId('item-target-margin')?.value || 0) || 0;
                        const cost = window.parseRupiah(byId('item-cost')?.value || 0);
                        const qty = parseFloat(byId('item-quantity')?.value || 0) || 0;
                        const discount = window.parseRupiah(byId('item-discount')?.value || 0);
                        if (cost <= 0 || qty <= 0 || margin <= 0 || margin >= 100) return;

                        const price = Math.ceil((cost / (1 - margin / 100)) + (discount / qty));
                        window.setRupiahValue(byId('item-price'), price);
                        refreshMargin();
                    }

                    function historyLabel(log) {
                        let label = log.date + ' · Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(log.price));
                        if (log.price_includes_tax) label += ' · ' + @js(__('incl. VAT'));
                        return label;
                    }

                    function selectLog(id) {
                        const log = historyLogs[String(id)];
                        if (!log) return;

                        byId('item-cost').value = log.price;
                        byId('item-cost-display').value = window.formatRupiah(log.price);
                        const info = byId('item-cost-history-info');
                        if (info) {
                            info.textContent = @js(__('Cost before VAT')) + ': ' + fmt(log.price)
                                + (log.price_includes_tax && log.price_entered ? ' · ' + @js(__('supplier price')) + ' ' + fmt(log.price_entered) + ' (' + @js(__('including VAT')) + ')' : '')
                                + (log.supplier ? ' · ' + log.supplier : '')
                                + (log.valid_until ? ' · ' + @js(__('valid until')) + ' ' + log.valid_until : '');
                        }

                        if (window.parseRupiah(byId('item-price')?.value || 0) <= 0) {
                            applyTargetMargin();
                        }
                        refreshMargin();
                    }

                    async function loadHistory(productId) {
                        historyLogs = {};
                        if (!productId) {
                            if (historyMount && window.__glideRemount) window.__glideRemount(historyMount, [], '');
                            byId('item-cost').value = '';
                            byId('item-cost-display').value = '';
                            const info = byId('item-cost-history-info');
                            if (info) info.textContent = '';
                            refreshMargin();
                            return;
                        }

                        try {
                            const response = await fetch('{{ url('products') }}/' + productId + '/cost-logs', {
                                headers: { 'Accept': 'application/json' },
                            });
                            const logs = await response.json();
                            historyLogs = Object.fromEntries(logs.map((log) => [String(log.id), log]));

                            const options = logs.map((log) => ({ value: String(log.id), label: historyLabel(log) }));
                            const latest = logs[0] ? String(logs[0].id) : '';
                            if (historyMount && window.__glideRemount) {
                                window.__glideRemount(historyMount, options, latest);
                            }
                            if (latest) {
                                selectLog(latest);
                            } else {
                                byId('item-cost').value = '';
                                byId('item-cost-display').value = '';
                                const info = byId('item-cost-history-info');
                                if (info) info.textContent = @js(__('No cost price for this product yet.'));
                                refreshMargin();
                            }
                        } catch {
                            // Diamkan: validasi server tetap menjadi jaring pengaman.
                        }
                    }

                    product.addEventListener('change', function () {
                        const data = productData[product.value];
                        if (!data) {
                            loadHistory('');
                            return;
                        }

                        byId('item-description').value = data.description || '';
                        byId('item-scope').value = data.sku || '';
                        byId('item-unit').value = data.unit || 'pcs';
                        window.setRupiahValue(byId('item-price'), data.price || 0);
                        byId('item-lead').value = 'Ready Stock';
                        byId('item-section').value = 'MATERIAL';

                        const info = byId('item-price-info');
                        if (info) {
                            info.textContent = data.cost !== null
                                ? @js(__('Latest estimator price')) + ': ' + fmt(data.cost)
                                    + (data.supplier ? ' · ' + data.supplier : '')
                                    + (data.date ? ' · ' + data.date : '')
                                : @js(__('No estimator price for this product yet.'));
                        }

                        loadHistory(product.value);
                    });

                    if (historyInput) {
                        historyInput.addEventListener('change', function () {
                            if (historyInput.value) selectLog(historyInput.value);
                        });
                    }

                    byId('item-target-margin')?.addEventListener('input', applyTargetMargin);
                    ['item-quantity', 'item-price', 'item-cost', 'item-discount'].forEach(function (id) {
                        byId(id)?.addEventListener('input', function () {
                            if (id === 'item-quantity' || id === 'item-discount') {
                                // Qty/diskon mengubah harga jual agar target margin tetap.
                                applyTargetMargin();
                            }
                            refreshMargin();
                        });
                    });
                    refreshMargin();
                })();
            </script>
            @endif
        @endif

    </div>

    @include('lead-tasks.partials.sales-attachments')

    <livewire:lead-task-chat :task="$task" />
</x-app-layout>
