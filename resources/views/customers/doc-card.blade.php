{{--
    Kartu dokumen ala Drive (klik = preview dalam layar).

    Props:
      $doc        ProjectDocument
      $canManage  bool  tampilkan tombol rename & hapus
      $badgeClass string  kelas warna badge pojok kiri atas
      $badgeText  string  isi badge (mis. "INV", "PO", "PAY")
      $metaLine   string  info tambahan di bawah nama file (ukuran file
                          otomatis dihitung & ditambahkan di belakangnya)
--}}
@props(['doc', 'canManage' => false, 'badgeClass' => 'bg-slate-500', 'badgeText' => null, 'metaLine' => null])

@php
    $docExt = strtolower(pathinfo($doc->file_name, PATHINFO_EXTENSION));
    $isImage = in_array($docExt, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    $isPdf = $docExt === 'pdf';
    $isVideo = in_array($docExt, ['mp4', 'webm', 'ogg', 'mov', 'avi']);
    $isAudio = in_array($docExt, ['mp3', 'wav', 'ogg']);

    // Jenis yang thumbnail-nya digambar dari ISI file (bukan hasil konversi).
    $contentKinds = ['txt' => 'txt', 'csv' => 'csv', 'docx' => 'docx', 'xlsx' => 'xlsx', 'pptx' => 'pptx', 'zip' => 'zip'];
    $isContent = isset($contentKinds[$docExt]);
    $docKind = $isImage ? 'image'
        : ($isPdf ? 'pdf'
        : ($isVideo ? 'video'
        : ($isAudio ? 'audio'
        : ($contentKinds[$docExt] ?? 'other'))));

    // Tipe yang tidak bisa dibaca browser (format biner lama + rar):
    // kartu berwarna + label ekstensi, ala Drive — tanpa perlu JS.
    $typeTile = match (true) {
        $docExt === 'doc' => ['bg-blue-500', 'DOC'],
        $docExt === 'xls' => ['bg-emerald-500', 'XLS'],
        $docExt === 'ppt' => ['bg-orange-500', 'PPT'],
        $docExt === 'rar' => ['bg-amber-700', 'RAR'],
        default => ['bg-slate-500', strtoupper($docExt ?: 'FILE')],
    };

    $badgeClass = $badgeClass ?: match (true) {
        $isPdf => 'bg-red-500',
        $isImage => 'bg-green-500',
        in_array($docExt, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']) => 'bg-blue-500',
        in_array($docExt, ['zip', 'rar']) => 'bg-amber-500',
        default => 'bg-slate-500',
    };
    $metaLine = $metaLine ?: ($doc->project?->project_name ?? __('General'));

    // Ukuran file: kolom file_size kosong untuk file era lama (sebelum kolom
    // ditambahkan) — jangan tampilkan "0 KB", coba hitung langsung dari
    // storage. Kalau tetap tidak ketahuan, sembunyikan saja.
    $docBytes = (int) $doc->file_size;
    if ($docBytes <= 0 && $doc->file_path) {
        try {
            $docBytes = \Illuminate\Support\Facades\Storage::disk('private')->size($doc->file_path);
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

<div class="doc-card relative rounded-xl border border-slate-200 bg-white transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-900/50"
     data-name="{{ mb_strtolower($doc->file_name) }}"
     data-project="{{ $doc->project_id ?? 'general' }}">
    {{-- Klik thumbnail = preview dalam layar --}}
    <div class="relative flex h-36 cursor-pointer items-center justify-center overflow-hidden bg-slate-100 dark:bg-slate-800"
         data-doc-open
         data-kind="{{ $docKind }}"
         data-url="{{ route('project-documents.preview', $doc) }}"
         data-download="{{ route('project-documents.download', $doc) }}"
         data-filename="{{ $doc->file_name }}"
         title="{{ $doc->file_name }}">
        {{-- Ikon fallback DI BELAKANG: hanya terlihat kalau render thumbnail gagal --}}
        @if(! $isImage)
            <svg class="absolute h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        @endif
        @if($isImage)
            <img src="{{ route('project-documents.preview', $doc) }}" alt="{{ $doc->file_name }}" loading="lazy" onerror="this.remove()"
                 class="pointer-events-none relative h-full w-full object-cover">
        @elseif($isPdf)
            <canvas data-pdf-url="{{ route('project-documents.preview', $doc) }}" class="pointer-events-none relative h-full w-full"></canvas>
        @elseif($isContent)
            {{-- Thumbnail digambar dari isi file (TXT/CSV/DOCX/XLSX/PPTX/ZIP) --}}
            <canvas data-doc-render="{{ $docKind }}" data-url="{{ route('project-documents.preview', $doc) }}"
                    data-filename="{{ $doc->file_name }}" class="pointer-events-none relative h-full w-full"></canvas>
        @else
            {{-- Tipe tak bisa dibaca browser (DOC/XLS/PPT lama, RAR): kartu berwarna + label --}}
            <div class="absolute inset-0 flex flex-col items-center justify-center gap-1 {{ $typeTile[0] }} text-white">
                <svg class="h-9 w-9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span class="text-[11px] font-bold tracking-wider">{{ $typeTile[1] }}</span>
            </div>
        @endif
        <span class="absolute left-2 top-2 rounded px-1.5 py-0.5 text-[11px] font-bold uppercase text-white {{ $badgeClass }}">{{ $badgeText ?: ($docExt ?: __('File')) }}</span>
    </div>
    <div class="p-3">
        <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $doc->file_name }}</p>
        <p class="mt-0.5 truncate text-xs text-slate-400">{{ $metaLine }}@if($docSize) · {{ $docSize }}@endif</p>
        <div class="mt-2 flex items-center justify-end">
            {{-- Aksi dokumen diringkas jadi satu tombol titik tiga; isinya
                 muncul sebagai panel kecil saat diklik. --}}
            <div class="relative" data-doc-menu>
                <x-icon-button icon="more" title="{{ __('More actions') }}" data-doc-menu-toggle />
                <div data-doc-menu-panel
                     class="doc-menu absolute bottom-full right-0 z-50 mb-2 w-44 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-800">
                    <button type="button" data-doc-open-child
                            class="w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700">
                        {{ __('Preview') }}
                    </button>
                    <a href="{{ route('project-documents.download', $doc) }}"
                       class="block w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700">
                        {{ __('Download') }}
                    </a>
                    @if($canManage)
                        <button type="button" data-doc-rename
                                data-action="{{ route('project-documents.rename', $doc) }}"
                                data-name="{{ $doc->file_name }}"
                                class="w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700">
                            {{ __('Rename') }}
                        </button>
                        <form action="{{ route('project-documents.destroy', $doc) }}" method="POST"
                              onsubmit="return confirm('{{ __('Delete this document?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="w-full px-3 py-2 text-left text-sm text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">
                                {{ __('Delete') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
