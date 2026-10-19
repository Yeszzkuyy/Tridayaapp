<x-app-layout>
<div class="max-w-[1400px] mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Document Repository') }}</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1">{{ __('All technical project documents') }}</p>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" class="flex flex-wrap gap-3">
        <select name="project_id" class="rounded-xl border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
            <option value="">{{ __('All Projects') }}</option>
            @foreach($documents->pluck('project')->unique('id') as $p)
                <option value="{{ $p->id }}" {{ request('project_id') == $p->id ? 'selected' : '' }}>{{ $p->project_name }}</option>
            @endforeach
        </select>
        <select name="document_category_id" class="rounded-xl border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
            <option value="">{{ __('All Categories') }}</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ request('document_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
            @endforeach
        </select>
        <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
    </form>

    {{-- Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-6 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Project') }}</th>
                        <th class="px-6 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Customer') }}</th>
                        <th class="px-6 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Category') }}</th>
                        <th class="px-6 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('File') }}</th>
                        <th class="px-6 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Uploaded By') }}</th>
                        <th class="px-6 py-3.5 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                    @forelse($documents as $doc)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                            <td class="px-6 py-4 text-sm font-medium text-slate-800 dark:text-slate-100">{{ $doc->project->project_name ?? '-' }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $doc->project->customer->name ?? '-' }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $doc->category->name ?? '-' }}</td>
                            <td class="px-6 py-4 text-sm">
                                <a href="{{ route('project-documents.preview', $doc) }}" target="_blank" class="text-accent-600 hover:text-accent-700 dark:text-accent-400 dark:hover:text-accent-300 font-medium">{{ $doc->file_name }}</a>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $doc->uploader->name ?? '-' }}</td>
                            <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">{{ $doc->created_at->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center text-sm text-slate-400 dark:text-slate-500">{{ __('No documents yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        <div class="mt-4">{{ $documents->links() }}</div>
        </div>
    </div>
</div>
</x-app-layout>
