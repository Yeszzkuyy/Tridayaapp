<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">
                    {{ __('Edit PIC') }}
                </h1>
                <p class="text-slate-500 mt-1">
                    {{ __('Edit PIC for customer:') }} <span class="font-semibold text-slate-700">{{ $customer->name }}</span>
                </p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('customers.show', $customer) }}" title="{{ __('Back to Customer') }}" />
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 w-full">
            <form action="{{ route('customer-contacts.update', $customerContact) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="space-y-4">
                    {{-- Nama --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('Name') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="name" 
                               value="{{ old('name', $customerContact->name) }}" 
                               required
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Jabatan --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('Position') }}
                        </label>
                        <input type="text" 
                               name="position" 
                               value="{{ old('position', $customerContact->position) }}"
                               placeholder="{{ __('e.g. Manager, Technician, etc.') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @error('position') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- WhatsApp --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('WhatsApp No.') }}
                        </label>
                        <input type="text" 
                               name="whatsapp" 
                               value="{{ old('whatsapp', $customerContact->whatsapp) }}"
                               placeholder="{{ __('PIC WhatsApp Number') }}"
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
                               value="{{ old('email', $customerContact->email) }}"
                               placeholder="{{ __('PIC Email') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @error('email') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Jadikan Primary --}}
                    <div class="flex items-center gap-2">
                        <input type="checkbox" 
                               name="is_primary" 
                               id="is_primary" 
                               value="1" 
                               {{ old('is_primary', $customerContact->is_primary) ? 'checked' : '' }}
                               class="w-5 h-5 rounded border-slate-300 text-accent-600 focus:ring-accent-500">
                        <label for="is_primary" class="text-sm font-medium text-slate-700">
                            {{ __('Set as Primary PIC') }}
                        </label>
                    </div>
                    @error('is_primary') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mt-6 flex gap-3">
                    <button type="submit"
                            class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Update PIC') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                    <a href="{{ route('customers.show', $customer) }}" 
                       class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                        {{ __('Cancel') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>