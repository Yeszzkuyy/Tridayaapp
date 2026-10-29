<x-app-layout>
    @if(session('success') && session('success_card'))
        <x-status-card :message="session('success')" />
    @endif

    @php
        $user = auth()->user();
        $isTech = $user?->can('manage-technician') ?? false;
        $isPricingEngineer = $priceRequest->assignee
            && strtolower((string) $priceRequest->assignee->email) === strtolower((string) config('services.price_request.pricing_engineer_email'));
        $requesterRoles = $priceRequest->requester?->roles?->pluck('name') ?? collect();
        $requesterRoleLabel = $requesterRoles->contains('technician')
            ? __('Technician')
            : ($requesterRoles->contains('inside-sales') ? __('Inside Sales') : __('Sales'));
        $backUrl = $isTech
            ? route('teknisi.requests.index', ['tab' => 'price'])
            : route('sales.requests.index', ['tab' => 'price']);
        $statusColors = [
            'waiting_lead' => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
            'accepted' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            'assigned' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
            'in_progress' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'waiting_sales_review' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300',
            'revision' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            'cancelled' => 'bg-slate-200 text-slate-500 dark:bg-slate-600 dark:text-slate-300',
        ];
        $isFallbackRequest = $priceRequest->status === 'waiting_lead' && $priceRequest->assigned_to === null;
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

        @if($priceRequest->proposal)
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ __('Quotation linked') }}:
                <a href="{{ route('sales.proposals.show', $priceRequest->proposal) }}" class="font-semibold underline">{{ $priceRequest->proposal->proposal_number }}</a>
            </div>
        @elseif(! in_array($priceRequest->status, ['rejected', 'cancelled'], true)
            && auth()->id() === $priceRequest->requested_by
            && (auth()->user()?->can('manage-sales') || auth()->user()?->can('manage-inside-sales')))
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-200">
                <span>{{ __('Turn this price request into a linked quotation without retyping the customer.') }}</span>
                <x-icon-button as="a" icon="add" href="{{ route('sales.proposals.create', ['price_request_id' => $priceRequest->id]) }}" label="{{ __('Create Quotation') }}" title="{{ __('Create Quotation') }}" />
            </div>
        @endif

        <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-5">
            <div class="space-y-4 xl:col-span-3">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6" aria-labelledby="price-request-details-heading">
                    <h2 id="price-request-details-heading" class="mb-4 text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Request details') }}</h2>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div class="flex min-w-0 items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-700">
                            @if($priceRequest->requester)
                                <x-user-avatar :user="$priceRequest->requester" size="w-11 h-11" text="text-sm" />
                            @else
                                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-500 dark:bg-slate-600 dark:text-slate-300" aria-hidden="true">
                                    <x-icon name="user" class="h-5 w-5" />
                                </span>
                            @endif
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('Requested by') }}</p>
                                <p class="truncate font-semibold text-slate-800 dark:text-slate-100">{{ $priceRequest->requester?->name ?? '-' }}</p>
                                <span class="mt-1 inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">{{ $requesterRoleLabel }}</span>
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
                                @if($priceRequest->assignee)
                                    <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $isPricingEngineer ? 'bg-accent-100 text-accent-800 dark:bg-accent-900 dark:text-accent-200' : 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' }}">
                                        {{ $isPricingEngineer ? __('Pricing Engineer') : __('Technician') }}
                                    </span>
                                @endif
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

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6" aria-labelledby="price-request-quotation-heading">
                    <h2 id="price-request-quotation-heading" class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Quotation File') }}</h2>
                    @if($priceRequest->quotation_path)
                        @php
                            $quotationFileName = preg_replace('/^\d+_/', '', basename($priceRequest->quotation_path));
                        @endphp
                        <div class="mt-4 flex flex-col gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-600 sm:flex-row sm:items-center sm:justify-between">
                            <p class="min-w-0 break-all text-sm font-medium text-slate-800 dark:text-slate-100">{{ $quotationFileName }}</p>
                            <x-icon-button as="a" icon="download" href="{{ route('sales.price-requests.quotation', $priceRequest) }}" label="{{ __('Download Quotation') }}" title="{{ __('Download Quotation') }}" />
                        </div>
                    @else
                        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ __('No quotation file attached.') }}</p>
                    @endif
                    @if($priceRequest->notes)
                        <div class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-600">
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Notes') }}</p>
                            <p class="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $priceRequest->notes }}</p>
                        </div>
                    @endif
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6" aria-labelledby="price-request-history-heading">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="price-request-history-heading" class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Calculation History') }}</h2>
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ trans_choice(':count version|:count versions', $priceRequest->replies->count(), ['count' => $priceRequest->replies->count()]) }}</span>
                    </div>

                    @if($priceRequest->replies->isNotEmpty())
                        <div class="mt-4 space-y-3">
                            @foreach($priceRequest->replies as $reply)
                                @php
                                    $replyFileName = preg_replace('/^(?:v\d+_)?\d+_/', '', basename($reply->file_path));
                                @endphp
                                <article class="rounded-xl border border-slate-200 p-4 dark:border-slate-600 {{ $loop->last ? 'bg-slate-50 dark:bg-slate-700/50' : '' }}">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('Version :number', ['number' => $reply->version]) }}</h3>
                                                @if($reply->accepted_at)
                                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-800 dark:bg-green-900/40 dark:text-green-300">{{ __('Accepted by :name', ['name' => $reply->acceptedBy?->name ?? __('Sales')]) }}</span>
                                                @elseif($loop->last && $priceRequest->status === 'waiting_sales_review')
                                                    <span class="rounded-full bg-cyan-100 px-2 py-0.5 text-[10px] font-semibold text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300">{{ __('Latest') }}</span>
                                                @endif
                                            </div>
                                            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                                                <span>{{ $reply->uploader?->name ?? __('Engineer') }}</span>
                                                <time datetime="{{ $reply->created_at?->toIso8601String() }}">{{ $reply->created_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') ?? '-' }}</time>
                                            </div>
                                            @if($reply->notes)
                                                <p class="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $reply->notes }}</p>
                                            @endif
                                            <p class="mt-3 break-all text-xs text-slate-500 dark:text-slate-400">{{ $replyFileName }}</p>
                                        </div>
                                        <x-icon-button as="a" icon="download" href="{{ route('sales.price-requests.replies.download', ['priceRequest' => $priceRequest, 'reply' => $reply]) }}" title="{{ __('Download version :number', ['number' => $reply->version]) }}" />
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        {{-- Handover ke Quotation untuk Sales --}}
                        @can('view-sales')
                            @php
                                $customerLead = $priceRequest->customer?->leads()->whereNotIn('status', ['won', 'lost'])->latest()->first()
                                    ?? $priceRequest->customer?->leads()->latest()->first();
                                $createQuotationUrl = $customerLead
                                    ? route('sales.proposals.create', ['lead_id' => $customerLead->id])
                                    : route('sales.proposals.create', ['q' => $priceRequest->customer?->name]);
                            @endphp
                            <div class="mt-4 flex flex-col gap-3 rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-xs font-semibold text-green-900 dark:text-green-200">{{ __('Langkah Selanjutnya: Buat Quotation') }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Gunakan hasil kalkulasi harga engineer di atas untuk menerbitkan penawaran resmi ke klien.') }}</p>
                                </div>
                                <x-icon-button as="a" icon="add" href="{{ $createQuotationUrl }}" label="{{ __('Create Quotation') }}" title="{{ __('Create Quotation') }}" />
                            </div>
                        @endcan
                    @else
                        <p class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-700 dark:text-slate-300">{{ __('No engineering reply has been submitted yet.') }}</p>
                    @endif
                </section>

                @if($isFallbackRequest)
                    @can('take', $priceRequest)
                        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-800 dark:bg-amber-900/20 sm:p-6" aria-labelledby="price-request-fallback-heading">
                            <h2 id="price-request-fallback-heading" class="text-base font-semibold text-amber-900 dark:text-amber-200">{{ __('Pricing engineer unavailable') }}</h2>
                            <p class="mt-1 text-sm text-amber-800 dark:text-amber-300">{{ __('A lead technician can take this request as a fallback.') }}</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <form action="{{ route('sales.price-requests.take', $priceRequest) }}" method="POST" data-loading-text="{{ __('Assigning…') }}">
                                    @csrf
                                    <x-icon-button type="submit" icon="sparkles" label="{{ __('Take Request') }}" title="{{ __('Take Request') }}" />
                                </form>
                                <form action="{{ route('sales.price-requests.reject', $priceRequest) }}" method="POST" data-loading-text="{{ __('Processing…') }}" onsubmit="return confirm('{{ __('Reject this request?') }}')">
                                    @csrf
                                    <x-icon-button type="submit" icon="delete" label="{{ __('Reject') }}" title="{{ __('Reject') }}" />
                                </form>
                            </div>
                        </section>
                    @endcan
                @endif

                @can('assign', $priceRequest)
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6" aria-labelledby="price-request-assign-heading">
                        <h2 id="price-request-assign-heading" class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Assign Technician') }}</h2>
                        <form action="{{ route('sales.price-requests.assign', $priceRequest) }}" method="POST" class="mt-4 flex flex-wrap items-end gap-2" data-loading-text="{{ __('Assigning…') }}">
                            @csrf
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Technician') }}</label>
                                <select name="technician_id" required class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                                    <option value="">{{ __('-- Select Technician --') }}</option>
                                    @foreach(($technicians ?? collect()) as $tech)
                                        <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <x-icon-button type="submit" icon="edit" label="{{ __('Assign') }}" title="{{ __('Assign') }}" />
                        </form>
                    </section>
                @endcan

                @can('reply', $priceRequest)
                    @if(in_array($priceRequest->status, ['in_progress', 'revision', 'accepted', 'assigned'], true))
                        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6" aria-labelledby="price-request-upload-heading">
                            <h2 id="price-request-upload-heading" class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ $priceRequest->status === 'revision' ? __('Upload Revised Calculation') : __('Upload Calculation') }}</h2>
                            <form action="{{ route('sales.price-requests.reply.store', $priceRequest) }}" method="POST" enctype="multipart/form-data" class="mt-4 space-y-3" data-loading-text="{{ __('Uploading…') }}">
                                @csrf
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Reply File (calculation / price)') }} <span class="text-red-500">*</span></label>
                                    <input type="file" name="reply" required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv"
                                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                                    @error('reply') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Reply Notes (optional)') }}</label>
                                    <textarea name="reply_notes" rows="3" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">{{ old('reply_notes') }}</textarea>
                                    @error('reply_notes') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <x-icon-button type="submit" icon="upload" label="{{ __('Upload Calculation') }}" title="{{ __('Upload Calculation') }}" />
                            </form>
                        </section>
                    @endif
                @endcan

                @can('decide', $priceRequest)
                    @if($priceRequest->status === 'waiting_sales_review')
                        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6" aria-labelledby="price-request-sales-decision-heading">
                            <h2 id="price-request-sales-decision-heading" class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Review Price') }}</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Choose the latest calculation or send a revision request.') }}</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <form action="{{ route('sales.price-requests.accept', $priceRequest) }}" method="POST" data-loading-text="{{ __('Processing…') }}" onsubmit="return confirm('{{ __('Accept this price as final?') }}')">
                                    @csrf
                                    <x-icon-button type="submit" icon="sparkles" label="{{ __('Accept Price') }}" title="{{ __('Accept Price') }}" />
                                </form>
                            </div>
                            <form action="{{ route('sales.price-requests.revision', $priceRequest) }}" method="POST" class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-600" data-loading-text="{{ __('Sending…') }}" onsubmit="return confirm('{{ __('Send this revision request to the Pricing Engineer?') }}')">
                                @csrf
                                <label for="revision-reason" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Revision / negotiation details') }}</label>
                                <textarea id="revision-reason" name="reason" rows="3" required maxlength="2000" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">{{ old('reason') }}</textarea>
                                @error('reason') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('This note will also be added to the discussion.') }}</p>
                                <div class="mt-3">
                                    <x-icon-button type="submit" icon="edit" label="{{ __('Request Revision / Negotiate') }}" title="{{ __('Request Revision / Negotiate') }}" />
                                </div>
                            </form>
                        </section>
                    @endif
                @endcan

                @can('cancel', $priceRequest)
                    <form action="{{ route('sales.price-requests.cancel', $priceRequest) }}" method="POST" data-loading-text="{{ __('Cancelling…') }}" onsubmit="return confirm('{{ __('Cancel this request?') }}')">
                        @csrf
                        <x-icon-button type="submit" icon="delete" label="{{ __('Cancel Request') }}" title="{{ __('Cancel Request') }}" />
                    </form>
                @endcan

            </div>

            <aside class="min-w-0 xl:col-span-2">
                <div class="xl:sticky xl:top-24">
                    <livewire:price-request-chat :price-request="$priceRequest" />
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
