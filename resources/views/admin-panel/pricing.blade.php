<x-app-layout>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('Tax & Margin') }}</h1>
        <x-back-button :href="route('admin-panel.index')" />
    </div>

    <form method="POST" action="{{ route('admin-panel.pricing.update') }}"
          class="max-w-2xl rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label for="vat_rate" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('VAT Rate (%)') }}</label>
                <input id="vat_rate" name="vat_rate" type="number" min="0" max="100" step="0.01" required
                       value="{{ old('vat_rate', $vatRate) }}"
                       class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                @error('vat_rate') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                <p class="mt-1 text-[11px] text-slate-400">{{ __('Used for new quotations. Each quotation keeps the rate it was created with.') }}</p>
            </div>

            <div>
                <label for="default_margin_percent" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Default Margin (%)') }}</label>
                <input id="default_margin_percent" name="default_margin_percent" type="number" min="0" max="99.99" step="0.01" required
                       value="{{ old('default_margin_percent', $defaultMargin) }}"
                       class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                @error('default_margin_percent') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                <p class="mt-1 text-[11px] text-slate-400">{{ __('Inherited by new quotation items. Each item can still be changed.') }}</p>
            </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-3">
            <x-secondary-button as="a" :href="route('admin-panel.index')">{{ __('Cancel') }}</x-secondary-button>
            <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
        </div>
    </form>
</x-app-layout>
