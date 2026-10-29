{{-- Upload foto bukti meeting (dipakai di New Meeting & laporan).
     Foto bisa dipilih berkali-kali (ditambahkan, bukan menimpa pilihan lama).
     Params: $existingPhotos (array path), $required (bool), $maxFiles (int sisa slot). --}}
@php
    $existingPhotos = $existingPhotos ?? [];
    $maxPhotos = \App\Models\FollowUp::MAX_MEETING_PHOTOS;
    $maxMb = intdiv(\App\Models\FollowUp::MEETING_PHOTO_MAX_KB, 1024);
    $required = $required ?? false;
    $maxFiles = $maxFiles ?? max(0, $maxPhotos - count($existingPhotos));
@endphp

<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <label class="block text-sm font-semibold text-slate-900 dark:text-white">
            {{ __('Meeting Proof Photos') }} @if($required)<span class="text-rose-500">*</span>@endif
        </label>
        <span class="text-xs text-slate-500 dark:text-slate-400">
            {{ __('Min. 1 photo, max. :n photos (Max :mb MB per photo)', ['n' => $maxPhotos, 'mb' => $maxMb]) }}
        </span>
    </div>
    <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
        {{ __('Upload photos taken during the meeting (e.g. discussion in person, presentation, or signed paper/document).') }}
    </p>

    @if(count($existingPhotos) > 0)
        <div class="mb-2 flex items-center justify-between">
            <span class="text-xs font-medium text-slate-600 dark:text-slate-300">
                {{ __('Uploaded photos') }}: {{ count($existingPhotos) }}/{{ $maxPhotos }}
            </span>
        </div>
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
        <div class="space-y-4"
             x-data="photoUploader({
                 maxFiles: {{ (int) $maxFiles }},
                 tooMany: @js(__('Too many photos selected. Maximum :n photos.', ['n' => $maxFiles])),
                 tooBig: @js(__('Some photos are still larger than :mb MB after compression.', ['mb' => $maxMb])),
                 failed: @js(__('This photo could not be processed. Please try another one.'))
             })">
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
                    <p class="text-xs text-slate-400 dark:text-slate-500">{{ __('You can pick photos several times; new picks are added.') }}</p>
                </div>
            </div>

            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                <span>
                    <span class="font-semibold text-slate-700 dark:text-slate-200" x-text="files.length"></span>/{{ $maxFiles }} {{ __('selected') }}
                </span>
                <button type="button"
                        x-show="files.length > 0"
                        class="text-xs font-medium text-rose-600 hover:text-rose-700 dark:text-rose-400"
                        @click="clearAll()">
                    {{ __('Clear all') }}
                </button>
            </div>

            <div x-show="previews.length > 0" class="grid grid-cols-3 gap-4 pt-2">
                <template x-for="(preview, index) in previews" :key="preview.url">
                    <div class="group relative aspect-video overflow-hidden rounded-xl border border-slate-200 bg-slate-100 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        <img :src="preview.url" class="h-full w-full object-cover" alt="">
                        <div class="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
                            <span class="text-[11px] font-medium text-white" x-text="preview.name"></span>
                            <span class="text-[10px] text-white/80" x-text="preview.sizeLabel"></span>
                            <button type="button"
                                    class="mt-1 inline-flex items-center gap-1 rounded-lg bg-white px-2 py-1 text-[11px] font-semibold text-rose-600 shadow"
                                    @click.stop="remove(index)">
                                {{ __('Remove') }}
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <p x-show="countError" x-text="countError" class="text-xs font-medium text-rose-600 dark:text-rose-400"></p>
            <p x-show="isProcessing" class="text-xs text-slate-500 dark:text-slate-400">{{ __('Processing photos…') }}</p>
        </div>
    @else
        <p class="text-xs text-slate-500 dark:text-slate-400">
            {{ __('Photo limit reached. Delete a photo first if you want to replace it.') }}
        </p>
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
    function photoUploader(options) {
        return {
            maxFiles: options.maxFiles || 1,
            files: [],
            previews: [],
            countError: '',
            isProcessing: false,

            async handleFiles(event) {
                this.countError = '';
                const incoming = Array.from(event.target.files || []);
                // Kosongkan input supaya foto yang sama bisa dipilih lagi.
                event.target.value = '';

                if (incoming.length === 0) {
                    return;
                }

                this.isProcessing = true;

                for (const file of incoming) {
                    if (!file.type.startsWith('image/')) {
                        continue;
                    }

                    if (this.files.length >= this.maxFiles) {
                        this.countError = options.tooMany;
                        break;
                    }

                    const prepared = await this.prepare(file);

                    if (!prepared) {
                        this.countError = options.failed;
                        continue;
                    }

                    if (prepared.size > {{ \App\Models\FollowUp::MEETING_PHOTO_MAX_KB }} * 1024) {
                        this.countError = options.tooBig;
                        continue;
                    }

                    this.files.push(prepared);
                    this.previews.push({
                        url: URL.createObjectURL(prepared),
                        name: prepared.name,
                        sizeLabel: this.formatSize(prepared.size),
                    });
                }

                this.isProcessing = false;
                this.syncInput();
            },

            remove(index) {
                const preview = this.previews[index];
                if (preview) {
                    URL.revokeObjectURL(preview.url);
                }
                this.files.splice(index, 1);
                this.previews.splice(index, 1);
                this.countError = '';
                this.syncInput();
            },

            clearAll() {
                this.previews.forEach((preview) => URL.revokeObjectURL(preview.url));
                this.files = [];
                this.previews = [];
                this.countError = '';
                this.syncInput();
            },

            /** Salin pilihan ke input file asli (ditambahkan, bukan diganti). */
            syncInput() {
                try {
                    const transfer = new DataTransfer();
                    this.files.forEach((file) => transfer.items.add(file));
                    this.$refs.fileInput.files = transfer.files;
                } catch (error) {
                    // Browser lama: biarkan pilihan terakhir apa adanya.
                }
            },

            formatSize(bytes) {
                if (bytes >= 1024 * 1024) {
                    return (bytes / 1024 / 1024).toFixed(1) + ' MB';
                }
                return Math.max(1, Math.round(bytes / 1024)) + ' KB';
            },

            /**
             * Foto dari kamera HP sering 3-5MB. Kecilkan di browser (maks sisi
             * 1600px, JPEG) supaya lolos batas ukuran per foto.
             */
            async prepare(file) {
                const maxBytes = {{ \App\Models\FollowUp::MEETING_PHOTO_MAX_KB }} * 1024 * 0.95;

                if (file.size <= maxBytes) {
                    return file;
                }

                try {
                    const bitmap = await this.toBitmap(file);
                    if (!bitmap) {
                        return null;
                    }

                    const maxDimension = 1600;
                    const scale = Math.min(1, maxDimension / Math.max(bitmap.width, bitmap.height));
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.max(1, Math.round(bitmap.width * scale));
                    canvas.height = Math.max(1, Math.round(bitmap.height * scale));
                    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);

                    let quality = 0.85;
                    let blob = await this.toBlob(canvas, quality);

                    while (blob && blob.size > maxBytes && quality > 0.4) {
                        quality -= 0.1;
                        blob = await this.toBlob(canvas, quality);
                    }

                    if (!blob || blob.size >= file.size) {
                        return file;
                    }

                    const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';

                    return new File([blob], name, { type: 'image/jpeg' });
                } catch (error) {
                    return file;
                }
            },

            async toBitmap(file) {
                if (window.createImageBitmap) {
                    return await createImageBitmap(file);
                }

                return await new Promise((resolve) => {
                    const image = new Image();
                    const url = URL.createObjectURL(file);
                    image.onload = () => {
                        URL.revokeObjectURL(url);
                        resolve(image);
                    };
                    image.onerror = () => {
                        URL.revokeObjectURL(url);
                        resolve(null);
                    };
                    image.src = url;
                });
            },

            toBlob(canvas, quality) {
                return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
            },
        };
    }
</script>
@endonce
