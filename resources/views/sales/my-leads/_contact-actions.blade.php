{{-- Aksi baris My Leads: buka detail lead atau catat follow up (+).
     Tombol kontak cepat (WhatsApp/Email/Telepon) tidak tampil di halaman ini;
     nomor & email tetap terbaca di kolom Contact. --}}
@php
    $first = $first ?? false;
    $followUpUrl = route('sales.follow-ups.create', [
        'customer_id' => $lead->customer_id,
        'lead_id' => $lead->id,
        'return' => 'my-leads',
    ]);
@endphp

<div class="flex items-center justify-end gap-1">
    <x-icon-button as="a" icon="view" href="{{ route('leads.show', $lead) }}" title="{{ __('View Lead') }}" />

    {{-- target=_top: formulir dibuka sebagai halaman penuh. Tanpa itu, isi tab
         langsung memantul ke daftar Follow Up karena alamatnya satu keluarga
         menu Follow Up. --}}
    @if($first)
        <span data-tour="followup">
            <x-icon-button as="a" icon="add" target="_top" :href="$followUpUrl" title="{{ __('Follow Up') }}" />
        </span>
    @else
        <x-icon-button as="a" icon="add" target="_top" :href="$followUpUrl" title="{{ __('Follow Up') }}" />
    @endif
</div>
