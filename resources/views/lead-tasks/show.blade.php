<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ $task->title }}</h1>
            <p class="text-slate-500 mt-1">{{ $task->lead?->customer?->name ?? '-' }}</p>
        </div>
        <div class="flex gap-2">
            <x-icon-button as="a" href="{{ route('lead-tasks.edit', $task) }}" title="{{ __('Edit') }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path d="M4 20h4.2L18.5 9.7a2.1 2.1 0 0 0 0-3l-1.2-1.2a2.1 2.1 0 0 0-3 0L4 15.8V20z" /><path d="M13.5 6.5l4 4" /></svg>
            </x-icon-button>
            <x-icon-button as="a" icon="back" href="{{ (auth()->user()?->can('view-sales') || auth()->user()?->can('manage-sales-leads')) ? route('sales.requests.index', ['tab' => 'inside-sales']) : route('lead-tasks.index') }}" title="{{ __('Back') }}" />
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 mb-6">
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <dt class="text-xs text-slate-400">{{ __('Status') }}</dt>
                <dd class="font-medium text-slate-800 dark:text-slate-100">{{ \App\Models\LeadTask::statusLabel($task->status) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">{{ __('Priority') }}</dt>
                <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $task->priority ? ucfirst($task->priority) : '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">{{ __('Assign to') }}</dt>
                <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $task->assignee?->name ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">{{ __('Due Date') }}</dt>
                <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $task->due_date ? $task->due_date->format('d M Y') : '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">{{ __('Created by') }}</dt>
                <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $task->creator?->name ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">{{ __('Lead') }}</dt>
                <dd class="font-medium text-slate-800 dark:text-slate-100">
                    <a href="{{ route('leads.show', $task->lead) }}" class="text-accent-600 hover:underline">
                        {{ $task->lead?->customer?->name ?? '-' }}
                    </a>
                </dd>
            </div>
        </dl>

        <div>
            <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">{{ __('Description') }}</h3>
            <p class="text-slate-700 dark:text-slate-200 whitespace-pre-wrap">{{ $task->description ?: '-' }}</p>
        </div>

        <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-700">
            <form action="{{ route('lead-tasks.update', $task) }}" method="POST" class="flex flex-wrap items-end gap-3">
                @csrf @method('PUT')
                <input type="hidden" name="title" value="{{ $task->title }}">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Change Status') }}</label>
                    <select name="status"
                            class="rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @foreach(\App\Models\LeadTask::STATUSES as $status)
                            <option value="{{ $status }}" @selected($task->status === $status)>
                                {{ \App\Models\LeadTask::statusLabel($status) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit"
                        class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                    {{ __('Save Status') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </button>
            </form>
        </div>
    </div>

    {{-- Lampiran quotation dari Inside Sales — riwayat versi (tidak menimpa). --}}
    @php
        $attachmentUser = auth()->user();
        $canManageAttachment = $attachmentUser && (
            (int) $task->assigned_to === (int) $attachmentUser->id
            || (int) $task->created_by === (int) $attachmentUser->id
            || $attachmentUser->can('manage-sales-leads')
        ) && \App\Support\PtAccess::canWritePt($attachmentUser, $task->lead?->customer?->pt_group);
        $attachmentTotal = $task->attachments->count();
        $attachmentUser = auth()->user();
        $canAcceptQuotation = $attachmentUser && (
            (int) $task->created_by === (int) $attachmentUser->id
            || (int) ($task->lead?->assigned_to ?? 0) === (int) $attachmentUser->id
            || $attachmentUser->can('manage-sales-leads')
        ) && \App\Support\PtAccess::canWritePt($attachmentUser, $task->lead?->customer?->pt_group);
    @endphp

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 mb-6">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">{{ __('Quotation Attachment') }}</h3>
            <span class="text-xs text-slate-400">{{ $attachmentTotal }} {{ __('file(s)') }} · {{ __('Latest first') }}</span>
        </div>

        @if($attachmentTotal > 0)
            <ul class="mb-4 space-y-2">
                @foreach($task->attachments as $attachment)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-600 dark:bg-slate-700/40">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $attachment->original_name }}</p>
                            <p class="text-xs text-slate-400">
                                v{{ $attachmentTotal - $loop->index }} · {{ $attachment->humanSize() }}
                                · {{ $attachment->uploader?->name ?? __('System') }}
                                · {{ $attachment->created_at?->translatedFormat('d M Y H:i') }}
                            </p>
                            @if($attachment->accepted_at && $attachment->proposal)
                                <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">
                                    ✓ {{ __('Accepted') }} {{ $attachment->accepted_at->translatedFormat('d M Y H:i') }} ·
                                    <a href="{{ route('sales.proposals.show', $attachment->proposal) }}" class="font-semibold underline">
                                        {{ $attachment->proposal->proposal_number }}
                                    </a>
                                </p>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if(! $attachment->accepted_at && $canAcceptQuotation)
                                <form action="{{ route('lead-tasks.attachments.accept', [$task, $attachment]) }}" method="POST"
                                      onsubmit="return confirm('{{ __('Accept this file and create a quotation?') }}')">
                                    @csrf
                                    <x-icon-button type="submit" icon="sparkles" title="{{ __('Accept as Quotation') }}" />
                                </form>
                            @endif
                            <x-icon-button as="a" icon="download" href="{{ route('lead-tasks.attachments.download', [$task, $attachment]) }}" title="{{ __('Download') }}" />
                            @if($canManageAttachment)
                                <form action="{{ route('lead-tasks.attachments.destroy', [$task, $attachment]) }}" method="POST"
                                      onsubmit="return confirm('{{ __('Delete this quotation file?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <x-icon-button icon="delete" type="submit" title="{{ __('Delete') }}" />
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ __('No quotation file uploaded yet.') }}</p>
        @endif

        @if($canManageAttachment)
            <form action="{{ route('lead-tasks.attachments.store', $task) }}" method="POST" enctype="multipart/form-data"
                  class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-700">
                @csrf
                <div class="min-w-0 flex-1">
                    <label for="quotation-attachment" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">
                        {{ __('Upload quotation file (PDF/Word/Excel/image, max 10MB)') }}
                    </label>
                    <input id="quotation-attachment" type="file" name="attachment" required
                           accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv"
                           class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                    @error('attachment') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <button type="submit"
                        class="group relative overflow-hidden rounded-xl bg-accent-600 px-6 py-2.5 text-sm font-medium text-white transition-all duration-300 hover:bg-accent-700 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                    {{ __('Save') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </button>
            </form>
        @endif
    </div>

    <livewire:lead-task-chat :task="$task" />
</x-app-layout>
