{{-- Dokumen Proyek — dikelompokkan per project.
Props:
  $projectDocuments  Collection dokumen dengan project_id terisi
  $projects          Collection project customer
  $canUploadDocs     bool
  $customer          Customer instance untuk link arsip
--}}
@props(['projectDocuments', 'projects', 'canUploadDocs' => false, 'customer' => null])

@php
    $groupedByProject = $projectDocuments->groupBy('project_id');
@endphp

<section class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white">
            {{ __('Projects') }}
        </h3>

        @if($projects->isNotEmpty())
            <label class="relative w-full sm:w-64">
                <span class="sr-only">{{ __('Cari proyek') }}</span>
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input id="proj-doc-search" type="search" placeholder="{{ __('Cari proyek...') }}"
                       autocomplete="off"
                       class="h-11 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500 dark:focus:ring-indigo-900">
            </label>
        @endif
    </div>

    <div id="proj-doc-grid" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        @forelse ($projects as $project)
            @php
                $projectDocs = $groupedByProject->get($project->id, collect());
                $docCount = $projectDocs->count();
                $canOpenProject = auth()->user()->can('view', $project);
            @endphp

            <article class="proj-doc-card rounded-xl border border-slate-200 bg-white p-4 transition hover:border-indigo-300 hover:shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:hover:border-indigo-700"
                     data-proj-name="{{ mb_strtolower($project->project_name ?? '') }}"
                     data-proj-code="{{ mb_strtolower($project->project_code ?? '') }}">
                @if($canOpenProject)
                    <a href="{{ route('project-documents.index', $project) }}"
                       class="group flex items-center gap-3">
                @else
                    <div class="flex items-center gap-3">
                @endif
                    <span class="shrink-0 rounded-lg bg-indigo-50 p-2.5 dark:bg-indigo-950">
                        <x-icon name="folder" class="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-semibold text-slate-900 transition-colors @if($canOpenProject) group-hover:text-indigo-600 dark:group-hover:text-indigo-400 @endif dark:text-white">
                            {{ $project->project_name }}
                        </span>
                        <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">
                            {{ $docCount }} {{ __('files') }}
                        </span>
                    </span>

                    @if($canOpenProject)
                        <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-300 transition-colors group-hover:text-indigo-500 dark:text-slate-600" />
                    @endif
                @if($canOpenProject)
                    </a>
                @else
                    </div>
                @endif

                {{-- Pratinjau file proyek - thumbnail per jenis file. --}}
                @if($projectDocs->isNotEmpty())
                    <div class="mt-3 grid grid-cols-2 gap-3 xl:grid-cols-3">
                        @foreach($projectDocs->take(4) as $doc)
                            @php
                                $isModuleDoc = $doc->isModuleDocument();
                                $metaLine = $isModuleDoc
                                    ? $doc->source_label
                                    : ($doc->version > 1 ? 'v' . $doc->version : null);
                            @endphp

                            @include('customers.doc-card', [
                                'doc' => $doc,
                                'canManage' => auth()->user()->can('update', $doc),
                                'badgeText' => $isModuleDoc ? $doc->source_label : null,
                                'badgeClass' => $isModuleDoc ? 'bg-indigo-600' : null,
                                'metaLine' => $metaLine,
                            ])
                        @endforeach
                    </div>

                    @if($docCount > 4 && $canOpenProject)
                        <a href="{{ route('project-documents.index', $project) }}"
                           class="mt-3 inline-block text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                            {{ __('View all') }} ({{ $docCount }})
                        </a>
                    @endif
                @endif
            </article>
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-slate-200 p-8 text-center dark:border-slate-700">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Belum ada proyek') }}
                </p>
            </div>
        @endforelse
    </div>

    <div id="proj-doc-no-results" class="hidden py-8 text-center text-sm text-slate-400">
        {{ __('Tidak ada proyek yang cocok.') }}
    </div>
</section>

{{-- Pencarian proyek (client-side, hanya menyaring kartu yang sudah dirender). --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const search = document.getElementById('proj-doc-search');
        const grid = document.getElementById('proj-doc-grid');
        const noResults = document.getElementById('proj-doc-no-results');

        if (!search || !grid) return;

        search.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            let visible = 0;

            grid.querySelectorAll('.proj-doc-card').forEach((card) => {
                const haystack = `${card.dataset.projName || ''} ${card.dataset.projCode || ''}`;
                const match = !q || haystack.includes(q);
                card.classList.toggle('hidden', !match);
                if (match) visible += 1;
            });

            noResults?.classList.toggle('hidden', visible > 0);
        });
    });
</script>
