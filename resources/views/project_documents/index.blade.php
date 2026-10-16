<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">{{ __('Project Documents') }}</h1>
                <p class="text-slate-500 mt-1">
                    {{ $project->project_name }} · {{ $project->customer?->name ?? '' }}
                </p>
            </div>
            <div class="flex gap-2">
                <x-icon-button as="a" icon="back" href="{{ route('customers.show', $project->customer_id) }}" title="{{ __('Back') }}" />
            </div>
        </div>
        {{-- Upload Form --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-6">
            <h3 class="text-lg font-bold text-slate-800 mb-4">{{ __('Upload Dokumen') }}</h3>
            
            <form action="{{ route('project-documents.store', $project) }}" 
                  method="POST" 
                  enctype="multipart/form-data"
                  x-data="{ dragging: false, fileName: '' }">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Category') }}</label>
                        <select name="document_category_id" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                            <option value="">{{ __('Tanpa Kategori') }}</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Notes') }}</label>
                        <input type="text" name="notes" placeholder="{{ __('Document notes') }}" 
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    </div>
                </div>
                {{-- Drag & Drop Zone --}}
                <div class="mt-4">
                    <div class="relative border-2 border-dashed rounded-xl p-4 text-center transition-all duration-200"
                         :class="dragging ? 'border-accent-500 bg-accent-50 dark:bg-accent-900/40 dark:border-accent-400' : 'border-slate-300 dark:border-slate-600 hover:border-accent-500 hover:bg-accent-50 dark:hover:bg-accent-900/40'"
                         @dragover.prevent="dragging = true"
                         @dragenter.prevent="dragging = true"
                         @dragleave.prevent="dragging = false"
                         @drop.prevent="dragging = false; if ($event.dataTransfer.files.length) { $refs.fileInput.files = $event.dataTransfer.files; fileName = $event.dataTransfer.files[0].name; }">
                        <input type="file" 
                               name="file" 
                               x-ref="fileInput"
                               @change="fileName = $event.target.files.length ? $event.target.files[0].name : ''"
                               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                        <svg class="w-8 h-8 text-slate-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="text-slate-600 font-medium text-sm">{{ __('Drag & drop files here') }}</p>
                        <p class="text-slate-400 text-xs">{{ __('or click to browse') }}</p>
                        <p class="text-xs text-slate-400 mt-1">{{ __('Maximum 20MB') }}</p>
                        <p class="text-sm text-slate-500 mt-2" x-text="fileName || '{{ __('No file selected') }}'"></p>
                        @error('file')
                            <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit"
                        class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Send') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Daftar Dokumen --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                <h3 class="text-lg font-bold text-slate-800">{{ __('Document List') }}</h3>
            </div>
            
            @if($documents->isNotEmpty())
                <div class="divide-y divide-slate-100">
                    @foreach($documents as $document)
                        <div class="flex items-center justify-between px-6 py-4 hover:bg-slate-50 transition">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-accent-100 text-accent-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-medium text-slate-800">{{ $document->file_name }}</p>
                                    <div class="flex items-center gap-3 text-xs text-slate-500">
                                        <span>{{ $document->category?->name ?? __('Tanpa Kategori') }}</span>
                                        <span>•</span>
                                        <span>{{ __('Uploaded by:') }} {{ $document->uploader?->name ?? '-' }}</span>
                                        <span>•</span>
                                        <span>{{ $document->created_at->format('d M Y H:i') }}</span>
                                    </div>
                                    @if($document->notes)
                                        <p class="text-sm text-slate-500 mt-1">{{ $document->notes }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <x-icon-button as="a" icon="view" href="{{ route('project-documents.preview', $document) }}"
                                               target="_blank" label="{{ __('Preview') }}" title="{{ __('Preview') }}" />
                                <x-icon-button as="a" icon="download" href="{{ route('project-documents.download', $document) }}"
                                               label="{{ __('Download') }}" title="{{ __('Download') }}" />
                                @can('manage-technician')
                                    <form action="{{ route('project-documents.destroy', $document) }}" 
                                          method="POST" 
                                          onsubmit="return confirm('{{ __('Delete this document?') }}')"
                                          class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-button icon="delete" type="submit" label="{{ __('Delete') }}" title="{{ __('Delete') }}" />
                                    </form>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-16 text-center text-slate-400">
                    {{ __('No documents yet. Upload your first document!') }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>