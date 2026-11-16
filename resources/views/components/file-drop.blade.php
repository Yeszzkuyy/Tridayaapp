{{-- Area upload file: drop zone + daftar file yang dipilih.

     <x-file-drop id="request_files" />
     <x-file-drop id="quotation-file" name="attachment" :max="1" :multiple="false" accept="..." />

     Props: name (default request_files[]), id, max (default 5), multiple, accept.
     Input asli tetap dipakai (sr-only) supaya submit multipart jalan normal;
     drag & drop hanya memindahkan file ke input tersebut. --}}

@props([
    'name' => 'request_files[]',
    'id' => 'request_files',
    'max' => 5,
    'multiple' => true,
    'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.zip',
])

@php
    $fileLimit = $multiple ? (int) $max : 1;
@endphp

<div
    class="rounded-2xl border-2 border-dashed px-4 py-5 transition-colors"
    :class="dragging
        ? 'border-accent-500 bg-accent-50 dark:bg-accent-500/10'
        : 'border-slate-200 bg-slate-50/70 dark:border-slate-700 dark:bg-slate-900/40'"
    x-data="{
        dragging: false,
        files: [],
        pick(list) {
            const dt = new DataTransfer();
            Array.from(list).slice(0, {{ $fileLimit }}).forEach((file) => dt.items.add(file));
            this.$refs.input.files = dt.files;
            this.sync();
        },
        sync() {
            this.files = Array.from(this.$refs.input.files).map((file) => ({
                name: file.name,
                size: file.size >= 1048576
                    ? (file.size / 1048576).toFixed(1) + ' MB'
                    : Math.max(1, Math.round(file.size / 1024)) + ' KB',
            }));
        },
    }"
    x-on:dragover.prevent="dragging = true"
    x-on:dragleave.prevent="dragging = false"
    x-on:drop.prevent="dragging = false; pick($event.dataTransfer.files)"
>
    <input x-ref="input" id="{{ $id }}" type="file" name="{{ $name }}" @if($multiple) multiple @endif class="sr-only"
           accept="{{ $accept }}"
           x-on:change="sync()">

    <label for="{{ $id }}" class="flex cursor-pointer flex-col items-center justify-center gap-2 text-center">
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-accent-500/15 text-accent-600 transition-transform duration-300 dark:bg-accent-500/20 dark:text-accent-300"
              :class="dragging ? 'scale-110' : ''">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 16V4m0 0L8 8m4-4 4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
            </svg>
        </span>
        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">
            {{ $multiple ? __('Drop files here or click to browse') : __('Drop a file here or click to browse') }}
        </span>
        <span class="text-xs text-slate-500 dark:text-slate-400">
            {{ $multiple
                ? __('PDF, Word, Excel, CSV, image, or ZIP. Max :count files, 10 MB each.', ['count' => $fileLimit])
                : __('PDF, Word, Excel, image, or ZIP. Max 10 MB.') }}
        </span>
    </label>

    <ul x-show="files.length" x-cloak class="mt-4 flex flex-wrap justify-center gap-2">
        <template x-for="(file, index) in files" :key="index">
            <li class="flex max-w-full items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <x-icon name="file-text" class="h-4 w-4 shrink-0 text-accent-600 dark:text-accent-400" />
                <span class="max-w-[14rem] truncate text-xs font-medium text-slate-700 dark:text-slate-200" x-text="file.name"></span>
                <span class="shrink-0 text-[11px] font-medium text-slate-400" x-text="file.size"></span>
            </li>
        </template>
    </ul>
</div>
