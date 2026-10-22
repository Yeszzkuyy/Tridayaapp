{{--
    Kartu project di tab Projects halaman customer.

    Isi: nama + status + jenis pekerjaan, progress bar, PIC Engineer
    (ditampilkan menonjol dengan avatar inisial), deskripsi singkat,
    ringkasan progres kerja (task selesai/total + jumlah dokumen), dan
    aksi icon-only.

    Data yang dihitung (tasks_count, tasks_done_count, documents_count)
    di-eager-load di CustomerController::show supaya tidak N+1.

    Props:
      $project  Project
--}}
@props(['project'])

@php
    $pic = trim((string) $project->pic_engineer);
    $initials = $pic !== ''
        ? collect(preg_split('/\s+/', $pic))
            ->filter()
            ->take(2)
            ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('')
        : '?';

    $progress = max(0, min(100, (int) ($project->progress ?? 0)));
    $tasksDone = (int) ($project->tasks_done_count ?? 0);
    $tasksTotal = (int) ($project->tasks_count ?? 0);
    $documentsCount = (int) ($project->documents_count ?? 0);
@endphp

<div class="flex h-full flex-col rounded-xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-900/50">
    {{-- Nama + status --}}
    <div class="flex items-start justify-between gap-2">
        <a href="{{ route('projects.show', $project) }}"
           class="min-w-0 flex-1 text-sm font-bold leading-snug text-slate-800 transition hover:text-accent-600 dark:text-slate-100 dark:hover:text-accent-400">
            <span class="line-clamp-2">{{ $project->project_name }}</span>
        </a>
        <div class="shrink-0">
            <x-status-badge :color="$project->status?->color ?? 'slate'">
                {{ $project->status?->name ?? __('Not Started') }}
            </x-status-badge>
        </div>
    </div>

    @if($project->workType?->name)
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ $project->workType->name }}</p>
    @endif

    {{-- Progress pengerjaan --}}
    <div class="mt-3">
        <div class="mb-1 flex items-center justify-between text-[11px] font-medium text-slate-500 dark:text-slate-400">
            <span>{{ __('Progress') }}</span>
            <span>{{ $progress }}%</span>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
            <div class="h-full rounded-full bg-accent-600" style="width: {{ $progress }}%"></div>
        </div>
    </div>

    {{-- PIC Engineer — informasi utama di kartu --}}
    <div class="mt-3 flex items-center gap-2.5 rounded-lg bg-slate-50 p-2.5 dark:bg-slate-800/60">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-accent-100 text-xs font-bold text-accent-700 dark:bg-accent-500/20 dark:text-accent-300">
            {{ $initials }}
        </span>
        <div class="min-w-0">
            <p class="text-[10px] uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ __('PIC Engineer') }}</p>
            <p class="truncate text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $pic !== '' ? $pic : '-' }}</p>
        </div>
    </div>

    {{-- Deskripsi singkat --}}
    @if($project->description)
        <p class="mt-3 line-clamp-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $project->description }}</p>
    @endif

    {{-- Ringkasan progres kerja --}}
    <div class="mt-3 flex flex-wrap items-center gap-1.5 text-[11px]">
        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-1 font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
            <x-icon name="check-circle" class="h-3.5 w-3.5" />
            {{ $tasksDone }}/{{ $tasksTotal }} {{ __('Tasks') }}
        </span>
        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-1 font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
            <x-icon name="file-text" class="h-3.5 w-3.5" />
            {{ $documentsCount }} {{ __('Documents') }}
        </span>
    </div>

    {{-- Aksi: icon-only sesuai aturan UI --}}
    <div class="mt-auto flex items-center justify-end gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
        <x-icon-button as="a" icon="view" href="{{ route('projects.show', $project) }}" title="{{ __('View') }}" />
        @can('update', $project)
            <x-icon-button as="a" icon="edit" href="{{ route('projects.edit', $project) }}" title="{{ __('Edit') }}" />
        @endcan
        @can('delete', $project)
            <form action="{{ route('projects.destroy', $project) }}" method="POST" class="m-0 inline-flex"
                  onsubmit="return confirm('{{ __('Delete this project?') }}')">
                @csrf @method('DELETE')
                <x-icon-button icon="delete" type="submit" title="{{ __('Delete') }}" />
            </form>
        @endcan
    </div>
</div>
