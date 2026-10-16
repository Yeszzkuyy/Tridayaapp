<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div><h1 class="text-3xl font-bold text-slate-800">{{ __('Edit PO') }}</h1><p class="text-slate-500 mt-1">{{ $purchaseOrder->po_number }}</p></div>
            <x-icon-button as="a" icon="back" href="{{ route('admin.pos.index') }}" title="{{ __('Kembali') }}" />
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <form action="{{ route('admin.pos.update', $purchaseOrder) }}" method="POST" class="space-y-6">
                @csrf @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Customer') }} <span class="text-red-500">*</span></label>
                    <x-searchable-select
                        name="customer_id"
                        :options="$customers->mapWithKeys(fn ($c) => [$c->id => $c->name])->all()"
                        :selected="old('customer_id', $purchaseOrder->customer_id)"
                        placeholder="{{ __('Cari & pilih customer...') }}"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Proyek Terkait') }}</label>
                    <select name="project_id" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500"
                            x-data="{ customerId: '{{ old('customer_id', $purchaseOrder->customer_id) }}' }"
                            @searchable-select:changed.window="customerId = $event.detail.id; $el.value = ''">
                        <option value="">{{ __('-- Tidak terkait proyek --') }}</option>
                        @foreach($projects as $p) <option value="{{ $p->id }}" data-customer="{{ $p->customer_id }}"
                                x-show="!customerId || customerId == '{{ $p->customer_id }}'"
                                {{ old('project_id', $purchaseOrder->project_id) == $p->id ? 'selected' : '' }}>{{ $p->project_name }}</option> @endforeach
                    </select>
                    @error('project_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Item') }} <span class="text-red-500">*</span></label>
                    <textarea name="items" rows="4" required class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('items', $purchaseOrder->items) }}</textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Nominal (Rp)') }} <span class="text-red-500">*</span></label>
                        <input type="number" name="amount" value="{{ old('amount', $purchaseOrder->amount) }}" required step="0.01" min="0" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Status') }}</label>
                        <select name="status" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                            @foreach($statuses as $val => $label) <option value="{{ $val }}" {{ old('status', $purchaseOrder->status) == $val ? 'selected' : '' }}>{{ $label }}</option> @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tanggal Terbit') }} <span class="text-red-500">*</span></label>
                    <x-datepicker name="issue_date" required value="{{ old('issue_date', $purchaseOrder->issue_date->format('Y-m-d')) }}"></x-datepicker>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Catatan') }}</label>
                    <textarea name="notes" rows="2" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('notes', $purchaseOrder->notes) }}</textarea>
                </div>
                <div class="flex gap-3">
                    <button type="submit"
                            class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Update') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                    <a href="{{ route('admin.pos.index') }}"
                       class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                        {{ __('Batal') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
