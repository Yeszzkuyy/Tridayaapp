<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">{{ __('Preview Dokumen') }}</h1>
                <p class="text-slate-500 mt-1">{{ $document->file_name }}</p>
            </div>
            <div class="flex gap-2">
                <x-icon-button as="a" icon="download" href="{{ route('project-documents.download', $document) }}"
                               label="{{ __('Download') }}" title="{{ __('Download') }}" />
                <x-icon-button as="a" icon="back" href="{{ route('project-documents.index', $document->project) }}" title="{{ __('Back') }}" />
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            @php
                $extension = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION));
                $fileUrl = route('project-documents.preview', $document);
            @endphp

            {{-- PDF --}}
            @if($extension == 'pdf')
                <embed src="{{ $fileUrl }}" type="application/pdf" width="100%" height="700px" />

            {{-- Gambar --}}
            @elseif(in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp']))
                <div class="flex justify-center">
                    <img src="{{ $fileUrl }}" alt="{{ $document->file_name }}" class="max-w-full max-h-[700px] object-contain">
                </div>

            {{-- Video --}}
            @elseif(in_array($extension, ['mp4', 'webm', 'ogg', 'mov', 'avi']))
                <video controls class="w-full max-h-[700px]">
                    <source src="{{ $fileUrl }}" type="video/mp4">
                    {{ __('Browser Anda tidak mendukung video.') }}
                </video>

            {{-- Audio --}}
            @elseif(in_array($extension, ['mp3', 'wav', 'ogg']))
                <div class="flex justify-center py-20">
                    <audio controls class="w-full max-w-2xl">
                        <source src="{{ $fileUrl }}" type="audio/mpeg">
                        {{ __('Browser Anda tidak mendukung audio.') }}
                    </audio>
                </div>

            {{-- Office (Word, Excel, PPT) --}}
            @elseif(in_array($extension, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']))
                <div class="text-center py-20">
                    <svg class="w-24 h-24 text-slate-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-slate-600 font-medium">{{ __('File Office tidak bisa di-preview di browser') }}</p>
                    <p class="text-slate-400 text-sm mt-2">{{ __('Silakan download untuk membuka file') }}</p>
                    <x-icon-button as="a" icon="download" href="{{ route('project-documents.download', $document) }}"
                                   label="{{ __('Download File') }}" title="{{ __('Download File') }}" class="mt-4" />
                </div>

            {{-- Lainnya --}}
            @else
                <div class="text-center py-20">
                    <svg class="w-24 h-24 text-slate-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-slate-600 font-medium">{{ __('Preview tidak tersedia untuk file ini') }}</p>
                    <x-icon-button as="a" icon="download" href="{{ route('project-documents.download', $document) }}"
                                   label="{{ __('Download File') }}" title="{{ __('Download File') }}" class="mt-4" />
                </div>
            @endif
        </div>
    </div>
</x-app-layout>