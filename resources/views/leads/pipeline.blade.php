<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Lead Pipeline') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Pipeline of :name — new leads land in the New column, drag to Cool/Warm/Hot to follow up', ['name' => auth()->user()->name]) }}</p>
        </div>
        <div class="flex items-center gap-3">
            @php
                $isAllClosed = $showAllClosed ?? false;
                $filterUrl = $isAllClosed ? route('leads.pipeline') : route('leads.pipeline', ['closed' => 'all']);
                $filterLabel = $isAllClosed ? __('This Week') : __('Show All');
                $canManageMarketing = auth()->user()->can('view-marketing') || auth()->user()->can('manage-marketing');
                $tableUrl = $canManageMarketing ? route('leads.index') : route('sales.my-leads');
                $tableLabel = $canManageMarketing ? __('Lead Table') : __('My Leads');
            @endphp

            {{-- Segmented Capsule: Filter Toggle & Table View Switcher --}}
            <div class="inline-flex items-center rounded-xl border border-slate-200/80 bg-slate-100 p-1 shadow-xs dark:border-slate-700/80 dark:bg-slate-800/90">
                {{-- 1. Filter Toggle --}}
                <a href="{{ $filterUrl }}"
                   wire:navigate.hover
                   title="{{ $filterLabel }}"
                   class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 {{ $isAllClosed
                       ? 'bg-white font-semibold text-accent-700 shadow-xs dark:bg-slate-700 dark:text-accent-300'
                       : 'font-medium text-slate-600 hover:bg-white/60 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-700/50 dark:hover:text-slate-200' }}">
                    <svg class="h-3.5 w-3.5 {{ $isAllClosed ? 'text-accent-600 dark:text-accent-400' : 'text-slate-400 dark:text-slate-500' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3z" />
                    </svg>
                    <span>{{ $filterLabel }}</span>
                </a>

                {{-- Divider --}}
                <div class="mx-1 h-3.5 w-px bg-slate-200 dark:bg-slate-700/80" aria-hidden="true"></div>

                {{-- 2. Table / List View --}}
                <a href="{{ $tableUrl }}"
                   wire:navigate.hover
                   title="{{ $tableLabel }}"
                   class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-slate-600 transition-all duration-200 hover:bg-white/60 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 dark:text-slate-400 dark:hover:bg-slate-700/50 dark:hover:text-slate-200">
                    <svg class="h-3.5 w-3.5 text-slate-400 dark:text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01" />
                    </svg>
                    <span>{{ $tableLabel }}</span>
                </a>
            </div>

            {{-- 3. Primary CTA: Add Lead --}}
            @can('manage-marketing')
                <a href="{{ route('leads.create') }}"
                   wire:navigate.hover
                   title="{{ __('Add Lead') }}"
                   class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl bg-accent-600 px-4 py-2 text-xs font-semibold text-white shadow-sm shadow-accent-500/25 transition-all duration-300 hover:bg-accent-700 hover:shadow-md hover:shadow-accent-500/40 hover:brightness-105 active:scale-95 shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500">
                    <svg class="h-4 w-4 shrink-0 transition-transform duration-300 group-hover:rotate-90" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span class="tracking-wide">{{ __('Add Lead') }}</span>
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/30 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </a>
            @endcan
        </div>
    </div>

    @php
        $newLeads = $newLeads ?? collect();
        $hasLeads = $leads->isNotEmpty() || $newLeads->isNotEmpty();
    @endphp
    @if($hasLeads)
    <div class="kanban-board overflow-x-auto pb-4"
         @if(auth()->user()->can('manage-marketing') || auth()->user()->can('manage-sales')) data-editable="1" @endif
         @if(auth()->user()->can('manage-sales') && !auth()->user()->can('manage-marketing')) data-sales-only="1" @endif>
        <div class="flex gap-4 min-w-max items-start">
            {{-- Kolom New (virtual): lead baru di-assign, belum ditangani.
                 Hanya sumber geser — kartu tidak bisa dikembalikan ke sini. --}}
            <div class="w-72 shrink-0 bg-slate-50 dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-600">
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200 dark:border-slate-600">
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-slate-200 text-slate-700">
                        {{ __('New') }}
                    </span>
                    <span class="text-sm font-semibold text-slate-500" data-count>{{ ($newLeads ?? collect())->count() }}</span>
                </div>
                <div class="kanban-list p-3 space-y-3 min-h-24" data-new-source="1">
                    @foreach(($newLeads ?? collect()) as $lead)
                        @include('leads._pipeline-card', ['lead' => $lead, 'cardDate' => $lead->assigned_at ?? $lead->incoming_date])
                    @endforeach
                </div>
            </div>
            @foreach($statuses as $status)
                @php
                    $columnLeads = $leads->where('status', $status);
                    $colors = [
                        'cool' => 'bg-blue-100 text-blue-800',
                        'warm' => 'bg-yellow-100 text-yellow-800',
                        'hot' => 'bg-orange-100 text-orange-800',
                        'won' => 'bg-green-100 text-green-800',
                        'lost' => 'bg-red-100 text-red-800',
                    ];
                @endphp
                <div class="w-72 shrink-0 bg-slate-50 dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-600">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200 dark:border-slate-600">
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium {{ $colors[$status] ?? 'bg-slate-100 text-slate-800' }}">
                            {{ ucfirst($status) }}
                        </span>
                        <span class="text-sm font-semibold text-slate-500" data-count>{{ $columnLeads->count() }}</span>
                        @if(in_array($status, ['won', 'lost'], true) && !($showAllClosed ?? false))
                            <span class="ml-1 text-[11px] text-slate-400">{{ __('this week') }}</span>
                        @endif
                    </div>
                    <div class="kanban-list p-3 space-y-3 min-h-24" data-status="{{ $status }}">
                        @foreach($columnLeads as $lead)
                            @include('leads._pipeline-card', ['lead' => $lead, 'cardDate' => $lead->incoming_date])
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @else
        <div class="rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 px-5 py-12 text-center">
            <p class="text-sm font-medium text-slate-500">{{ __('No leads in your pipeline yet.') }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ __('New leads from Management will appear here.') }}</p>
        </div>
    @endif

    @if(auth()->user()->can('manage-marketing') || auth()->user()->can('manage-sales'))
    <script>
    function initPipelineDnD(retry) {
        var board = document.querySelector('.kanban-board[data-editable]');
        if (!board) return;
        if (typeof Sortable === 'undefined') {
            // Modul pipeline-dnd.js bisa belum selesai dimuat saat tiba via
            // wire:navigate (SPA): coba lagi sesaat, baru menyerah.
            if ((retry || 0) < 10) {
                setTimeout(function () { initPipelineDnD((retry || 0) + 1); }, 100);
                return;
            }
            console.warn('SortableJS not available, pipeline drag & drop disabled.');
            return;
        }
        var salesOnly = board.hasAttribute('data-sales-only');

        var csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        var pendingChanges = new Map();

        board.querySelectorAll('.kanban-list').forEach(function (list) {
            // Idempoten: lewati list yang sudah di-init (navigasi SPA bisa
            // memanggil init ini berulang pada DOM yang sama).
            if (list.dataset.dndInit) return;
            list.dataset.dndInit = '1';
            // Kolom New hanya sumber geser (tidak bisa jadi target drop).
            var isNewSource = list.hasAttribute('data-new-source');
            new Sortable(list, {
                group: isNewSource ? { name: 'leads', pull: true, put: false } : 'leads',
                animation: 150,
                ghostClass: 'opacity-40',
                draggable: salesOnly ? '.kanban-card[data-mine="1"]' : '.kanban-card',
                onEnd: function (evt) {
                    var leadId = evt.item.dataset.leadId;
                    var newStatus = evt.to.dataset.status;
                    var oldStatus = evt.from.dataset.status;

                    if (!newStatus || oldStatus === newStatus) {
                        pendingChanges.delete(leadId);
                    } else {
                        pendingChanges.set(leadId, { newStatus: newStatus, oldStatus: oldStatus, element: evt.item });
                    }

                    [evt.from, evt.to].forEach(function (l) {
                        var col = l.closest('.w-72');
                        if (col) {
                            var counter = col.querySelector('[data-count]');
                            if (counter) counter.textContent = l.querySelectorAll('.kanban-card').length;
                        }
                    });

                    updateSaveButton();
                },
            });
        });

        function updateSaveButton() {
            var saveBtn = document.getElementById('pipeline-save-btn');
            var count = pendingChanges.size;

            if (count === 0) {
                if (saveBtn) saveBtn.remove();
                return;
            }

            if (!saveBtn) {
                saveBtn = document.createElement('button');
                saveBtn.id = 'pipeline-save-btn';
                saveBtn.className = 'px-5 py-3 rounded-xl bg-accent-600 hover:bg-accent-700 text-white font-semibold transition fixed bottom-6 right-6 shadow-lg z-50 flex items-center gap-2';
                saveBtn.onclick = savePendingChanges;
                document.body.appendChild(saveBtn);
            }

            saveBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg> {{ __('Save Changes') }} (' + count + ')';
        }

        function savePendingChanges() {
            if (pendingChanges.size === 0) return;

            var saveBtn = document.getElementById('pipeline-save-btn');
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> {{ __('Saving...') }}';
            }

            var changes = Array.from(pendingChanges.entries()).map(function (entry) {
                return { lead_id: parseInt(entry[0]), status: entry[1].newStatus };
            });

            fetch('{{ route('leads.batch-status') }}', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ changes: changes }),
            }).then(function (res) {
                if (!res.ok) throw new Error();
                return res.json();
            }).then(function (data) {
                pendingChanges.clear();
                if (saveBtn) saveBtn.remove();
                // Reload agar kartu pindah ke kolom yang benar
                // (khususnya yang keluar dari kolom New).
                window.location.reload();
            }).catch(function () {
                alert('{{ __('Failed to save changes. Please try again.') }}');
                if (saveBtn) {
                    saveBtn.disabled = false;
                    updateSaveButton();
                }
            });
        }

        function showToast(message) {
            var toast = document.createElement('div');
            toast.className = 'fixed bottom-20 right-6 bg-green-600 text-white px-5 py-3 rounded-xl shadow-lg z-50 transition-opacity flex items-center gap-2';
            toast.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg> ' + message;
            document.body.appendChild(toast);
            setTimeout(function () {
                toast.style.opacity = '0';
                setTimeout(function () { toast.remove(); }, 300);
            }, 3000);
        }
    }
    // Full load: script di ujung body sehingga DOM sudah siap.
    // Navigasi SPA (wire:navigate): DOMContentLoaded tidak fire lagi.
    initPipelineDnD();
    document.addEventListener('livewire:navigated', function () { initPipelineDnD(); });
    </script>
    @vite(['resources/js/pipeline-dnd.js'])
    @endif
</x-app-layout>
