{{--
    Modal unggah / ganti laporan survey (PDF).
    Dipakai dari halaman daftar & detail survey.

    Trigger:
        - Dari baris:   event browser `report-upload` dengan ID survey
        - Dari header:  event browser `report-upload-open` (pilih survey di dropdown)

    Modal menerima ID survey dari baris/detail yang diklik,
    mengirim multipart/form-data dengan CSRF, mencegah double submit,
    dan menampilkan error validasi.
--}}
@php
    // Base URL dibuat sekali; placeholder __SURVEY__ diganti ID survey di sisi JS.
    $reportUploadBase = route('teknisi.surveys.report.upload', ['survey' => '__SURVEY__']);
@endphp

<div data-survey-report-modal data-upload-url="{{ $reportUploadBase }}"
    x-data="{
        surveyId: @json((string) old('report_survey_id', '')),
        submitting: false,
        openModal(id) {
            this.surveyId = id;
            this.submitting = false;
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'survey-report-upload' }));
        },
        submitUrl() {
            return @js($reportUploadBase).replace('__SURVEY__', this.surveyId);
        },
    }"
    @report-upload.window="openModal($event.detail.id)"
>
    <x-modal name="survey-report-upload" maxWidth="md" :show="$errors->has('report')">
        <form data-report-upload-form :action="submitUrl()" method="POST" enctype="multipart/form-data" @submit="submitting = true">
            @csrf

            <div class="p-6">
                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Upload Survey Report') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Choose PDF file (max 5 MB)') }}</p>

                <input type="hidden" name="report_survey_id" data-report-survey-id :value="surveyId">

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
                            onclick="(function(){var c=document.querySelector('[data-survey-report-modal]'),r=c&&c.querySelector('.fixed.inset-0.z-50');if(r){r.style.display='none';r.querySelectorAll('[x-show]').forEach(function(e){e.style.display='none'})}document.body.classList.remove('overflow-y-hidden');window.dispatchEvent(new CustomEvent('close-modal',{detail:'survey-report-upload'}))})()"
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
