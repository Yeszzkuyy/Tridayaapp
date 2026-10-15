{{-- Tombol "Buat Dokumen BAST" — dropdown 4 pilihan template perusahaan
     (MGK / NTI / TPS / WANI) dari link Google Drive "Make a copy" di env.
     Murni tautan statis yang dibuka di tab baru (tanpa Google Drive API):
     pengguna membuat salinan dokumen sendiri, template asli tidak berubah.
     Pilihan tanpa link disembunyikan; bila semua kosong tombol tidak tampil. --}}
@php
    $bastTemplates = collect(config('services.bast-templates'))->filter();
@endphp

@if($bastTemplates->isNotEmpty())
    <x-dropdown align="right" width="w-56">
        <x-slot name="trigger">
            <x-icon-button type="button" title="{{ __('Create BAST Document') }}" label="{{ __('Create BAST Document') }}">
                <x-icon name="file-text" class="h-5 w-5" />
            </x-icon-button>
        </x-slot>
        <x-slot name="content">
            <div class="py-1">
                <p class="px-4 py-1 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Choose company template') }}</p>
                @foreach($bastTemplates as $company => $url)
                    {{-- Item dengan hover halus: teks geser pelan + panah fade-in + warna accent --}}
                    <a href="{{ $url }}" target="_blank" rel="noopener"
                       class="group/bast-item flex w-full items-center justify-between gap-3 px-4 py-2 text-start text-sm leading-5 text-gray-700 transition-colors duration-300 ease-out hover:bg-accent-50 hover:text-accent-700 focus:outline-none focus:bg-accent-50 dark:text-slate-200 dark:hover:bg-slate-700 dark:hover:text-accent-300">
                        <span class="transition-transform duration-300 ease-out group-hover/bast-item:translate-x-1">{{ $company }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                             class="h-4 w-4 -translate-x-1 opacity-0 transition-all duration-300 ease-out group-hover/bast-item:translate-x-0 group-hover/bast-item:opacity-100"
                             aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                    </a>
                @endforeach
            </div>
        </x-slot>
    </x-dropdown>
@endif
