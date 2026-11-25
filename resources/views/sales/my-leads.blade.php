<x-app-layout>
    <div x-data="{ tips: localStorage.getItem('sales_followup_tips') !== 'off' }">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-content-primary">{{ __('My Leads') }}</h1>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2">
            @can('manage-sales')
                <x-icon-button as="a" icon="add" href="{{ route('leads.create') }}" label="{{ __('Add Lead') }}" title="{{ __('Add Lead') }}" />
                <x-flow-button as="a" :href="route('sales.follow-ups.create')"
                               icon="add"
                               label="{{ __('Add Follow Up') }}"
                               title="{{ __('Add Follow Up') }}" />
            @endcan
            <button type="button"
                    @click="tips = true; localStorage.setItem('sales_followup_tips', 'on')"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-300 bg-[var(--card-bg)] text-content-secondary transition-all duration-300 hover:bg-slate-50 hover:text-accent-600 active:scale-95 dark:border-slate-600 dark:bg-slate-800 dark:hover:bg-slate-700"
                    title="{{ __('Show guide') }}">
                <span class="text-sm font-bold">?</span>
                <span class="sr-only">{{ __('Show guide') }}</span>
            </button>
            <x-icon-button as="a" icon="import" href="{{ route('sales.my-leads.export', request()->only(['search', 'company', 'pic', 'project', 'status', 'last_activity', 'next_follow_up', 'overdue', 'touched', 'active', 'won_month', 'sort', 'sales_id'])) }}" title="{{ __('Export CSV') }}" />
            <x-back-button :href="route('sales.dashboard')" :label="__('Back')" />
        </div>
    </div>

    @php($newLeadCount = $leads->getCollection()->whereNull('acknowledged_at')->count())
    <div x-show="tips" x-cloak
         class="mb-4 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-800/60">
        <div class="flex items-start gap-3">
            <x-icon name="bolt" class="h-5 w-5 shrink-0 text-content-tertiary" />
            <div class="min-w-0 flex-1 text-sm text-content-secondary">
                <p class="font-semibold">{{ __('Quick guide: 3 steps to follow up') }}</p>
                <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 bg-[var(--card-bg)] px-3 py-2 dark:border-slate-700 dark:bg-slate-800/60">
                        <span class="text-xs font-semibold text-content-secondary">1</span>
                        <p class="mt-0.5">{{ __('Pick a prospect from the table.') }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-[var(--card-bg)] px-3 py-2 dark:border-slate-700 dark:bg-slate-800/60">
                        <span class="text-xs font-semibold text-content-secondary">2</span>
                        <p class="mt-0.5">{{ __('Contact the PIC or write the latest note.') }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-[var(--card-bg)] px-3 py-2 dark:border-slate-700 dark:bg-slate-800/60">
                        <span class="text-xs font-semibold text-content-secondary">3</span>
                        <p class="mt-0.5">{{ __('Set the next follow up and update the stage.') }}</p>
                    </div>
                </div>
                @if($newLeadCount > 0)
                    <p class="mt-2 font-medium text-content-secondary">{{ __('You have :count new lead(s) from Management. Record the first follow up today.', ['count' => $newLeadCount]) }}</p>
                @endif
            </div>
            <button type="button" @click="localStorage.setItem('sales_followup_tips', 'off'); tips = false"
                    class="rounded-xl p-1.5 text-content-tertiary transition hover:bg-white hover:text-content-secondary dark:hover:bg-slate-700 dark:hover:text-content-tertiary"
                    title="{{ __('Dismiss') }}">
                <span class="sr-only">{{ __('Dismiss') }}</span>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    <div class="bg-[var(--card-bg)] dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600">
        @include('sales.my-leads._filters')

        <div class="hidden lg:block overflow-x-auto">
            @include('sales.my-leads._table')
        </div>

        <div class="lg:hidden p-3 space-y-3">
            @include('sales.my-leads._cards')
        </div>

        @if($leads->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-600">
                {{ $leads->links() }}
            </div>
        @endif
    </div>
    </div>

    <script>
        window.myLeadsRow = function (config) {
            const jsonHeaders = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            };
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            if (token) jsonHeaders['X-CSRF-TOKEN'] = token;

            return {
                stage: config.stage,
                savedStage: config.stage,
                note: config.note || '',
                draftNote: config.note || '',
                editingNote: false,
                savingNote: false,
                noteError: '',
                lostOpen: false,
                lostReason: '',
                lostNote: '',
                stageSaving: false,
                nextOpen: false,
                nextDate: config.nextDate || '',
                nextTime: config.nextTime || '',
                nextLabel: config.nextLabel || '',
                nextState: config.nextState || 'upcoming',
                nextSaving: false,
                hasAcceptedProposal: !!config.hasAcceptedProposal,
                stageDotClass() {
                    const map = {
                        cool: 'bg-blue-500',
                        warm: 'bg-amber-400',
                        hot: 'bg-orange-500',
                        won: 'bg-emerald-500',
                        lost: 'bg-red-500',
                    };
                    return map[this.stage] || 'bg-slate-300 dark:bg-slate-600';
                },
                setStageSelect(value) {
                    const mount = this.$root.querySelector('[data-glide-mount]');
                    if (!mount || !window.__glideRemount) return;
                    let options = [];
                    try { options = JSON.parse(mount.dataset.options || '[]'); } catch (e) {}
                    window.__glideRemount(mount, options, value);
                },
                startNote() {
                    this.draftNote = this.note;
                    this.noteError = '';
                    this.editingNote = true;
                },
                cancelNote() {
                    this.editingNote = false;
                    this.noteError = '';
                },
                saveNote() {
                    const text = this.draftNote.trim();
                    if (!text) {
                        this.noteError = config.requiredNote;
                        return;
                    }
                    this.savingNote = true;
                    this.noteError = '';
                    fetch(config.noteUrl, { method: 'PATCH', headers: jsonHeaders, body: JSON.stringify({ note: text }) })
                        .then(async (response) => {
                            const data = await response.json().catch(() => ({}));
                            if (!response.ok) {
                                throw new Error((data.errors && data.errors.note && data.errors.note[0]) || data.message || config.genericError);
                            }
                            return data;
                        })
                        .then((data) => {
                            this.note = data.note || text;
                            this.draftNote = '';
                            this.editingNote = false;
                            if (window.toast) window.toast(data.message || config.savedNote, true);
                        })
                        .catch((error) => {
                            this.noteError = error.message;
                            if (window.toast) window.toast(error.message, false);
                        })
                        .finally(() => { this.savingNote = false; });
                },
                stageChanged(value) {
                    if (value === 'lost') {
                        this.stage = this.savedStage;
                        this.setStageSelect(this.savedStage);
                        this.lostOpen = true;
                        return;
                    }
                    if (value === 'won' && !this.hasAcceptedProposal) {
                        this.stage = this.savedStage;
                        this.setStageSelect(this.savedStage);
                        if (window.toast) window.toast(config.wonNeedsQuotation, false);
                        return;
                    }
                    this.patchStage(value, {});
                },
                patchStage(value, extra) {
                    this.stageSaving = true;
                    fetch(config.statusUrl, {
                        method: 'PATCH',
                        headers: jsonHeaders,
                        body: JSON.stringify(Object.assign({ status: value }, extra)),
                    })
                        .then(async (response) => {
                            const data = await response.json().catch(() => ({}));
                            if (!response.ok) {
                                throw new Error((data.errors && data.errors.status && data.errors.status[0]) || data.message || config.genericError);
                            }
                            return data;
                        })
                        .then(() => {
                            this.savedStage = value;
                            this.stage = value;
                            this.setStageSelect(value);
                            this.lostOpen = false;
                            if (window.toast) window.toast(config.stageSaved, true);
                        })
                        .catch((error) => {
                            this.stage = this.savedStage;
                            this.setStageSelect(this.savedStage);
                            if (window.toast) window.toast(error.message, false);
                        })
                        .finally(() => { this.stageSaving = false; });
                },
                confirmLost() {
                    if (!this.lostReason || !this.lostNote.trim()) {
                        if (window.toast) window.toast(config.lostRequired, false);
                        return;
                    }
                    this.patchStage('lost', { lost_reason: this.lostReason, lost_note: this.lostNote.trim() });
                },
                cancelLost() {
                    this.lostOpen = false;
                    this.lostReason = '';
                    this.lostNote = '';
                    this.stage = this.savedStage;
                    this.setStageSelect(this.savedStage);
                },
                openNext() {
                    this.nextOpen = true;
                    if (!this.nextDate) this.nextDate = config.today;
                },
                cancelNext() {
                    this.nextOpen = false;
                },
                saveNext() {
                    if (!this.nextDate) {
                        if (window.toast) window.toast(config.dateRequired, false);
                        return;
                    }
                    this.nextSaving = true;
                    fetch(config.nextUrl, {
                        method: 'PATCH',
                        headers: jsonHeaders,
                        body: JSON.stringify({
                            next_follow_up_date: this.nextDate,
                            next_follow_up_time: this.nextTime || null,
                        }),
                    })
                        .then(async (response) => {
                            const data = await response.json().catch(() => ({}));
                            if (!response.ok) {
                                throw new Error((data.errors && data.errors.next_follow_up_date && data.errors.next_follow_up_date[0]) || data.message || config.genericError);
                            }
                            return data;
                        })
                        .then((data) => {
                            this.nextLabel = data.date;
                            this.nextTime = data.time || '';
                            this.nextState = data.state;
                            this.nextOpen = false;
                            if (window.toast) window.toast(data.message, true);
                        })
                        .catch((error) => {
                            if (window.toast) window.toast(error.message, false);
                        })
                        .finally(() => { this.nextSaving = false; });
                },
                nextClass() {
                    return {
                        due: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                        overdue: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                        today: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                        completed: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
                        upcoming: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
                    }[this.nextState] || 'bg-slate-100 text-content-secondary dark:bg-slate-700 dark:text-content-secondary';
                },
                nextStateText() {
                    return config.stateLabels[this.nextState] || config.stateLabels.upcoming;
                },
            };
        };
    </script>

    @if($leads->isNotEmpty())
        <script type="module" src="{{ \Illuminate\Support\Facades\Vite::asset('resources/js/leads-tour.js') }}"></script>
    @endif
</x-app-layout>
