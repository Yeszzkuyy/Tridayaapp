{{-- Tombol "Buat Dokumen BAST" — dropdown 4 pilihan template perusahaan
     (MGK / NTI / TPS / WANI) dari link Google Drive "Make a copy" di env.
     Murni tautan statis yang dibuka di tab baru (tanpa Google Drive API):
     pengguna membuat salinan dokumen sendiri, template asli tidak berubah.
     Pilihan tanpa link disembunyikan; bila semua kosong tombol tidak tampil.

     Animasi hover ala switcher Month/Week/Day (RubberSegment di jadwal teknisi):
     pill highlight meluncur halus efek spring mengikuti kursor antar item,
     teks item berubah putih saat pill menimpanya, item mengecil sedikit saat
     ditekan. Daftar tetap vertikal (dropdown), bukan segmen horizontal. --}}
@php
    $bastTemplates = collect(config('services.bast-templates'))->filter();
@endphp

@if($bastTemplates->isNotEmpty())
    <x-dropdown align="right" width="w-56">
        <x-slot name="trigger">
            <x-icon-button type="button" data-bast-trigger title="{{ __('Create BAST Document') }}" label="{{ __('Create BAST Document') }}">
                <x-icon name="file-text" class="h-5 w-5" />
            </x-icon-button>
        </x-slot>
        <x-slot name="content">
            <div class="py-1" data-bast-menu
                 x-data="{
                    active: null,
                    y: 0,
                    h: 0,
                    visible: false,
                    get pillStyle() {
                        return 'transform: translateY(' + this.y + 'px); height: ' + this.h + 'px; opacity: ' + (this.visible ? 1 : 0) + ';'
                            + 'transition: transform 380ms cubic-bezier(0.34, 1.35, 0.64, 1), height 300ms cubic-bezier(0.23, 1, 0.32, 1), opacity 180ms ease;';
                    },
                    move(el, i) {
                        this.y = el.offsetTop;
                        this.h = el.offsetHeight;
                        this.visible = true;
                        this.active = i;
                    },
                    hide() {
                        this.visible = false;
                        this.active = null;
                    },
                    closePanel() {
                        setTimeout(() => {
                            const trigger = document.querySelector('[data-bast-trigger]');
                            const root = trigger && trigger.closest('[x-data]');
                            if (root) root.dispatchEvent(new CustomEvent('close'));
                        }, 80);
                    },
                 }"
                 @mouseleave="hide()">
                <p class="px-4 py-1 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Choose company template') }}</p>
                <div class="relative px-1.5 pb-1.5">
                    {{-- Pill highlight (efek spring) — mengambang di belakang teks item --}}
                    <span class="pointer-events-none absolute left-1.5 right-1.5 top-0 rounded-lg bg-accent-600 dark:bg-accent-500"
                          :style="pillStyle" aria-hidden="true"></span>
                    @foreach($bastTemplates as $company => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener"
                           class="relative block px-4 py-2 text-start text-sm leading-5 transition-all duration-200 ease-out active:scale-[0.98]"
                           :class="active === {{ $loop->index }} ? 'text-white' : 'text-gray-700 dark:text-slate-200'"
                           @mouseenter="move($el, {{ $loop->index }})"
                           @click="closePanel()">{{ $company }}</a>
                    @endforeach
                </div>
            </div>
        </x-slot>
    </x-dropdown>
@endif
