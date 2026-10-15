<div>
    @if (session()->has('message'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-4">
            {{ session('message') }}
        </div>
    @endif

    {{-- Form Tambah --}}
    <div class="bg-slate-50 rounded-xl p-4 mb-6">
        <h3 class="text-lg font-bold text-slate-800 mb-3">Tambah Status Baru</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <input type="text" 
                       wire:model="name" 
                       placeholder="Nama Status"
                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
            <div class="flex items-center gap-3">
                <input type="color" 
                       wire:model="color" 
                       value="#3b82f6"
                       class="w-12 h-12 rounded border-slate-300 cursor-pointer">
                <input type="text" 
                       wire:model="color" 
                       placeholder="#3b82f6"
                       class="flex-1 rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
            </div>
            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input type="checkbox" wire:model="is_default" class="w-5 h-5 rounded border-slate-300 text-accent-600">
                    Default
                </label>
                <button wire:click="save" 
                        class="px-4 py-2 bg-accent-600 hover:bg-accent-700 text-white rounded-xl transition">
                    + Tambah
                </button>
            </div>
        </div>
    </div>

    {{-- Tabel Status --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">Nama</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">Warna</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">Default</th>
                        <th class="px-6 py-4 text-right text-xs font-medium text-slate-500 dark:text-slate-200 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                    @forelse($statuses as $status)
                        <tr class="hover:bg-slate-50 transition" wire:key="status-{{ $status->id }}">
                            <td class="px-6 py-4 font-medium text-slate-800">{{ $status->name }}</td>
                            <td class="px-6 py-4">
                                <span data-status-color="{{ $status->color ?? '#3b82f6' }}" class="px-3 py-1 text-xs font-semibold rounded-full"
                                      style="background-color: {{ $status->color ?? '#3b82f6' }}20; color: {{ $status->color ?? '#3b82f6' }}">
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
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <x-icon-button icon="edit" label="Edit" title="Edit"
                                           wire:click="edit({{ $status->id }})" />
                                    <button wire:click="delete({{ $status->id }})" 
                                            onclick="return confirm('Hapus status ini?')"
                                            class="group relative overflow-hidden rounded-lg border border-red-200 bg-white px-6 py-2.5 text-sm font-medium text-red-600 transition-all duration-300 hover:scale-105 hover:border-red-600 hover:bg-red-600 hover:text-white hover:shadow-md active:scale-95 dark:border-slate-700 dark:bg-slate-800 dark:text-red-400 dark:hover:border-red-500 dark:hover:bg-red-500 dark:hover:text-white">Hapus<span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span></button>
                                </div>
                            </td>
                        </tr>

                        {{-- Row Edit --}}
                        @if($editId == $status->id)
                            <tr class="bg-accent-50">
                                <td colspan="4" class="px-6 py-4">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <input type="text" 
                                                   wire:model="editName" 
                                                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                                            @error('editName') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <input type="color" 
                                                   wire:model="editColor" 
                                                   class="w-12 h-12 rounded border-slate-300 cursor-pointer">
                                            <input type="text" 
                                                   wire:model="editColor" 
                                                   class="flex-1 rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                                        </div>
                                        <div class="flex items-center gap-4">
                                            <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                                                <input type="checkbox" wire:model="editIsDefault" class="w-5 h-5 rounded border-slate-300 text-accent-600">
                                                Default
                                            </label>
                                            <button wire:click="update" 
                                                    class="px-4 py-2 bg-accent-600 hover:bg-accent-700 text-white rounded-xl transition">
                                                Update
                                            </button>
                                            <button wire:click="cancelEdit" 
                                                    class="px-4 py-2 bg-accent-500 hover:bg-accent-600 text-white rounded-xl transition">
                                                Batal
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="4" class="py-16 text-center text-slate-400">
                                Belum ada status.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-600">
            {{ $statuses->links() }}
        </div>
    </div>
</div>
