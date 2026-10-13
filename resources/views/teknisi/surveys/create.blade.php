<x-app-layout>
<div class="px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Tambah Survey') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Isi data survey — judul dan sales wajib diisi') }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('teknisi.surveys.index') }}" title="{{ __('Kembali') }}" />
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 w-full">
        <form action="{{ route('teknisi.surveys.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="space-y-4">
                {{-- Judul + Sales --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Judul Survey') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="title" value="{{ old('title') }}" required maxlength="255"
                               placeholder="{{ __('Contoh: Survey Kebutuhan CCTV Gedung A') }}"
                               class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">
                        @error('title') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Sales (pemilik survey — yang bisa memantau hasilnya) --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Sales') }} <span class="text-red-500">*</span></label>
                        <select name="sales_user_id" required
                                class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">
                            <option value="">{{ __('Pilih Sales') }}</option>
                            @foreach($salesUsers as $sales)
                                <option value="{{ $sales->id }}" {{ old('sales_user_id') == $sales->id ? 'selected' : '' }}>{{ $sales->name }}</option>
                            @endforeach
                        </select>
                        @error('sales_user_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Customer + Project (opsional) --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Customer') }}</label>
                        <select name="customer_id"
                                class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">
                            <option value="">{{ __('Tanpa Customer') }}</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" {{ old('customer_id', $presetCustomerId ?? null) == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                        @error('customer_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Project') }}</label>
                        <select name="project_id"
                                class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">
                            <option value="">{{ __('Belum ada project') }}</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" {{ old('project_id', $presetProjectId ?? null) == $project->id ? 'selected' : '' }}>{{ $project->project_name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-400">{{ __('Opsional — pilih bila project sudah ada.') }}</p>
                        @error('project_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Survey Date --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Tanggal Survey') }} <span class="text-red-500">*</span></label>
                        <input type="date" name="survey_date" value="{{ old('survey_date') }}" required
                               class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">
                        @error('survey_date') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Location --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Lokasi') }}</label>
                        <input type="text" name="location" value="{{ old('location') }}"
                               class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">
                        @error('location') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- PIC --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('PIC') }}</label>
                    <input type="text" name="pic" value="{{ old('pic') }}"
                           class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">
                    @error('pic') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Survey Data --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Data Survey') }}</label>
                    <textarea name="survey_data" rows="4"
                              class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">{{ old('survey_data') }}</textarea>
                    @error('survey_data') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Report file (opsional — boleh menyusul setelah survey dikerjakan) --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Laporan Survey (PDF)') }}</label>
                    <input type="file" name="report" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp,.zip,.rar,.txt,.csv"
                           class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-accent-50 file:text-accent-700 file:text-sm file:font-medium">
                    <p class="mt-2 text-xs text-slate-400">{{ __('PDF or common documents — max 5 MB. Can be uploaded later from the survey detail.') }}</p>
                    @error('report') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit"
                        class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-500 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                    {{ __('Save') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </button>
                <a href="{{ route('teknisi.surveys.index') }}"
                   class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                    {{ __('Batal') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </a>
            </div>
        </form>
    </div>
</div>
</x-app-layout>