<x-app-layout>
    @if(session('success') && session('success_card'))
        <x-status-card :message="session('success')" />
    @endif

    @php
        $subtotal = (float) $items->sum('subtotal');
        $cost = $items->filter(fn ($item) => $item->unit_cost !== null)
            ->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_cost);
        $allCostsKnown = $items->isNotEmpty() && $items->every(fn ($item) => $item->unit_cost !== null);
        $margin = $subtotal - $cost;
        $vat = round($subtotal * 0.12, 2);
        $total = $subtotal + $vat;
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('Create Quotation') }}</h1>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $task->title }} · {{ $task->lead?->customer?->name ?? '-' }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <form action="{{ route('inside-sales.quotations.pull-prices', $task) }}" method="POST" data-loading-text="{{ __('Pulling…') }}">
                @csrf
                <x-icon-button type="submit" icon="reset" label="{{ __('Pull Latest Price') }}" title="{{ __('Pull Latest Price') }}" />
            </form>
            <x-back-button :href="route('lead-tasks.show', $task)" />
        </div>
    </div>

    @if($errors->any())
        <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300">
            {{ __('Please check the quotation data below.') }}
        </div>
    @endif

    <form action="{{ route('inside-sales.quotations.store', $task) }}" method="POST" class="space-y-6">
        @csrf

        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Quotation Data') }}</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="proposal_number" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('No. Quotation') }} <span class="text-rose-500">*</span></label>
                    <input id="proposal_number" type="text" name="proposal_number" required maxlength="100"
                           value="{{ old('proposal_number', $prefill['proposal_number']) }}" placeholder="217R6-FE-WANI-2026"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                    @error('proposal_number') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="proposal_date" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Date') }} <span class="text-rose-500">*</span></label>
                    <input id="proposal_date" type="date" name="proposal_date" required
                           value="{{ old('proposal_date', $prefill['proposal_date']) }}"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                    @error('proposal_date') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="customer" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Organization') }} <span class="text-rose-500">*</span></label>
                    <input id="customer" type="text" name="customer" required maxlength="255"
                           value="{{ old('customer', $prefill['customer']) }}"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                    @error('customer') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="contact_name" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Contact') }}</label>
                    <input id="contact_name" type="text" name="contact_name" maxlength="255"
                           value="{{ old('contact_name', $prefill['contact_name']) }}"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                </div>

                <div>
                    <label for="contact_email" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('E-mail') }}</label>
                    <input id="contact_email" type="email" name="contact_email" maxlength="255"
                           value="{{ old('contact_email', $prefill['contact_email']) }}"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                    @error('contact_email') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="contact_phone" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Phone') }}</label>
                    <input id="contact_phone" type="text" name="contact_phone" maxlength="50"
                           value="{{ old('contact_phone', $prefill['contact_phone']) }}"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                </div>

                <div class="md:col-span-2">
                    <label for="customer_address" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Address') }}</label>
                    <textarea id="customer_address" name="customer_address" rows="2" maxlength="2000"
                              class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">{{ old('customer_address', $prefill['customer_address']) }}</textarea>
                </div>

                <div class="md:col-span-2">
                    <label for="project_description" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Project Description') }}</label>
                    <textarea id="project_description" name="project_description" rows="3" maxlength="3000"
                              class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">{{ old('project_description', $prefill['project_description']) }}</textarea>
                </div>

                <div>
                    <label for="payment_terms_text" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Payment') }}</label>
                    <input id="payment_terms_text" type="text" name="payment_terms_text" maxlength="255"
                           value="{{ old('payment_terms_text', $prefill['payment_terms_text']) }}" placeholder="TT in Advance"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                </div>
                <div>
                    <label for="delivery_terms" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Delivery Terms') }}</label>
                    <input id="delivery_terms" type="text" name="delivery_terms" maxlength="255"
                           value="{{ old('delivery_terms', $prefill['delivery_terms']) }}" placeholder="{{ __('Ready stock / indent estimate') }}"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                </div>
                <div>
                    <label for="warranty_terms" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Warranty Terms') }}</label>
                    <input id="warranty_terms" type="text" name="warranty_terms" maxlength="255"
                           value="{{ old('warranty_terms', $prefill['warranty_terms']) }}" placeholder="{{ __('Warranty from distributor') }}"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                </div>
                <div>
                    <label for="letterhead_pt" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Company / Kop') }} <span class="text-rose-500">*</span></label>
                    <select id="letterhead_pt" name="letterhead_pt" required
                            class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                        @foreach($companies as $pt)
                            <option value="{{ $pt }}" @selected(old('letterhead_pt', $prefill['letterhead_pt']) === $pt)>{{ $pt }} - {{ config('companies.'.$pt.'.name') }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-700/60">
                <h2 class="font-semibold text-slate-700 dark:text-slate-200">{{ __('Quotation Items') }}</h2>
                <a href="{{ route('lead-tasks.show', $task) }}" class="text-xs font-semibold text-accent-600 hover:underline dark:text-accent-400">{{ __('Edit items on task') }}</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:bg-slate-900/40">
                        <tr>
                            <th class="px-5 py-3">{{ __('Scope of Supply') }}</th>
                            <th class="px-5 py-3">{{ __('Description') }}</th>
                            <th class="px-5 py-3">{{ __('Lead Time') }}</th>
                            <th class="px-5 py-3 text-right">{{ __('Qty') }}</th>
                            <th class="px-5 py-3">{{ __('Unit') }}</th>
                            <th class="px-5 py-3 text-right">{{ __('Pricelist') }}</th>
                            <th class="px-5 py-3 text-right">{{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($items as $item)
                            <tr>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $item->scope_of_supply ?: ($item->product?->sku ?: '-') }}</td>
                                <td class="px-5 py-3 text-slate-800 dark:text-slate-100">
                                    {{ $item->description }}
                                    @if($item->stock_status || $item->warranty_info)
                                        <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-slate-400">
                                            @if($item->stock_status)
                                                <span>{{ __('Stock') }}: {{ $item->stock_status === 'indent' ? __('Indent') : __('Ready') }}{{ $item->stock_status === 'indent' && $item->stock_eta ? ' '.$item->stock_eta : '' }}</span>
                                            @endif
                                            @if($item->warranty_info)
                                                <span>{{ __('Warranty') }}: {{ $item->warranty_info }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $item->lead_time ?: '-' }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="px-5 py-3">{{ $item->unit }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                                <td class="px-5 py-3 text-right font-semibold tabular-nums">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="grid grid-cols-1 gap-3 border-t border-slate-100 px-5 py-4 dark:border-slate-700/60 sm:grid-cols-2">
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    <p>{{ __('Cost') }}: Rp {{ number_format($cost, 0, ',', '.') }}</p>
                    <p class="mt-1 font-semibold {{ $allCostsKnown ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                        {{ __('Margin') }}: {{ $allCostsKnown ? 'Rp '.number_format($margin, 0, ',', '.') : __('Cost price incomplete') }}
                    </p>
                    <p class="mt-2">{{ __('VAT 12% is calculated automatically.') }}</p>
                </div>
                <div class="text-right text-sm">
                    <p class="text-slate-500 dark:text-slate-400">{{ __('Total Unit') }}: <span class="font-semibold text-slate-800 dark:text-slate-100">Rp {{ number_format($subtotal, 0, ',', '.') }}</span></p>
                    <p class="mt-1 text-slate-500 dark:text-slate-400">{{ __('VAT 12%') }}: <span class="font-semibold text-slate-800 dark:text-slate-100">Rp {{ number_format($vat, 0, ',', '.') }}</span></p>
                    <p class="mt-2 text-lg font-extrabold text-slate-900 dark:text-white">Rp {{ number_format($total, 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <x-secondary-button as="a" :href="route('lead-tasks.show', $task)">{{ __('Cancel') }}</x-secondary-button>
            <x-primary-button type="submit">{{ __('Generate Quotation') }}</x-primary-button>
        </div>
    </form>
</x-app-layout>
