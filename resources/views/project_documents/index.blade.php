<x-app-layout>
    @if(session('success'))
        <x-status-card :message="session('success')" />
    @endif

    @php
        $pageDocs = $documents->getCollection();
        $categoryCount = $pageDocs->pluck('document_category_id')->filter()->unique()->count();
        $latestDoc = $pageDocs->sortByDesc('created_at')->first();
        $filterCategories = $categories
            ->concat($pageDocs->pluck('category')->filter())
            ->unique('id')
            ->sortBy('name');
    @endphp

    <div class="px-4 sm:px-6 lg:px-8">
        {{-- Breadcrumb --}}
        <nav aria-label="Breadcrumb" class="mb-4">
            <ol class="flex flex-wrap items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400">
                <li>
                    <a href="{{ route('customers.index') }}"
                       class="transition-colors hover:text-indigo-600 dark:hover:text-indigo-400">{{ __('Customer') }}</a>
                </li>
                <li aria-hidden="true" class="text-slate-300 dark:text-slate-600">/</li>
                <li>
                    <a href="{{ route('customers.show', $project->customer_id) }}"
                       class="max-w-[12rem] truncate transition-colors hover:text-indigo-600 dark:hover:text-indigo-400">
                        {{ $project->customer?->name ?? __('Customer') }}
                    </a>
                </li>
                <li aria-hidden="true" class="text-slate-300 dark:text-slate-600">/</li>
                <li class="font-medium text-slate-700 dark:text-slate-200" aria-current="page">{{ __('Project') }}</li>
            </ol>
        </nav>

        {{-- Judul proyek --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="truncate text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">
                        {{ $project->project_name }}
                    </h1>
                    @if($project->status?->name)
                        <x-status-badge color="blue">{{ $project->status->name }}</x-status-badge>
                    @endif
                </div>
                <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
                    <span class="font-mono text-slate-600 dark:text-slate-300">
                        {{ $project->project_code ?: __('Tanpa kode') }}
                    </span>
                    <span aria-hidden="true">·</span>
                    <span>{{ $project->customer?->name }}</span>
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <x-icon-button as="a" icon="back" href="{{ route('customers.show', $project->customer_id) }}"
                               title="{{ __('Back') }}" />
            </div>
        </div>

        {{-- Ringkasan --}}
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Total Dokumen') }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ $documents->total() }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Kategori') }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ $categoryCount }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Terakhir Diunggah') }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">
                    {{ $latestDoc?->created_at?->format('d M Y') ?? '-' }}
                </p>
            </div>
        </div>

        {{-- Toolbar pencarian, filter, urutan --}}
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <label class="relative flex-1">
                    <span class="sr-only">{{ __('Cari dokumen') }}</span>
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input id="proj-doc-search" type="search" placeholder="{{ __('Cari dokumen...') }}"
                           autocomplete="off"
                           class="h-11 w-full rounded-xl border border-slate-200 bg-white pl-9 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500 dark:focus:ring-indigo-900">
                </label>

                <select id="proj-doc-category" aria-label="{{ __('Filter kategori') }}"
                        class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-800 dark:text-white lg:w-56">
                    <option value="">{{ __('Semua Kategori') }}</option>
                    @foreach($filterCategories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>

                <select id="proj-doc-sort" aria-label="{{ __('Urutkan') }}"
                        class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-800 dark:text-white lg:w-52">
                    <option value="latest">{{ __('Urutkan: Terbaru') }}</option>
                    <option value="oldest">{{ __('Urutkan: Terlama') }}</option>
                    <option value="name">{{ __('Urutkan: Nama') }}</option>
                </select>

                <x-icon-button as="a" href="{{ route('customer-documents.archive', $project->customer_id) }}"
                               title="{{ __('Arsip Dokumen') }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m6 4.5h4.5m-9-4.5h13.5v-1.5a1.5 1.5 0 00-1.5-1.5H5.25a1.5 1.5 0 00-1.5 1.5v1.5z"/>
                    </svg>
                </x-icon-button>
            </div>
        </div>

        {{-- Form upload (aksi lama dipertahankan) --}}
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h2 class="mb-4 text-base font-semibold text-slate-900 dark:text-white">{{ __('Upload Dokumen Baru') }}</h2>

            <form action="{{ route('project-documents.store', $project) }}"
                  method="POST"
                  enctype="multipart/form-data"
                  x-data="{ dragging: false, fileName: '' }">
                @csrf
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Category') }}</label>
                        <select name="document_category_id"
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800">
                            <option value="">{{ __('Tanpa Kategori') }}</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Notes') }}</label>
                        <input type="text" name="notes" placeholder="{{ __('Document notes') }}"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800">
                    </div>
                </div>

                <div class="mt-4">
                    <div class="relative rounded-xl border-2 border-dashed p-4 text-center transition-all duration-200"
                         :class="dragging ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-950/40 dark:border-indigo-400' : 'border-slate-300 dark:border-slate-600 hover:border-indigo-400 hover:bg-indigo-50/60 dark:hover:bg-indigo-950/30'"
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
                        <p class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ __('Drag & drop files here') }}</p>
                        <p class="text-xs text-slate-400">{{ __('or click to browse') }} · {{ __('Maximum 20MB') }}</p>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400" x-text="fileName || '{{ __('No file selected') }}'"></p>
                        @error('file')
                            <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button type="submit"
                            class="group relative overflow-hidden rounded-xl bg-accent-600 px-6 py-2.5 text-sm font-medium text-white transition-all duration-300 hover:bg-accent-700 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Upload') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Daftar dokumen --}}
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex items-center justify-between rounded-t-2xl border-b border-slate-200 bg-slate-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-800/60">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ __('Document List') }}</h2>
                <span class="text-sm text-slate-500 dark:text-slate-400">{{ $documents->total() }} {{ __('dokumen') }}</span>
            </div>

            @if($documents->isNotEmpty())
                <div id="proj-doc-list" class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($documents as $document)
                        @php
                            $docExt = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION));
                            $isPdf = $docExt === 'pdf';
                            $isImage = in_array($docExt, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                            $docCategory = $document->category?->name ?? __('Tanpa Kategori');
                            $docSize = '';
                            $docBytes = (int) $document->file_size;
                            if ($docBytes <= 0 && $document->file_path) {
                                try {
                                    $docBytes = \Illuminate\Support\Facades\Storage::disk('private')->size($document->file_path);
                                } catch (\Throwable $e) {
                                    $docBytes = 0;
                                }
                            }
                            $docSize = match (true) {
                                $docBytes <= 0 => '',
                                $docBytes < 1024 => $docBytes . ' B',
                                $docBytes >= 1048576 => number_format($docBytes / 1048576, 1) . ' MB',
                                default => number_format($docBytes / 1024, 1) . ' KB',
                            };
                        @endphp

                        <div class="proj-doc-row flex flex-col gap-3 px-5 py-4 transition hover:bg-slate-50 dark:hover:bg-slate-800/60 sm:flex-row sm:items-center sm:justify-between"
                             data-doc-name="{{ mb_strtolower($document->file_name) }}"
                             data-doc-cat="{{ $document->document_category_id ?? '' }}"
                             data-doc-date="{{ $document->created_at?->timestamp ?? 0 }}"
                             data-doc-id="{{ $document->id }}">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl @if($isImage) bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400 @elseif($isPdf) bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-400 @else bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300 @endif">
                                    @if($isImage)
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                            <rect x="3" y="4" width="18" height="16" rx="2"/>
                                            <circle cx="8.5" cy="9.5" r="1.5"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 15l-4.5-4.5L7 20"/>
                                        </svg>
                                    @elseif($isPdf)
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                        </svg>
                                    @else
                                        <x-icon name="file-text" class="h-5 w-5" />
                                    @endif
                                </span>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">
                                            {{ $document->file_name }}
                                        </p>
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                            v{{ (int) ($document->version ?? 1) }}
                                        </span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                                        <span>{{ $docCategory }}</span>
                                        <span aria-hidden="true">·</span>
                                        <span>{{ $document->created_at?->format('d M Y') }}</span>
                                        @if($docSize)
                                            <span aria-hidden="true">·</span>
                                            <span>{{ $docSize }}</span>
                                        @endif
                                        @if($document->uploader?->name)
                                            <span aria-hidden="true">·</span>
                                            <span>{{ __('Uploaded by:') }} {{ $document->uploader->name }}</span>
                                        @endif
                                    </div>
                                    @if($document->notes)
                                        <p class="mt-1 truncate text-xs text-slate-400">{{ $document->notes }}</p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-1.5 self-end sm:self-center">
                                <x-icon-button as="a" icon="view" href="{{ route('project-documents.preview', $document) }}"
                                               target="_blank" title="{{ __('Preview') }}" />
                                <x-icon-button as="a" icon="download" href="{{ route('project-documents.download', $document) }}"
                                               title="{{ __('Download') }}" />

                                <div class="relative" data-doc-menu>
                                    <x-icon-button icon="more" title="{{ __('More actions') }}" data-doc-menu-toggle />
                                    <div data-doc-menu-panel
                                         class="doc-menu absolute right-0 top-full z-50 mt-2 w-48 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-800">
                                        @if($document->isModuleDocument() && $document->sourceUrl())
                                            <a href="{{ $document->sourceUrl() }}"
                                               class="block w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700">
                                                {{ __('View in :module', ['module' => $document->source_label]) }}
                                            </a>
                                        @else
                                            <a href="{{ route('project-documents.version-history', $document) }}"
                                               class="block w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700">
                                                {{ __('Riwayat Versi') }}
                                            </a>
                                        @endif

                                        @if(! $document->isModuleDocument())
                                            @can('update', $document)
                                                <button type="button" data-doc-rename
                                                        data-action="{{ route('project-documents.rename', $document) }}"
                                                        data-name="{{ $document->file_name }}"
                                                        class="w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700">
                                                    {{ __('Rename') }}
                                                </button>
                                            @endcan

                                            @can('archive', $document)
                                                <button type="button" data-doc-archive
                                                        data-doc-id="{{ $document->id }}"
                                                        data-doc-name="{{ $document->file_name }}"
                                                        class="w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700">
                                                    {{ __('Arsipkan') }}
                                                </button>
                                            @endcan
                                        @endif

                                        @can('forceDelete', $document)
                                            <form action="{{ route('project-documents.destroy', $document) }}"
                                                  method="POST"
                                                  onsubmit="return confirm('{{ __('Hapus permanen dokumen ini? Tindakan ini tidak bisa dibatalkan.') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="w-full px-3 py-2 text-left text-sm text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40">
                                                    {{ __('Hapus Permanen') }}
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div id="proj-doc-no-results" class="hidden px-5 py-10 text-center text-sm text-slate-400">
                    {{ __('Tidak ada dokumen yang cocok.') }}
                </div>

                @if($documents->hasPages())
                    <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-700">
                        {{ $documents->links() }}
                    </div>
                @endif
            @else
                <div class="px-5 py-16 text-center">
                    <p class="font-medium text-slate-700 dark:text-slate-200">{{ __('Belum ada dokumen') }}</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Unggah dokumen pertama proyek ini melalui form di atas.') }}
                    </p>
                </div>
            @endif
        </div>
    </div>

    {{-- Modal konfirmasi arsip --}}
    <div id="archive-confirm-modal" class="fixed inset-0 z-[95] hidden items-center justify-center bg-slate-950/70 p-4" data-doc-modal>
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900">
            <h3 class="mb-4 text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Arsipkan Dokumen') }}</h3>
            <p class="mb-6 text-sm text-slate-600 dark:text-slate-300" id="archive-modal-message">
                {{ __('Dokumen akan dipindahkan ke Arsip dan tidak lagi muncul di daftar aktif. File dan riwayat versi tetap tersimpan.') }}
            </p>
            <form id="archive-confirm-form" method="POST" action="#">
                @csrf
                <input type="hidden" name="archive" value="1">
                <div class="mt-4 flex justify-end gap-3">
                    <button type="button" data-doc-modal-close
                            class="rounded-xl px-5 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                        {{ __('Batal') }}
                    </button>
                    <button type="submit"
                            class="group relative overflow-hidden rounded-xl border border-red-200 bg-white px-6 py-2.5 text-sm font-medium text-red-600 transition-all duration-300 hover:scale-105 hover:border-red-600 hover:bg-red-600 hover:text-white hover:shadow-md active:scale-95 dark:border-slate-700 dark:bg-slate-800 dark:text-red-400 dark:hover:border-red-500 dark:hover:bg-red-500 dark:hover:text-white">
                        {{ __('Ya, Arsipkan') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal ubah nama (dipakai doc-thumbs.js) --}}
    <div id="doc-rename-modal" class="fixed inset-0 z-[95] hidden items-center justify-center bg-slate-950/70 p-4" data-doc-modal>
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900">
            <h3 class="mb-4 text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Rename') }}</h3>
            <form id="doc-rename-form" method="POST" action="#">
                @csrf
                @method('PATCH')
                <input type="text" name="name" id="doc-rename-input" required maxlength="255"
                       class="w-full rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800">
                <p class="mt-1 text-xs text-slate-400">{{ __('File extension is kept automatically.') }}</p>
                <div class="mt-4 flex justify-end gap-3">
                    <button type="button" data-doc-modal-close
                            class="rounded-xl px-5 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit"
                            class="group relative overflow-hidden rounded-xl bg-accent-600 px-6 py-2.5 text-sm font-medium text-white transition-all duration-300 hover:bg-accent-700 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Save') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Pencarian, filter kategori, dan urutan (client-side, per halaman).
            const search = document.getElementById('proj-doc-search');
            const categoryFilter = document.getElementById('proj-doc-category');
            const sortFilter = document.getElementById('proj-doc-sort');
            const list = document.getElementById('proj-doc-list');
            const noResults = document.getElementById('proj-doc-no-results');

            if (list) {
                const rows = () => Array.from(list.querySelectorAll('.proj-doc-row'));

                const apply = () => {
                    const q = (search?.value || '').trim().toLowerCase();
                    const cat = categoryFilter?.value || '';
                    const sort = sortFilter?.value || 'latest';
                    let visible = 0;

                    rows().forEach((row) => {
                        const matchName = !q || (row.dataset.docName || '').includes(q);
                        const matchCat = !cat || (row.dataset.docCat || '') === cat;
                        const show = matchName && matchCat;
                        row.classList.toggle('hidden', !show);
                        if (show) visible += 1;
                    });

                    const sorted = rows()
                        .filter((row) => !row.classList.contains('hidden'))
                        .sort((a, b) => {
                            if (sort === 'name') {
                                return (a.dataset.docName || '').localeCompare(b.dataset.docName || '');
                            }

                            const dateA = parseInt(a.dataset.docDate || '0', 10);
                            const dateB = parseInt(b.dataset.docDate || '0', 10);

                            return sort === 'oldest' ? dateA - dateB : dateB - dateA;
                        });

                    sorted.forEach((row) => list.appendChild(row));

                    noResults?.classList.toggle('hidden', visible > 0);
                };

                search?.addEventListener('input', apply);
                categoryFilter?.addEventListener('change', apply);
                sortFilter?.addEventListener('change', apply);
            }

            // Konfirmasi arsip dokumen (aksi lama dipertahankan).
            document.querySelectorAll('[data-doc-archive]').forEach((btn) => {
                btn.addEventListener('click', function () {
                    const docId = this.dataset.docId;
                    const docName = this.dataset.docName || '';
                    const message = document.getElementById('archive-modal-message');
                    const form = document.getElementById('archive-confirm-form');
                    const modal = document.getElementById('archive-confirm-modal');

                    if (message) {
                        message.textContent = '{{ __('Dokumen :name akan diarsipkan dan tidak lagi muncul di daftar aktif.') }}'
                            .replace(':name', docName);
                    }

                    if (form) {
                        form.action = '{{ route('project-documents.archive', '__ID__') }}'.replace('__ID__', docId);
                    }

                    document.querySelectorAll('[data-doc-menu-panel].doc-open').forEach((panel) => {
                        panel.classList.remove('doc-open');
                    });

                    if (modal) {
                        modal.classList.remove('hidden');
                        modal.classList.add('doc-open');
                        document.body.style.overflow = 'hidden';
                    }
                });
            });
        });
    </script>

    <script type="module" src="{{ \Illuminate\Support\Facades\Vite::asset('resources/js/doc-thumbs.js') }}"></script>
</x-app-layout>
