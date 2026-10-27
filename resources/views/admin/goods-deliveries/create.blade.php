<x-app-layout>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Create Delivery Note') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Record goods sent to the customer for a project.') }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('admin.goods-deliveries.index') }}" title="{{ __('Back') }}" />
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 mb-5">
        <form method="GET" action="{{ route('admin.goods-deliveries.create') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-72 flex-1">
                <label for="project_id" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Project') }}</label>
                <select id="project_id" name="project_id" required onchange="this.form.submit()"
                        class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                    <option value="">{{ __('-- Select project --') }}</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" @selected($selectedProjectId === $project->id)>{{ $project->project_name }} · {{ $project->customer?->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    @if($selectedProject)
        <form action="{{ route('admin.goods-deliveries.store') }}" method="POST"
              class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-6">
            @csrf
            <input type="hidden" name="project_id" value="{{ $selectedProject->id }}">

            @if($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm dark:bg-red-900/30 dark:border-red-800 dark:text-red-200">{{ $errors->first() }}</div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Delivery Date') }} <span class="text-red-500">*</span></label>
                    <x-datepicker name="delivery_date" required value="{{ old('delivery_date', date('Y-m-d')) }}" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Recipient Name') }}</label>
                    <input type="text" name="recipient_name" value="{{ old('recipient_name') }}" maxlength="150"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                </div>
                <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 px-4 py-2 text-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Customer') }}</p>
                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $selectedProject->customer?->name }}</p>
                </div>
            </div>

            <div>
                <h2 class="font-semibold text-slate-800 dark:text-slate-100 mb-2">{{ __('Delivery Items') }}</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                        <thead>
                            <tr>
                                <th class="px-2 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Product (optional)') }}</th>
                                <th class="px-2 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Item Name') }}</th>
                                <th class="px-2 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Qty') }}</th>
                                <th class="px-2 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Unit') }}</th>
                                <th class="px-2 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Serial Numbers') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @for($i = 0; $i < 4; $i++)
                                <tr>
                                    <td class="px-2 py-2">
                                        <select name="items[{{ $i }}][product_id]"
                                                class="w-48 rounded-xl border-slate-300 text-xs focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                                            <option value="">{{ __('-- none --') }}</option>
                                            @foreach($products as $product)
                                                <option value="{{ $product->id }}" @selected(old("items.{$i}.product_id") == $product->id)>{{ $product->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-2 py-2">
                                        <input type="text" name="items[{{ $i }}][item_name]" value="{{ old("items.{$i}.item_name") }}" maxlength="255"
                                               class="w-52 rounded-xl border-slate-300 text-xs focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                                    </td>
                                    <td class="px-2 py-2">
                                        <input type="number" step="0.01" min="0" name="items[{{ $i }}][quantity]" value="{{ old("items.{$i}.quantity") }}"
                                               class="w-24 rounded-xl border-slate-300 text-xs focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                                    </td>
                                    <td class="px-2 py-2">
                                        <input type="text" name="items[{{ $i }}][unit]" value="{{ old("items.{$i}.unit", 'pcs') }}" maxlength="16"
                                               class="w-20 rounded-xl border-slate-300 text-xs focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                                    </td>
                                    <td class="px-2 py-2">
                                        <input type="text" name="items[{{ $i }}][serial_numbers_text]" value="{{ old("items.{$i}.serial_numbers_text") }}"
                                               placeholder="{{ __('Comma separated') }}"
                                               class="w-56 rounded-xl border-slate-300 text-xs focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                                    </td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
                <p class="mt-1 text-xs text-slate-400">{{ __('Fill at least one row. Empty rows are ignored.') }}</p>
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
                <a href="{{ route('admin.goods-deliveries.index') }}"
                   class="group relative overflow-hidden rounded-xl bg-accent-500 hover:bg-accent-600 text-white px-6 py-2.5 text-sm font-medium text-center transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                    {{ __('Cancel') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </a>
            </div>
        </form>
    @else
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-8 text-center text-slate-400">
            {{ __('Select a project to create a delivery note.') }}
        </div>
    @endif
</x-app-layout>
