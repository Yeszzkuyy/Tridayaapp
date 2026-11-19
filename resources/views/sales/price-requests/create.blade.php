<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Request Price') }}</h1>
                <p class="text-slate-500 mt-1">{{ __('Attach the quotation you have prepared - Engineering will reply with the installation price.') }}</p>
            </div>
            <x-back-button :href="$backUrl" />
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
            <form action="{{ route('sales.price-requests.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4" data-loading-text="{{ __('Saving…') }}">
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
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Project (optional)') }}</label>
                    <select name="project_id"
                            class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                        <option value="">{{ __('No project yet') }}</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected((string) old('project_id', $presetProjectId) === (string) $project->id)>{{ $project->project_name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-400">{{ __('Select the project if this price request is for an existing project.') }}</p>
                    @error('project_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Quotation File') }} <span class="text-red-500">*</span></label>
                    <input type="file" name="quotation" required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.zip"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                    <p class="mt-1 text-xs text-slate-400">{{ __('PDF quotation - max 10 MB. Excel, Word, or image also allowed.') }}</p>
                    @error('quotation') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Notes (optional)') }}</label>
                    <textarea name="notes" rows="3" placeholder="{{ __('Anything else Engineering should know...') }}"
                              class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">{{ old('notes') }}</textarea>
                    @error('notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <x-secondary-button as="a" :href="$backUrl">{{ __('Cancel') }}</x-secondary-button>
                    <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
