<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">{{ $document->file_name }}</h1>
                <p class="text-slate-500 mt-1">{{ __('Riwayat Versi Dokumen') }}</p>
            </div>
            <div class="flex gap-2">
                <x-icon-button as="a" icon="back" href="{{ url()->previous() ?? route('projects.show', $document->project_id) }}" title="{{ __('Kembali') }}" />
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">{{ __('Versi Dokumen') }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Setiap versi tersimpan sebagai file terpisah. Versi lama tidak ditimpa.') }}
                    </p>
                </div>
                @can('uploadVersion', $document)
                    <x-icon-button icon="add" label="{{ __('Upload Versi Baru') }}" title="{{ __('Upload Versi Baru') }}"
                                   data-doc-version-upload-open />
                @endcan
            </div>

            @if($versions->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                        <thead class="bg-slate-50 dark:bg-slate-700">
                            <tr>
                                <th class="px-6 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Versi') }}</th>
                                <th class="px-6 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Nama File') }}</th>
                                <th class="px-6 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Ukuran') }}</th>
                                <th class="px-6 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Diunggah Oleh') }}</th>
                                <th class="px-6 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Tanggal') }}</th>
                                <th class="px-6 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-600">
                            @foreach($versions as $version)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                                    <td class="px-6 py-4 text-sm font-medium text-slate-800 dark:text-slate-100">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                            {{ $version->version === ($versions->max('version') ?? 1) ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }}">
                                            v{{ $version->version }}
                                            @if($version->version === ($versions->max('version') ?? 1))
                                                {{ __('(terbaru)') }}
                                            @endif
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300 truncate max-w-xs">{{ $version->file_name }}</td>
                                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                                        @php
                                            $bytes = (int) ($version->file_size ?? 0);
                                            $size = $bytes <= 0 ? '-' : ($bytes >= 1048576 ? number_format($bytes / 1048576, 1).' MB' : (number_format($bytes / 1024, 1).' KB'));
                                        @endphp
                                        {{ $size }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $version->uploader?->name ?? '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $version->created_at->format('d M Y H:i') }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex items-center gap-2">
                                            <x-icon-button as="a" icon="download" href="{{ route('project-documents.download', $version) }}"
                                                label="{{ __('Unduh Versi ini') }}" title="{{ __('Unduh Versi ini') }}" size="sm" />
                                            @if($version->id !== $document->id)
                                                <form action="{{ route('project-documents.destroy', $version) }}" method="POST"
                                                    onsubmit="return confirm('{{ __('Hapus versi ini secara permanen?') }}')"
                                                    class="inline">
                                                    @csrf @method('DELETE')
                                                    @can('forceDelete', $version)
                                                        <x-icon-button icon="delete" type="submit" label="{{ __('Hapus Permanen') }}" title="{{ __('Hapus Permanen') }}" size="sm" variant="delete" />
                                                    @endcan
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-10">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Belum ada riwayat versi.') }}</p>
                </div>
            @endif

            @can('archive', $document)
                <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-600">
                    <form action="{{ route('project-documents.archive', $document) }}" method="POST"
                          onsubmit="return confirm('{{ __('Arsipkan dokumen ini? File dan riwayat versi tetap tersimpan.') }}')">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center gap-1.5 text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors">
                            <x-icon name="archive" class="h-4 w-4" />
                            {{ __('Arsipkan Dokumen') }}
                        </button>
                    </form>
                </div>
            @endcan
        </div>
    </div>

    {{-- Modal Upload Versi Baru --}}
    @can('uploadVersion', $document)
    <div id="version-upload-modal" class="fixed inset-0 z-[90] hidden items-center justify-center bg-slate-950/70 p-4" data-doc-modal>
        <div class="max-h-full w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Upload Versi Baru') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $document->file_name }}</p>
                </div>
                <button type="button" data-doc-modal-close aria-label="{{ __('Tutup') }}"
                        class="flex h-9 w-9 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form action="{{ route('project-documents.store-version', $document) }}" method="POST" enctype="multipart/form-data"
                  x-data="{ dragging: false, fileName: '' }">
                @csrf
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('Catatan Versi') }}</label>
                        <input type="text" name="notes" placeholder="{{ __('Catatan untuk versi ini') }}"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                    </div>
                </div>
                <div class="relative mt-3 rounded-xl border-2 border-dashed p-4 text-center transition-all duration-200"
                     :class="dragging ? 'border-accent-500 bg-accent-50 dark:bg-accent-900/40 dark:border-accent-400' : 'border-slate-300 dark:border-slate-600 hover:border-accent-500 hover:bg-accent-50 dark:hover:bg-accent-900/40'"
                     @dragover.prevent="dragging = true"
                     @dragenter.prevent="dragging = true"
                     @dragleave.prevent="dragging = false"
                     @drop.prevent="dragging = false; if ($event.dataTransfer.files.length) { $refs.fileInput.files = $event.dataTransfer.files; fileName = $event.dataTransfer.files[0].name; }">
                    <input type="file"
                           name="file"
                           x-ref="fileInput"
                           @change="fileName = $event.target.files.length ? $event.target.files[0].name : ''"
                           class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                    <svg class="mx-auto mb-2 h-8 w-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ __('Drag & drop file versi baru') }}</p>
                    <p class="text-xs text-slate-400">{{ __('or click to browse') }} · {{ __('Maximum 20MB') }}</p>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400" x-text="fileName || '{{ __('No file selected') }}'"></p>
                    @error('file')
                        <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div class="mt-4 flex justify-end gap-3">
                    <button type="button" data-doc-modal-close
                            class="rounded-xl px-5 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                        {{ __('Batal') }}
                    </button>
                    <button type="submit" data-doc-upload-submit
                            :disabled="!fileName"
                            :class="!fileName ? 'opacity-50 cursor-not-allowed' : ''"
                            class="group relative overflow-hidden rounded-xl bg-accent-600 px-6 py-2.5 text-sm font-medium text-white transition-all duration-300 hover:bg-accent-700 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Unggah Versi') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endcan
</x-app-layout>
