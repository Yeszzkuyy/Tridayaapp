{{--
    Kartu dokumen ala Drive (klik = preview dalam layar).

    Props:
      $doc        ProjectDocument
      $canManage  bool  tampilkan tombol rename & hapus
      $badgeClass string  kelas warna badge pojok kiri atas
      $badgeText  string  isi badge (mis. "INV", "PO", "PAY")
      $metaLine   string  baris info di bawah nama file
--}}
@props(['doc', 'canManage' => false, 'badgeClass' => 'bg-slate-500', 'badgeText' => null, 'metaLine' => null])

@php
    $docExt = strtolower(pathinfo($doc->file_name, PATHINFO_EXTENSION));
    $isImage = in_array($docExt, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    $isPdf = $docExt === 'pdf';
    $isVideo = in_array($docExt, ['mp4', 'webm', 'ogg', 'mov', 'avi']);
    $isAudio = in_array($docExt, ['mp3', 'wav', 'ogg']);
    $docKind = $isImage ? 'image' : ($isPdf ? 'pdf' : ($isVideo ? 'video' : ($isAudio ? 'audio' : 'other')));
    $badgeClass = $badgeClass ?: match (true) {
        $isPdf => 'bg-red-500',
        $isImage => 'bg-green-500',
        in_array($docExt, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']) => 'bg-blue-500',
        in_array($docExt, ['zip', 'rar']) => 'bg-amber-500',
        default => 'bg-slate-500',
    };
    $metaLine = $metaLine ?: ($doc->project?->project_name ?? __('General'))
        . ' · ' . number_format($doc->file_size / 1024, 1) . ' KB';
@endphp

<div class="doc-card overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-900/50"
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
        @if($isImage)
            <img src="{{ route('project-documents.preview', $doc) }}" alt="{{ $doc->file_name }}" loading="lazy" onerror="this.remove()"
                 class="pointer-events-none relative h-full w-full object-cover">
        @elseif($isPdf)
            <canvas data-pdf-url="{{ route('project-documents.preview', $doc) }}" class="pointer-events-none relative h-full w-full"></canvas>
        @endif
        @if(! $isImage)
            <svg class="absolute h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        @endif
        <span class="absolute left-2 top-2 rounded px-1.5 py-0.5 text-[11px] font-bold uppercase text-white {{ $badgeClass }}">{{ $badgeText ?: ($docExt ?: __('File')) }}</span>
    </div>
    <div class="p-3">
        <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $doc->file_name }}</p>
        <p class="mt-0.5 truncate text-xs text-slate-400">{{ $metaLine }}</p>
        <div class="mt-2 flex gap-1.5">
            <x-icon-button icon="view" title="{{ __('Preview') }}" data-doc-open-child />
            <x-icon-button as="a" icon="download" href="{{ route('project-documents.download', $doc) }}" title="{{ __('Download') }}" />
            @if($canManage)
                <x-icon-button icon="edit" title="{{ __('Rename') }}"
                               data-doc-rename
                               data-action="{{ route('project-documents.rename', $doc) }}"
                               data-name="{{ $doc->file_name }}" />
                <form action="{{ route('project-documents.destroy', $doc) }}" method="POST" class="inline-flex"
                      onsubmit="return confirm('{{ __('Delete this document?') }}')">
                    @csrf @method('DELETE')
                    <x-icon-button icon="delete" type="submit" title="{{ __('Delete') }}" />
                </form>
            @endif
        </div>
    </div>
</div>
