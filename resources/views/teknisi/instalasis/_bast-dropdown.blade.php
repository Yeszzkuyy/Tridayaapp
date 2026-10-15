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
                    <x-dropdown-link :href="$url" target="_blank" rel="noopener">{{ $company }}</x-dropdown-link>
                @endforeach
            </div>
        </x-slot>
    </x-dropdown>
@endif
