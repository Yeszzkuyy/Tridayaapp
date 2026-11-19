<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-white">{{ __('Create Purchase Order') }}</h1>
            </div>
            <x-back-button href="{{ route('admin.pos.index') }}" />
        </div>

        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
            <strong>{{ __('Legacy Customer PO') }}</strong> — {{ __('Legacy Customer PO is read-only. Use the Quotation handoff for new Customer PO.') }}
            <a href="{{ route('admin.purchasing.index') }}" class="font-semibold underline">{{ __('Open Quotation Handoff') }}</a>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
            <form action="{{ route('admin.pos.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Customer') }} <span class="text-red-500">*</span></label>
                    <x-searchable-select
                        name="customer_id"
                        :options="$customers->mapWithKeys(fn ($c) => [$c->id => $c->name])->all()"
                        :selected="old('customer_id', $customerId)"
                        placeholder="{{ __('Search & select customer...') }}"
                    />
                    @error('customer_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Related Project') }}</label>
                    <select name="project_id" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                            x-data="{ customerId: '{{ old('customer_id', $customerId) }}' }"
                            @searchable-select:changed.window="customerId = $event.detail.id; $el.value = ''">
                        <option value="">{{ __('-- Not linked to a project --') }}</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" data-customer="{{ $p->customer_id }}"
                                    x-show="!customerId || customerId == '{{ $p->customer_id }}'"
                                    {{ old('project_id', $projectId ?? null) == $p->id ? 'selected' : '' }}>{{ $p->project_name }}</option>
                        @endforeach
                    </select>
                    @error('project_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Item') }} <span class="text-red-500">*</span></label>
                    <textarea name="items" rows="4" required placeholder="{{ __('Description of the goods/services ordered...') }}"
                              class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">{{ old('items', $itemsText ?? null) }}</textarea>
                    @error('items') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Amount (Rp)') }} <span class="text-red-500">*</span></label>
                        <input type="number" name="amount" value="{{ old('amount') }}" required step="0.01" min="0"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                        @error('amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Status') }}</label>
                        <select name="status" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                            @foreach($statuses as $val => $label)
                                <option value="{{ $val }}" {{ old('status', 'draft') == $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Issue Date') }} <span class="text-red-500">*</span></label>
                    <x-datepicker name="issue_date" required value="{{ old('issue_date', date('Y-m-d')) }}"></x-datepicker>
                    @error('issue_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('PO File') }}</label>
                    <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                    <p class="mt-1 text-xs text-slate-400">{{ __('Optional. The file also appears in the customer Documents tab.') }} · {{ __('Maximum 20MB') }}</p>
                    @error('file') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Notes') }}</label>
                    <textarea name="notes" rows="2"
                              class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">{{ old('notes') }}</textarea>
                    @error('notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-3">
                    <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
                    <x-secondary-button as="a" href="{{ route('admin.pos.index') }}">{{ __('Cancel') }}</x-secondary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
