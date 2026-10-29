<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Create Quotation') }}</h1>
                <p class="text-slate-500 mt-1">{{ $lead->customer?->name ?? 'Lead #'.$lead->id }}</p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('leads.show', $lead) }}" title="{{ __('Back') }}" />
        </div>

        @if($completedTech)
            <div class="mb-4 p-4 rounded-2xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                <p class="text-xs font-semibold text-green-700 dark:text-green-300 uppercase">{{ __('Technical Results Reference') }}</p>
                <p class="text-sm mt-1 whitespace-pre-wrap">{{ $completedTech->technical_result }}</p>
            </div>
        @endif

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
            @if($priceRequest ?? null)
                <div class="mb-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-200">
                    {{ __('This quotation will be linked to Price Request #:id.', ['id' => $priceRequest->id]) }}
                    <a href="{{ route('sales.price-requests.show', $priceRequest) }}" class="font-semibold underline">{{ __('Open price request') }}</a>
                </div>
            @endif

            <form action="{{ route('sales.proposals.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                @csrf
                <input type="hidden" name="lead_id" value="{{ $lead->id }}">
                @if($priceRequest ?? null)
                    <input type="hidden" name="price_request_id" value="{{ $priceRequest->id }}">
                @endif

                <div>
                    <label for="quotation_file" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                        {{ __('Quotation File') }} <span class="text-red-500">*</span>
                    </label>
                    <input id="quotation_file" type="file" name="quotation_file" required
                           accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                    <p class="mt-1 text-xs text-slate-400">{{ __('Upload the quotation file (PDF/Word/Excel/image, max 10MB).') }}</p>
                    @error('quotation_file') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label for="proposal_date" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('Date') }} <span class="text-red-500">*</span></label>
                        <input id="proposal_date" type="date" name="proposal_date" required
                               value="{{ old('proposal_date', now()->format('Y-m-d')) }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                        @error('proposal_date') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="valid_until" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('Valid Until') }}</label>
                        <input id="valid_until" type="date" name="valid_until" value="{{ old('valid_until') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                        @error('valid_until') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="notes" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('Notes') }}</label>
                    <textarea id="notes" name="notes" rows="3"
                              class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">{{ old('notes', $prefillNotes ?? '') }}</textarea>
                    @error('notes') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <a href="{{ route('leads.show', $lead) }}"
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
