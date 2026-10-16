<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8">
        
        {{-- Banner Header --}}
        <div data-rise class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-800 via-slate-900 to-indigo-950 px-5 py-6 sm:px-8 sm:py-7 mb-6">
            <div class="pointer-events-none absolute -top-20 -right-16 h-56 w-56 rounded-full bg-indigo-500/20 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-24 -left-12 h-52 w-52 rounded-full bg-blue-500/10 blur-3xl"></div>

            <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-400 to-blue-600 text-lg font-bold text-white shadow-lg sm:h-14 sm:w-14 sm:text-xl">
                        {{ strtoupper(Str::substr($customer->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <h1 class="truncate text-2xl font-bold text-white sm:text-3xl">{{ $customer->name }}</h1>
                        <p class="mt-1 flex items-center gap-1.5 truncate text-sm text-slate-300">
                            <x-icon name="map-pin" class="h-4 w-4 shrink-0 text-slate-400" />
                            {{ __('Detail Customer') }} • {{ $customer->address ?? __('Alamat tidak tersedia') }}
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 gap-2">
                    @can('update', $customer)
                        <a href="{{ route('customers.edit', $customer) }}"
                           class="flex-1 sm:flex-none text-center px-4 py-2 sm:px-5 sm:py-2.5 rounded-xl bg-accent-100 hover:bg-accent-200 text-accent-700 text-sm font-medium transition-all duration-200 hover:scale-[1.03]">
                            Edit Customer
                        </a>
                    @endcan
                    <a href="{{ route('customers.index') }}"
                       class="flex-1 sm:flex-none text-center px-4 py-2 sm:px-5 sm:py-2.5 rounded-xl border border-white/30 text-white hover:bg-white/10 text-sm font-medium transition-all duration-200 hover:scale-[1.03]">
                        {{ __('Kembali') }}
                    </a>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <div x-data="{
                tab: 'overview',
                moveIndicator(btn) {
                    if (!btn) return;
                    const i = this.$refs.indicator;
                    i.style.width = btn.offsetWidth + 'px';
                    i.style.left = btn.offsetLeft + 'px';
                }
             }"
             x-init="$nextTick(() => moveIndicator($refs.tabs.querySelector('.tab-active')))"
             @resize.window.debounce.150ms="moveIndicator($refs.tabs.querySelector('.tab-active'))"
             data-rise="1"
             class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">

            {{-- Tab Navigation --}}
            <div class="border-b border-slate-200 dark:border-slate-600">
                <nav x-ref="tabs" class="relative flex gap-4 sm:gap-6 px-4 sm:px-6 overflow-x-auto whitespace-nowrap scrollbar-hide">
                    <span x-ref="indicator" class="tab-indicator absolute bottom-0 left-0 h-0.5 w-0 rounded-full bg-accent-500"></span>

                    <button @click="tab = 'overview'; moveIndicator($el)"
                            :class="tab === 'overview' ? 'tab-active text-accent-600 dark:text-accent-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                            class="shrink-0 py-3 sm:py-4 px-1 font-medium text-xs sm:text-sm transition-colors duration-200">
                        <x-icon name="grid" class="h-4 w-4 mr-1.5 inline-block align-[-1px]" /> Overview
                    </button>
                    <button @click="tab = 'projects'; moveIndicator($el)"
                            :class="tab === 'projects' ? 'tab-active text-accent-600 dark:text-accent-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                            class="shrink-0 py-3 sm:py-4 px-1 font-medium text-xs sm:text-sm transition-colors duration-200">
                        <x-icon name="folder" class="h-4 w-4 mr-1.5 inline-block align-[-1px]" /> Projects
                    </button>
                    <button @click="tab = 'contacts'; moveIndicator($el)"
                            :class="tab === 'contacts' ? 'tab-active text-accent-600 dark:text-accent-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                            class="shrink-0 py-3 sm:py-4 px-1 font-medium text-xs sm:text-sm transition-colors duration-200">
                        <x-icon name="users" class="h-4 w-4 mr-1.5 inline-block align-[-1px]" /> Contacts
                    </button>
                    <button @click="tab = 'documents'; moveIndicator($el)"
                            :class="tab === 'documents' ? 'tab-active text-accent-600 dark:text-accent-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                            class="shrink-0 py-3 sm:py-4 px-1 font-medium text-xs sm:text-sm transition-colors duration-200">
                        <x-icon name="book" class="h-4 w-4 mr-1.5 inline-block align-[-1px]" /> Documents
                    </button>
                    @can('view-sales')
                    <button @click="tab = 'followups'; moveIndicator($el)"
                            :class="tab === 'followups' ? 'tab-active text-accent-600 dark:text-accent-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                            class="shrink-0 py-3 sm:py-4 px-1 font-medium text-xs sm:text-sm transition-colors duration-200">
                        <x-icon name="phone" class="h-4 w-4 mr-1.5 inline-block align-[-1px]" /> Follow Up
                    </button>
                    @endcan
                    <button @click="tab = 'activity'; moveIndicator($el)"
                            :class="tab === 'activity' ? 'tab-active text-accent-600 dark:text-accent-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                            class="shrink-0 py-3 sm:py-4 px-1 font-medium text-xs sm:text-sm transition-colors duration-200">
                        <x-icon name="activity" class="h-4 w-4 mr-1.5 inline-block align-[-1px]" /> Activity
                    </button>
                </nav>
            </div>

            {{-- Tab Content --}}
            <div class="p-6">
                
                {{-- TAB 1: OVERVIEW --}}
                <div x-show="tab === 'overview'" x-transition>
                    @php
                        $totalProjects = $customer->projects->count();
                        $doneProjects = $customer->projects->where('project_status_id', 5)->count();
                        $activeProjects = $customer->projects->where('project_status_id', '!=', 5)->where('project_status_id', '!=', 6)->count();
                        $totalContacts = $customer->contacts->count();
                        $activities = App\Models\ProjectActivity::whereIn('project_id', $customer->projects->pluck('id'))
                            ->with(['project', 'user'])
                            ->latest()
                            ->take(5)
                            ->get();
                        $infoFields = [
                            ['icon' => 'building', 'label' => __('Nama'), 'value' => $customer->name],
                            ['icon' => 'building', 'label' => __('Customer dari Company'), 'value' => $customer->pt_group ?? '-'],
                            ['icon' => 'user', 'label' => 'PIC', 'value' => $customer->contacts->first()?->name ?: $customer->contact_person],
                            ['icon' => 'map-pin', 'label' => __('Alamat'), 'value' => $customer->address],
                            ['icon' => 'phone', 'label' => __('Telepon'), 'value' => \App\Support\PhoneFormatter::format($customer->phone)],
                            ['icon' => 'chat', 'label' => __('WA Perusahaan'), 'value' => \App\Support\PhoneFormatter::format($customer->whatsapp), 'badge' => __('Perusahaan'), 'badgeColor' => 'blue'],
                            ['icon' => 'chat', 'label' => __('WA PIC'), 'value' => \App\Support\PhoneFormatter::format($customer->contacts->first()?->whatsapp), 'badge' => __('PIC'), 'badgeColor' => 'emerald'],
                            ['icon' => 'mail', 'label' => 'Email', 'value' => $customer->email],
                            ['icon' => 'file-text', 'label' => 'NPWP', 'value' => $customer->npwp],
                        ];
                        $stats = [
                            ['icon' => 'folder', 'label' => __('Total Project'), 'value' => $totalProjects, 'chip' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400'],
                            ['icon' => 'check-circle', 'label' => __('Project Selesai'), 'value' => $doneProjects, 'chip' => 'bg-green-50 text-green-600 dark:bg-green-500/10 dark:text-green-400'],
                            ['icon' => 'bolt', 'label' => __('Project Aktif'), 'value' => $activeProjects, 'chip' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400'],
                            ['icon' => 'users', 'label' => __('Total PIC'), 'value' => $totalContacts, 'chip' => 'bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-400'],
                        ];
                    @endphp

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Info Perusahaan --}}
                        <div data-rise="1" class="bg-slate-50 dark:bg-slate-900/50 rounded-xl p-6">
                            <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">{{ __('Informasi Perusahaan') }}</h3>
                            <div class="space-y-1">
                                @foreach($infoFields as $field)
                                    <div class="info-row flex items-start gap-3 rounded-lg px-3 py-2 -mx-3">
                                        <x-icon name="{{ $field['icon'] }}" class="w-4 h-4 mt-1 text-indigo-400 shrink-0" />
                                        <div class="min-w-0">
                                            <p class="text-xs text-slate-400">{{ $field['label'] }}</p>
                                            <p class="flex flex-wrap items-center gap-2 font-medium text-slate-800 dark:text-slate-100">{{ $field['value'] ?? '-' }}@if(!empty($field['badge']))<x-status-badge :color="$field['badgeColor'] ?? 'slate'">{{ $field['badge'] }}</x-status-badge>@endif</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @if($customer->npwp_document_path)
                                <a href="{{ route('customers.npwp', $customer) }}" class="mt-3 inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:underline">
                                    <x-icon name="file-text" class="w-4 h-4" />{{ __('Unduh Dokumen NPWP') }}
                                </a>
                            @endif
                        </div>

                        {{-- Statistik --}}
                        <div data-rise="2" class="bg-slate-50 dark:bg-slate-900/50 rounded-xl p-6">
                            <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">{{ __('Statistik') }}</h3>
                            <div class="grid grid-cols-2 gap-3 sm:gap-4">
                                @foreach($stats as $i => $stat)
                                    <div class="stat-card bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm flex items-center gap-3" data-rise="{{ $i + 1 }}">
                                        <span class="flex h-10 w-10 items-center justify-center rounded-lg {{ $stat['chip'] }} shrink-0">
                                            <x-icon name="{{ $stat['icon'] }}" class="w-5 h-5" />
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-2xl font-bold tabular-nums text-slate-800 dark:text-slate-100"
                                               x-data="countUp({{ $stat['value'] }})" x-text="n"></p>
                                            <p class="text-[11px] sm:text-xs text-slate-500">{{ $stat['label'] }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Recent Activity: vertical timeline --}}
                    <div data-rise="3" class="mt-6 bg-slate-50 dark:bg-slate-900/50 rounded-xl p-6">
                        <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">{{ __('Aktivitas Terbaru') }}</h3>
                        @if($activities->isNotEmpty())
                            <div class="relative">
                                <div class="absolute left-[15px] top-8 bottom-4 w-0.5 bg-slate-200 dark:bg-slate-700"></div>
                                @foreach($activities as $activity)
                                    <div class="relative pl-10 pb-5 last:pb-0" data-rise="{{ min($loop->iteration, 4) }}">
                                        <div class="absolute left-0 top-0">
                                            <x-user-avatar :user="$activity->user" size="w-8 h-8" text="text-xs" />
                                        </div>
                                        <div class="bg-white dark:bg-slate-800 rounded-xl p-3.5 shadow-sm">
                                            <p class="text-sm text-slate-800 dark:text-slate-100">
                                                <span class="font-semibold">{{ $activity->user?->name ?? 'System' }}</span>
                                                {{ $activity->title ?? 'Activity' }}
                                                <span class="text-xs text-slate-400">· {{ $activity->created_at->diffForHumans() }}</span>
                                            </p>
                                            <p class="text-xs text-slate-500 mt-0.5">{{ $activity->project?->name ?? 'Project' }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-slate-400 text-sm text-center py-4">{{ __('Belum ada aktivitas.') }}</p>
                        @endif
                    </div>
                </div>

                {{-- TAB 2: PROJECTS --}}
                <div x-show="tab === 'projects'" x-transition>
                    <x-section-header title="{{ __('Daftar Project') }}">
                        @can('manage-technician')
                            <x-add-button href="{{ route('projects.create', ['customer_id' => $customer->id]) }}">
                                {{ __('Tambah Project') }}
                            </x-add-button>
                        @endcan
                    </x-section-header>

                    @if($customer->projects->isNotEmpty())
                        <x-data-table>
                            <thead class="bg-slate-50 dark:bg-slate-700">
                                <tr>
                                    <x-th>{{ __('Nama Project') }}</x-th>
                                    <x-th>Status</x-th>
                                    <x-th>Progress</x-th>
                                    <x-th>{{ __('Tanggal Dibuat') }}</x-th>
                                    <x-th class="text-right">{{ __('Aksi') }}</x-th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-600">
                                @foreach($customer->projects as $project)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700">
                                        <td class="px-6 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $project->project_name }}</td>

                                        {{-- Status --}}
                                        <td class="px-6 py-3">
                                            <x-status-badge :color="$project->status?->color ?? 'slate'">
                                                {{ $project->status?->name ?? __('Belum Memulai') }}
                                            </x-status-badge>
                                        </td>

                                        {{-- Progress --}}
                                        <td class="px-6 py-3">
                                            <div class="flex items-center gap-2">
                                                <div class="w-24 h-2 bg-slate-200 rounded-full overflow-hidden">
                                                    <div class="h-full bg-accent-600 rounded-full" style="width: {{ $project->progress ?? 0 }}%"></div>
                                                </div>
                                                <span class="text-xs text-slate-600 dark:text-slate-300">{{ $project->progress ?? 0 }}%</span>
                                            </div>
                                        </td>

                                        {{-- Tanggal Dibuat --}}
                                        <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $project->created_at->format('d M Y') }}</td>

                                        {{-- Aksi --}}
                                        <td class="px-6 py-3 text-right">
                                            <div class="flex flex-wrap justify-end items-center gap-x-3 gap-y-1">
                                                <a href="{{ route('projects.show', $project) }}"
                                                   class="text-accent-600 hover:text-accent-800 text-sm whitespace-nowrap">Detail</a>
                                                @can('manage-technician')
                                                    <a href="{{ route('projects.edit', $project) }}"
                                                       class="text-amber-600 hover:text-amber-800 text-sm whitespace-nowrap">Edit</a>
                                                @endcan
                                                @can('manage-technician')
                                                    <form action="{{ route('projects.destroy', $project) }}" method="POST"
                                                          onsubmit="return confirm('{{ __('Hapus project ini?') }}')" class="inline-block m-0">
                                                        @csrf @method('DELETE')
                                                        <button class="text-red-600 hover:text-red-800 text-sm whitespace-nowrap">{{ __('Hapus') }}</button>
                                                    </form>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-data-table>
                    @else
                        <x-empty-state label="{{ __('project') }}" />
                    @endif
                </div>

                {{-- TAB 3: CONTACTS --}}
                <div x-show="tab === 'contacts'" x-transition>
                    <x-section-header title="{{ __('Daftar PIC / Customer Contacts') }}">
                        @can('manage-sales')
                            <x-add-button href="{{ route('customer-contacts.create', $customer) }}">
                                {{ __('Tambah PIC') }}
                            </x-add-button>
                        @endcan
                    </x-section-header>

                    @if($customer->contacts->isNotEmpty())
                        <x-data-table>
                            <thead class="bg-slate-50 dark:bg-slate-700">
                                <tr>
                                    <x-th>{{ __('Nama') }}</x-th>
                                    <x-th>{{ __('Jabatan') }}</x-th>
                                    <x-th>WA & Email</x-th>
                                    <x-th>Status</x-th>
                                    <x-th class="text-right">{{ __('Aksi') }}</x-th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-600">
                                @foreach($customer->contacts as $contact)
                                    <tr class="{{ $contact->is_primary ? 'bg-accent-50 dark:bg-accent-900/30' : '' }}">
                                        <td class="px-6 py-3 font-medium text-slate-800 dark:text-slate-100 align-middle">{{ $contact->name }}</td>
                                        <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-300 align-middle">{{ $contact->position ?? '-' }}</td>
                                        <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-300 align-middle">
                                            @if($contact->whatsapp)<span>{{ __('WA:') }} {{ \App\Support\PhoneFormatter::format($contact->whatsapp) }}</span><br>@endif
                                            <span class="text-xs text-slate-400">{{ $contact->email ?? '-' }}</span>
                                        </td>
                                        <td class="px-6 py-3 align-middle">
                                            <form action="{{ route('customer-contacts.primary', $contact) }}" method="POST">
                                                @csrf @method('PATCH')
                                                <label class="flex cursor-pointer items-center gap-1.5">
                                                    <input type="checkbox" value="1" {{ $contact->is_primary ? 'checked' : '' }}
                                                           onchange="this.form.submit()"
                                                           class="h-5 w-5 rounded border-slate-300 text-accent-600 focus:ring-accent-500">
                                                    <span class="text-xs text-slate-500">{{ __('Utama') }}</span>
                                                </label>
                                            </form>
                                        </td>
                                        <td class="px-6 py-3 text-right align-middle">
                                            @can('manage-sales')
                                                <a href="{{ route('customer-contacts.edit', $contact) }}"
                                                   class="text-amber-600 hover:text-amber-800 text-sm inline-block align-middle">Edit</a>
                                            @endcan
                                            @can('manage-sales')
                                                <form action="{{ route('customer-contacts.destroy', $contact) }}"
                                                      method="POST"
                                                      onsubmit="return confirm('{{ __('Hapus PIC ini?') }}')"
                                                      class="inline-block align-middle ml-2">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="text-red-600 hover:text-red-800 text-sm inline-block align-middle bg-transparent border-0 cursor-pointer p-0">
                                                        {{ __('Hapus') }}
                                                    </button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-data-table>
                    @else
                        <x-empty-state label="PIC" />
                    @endif
                </div>
                
                {{-- TAB 4: DOCUMENTS --}}
                <div x-show="tab === 'documents'" x-transition>
                    @php
                        $firstProject = $customer->projects->first();
                    @endphp
                    <x-section-header title="{{ __('Dokumen') }}">
                        @can('manage-technician')
                            @if($firstProject)
                                <x-add-button href="{{ route('project-documents.index', $firstProject) }}">
                                    {{ __('Kelola Dokumen') }}
                                </x-add-button>
                            @else
                                <span class="inline-flex justify-center items-center px-4 py-2 bg-gray-300 text-white text-sm rounded-md cursor-not-allowed">
                                    {{ __('Belum ada project') }}
                                </span>
                            @endif
                        @endcan
                    </x-section-header>
                    
                    @php
                        // Ambil semua project ID dari customer
                        $projectIds = $customer->projects->pluck('id')->toArray();
                        
                        // Query dokumen berdasarkan project ID
                        $documents = App\Models\ProjectDocument::whereIn('project_id', $projectIds)
                            ->with(['project', 'category', 'uploader'])
                            ->whereNull('deleted_at')
                            ->latest()
                            ->get();
                    @endphp

                    @if($documents->isNotEmpty())
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($documents as $doc)
                                <div class="bg-slate-50 dark:bg-slate-900/50 rounded-xl p-4 border border-slate-200 hover:shadow-md transition">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1 min-w-0">
                                            {{-- Icon --}}
                                            <div class="flex items-center gap-2 mb-1">
                                                <svg class="w-5 h-5 text-accent-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                                <p class="font-medium text-slate-800 dark:text-slate-100 text-sm truncate">{{ $doc->file_name }}</p>
                                            </div>
                                            <p class="text-xs text-slate-500">{{ $doc->project?->project_name ?? 'Project' }}</p>
                                            <p class="text-xs text-slate-400">{{ $doc->category?->name ?? 'Uncategorized' }}</p>
                                            <p class="text-xs text-slate-400 mt-1">{{ number_format($doc->file_size / 1024, 1) }} KB</p>
                                            <p class="text-xs text-slate-400">{{ __('Upload:') }} {{ $doc->uploader?->name ?? '-' }}</p>
                                        </div>
                                        <div class="flex gap-2 flex-shrink-0 ml-2">
                                            <a href="{{ route('project-documents.preview', $doc) }}"
                                               target="_blank"
                                               class="text-accent-600 hover:text-accent-800 text-xs whitespace-nowrap">Preview</a>
                                            <a href="{{ route('project-documents.download', $doc) }}"
                                               class="text-accent-600 hover:text-accent-800 text-xs">Download</a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <x-empty-state label="{{ __('dokumen') }}" :description="$firstProject ? null : __('Buat project terlebih dahulu untuk upload dokumen.')">
                            @if($firstProject)
                                <a href="{{ route('project-documents.index', $firstProject) }}"
                                   class="text-accent-600 hover:text-accent-800 text-sm font-medium">
                                    {{ __('Upload dokumen sekarang') }}
                                </a>
                            @endif
                        </x-empty-state>
                    @endif
                </div>

                {{-- TAB 5: ADMIN DOCUMENTS --}}
                @can('view-admin')
                <div x-show="tab === 'documents'" x-transition>
                    <div class="mt-8 space-y-6">
                        <div>
                            <x-section-header title="Invoice">
                                @can('manage-admin')
                                    <x-add-button href="{{ route('admin.invoices.create', ['customer_id' => $customer->id]) }}">
                                        {{ __('Create Invoice') }}
                                    </x-add-button>
                                @endcan
                            </x-section-header>
                            @php $custInvoices = $customer->invoices()->with('payments')->latest('issue_date')->get(); @endphp
                            @if($custInvoices->isNotEmpty())
                                <x-data-table>
                                    <thead class="bg-slate-50 dark:bg-slate-700">
                                        <tr>
                                            <x-th>{{ __('No Invoice') }}</x-th>
                                            <x-th class="text-right">{{ __('Nominal') }}</x-th>
                                            <x-th class="text-center">Status</x-th>
                                            <x-th class="text-right">{{ __('Aksi') }}</x-th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 dark:divide-slate-600">
                                        @foreach($custInvoices as $inv)
                                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700">
                                                <td class="px-6 py-3 font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $inv->invoice_number }}</td>
                                                <td class="px-6 py-3 text-right font-mono text-slate-800 dark:text-slate-100">Rp {{ number_format($inv->amount, 0, ',', '.') }}</td>
                                                <td class="px-6 py-3 text-center">
                                                    @php
                                                        $invBadgeColor = match($inv->status) {
                                                            'paid' => 'green',
                                                            'cancelled' => 'red',
                                                            default => 'yellow',
                                                        };
                                                    @endphp
                                                    <x-status-badge :color="$invBadgeColor">{{ $inv->status === 'paid' ? __('Lunas') : ($inv->status === 'cancelled' ? __('Dibatalkan') : __('Belum Bayar')) }}</x-status-badge>
                                                </td>
                                                <td class="px-6 py-3 text-right"><a href="{{ route('admin.invoices.show', $inv) }}" class="text-accent-600 hover:text-accent-800 text-sm">Detail</a></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </x-data-table>
                            @else
                                <x-empty-state label="{{ __('invoice') }}" />
                            @endif
                        </div>

                        <div>
                            <x-section-header title="Purchase Order">
                                @can('manage-admin')
                                    <x-add-button href="{{ route('admin.pos.create', ['customer_id' => $customer->id]) }}">
                                        {{ __('Create PO') }}
                                    </x-add-button>
                                @endcan
                            </x-section-header>
                            @php $custPos = $customer->purchaseOrders()->latest('issue_date')->get(); @endphp
                            @if($custPos->isNotEmpty())
                                <x-data-table>
                                    <thead class="bg-slate-50 dark:bg-slate-700">
                                        <tr>
                                            <x-th>{{ __('No PO') }}</x-th>
                                            <x-th>Item</x-th>
                                            <x-th class="text-center">Status</x-th>
                                            <x-th class="text-right">{{ __('Aksi') }}</x-th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 dark:divide-slate-600">
                                        @foreach($custPos as $po)
                                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700">
                                                <td class="px-6 py-3 font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $po->po_number }}</td>
                                                <td class="px-6 py-3 text-slate-600 dark:text-slate-300 max-w-xs truncate">{{ Str::limit($po->items, 60) }}</td>
                                                <td class="px-6 py-3 text-center">
                                                    @php
                                                        $poBadgeColor = match($po->status) {
                                                            'selesai' => 'green',
                                                            'dibatalkan' => 'red',
                                                            'diproses' => 'yellow',
                                                            default => 'slate',
                                                        };
                                                    @endphp
                                                    <x-status-badge :color="$poBadgeColor">{{ $po->statusLabel() }}</x-status-badge>
                                                </td>
                                                <td class="px-6 py-3 text-right"><a href="{{ route('admin.pos.show', $po) }}" class="text-accent-600 hover:text-accent-800 text-sm">Detail</a></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </x-data-table>
                            @else
                                <x-empty-state label="PO" />
                            @endif
                        </div>

                        <div>
                            <x-section-header title="{{ __('Payment') }}">
                                @can('manage-admin')
                                    <x-add-button href="{{ route('admin.payments.create') }}">
                                        {{ __('Record Payment') }}
                                    </x-add-button>
                                @endcan
                            </x-section-header>
                            @php $custPayments = \App\Models\Payment::whereHas('invoice', fn($q) => $q->where('customer_id', $customer->id))->with('invoice')->latest('payment_date')->get(); @endphp
                            @if($custPayments->isNotEmpty())
                                <x-data-table>
                                    <thead class="bg-slate-50 dark:bg-slate-700">
                                        <tr>
                                            <x-th>Invoice</x-th>
                                            <x-th>{{ __('Tgl Bayar') }}</x-th>
                                            <x-th class="text-right">{{ __('Nominal') }}</x-th>
                                            <x-th class="text-center">{{ __('Bukti') }}</x-th>
                                            <x-th class="text-right">{{ __('Aksi') }}</x-th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 dark:divide-slate-600">
                                        @foreach($custPayments as $pm)
                                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700">
                                                <td class="px-6 py-3 font-mono text-sm text-slate-800 dark:text-slate-100">{{ $pm->invoice->invoice_number }}</td>
                                                <td class="px-6 py-3 text-slate-600 dark:text-slate-300">{{ $pm->payment_date->format('d M Y') }}</td>
                                                <td class="px-6 py-3 text-right font-mono text-slate-800 dark:text-slate-100">Rp {{ number_format($pm->amount, 0, ',', '.') }}</td>
                                                <td class="px-6 py-3 text-center">@if($pm->proof_file)<a href="{{ route('admin.payments.proof', $pm) }}" target="_blank" class="text-accent-600 hover:text-accent-800 text-xs underline">{{ __('Lihat') }}</a>@else - @endif</td>
                                                <td class="px-6 py-3 text-right"><a href="{{ route('admin.payments.show', $pm) }}" class="text-accent-600 hover:text-accent-800 text-sm">Detail</a></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </x-data-table>
                            @else
                                <x-empty-state label="{{ __('pembayaran') }}" />
                            @endif
                        </div>
                    </div>
                </div>
                @endcan

                {{-- TAB 6: FOLLOW UPS --}}
                @can('view-sales')
                <div x-show="tab === 'followups'" x-transition>
                    <div class="space-y-8">
                        <div>
                            <x-section-header title="Follow Up">
                                @can('manage-sales')
                                    <x-add-button href="{{ route('sales.follow-ups.create', ['customer_id' => $customer->id]) }}">
                                        {{ __('Add Follow Up') }}
                                    </x-add-button>
                                @endcan
                            </x-section-header>
                            @php $followups = $customer->followUps()->with(['creator'])->latest('follow_up_date')->get(); @endphp
                            @if($followups->isNotEmpty())
                                <div class="space-y-3">
                                    @foreach($followups as $fu)
                                        <div class="bg-slate-50 dark:bg-slate-900/50 rounded-xl p-4 border border-slate-200">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <p class="text-sm text-slate-700 dark:text-slate-200">{{ $fu->description }}</p>
                                                    <div class="flex gap-3 mt-2 text-xs text-slate-400">
                                                        <span>{{ $fu->creator?->name ?? '-' }}</span>
                                                        @if($fu->follow_up_date)
                                                            <span>· {{ $fu->follow_up_date->format('d M Y') }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <x-empty-state label="{{ __('follow up') }}" />
                            @endif
                        </div>
                    </div>
                </div>
                @endcan

                {{-- TAB 10: ACTIVITY --}}
                <div x-show="tab === 'activity'" x-transition>
                    <x-section-header title="{{ __('Timeline Aktivitas') }}" />

                    @php
                        $allActivities = App\Models\ProjectActivity::whereIn('project_id', $customer->projects->pluck('id'))
                            ->with(['project', 'user'])
                            ->latest()
                            ->get();
                    @endphp

                    @if($allActivities->isNotEmpty())
                        <div class="relative">
                            <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-slate-200"></div>
                            @foreach($allActivities as $activity)
                                <div class="relative pl-12 pb-6 last:pb-0">
                                    <div class="absolute left-2 top-1 w-5 h-5 rounded-full bg-accent-500 border-4 border-white shadow-sm"></div>
                                    <div class="bg-slate-50 dark:bg-slate-900/50 rounded-xl p-4">
                                        <div class="flex items-center gap-3">
                                            <span class="font-semibold text-sm text-slate-800 dark:text-slate-100">{{ $activity->user?->name ?? 'System' }}</span>
                                            <span class="text-xs text-slate-400">{{ $activity->created_at->format('d M Y H:i') }}</span>
                                        </div>
                                        <p class="text-sm text-slate-700 mt-1">{{ $activity->title ?? 'Activity' }}</p>
                                        <p class="text-xs text-slate-500">{{ $activity->project?->name ?? 'Project' }}</p>
                                        @if($activity->description)
                                            <p class="text-xs text-slate-400 mt-1">{{ $activity->description }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <x-empty-state label="{{ __('aktivitas') }}" />
                    @endif
                </div>

            </div>
        </div>

    </div>

    <script>
        function countUp(target, duration = 400) {
            return {
                n: 0,
                init() {
                    if (!target || matchMedia('(prefers-reduced-motion: reduce)').matches) {
                        this.n = target;
                        return;
                    }
                    const t0 = performance.now();
                    const step = (t) => {
                        const p = Math.min((t - t0) / duration, 1);
                        this.n = Math.round(target * (1 - Math.pow(1 - p, 3)));
                        if (p < 1) requestAnimationFrame(step);
                    };
                    requestAnimationFrame(step);
                },
            };
        }
    </script>
</x-app-layout>