<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">{{ __('Document Category') }}</h1>
                <p class="text-slate-500 mt-1">{{ __('Kelola kategori dokumen') }}</p>
            </div>
            <x-icon-button as="a" icon="add" href="{{ route('admin-panel.document-categories.create') }}" label="{{ __('Tambah Kategori') }}" title="{{ __('Tambah Kategori') }}" />
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                    <thead class="bg-slate-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Nama Kategori') }}</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Dibuat Pada') }}</th>
                            <th class="px-6 py-4 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                        @forelse($categories as $category)
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-6 py-4 font-medium text-slate-800">{{ __($category->name) }}</td>
                                <td class="px-6 py-4 text-slate-600">{{ $category->created_at->format('d M Y H:i') }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-3">
                                        <x-icon-button as="a" icon="edit" href="{{ route('admin-panel.document-categories.edit', $category) }}" title="{{ __('Edit') }}" />
                                        <form action="{{ route('admin-panel.document-categories.destroy', $category) }}" 
                                              method="POST" 
                                              onsubmit="return confirm('{{ __('Hapus kategori ini?') }}')"
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
                                <td colspan="3" class="py-16 text-center text-slate-400">
                                    {{ __('Belum ada kategori dokumen.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout> 