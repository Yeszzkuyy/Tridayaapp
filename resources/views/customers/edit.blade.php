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

                    {{-- Phone --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('Phone No.') }}
                        </label>
                        <input type="text" 
                               name="phone" 
                               value="{{ old('phone', $customer->phone) }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @error('phone') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- WhatsApp --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('WhatsApp No.') }}
                        </label>
                        <input type="text"
                               name="whatsapp"
                               value="{{ old('whatsapp', $customer->whatsapp) }}"
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
                                <p class="mb-1"><a href="{{ route('customers.npwp', $customer) }}" class="text-sm text-indigo-600 hover:underline">{{ $customer->npwpDisplayName() }}</a></p>
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
    </div>
</x-app-layout>