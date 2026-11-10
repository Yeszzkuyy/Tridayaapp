<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-white">{{ __('Record Payment') }}</h1>
            </div>
            <x-back-button href="{{ route('admin.payments.index') }}" />
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6"
             x-data="{
                 remaining: {{ \Illuminate\Support\Js::from($remaining) }},
                 pick(id) {
                     const value = this.remaining[id];
                     if (value !== undefined && this.$refs.amount) {
                         this.$refs.amount.value = value;
                     }
                 }
             }"
             @searchable-select:changed.window="pick($event.detail.id)">
            <form action="{{ route('admin.payments.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Invoice') }} <span class="text-red-500">*</span></label>
                    <x-searchable-select
                        name="invoice_id"
                        :options="$invoiceOptions"
                        :selected="old('invoice_id', $invoiceId)"
                        placeholder="{{ __('Search & select invoice...') }}"
                    />
                    @error('invoice_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Amount (Rp)') }} <span class="text-red-500">*</span></label>
                        <input type="number" name="amount" x-ref="amount" value="{{ old('amount', $defaultAmount) }}" required step="0.01" min="0"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                        <p class="mt-1 text-xs text-slate-400">{{ __('Selecting an invoice fills in the remaining amount automatically.') }}</p>
                        @error('amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Tanggal Bayar') }} <span class="text-red-500">*</span></label>
                        <x-datepicker name="payment_date" required value="{{ old('payment_date', date('Y-m-d')) }}"></x-datepicker>
                        @error('payment_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Payment Method') }}</label>
                    <input type="text" name="payment_method" value="{{ old('payment_method') }}" placeholder="{{ __('e.g. Bank Transfer, Cash, etc.') }}"
                           class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                    @error('payment_method') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Transfer Proof (file)') }}</label>
                    <input type="file" name="proof_file" accept=".jpg,.jpeg,.png,.pdf"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                    <p class="mt-1 text-xs text-slate-400">{{ __('Optional. The proof also appears in the customer Documents tab.') }} · {{ __('Maximum 5MB') }}</p>
                    @error('proof_file') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Notes') }}</label>
                    <textarea name="notes" rows="2"
                              class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">{{ old('notes') }}</textarea>
                    @error('notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-3">
                    <x-primary-button type="submit">{{ __('Save Payment') }}</x-primary-button>
                    <x-secondary-button as="a" href="{{ route('admin.payments.index') }}">{{ __('Cancel') }}</x-secondary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
