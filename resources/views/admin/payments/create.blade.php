<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div><h1 class="text-3xl font-bold text-slate-800">{{ __('Catat Pembayaran') }}</h1><p class="text-slate-500 mt-1">{{ __('Catat pembayaran dari invoice.') }}</p></div>
            <x-icon-button as="a" icon="back" href="{{ route('admin.payments.index') }}" title="{{ __('Kembali') }}" />
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <form action="{{ route('admin.payments.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Invoice <span class="text-red-500">*</span></label>
                    <x-searchable-select
                        name="invoice_id"
                        :options="$invoices->mapWithKeys(fn ($i) => [$i->id => $i->invoice_number . ' — ' . ($i->customer?->name ?? '-') . ' (Rp ' . number_format($i->amount, 0, ',', '.') . ', ' . $i->status . ')'])->all()"
                        :selected="old('invoice_id', $invoiceId)"
                        placeholder="{{ __('Cari & pilih invoice...') }}"
                    />
                    @error('invoice_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Nominal (Rp)') }} <span class="text-red-500">*</span></label>
                        <input type="number" name="amount" value="{{ old('amount') }}" required step="0.01" min="0" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @error('amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tanggal Bayar') }} <span class="text-red-500">*</span></label>
                        <x-datepicker name="payment_date" required value="{{ old('payment_date', date('Y-m-d')) }}"></x-datepicker>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Metode Pembayaran') }}</label>
                    <input type="text" name="payment_method" value="{{ old('payment_method') }}" placeholder="{{ __('mis: Transfer Bank, Tunai, dll') }}" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Bukti Transfer (file)') }}</label>
                    <input type="file" name="proof_file" accept="image/*,application/pdf" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    @error('proof_file') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Catatan') }}</label>
                    <textarea name="notes" rows="2" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('notes') }}</textarea>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-green-600 hover:bg-green-700 text-white font-medium transition">{{ __('Simpan Pembayaran') }}</button>
                    <a href="{{ route('admin.payments.index') }}" class="px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white font-medium transition">{{ __('Batal') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
