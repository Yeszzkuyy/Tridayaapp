{{--
    Tombol "lihat file" (ikon mata) untuk jendela pratinjau bersama.

    <x-file-preview :url="route('project-documents.preview', $doc)"
                    :download="route('project-documents.download', $doc)"
                    :name="$doc->file_name" />

    url      : endpoint yang menyajikan file (wajib).
    download : endpoint unduhan asli (opsional, fallback ke url).
    name     : nama file asli, dipakai untuk menentukan jenis pratinjau.
    label    : isi bila tombol perlu teks di samping ikon.
--}}
@props(['url', 'download' => null, 'name' => '', 'label' => null])

@php
    $kind = \App\Rules\SecureFile::previewKind((string) $name);
@endphp

<x-icon-button icon="view" :label="$label" title="{{ __('View') }}"
    data-doc-open
    data-kind="{{ $kind }}"
    data-url="{{ $url }}"
    data-download="{{ $download ?: $url }}"
    data-filename="{{ $name }}" {{ $attributes }} />
