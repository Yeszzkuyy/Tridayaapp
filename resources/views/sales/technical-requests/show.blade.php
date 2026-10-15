<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ $technicalRequest->title }}</h1>
                <p class="text-slate-500 mt-1">
                    {{ \App\Models\TechnicalRequest::typeLabel($technicalRequest->request_type) }} •
                    {{ \App\Models\TechnicalRequest::statusLabel($technicalRequest->status) }}
                    @if($technicalRequest->priority === 'urgent')
                        • <span class="text-red-600 font-semibold">{{ __('Urgent') }}</span>
                    @endif
                </p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('leads.show', $technicalRequest->lead) }}" title="{{ __('Back') }}" />
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <p class="text-slate-500">{{ __('Lead') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $technicalRequest->lead->customer?->name ?? 'Lead #'.$technicalRequest->lead_id }}</span></p>
                <p class="text-slate-500">{{ __('Diminta oleh') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $technicalRequest->requester?->name ?? '-' }}</span></p>
                <p class="text-slate-500">{{ __('Target') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $technicalRequest->target_date?->format('d M Y') ?? '-' }}</span></p>
                <p class="text-slate-500">{{ __('Teknisi') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $technicalRequest->technician?->name ?? '-' }}</span></p>
            </div>
            @if($technicalRequest->requirement)
                <div><p class="text-xs font-semibold text-slate-400 uppercase">{{ __('Kebutuhan') }}</p><p class="text-sm whitespace-pre-wrap">{{ $technicalRequest->requirement }}</p></div>
            @endif
            @if($technicalRequest->problem_description)
                <div><p class="text-xs font-semibold text-slate-400 uppercase">{{ __('Deskripsi Masalah') }}</p><p class="text-sm whitespace-pre-wrap">{{ $technicalRequest->problem_description }}</p></div>
            @endif
            @if($technicalRequest->scope)
                <div><p class="text-xs font-semibold text-slate-400 uppercase">{{ __('Scope') }}</p><p class="text-sm whitespace-pre-wrap">{{ $technicalRequest->scope }}</p></div>
            @endif
            @if($technicalRequest->notes)
                <div><p class="text-xs font-semibold text-slate-400 uppercase">{{ __('Catatan') }}</p><p class="text-sm whitespace-pre-wrap">{{ $technicalRequest->notes }}</p></div>
            @endif
            @if($technicalRequest->attachment_path)
                <x-icon-button as="a" icon="download" href="{{ route('sales.technical-requests.attachment', $technicalRequest) }}" label="{{ __('Download Lampiran') }}" title="{{ __('Download Lampiran') }}" />
            @endif

            {{-- Survey terkait request ini (otomatis dibuat saat assign) --}}
            @if($technicalRequest->survey)
                <div class="p-3 rounded-xl bg-accent-50/60 dark:bg-accent-500/10 border border-accent-200 dark:border-accent-500/30">
                    <p class="text-xs font-semibold text-accent-700 dark:text-accent-300 uppercase">{{ __('Survey Terkait') }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <x-icon-button as="a" icon="view" href="{{ route('teknisi.surveys.show', $technicalRequest->survey) }}" label="{{ $technicalRequest->survey->title }}" title="{{ $technicalRequest->survey->title }}" />
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $technicalRequest->survey->hasReport() ? __('Laporan sudah diunggah') : __('Laporan belum diunggah') }}
                        </span>
                    </div>
                </div>
            @endif
            @if($technicalRequest->technical_result)
                <div class="p-3 rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                    <p class="text-xs font-semibold text-green-700 dark:text-green-300 uppercase">{{ __('Hasil Teknis') }}</p>
                    <p class="text-sm whitespace-pre-wrap mt-1">{{ $technicalRequest->technical_result }}</p>
                </div>
            @endif

            {{-- Lead Teknisi: review + assign --}}
            @can('manage-technician')
                @if($technicalRequest->status === 'waiting_lead')
                    <div class="flex flex-wrap gap-2 pt-2">
                        <form action="{{ route('sales.technical-requests.review', $technicalRequest) }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="decision" value="accepted">
                            <x-icon-button type="submit" icon="sparkles" label="{{ __('Accept') }}" title="{{ __('Accept') }}" />
                        </form>
                        <form action="{{ route('sales.technical-requests.review', $technicalRequest) }}" method="POST" class="inline"
                              onsubmit="return confirm('{{ __('Tolak request ini?') }}')">
                            @csrf
                            <input type="hidden" name="decision" value="rejected">
                            <x-icon-button type="submit" icon="delete" label="{{ __('Reject') }}" title="{{ __('Reject') }}" />
                        </form>
                    </div>
                @endif
                @if(in_array($technicalRequest->status, ['accepted', 'assigned']))
                    <form action="{{ route('sales.technical-requests.assign', $technicalRequest) }}" method="POST" class="flex flex-wrap items-end gap-2 pt-2">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Assign Teknisi') }}</label>
                            <select name="technician_id" required class="rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                                <option value="">{{ __('-- Pilih Teknisi --') }}</option>
                                @foreach($technicians as $tech)
                                    <option value="{{ $tech->id }}" @selected((int) $technicalRequest->assigned_technician_id === (int) $tech->id)>{{ $tech->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit"
                                class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                            {{ __('Save') }}
                            <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                        </button>
                    </form>
                @endif
            @endcan

            {{-- Teknisi yang di-assign: progres + hasil + selesai --}}
            @if((int) $technicalRequest->assigned_technician_id === (int) auth()->id() && in_array($technicalRequest->status, ['assigned', 'in_progress']))
                <form action="{{ route('sales.technical-requests.progress', $technicalRequest) }}" method="POST" class="flex flex-wrap items-end gap-2 pt-2">
                    @csrf
                    <div class="flex-1 min-w-52">
                        <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Catatan Progres') }}</label>
                        <input type="text" name="notes" value="{{ old('notes', $technicalRequest->notes) }}"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">
                    </div>
                    <x-icon-button type="submit" icon="edit" label="{{ __('Update Progress') }}" title="{{ __('Update Progress') }}" />
                </form>
                <form action="{{ route('sales.technical-requests.result', $technicalRequest) }}" method="POST" class="pt-1">
                    @csrf
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Hasil Teknis') }}</label>
                    <textarea name="technical_result" rows="4" required
                              class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:bg-slate-800 dark:border-slate-600">{{ old('technical_result', $technicalRequest->technical_result) }}</textarea>
                    @error('technical_result') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    <div class="flex flex-wrap gap-2 mt-2">
                        <button type="submit"
                                class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                            {{ __('Save') }}
                            <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
                <form action="{{ route('sales.technical-requests.complete', $technicalRequest) }}" method="POST" class="inline pt-1"
                      onsubmit="return confirm('{{ __('Selesaikan pekerjaan ini?') }}')">
                    @csrf
                    <x-icon-button type="submit" icon="sparkles" label="{{ __('Complete') }}" title="{{ __('Complete') }}" />
                </form>
            @endif

            {{-- Sales: batalkan bila belum selesai --}}
            @if(!in_array($technicalRequest->status, ['completed', 'cancelled']) && ((int) $technicalRequest->requested_by === (int) auth()->id() || auth()->user()?->can('manage-sales-leads')))
                <form action="{{ route('sales.technical-requests.cancel', $technicalRequest) }}" method="POST" class="pt-2"
                      onsubmit="return confirm('{{ __('Batalkan request ini?') }}')">
                    @csrf
                    <x-icon-button type="submit" icon="delete" label="{{ __('Cancel') }}" title="{{ __('Cancel') }}" />
                </form>
            @endif

            {{-- Sales: lanjut ke penawaran setelah completed --}}
            @if($technicalRequest->status === 'completed' && auth()->user()?->can('manage-sales'))
                <x-icon-button as="a" icon="add" href="{{ route('sales.proposals.create', ['lead_id' => $technicalRequest->lead_id]) }}" label="{{ __('Buat Penawaran') }}" title="{{ __('Buat Penawaran') }}" />
            @endif
        </div>
    </div>
</x-app-layout>
