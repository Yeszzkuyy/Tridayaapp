<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">{{ __('Tambah Kategori Dokumen') }}</h1>
                <p class="text-slate-500 mt-1">{{ __('Buat kategori dokumen baru') }}</p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('admin-panel.document-categories.index') }}" title="{{ __('Kembali') }}" />
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 w-full max-w-2xl">
            <form action="{{ route('admin-panel.document-categories.store') }}" method="POST">
                @csrf

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Nama Kategori') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               placeholder="{{ __('Contoh: Survey, Drawing, BAST') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex gap-3">
                    <button type="submit"
                            class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Simpan') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                    <a href="{{ route('admin-panel.document-categories.index') }}" 
                       class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                        {{ __('Batal') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>