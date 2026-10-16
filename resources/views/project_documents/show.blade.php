<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Detail Dokumen
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Project</dt>
                        <dd class="text-gray-900">{{ $projectDocument->project->project_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Kategori Dokumen</dt>
                        <dd>
                            <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700">
                                {{ $projectDocument->category->name ?? '-' }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Nama File</dt>
                        <dd class="text-gray-900">{{ $projectDocument->file_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Diupload oleh</dt>
                        <dd class="text-gray-900">{{ $projectDocument->uploader->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Tanggal Upload</dt>
                        <dd class="text-gray-900">{{ $projectDocument->created_at->format('d M Y, H:i') }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Catatan</dt>
                        <dd class="text-gray-900">{{ $projectDocument->notes ?? '-' }}</dd>
                    </div>
                </dl>

                <div class="mt-6 flex items-center gap-4">
                    <x-icon-button as="a" icon="view" href="{{ route('project-documents.preview', $projectDocument) }}"
                                   target="_blank" label="Lihat / Download File" title="Lihat / Download File" />
                    <x-icon-button as="a" icon="back" href="{{ route('projects.show', $projectDocument->project) }}" title="Kembali ke project" />
                </div>
            </div>

        </div>
    </div>
</x-app-layout>