<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Admin Panel') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Manajemen User, Role & Permission (Super Admin)') }}</p>
        </div>
        <div class="flex gap-2">
            <x-icon-button as="a" href="{{ route('admin-panel.roles.create') }}" title="{{ __('Add Role') }}">
                <x-icon name="users" class="h-5 w-5" />
            </x-icon-button>
            <x-icon-button as="a" href="{{ route('admin-panel.users.create') }}" title="{{ __('Add User') }}">
                <x-icon name="user" class="h-5 w-5" />
            </x-icon-button>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl text-green-800">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-800">
            {{ session('error') }}
        </div>
    @endif

    {{-- Users --}}
    <div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
            <table class="w-full">
                <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-200 uppercase tracking-wider w-16">{{ __('Foto') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-200 uppercase tracking-wider">{{ __('Nama') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-200 uppercase tracking-wider">{{ __('Email') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-200 uppercase tracking-wider">{{ __('Role') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-200 uppercase tracking-wider">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-200 uppercase tracking-wider">{{ __('Dibuat') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-200 uppercase tracking-wider">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-600">
                    @forelse($users as $user)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                            <td class="px-4 py-3">
                                <x-user-avatar :user="$user" size="w-10 h-10" text="text-sm" clickable />
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900 dark:text-slate-100">{{ $user->name }}</p>
                                @if($user->id === auth()->id())
                                    <span class="text-xs text-accent-600">{{ __('(Anda)') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $user->email }}</td>
                            <td class="px-4 py-3">
                                @forelse($user->roles as $role)
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 mr-1 mb-1">{{ $role->name }}</span>
                                @empty
                                    <span class="text-slate-400 text-sm">-</span>
                                @endforelse
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                                    {{ $user->trashed() ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                    {{ $user->trashed() ? __('Nonaktif') : __('Aktif') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">{{ $user->created_at->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <x-icon-button as="a" icon="edit" href="{{ route('admin-panel.users.edit', $user) }}" title="{{ __('Edit user') }}" />
                                @if($user->id !== auth()->id())
                                    <form action="{{ route('admin-panel.users.destroy', $user) }}" method="POST" class="inline" onsubmit="return confirm('{{ __('Hapus user ini?') }}')">
                                        @csrf @method('DELETE')
                                        <x-icon-button icon="delete" type="submit" title="{{ __('Delete user') }}" />
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-slate-400">{{ __('Tidak ada data user.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- Pagination --}}
            @if($users->hasPages())
                <div class="p-4 border-t border-slate-200">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>

</x-app-layout>