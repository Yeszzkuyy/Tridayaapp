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
                            ['icon' => 'building', 'label' => __('Customers from Company'), 'value' => $customer->pt_group],
                            ['icon' => 'user', 'label' => __('PIC'), 'value' => $customer->picName()],
                            ['icon' => 'map-pin', 'label' => __('Address'), 'value' => $customer->address],
                            ['icon' => 'map-pin', 'label' => __('Billing Address'), 'value' => $customer->billingAddress()],
                            ['icon' => 'map-pin', 'label' => __('Shipping Address'), 'value' => $customer->shippingAddress()],
                            ['icon' => 'phone', 'label' => __('Telepon'), 'value' => \App\Support\PhoneFormatter::format($customer->phone)],
                            ['icon' => 'chat', 'label' => __('Company WhatsApp'), 'value' => \App\Support\PhoneFormatter::format($customer->whatsapp), 'badge' => __('Company'), 'badgeColor' => 'blue'],
                            ['icon' => 'chat', 'label' => __('PIC WhatsApp'), 'value' => \App\Support\PhoneFormatter::format($customer->primaryPic()?->whatsapp), 'badge' => __('PIC'), 'badgeColor' => 'emerald'],
                            ['icon' => 'mail', 'label' => __('Email'), 'value' => $customer->email],
                            ['icon' => 'user', 'label' => __('Finance PIC'), 'value' => $customer->financeContact()],
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
                                            <p class="flex flex-wrap items-center gap-2 font-medium text-slate-800 dark:text-slate-100 {{ empty($field['value']) ? 'text-slate-400 italic' : '' }}">{{ $field['value'] ?: __('Not set yet') }}@if(!empty($field['badge']))<x-status-badge :color="$field['badgeColor'] ?? 'slate'">{{ $field['badge'] }}</x-status-badge>@endif</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @if($customer->npwp_document_path)
                                <a href="{{ route('customers.npwp', $customer) }}" target="_blank" rel="noopener" class="mt-3 inline-flex max-w-full items-center gap-1.5 text-sm text-indigo-600 hover:underline">
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
                        {{-- Satu kartu per project, 3 kolom seperti kartu dokumen. --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach($customer->projects as $project)
                                @include('customers.project-card', ['project' => $project])
                            @endforeach
                        </div>
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
                        // Split dokumen berdasarkan jenis: project & general
                        // (dokumen transaksi sudah dipisah di controller).
                        $projectDocuments = $documents->whereNotNull('project_id');
                        $generalDocuments = $documents->whereNull('project_id');
                    @endphp

                    {{-- Breadcrumb + judul halaman Documents --}}
                    <nav aria-label="Breadcrumb" class="mb-4">
                        <ol class="flex flex-wrap items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400">
                            <li>
                                <a href="{{ route('customers.index') }}"
                                   class="transition-colors hover:text-indigo-600 dark:hover:text-indigo-400">{{ __('Customer') }}</a>
                            </li>
                            <li aria-hidden="true" class="text-slate-300 dark:text-slate-600">/</li>
                            <li>
                                <a href="{{ route('customers.show', $customer) }}"
                                   class="max-w-[12rem] truncate transition-colors hover:text-indigo-600 dark:hover:text-indigo-400">{{ $customer->name }}</a>
                            </li>
                            <li aria-hidden="true" class="text-slate-300 dark:text-slate-600">/</li>
                            <li class="font-medium text-slate-700 dark:text-slate-200" aria-current="page">{{ __('Documents') }}</li>
                        </ol>
                    </nav>

                    <div class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900 dark:text-white sm:text-2xl">{{ __('Documents') }}</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ __('Semua dokumen pelanggan: proyek, umum, dan transaksi.') }}
                            </p>
                        </div>
                        @if($canUploadDocs)
                            <div class="shrink-0">
                                <x-icon-button icon="add" label="{{ __('Upload Document') }}" title="{{ __('Upload Document') }}"
                                               data-doc-upload-open />
                            </div>
                        @endif
                    </div>

                    <div class="space-y-8">
                        {{-- Section 1: Project Documents (kelompokkan per project) --}}
                        @include('customers.partials.project-documents', [
                            'projectDocuments' => $projectDocuments,
                            'projects' => $customer->projects,
                            'canUploadDocs' => $canUploadDocs,
                            'customer' => $customer,
                        ])

                        {{-- Section 2: General Documents (dokumen umum customer) --}}
                        @include('customers.partials.general-documents', [
                            'generalDocuments' => $generalDocuments,
                            'canUploadDocs' => $canUploadDocs,
                        ])
                    </div>
                </div>

                {{-- TAB 5: TRANSACTION DOCUMENTS (Invoice, PO, Payment, Quotation) --}}
                @canany(['view-admin', 'view-sales'])
                @php
                    $custQuotations = \App\Models\Proposal::whereHas('lead', fn ($q) => $q->where('customer_id', $customer->id))
                        ->with(['lead', 'creator'])
                        ->latest('proposal_date')
                        ->get();
                @endphp
                <div x-show="tab === 'documents'" x-transition class="space-y-6">
                    {{-- Section Quotation / Penawaran Harga (Mirroring dari Sales) --}}
                    <div class="border-t border-slate-200 dark:border-slate-600 pt-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Quotation / Penawaran Harga') }}</h3>
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Dokumen penawaran harga resmi dari divisi Sales yang terhubung dengan customer ini.') }}</p>
                            </div>
                            <a href="{{ route('sales.proposals.index') }}"
                               class="text-sm font-medium text-accent-600 hover:text-accent-800 dark:text-accent-400 dark:hover:text-accent-300">
                                {{ __('Lihat Semua Quotation') }} →
                            </a>
                        </div>

                        @if($custQuotations->isNotEmpty())
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                @foreach($custQuotations as $quo)
                                    <div class="group relative block overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-900/50">
                                        <div class="relative flex h-36 items-center justify-center overflow-hidden bg-slate-100 dark:bg-slate-800">
                                            <svg class="h-10 w-10 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            <span class="absolute left-2 top-2 rounded bg-indigo-600 px-1.5 py-0.5 text-[11px] font-bold uppercase text-white">QUO</span>
                                            <div class="absolute right-2 top-2 flex items-center gap-1">
                                                <x-icon-button as="a" icon="view" href="{{ route('sales.proposals.preview', $quo) }}" target="_blank" title="{{ __('Preview PDF') }}" />
                                            </div>
                                        </div>
                                        <div class="p-3">
                                            <p class="flex flex-wrap items-center gap-2 font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">
                                                <a href="{{ route('sales.proposals.show', $quo) }}" class="truncate hover:text-accent-600 dark:hover:text-accent-400">
                                                    {{ $quo->proposal_number }}
                                                </a>
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold {{ \App\Models\Proposal::statusBadgeClasses($quo->status) }}">
                                                    {{ \App\Models\Proposal::statusLabel($quo->status) }}
                                                </span>
                                            </p>
                                            <p class="mt-0.5 font-mono text-sm text-slate-700 dark:text-slate-200 font-medium">Rp {{ number_format($quo->grand_total, 0, ',', '.') }}</p>
                                            <p class="mt-0.5 truncate text-xs text-slate-400">
                                                {{ $quo->proposal_date?->format('d M Y') ?? '-' }}
                                                @if($quo->valid_until)
                                                    · {{ __('Valid s/d') }} {{ $quo->valid_until->format('d M Y') }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="bg-slate-50 dark:bg-slate-900/50 rounded-xl p-4 border border-slate-200 dark:border-slate-600 text-center">
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Belum ada quotation / penawaran untuk customer ini.') }}</p>
                                <a href="{{ route('sales.proposals.create') }}"
                                   class="mt-2 inline-block text-sm text-accent-600 hover:text-accent-800 dark:text-accent-400 dark:hover:text-accent-300">
                                    {{ __('Buat Quotation Baru') }} →
                                </a>
                            </div>
                        @endif
                    </div>

                    @include('customers.partials.transaction-documents', [
                        'custInvoices' => $custInvoices,
                        'custPos' => $custPos,
                        'custPayments' => $custPayments,
                        'typedDocs' => $typedDocs,
                        'canUploadDocs' => $canUploadDocs,
                    ])
                </div>
                @endcanany

                    {{-- Modul popup dokumen (dipakai oleh doc-thumbs.js) --}}
                    @if($canUploadDocs)
                        {{-- Popup upload --}}
                        <div id="doc-upload-modal" class="fixed inset-0 z-[90] {{ $errors->has('file') ? 'doc-open' : 'hidden' }} items-center justify-center bg-slate-950/70 p-4" data-doc-modal>
                            <div class="max-h-full w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900">
                                <div class="mb-4 flex items-center justify-between">
                                    <div>
                                        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Upload File') }}</h3>
                                        <p id="doc-upload-target" class="hidden text-xs text-slate-500 dark:text-slate-400"></p>
                                    </div>
                                    <button type="button" data-doc-modal-close aria-label="{{ __('Close') }}"
                                            class="flex h-9 w-9 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                                <form action="{{ route('customer-documents.store', $customer) }}" method="POST" enctype="multipart/form-data"
                                      x-data="{ dragging: false, fileName: '' }">
                                    @csrf
                                    <input type="hidden" name="doc_type" id="doc-doc-type" value="{{ old('doc_type') }}">
                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        @if($customer->projects->isNotEmpty())
                                            <div id="doc-project-field">
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
                                        <button type="submit" data-doc-upload-submit
                                                :disabled="!fileName"
                                                :class="!fileName ? 'opacity-50 cursor-not-allowed' : ''"
                                                class="group relative overflow-hidden rounded-xl bg-accent-600 px-6 py-2.5 text-sm font-medium text-white transition-all duration-300 hover:bg-accent-700 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                                            {{ __('Upload') }}
                                            <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif
                    {{-- Lightbox & rename dipakai semua pengunjung dokumen; aksi tulis tetap dijaga backend. --}}

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
                            <div class="absolute left-[15px] top-8 bottom-4 w-0.5 bg-slate-200 dark:bg-slate-700"></div>
                            @foreach($timeline as $item)
                                <div class="relative pl-10 pb-5 last:pb-0">
                                    <div class="absolute left-0 top-0">
                                        <x-user-avatar :user="$item['user']" size="w-8 h-8" text="text-xs" />
                                    </div>
                                    <div class="bg-slate-50 dark:bg-slate-900/50 rounded-xl p-3.5">
                                        <p class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm text-slate-800 dark:text-slate-100">
                                            <span class="font-semibold">{{ $item['user']?->name ?? __('System') }}</span>
                                            <x-timeline-badge :type="$item['type']" />
                                            <span>{{ $item['title'] }}</span>
                                            <span class="text-xs text-slate-400">· {{ $item['date']?->diffForHumans() }}</span>
                                        </p>
                                        @if($item['detail'] || $item['context'])
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $item['detail'] }}@if($item['context']) · {{ $item['context'] }}@endif</p>
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
    {{-- Thumbnail PDF + popup/filter/preview dokumen (script langsung, bukan @push
         yang ternyata terbuang di komponen layout). --}}
    <script type="module" src="{{ \Illuminate\Support\Facades\Vite::asset('resources/js/doc-thumbs.js') }}"></script>
</x-app-layout>