<x-app-layout>
    @if(session('success') && session('success_card'))
        <x-status-card :message="session('success')" />
    @endif
    @php
        $isTech = auth()->user()?->can('manage-technician');
        $backUrl = $isTech
            ? route('teknisi.requests.index', ['tab' => 'price'])
            : route('sales.requests.index', ['tab' => 'price']);
        $quotationFileName = $priceRequest->quotation_path
            ? preg_replace('/^\d+_/', '', basename($priceRequest->quotation_path))
            : null;
        $replyFileName = $priceRequest->reply_path
            ? preg_replace('/^\d+_/', '', basename($priceRequest->reply_path))
            : null;
        $statusColors = [
            'waiting_lead' => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
            'accepted' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            'assigned' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
            'in_progress' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            'cancelled' => 'bg-slate-200 text-slate-500 dark:bg-slate-600 dark:text-slate-300',
        ];
    @endphp
    <div>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ __('Price Request') }}</h1>
                    <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">#{{ $priceRequest->id }}</span>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusColors[$priceRequest->status] ?? 'bg-slate-200 text-slate-700' }}">{{ \App\Models\PriceRequest::statusLabel($priceRequest->status) }}</span>
                </div>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Customer') }}:
                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $priceRequest->customer?->name ?? '-' }}</span>
                </p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ $backUrl }}" title="{{ __('Back') }}" />
        </div>

        <div class="space-y-4">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6" aria-labelledby="price-request-people-heading">
                <h2 id="price-request-people-heading" class="mb-4 text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Request details') }}</h2>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div class="flex min-w-0 items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-700">
                        <x-user-avatar :user="$priceRequest->requester" size="w-11 h-11" text="text-sm" />
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('Requested by') }}</p>
                            <p class="truncate font-semibold text-slate-800 dark:text-slate-100">{{ $priceRequest->requester?->name ?? '-' }}</p>
                        </div>
                    </div>

                    <div class="flex min-w-0 items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-700">
                        @if($priceRequest->assignee)
                            <x-user-avatar :user="$priceRequest->assignee" size="w-11 h-11" text="text-sm" color="green" />
                        @else
                            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-500 dark:bg-slate-600 dark:text-slate-300" aria-hidden="true">
                                <x-icon name="tools" class="h-5 w-5" />
                            </span>
                        @endif
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('Engineer') }}</p>
                            <p class="truncate font-semibold text-slate-800 dark:text-slate-100">{{ $priceRequest->assignee?->name ?? __('Waiting for engineer') }}</p>
                        </div>
                    </div>
                </div>

                <dl class="mt-5 grid grid-cols-1 gap-4 border-t border-slate-200 pt-4 text-sm dark:border-slate-600 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Created') }}</dt>
                        <dd class="mt-1 font-medium text-slate-800 dark:text-slate-100">{{ $priceRequest->created_at?->format('d M Y, H:i') ?? '-' }}</dd>
                    </div>
                    @if($priceRequest->completed_at)
                        <div>
                            <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Completed') }}</dt>
                            <dd class="mt-1 font-medium text-slate-800 dark:text-slate-100">{{ $priceRequest->completed_at->format('d M Y, H:i') }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            @if($priceRequest->notes)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6" aria-labelledby="price-request-notes-heading">
                    <h2 id="price-request-notes-heading" class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Notes') }}</h2>
                    <p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $priceRequest->notes }}</p>
                </section>
            @endif

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6" aria-labelledby="price-request-attachments-heading">
                    <h2 id="price-request-attachments-heading" class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Attachments') }}</h2>
                    <div class="mt-4 rounded-xl border border-slate-200 p-4 dark:border-slate-600">
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Quotation File') }}</p>
                        @if($priceRequest->quotation_path)
                            <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <p class="min-w-0 break-all text-sm font-medium text-slate-800 dark:text-slate-100">{{ $quotationFileName }}</p>
                                <x-icon-button as="a" icon="download" href="{{ route('sales.price-requests.quotation', $priceRequest) }}" label="{{ __('Download Quotation') }}" title="{{ __('Download Quotation') }}" />
                            </div>
                        @else
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('No quotation file attached.') }}</p>
                        @endif
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6" aria-labelledby="price-request-reply-heading">
                    <h2 id="price-request-reply-heading" class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Engineering Reply') }}</h2>
                    @if($priceRequest->reply_notes || $priceRequest->reply_path)
                        <div class="mt-4 space-y-3 rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
                            @if($priceRequest->reply_notes)
                                <p class="whitespace-pre-wrap text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $priceRequest->reply_notes }}</p>
                            @endif
                            @if($priceRequest->reply_path)
                                <div class="flex flex-col gap-3 border-t border-green-200 pt-3 dark:border-green-800 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-green-800 dark:text-green-300">{{ __('Reply File (calculation / price)') }}</p>
                                        <p class="mt-1 break-all text-sm font-medium text-slate-800 dark:text-slate-100">{{ $replyFileName }}</p>
                                    </div>
                                    <x-icon-button as="a" icon="download" href="{{ route('sales.price-requests.reply', $priceRequest) }}" label="{{ __('Download Reply') }}" title="{{ __('Download Reply') }}" />
                                </div>
                            @endif

                            {{-- Handover ke Quotation untuk Sales --}}
                            @can('view-sales')
                                @php
                                    $customerLead = $priceRequest->customer?->leads()->whereNotIn('status', ['won', 'lost'])->latest()->first()
                                        ?? $priceRequest->customer?->leads()->latest()->first();
                                    $createQuotationUrl = $customerLead
                                        ? route('sales.proposals.create', ['lead_id' => $customerLead->id])
                                        : route('sales.proposals.create', ['q' => $priceRequest->customer?->name]);
                                @endphp
                                <div class="flex flex-col gap-3 border-t border-green-200 pt-3 dark:border-green-800 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-xs font-semibold text-green-900 dark:text-green-200">{{ __('Langkah Selanjutnya: Buat Quotation') }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Gunakan hasil kalkulasi harga engineer di atas untuk menerbitkan penawaran resmi ke klien.') }}</p>
                                    </div>
                                    <x-icon-button as="a" icon="add" href="{{ $createQuotationUrl }}" label="{{ __('Create Quotation') }}" title="{{ __('Create Quotation') }}" />
                                </div>
                            @endcan
                        </div>
                    @else
                        <p class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-700 dark:text-slate-300">{{ __('No engineering reply has been submitted yet.') }}</p>
                    @endif
                </section>
            </div>

            @if(!$priceRequest->isClosed() && ($isTech || (int) $priceRequest->requested_by === (int) auth()->id() || auth()->user()?->can('manage-sales-leads')))
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6" aria-labelledby="price-request-actions-heading">
                    <h2 id="price-request-actions-heading" class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Request actions') }}</h2>

                    {{-- Lead Teknisi: review + atur penghandle (seperti TechnicalRequest) --}}
                    @if($isTech && $priceRequest->status === 'waiting_lead')
                        <div class="mt-4 flex flex-wrap gap-2">
                            <form action="{{ route('sales.price-requests.review', $priceRequest) }}" method="POST" class="inline" data-loading-text="{{ __('Processing…') }}">
                                @csrf
                                <input type="hidden" name="decision" value="accepted">
                                <x-icon-button type="submit" icon="sparkles" label="{{ __('Accept') }}" title="{{ __('Accept') }}" />
                            </form>
                            <form action="{{ route('sales.price-requests.review', $priceRequest) }}" method="POST" class="inline" data-loading-text="{{ __('Processing…') }}"
                                  onsubmit="return confirm('{{ __('Reject this request?') }}')">
                                @csrf
                                <input type="hidden" name="decision" value="rejected">
                                <x-icon-button type="submit" icon="delete" label="{{ __('Reject') }}" title="{{ __('Reject') }}" />
                            </form>
                        </div>
                    @endif

                    @if($isTech && in_array($priceRequest->status, ['accepted', 'assigned', 'in_progress']))
                        <form action="{{ route('sales.price-requests.assign', $priceRequest) }}" method="POST" class="mt-4 flex flex-wrap items-end gap-2" data-loading-text="{{ __('Assigning…') }}">
                            @csrf
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Assign Technician') }}</label>
                                <select name="technician_id" required class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                                    <option value="">{{ __('-- Select Technician --') }}</option>
                                    @foreach(($technicians ?? collect()) as $tech)
                                        <option value="{{ $tech->id }}" @selected((int) $priceRequest->assigned_to === (int) $tech->id)>{{ $tech->name }}</option>
                                    @endforeach
                                </select>
                                @error('technician_id') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                            <button type="submit"
                                    class="group relative overflow-hidden rounded-xl bg-accent-600 px-6 py-2.5 text-sm font-medium text-white transition-all duration-300 hover:bg-accent-700 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                                {{ __('Save') }}
                                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                            </button>
                        </form>
                    @endif

                    {{-- Teknisi: ambil cepat, atau balas dengan file --}}
                    @if($isTech && !$priceRequest->isClosed())
                        <div class="mt-4 flex flex-wrap gap-2">
                            <form action="{{ route('sales.price-requests.take', $priceRequest) }}" method="POST" class="inline" data-loading-text="{{ __('Processing…') }}">
                                @csrf
                                <x-icon-button type="submit" icon="sparkles" label="{{ __('Take Request') }}" title="{{ __('Take Request') }}" />
                            </form>
                            <form action="{{ route('sales.price-requests.reject', $priceRequest) }}" method="POST" class="inline" data-loading-text="{{ __('Processing…') }}"
                                  onsubmit="return confirm('{{ __('Reject this request?') }}')">
                                @csrf
                                <x-icon-button type="submit" icon="delete" label="{{ __('Reject') }}" title="{{ __('Reject') }}" />
                            </form>
                        </div>

                        <form action="{{ route('sales.price-requests.reply.store', $priceRequest) }}" method="POST" enctype="multipart/form-data" class="mt-5 space-y-3 border-t border-slate-200 pt-4 dark:border-slate-600" data-loading-text="{{ __('Sending…') }}">
                            @csrf
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Reply File (calculation / price)') }} <span class="text-red-500">*</span></label>
                                <input type="file" name="reply" required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv"
                                       class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                                @error('reply') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Reply Notes (optional)') }}</label>
                                <textarea name="reply_notes" rows="3"
                                          class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">{{ old('reply_notes') }}</textarea>
                                @error('reply_notes') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                            <x-icon-button type="submit" icon="mail" label="{{ __('Send Reply') }}" title="{{ __('Send Reply') }}" />
                        </form>
                    @endif

                    {{-- Sales pemilik / management: batalkan --}}
                    @if(!$priceRequest->isClosed() && ((int) $priceRequest->requested_by === (int) auth()->id() || auth()->user()?->can('manage-sales-leads')))
                        <form action="{{ route('sales.price-requests.cancel', $priceRequest) }}" method="POST" class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-600" data-loading-text="{{ __('Cancelling…') }}"
                              onsubmit="return confirm('{{ __('Cancel this request?') }}')">
                            @csrf
                            <x-icon-button type="submit" icon="delete" label="{{ __('Cancel Request') }}" title="{{ __('Cancel Request') }}" />
                        </form>
                    @endif
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
