<x-app-layout>
    @php
        $statusMeta = [
            'indexing' => ['label' => __('Indexing'), 'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'],
            'ready' => ['label' => __('Ready'), 'class' => 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300'],
            'failed' => ['label' => __('Failed'), 'class' => 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-300'],
        ];
    @endphp

    <div class="px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Knowledge Base') }}</h1>
                <p class="mt-1 text-slate-500 dark:text-slate-400">{{ __('Upload internal company documents to answer AI questions (SOP, proposal, documentation, reports).') }}</p>
            </div>
            <form action="{{ route('knowledge-base.sync') }}" method="POST" class="shrink-0">
                @csrf
                <x-icon-button icon="search" type="submit" label="{{ __('Check Indexing Status') }}" title="{{ __('Check Indexing Status') }}" />
            </form>
        </div>

        @if($errors->has('sync') || $errors->has('delete'))
            <div class="mb-4 rounded-xl bg-red-100 px-5 py-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400">
                {{ $errors->first('sync') ?? $errors->first('delete') }}
            </div>
        @endif

        {{-- Upload form --}}
        <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700">
            <h2 class="mb-4 text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Unggah Dokumen') }}</h2>
            <form action="{{ route('knowledge-base.store') }}" method="POST" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">
                @csrf

                <div>
                    <x-input-label for="document" :value="__('Document File')" />
                    <input type="file" name="document" id="document"
                           class="mt-1 block w-full rounded-xl border-slate-300 text-sm dark:border-slate-600"
                           accept=".pdf,.txt,.md,.html,.doc,.docx,.xls,.xlsx,.ppt,.pptx" required />
                    <x-input-error :messages="$errors->get('document')" class="mt-1" />
                    <p class="mt-1 text-xs text-slate-400">{{ __('PDF, text, markdown, HTML, or Office documents (max 20 MB).') }}</p>
                </div>

                <div>
                    <x-input-label for="category" :value="__('Category')" />
                    <select name="category" id="category" required
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm dark:border-slate-600">
                        <option value="">{{ __('Select category…') }}</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ $category->label() }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('category')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="project_id" :value="__('Related Project (optional)')" />
                    <select name="project_id" id="project_id"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm dark:border-slate-600">
                        <option value="">{{ __('None') }}</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>{{ $project->project_name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('project_id')" class="mt-1" />
                </div>

                <div class="flex items-end">
                    <button type="submit"
                            class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Unggah Dokumen') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Filter --}}
        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('knowledge-base.index') }}"
               class="rounded-full px-4 py-1.5 text-xs font-semibold transition {{ blank($filters['category'] ?? null) ? 'bg-accent-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                {{ __('All Categories') }}
            </a>
            @foreach($categories as $category)
                <a href="{{ route('knowledge-base.index', ['category' => $category->value, 'status' => $filters['status'] ?? null]) }}"
                   class="rounded-full px-4 py-1.5 text-xs font-semibold transition {{ ($filters['category'] ?? null) === $category->value ? 'bg-accent-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    {{ $category->label() }}
                </a>
            @endforeach
        </div>

        {{-- Table --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-600">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                    <thead class="bg-slate-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('File') }}</th>
                            <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Category') }}</th>
                            <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Project') }}</th>
                            <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Uploader') }}</th>
                            <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Date') }}</th>
                            <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Status') }}</th>
                            <th class="px-6 py-4 text-right text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                        @forelse($documents as $document)
                            <tr class="transition hover:bg-slate-50 dark:hover:bg-white/5">
                                <td class="max-w-[220px] px-6 py-4">
                                    <p class="truncate font-medium text-slate-800 dark:text-slate-100">{{ $document->original_name }}</p>
                                    <p class="text-xs text-slate-400">{{ number_format($document->size / 1024, 1) }} KB</p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                                        {{ $document->category->label() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $document->project?->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $document->uploader?->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $document->created_at->format('d M Y H:i') }}</td>
                                <td class="px-6 py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusMeta[$document->status]['class'] ?? 'bg-slate-100 text-slate-600' }}">
                                        {{ $statusMeta[$document->status]['label'] ?? $document->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <form action="{{ route('knowledge-base.destroy', $document) }}" method="POST"
                                          onsubmit="return confirm('{{ __('Delete this document from the Knowledge Base?') }}')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="group relative overflow-hidden rounded-lg border border-red-200 bg-white px-6 py-2.5 text-sm font-medium text-red-600 transition-all duration-300 hover:scale-105 hover:border-red-600 hover:bg-red-600 hover:text-white hover:shadow-md active:scale-95 dark:border-slate-700 dark:bg-slate-800 dark:text-red-400 dark:hover:border-red-500 dark:hover:bg-red-500 dark:hover:text-white">
                                            {{ __('Delete') }}
                                            <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-slate-400">{{ __('There are no documents in the Knowledge Base.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
        <div class="mt-4">{{ $documents->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>