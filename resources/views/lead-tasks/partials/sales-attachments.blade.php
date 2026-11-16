{{-- Lampiran pendukung dari Sales (brief/requirement), terpisah dari file quotation. --}}
@php
    $fileUser = auth()->user();
    $canUploadRequestFiles = $fileUser && (
        (int) $task->created_by === (int) $fileUser->id
        || (int) $task->lead?->assigned_to === (int) $fileUser->id
        || $fileUser->can('manage-sales-leads')
    );
@endphp
<div class="mb-6 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800/60">
        <div class="flex items-center gap-3.5">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-accent-500/10 text-accent-600 dark:bg-accent-500/15 dark:text-accent-300">
                <x-icon name="file-text" class="h-5 w-5" />
            </span>
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('Supporting Attachments') }}</h3>
        </div>
        <span class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-500 dark:border-slate-700 dark:text-slate-400">
            {{ $task->requestFiles->count() }} {{ __('file(s)') }}
        </span>
    </div>

    @if($task->requestFiles->isNotEmpty())
        <ul class="divide-y divide-slate-100 dark:divide-slate-800/60">
            @foreach($task->requestFiles as $file)
                @php
                    $extension = strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));
                    [$fileBoxTone, $fileIconTone] = match (true) {
                        $extension === 'pdf' => ['bg-rose-500/10', 'text-rose-600 dark:text-rose-400'],
                        in_array($extension, ['doc', 'docx']) => ['bg-blue-500/10', 'text-blue-600 dark:text-blue-400'],
                        in_array($extension, ['xls', 'xlsx', 'csv']) => ['bg-emerald-500/10', 'text-emerald-600 dark:text-emerald-400'],
                        in_array($extension, ['jpg', 'jpeg', 'png', 'webp']) => ['bg-sky-500/10', 'text-sky-600 dark:text-sky-400'],
                        $extension === 'zip' => ['bg-amber-500/10', 'text-amber-600 dark:text-amber-400'],
                        default => ['bg-slate-500/10', 'text-slate-600 dark:text-slate-300'],
                    };
                @endphp
                <li class="flex flex-wrap items-center gap-3 px-4 py-3 transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-700/35">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $fileBoxTone }} {{ $fileIconTone }}">
                        <x-icon name="file-text" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('lead-tasks.request-files.download', [$task, $file]) }}"
                           title="{{ $file->original_name }}"
                           class="block truncate text-sm font-semibold text-slate-800 hover:text-accent-600 dark:text-slate-100 dark:hover:text-accent-400">{{ $file->original_name }}</a>
                        <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                            <span class="font-semibold uppercase">{{ $extension !== '' ? $extension : __('File') }}</span>
                            <span class="mx-1 text-slate-300 dark:text-slate-600">·</span>{{ $file->humanSize() }}
                            <span class="mx-1 text-slate-300 dark:text-slate-600">·</span>{{ $file->uploader?->name ?? '-' }}
                            <span class="mx-1 text-slate-300 dark:text-slate-600">·</span>{{ $file->created_at?->format('d M Y H:i') }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <x-icon-button as="a" icon="download"
                                       href="{{ route('lead-tasks.request-files.download', [$task, $file]) }}"
                                       title="{{ __('Download') }}" />
                        @if($fileUser && ((int) $file->uploaded_by === (int) $fileUser->id || $fileUser->can('manage-sales-leads')))
                            <form action="{{ route('lead-tasks.request-files.destroy', [$task, $file]) }}" method="POST"
                                  data-confirm="{{ __('Delete this supporting file?') }}" data-confirm-tone="danger">
                                @csrf @method('DELETE')
                                <x-icon-button icon="delete" type="submit" title="{{ __('Delete') }}" />
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <p class="px-5 py-5 text-sm text-slate-500 dark:text-slate-400">{{ __('No supporting attachments yet.') }}</p>
    @endif

    @if($canUploadRequestFiles)
        <form action="{{ route('lead-tasks.request-files.store', $task) }}" method="POST" enctype="multipart/form-data"
              class="space-y-3 border-t border-slate-100 px-5 py-5 dark:border-slate-800/60">
            @csrf
            <x-file-drop id="request-files" />
            @error('request_files') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
            @error('request_files.*') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
            <div class="flex justify-end">
                <x-icon-button type="submit" icon="upload" label="{{ __('Upload') }}" title="{{ __('Upload') }}" />
            </div>
        </form>
    @endif
</div>
