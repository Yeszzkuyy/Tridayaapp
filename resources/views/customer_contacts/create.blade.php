<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">
                    {{ __('Tambah PIC') }}
                </h1>
                <p class="text-slate-500 mt-1">
                    {{ __('Tambah PIC untuk customer:') }} <span class="font-semibold text-slate-700">{{ $customer->name }}</span>
                </p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('customers.show', $customer) }}" title="{{ __('← Kembali ke Customer') }}" />
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 w-full">
            <form action="{{ route('customer-contacts.store', $customer) }}" method="POST">
                @csrf

                <div class="space-y-4">
                    {{-- Nama --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('Nama') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="name" 
                               value="{{ old('name') }}" 
                               required
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Jabatan --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('Jabatan') }}
                        </label>
                        <input type="text" 
                               name="position" 
                               value="{{ old('position') }}"
                               placeholder="{{ __('Contoh: Manager, Teknisi, dll') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @error('position') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- WhatsApp --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('No WA') }}
                        </label>
                        <input type="text" 
                               name="whatsapp" 
                               value="{{ old('whatsapp') }}"
                               placeholder="{{ __('Nomor WhatsApp PIC') }}"
                               inputmode="tel"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @error('whatsapp') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            Email
                        </label>
                        <input type="email" 
                               name="email" 
                               value="{{ old('email') }}"
                               placeholder="{{ __('Email PIC') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @error('email') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Jadikan Primary --}}
                    <div class="flex items-center gap-2">
                        <input type="checkbox" 
                               name="is_primary" 
                               id="is_primary" 
                               value="1" 
                               {{ old('is_primary') ? 'checked' : '' }}
                               class="w-5 h-5 rounded border-slate-300 text-accent-600 focus:ring-accent-500">
                        <label for="is_primary" class="text-sm font-medium text-slate-700">
                            {{ __('Jadikan PIC Utama (Primary)') }}
                        </label>
                    </div>
                    @error('is_primary') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mt-6 flex gap-3">
                    <button type="submit" class="px-6 py-3 rounded-xl bg-accent-600 hover:bg-accent-700 text-white font-medium transition text-base">
                        {{ __('Simpan PIC') }}
                    </button>
                    <a href="{{ route('customers.show', $customer) }}" 
                       class="px-6 py-3 rounded-xl bg-accent-500 hover:bg-accent-600 text-white font-medium transition text-base">
                        {{ __('Batal') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>