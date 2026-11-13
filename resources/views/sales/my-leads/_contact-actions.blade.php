{{-- Aksi cepat kontak + buka lead/follow up. Klik WA/Email/Call hanya
     mencatat "mulai menghubungi", bukan bukti pesan terkirim. --}}
@php
    $followUpId = $followUp->id ?? null;
    $contactUrl = $contact['contactUrl'] ?? null;
@endphp

<div class="flex flex-wrap items-center justify-end gap-1.5">
    <x-dropdown align="right" width="w-44">
        <x-slot name="trigger">
            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white transition hover:scale-110 hover:border-accent-300 active:scale-95 dark:border-slate-700 dark:bg-slate-800"
                    title="{{ __('Lead color') }}" aria-label="{{ __('Lead color') }}">
                <span class="h-3.5 w-3.5 rounded-full ring-1 ring-black/10 dark:ring-white/20" :class="colorDotClass()"></span>
            </button>
        </x-slot>
        <x-slot name="content">
            <p class="px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Lead color') }}</p>
            @foreach(['default' => __('Default'), 'red' => __('Red'), 'yellow' => __('Yellow'), 'green' => __('Green')] as $value => $label)
                <button type="button" x-on:click="setColor('{{ $value }}')"
                        class="flex w-full items-center gap-2 px-3 py-2 text-start text-sm text-slate-700 transition hover:bg-[var(--menu-hover-bg)] dark:text-slate-200">
                    <span class="h-3 w-3 rounded-full {{ \App\Models\Lead::colorDotClasses($value === 'default' ? null : $value) }}"></span>
                    <span class="flex-1">{{ $label }}</span>
                    <span x-show="color === '{{ $value === 'default' ? '' : $value }}'" class="text-accent-600 dark:text-accent-400">&#10003;</span>
                </button>
            @endforeach
        </x-slot>
    </x-dropdown>

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
