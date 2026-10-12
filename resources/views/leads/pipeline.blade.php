<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Pipeline Lead</h1>
            <p class="text-slate-500 mt-1">{{ __('Pipeline milik :name — lead baru masuk di kolom New, geser ke Cool/Warm/Hot untuk menindaklanjuti', ['name' => auth()->user()->name]) }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ ($showAllClosed ?? false) ? route('leads.pipeline') : route('leads.pipeline', ['closed' => 'all']) }}"
               class="px-4 py-2.5 rounded-xl bg-accent-50 hover:bg-accent-100 text-accent-700 text-sm font-medium transition">
                {{ ($showAllClosed ?? false) ? __('Minggu ini') : __('Tampilkan semua') }}
            </a>
            @if(auth()->user()->can('view-marketing') || auth()->user()->can('manage-marketing'))
                <a href="{{ route('leads.index') }}"
                   class="px-4 py-2.5 rounded-xl bg-accent-50 hover:bg-accent-100 text-accent-700 text-sm font-medium transition">
                    {{ __('Tabel Lead') }}
                </a>
            @else
                <a href="{{ route('sales.my-leads') }}"
                   class="px-4 py-2.5 rounded-xl bg-accent-50 hover:bg-accent-100 text-accent-700 text-sm font-medium transition">
                    {{ __('My Leads') }}
                </a>
            @endif
            @can('manage-marketing')
                <a href="{{ route('leads.create') }}"
                   class="px-5 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white font-medium transition">
                    {{ __('+ Tambah Lead') }}
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
                            <span class="ml-1 text-[11px] text-slate-400">{{ __('minggu ini') }}</span>
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
            <p class="text-sm font-medium text-slate-500">{{ __('Belum ada lead di pipeline Anda.') }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ __('Lead baru dari Management akan muncul di sini.') }}</p>
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
                saveBtn.className = 'px-5 py-3 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-semibold transition fixed bottom-6 right-6 shadow-lg z-50 flex items-center gap-2';
                saveBtn.onclick = savePendingChanges;
                document.body.appendChild(saveBtn);
            }

            saveBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg> {{ __('Simpan Perubahan') }} (' + count + ')';
        }

        function savePendingChanges() {
            if (pendingChanges.size === 0) return;

            var saveBtn = document.getElementById('pipeline-save-btn');
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> {{ __('Menyimpan...') }}';
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
                alert('{{ __('Gagal menyimpan perubahan. Silakan coba lagi.') }}');
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
