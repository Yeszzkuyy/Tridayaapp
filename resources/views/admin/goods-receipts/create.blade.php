<x-app-layout>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Receive Goods') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Select an issued supplier PO, enter the physical quantity received, then verify.') }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('admin.goods-receipts.index') }}" title="{{ __('Back') }}" />
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 mb-5">
        <form method="GET" action="{{ route('admin.goods-receipts.create') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-72 flex-1">
                <label for="supplier_purchase_order_id" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Supplier PO') }}</label>
                <select id="supplier_purchase_order_id" name="supplier_purchase_order_id" required
                        onchange="this.form.submit()"
                        class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                    <option value="">{{ __('-- Select supplier PO --') }}</option>
                    @foreach($orders as $order)
                        <option value="{{ $order->id }}" @selected($selectedId === $order->id)>
                            {{ $order->po_number }} · {{ $order->supplier?->name }} · {{ \App\Models\SupplierPurchaseOrder::statusLabel($order->status) }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    @if($selectedOrder)
        <form action="{{ route('admin.goods-receipts.store') }}" method="POST" enctype="multipart/form-data"
              class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-6">
            @csrf
            <input type="hidden" name="supplier_purchase_order_id" value="{{ $selectedOrder->id }}">

            @if($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm dark:bg-red-900/30 dark:border-red-800 dark:text-red-200">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Received Date') }} <span class="text-red-500">*</span></label>
                    <x-datepicker name="received_date" required value="{{ old('received_date', date('Y-m-d')) }}" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Supplier Delivery Note No.') }}</label>
                    <input type="text" name="supplier_delivery_note_no" value="{{ old('supplier_delivery_note_no') }}" maxlength="100"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Attachment') }}</label>
                    <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                           class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                </div>
            </div>

            <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                {{ __('Supplier') }}: <strong>{{ $selectedOrder->supplier?->name }}</strong>
                @if($selectedOrder->project)
                    · {{ __('Project') }}: <strong>{{ $selectedOrder->project->project_name }}</strong>
                @endif
                · {{ __('PO status') }}: <strong>{{ \App\Models\SupplierPurchaseOrder::statusLabel($selectedOrder->status) }}</strong>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Item') }}</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Ordered') }}</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Received') }}</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Remaining') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Receive Now') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Serial Numbers') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Condition') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($selectedOrder->items as $index => $item)
                            <tr>
                                <td class="px-3 py-3">
                                    <div class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $item->item_name }}</div>
                                    <div class="text-xs text-slate-500">{{ $item->part_number ?? __('Not filled') }}</div>
                                    <input type="hidden" name="items[{{ $index }}][supplier_purchase_order_item_id]" value="{{ $item->id }}">
                                </td>
                                <td class="px-3 py-3 text-center text-sm text-slate-700 dark:text-slate-300">{{ number_format($item->quantity_ordered, 2) }} {{ $item->unit }}</td>
                                <td class="px-3 py-3 text-center text-sm text-slate-700 dark:text-slate-300">{{ number_format($item->quantity_received, 2) }}</td>
                                <td class="px-3 py-3 text-center text-sm font-semibold {{ $item->quantityRemaining() > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400' }}">{{ number_format($item->quantityRemaining(), 2) }}</td>
                                <td class="px-3 py-3">
                                    <input type="number" step="0.01" min="0" max="{{ max(0, $item->quantityRemaining()) }}"
                                           name="items[{{ $index }}][quantity_received]" value="{{ old("items.{$index}.quantity_received", 0) }}"
                                           class="w-28 rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                                </td>
                                <td class="px-3 py-3">
                                    <textarea name="items[{{ $index }}][serial_numbers_text]" rows="1"
                                              placeholder="{{ $item->product?->has_serial_number ? __('Serial numbers, comma separated') : __('Optional') }}"
                                              class="w-56 rounded-xl border-slate-300 text-xs focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">{{ old("items.{$index}.serial_numbers_text") }}</textarea>
                                    @if($item->product?->has_serial_number)
                                        <p class="mt-1 text-[11px] text-amber-600 dark:text-amber-400">{{ __('Serialized item: enter one serial number per received unit.') }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <input type="text" name="items[{{ $index }}][condition_notes]" value="{{ old("items.{$index}.condition_notes") }}" maxlength="255"
                                           placeholder="{{ __('Good / damaged') }}"
                                           class="w-36 rounded-xl border-slate-300 text-xs focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Notes') }}</label>
                <textarea name="notes" rows="2" class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">{{ old('notes') }}</textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit"
                        class="group relative overflow-hidden rounded-xl bg-accent-600 hover:bg-accent-700 text-white px-6 py-2.5 text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                    {{ __('Save') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </button>
                <a href="{{ route('admin.goods-receipts.index') }}"
                   class="group relative overflow-hidden rounded-xl bg-accent-500 hover:bg-accent-600 text-white px-6 py-2.5 text-sm font-medium text-center transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                    {{ __('Cancel') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </a>
            </div>
        </form>
    @else
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-8 text-center text-slate-400">
            {{ __('Select a supplier PO to start receiving goods.') }}
        </div>
    @endif
</x-app-layout>
