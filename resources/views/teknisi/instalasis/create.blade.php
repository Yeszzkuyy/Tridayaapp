<x-app-layout>
<div class="px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Tambah Instalasi') }}</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1">{{ __('Jadwalkan instalasi baru') }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('teknisi.instalasis.index') }}" title="{{ __('Kembali') }}" />
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 w-full">
        <form action="{{ route('teknisi.instalasis.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Project') }} <span class="text-red-500">*</span></label>
                    <select name="project_id" required class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">
                        <option value="">{{ __('Pilih Project') }}</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" {{ old('project_id', $selectedProject) == $project->id ? 'selected' : '' }}>
                                {{ $project->project_name }} — {{ $project->customer?->name ?? '-' }}
                            </option>
                        @endforeach
                    </select>
                    @error('project_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Lokasi') }}</label>
                        <input type="text" name="location" value="{{ old('location') }}" class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">
                        @error('location') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('PIC Teknisi') }}</label>
                        <input type="text" name="technician_pic" value="{{ old('technician_pic') }}" class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">
                        @error('technician_pic') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Jadwal') }}</label>
                        <input type="date" name="schedule_date" value="{{ old('schedule_date') }}" class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">
                        @error('schedule_date') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Job Status') }}</label>
                        <input type="text" name="job_status" value="{{ old('job_status') }}" class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">
                        @error('job_status') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Status') }} <span class="text-red-500">*</span></label>
                    <select name="status" required class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">
                        <option value="">{{ __('Pilih Status') }}</option>
                        @foreach(['scheduled' => __('Scheduled'), 'on_progress' => __('On Progress'), 'waiting' => __('Waiting'), 'completed' => __('Completed'), 'cancelled' => __('Cancelled')] as $val => $lbl)
                            <option value="{{ $val }}" {{ old('status') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                    @error('status') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Upload Installation Report File') }}</label>
                    <input type="file" name="report_file" accept=".pdf,.doc,.docx"
                           class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-accent-50 file:text-accent-700 file:text-sm file:font-medium">
                    <p class="mt-2 text-xs text-slate-400">{{ __('PDF or DOC, max 5 MB.') }}</p>
                    @error('report_file') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit"
                        class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                    {{ __('Simpan') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </button>
                <a href="{{ route('teknisi.instalasis.index') }}"
                   class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                    {{ __('Batal') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </a>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
