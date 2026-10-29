{{-- Dokumen Umum — dokumen level customer tanpa project.
Props:
  $generalDocuments Collection dokumen dengan project_id null
  $canUploadDocs    bool
--}}
@props(['generalDocuments', 'canUploadDocs' => false])

@php
    // Kelompokkan per kategori supaya file hasil upload langsung terlihat
    // di kelompok kategorinya. "Tanpa Kategori" ditaruh paling bawah.
    $sections = $generalDocuments
        ->whereNotNull('document_category_id')
        ->groupBy('document_category_id')
        ->map(fn ($docs) => [
            'id' => (int) $docs->first()->document_category_id,
            'name' => $docs->first()->category?->name ?? __('Tanpa Kategori'),
            'docs' => $docs,
        ])
        ->sortBy('name')
        ->values();

    $uncategorized = $generalDocuments->whereNull('document_category_id');

    if ($uncategorized->isNotEmpty()) {
        $sections->push([
            'id' => 0,
            'name' => __('Tanpa Kategori'),
            'docs' => $uncategorized,
        ]);
    }
@endphp

<section class="space-y-4">
    {{-- Header: judul + jumlah di kiri, pencarian & filter di kanan. --}}
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-center gap-2">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">
                {{ __('General') }}
            </h3>
            @if($generalDocuments->isNotEmpty())
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold leading-4 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    {{ $generalDocuments->count() }}
                </span>
            @endif
        </div>

        @if($generalDocuments->isNotEmpty())
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <label class="relative w-full sm:w-56">
                    <span class="sr-only">{{ __('Cari dokumen') }}</span>
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input id="gen-doc-search" type="search" placeholder="{{ __('Cari dokumen...') }}"
                           autocomplete="off"
                           class="h-10 w-full rounded-xl border border-slate-200 bg-white pl-9 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500 dark:focus:ring-indigo-900">
                </label>

                <div class="relative w-full sm:w-44">
                    <select id="gen-doc-category" aria-label="{{ __('Filter kategori') }}"
                            class="h-10 w-full appearance-none rounded-xl border border-slate-200 bg-white pl-3 pr-9 text-sm text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:focus:ring-indigo-900">
                        <option value="">{{ __('Semua kategori') }}</option>
                        @foreach($sections as $section)
                            <option value="{{ $section['id'] === 0 ? 'none' : $section['id'] }}" @selected((string) session('doc_category_id') === (string) $section['id'])>
                                {{ $section['name'] }} ({{ $section['docs']->count() }})
                            </option>
                        @endforeach
                    </select>
                    <x-icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                </div>
            </div>
        @endif
    </div>

    @if($generalDocuments->isNotEmpty())
        <div id="gen-doc-sections" class="space-y-6">
            @foreach($sections as $section)
                <div data-doc-section>
                    <div class="mb-3 flex items-center gap-2">
                        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                            {{ $section['name'] }}
                        </h3>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold leading-4 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                            {{ $section['docs']->count() }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($section['docs'] as $doc)
                            @php
                                // Dokumen milik modul (survey/sizing/instalasi) hanya
                                // dikelola dari menu modulnya — di sini read-only.
                                $isModuleDoc = $doc->isModuleDocument();
                                $metaLine = $isModuleDoc
                                    ? $doc->source_label
                                    : ($doc->version > 1 ? 'v' . $doc->version : null);
                            @endphp

                            <div class="gen-doc-card"
                                 data-doc-name="{{ mb_strtolower($doc->file_name) }}"
                                 data-doc-cat="{{ $doc->document_category_id ?: 'none' }}">
                                @include('customers.doc-card', [
                                    'doc' => $doc,
                                    'canManage' => $canUploadDocs && ! $isModuleDoc,
                                    'badgeText' => $isModuleDoc ? $doc->source_label : null,
                                    'badgeClass' => $isModuleDoc ? 'bg-indigo-600' : null,
                                    'metaLine' => $metaLine,
                                ])
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div id="gen-doc-no-results"
             class="hidden rounded-2xl border border-dashed border-slate-200 px-6 py-10 text-center dark:border-slate-700">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                {{ __('Tidak ada dokumen yang cocok.') }}
            </p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                {{ __('Coba kata kunci lain atau ubah filter kategori.') }}
            </p>
        </div>
    @else
        {{-- Empty state: tombol upload ada di header halaman (satu pintu). --}}
        <div class="rounded-2xl border border-dashed border-slate-200 px-6 py-10 text-center dark:border-slate-700">
            <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-slate-50 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
                <x-icon name="file-text" class="h-5 w-5 text-slate-400 dark:text-slate-500" />
            </div>
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                {{ __('Belum ada dokumen umum') }}
            </p>
        </div>
    @endif
</section>

{{-- Pencarian & filter kategori dokumen umum (client-side, per section). --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const search = document.getElementById('gen-doc-search');
        const categoryFilter = document.getElementById('gen-doc-category');
        const container = document.getElementById('gen-doc-sections');
        const noResults = document.getElementById('gen-doc-no-results');

        if (!search || !container) return;

        // Bila baru upload, filter langsung diarahkan ke kategori file itu.
        const uploadedCategory = @json((string) session('doc_category_id') ?? '');
        if (uploadedCategory && categoryFilter) {
            categoryFilter.value = uploadedCategory === '0' ? 'none' : uploadedCategory;
        }

        function apply() {
            const q = (search.value || '').trim().toLowerCase();
            const cat = categoryFilter?.value || '';
            let visible = 0;

            container.querySelectorAll('.gen-doc-card').forEach((card) => {
                const matchName = !q || (card.dataset.docName || '').includes(q);
                const matchCat = !cat || (card.dataset.docCat || '') === cat;
                const show = matchName && matchCat;
                card.classList.toggle('hidden', !show);
                if (show) visible += 1;
            });

            // Section tanpa file terlihat ikut disembunyikan.
            container.querySelectorAll('[data-doc-section]').forEach((section) => {
                const hasVisible = section.querySelectorAll('.gen-doc-card:not(.hidden)').length > 0;
                section.classList.toggle('hidden', !hasVisible);
            });

            noResults?.classList.toggle('hidden', visible > 0);
        }

        search.addEventListener('input', apply);
        categoryFilter?.addEventListener('change', apply);
        apply();
    });
</script>
