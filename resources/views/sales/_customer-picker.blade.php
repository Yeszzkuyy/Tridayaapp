{{-- Searchable customer picker (Add Follow Up, filter Follow Up, ...).
     Params: $name, $options ([id => label]), $selected = null,
             $placeholder = null, $required = false, $hint = null,
             $submitOnPick = false (mode filter: pilih = isi teks + submit form).
     Memancarkan event `customer-picked` {id, field} tiap ganti pilihan. --}}
@once
<style>
    /* Motion dropdown disamakan glider: pop 180ms in / 120ms out. */
    .fu-combo-menu { opacity: 0; transform: scale(.95); transform-origin: top; transition: opacity 180ms cubic-bezier(.23,1,.32,1), transform 180ms cubic-bezier(.23,1,.32,1); pointer-events: none; }
    .fu-combo-menu[data-open="1"] { opacity: 1; transform: none; pointer-events: auto; }
    .fu-combo-menu[data-open="0"] { transition-duration: 120ms, 120ms; }
</style>
@endonce
<div x-data="{
        options: {{ json_encode($options ?? []) }},
        field: {{ json_encode($name) }},
        submitOnPick: {{ json_encode(!empty($submitOnPick)) }},
        selectedId: {{ json_encode($selected ?? null) }},
        query: '',
        open: false,
        active: 0,
        str(v) { return (v === null || v === undefined || v === '') ? null : String(v); },
        init() {
            this.selectedId = this.str(this.selectedId);
            if (this.selectedId && !this.options[this.selectedId]) {
                this.selectedId = null;
                this.$nextTick(() => this.$dispatch('customer-picked', { id: null, field: this.field }));
            }
            this.query = this.selectedId ? (this.options[this.selectedId] ?? '') : '';
            if (this.submitOnPick) {
                const el = this.$el.querySelector('input[type=text]');
                if (el && el.value) this.query = el.value;
            }
        },
        get entries() {
            const q = this.query.toLowerCase().trim();
            return Object.entries(this.options).filter(([id, label]) => !q || label.toLowerCase().includes(q));
        },
        pick(id, label) {
            this.selectedId = String(id);
            this.query = label;
            this.open = false;
            this.$dispatch('customer-picked', { id: String(id), field: this.field });
            if (this.submitOnPick) this.$el.closest('form')?.requestSubmit();
        },
        clear() {
            this.selectedId = null;
            this.query = '';
            this.open = true;
            this.active = 0;
            this.$dispatch('customer-picked', { id: null, field: this.field });
            if (this.submitOnPick) this.$el.closest('form')?.requestSubmit();
        },
        onArrow(e, step) {
            if (!this.open || !this.entries.length) return;
            e.preventDefault();
            this.active = Math.max(0, Math.min(this.active + step, this.entries.length - 1));
            this.$nextTick(() => this.$refs.menu?.querySelectorAll('button')[this.active]?.scrollIntoView({ block: 'nearest' }));
        },
        pickActive(e) {
            if (!this.open || !this.entries.length) return;
            e.preventDefault();
            const [id, label] = this.entries[Math.max(0, Math.min(this.active, this.entries.length - 1))];
            this.pick(id, label);
        }
    }" class="relative">
    @if(empty($submitOnPick))
        <input type="hidden" name="{{ $name }}" :value="selectedId">
    @endif
    <input type="text" x-model="query" @focus="open = true; active = 0" @input="open = true; active = 0"
           @keydown.escape="open = false"
           @keydown.arrow-down="onArrow($event, 1)" @keydown.arrow-up="onArrow($event, -1)"
           @keydown.enter="pickActive($event)"
           @blur="setTimeout(() => open = false, 150)"
           placeholder="{{ $placeholder ?? __('Search customer...') }}" autocomplete="off"
           @if(!empty($submitOnPick)) name="{{ $name }}" value="{{ request($name, '') }}" @endif
           @if(!empty($required)) required @endif
           class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 pr-16">
    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
             :style="{ transform: open ? 'rotate(180deg)' : '', transition: 'transform 200ms cubic-bezier(0.23,1,0.32,1)' }"><path d="m6 9 6 6 6-6" /></svg>
    </span>
    <button type="button" x-show="selectedId" @mousedown.prevent="clear()"
            title="{{ __('Change customer') }}" aria-label="{{ __('Change customer') }}"
            class="absolute right-8 top-1/2 -translate-y-1/2 rounded-lg px-1 py-1 text-lg leading-none text-slate-400 hover:text-red-500 transition">&times;</button>
    <div x-cloak x-ref="menu" :data-open="open ? '1' : '0'"
         class="fu-combo-menu absolute z-20 mt-1 w-full bg-white dark:bg-slate-800 border-2 border-slate-300 dark:border-slate-600 rounded-xl shadow-lg max-h-60 overflow-y-auto divide-y divide-slate-200 dark:divide-slate-700">
        <template x-for="([id, label], i) in entries" :key="id">
            <button type="button" @mousedown.prevent="pick(id, label)"
                    class="w-full text-left px-4 py-2.5 text-sm hover:bg-accent-50 dark:hover:bg-accent-500/10 text-slate-700 dark:text-slate-200 transition"
                    :class="i === active
                        ? 'bg-accent-500/10 text-accent-700 dark:bg-accent-500/20 dark:text-accent-200'
                        : (String(id) === String(selectedId) ? 'bg-accent-50 dark:bg-accent-500/10' : '')"
                    x-text="label"></button>
        </template>
        <p x-show="entries.length === 0" class="px-4 py-3 text-sm text-slate-400">{{ __('No results.') }}</p>
    </div>
</div>
@if(!empty($hint))<p class="mt-1 text-xs text-slate-400">{{ $hint }}</p>@endif
