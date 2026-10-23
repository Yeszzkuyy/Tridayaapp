<x-app-layout>
@php
    $archivedDocuments = $archivedDocuments ?? collect();
    $customer = $customer ?? null;
@endphp
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">{{ __('Arsip Dokumen') }}</h1>
                <p class="text-slate-500 mt-1">
                    {{ __('Dokumen yang telah diarsipkan dari project. Otomatis dihapus permanen :days hari setelah tanggal diarsipkan.', ['days' => \App\Models\ProjectDocument::TRASH_RETENTION_DAYS]) }}
                </p>
            </div>
            <div class="flex gap-2">
                <x-icon-button as="a" icon="back" href="{{ route('customers.index') }}" title="{{ __('Back') }}" />
            </div>
        </div>

        {{-- Toolbar: Cari, Filter Tanggal Arsip --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="relative flex-1">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input id="arch-doc-search" type="search" placeholder="{{ __('Cari dokumen') }}" autocomplete="off"
                           class="h-11 w-full rounded-xl border border-slate-300 pl-10 pr-4 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                </div>

                <select id="arch-doc-sort" class="h-11 rounded-xl border border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800 sm:w-64">
                    <option value="archived">{{ __('Diarsipkan: Terbaru') }}</option>
                    <option value="name">{{ __('Nama') }}</option>
                </select>

            </div>

            {{-- Info ringkas --}}
            <div class="mt-4 flex flex-col sm:flex-row gap-4 text-sm text-slate-500 dark:text-slate-400">
                <span><strong>{{ $archivedDocuments->count() }}</strong> {{ __('dokumen diarsipkan') }}</span>
            </div>
        </div>

        {{-- Daftar Arsip --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                <h3 class="text-lg font-bold text-slate-800">{{ __('Dokumen Terarsip') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Dokumen diarsipkan tidak muncul di daftar aktif, tetapi file dan riwayat versi tetap tersimpan.') }}
                </p>
            </div>

            @if($archivedDocuments->isNotEmpty())
                <div id="arch-doc-list" class="divide-y divide-slate-100">
                    @foreach($archivedDocuments as $document)
                        @php
                            $docExt = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION));
                            $isPdf = $docExt === 'pdf';
                            $isImage = in_array($docExt, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                            $docKind = $isImage ? 'image' : ($isPdf ? 'pdf' : 'other');
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
                            $archivedDate = $document->deleted_at?->format('d M Y H:i') ?? '-';
                        @endphp
                        <div class="flex items-center justify-between px-6 py-4 hover:bg-slate-50 transition opacity-75"
                             data-doc-name="{{ mb_strtolower($document->file_name) }}">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-slate-100 text-slate-500">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="font-medium text-slate-500 truncate max-w-xs line-through">{{ $document->file_name }}</p>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-400">
                                            {{ __('Arsip') }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs text-slate-400">
                                        <span>{{ $document->category?->name ?? __('Tanpa Kategori') }}</span>
                                        <span>•</span>
                                        <span>{{ __('Diarsipkan') }}: {{ $archivedDate }}</span>
                                        <span>•</span>
                                        <span>{{ __('Auto-deleted on') }}: {{ $document->trashDeadline()?->format('d M Y H:i') ?? '-' }}</span>
                                    </div>
                                    @if($docSize)
                                        <p class="text-xs text-slate-400 mt-1">{{ $docSize }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <x-icon-button as="a" icon="download" href="{{ route('project-documents.download', $document) }}"
                                               label="{{ __('Download') }}" title="{{ __('Download') }}" />
                                <button type="button" data-doc-restore
                                        data-doc-id="{{ $document->id }}"
                                        data-doc-name="{{ $document->file_name }}"
                                        class="flex items-center gap-1.5 text-sm font-medium text-emerald-600 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300 transition-colors">
                                    <x-icon name="refresh" class="h-4 w-4" />
                                    {{ __('Pulihkan') }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div id="arch-doc-no-results" class="hidden py-10 text-center text-sm text-slate-400">
                    {{ __('Tidak ada dokumen yang cocok.') }}
                </div>
            @else
                <x-empty-state label="{{ __('archived documents') }}"
                               description="{{ __('Belum ada dokumen yang diarsipkan.') }}" />
            @endif
        </div>

        {{-- Modal Konfirmasi Pulihkan --}}
        <div id="restore-confirm-modal" class="fixed inset-0 z-[95] hidden items-center justify-center bg-slate-950/70 p-4" data-doc-modal>
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900">
                <h3 class="mb-4 text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Pulihkan Dokumen') }}</h3>
                <p class="text-sm text-slate-600 dark:text-slate-300 mb-6">
                    {{ __('Dokumen akan dikembalikan ke daftar aktif dan dapat diakses kembali dari Project Documents.') }}
                </p>
                <form id="restore-confirm-form" method="POST" action="#">
                    @csrf
                    <input type="hidden" name="restore" value="1">
                    <div class="mt-4 flex justify-end gap-3">
                        <button type="button" data-doc-modal-close
                                class="rounded-xl px-5 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                            {{ __('Batal') }}
                        </button>
                        <button type="submit"
                                class="group relative overflow-hidden rounded-xl bg-accent-600 px-6 py-2.5 text-sm font-medium text-white transition-all duration-300 hover:bg-accent-700 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                            {{ __('Pulihkan') }}
                            <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const search = document.getElementById('arch-doc-search');
                const sortFilter = document.getElementById('arch-doc-sort');
                const list = document.getElementById('arch-doc-list');
                const noResults = document.getElementById('arch-doc-no-results');

                if (!list) return;

                function apply() {
                    const q = (search?.value || '').trim().toLowerCase();
                    let visible = 0;

                    list.querySelectorAll('> div').forEach((item) => {
                        const match = !q || (item.dataset.docName || '').includes(q);
                        item.classList.toggle('hidden', !match);
                        if (match) visible += 1;
                    });

                    noResults.classList.toggle('hidden', visible > 0);
                }

                search?.addEventListener('input', apply);
                sortFilter?.addEventListener('change', apply);

                // Pulihkan dokumen: kirim ke route restore (backend aktif).
                document.querySelectorAll('[data-doc-restore]').forEach((btn) => {
                    btn.addEventListener('click', function() {
                        const form = document.getElementById('restore-confirm-form');
                        const modal = document.getElementById('restore-confirm-modal');

                        if (form) {
                            form.action = '{{ route('project-documents.restore', '__ID__') }}'.replace('__ID__', this.dataset.docId || '');
                        }

                        if (modal) {
                            modal.classList.remove('hidden');
                            modal.classList.add('flex');
                        }
                    });
                });

                // Tutup modal
                document.querySelectorAll('[data-doc-modal-close]').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        btn.closest('[data-doc-modal]').classList.add('hidden');
                        btn.closest('[data-doc-modal]').classList.remove('flex');
                    });
                });
            });
        </script>
    </div>
</x-app-layout>