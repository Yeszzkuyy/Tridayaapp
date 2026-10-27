{{-- Upload foto bukti meeting (dipakai di New Meeting & laporan).
     Params: $existingPhotos (array path), $required (bool), $maxFiles (int sisa slot). --}}
@php
    $existingPhotos = $existingPhotos ?? [];
    $required = $required ?? false;
    $maxFiles = $maxFiles ?? max(0, 3 - count($existingPhotos));
@endphp

<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
    <div class="mb-3 flex items-center justify-between">
        <label class="block text-sm font-semibold text-slate-900 dark:text-white">
            {{ __('Meeting Proof Photos') }} @if($required)<span class="text-rose-500">*</span>@endif
        </label>
        <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('Min. 1 photo, max. 3 photos (Max 5MB per photo)') }}</span>
    </div>
    <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
        {{ __('Upload photos taken during the meeting (e.g. discussion in person, presentation, or signed paper/document).') }}
    </p>

    @if(count($existingPhotos) > 0)
        <div class="mb-2 grid grid-cols-3 gap-3">
            @foreach($existingPhotos as $photo)
                <a href="{{ asset('storage/'.$photo) }}" target="_blank" rel="noopener noreferrer"
                   class="group relative aspect-video overflow-hidden rounded-xl border border-slate-200 bg-slate-100 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <img src="{{ asset('storage/'.$photo) }}" alt="{{ __('Meeting documentation') }}"
                         class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                </a>
            @endforeach
        </div>
        <p class="mb-2 text-xs text-slate-400">{{ __('These photos were uploaded when the meeting was created.') }}</p>
    @endif

    @if($maxFiles > 0)
        <div class="space-y-4" x-data="photoUploader({{ $maxFiles }})">
            <div class="relative cursor-pointer rounded-xl border-2 border-dashed border-slate-300 bg-slate-50/50 p-6 text-center transition-colors hover:border-accent-500 dark:border-slate-600 dark:bg-slate-900/30 dark:hover:border-accent-400"
                 @click="$refs.fileInput.click()">
                <input type="file"
                       x-ref="fileInput"
                       name="meeting_photos[]"
                       accept="image/png,image/jpeg,image/jpg,image/webp"
                       multiple
                       class="hidden"
                       @change="handleFiles($event)">

                <div class="pointer-events-none flex flex-col items-center justify-center space-y-2">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-accent-100 text-accent-600 dark:bg-accent-900/40 dark:text-accent-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div class="text-sm text-slate-700 dark:text-slate-300">
                        <span class="font-semibold text-accent-600 dark:text-accent-400">{{ __('Click to upload') }}</span> {{ __('or take photo from camera') }}
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">JPG, JPEG, PNG, WEBP ({{ __('max :n files', ['n' => $maxFiles]) }})</p>
                </div>
            </div>

            <div x-show="previews.length > 0" class="grid grid-cols-3 gap-4 pt-2">
                <template x-for="(preview, index) in previews" :key="index">
                    <div class="group relative aspect-video overflow-hidden rounded-xl border border-slate-200 bg-slate-100 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        <img :src="preview" class="h-full w-full object-cover">
                        <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                            <span class="text-xs font-medium text-white" x-text="'Foto ' + (index + 1)"></span>
                        </div>
                    </div>
                </template>
            </div>

            <p x-show="countError" class="text-xs font-medium text-rose-600 dark:text-rose-400" x-text="countError"></p>
        </div>
    @endif

    @error('meeting_photos')
        <p class="mt-2 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
    @enderror
    @error('meeting_photos.*')
        <p class="mt-2 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
    @enderror
</div>

@once
<script>
    function photoUploader(maxFiles) {
        return {
            previews: [],
            countError: '',
            handleFiles(event) {
                const files = event.target.files;
                this.previews = [];
                this.countError = '';

                if (files.length > maxFiles) {
                    this.countError = @js(__('Too many photos selected.'));
                    event.target.value = '';
                    return;
                }

                Array.from(files).forEach(file => {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = (e) => { this.previews.push(e.target.result); };
                        reader.readAsDataURL(file);
                    }
                });
            }
        }
    }
</script>
@endonce
