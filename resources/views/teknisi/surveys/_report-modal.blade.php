{{--
    Modal unggah / ganti laporan survey (PDF).
    Dipakai dari halaman daftar & detail survey.

    Trigger:
        - Dari baris:   @click="$dispatch('report-upload', { id: {{ $survey->id }} })"
        - Dari header:  @click="$dispatch('report-upload-open')"  (pilih survey di dropdown)

    Modal menerima ID survey dari baris yang diklik (atau pilihan dropdown),
    mengirim multipart/form-data dengan CSRF, mencegah double submit,
    dan menampilkan error validasi.
--}}
@php
    // Base URL dibuat sekali; placeholder __SURVEY__ diganti ID survey di sisi JS.
    $reportUploadBase = route('teknisi.surveys.report.upload', ['survey' => '__SURVEY__']);
    /** @var \Illuminate\Support\Collection $reportSurveyOptions */
    $reportSurveyOptions = $reportSurveyOptions ?? collect();
@endphp

<div
    x-data="{
        surveyId: @json((string) old('report_survey_id', '')),
        submitting: false,
        options: @js($reportSurveyOptions),
        openModal(id) {
            this.surveyId = id;
            this.submitting = false;
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'survey-report-upload' }));
        },
        openPicker() {
            this.surveyId = '';
            this.submitting = false;
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'survey-report-upload' }));
        },
        submitUrl() {
            return @js($reportUploadBase).replace('__SURVEY__', this.surveyId);
        },
    }"
    @report-upload.window="openModal($event.detail.id)"
    @report-upload-open.window="openPicker()"
>
    <x-modal name="survey-report-upload" maxWidth="md" :show="$errors->has('report')">
        <form :action="submitUrl()" method="POST" enctype="multipart/form-data" @submit="submitting = true">
            @csrf

            <div class="p-6">
                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Upload Survey Report') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Choose PDF file (max 5 MB)') }}</p>

                <input type="hidden" name="report_survey_id" :value="surveyId">

                {{-- Pilih survey bila dibuka dari header tanpa konteks baris --}}
                <div class="mt-4" x-show="!surveyId && options.length">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Survey') }}</label>
                    <select x-model="surveyId"
                            class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500 text-sm">
                        <option value="">{{ __('Select Survey') }}</option>
                        <template x-for="s in options" :key="s.id">
                            <option :value="s.id" x-text="s.name"></option>
                        </template>
                    </select>
                </div>
                <div class="mt-4" x-show="!surveyId && !options.length">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Belum ada survey. Buat survey dulu.') }}</p>
                </div>

                <div class="mt-4">
                    <input type="file" name="report" accept="application/pdf" required
                           @change="submitting = false"
                           class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-accent-50 file:text-accent-700 file:text-sm file:font-medium">
                    <p class="mt-2 text-xs text-slate-400">{{ __('PDF file only, maximum 5 MB.') }}</p>
                    @error('report')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button"
                            @click="$dispatch('close-modal', 'survey-report-upload')"
                            class="inline-flex items-center justify-center rounded-xl bg-accent-500 px-5 py-2.5 text-sm font-medium text-white transition-all duration-300 hover:bg-accent-600 hover:shadow-lg active:scale-95">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" :disabled="submitting || !surveyId"
                            class="inline-flex items-center justify-center rounded-xl bg-accent-600 px-5 py-2.5 text-sm font-medium text-white transition-all duration-300 hover:bg-accent-500 hover:shadow-xl hover:shadow-accent-500/40 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="!submitting">{{ __('Save') }}</span>
                        <span x-show="submitting">{{ __('Uploading...') }}</span>
                    </button>
                </div>
            </div>
        </form>
    </x-modal>
</div>