<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Tambah Partner') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Simpan data supplier, vendor, kontraktor, partner, atau distributor.') }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('partners.index') }}" title="{{ __('Kembali') }}" />
    </div>

    <form action="{{ route('partners.store') }}" method="POST"
          class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-6">
        @csrf

        {{-- Informasi Partner --}}
        <section class="grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-6">
            <div>
                <label for="name" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                    {{ __('Nama Partner') }} <span class="text-red-500">*</span>
                    <x-info-tip tip="{{ __('Nama resmi perusahaan atau instansi partner.') }}" />
                </label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required placeholder="{{ __('cth: PT Sumber Jaya') }}"
                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="type" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                    {{ __('Tipe') }} <span class="text-red-500">*</span>
                    <x-info-tip tip="{{ __('Kategori kerja sama: Supplier (barang), Vendor (jasa), Kontraktor, Distributor, atau Partner umum.') }}" />
                </label>
                <select name="type" id="type" required
                        class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    @foreach(\App\Models\Partner::TYPES as $val => $label)
                        <option value="{{ $val }}" {{ old('type', 'supplier') == $val ? 'selected' : '' }}>{{ __($label) }}</option>
                    @endforeach
                </select>
                @error('type')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </section>

        {{-- Kontak --}}
        <section class="grid grid-cols-1 md:grid-cols-3 gap-x-10 gap-y-6 pt-6 border-t border-slate-200">
            <div>
                <label for="contact_person" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                    Contact Person
                    <x-info-tip tip="{{ __('Nama orang yang bisa dihubungi di partner ini.') }}" />
                </label>
                <input type="text" name="contact_person" id="contact_person" value="{{ old('contact_person') }}" placeholder="{{ __('cth: Pak Budi Santoso') }}"
                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                @error('contact_person')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="phone" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                    {{ __('Telepon / WhatsApp') }}
                    <x-info-tip tip="{{ __('Nomor yang aktif dihubungi, boleh format link WhatsApp.') }}" />
                </label>
                <input type="text" name="phone" id="phone" value="{{ old('phone') }}" placeholder="{{ __('cth: wa.me/6281234567890 atau 08xx-xxxx-xxxx') }}"
                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                @error('phone')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" placeholder="{{ __('cth: budi@ptsumberjaya.com') }}"
                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </section>

        {{-- Lainnya --}}
        <section class="space-y-6 pt-6 border-t border-slate-200">
            <div>
                <label for="address" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Alamat') }}</label>
                <textarea name="address" id="address" rows="2"
                          placeholder="{{ __('cth: Jl. Industri Raya No. 15, Bekasi, Jawa Barat') }}"
                          class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('address') }}</textarea>
                @error('address')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="notes" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Catatan') }}</label>
                <textarea name="notes" id="notes" rows="3" placeholder="{{ __('Catatan tambahan tentang partner ini...') }}"
                          class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('notes') }}</textarea>
                @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </section>

        {{-- Aksi --}}
        <div class="flex justify-end gap-3 pt-6 border-t border-slate-200">
            <a href="{{ route('partners.index') }}"
               class="px-4 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition">
                {{ __('Batal') }}
            </a>
            <button type="submit"
                    class="px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition">
                {{ __('Simpan Partner') }}
            </button>
        </div>
    </form>
</x-app-layout>
