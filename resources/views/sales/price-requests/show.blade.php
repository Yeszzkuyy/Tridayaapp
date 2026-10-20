<x-app-layout>
    @php
        $isTech = auth()->user()?->can('manage-technician');
        $backUrl = $isTech
            ? route('teknisi.requests.index', ['tab' => 'price'])
            : route('sales.requests.index', ['tab' => 'price']);
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
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Price Request') }} — {{ $priceRequest->customer?->name ?? '-' }}</h1>
                <p class="text-slate-500 mt-1">
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusColors[$priceRequest->status] ?? 'bg-slate-200 text-slate-700' }}">{{ \App\Models\PriceRequest::statusLabel($priceRequest->status) }}</span>
                    • {{ __('Requested by :name', ['name' => $priceRequest->requester?->name ?? '-']) }}
                    • {{ $priceRequest->created_at?->format('d M Y H:i') }}
                </p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ $backUrl }}" title="Back" />
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <p class="text-slate-500">{{ __('Customer') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $priceRequest->customer?->name ?? '-' }}</span></p>
                <p class="text-slate-500">{{ __('Requested by') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $priceRequest->requester?->name ?? '-' }}</span></p>
                <p class="text-slate-500">{{ __('Engineer') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $priceRequest->assignee?->name ?? '-' }}</span></p>
                <p class="text-slate-500">{{ __('Completed') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $priceRequest->completed_at?->format('d M Y H:i') ?? '-' }}</span></p>
            </div>

            @if($priceRequest->notes)
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase">{{ __('Notes') }}</p>
                    <p class="text-sm whitespace-pre-wrap">{{ $priceRequest->notes }}</p>
                </div>
            @endif

            <div class="flex flex-wrap gap-2">
                @if($priceRequest->quotation_path)
                    <x-icon-button as="a" icon="download" href="{{ route('sales.price-requests.quotation', $priceRequest) }}" label="{{ __('Download Quotation') }}" title="{{ __('Download Quotation') }}" />
                @endif
                @if($priceRequest->reply_path)
                    <x-icon-button as="a" icon="download" href="{{ route('sales.price-requests.reply', $priceRequest) }}" label="{{ __('Download Reply') }}" title="{{ __('Download Reply') }}" />
                @endif
            </div>

            @if($priceRequest->reply_path || $priceRequest->reply_notes)
                <div class="p-3 rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                    <p class="text-xs font-semibold text-green-700 dark:text-green-300 uppercase">{{ __('Engineering Reply') }}</p>
                    @if($priceRequest->reply_notes)
                        <p class="text-sm whitespace-pre-wrap mt-1">{{ $priceRequest->reply_notes }}</p>
                    @endif
                </div>
            @endif

            {{-- Lead Teknisi: review + atur penghandle (seperti TechnicalRequest) --}}
            @if($isTech && $priceRequest->status === 'waiting_lead')
                <div class="flex flex-wrap gap-2 pt-2">
                    <form action="{{ route('sales.price-requests.review', $priceRequest) }}" method="POST" class="inline">
                        @csrf
                        <input type="hidden" name="decision" value="accepted">
                        <x-icon-button type="submit" icon="sparkles" label="{{ __('Accept') }}" title="{{ __('Accept') }}" />
                    </form>
                    <form action="{{ route('sales.price-requests.review', $priceRequest) }}" method="POST" class="inline"
                          onsubmit="return confirm('{{ __('Reject this request?') }}')">
                        @csrf
                        <input type="hidden" name="decision" value="rejected">
                        <x-icon-button type="submit" icon="delete" label="{{ __('Reject') }}" title="{{ __('Reject') }}" />
                    </form>
                </div>
            @endif

            @if($isTech && in_array($priceRequest->status, ['accepted', 'assigned', 'in_progress']))
                <form action="{{ route('sales.price-requests.assign', $priceRequest) }}" method="POST" class="flex flex-wrap items-end gap-2 pt-2">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Assign Technician') }}</label>
                        <select name="technician_id" required class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                            <option value="">{{ __('-- Select Technician --') }}</option>
                            @foreach(($technicians ?? collect()) as $tech)
                                <option value="{{ $tech->id }}" @selected((int) $priceRequest->assigned_to === (int) $tech->id)>{{ $tech->name }}</option>
                            @endforeach
                        </select>
                        @error('technician_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit"
                            class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Save') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                </form>
            @endif

            {{-- Teknisi: ambil cepat, atau balas dengan file --}}
            @if($isTech && !$priceRequest->isClosed())
                <div class="flex flex-wrap gap-2 pt-2">
                    <form action="{{ route('sales.price-requests.take', $priceRequest) }}" method="POST" class="inline">
                        @csrf
                        <x-icon-button type="submit" icon="sparkles" label="{{ __('Take Request') }}" title="{{ __('Take Request') }}" />
                    </form>
                    <form action="{{ route('sales.price-requests.reject', $priceRequest) }}" method="POST" class="inline"
                          onsubmit="return confirm('{{ __('Reject this request?') }}')">
                        @csrf
                        <x-icon-button type="submit" icon="delete" label="{{ __('Reject') }}" title="{{ __('Reject') }}" />
                    </form>
                </div>

                <form action="{{ route('sales.price-requests.reply.store', $priceRequest) }}" method="POST" enctype="multipart/form-data" class="space-y-3 pt-2">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Reply File (calculation / price)') }} <span class="text-red-500">*</span></label>
                        <input type="file" name="reply" required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                        @error('reply') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Reply Notes (optional)') }}</label>
                        <textarea name="reply_notes" rows="2"
                                  class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">{{ old('reply_notes') }}</textarea>
                        @error('reply_notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit"
                            class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Send Reply') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                </form>
            @endif

            {{-- Sales pemilik / management: batalkan --}}
            @if(!$priceRequest->isClosed() && ((int) $priceRequest->requested_by === (int) auth()->id() || auth()->user()?->can('manage-sales-leads')))
                <form action="{{ route('sales.price-requests.cancel', $priceRequest) }}" method="POST" class="pt-2"
                      onsubmit="return confirm('{{ __('Cancel this request?') }}')">
                    @csrf
                    <x-icon-button type="submit" icon="delete" label="{{ __('Cancel Request') }}" title="{{ __('Cancel Request') }}" />
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
