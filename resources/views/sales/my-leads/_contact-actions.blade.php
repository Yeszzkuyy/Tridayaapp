{{-- Aksi cepat kontak + buka lead/follow up. Klik WA/Email/Call hanya
     mencatat "mulai menghubungi", bukan bukti pesan terkirim. --}}
@php
    $followUpId = $followUp->id ?? null;
    $contactUrl = $contact['contactUrl'] ?? null;
@endphp

<div class="flex flex-wrap items-center justify-end gap-1.5">
    @if(!empty($contact['whatsapp']))
        <x-icon-button as="a" icon="whatsapp" href="{{ $contact['whatsapp'] }}" target="_blank" rel="noopener"
                       title="{{ __('WhatsApp') }}"
                       data-contact-channel="whatsapp"
                       data-contact-url="{{ $contactUrl }}"
                       data-contact-follow-up="{{ $followUpId }}" />
    @endif

    @if(!empty($contact['email']))
        <x-icon-button as="a" icon="mail"
                       href="mailto:{{ $contact['email'] }}?subject={{ rawurlencode(__('Follow up')) }}"
                       title="{{ __('Email') }}"
                       data-contact-channel="email"
                       data-contact-url="{{ $contactUrl }}"
                       data-contact-follow-up="{{ $followUpId }}" />
    @endif

    @if(!empty($contact['phone']))
        <x-icon-button as="a" icon="phone" href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}"
                       title="{{ __('Call') }}"
                       data-contact-channel="call"
                       data-contact-url="{{ $contactUrl }}"
                       data-contact-follow-up="{{ $followUpId }}" />
    @endif

    <x-icon-button as="a" icon="view" href="{{ route('leads.show', $lead) }}" title="{{ __('View Lead') }}" />

    @if($first)
        <span data-tour="followup">
            <x-icon-button as="a" icon="add" href="{{ route('sales.follow-ups.create', ['customer_id' => $lead->customer_id, 'lead_id' => $lead->id]) }}" title="{{ __('Follow Up') }}" />
        </span>
    @else
        <x-icon-button as="a" icon="add" href="{{ route('sales.follow-ups.create', ['customer_id' => $lead->customer_id, 'lead_id' => $lead->id]) }}" title="{{ __('Follow Up') }}" />
    @endif
</div>
