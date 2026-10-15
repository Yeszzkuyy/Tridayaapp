<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">{{ __('Tambah Account Manager') }}</h1>
                <p class="text-slate-500 mt-1">{{ __('Buat data Account Manager baru') }}</p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('admin-panel.account-managers.index') }}" title="{{ __('← Kembali') }}" />
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 w-full max-w-2xl">
            <form action="{{ route('admin-panel.account-managers.store') }}" method="POST">
                @csrf

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Nama Account Manager') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               placeholder="{{ __('Contoh: AM Ahmad Dhani') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex gap-3">
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white font-medium transition">
                        {{ __('Simpan') }}
                    </button>
                    <a href="{{ route('admin-panel.account-managers.index') }}" 
                       class="px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white font-medium transition">
                        {{ __('Batal') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>