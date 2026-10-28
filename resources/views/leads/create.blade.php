<x-app-layout>
    @php($prefill = $prefill ?? [])
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Add New Lead / Opportunity') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Manage marketing leads and sales opportunities') }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('leads.index') }}" title="{{ __('Back') }}" />
    </div>

    <form action="{{ route('leads.store') }}" method="POST" enctype="multipart/form-data"
          data-loading-text="Saving…"
          class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
        @csrf
        @if(!empty($prefill['whatsapp_account_id']))
            <input type="hidden" name="whatsapp_account_id" value="{{ $prefill['whatsapp_account_id'] }}">
        @endif

        {{-- Info Umum --}}
        <section class="grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-6">
            <div>
<label for="pt_group" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                        {{ __('Lead from PT') }} <span class="text-red-500">*</span>
                        <x-info-tip tip="{{ __('The group company entity handling this lead: NTI, MGK, TPS, or WANI.') }}" />
                    </label>
                <x-glide-select name="pt_group" :label="__('Lead from PT')" required
                    :options="collect($ptGroups)->all()"
                    :value="old('pt_group', $prefill['pt_group'] ?? '')"
                    :placeholder="__('Pilih PT')" :error="$errors->first('pt_group')" />
            </div>

            <div>
                <label for="incoming_date" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                    {{ __('Entry Date') }} <span class="text-red-500">*</span>
                    <x-info-tip tip="{{ __('The date this lead first reached the team (e.g. from chat/email).') }}" />
                </label>
                <x-datepicker name="incoming_date" id="incoming_date" required value="{{ old('incoming_date', now()->toDateString()) }}"></x-datepicker>
            </div>

            <div>
                <label for="source" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                    {{ __('Entered by') }}
                    <x-info-tip tip="{{ __('Where this lead came from: WhatsApp, email, phone, canvassing, event, etc.') }}" />
                </label>
                <x-glide-select name="source" :label="__('Entered by')"
                    :options="collect($sources)->map(fn ($s) => ['value' => $s, 'label' => \App\Http\Controllers\LeadController::label($s)])->all()"
                    :value="old('source', $prefill['source'] ?? '')" :placeholder="__('Select')" />
            </div>

            <div>
                <label for="segment" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                    {{ __('Segmentation') }} <span class="text-red-500">*</span>
                    <x-info-tip tip="{{ __('Type of prospective client: End User, Vendor, System Integrator, Contractor, Gov, Principle, Distributor, or other.') }}" />
                </label>
                <x-glide-select name="segment" :label="__('Segmentation')" required
                    :options="collect($segments)->map(fn ($s) => ['value' => $s, 'label' => \App\Http\Controllers\LeadController::label($s)])->all()"
                    :value="old('segment', '')"
                    :placeholder="__('Select Segmentation')" :error="$errors->first('segment')" />
            </div>
        </section>

        {{-- Data Customer --}}
        <section x-data="{ mode: '{{ old('customer_mode', $prefill['customer_mode'] ?? 'new') }}' }" class="border-t border-slate-200">
            <label class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-3">
                {{ __('Customer Data') }} <span class="text-red-500">*</span>
                <x-info-tip tip="{{ __('Choose New Customer if not recorded before, or Existing Customer if already in the database.') }}" />
            </label>

            <div class="flex items-center gap-6 mb-4">
                <label class="inline-flex items-center gap-2 cursor-pointer text-sm font-medium text-slate-700">
                    <input type="radio" name="customer_mode" value="new" x-model="mode" class="accent-blue-600">
                    {{ __('New Customer') }}
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer text-sm font-medium text-slate-700">
                    <input type="radio" name="customer_mode" value="existing" x-model="mode" class="accent-blue-600">
                    {{ __('Existing Customer') }}
                </label>
            </div>

            <div x-show="mode === 'new'" x-cloak class="space-y-4">
                <div>
                    <label for="customer_name" class="block text-sm font-medium text-slate-700 mb-1">
                        {{ __('Company') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="customer_name" id="customer_name" value="{{ old('customer_name', $prefill['customer_name'] ?? '') }}"
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
                                  class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('customer_address') }}</textarea>
                    </div>
                    <div>
                        <label for="customer_contact_person" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                            {{ __('PIC') }}
                            <x-info-tip tip="{{ __('Nama orang yang bisa dihubungi di perusahaan tersebut.') }}" />
                        </label>
                        <input type="text" name="customer_contact_person" id="customer_contact_person" value="{{ old('customer_contact_person', $prefill['customer_contact_person'] ?? '') }}"
                               placeholder="{{ __('e.g. Ms. Vita') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    </div>
                    <div>
                        <label for="customer_phone" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Office Phone') }}</label>
                        <input type="text" name="customer_phone" id="customer_phone" value="{{ old('customer_phone') }}"
                               placeholder="{{ __('e.g. 0812-3456-7890') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    </div>
                    <div>
                        <label for="customer_whatsapp" class="block text-sm font-medium text-slate-700 mb-1">{{ __('WhatsApp No.') }}</label>
                        <input type="text" name="customer_whatsapp" id="customer_whatsapp" value="{{ old('customer_whatsapp', $prefill['customer_whatsapp'] ?? '') }}"
                               placeholder="{{ __('e.g. 0812-3456-7890') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    </div>
                    <div class="md:col-span-2">
                        <label for="customer_email" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Email') }}</label>
                        <input type="email" name="customer_email" id="customer_email" value="{{ old('customer_email') }}"
                               placeholder="{{ __('e.g. vita@ptkoin.com') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @error('customer_email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div x-show="mode === 'existing'" x-cloak class="space-y-4">
                <div>
                    <label class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                        {{ __('Select Customer') }} <span class="text-red-500">*</span>
                        <x-info-tip tip="{{ __('Type the company name to search for a registered customer.') }}" />
                    </label>
                    <x-searchable-select
                        name="customer_id"
                        :options="$customers->mapWithKeys(fn ($c) => [$c->id => $c->name.($c->picName() ? ' - '.$c->picName() : '')])->all()"
                        :selected="old('customer_id')"
                        placeholder="{{ __('Type the customer name to search...') }}"
                    />
                    @error('customer_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="customer_contact_person_existing" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                        {{ __('PIC (update customer contact)') }}
                        <x-info-tip tip="{{ __('Isi hanya jika ingin memperbarui nama PIC customer ini.') }}" />
                    </label>
                    <input type="text" name="customer_contact_person" id="customer_contact_person_existing" value="{{ old('customer_contact_person') }}"
                           placeholder="{{ __('leave blank if unchanged') }}"
                           class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
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
                              class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('kebutuhan', $prefill['kebutuhan'] ?? '') }}</textarea>
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
                        :value="old('partner_id', '')" :placeholder="__('Select')" :empty-label="__('No Partner')" />
                </div>
            </div>
        </section>

        {{-- Lampiran --}}
        <section class="border-t border-slate-200">
            <label class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                {{ __('Attachments (BOQ / User Requirements)') }}
                <x-info-tip tip="{{ __('Upload the initial BOQ, specifications, or user requirement documents. Max 5 files, 10 MB per file.') }}" />
            </label>
            <input type="file" name="attachments[]" multiple
                   accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.txt,.csv"
                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-accent-50 file:text-accent-700 file:text-sm file:font-medium">
            @error('attachments.*')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </section>

        {{-- Aksi --}}
        <div class="flex justify-end gap-3 border-t border-slate-200">
            <a href="{{ route('leads.index') }}"
               class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                {{ __('Cancel') }}
                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
            </a>
            <button type="submit"
                    class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                {{ __('Save Lead') }}
                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
            </button>
        </div>
    </form>
</x-app-layout>
