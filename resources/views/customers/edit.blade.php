<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
            <div class="min-w-0">
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-800">
                    {{ __('Edit Customer') }}
                </h1>
                <p class="text-slate-500 mt-1">
                    {{ __('Edit customer data:') }} <span class="font-semibold text-slate-700">{{ $customer->name }}</span>
                </p>
            </div>
            <div class="sm:self-start">
                <x-icon-button as="a" icon="back" href="{{ route('customers.show', $customer) }}" title="{{ __('Back to Customer') }}" />
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 w-full">
            <form action="{{ route('customers.update', $customer) }}" method="POST" enctype="multipart/form-data" data-loading-text="Saving…">
                @csrf
                @method('PUT')

                <div class="space-y-4">
                    {{-- Nama Perusahaan --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('Company Name') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               name="name"
                               value="{{ old('name', $customer->name) }}"
                               required
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Company Group --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('Customers from Company') }} <span class="text-red-500">*</span>
                        </label>
                        @if(count($ptGroups) === 1)
                            {{-- Sales terkunci ke company-nya: PT tidak bisa dipindah --}}
                            <input type="hidden" name="pt_group" value="{{ old('pt_group', $customer->pt_group ?: $ptGroups[0]) }}">
                            <div class="flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-200">
                                <x-icon name="building" class="h-4 w-4 text-slate-400" />
                                {{ old('pt_group', $customer->pt_group ?: $ptGroups[0]) }}
                            </div>
                            @error('pt_group') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        @else
                            <x-glide-select name="pt_group" :label="__('Customers from Company')" required
                                :options="collect($ptGroups)->all()"
                                :value="old('pt_group', $customer->pt_group ?? '')"
                                :placeholder="__('Pilih Company')" :error="$errors->first('pt_group')" />
                        @endif
                    </div>

                    {{-- Address --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('Address') }}
                        </label>
                        <textarea name="address" 
                                  rows="3"
                                  class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('address', $customer->address) }}</textarea>
                        @error('address') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Billing & Shipping --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                {{ __('Billing Address') }}
                            </label>
                            <textarea name="billing_address"
                                      rows="3"
                                      placeholder="{{ __('Leave empty to use the main address') }}"
                                      class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('billing_address', $customer->billing_address) }}</textarea>
                            @error('billing_address') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                {{ __('Shipping Address') }}
                            </label>
                            <textarea name="shipping_address"
                                      rows="3"
                                      placeholder="{{ __('Leave empty to use the main address') }}"
                                      class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('shipping_address', $customer->shipping_address) }}</textarea>
                            @error('shipping_address') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- PIC utama --}}
                    @php
                        $pic = $customer->primaryPic();
                        $picValues = [
                            'name' => old('pic_name', $pic?->name ?: $customer->contact_person),
                            'position' => old('pic_position', $pic?->position),
                            'phone' => old('pic_phone', $pic?->phone),
                            'whatsapp' => old('pic_whatsapp', $pic?->whatsapp),
                            'email' => old('pic_email', $pic?->email),
                        ];
                    @endphp
                    <div class="rounded-xl border border-slate-200 dark:border-slate-600 p-4 space-y-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('PIC') }}</p>
                            <p class="text-xs text-slate-500">{{ __('Primary') }}</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="pic_name" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Name') }}</label>
                                <input type="text" id="pic_name" name="pic_name" value="{{ $picValues['name'] }}"
                                       placeholder="{{ __('Person / PIC name') }}"
                                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                                @error('pic_name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="pic_position" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Position') }}</label>
                                <input type="text" id="pic_position" name="pic_position" value="{{ $picValues['position'] }}"
                                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                                @error('pic_position') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="pic_phone" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Phone') }}</label>
                                <input type="text" id="pic_phone" name="pic_phone" value="{{ $picValues['phone'] }}"
                                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                                @error('pic_phone') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="pic_whatsapp" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('WhatsApp') }}</label>
                                <input type="text" id="pic_whatsapp" name="pic_whatsapp" value="{{ $picValues['whatsapp'] }}"
                                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                                @error('pic_whatsapp') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label for="pic_email" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Email') }}</label>
                                <input type="email" id="pic_email" name="pic_email" value="{{ $picValues['email'] }}"
                                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                                @error('pic_email') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Finance PIC --}}
                    <div class="rounded-xl border border-slate-200 dark:border-slate-600 p-4 space-y-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('Finance PIC') }}</p>
                            <p class="text-xs text-slate-500">{{ __('Contact person for invoices and billing follow-up.') }}</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Name') }}</label>
                                <input type="text" name="finance_pic_name" value="{{ old('finance_pic_name', $customer->finance_pic_name) }}"
                                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                                @error('finance_pic_name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Phone / WhatsApp') }}</label>
                                <input type="text" name="finance_pic_phone" value="{{ old('finance_pic_phone', $customer->finance_pic_phone) }}"
                                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                                @error('finance_pic_phone') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Email') }}</label>
                                <input type="email" name="finance_pic_email" value="{{ old('finance_pic_email', $customer->finance_pic_email) }}"
                                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                                @error('finance_pic_email') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Phone (perusahaan) --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('Company Phone No.') }}
                        </label>
                        <input type="text" 
                               name="phone" 
                               value="{{ old('phone', $customer->phone) }}"
                               placeholder="{{ __('e.g. 021-1234-5678') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @error('phone') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- WhatsApp (perusahaan) --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('Company WhatsApp No.') }}
                        </label>
                        <input type="text"
                               name="whatsapp"
                               value="{{ old('whatsapp', $customer->whatsapp) }}"
                               placeholder="{{ __('e.g. 0812-3456-7890') }}"
                               inputmode="tel"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @error('whatsapp') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('Email') }}
                        </label>
                        <input type="email" 
                               name="email" 
                               value="{{ old('email', $customer->email) }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @error('email') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- NPWP --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                {{ __('NPWP') }}
                            </label>
                            <input type="text"
                                   name="npwp"
                                   value="{{ old('npwp', $customer->npwp) }}"
                                   placeholder="{{ __('e.g. 01.234.567.8-901.234') }}"
                                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                            @error('npwp') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                {{ __('NPWP Document') }}
                            </label>
                            @if($customer->npwp_document_path)
                                <p class="mb-1"><a href="{{ route('customers.npwp', $customer) }}" target="_blank" rel="noopener" class="text-sm text-indigo-600 hover:underline">{{ $customer->npwpDisplayName() }}</a></p>
                            @endif
                            <input type="file"
                                   name="npwp_document" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                   class="w-full text-sm text-slate-500 file:mr-2 file:py-2 file:px-3 file:rounded-xl file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            @error('npwp_document') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('Notes') }}
                        </label>
                        <textarea name="notes" 
                                  rows="3"
                                  class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('notes', $customer->notes) }}</textarea>
                        @error('notes') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex flex-col-reverse sm:flex-row gap-3">
                    <a href="{{ route('customers.show', $customer) }}" 
                       class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                        {{ __('Cancel') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </a>
                    <button type="submit"
                            class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Update Customer') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>

        {{-- PIC / Kontak perusahaan --}}
        <div class="mt-6 bg-white rounded-2xl shadow-sm border border-slate-200 p-6 w-full">
            <div class="mb-2 flex items-center justify-between gap-3">
                <h2 class="text-lg font-bold text-slate-800">{{ __('PIC / Customer Contacts List') }}</h2>
                <x-icon-button as="a" icon="add" href="{{ route('customer-contacts.create', $customer) }}" label="{{ __('Add PIC') }}" title="{{ __('Add PIC') }}" />
            </div>

            @if($customer->contacts->isNotEmpty())
                <ul class="divide-y divide-slate-200">
                    @foreach($customer->contacts as $contact)
                        <li class="flex items-center justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-2 font-semibold text-slate-800">
                                    <span class="truncate">{{ $contact->name }}</span>
                                    @if($contact->is_primary)
                                        <x-status-badge color="green">{{ __('Primary') }}</x-status-badge>
                                    @endif
                                </p>
                                <p class="mt-0.5 truncate text-sm text-slate-500">
                                    {{ $contact->position ?? '-' }}
                                    @if($contact->whatsapp) · {{ \App\Support\PhoneFormatter::format($contact->whatsapp) }} @endif
                                    @if($contact->email) · {{ $contact->email }} @endif
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <x-icon-button as="a" icon="edit" href="{{ route('customer-contacts.edit', $contact) }}" title="{{ __('Edit PIC') }}" />
                                <form action="{{ route('customer-contacts.destroy', $contact) }}" method="POST" class="inline-flex" onsubmit="return confirm('{{ __('Delete this PIC?') }}')">
                                    @csrf @method('DELETE')
                                    <x-icon-button icon="delete" type="submit" title="{{ __('Delete PIC') }}" />
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-empty-state label="{{ __('PIC') }}" />
            @endif
        </div>
    </div>
</x-app-layout>