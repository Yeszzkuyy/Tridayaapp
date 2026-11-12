{{--
    Customer-Centric Hub: daftar customer yang dipegang satu sales.
    Dipakai di dashboard sales pribadi dan halaman drill-down Management.
--}}
@php
    $toneClasses = [
        'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'violet' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
        'indigo' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
        'blue' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        'sky' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
        'orange' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
        'slate' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    ];
@endphp

<div
    x-data="{
        darOpen: false,
        docOpen: false,
        poOpen: false,
        target: { id: null, name: '', leadId: null },
        poTarget: { name: '', quotations: [] },
        poForm: { proposalId: '', poNumber: '', poDate: '{{ now()->toDateString() }}', deliveryDeadline: '', deliveryAddress: '' },
        openDar(data) {
            this.target = { id: data.customerId, name: data.customerName, leadId: data.leadId || null };
            this.darOpen = true;
        },
        openDoc(data) {
            this.target = { id: data.customerId, name: data.customerName, leadId: data.leadId || null };
            this.docOpen = true;
        },
        openPo(data) {
            const quotations = JSON.parse(data.quotations || '[]');
            this.poTarget = { name: data.customerName, quotations };
            this.applyQuotation(quotations[0]?.id || '');
            this.poOpen = true;
        },
        applyQuotation(id) {
            const quotation = this.poTarget.quotations.find((item) => String(item.id) === String(id));
            this.poForm = {
                proposalId: id || '',
                poNumber: quotation?.po_number || '',
                poDate: '{{ now()->toDateString() }}',
                deliveryDeadline: quotation?.delivery_deadline || '',
                deliveryAddress: quotation?.delivery_address || '',
            };
        },
        selectedHasPo() {
            const quotation = this.poTarget.quotations.find((item) => String(item.id) === String(this.poForm.proposalId));
            return Boolean(quotation?.has_po);
        },
    }"
    class="lynk-enter mb-6 rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90"
    style="--d: 360ms"
>
    <div class="flex flex-col gap-3 border-b border-slate-100 p-4 dark:border-slate-800/60 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">{{ __('Customer Hub') }}</h2>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Customers you handle, with the latest update and documents.') }}</p>
        </div>

        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input
                type="search"
                name="q"
                value="{{ $hubSearch }}"
                placeholder="{{ __('Search customer...') }}"
                class="h-10 w-full rounded-xl border-slate-200 bg-white text-sm text-slate-700 placeholder:text-slate-400 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 sm:w-56"
            >
            <select name="stage" class="h-10 rounded-xl border-slate-200 bg-white text-sm text-slate-600 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                <option value="">{{ __('All stages') }}</option>
                @foreach($hubStages as $key => $label)
                    <option value="{{ $key }}" @selected($hubStage === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
            @if($hubSearch !== '' || $hubStage !== '')
                <x-icon-button as="a" icon="reset" :href="$hubResetRoute ?? route('sales.dashboard')" title="{{ __('Reset') }}" />
            @endif
            <x-icon-button as="a" icon="customer" href="{{ route('customers.index', ['status' => 'all']) }}" label="{{ __('See all') }}" title="{{ __('See all my customers') }}" />
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3 font-semibold">{{ __('Customer') }}</th>
                    <th class="px-4 py-3 font-semibold">{{ __('Stage') }}</th>
                    <th class="px-4 py-3 font-semibold">{{ __('Latest Update') }}</th>
                    <th class="px-4 py-3 text-center font-semibold">{{ __('Documents') }}</th>
                    <th class="px-4 py-3 text-right font-semibold">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                @forelse($customerHub as $row)
                    @php($customer = $row['customer'])
                    <tr
                        class="transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-700/35"
                        data-customer-id="{{ $customer->id }}"
                        data-customer-name="{{ $customer->name }}"
                        data-lead-id="{{ $row['lead']?->id }}"
                        data-quotations="{{ e($row['accepted_quotations']->toJson()) }}"
                    >
                        <td class="px-4 py-3">
                            <a href="{{ route('customers.show', $customer) }}" class="font-semibold text-slate-800 hover:text-accent-600 dark:text-slate-100 dark:hover:text-accent-400">
                                {{ $customer->name }}
                            </a>
                            <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $customer->pt_group ?: '-' }}</span>
                                @if($customer->contact_person)
                                    <span>{{ $customer->contact_person }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-bold {{ $toneClasses[$row['tone']] ?? $toneClasses['slate'] }}">
                                {{ $row['stage'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if($row['last_note'])
                                <p class="max-w-xs truncate text-slate-700 dark:text-slate-200" title="{{ $row['last_note'] }}">{{ $row['last_note'] }}</p>
                                <p class="mt-0.5 text-[11px] text-slate-400">{{ $row['last_note_at']?->locale(app()->getLocale())->translatedFormat('d M Y, H:i') }}</p>
                            @else
                                <span class="text-xs italic text-slate-400">{{ __('No update note yet') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center font-bold tabular-nums text-slate-700 dark:text-slate-200">{{ $row['documents'] }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <x-icon-button icon="add" x-on:click="openDar($el.closest('tr').dataset)" title="{{ __('Fill DAR Update') }}" />
                                @can('manage-sales')
                                    @if($row['accepted_quotations']->isNotEmpty())
                                        <x-icon-button icon="receipt" x-on:click="openPo($el.closest('tr').dataset)" title="{{ __('Upload PO') }}" />
                                    @endif
                                @endcan
                                <x-icon-button icon="upload" x-on:click="openDoc($el.closest('tr').dataset)" title="{{ __('Upload Document') }}" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center">
                            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ __('No customers yet') }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ __('Customers will appear here once a lead is assigned to you.') }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal: catat update harian (DAR) --}}
    <div x-show="darOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-slate-900/50" x-on:click="darOpen = false" aria-hidden="true"></div>
        <div class="relative w-full max-w-lg rounded-2xl border border-slate-100 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-800" x-on:keydown.escape.window="darOpen = false">
            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">{{ __('Fill DAR Update') }}</h3>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400" x-text="target.name"></p>

            <form method="POST" action="{{ route('sales.follow-ups.store') }}" class="mt-4 space-y-3">
                @csrf
                <input type="hidden" name="customer_id" :value="target.id">
                <input type="hidden" name="lead_id" :value="target.leadId">

                <div>
                    <label for="hub-dar-type" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Contact Type') }}</label>
                    <select id="hub-dar-type" name="type" class="h-10 w-full rounded-xl border-slate-200 bg-white text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-700 dark:bg-slate-900">
                        <option value="call">{{ __('Call') }}</option>
                        <option value="whatsapp">{{ __('WhatsApp') }}</option>
                        <option value="email">{{ __('Email') }}</option>
                        <option value="note" selected>{{ __('Note') }}</option>
                    </select>
                </div>

                <div>
                    <label for="hub-dar-note" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Progress Note') }}</label>
                    <textarea id="hub-dar-note" name="description" rows="3" required class="w-full rounded-xl border-slate-200 bg-white text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-700 dark:bg-slate-900" placeholder="{{ __('What did you discuss?') }}"></textarea>
                </div>

                <div>
                    <label for="hub-dar-next" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Next Follow Up') }}</label>
                    <input id="hub-dar-next" type="date" name="next_follow_up_date" class="h-10 w-full rounded-xl border-slate-200 bg-white text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-700 dark:bg-slate-900">
                </div>

                <div class="flex items-center justify-end gap-2 pt-1">
                    <x-secondary-button x-on:click="darOpen = false">{{ __('Cancel') }}</x-secondary-button>
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: upload dokumen / PO --}}
    <div x-show="docOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-slate-900/50" x-on:click="docOpen = false" aria-hidden="true"></div>
        <div class="relative w-full max-w-lg rounded-2xl border border-slate-100 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-800" x-on:keydown.escape.window="docOpen = false">
            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">{{ __('Upload Document') }}</h3>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400" x-text="target.name"></p>

            <form method="POST" x-bind:action="'{{ url('/customers') }}/' + target.id + '/documents'" enctype="multipart/form-data" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label for="hub-doc-file" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('File') }}</label>
                    <input id="hub-doc-file" type="file" name="file" required class="block w-full rounded-xl border border-slate-200 bg-white p-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-accent-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white dark:border-slate-700 dark:bg-slate-900">
                    <p class="mt-1 text-[11px] text-slate-400">{{ __('PDF or image, maximum 20 MB.') }}</p>
                </div>

                <div>
                    <label for="hub-doc-notes" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Notes') }}</label>
                    <input id="hub-doc-notes" type="text" name="notes" maxlength="500" class="h-10 w-full rounded-xl border-slate-200 bg-white text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-700 dark:bg-slate-900" placeholder="{{ __('Optional note') }}">
                </div>

                <div class="flex items-center justify-end gap-2 pt-1">
                    <x-secondary-button x-on:click="docOpen = false">{{ __('Cancel') }}</x-secondary-button>
                    <x-primary-button>{{ __('Upload') }}</x-primary-button>
                </div>
            </form>
        </div>
    {{-- Modal: upload PO resmi ke quotation Accepted --}}
    @can('manage-sales')
        <div x-show="poOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-slate-900/50" x-on:click="poOpen = false" aria-hidden="true"></div>
            <div class="relative w-full max-w-lg rounded-2xl border border-slate-100 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-800" x-on:keydown.escape.window="poOpen = false">
                <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">{{ __('Upload Customer PO') }}</h3>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400" x-text="poTarget.name"></p>

                <form method="POST" x-bind:action="'{{ url('/sales/proposals') }}/' + poForm.proposalId + '/po'" enctype="multipart/form-data" class="mt-4 space-y-3">
                    @csrf

                    <div>
                        <label for="hub-po-quotation" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Accepted Quotation') }}</label>
                        <select id="hub-po-quotation" name="proposal_id" required
                                x-model="poForm.proposalId" x-on:change="applyQuotation($event.target.value)"
                                class="h-10 w-full rounded-xl border-slate-200 bg-white text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-700 dark:bg-slate-900">
                            <template x-for="quotation in poTarget.quotations" :key="quotation.id">
                                <option :value="quotation.id" x-text="quotation.number + ' - ' + quotation.total_label"></option>
                            </template>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label for="hub-po-number" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('PO Number') }}</label>
                            <input id="hub-po-number" type="text" name="po_number" maxlength="100" x-model="poForm.poNumber"
                                   class="h-10 w-full rounded-xl border-slate-200 bg-white text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-700 dark:bg-slate-900">
                        </div>
                        <div>
                            <label for="hub-po-date" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('PO Date') }}</label>
                            <input id="hub-po-date" type="date" name="po_date" x-model="poForm.poDate"
                                   class="h-10 w-full rounded-xl border-slate-200 bg-white text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-700 dark:bg-slate-900">
                        </div>
                    </div>

                    <div>
                        <label for="hub-po-deadline" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Delivery Deadline') }}</label>
                        <input id="hub-po-deadline" type="date" name="delivery_deadline" x-model="poForm.deliveryDeadline"
                               class="h-10 w-full rounded-xl border-slate-200 bg-white text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-700 dark:bg-slate-900">
                    </div>

                    <div>
                        <label for="hub-po-address" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Delivery Address') }}</label>
                        <textarea id="hub-po-address" name="delivery_address" rows="2" required maxlength="255" x-model="poForm.deliveryAddress"
                                  class="w-full rounded-xl border-slate-200 bg-white text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-700 dark:bg-slate-900"
                                  placeholder="{{ __('Project delivery address (separate from office billing address).') }}"></textarea>
                    </div>

                    <div>
                        <label for="hub-po-file" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('PO File') }}</label>
                        <input id="hub-po-file" type="file" name="po_file" accept=".pdf,.jpg,.jpeg,.png" x-bind:required="!selectedHasPo()"
                               class="block w-full rounded-xl border border-slate-200 bg-white p-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-accent-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white dark:border-slate-700 dark:bg-slate-900">
                        <p class="mt-1 text-[11px] text-slate-400" x-text="selectedHasPo() ? '{{ __('A PO document already exists. Upload a new file only to replace it.') }}' : '{{ __('PDF or image, maximum 10 MB.') }}'"></p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <x-secondary-button x-on:click="poOpen = false">{{ __('Cancel') }}</x-secondary-button>
                        <x-primary-button>{{ __('Upload PO') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
</div>
