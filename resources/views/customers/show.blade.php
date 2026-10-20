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
                    @endphp
                    <x-section-header title="{{ __('Documents') }}" />
                    
                    @php
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

                    @if($documents->isNotEmpty())
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($documents as $doc)
                                @php
                                    $docExt = strtolower(pathinfo($doc->file_name, PATHINFO_EXTENSION));
                                    $isImage = in_array($docExt, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                    $isPdf = $docExt === 'pdf';
                                    $badgeColor = match(true) {
                                        $isPdf => 'bg-red-500',
                                        $isImage => 'bg-green-500',
                                        in_array($docExt, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']) => 'bg-blue-500',
                                        in_array($docExt, ['zip', 'rar']) => 'bg-amber-500',
                                        default => 'bg-slate-500',
                                    };
                                @endphp
                                <a href="{{ route('project-documents.preview', $doc) }}" target="_blank" title="{{ $doc->file_name }}"
                                   class="block overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-900/50">
                                    {{-- Thumbnail ala Drive --}}
                                    <div class="relative flex h-36 items-center justify-center overflow-hidden bg-slate-100 dark:bg-slate-800">
                                        @if($isImage)
                                            <img src="{{ route('project-documents.preview', $doc) }}" alt="{{ $doc->file_name }}" loading="lazy" onerror="this.remove()"
                                                 class="relative h-full w-full object-cover">
                                        @elseif($isPdf)
                                            <canvas data-pdf-url="{{ route('project-documents.preview', $doc) }}" class="relative h-full w-full"></canvas>
                                        @endif
                                        @if(! $isImage)
                                            <svg class="absolute h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                        @endif
                                        <span class="absolute left-2 top-2 rounded px-1.5 py-0.5 text-[11px] font-bold uppercase text-white {{ $badgeColor }}">{{ $docExt ?: __('File') }}</span>
                                    </div>
                                    <div class="p-3">
                                        <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $doc->file_name }}</p>
                                        <p class="mt-0.5 truncate text-xs text-slate-400">{{ $doc->project?->project_name ?? __('General') }} · {{ number_format($doc->file_size / 1024, 1) }} KB</p>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <x-empty-state label="{{ __('documents') }}" />
                    @endif
                    @if($canUploadDocs)
                        <form action="{{ route('customer-documents.store', $customer) }}" method="POST" enctype="multipart/form-data"
                              x-data="{ dragging: false, fileName: '' }"
                              class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/50">
                            @csrf
                            <p class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('Upload File') }}</p>
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
                            <div class="mt-3 flex justify-center">
                                <button type="submit"
                                        class="group relative overflow-hidden rounded-xl bg-accent-600 px-6 py-2.5 text-sm font-medium text-white transition-all duration-300 hover:bg-accent-700 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                                    {{ __('Upload') }}
                                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                                </button>
                            </div>
                        </form>
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