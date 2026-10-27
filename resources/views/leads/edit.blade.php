<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Edit Lead:') }} {{ $lead->customer->name }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Update lead / opportunity information') }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('leads.show', $lead) }}" title="{{ __('Back') }}" />
    </div>

    <form action="{{ route('leads.update', $lead) }}" method="POST" enctype="multipart/form-data"
          data-loading-text="Saving…"
          class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
        @csrf
        @method('PUT')

        {{-- Info Umum --}}
        <section class="grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-6">
            <div>
<label for="pt_group" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                        {{ __('Lead from PT') }} <span class="text-red-500">*</span>
                        <x-info-tip tip="{{ __('The group company entity handling this lead: NTI, MGK, TPS, or WANI.') }}" />
                    </label>
                <x-glide-select name="pt_group" :label="__('Lead from PT')" required
                    :options="collect($ptGroups)->all()"
                    :value="old('pt_group', $lead->pt_group ?? '')"
                    :placeholder="__('Pilih PT')" :error="$errors->first('pt_group')" />
            </div>

            <div>
                <label for="incoming_date" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                    {{ __('Entry Date') }} <span class="text-red-500">*</span>
                    <x-info-tip tip="{{ __('The date this lead first reached the team (e.g. from chat/email).') }}" />
                </label>
                <x-datepicker name="incoming_date" id="incoming_date" required value="{{ old('incoming_date', $lead->incoming_date?->format('Y-m-d')) }}"></x-datepicker>
            </div>

            <div>
                <label for="source" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                    {{ __('Entered by') }}
                    <x-info-tip tip="{{ __('Where this lead came from: WhatsApp, email, phone, canvassing, event, etc.') }}" />
                </label>
                <x-glide-select name="source" :label="__('Entered by')"
                    :options="collect($sources)->map(fn ($s) => ['value' => $s, 'label' => \App\Http\Controllers\LeadController::label($s)])->all()"
                    :value="old('source', $lead->source ?? '')" :placeholder="__('Select')" />
            </div>

            <div>
                <label for="segment" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                    {{ __('Segmentation') }} <span class="text-red-500">*</span>
                    <x-info-tip tip="{{ __('Type of prospective client: End User, Vendor, System Integrator, Contractor, Gov, Principle, Distributor, or other.') }}" />
                </label>
                <x-glide-select name="segment" :label="__('Segmentation')" required
                    :options="collect($segments)->map(fn ($s) => ['value' => $s, 'label' => \App\Http\Controllers\LeadController::label($s)])->all()"
                    :value="old('segment', $lead->segment ?? '')"
                    :placeholder="__('Select Segmentation')" :error="$errors->first('segment')" />
            </div>
        </section>

        {{-- Data Customer --}}
        <section class="border-t border-slate-200">
            <label class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-3">
                {{ __('Customer Data') }} <span class="text-red-500">*</span>
                <x-info-tip tip="{{ __('Update the customer data related to this lead.') }}" />
            </label>

            <input type="hidden" name="customer_mode" value="existing">
            <input type="hidden" name="customer_id" value="{{ old('customer_id', $lead->customer_id) }}">

            <div class="space-y-4">
                <div>
                    <label for="customer_name" class="block text-sm font-medium text-slate-700 mb-1">
                        {{ __('Company') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="customer_name" id="customer_name" value="{{ old('customer_name', $lead->customer->name) }}"
                           placeholder="{{ __('e.g. PT Koin Konstruksi') }}"
                           class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    @error('customer_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label for="customer_address" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Address') }}</label>
                        <textarea name="customer_address" id="customer_address" rows="2"
                                  placeholder="{{ __('e.g. Plaza Kebon Jeruk Block D7-8, Jl. Raya Perjuangan, West Jakarta 11530') }}"
                                  class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('customer_address', $lead->customer->address) }}</textarea>
                    </div>
                    <div>
                        <label for="customer_contact_person" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                            {{ __('PIC') }}
                            <x-info-tip tip="{{ __('Nama orang yang bisa dihubungi di perusahaan tersebut.') }}" />
                        </label>
                        <input type="text" name="customer_contact_person" id="customer_contact_person" value="{{ old('customer_contact_person', $lead->customer->contact_person) }}"
                               placeholder="{{ __('e.g. Ms. Vita') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    </div>
                    <div>
                        <label for="customer_phone" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Office Phone') }}</label>
                        <input type="text" name="customer_phone" id="customer_phone" value="{{ old('customer_phone', $lead->customer->phone) }}"
                               placeholder="{{ __('e.g. 0812-3456-7890') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    </div>
                    <div>
                        <label for="customer_whatsapp" class="block text-sm font-medium text-slate-700 mb-1">{{ __('WhatsApp No.') }}</label>
                        <input type="text" name="customer_whatsapp" id="customer_whatsapp" value="{{ old('customer_whatsapp', $lead->customer->whatsapp) }}"
                               placeholder="{{ __('e.g. 0812-3456-7890') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    </div>
                    <div class="md:col-span-2">
                        <label for="customer_email" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Email') }}</label>
                        <input type="email" name="customer_email" id="customer_email" value="{{ old('customer_email', $lead->customer->email) }}"
                               placeholder="{{ __('e.g. vita@ptkoin.com') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @error('customer_email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </section>

        {{-- Detail --}}
        <section class="border-t border-slate-200">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2">
                <div>
                    <label for="kebutuhan" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                        {{ __('Requirement') }} <span class="text-red-500">*</span>
                        <x-info-tip tip="{{ __('Summary of what the user needs: product, quantity, and whether installation is included.') }}" />
                    </label>
                    <textarea name="kebutuhan" id="kebutuhan" rows="2" required
                              placeholder="{{ __('e.g. Cisco IP Phone 780 Series requirement with installation') }}"
                              class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('kebutuhan', $lead->kebutuhan) }}</textarea>
                    @error('kebutuhan') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        {{-- Penugasan --}}
        <section class="border-t border-slate-200">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                <div>
                    <label for="partner_id" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                        {{ __('Related Partner') }}
                        <x-info-tip tip="{{ __('Select a partner if this lead involves a specific vendor/supplier/contractor. Optional.') }}" />
                    </label>
                    <x-glide-select name="partner_id" :label="__('Related Partner')" tags
                        :options="collect($partners)->map(fn ($p) => ['value' => $p->id, 'label' => $p->name, 'tag' => __(\App\Models\Partner::TYPES[$p->type] ?? $p->type)])->all()"
                        :value="old('partner_id', $lead->partner_id ?? '')" :placeholder="__('Select')" :empty-label="__('No Partner')" />
                </div>
            </div>
        </section>

        {{-- Lampiran --}}
        <section class="border-t border-slate-200 space-y-4">
            @if($lead->documents->isNotEmpty())
                <div>
                    <label class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-2">
                        {{ __('Current Attachments') }}
                        <x-info-tip tip="{{ __('Click the file name to view its contents.') }}" />
                    </label>
                    <ul class="space-y-1">
                        @foreach($lead->documents as $doc)
                            <li class="flex items-center justify-between gap-3">
                                <a href="{{ route('leads.attachments.show', [$lead, $doc]) }}" target="_blank"
                                   class="text-sm text-accent-600 hover:underline">{{ $doc->file_name }}</a>
                                <button type="button"
                                        class="group relative overflow-hidden rounded-lg border border-red-200 bg-white px-6 py-2.5 text-sm font-medium text-red-600 transition-all duration-300 hover:scale-105 hover:border-red-600 hover:bg-red-600 hover:text-white hover:shadow-md active:scale-95 dark:border-slate-700 dark:bg-slate-800 dark:text-red-400 dark:hover:border-red-500 dark:hover:bg-red-500 dark:hover:text-white"
                                        onclick="if(confirm('{{ __('Yakin hapus lampiran ini?') }}')){fetch('{{ route('leads.attachments.destroy', [$lead, $doc]) }}',{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json','Content-Type':'application/json'},body:JSON.stringify({_method:'DELETE'})}).then(r=>r.ok&&location.reload())}">
                                    {{ __('Delete') }}
                                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <label class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                    {{ __('Add Attachments (BOQ / User Requirements)') }}
                    <x-info-tip tip="{{ __('Upload the initial BOQ, specifications, or user requirement documents. Max 5 files per save, 10 MB per file.') }}" />
                </label>
                <input type="file" name="attachments[]" multiple
                       accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.txt,.csv"
                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-accent-50 file:text-accent-700 file:text-sm file:font-medium">
                @error('attachments')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error('attachments.*')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </section>

        {{-- Aksi --}}
        <div class="flex justify-end gap-3 border-t border-slate-200">
            <a href="{{ route('leads.show', $lead) }}"
               class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                {{ __('Cancel') }}
                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
            </a>
            <button type="submit"
                    class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                {{ __('Update Lead') }}
                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
            </button>
        </div>
    </form>
</x-app-layout>
