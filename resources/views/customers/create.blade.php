<x-app-layout>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
        <div class="min-w-0">
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-800">
                {{ __('Tambah Customer') }}
            </h1>
            <p class="text-slate-500 mt-1">
                {{ __('Catat perusahaan / customer baru ke Tridaya App.') }}
            </p>
        </div>
        <div class="group relative sm:self-start">
            <a href="{{ route('customers.index') }}"
               title="{{ __('Kembali') }}"
               aria-label="{{ __('Kembali') }}"
               class="relative inline-flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl bg-accent-500 text-sm font-medium text-white shadow-sm transition-all duration-300 hover:scale-110 hover:bg-accent-600 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 focus-visible:ring-offset-2 active:scale-95">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
            </a>
            <span class="pointer-events-none absolute right-0 top-full z-10 mt-2 origin-top-right -translate-y-1 whitespace-nowrap rounded-lg bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 shadow-lg transition-all duration-200 group-hover:translate-y-0 group-hover:opacity-100 dark:bg-slate-700" role="tooltip">{{ __('Kembali') }}</span>
        </div>
    </div>

    <form action="{{ route('customers.store') }}" method="POST" enctype="multipart/form-data"
          data-loading-text="Saving…"
          class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-4 w-full">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
                {{ __('Nama Perusahaan') }} <span class="text-red-500">*</span>
            </label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required
                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
            @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="pt_group" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
                {{ __('Customer dari Company') }} <span class="text-red-500">*</span>
            </label>
            <x-glide-select name="pt_group" :label="__('Customer dari Company')" required
                :options="collect($ptGroups)->all()"
                :value="old('pt_group', '')"
                :placeholder="__('Pilih Company')" :error="$errors->first('pt_group')" />
        </div>

        <div>
            <label for="address" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
                Address
            </label>
            <textarea id="address" name="address" rows="3"
                      class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('address') }}</textarea>
            @error('address') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="phone" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
                {{ __('No Telp') }}
            </label>
            <input type="text" id="phone" name="phone" value="{{ old('phone') }}"
                   placeholder="{{ __('cth: 021-1234-5678') }}"
                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
            @error('phone') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="whatsapp" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
                {{ __('No WA') }}
            </label>
            <input type="text" id="whatsapp" name="whatsapp" value="{{ old('whatsapp') }}"
                   placeholder="{{ __('cth: 0812-3456-7890') }}"
                   inputmode="tel"
                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
            @error('whatsapp') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
                Email
            </label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
            @error('email') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="npwp" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
                    NPWP
                </label>
                <input type="text" id="npwp" name="npwp" value="{{ old('npwp') }}" placeholder="cth: 01.234.567.8-901.234"
                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                @error('npwp') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="npwp_document" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
                    {{ __('Dokumen NPWP') }}
                </label>
                <input type="file" id="npwp_document" name="npwp_document" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                       class="w-full text-sm text-slate-500 file:mr-2 file:py-2 file:px-3 file:rounded-xl file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                @error('npwp_document') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="notes" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
                Notes
            </label>
            <textarea id="notes" name="notes" rows="3"
                      class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('notes') }}</textarea>
            @error('notes') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 border-t border-slate-200 dark:border-slate-600 pt-5">
            <a href="{{ route('customers.index') }}"
               class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                {{ __('Batal') }}
                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
            </a>
            <button type="submit"
                    class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                {{ __('Save') }}
                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
            </button>
        </div>
    </form>
</x-app-layout>
