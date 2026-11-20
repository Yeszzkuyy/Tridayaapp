<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-content-primary dark:text-content-primary">{{ $isSuperAdmin ? __('All Trash') : __('My Trash') }}</h1>
                <p class="text-content-secondary mt-1">{{ $isSuperAdmin ? __('All deleted data from every user') : __('Your deleted data') }}</p>
            </div>

            <div class="flex items-center gap-3">
                @if($isSuperAdmin && $users && $users->isNotEmpty())
                    <form method="GET" action="{{ route('trash.index') }}">
                        <select name="user" onchange="this.form.submit()"
                                class="rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm text-content-secondary dark:text-content-primary">
                            <option value="">{{ __('All Owners') }}</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ request('user') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                @endif
                @if(auth()->user()->hasRole('super-admin') || auth()->user()->can('manage-technician') || auth()->user()->can('manage-sales'))
                <form action="{{ route('trash.clear') }}" method="POST"
                      data-confirm="{{ __('Permanently delete these items? This action cannot be undone.') }}" data-confirm-tone="danger">
                    @csrf
                    @method('DELETE')
                    <x-icon-button icon="delete" type="submit" title="{{ $isSuperAdmin ? __('Empty Trash') : __('Empty My Trash') }}" />
                </form>
                @endif
                <x-icon-button as="a" icon="back" href="{{ route('customers.index') }}" title="{{ __('Back') }}" />
            </div>
        </div>

        {{-- Document Trash --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700">
                <h2 class="text-lg font-bold text-content-primary dark:text-content-primary">
                    {{ __('Deleted Documents') }}
                    <span class="ml-2 inline-flex items-center rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-semibold text-content-secondary dark:bg-slate-600 dark:text-content-primary">{{ $documents->total() }}</span>
                </h2>
                <p class="mt-1 text-sm text-content-secondary dark:text-content-secondary">
                    {{ __('Documents in Trash are permanently deleted automatically :days days after the deleted date.', ['days' => \App\Models\ProjectDocument::TRASH_RETENTION_DAYS]) }}
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-content-secondary dark:text-content-primary">{{ __('Document') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-content-secondary dark:text-content-primary">{{ __('Customer / Project') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-content-secondary dark:text-content-primary">{{ __('Deleted By') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-content-secondary dark:text-content-primary">{{ __('Deleted At') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-content-secondary dark:text-content-primary">{{ __('Auto-deleted on') }}</th>
                            <th class="px-6 py-4 text-right text-xs uppercase tracking-wider text-content-secondary dark:text-content-primary">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                        @forelse($documents as $document)
                            @php($daysLeft = $document->trashDaysLeft())
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-content-primary dark:text-content-primary">{{ $document->file_name }}</p>
                                    <p class="text-xs text-content-secondary dark:text-content-tertiary">
                                        {{ \App\Services\DocumentRegistry::TYPE_LABELS[$document->doc_type] ?? ($document->doc_type ? strtoupper($document->doc_type) : __('Project')) }}
                                        · {{ $document->category?->name ?? __('Tanpa Kategori') }}
                                    </p>
                                </td>
                                <td class="px-6 py-4 text-content-secondary dark:text-content-secondary">
                                    {{ $document->customer?->name ?? '-' }}
                                    @if($document->project)
                                        <span class="block text-xs text-content-tertiary">{{ $document->project->project_name }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-content-secondary dark:text-content-secondary">{{ $document->deleter?->name ?? '-' }}</td>
                                <td class="px-6 py-4 text-content-secondary dark:text-content-secondary">
                                    {{ $document->deleted_at?->format('d M Y H:i') ?? '-' }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="block text-sm text-content-secondary dark:text-content-secondary">
                                        {{ $document->trashDeadline()?->format('d M Y H:i') ?? '-' }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $daysLeft <= 3 ? 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300' : 'bg-slate-100 text-content-secondary dark:bg-slate-700 dark:text-content-secondary' }}">
                                        {{ __(':days hari lagi', ['days' => $daysLeft]) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                        @can('restore', $document)
                                            <form action="{{ route('trash.restore-document', $document->id) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <x-icon-button icon="reset" type="submit" title="{{ __('Restore') }}" />
                                            </form>
                                        @endcan
                                        @can('forceDelete', $document)
                                            <x-icon-button icon="delete" type="button" x-data=""
                                                           title="{{ __('Delete permanently') }}"
                                                           @click="$dispatch('open-modal', 'confirm-destroy-document-{{ $document->id }}')" />
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-16 text-center text-content-tertiary">
                                    {{ __('No deleted documents.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">{{ $documents->links() }}</div>
        </div>

        {{-- Customer Trash --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700">
                <h2 class="text-lg font-bold text-content-primary dark:text-content-primary">{{ __('Deleted Customers') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-content-secondary dark:text-content-primary">{{ __('Customer Name') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-content-secondary dark:text-content-primary">{{ __('Email') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-content-secondary dark:text-content-primary">{{ __('Owner') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-content-secondary dark:text-content-primary">{{ __('Deleted By') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-content-secondary dark:text-content-primary">{{ __('Deleted At') }}</th>
                            <th class="px-6 py-4 text-right text-xs uppercase tracking-wider text-content-secondary dark:text-content-primary">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                        @forelse($customers as $customer)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                <td class="px-6 py-4 font-semibold text-content-primary dark:text-content-primary">{{ $customer->name }}</td>
                                <td class="px-6 py-4 text-content-secondary dark:text-content-secondary">{{ $customer->email ?? '-' }}</td>
                                <td class="px-6 py-4 text-content-secondary dark:text-content-secondary">{{ $ownerNames[$customerOwners[$customer->id] ?? null] ?? '-' }}</td>
                                <td class="px-6 py-4 text-content-secondary dark:text-content-secondary">{{ $customer->deleter?->name ?? '-' }}</td>
                                <td class="px-6 py-4 text-content-secondary dark:text-content-secondary">{{ $customer->deleted_at->format('d M Y H:i') }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                        @can('restore', $customer)
                                        <form action="{{ route('trash.restore-customer', $customer->id) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <x-icon-button icon="reset" type="submit" title="{{ __('Restore') }}" />
                                        </form>
                                        @endcan
                                        @can('forceDelete', $customer)
                                        <x-icon-button icon="delete" type="button" x-data=""
                                                       title="{{ __('Delete permanently') }}"
                                                       @click="$dispatch('open-modal', 'confirm-destroy-customer-{{ $customer->id }}')" />
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-16 text-center text-content-tertiary">
                                    {{ __('No deleted customers.') }}
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
                            <h3 class="text-lg font-bold text-content-primary dark:text-content-primary">{{ __('Delete permanently?') }}</h3>
                            <p class="text-sm text-content-secondary mt-1">
                                {{ __('Customer') }} "{{ $customer->name }}" {{ __('will be deleted') }} <b>{{ __('forever') }}</b>.
                            </p>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" @click="$dispatch('close')"
                                class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                            {{ __('Cancel') }}
                            <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
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
                <h2 class="text-lg font-bold text-content-primary dark:text-content-primary">{{ __('Deleted Projects') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-content-secondary dark:text-content-primary">{{ __('Project Name') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-content-secondary dark:text-content-primary">{{ __('Customer') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-content-secondary dark:text-content-primary">{{ __('Owner') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-content-secondary dark:text-content-primary">{{ __('Deleted By') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-content-secondary dark:text-content-primary">{{ __('Deleted At') }}</th>
                            <th class="px-6 py-4 text-right text-xs uppercase tracking-wider text-content-secondary dark:text-content-primary">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                        @forelse($projects as $project)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                <td class="px-6 py-4 font-semibold text-content-primary dark:text-content-primary">{{ $project->project_name }}</td>
                                <td class="px-6 py-4 text-content-secondary dark:text-content-secondary">{{ $project->customer?->name ?? '-' }}</td>
                                <td class="px-6 py-4 text-content-secondary dark:text-content-secondary">{{ $ownerNames[$projectOwners[$project->id] ?? null] ?? '-' }}</td>
                                <td class="px-6 py-4 text-content-secondary dark:text-content-secondary">{{ $project->deleter?->name ?? '-' }}</td>
                                <td class="px-6 py-4 text-content-secondary dark:text-content-secondary">{{ $project->deleted_at->format('d M Y H:i') }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                        @can('restore', $project)
                                        <form action="{{ route('trash.restore-project', $project->id) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <x-icon-button icon="reset" type="submit" title="{{ __('Restore') }}" />
                                        </form>
                                        @endcan
                                        @can('forceDelete', $project)
                                        <x-icon-button icon="delete" type="button" x-data=""
                                                       title="{{ __('Delete permanently') }}"
                                                       @click="$dispatch('open-modal', 'confirm-destroy-project-{{ $project->id }}')" />
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-16 text-center text-content-tertiary">
                                    {{ __('No deleted projects.') }}
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
                            <h3 class="text-lg font-bold text-content-primary dark:text-content-primary">{{ __('Delete permanently?') }}</h3>
                            <p class="text-sm text-content-secondary mt-1">
                                {{ __('Project') }} "{{ $project->project_name }}" {{ __('will be deleted') }} <b>{{ __('forever') }}</b>.
                            </p>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" @click="$dispatch('close')"
                                class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                            {{ __('Cancel') }}
                            <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
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


        @foreach($documents as $document)
            <x-modal name="confirm-destroy-document-{{ $document->id }}" maxWidth="md">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                            <x-icon name="trash" class="w-5 h-5 text-red-600" />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-content-primary dark:text-content-primary">{{ __('Delete permanently?') }}</h3>
                            <p class="text-sm text-content-secondary mt-1">
                                {{ __('Document') }} "{{ $document->file_name }}" {{ __('will be deleted') }} <b>{{ __('forever') }}</b>.
                            </p>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" @click="$dispatch('close')"
                                class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                            {{ __('Cancel') }}
                            <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                        </button>
                        <form action="{{ route('trash.destroy-document', $document->id) }}" method="POST">
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
