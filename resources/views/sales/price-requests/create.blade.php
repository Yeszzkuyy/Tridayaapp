<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Request Price') }}</h1>
                <p class="text-slate-500 mt-1">{{ __('Attach the quotation you have prepared — Engineering will reply with the installation price.') }}</p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ $backUrl }}" title="Back" />
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
            <form action="{{ route('sales.price-requests.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Customer (PT)') }} <span class="text-red-500">*</span></label>
                    @include('sales._customer-picker', [
                        'name' => 'customer_id',
                        'options' => $customers,
                        'selected' => old('customer_id'),
                        'placeholder' => __('Search customer...'),
                        'required' => true,
                        'hint' => __('Choose the PT the quotation is for.'),
                    ])
                    @error('customer_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Quotation File') }} <span class="text-red-500">*</span></label>
                    <input type="file" name="quotation" required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                    <p class="mt-1 text-xs text-slate-400">{{ __('PDF, Excel, or Word — max 10 MB.') }}</p>
                    @error('quotation') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Notes (optional)') }}</label>
                    <textarea name="notes" rows="3" placeholder="{{ __('Anything else Engineering should know...') }}"
                              class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">{{ old('notes') }}</textarea>
                    @error('notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <a href="{{ $backUrl }}"
                       class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                        {{ __('Cancel') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </a>
                    <button type="submit"
                            class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Save') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
