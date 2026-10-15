<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Project Status</h1>
                <p class="text-slate-500 mt-1">{{ __('Kelola status project') }}</p>
            </div>
            <x-icon-button as="a" icon="add" href="{{ route('admin-panel.project-statuses.create') }}" label="{{ __('+ Tambah Status') }}" title="{{ __('+ Tambah Status') }}" />
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                    <thead class="bg-slate-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Nama Status') }}</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Warna') }}</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">Default</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Dibuat Pada') }}</th>
                            <th class="px-6 py-4 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                        @forelse($statuses as $status)
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-6 py-4 font-medium text-slate-800">{{ $status->name }}</td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: {{ $status->color ?? '#gray' }}20; color: {{ $status->color ?? '#gray' }}">
                                        {{ $status->color ?? 'Default' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @if($status->is_default)
                                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-700">Primary</span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-slate-600">{{ $status->created_at->format('d M Y H:i') }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-3">
                                        <x-icon-button as="a" icon="edit" href="{{ route('admin-panel.project-statuses.edit', $status) }}" title="Edit" />
                                        <form action="{{ route('admin-panel.project-statuses.destroy', $status) }}" 
                                              method="POST" 
                                              onsubmit="return confirm('{{ __('Hapus status ini?') }}')"
                                              class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <x-icon-button icon="delete" type="submit" title="{{ __('Hapus') }}" />
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-16 text-center text-slate-400">
                                    {{ __('Belum ada status project.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>