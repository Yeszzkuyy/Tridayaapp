<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
            <div class="min-w-0">
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-800">
                    {{ __('Edit Customer') }}
                </h1>
                <p class="text-slate-500 mt-1">
                    {{ __('Edit data customer:') }} <span class="font-semibold text-slate-700">{{ $customer->name }}</span>
                </p>
            </div>
            <div class="sm:self-start">
                <x-icon-button as="a" icon="back" href="{{ route('customers.show', $customer) }}" title="{{ __('Kembali ke Customer') }}" />
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
                            {{ __('Nama Perusahaan') }} <span class="text-red-500">*</span>
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
                            {{ __('Customer dari Company') }} <span class="text-red-500">*</span>
                        </label>
                        <x-glide-select name="pt_group" :label="__('Customer dari Company')" required
                            :options="collect($ptGroups)->all()"
                            :value="old('pt_group', $customer->pt_group ?? '')"
                            :placeholder="__('Pilih Company')" :error="$errors->first('pt_group')" />
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
                            {{ __('No Telp') }}
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
                            {{ __('No WA') }}
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
                                   placeholder="{{ __('cth: 01.234.567.8-901.234') }}"
                                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                            @error('npwp') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                {{ __('Dokumen NPWP') }}
                            </label>
                            @if($customer->npwp_document_path)
                                <p class="mb-1"><a href="{{ route('customers.npwp', $customer) }}" class="text-sm text-indigo-600 hover:underline">{{ __('Unduh dokumen saat ini') }}</a></p>
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
                        {{ __('Batal') }}
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