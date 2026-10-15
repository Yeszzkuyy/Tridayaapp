<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ $isSuperAdmin ? 'All Trash' : __('My Trash') }}</h1>
                <p class="text-slate-500 mt-1">{{ $isSuperAdmin ? __('Seluruh data terhapus dari semua user') : __('Data milikmu yang telah dihapus') }}</p>
            </div>

            <div class="flex items-center gap-3">
                @if($isSuperAdmin && $users && $users->isNotEmpty())
                    <form method="GET" action="{{ route('trash.index') }}">
                        <select name="user" onchange="this.form.submit()"
                                class="rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200">
                            <option value="">{{ __('All Owners') }}</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ request('user') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                @endif
                <form action="{{ route('trash.clear') }}" method="POST"
                      onsubmit="return confirm('{{ __('Permanently delete these items? This action cannot be undone.') }}')">
                    @csrf
                    @method('DELETE')
                    <x-icon-button icon="delete" type="submit" title="{{ $isSuperAdmin ? __('Empty Trash') : __('Empty My Trash') }}" />
                </form>
                <x-icon-button as="a" icon="back" href="{{ route('customers.index') }}" title="Back" />
            </div>
        </div>

        {{-- Customer Trash --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700">
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-200">{{ __('Customer Terhapus') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Nama Customer') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">Email</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Owner') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Dihapus Oleh') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Dihapus Pada') }}</th>
                            <th class="px-6 py-4 text-right text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                        @forelse($customers as $customer)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-100">{{ $customer->name }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $customer->email ?? '-' }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $ownerNames[$customerOwners[$customer->id] ?? null] ?? '-' }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $customer->deleter?->name ?? '-' }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $customer->deleted_at->format('d M Y H:i') }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                        @can('restore', $customer)
                                        <form action="{{ route('trash.restore-customer', $customer->id) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <x-icon-button icon="reset" type="submit" title="Restore" />
                                        </form>
                                        @endcan
                                        @can('forceDelete', $customer)
                                        <x-icon-button icon="delete" type="button" x-data=""
                                                       title="Delete permanently"
                                                       @click="$dispatch('open-modal', 'confirm-destroy-customer-{{ $customer->id }}')" />
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-16 text-center text-slate-400">
                                    {{ __('Tidak ada customer yang terhapus.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">{{ $customers->links() }}</div>
        </div>

        @foreach($customers as $customer)
            <x-modal name="confirm-destroy-customer-{{ $customer->id }}" maxWidth="md">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                            <x-icon name="trash" class="w-5 h-5 text-red-600" />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Delete permanently?') }}</h3>
                            <p class="text-sm text-slate-500 mt-1">
                                {{ __('Customer') }} "{{ $customer->name }}" {{ __('will be deleted') }} <b>{{ __('forever') }}</b>.
                            </p>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" @click="$dispatch('close')"
                                class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-white dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700 text-sm font-medium transition-colors duration-200">
                            {{ __('Cancel') }}
                        </button>
                        <form action="{{ route('trash.destroy-customer', $customer->id) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button class="group relative overflow-hidden inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-6 py-2.5 text-sm font-medium text-red-600 transition-all duration-300 hover:scale-105 hover:border-red-600 hover:bg-red-600 hover:text-white hover:shadow-md active:scale-95 dark:border-slate-700 dark:bg-slate-800 dark:text-red-400 dark:hover:border-red-500 dark:hover:bg-red-500 dark:hover:text-white">
                                <x-icon name="trash" class="w-4 h-4" />
                                {{ __('Yes, delete permanently') }}
                                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                            </button>
                        </form>
                    </div>
                </div>
            </x-modal>
        @endforeach

        {{-- Project Trash --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700">
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-200">{{ __('Project Terhapus') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Nama Project') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">Customer</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Owner') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Dihapus Oleh') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Dihapus Pada') }}</th>
                            <th class="px-6 py-4 text-right text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                        @forelse($projects as $project)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-100">{{ $project->project_name }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $project->customer?->name ?? '-' }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $ownerNames[$projectOwners[$project->id] ?? null] ?? '-' }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $project->deleter?->name ?? '-' }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $project->deleted_at->format('d M Y H:i') }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                        @can('restore', $project)
                                        <form action="{{ route('trash.restore-project', $project->id) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <x-icon-button icon="reset" type="submit" title="Restore" />
                                        </form>
                                        @endcan
                                        @can('forceDelete', $project)
                                        <x-icon-button icon="delete" type="button" x-data=""
                                                       title="Delete permanently"
                                                       @click="$dispatch('open-modal', 'confirm-destroy-project-{{ $project->id }}')" />
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-16 text-center text-slate-400">
                                    {{ __('Tidak ada project yang terhapus.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">{{ $projects->links() }}</div>
        </div>

        @foreach($projects as $project)
            <x-modal name="confirm-destroy-project-{{ $project->id }}" maxWidth="md">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                            <x-icon name="trash" class="w-5 h-5 text-red-600" />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Delete permanently?') }}</h3>
                            <p class="text-sm text-slate-500 mt-1">
                                {{ __('Project') }} "{{ $project->project_name }}" {{ __('will be deleted') }} <b>{{ __('forever') }}</b>.
                            </p>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" @click="$dispatch('close')"
                                class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-white dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700 text-sm font-medium transition-colors duration-200">
                            {{ __('Cancel') }}
                        </button>
                        <form action="{{ route('trash.destroy-project', $project->id) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button class="group relative overflow-hidden inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-6 py-2.5 text-sm font-medium text-red-600 transition-all duration-300 hover:scale-105 hover:border-red-600 hover:bg-red-600 hover:text-white hover:shadow-md active:scale-95 dark:border-slate-700 dark:bg-slate-800 dark:text-red-400 dark:hover:border-red-500 dark:hover:bg-red-500 dark:hover:text-white">
                                <x-icon name="trash" class="w-4 h-4" />
                                {{ __('Yes, delete permanently') }}
                                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                            </button>
                        </form>
                    </div>
                </div>
            </x-modal>
        @endforeach
    </div>
</x-app-layout>
