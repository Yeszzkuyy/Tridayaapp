<x-app-layout>
    @if(session('success'))
        <x-status-card :message="session('success')" />
    @endif
    <div class="px-4 sm:px-6 lg:px-8">
        
        {{-- Banner Header --}}
        <div data-rise class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-white via-indigo-50 to-accent-100 px-5 py-6 sm:px-8 sm:py-7 mb-6 border border-slate-200/60 dark:border-0 dark:from-slate-800 dark:via-slate-900 dark:to-indigo-950">
            <div class="pointer-events-none absolute -top-20 -right-16 h-56 w-56 rounded-full bg-indigo-500/20 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-24 -left-12 h-52 w-52 rounded-full bg-blue-500/10 blur-3xl"></div>

            <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-400 to-blue-600 text-lg font-bold text-white shadow-lg sm:h-14 sm:w-14 sm:text-xl">
                        {{ strtoupper(Str::substr($customer->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <h1 class="truncate text-2xl font-bold text-slate-800 dark:text-white sm:text-3xl">{{ $customer->name }}</h1>
                        <p class="mt-1 flex items-center gap-1.5 truncate text-sm text-slate-500 dark:text-slate-300">
                            <x-icon name="map-pin" class="h-4 w-4 shrink-0 text-slate-400" />
                            {{ __('Customer Detail') }} • {{ $customer->address ?? __('Address not available') }}
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 gap-2">
                    @can('update', $customer)
                        <a href="{{ route('customers.edit', $customer) }}"
                           class="flex-1 sm:flex-none text-center px-4 py-2 sm:px-5 sm:py-2.5 rounded-xl bg-accent-100 hover:bg-accent-200 text-accent-700 text-sm font-medium transition-all duration-200 hover:scale-[1.03]">
                            {{ __('Edit Customer') }}
                        </a>
                    @endcan
                    <a href="{{ route('customers.index') }}"
                       class="flex-1 sm:flex-none text-center px-4 py-2 sm:px-5 sm:py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 dark:border-white/30 dark:text-white dark:hover:bg-white/10 text-sm font-medium transition-all duration-200 hover:scale-[1.03]">
                        {{ __('Back') }}
                    </a>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <div x-data="{
                tab: '{{ session('doc_uploaded') || $errors->has('file') ? 'documents' : 'overview' }}',
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
                        <x-icon name="grid" class="h-4 w-4 mr-1.5 inline-block align-[-1px]" /> {{ __('Overview') }}
                    </button>
                    <button @click="tab = 'projects'; moveIndicator($el)"
                            :class="tab === 'projects' ? 'tab-active text-accent-600 dark:text-accent-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                            class="shrink-0 py-3 sm:py-4 px-1 font-medium text-xs sm:text-sm transition-colors duration-200">
                        <x-icon name="folder" class="h-4 w-4 mr-1.5 inline-block align-[-1px]" /> {{ __('Projects') }}
                    </button>
                    <button @click="tab = 'contacts'; moveIndicator($el)"
                            :class="tab === 'contacts' ? 'tab-active text-accent-600 dark:text-accent-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                            class="shrink-0 py-3 sm:py-4 px-1 font-medium text-xs sm:text-sm transition-colors duration-200">
                        <x-icon name="users" class="h-4 w-4 mr-1.5 inline-block align-[-1px]" /> {{ __('Contacts') }}
                    </button>
                    <button @click="tab = 'documents'; moveIndicator($el)"
                            :class="tab === 'documents' ? 'tab-active text-accent-600 dark:text-accent-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                            class="shrink-0 py-3 sm:py-4 px-1 font-medium text-xs sm:text-sm transition-colors duration-200">
                        <x-icon name="book" class="h-4 w-4 mr-1.5 inline-block align-[-1px]" /> {{ __('Documents') }}
                    </button>
                    @can('view-sales')
                    <button @click="tab = 'followups'; moveIndicator($el)"
                            :class="tab === 'followups' ? 'tab-active text-accent-600 dark:text-accent-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                            class="shrink-0 py-3 sm:py-4 px-1 font-medium text-xs sm:text-sm transition-colors duration-200">
                        <x-icon name="phone" class="h-4 w-4 mr-1.5 inline-block align-[-1px]" /> {{ __('Follow Up') }}
                    </button>
                    @endcan
                    <button @click="tab = 'activity'; moveIndicator($el)"
                            :class="tab === 'activity' ? 'tab-active text-accent-600 dark:text-accent-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                            class="shrink-0 py-3 sm:py-4 px-1 font-medium text-xs sm:text-sm transition-colors duration-200">
                        <x-icon name="activity" class="h-4 w-4 mr-1.5 inline-block align-[-1px]" /> {{ __('Activity') }}
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
                        $infoFields = [
                            ['icon' => 'building', 'label' => __('Name'), 'value' => $customer->name],
                            ['icon' => 'building', 'label' => __('Customers from Company'), 'value' => $customer->pt_group ?? '-'],
                            ['icon' => 'user', 'label' => __('PIC'), 'value' => $customer->contacts->first()?->name ?: $customer->contact_person],
                            ['icon' => 'map-pin', 'label' => __('Address'), 'value' => $customer->address],
                            ['icon' => 'phone', 'label' => __('Telepon'), 'value' => \App\Support\PhoneFormatter::format($customer->phone)],
                            ['icon' => 'chat', 'label' => __('Company WhatsApp'), 'value' => \App\Support\PhoneFormatter::format($customer->whatsapp), 'badge' => __('Company'), 'badgeColor' => 'blue'],
                            ['icon' => 'chat', 'label' => __('PIC WhatsApp'), 'value' => \App\Support\PhoneFormatter::format($customer->contacts->first()?->whatsapp), 'badge' => __('PIC'), 'badgeColor' => 'emerald'],
                            ['icon' => 'mail', 'label' => __('Email'), 'value' => $customer->email],
                            ['icon' => 'file-text', 'label' => __('NPWP'), 'value' => $customer->npwp],
                        ];
                        $stats = [
                            ['icon' => 'folder', 'label' => __('Total Projects'), 'value' => $totalProjects, 'chip' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400'],
                            ['icon' => 'check-circle', 'label' => __('Completed Projects'), 'value' => $doneProjects, 'chip' => 'bg-green-50 text-green-600 dark:bg-green-500/10 dark:text-green-400'],
                            ['icon' => 'bolt', 'label' => __('Active Projects'), 'value' => $activeProjects, 'chip' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400'],
                            ['icon' => 'users', 'label' => __('Total PICs'), 'value' => $totalContacts, 'chip' => 'bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-400'],
                        ];
                    @endphp

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Info Perusahaan --}}
                        <div data-rise="1" class="bg-slate-50 dark:bg-slate-900/50 rounded-xl p-6">
                            <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">{{ __('Company Information') }}</h3>
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
                                <a href="{{ route('customers.npwp', $customer) }}" class="mt-3 inline-flex max-w-full items-center gap-1.5 text-sm text-indigo-600 hover:underline">
                                    <x-icon name="file-text" class="h-4 w-4 shrink-0" /><span class="truncate">{{ $customer->npwpDisplayName() }}</span>
                                </a>
                            @endif
                        </div>

                        {{-- Statistik --}}
                        <div data-rise="2" class="bg-slate-50 dark:bg-slate-900/50 rounded-xl p-6">
                            <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">{{ __('Statistics') }}</h3>
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

                    {{-- Recent Activity: vertical timeline (gabungan semua sumber) --}}
                    <div data-rise="3" class="mt-6 bg-slate-50 dark:bg-slate-900/50 rounded-xl p-6">
                        <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">{{ __('Recent Activity') }}</h3>
                        @php $recentTimeline = $timeline->take(5); @endphp
                        @if($recentTimeline->isNotEmpty())
                            <div class="relative">
                                <div class="absolute left-[15px] top-8 bottom-4 w-0.5 bg-slate-200 dark:bg-slate-700"></div>
                                @foreach($recentTimeline as $item)
                                    <div class="relative pl-10 pb-5 last:pb-0" data-rise="{{ min($loop->iteration, 4) }}">
                                        <div class="absolute left-0 top-0">
                                            <x-user-avatar :user="$item['user']" size="w-8 h-8" text="text-xs" />
                                        </div>
                                        <div class="bg-white dark:bg-slate-800 rounded-xl p-3.5 shadow-sm">
                                            <p class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm text-slate-800 dark:text-slate-100">
                                                <span class="font-semibold">{{ $item['user']?->name ?? __('System') }}</span>
                                                <x-timeline-badge :type="$item['type']" />
                                                <span>{{ $item['title'] }}</span>
                                                <span class="text-xs text-slate-400">· {{ $item['date']?->diffForHumans() }}</span>
                                            </p>
                                            @if($item['detail'] || $item['context'])
                                                <p class="text-xs text-slate-500 mt-0.5">{{ $item['detail'] }}@if($item['context']) · {{ $item['context'] }}@endif</p>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-slate-400 text-sm text-center py-4">{{ __('No activity yet.') }}</p>
                        @endif
                    </div>
                </div>

                {{-- TAB 2: PROJECTS --}}
                <div x-show="tab === 'projects'" x-transition>
                    <x-section-header title="{{ __('Project List') }}">
                        @if(auth()->user()->can('create', App\Models\Project::class) || auth()->user()->can('update', $customer))
                            <x-add-button href="{{ route('projects.create', ['customer_id' => $customer->id]) }}">
                                {{ __('Add Project') }}
                            </x-add-button>
                        @endif
                    </x-section-header>

                    @if($customer->projects->isNotEmpty())
                        <x-data-table>
                            <thead class="bg-slate-50 dark:bg-slate-700">
                                <tr>
                                    <x-th>{{ __('Project Name') }}</x-th>
                                    <x-th>{{ __('Status') }}</x-th>
                                    <x-th>{{ __('Progress') }}</x-th>
                                    <x-th>{{ __('Created Date') }}</x-th>
                                    <x-th class="text-right">{{ __('Actions') }}</x-th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-600">
                                @foreach($customer->projects as $project)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700">
                                        <td class="px-6 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $project->project_name }}</td>

                                        {{-- Status --}}
                                        <td class="px-6 py-3">
                                            <x-status-badge :color="$project->status?->color ?? 'slate'">
                                                {{ $project->status?->name ?? __('Not Started') }}
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
                                                   class="text-accent-600 hover:text-accent-800 text-sm whitespace-nowrap">{{ __('Detail') }}</a>
                                                @can('update', $project)
                                                    <a href="{{ route('projects.edit', $project) }}"
                                                       class="text-amber-600 hover:text-amber-800 text-sm whitespace-nowrap">{{ __('Edit') }}</a>
                                                @endcan
                                                @can('delete', $project)
                                                    <form action="{{ route('projects.destroy', $project) }}" method="POST"
                                                          onsubmit="return confirm('{{ __('Delete this project?') }}')" class="inline-block m-0">
                                                        @csrf @method('DELETE')
                                                        <button class="text-red-600 hover:text-red-800 text-sm whitespace-nowrap">{{ __('Delete') }}</button>
                                                    </form>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-data-table>
                    @else
                        <x-empty-state label="{{ __('projects') }}" />
                    @endif
                </div>

                {{-- TAB 3: CONTACTS --}}
                <div x-show="tab === 'contacts'" x-transition>
                    <x-section-header title="{{ __('PIC / Customer Contacts List') }}">
                        @can('update', $customer)
                            <x-add-button href="{{ route('customer-contacts.create', $customer) }}">
                                {{ __('Add PIC') }}
                            </x-add-button>
                        @endcan
                    </x-section-header>

                    @if($customer->contacts->isNotEmpty())
                        <x-data-table>
                            <thead class="bg-slate-50 dark:bg-slate-700">
                                <tr>
                                    <x-th>{{ __('Name') }}</x-th>
                                    <x-th>{{ __('Position') }}</x-th>
                                    <x-th>{{ __('WA & Email') }}</x-th>
                                    <x-th>{{ __('Status') }}</x-th>
                                    <x-th class="text-right">{{ __('Actions') }}</x-th>
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
                                            @can('update', $customer)
                                                <form action="{{ route('customer-contacts.primary', $contact) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <label class="flex cursor-pointer items-center gap-1.5">
                                                        <input type="checkbox" value="1" {{ $contact->is_primary ? 'checked' : '' }}
                                                               onchange="this.form.submit()"
                                                               class="h-5 w-5 rounded border-slate-300 text-accent-600 focus:ring-accent-500">
                                                        <span class="text-xs text-slate-500">{{ __('Primary') }}</span>
                                                    </label>
                                                </form>
                                            @else
                                                @if($contact->is_primary)
                                                    <x-status-badge color="green">{{ __('Primary') }}</x-status-badge>
                                                @endif
                                            @endcan
                                        </td>
                                        <td class="px-6 py-3 text-right align-middle">
                                            @can('update', $customer)
                                                <a href="{{ route('customer-contacts.edit', $contact) }}"
                                                   class="text-amber-600 hover:text-amber-800 text-sm inline-block align-middle">{{ __('Edit') }}</a>
                                            @endcan
                                            @can('update', $customer)
                                                <form action="{{ route('customer-contacts.destroy', $contact) }}"
                                                      method="POST"
                                                      onsubmit="return confirm('{{ __('Delete this PIC?') }}')"
                                                      class="inline-block align-middle ml-2">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="text-red-600 hover:text-red-800 text-sm inline-block align-middle bg-transparent border-0 cursor-pointer p-0">
                                                        {{ __('Delete') }}
                                                    </button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-data-table>
                    @else
                        <x-empty-state label="{{ __('PIC') }}" />
                    @endif
                </div>
                
                {{-- TAB 4: DOCUMENTS --}}
                <div x-show="tab === 'documents'" x-transition>
                    @php
                        $canUploadDocs = auth()->user()->can('update', $customer) || auth()->user()->can('manage-technician');

                        // Dokumen project-project customer + dokumen umum (tanpa project)
                        $projectIds = $customer->projects->pluck('id')->toArray();
                        $documents = App\Models\ProjectDocument::where(function ($query) use ($projectIds, $customer) {
                                $query->whereIn('project_id', $projectIds)
                                    ->orWhere('customer_id', $customer->id);
                            })
                            ->with(['project', 'category', 'uploader'])
                            ->whereNull('deleted_at')
                            ->latest()
                            ->get();
                    @endphp
                    <x-section-header title="{{ __('Documents') }}">
                        @if($canUploadDocs)
                            <x-icon-button icon="add" label="{{ __('Upload File') }}" title="{{ __('Upload File') }}" data-doc-upload-open />
                        @endif
                    </x-section-header>

                    @if($documents->isNotEmpty())
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row">
                            <div class="relative flex-1">
                                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input id="doc-search" type="search" placeholder="{{ __('Search documents') }}" autocomplete="off"
                                       class="h-11 w-full rounded-xl border border-slate-300 pl-10 pr-4 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                            </div>
                            <select id="doc-project-filter" class="h-11 rounded-xl border border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800 sm:w-52">
                                <option value="">{{ __('All projects') }}</option>
                                <option value="general">{{ __('General') }}</option>
                                @foreach($customer->projects as $p)
                                    <option value="{{ $p->id }}">{{ $p->project_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    
                    @if($documents->isNotEmpty())
                        <div id="doc-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($documents as $doc)
                                @php
                                    $docExt = strtolower(pathinfo($doc->file_name, PATHINFO_EXTENSION));
                                    $isImage = in_array($docExt, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                    $isPdf = $docExt === 'pdf';
                                    $isVideo = in_array($docExt, ['mp4', 'webm', 'ogg', 'mov', 'avi']);
                                    $isAudio = in_array($docExt, ['mp3', 'wav', 'ogg']);
                                    $docKind = $isImage ? 'image' : ($isPdf ? 'pdf' : ($isVideo ? 'video' : ($isAudio ? 'audio' : 'other')));
                                    $badgeColor = match(true) {
                                        $isPdf => 'bg-red-500',
                                        $isImage => 'bg-green-500',
                                        in_array($docExt, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']) => 'bg-blue-500',
                                        in_array($docExt, ['zip', 'rar']) => 'bg-amber-500',
                                        default => 'bg-slate-500',
                                    };
                                    $canDocManage = $doc->project_id
                                        ? auth()->user()->can('manage-technician')
                                        : $canUploadDocs;
                                @endphp
                                <div class="doc-card overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-900/50"
                                     data-name="{{ mb_strtolower($doc->file_name) }}"
                                     data-project="{{ $doc->project_id ?? 'general' }}">
                                    {{-- Klik thumbnail = preview dalam layar --}}
                                    <div class="relative flex h-36 cursor-pointer items-center justify-center overflow-hidden bg-slate-100 dark:bg-slate-800"
                                         data-doc-open
                                         data-kind="{{ $docKind }}"
                                         data-url="{{ route('project-documents.preview', $doc) }}"
                                         data-download="{{ route('project-documents.download', $doc) }}"
                                         data-filename="{{ $doc->file_name }}"
                                         title="{{ $doc->file_name }}">
                                        @if($isImage)
                                            <img src="{{ route('project-documents.preview', $doc) }}" alt="{{ $doc->file_name }}" loading="lazy" onerror="this.remove()"
                                                 class="pointer-events-none relative h-full w-full object-cover">
                                        @elseif($isPdf)
                                            <canvas data-pdf-url="{{ route('project-documents.preview', $doc) }}" class="pointer-events-none relative h-full w-full"></canvas>
                                        @endif
                                        @if(! $isImage)
                                            <svg class="absolute h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 012-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                        @endif
                                        <span class="absolute left-2 top-2 rounded px-1.5 py-0.5 text-[11px] font-bold uppercase text-white {{ $badgeColor }}">{{ $docExt ?: __('File') }}</span>
                                    </div>
                                    <div class="p-3">
                                        <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $doc->file_name }}</p>
                                        <p class="mt-0.5 truncate text-xs text-slate-400">{{ $doc->project?->project_name ?? __('General') }} · {{ number_format($doc->file_size / 1024, 1) }} KB</p>
                                        <div class="mt-2 flex gap-1.5">
                                            <button type="button" data-doc-open-child title="{{ __('Preview') }}" aria-label="{{ __('Preview') }}"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-indigo-600 transition hover:border-indigo-600 hover:bg-indigo-600 hover:text-white dark:border-slate-600 dark:bg-slate-800 dark:text-indigo-400">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            </button>
                                            <a href="{{ route('project-documents.download', $doc) }}" title="{{ __('Download') }}" aria-label="{{ __('Download') }}"
                                               class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-emerald-600 transition hover:border-emerald-600 hover:bg-emerald-600 hover:text-white dark:border-slate-600 dark:bg-slate-800 dark:text-emerald-400">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                            </a>
                                            @if($canDocManage)
                                                <button type="button" title="{{ __('Rename') }}" aria-label="{{ __('Rename') }}"
                                                        data-doc-rename
                                                        data-action="{{ route('project-documents.rename', $doc) }}"
                                                        data-name="{{ $doc->file_name }}"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-amber-600 transition hover:border-amber-600 hover:bg-amber-600 hover:text-white dark:border-slate-600 dark:bg-slate-800 dark:text-amber-400">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125"/></svg>
                                                </button>
                                                <form action="{{ route('project-documents.destroy', $doc) }}" method="POST" class="inline-flex"
                                                      onsubmit="return confirm('{{ __('Delete this document?') }}')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"
                                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-red-600 transition hover:border-red-600 hover:bg-red-600 hover:text-white dark:border-slate-600 dark:bg-slate-800 dark:text-red-400">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div id="doc-no-results" class="hidden py-10 text-center text-sm text-slate-400">{{ __('No documents match the filter.') }}</div>
                    @else
                        <x-empty-state label="{{ __('documents') }}" />
                    @endif
                    @if($canUploadDocs)
                        {{-- Popup upload --}}
                        <div id="doc-upload-modal" class="fixed inset-0 z-[90] hidden items-center justify-center bg-slate-950/70 p-4" data-doc-modal>
                            <div class="max-h-full w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900">
                                <div class="mb-4 flex items-center justify-between">
                                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Upload File') }}</h3>
                                    <button type="button" data-doc-modal-close aria-label="{{ __('Close') }}"
                                            class="flex h-9 w-9 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                        <form action="{{ route('customer-documents.store', $customer) }}" method="POST" enctype="multipart/form-data"
                              x-data="{ dragging: false, fileName: '' }">
                            @csrf
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                @if($customer->projects->isNotEmpty())
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('Project') }}</label>
                                        <select name="project_id" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                                            <option value="">{{ __('General') }}</option>
                                            @foreach($customer->projects as $p)
                                                <option value="{{ $p->id }}">{{ $p->project_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('Notes') }}</label>
                                    <input type="text" name="notes" value="{{ old('notes') }}" placeholder="{{ __('Document notes') }}"
                                           class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                                </div>
                            </div>
                            <div class="relative mt-3 rounded-xl border-2 border-dashed p-4 text-center transition-all duration-200"
                                 :class="dragging ? 'border-accent-500 bg-accent-50 dark:bg-accent-900/40 dark:border-accent-400' : 'border-slate-300 dark:border-slate-600 hover:border-accent-500 hover:bg-accent-50 dark:hover:bg-accent-900/40'"
                                 @dragover.prevent="dragging = true"
                                 @dragenter.prevent="dragging = true"
                                 @dragleave.prevent="dragging = false"
                                 @drop.prevent="dragging = false; if ($event.dataTransfer.files.length) { $refs.fileInput.files = $event.dataTransfer.files; fileName = $event.dataTransfer.files[0].name; }">
                                <input type="file"
                                       name="file"
                                       x-ref="fileInput"
                                       @change="fileName = $event.target.files.length ? $event.target.files[0].name : ''"
                                       class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                                <svg class="mx-auto mb-2 h-8 w-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <p class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ __('Drag & drop files here') }}</p>
                                <p class="text-xs text-slate-400">{{ __('or click to browse') }} · {{ __('Maximum 20MB') }}</p>
                                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400" x-text="fileName || '{{ __('No file selected') }}'"></p>
                                @error('file')
                                    <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="mt-4 flex justify-end gap-3">
                                <button type="button" data-doc-modal-close
                                        class="rounded-xl px-5 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="submit"
                                        class="group relative overflow-hidden rounded-xl bg-accent-600 px-6 py-2.5 text-sm font-medium text-white transition-all duration-300 hover:bg-accent-700 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                                    {{ __('Upload') }}
                                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                                </button>
                            </div>
                        </form>
                            </div>
                        </div>

                        {{-- Tampilan file dalam layar --}}
                        <div id="doc-lightbox" class="fixed inset-0 z-[90] hidden items-center justify-center bg-slate-950/80 p-4" data-doc-modal>
                            <div class="flex max-h-full w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-900">
                                <div class="flex items-center justify-between gap-3 border-b border-slate-200 p-4 dark:border-slate-700">
                                    <p id="doc-lightbox-name" class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-800 dark:text-slate-100"></p>
                                    <div class="flex shrink-0 gap-2">
                                        <a id="doc-lightbox-download" href="#"
                                           class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-emerald-600 transition hover:bg-emerald-600 hover:text-white dark:border-slate-600 dark:text-emerald-400"
                                           title="{{ __('Download') }}" aria-label="{{ __('Download') }}">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                        </a>
                                        <button type="button" data-doc-modal-close aria-label="{{ __('Close') }}"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </div>
                                <div id="doc-lightbox-body" class="flex min-h-[40vh] items-center justify-center overflow-auto bg-slate-100 p-4 dark:bg-slate-950/60"></div>
                            </div>
                        </div>

                        {{-- Popup ubah nama --}}
                        <div id="doc-rename-modal" class="fixed inset-0 z-[95] hidden items-center justify-center bg-slate-950/70 p-4" data-doc-modal>
                            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900">
                                <h3 class="mb-4 text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Rename') }}</h3>
                                <form id="doc-rename-form" method="POST" action="#">
                                    @csrf @method('PATCH')
                                    <input type="text" name="name" id="doc-rename-input" required maxlength="255"
                                           class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                                    <p class="mt-1 text-xs text-slate-400">{{ __('File extension is kept automatically.') }}</p>
                                    <div class="mt-4 flex justify-end gap-3">
                                        <button type="button" data-doc-modal-close
                                                class="rounded-xl px-5 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                            {{ __('Cancel') }}
                                        </button>
                                        <button type="submit"
                                                class="rounded-xl bg-accent-600 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-accent-700 active:scale-95">
                                            {{ __('Save') }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- TAB 5: ADMIN DOCUMENTS --}}
                @can('view-admin')
                <div x-show="tab === 'documents'" x-transition>
                    <div class="mt-8 space-y-6">
                        <div>
                            <x-section-header title="{{ __('Invoice') }}" />
                            @php $custInvoices = $customer->invoices()->with('payments')->latest('issue_date')->get(); @endphp
                            @if($custInvoices->isNotEmpty())
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    @foreach($custInvoices as $inv)
                                        @php
                                            $invBadgeColor = match($inv->status) {
                                                'paid' => 'green',
                                                'cancelled' => 'red',
                                                default => 'yellow',
                                            };
                                            $invStatusLabel = $inv->status === 'paid' ? __('Paid') : ($inv->status === 'cancelled' ? __('Cancelled') : __('Unpaid'));
                                        @endphp
                                        <a href="{{ route('admin.invoices.show', $inv) }}" title="{{ $inv->invoice_number }}"
                                           class="block overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-900/50">
                                            <div class="relative flex h-24 items-center justify-center overflow-hidden bg-slate-100 dark:bg-slate-800">
                                                <svg class="h-10 w-10 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                                <span class="absolute left-2 top-2 rounded bg-teal-500 px-1.5 py-0.5 text-[11px] font-bold uppercase text-white">INV</span>
                                            </div>
                                            <div class="p-3">
                                                <p class="flex flex-wrap items-center gap-2 font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">
                                                    <span class="truncate">{{ $inv->invoice_number }}</span>
                                                    <x-status-badge :color="$invBadgeColor">{{ $invStatusLabel }}</x-status-badge>
                                                </p>
                                                <p class="mt-0.5 font-mono text-sm text-slate-500 dark:text-slate-400">Rp {{ number_format($inv->amount, 0, ',', '.') }}</p>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <x-empty-state label="{{ __('invoices') }}" />
                            @endif
                            @can('manage-admin')
                                <div class="mt-4 flex justify-center">
                                    <x-add-button href="{{ route('admin.invoices.create', ['customer_id' => $customer->id]) }}">
                                        {{ __('Create Invoice') }}
                                    </x-add-button>
                                </div>
                            @endcan
                        </div>

                        <div>
                            <x-section-header title="{{ __('Purchase Order') }}" />
                            @php $custPos = $customer->purchaseOrders()->latest('issue_date')->get(); @endphp
                            @if($custPos->isNotEmpty())
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    @foreach($custPos as $po)
                                        @php
                                            $poBadgeColor = match($po->status) {
                                                'selesai' => 'green',
                                                'dibatalkan' => 'red',
                                                'diproses' => 'yellow',
                                                default => 'slate',
                                            };
                                        @endphp
                                        <a href="{{ route('admin.pos.show', $po) }}" title="{{ $po->po_number }}"
                                           class="block overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-900/50">
                                            <div class="relative flex h-24 items-center justify-center overflow-hidden bg-slate-100 dark:bg-slate-800">
                                                <svg class="h-10 w-10 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                                <span class="absolute left-2 top-2 rounded bg-violet-500 px-1.5 py-0.5 text-[11px] font-bold uppercase text-white">PO</span>
                                            </div>
                                            <div class="p-3">
                                                <p class="flex flex-wrap items-center gap-2 font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">
                                                    <span class="truncate">{{ $po->po_number }}</span>
                                                    <x-status-badge :color="$poBadgeColor">{{ $po->statusLabel() }}</x-status-badge>
                                                </p>
                                                <p class="mt-0.5 truncate text-xs text-slate-400">{{ Str::limit($po->items, 60) }}</p>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <x-empty-state label="{{ __('PO') }}" />
                            @endif
                            @can('manage-admin')
                                <div class="mt-4 flex justify-center">
                                    <x-add-button href="{{ route('admin.pos.create', ['customer_id' => $customer->id]) }}">
                                        {{ __('Create PO') }}
                                    </x-add-button>
                                </div>
                            @endcan
                        </div>

                        <div>
                            <x-section-header title="{{ __('Payment') }}" />
                            @php $custPayments = \App\Models\Payment::whereHas('invoice', fn($q) => $q->where('customer_id', $customer->id))->with('invoice')->latest('payment_date')->get(); @endphp
                            @if($custPayments->isNotEmpty())
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    @foreach($custPayments as $pm)
                                        <a href="{{ route('admin.payments.show', $pm) }}" title="{{ $pm->invoice->invoice_number }}"
                                           class="block overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-900/50">
                                            <div class="relative flex h-24 items-center justify-center overflow-hidden bg-slate-100 dark:bg-slate-800">
                                                <svg class="h-10 w-10 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                          d="M9 14l6-6m-5.5.5h.01m4.99 1H15a5 5 0 015 5v7H5v-7a5 5 0 015-5h2.5z"/>
                                                </svg>
                                                <span class="absolute left-2 top-2 rounded bg-emerald-500 px-1.5 py-0.5 text-[11px] font-bold uppercase text-white">PAY</span>
                                            </div>
                                            <div class="p-3">
                                                <p class="truncate font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $pm->invoice->invoice_number }}</p>
                                                <p class="mt-0.5 font-mono text-sm text-slate-500 dark:text-slate-400">Rp {{ number_format($pm->amount, 0, ',', '.') }}</p>
                                                <p class="mt-0.5 truncate text-xs text-slate-400">{{ $pm->payment_date->format('d M Y') }}{{ $pm->proof_file ? ' · ' . __('Proof') : '' }}</p>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <x-empty-state label="{{ __('payments') }}" />
                            @endif
                            @can('manage-admin')
                                <div class="mt-4 flex justify-center">
                                    <x-add-button href="{{ route('admin.payments.create') }}">
                                        {{ __('Record Payment') }}
                                    </x-add-button>
                                </div>
                            @endcan
                        </div>
                    </div>
                </div>
                @endcan

                {{-- TAB 6: FOLLOW UPS --}}
                @can('view-sales')
                <div x-show="tab === 'followups'" x-transition>
                    <div class="space-y-8">
                        <div>
                            <x-section-header title="{{ __('Follow Up') }}">
                                @can('update', $customer)
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
                                <x-empty-state label="{{ __('follow ups') }}" />
                            @endif
                        </div>
                    </div>
                </div>
                @endcan

                {{-- TAB 10: ACTIVITY --}}
                <div x-show="tab === 'activity'" x-transition>
                    <x-section-header title="{{ __('Activity Timeline') }}" />

                    @if($timeline->isNotEmpty())
                        <div class="relative">
                            <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-slate-200 dark:bg-slate-700"></div>
                            @foreach($timeline as $item)
                                <div class="relative pl-12 pb-6 last:pb-0">
                                    <div class="absolute left-2 top-1 w-5 h-5 rounded-full bg-accent-500 border-4 border-white shadow-sm dark:border-slate-800"></div>
                                    <div class="bg-slate-50 dark:bg-slate-900/50 rounded-xl p-4">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <x-timeline-badge :type="$item['type']" />
                                            <span class="font-semibold text-sm text-slate-800 dark:text-slate-100">{{ $item['user']?->name ?? __('System') }}</span>
                                            <span class="text-xs text-slate-400">{{ $item['date']?->format('d M Y H:i') }}</span>
                                        </div>
                                        <p class="text-sm text-slate-700 dark:text-slate-200 mt-1">{{ $item['title'] }}</p>
                                        @if($item['detail'] || $item['context'])
                                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $item['detail'] }}@if($item['context']) · {{ $item['context'] }}@endif</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <x-empty-state label="{{ __('activity') }}" />
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

@push('scripts')
    @vite(['resources/js/doc-thumbs.js'])
@endpush