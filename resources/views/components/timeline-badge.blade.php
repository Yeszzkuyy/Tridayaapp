{{-- Badge jenis aktivitas untuk timeline customer. --}}
@props(['type' => 'project'])

@php
[$label, $color] = match ($type) {
    'followup' => [__('Follow-up'), 'cyan'],
    'meeting' => [__('Meeting'), 'purple'],
    'document' => [__('Document'), 'blue'],
    'pic' => ['PIC', 'emerald'],
    'invoice' => [__('Invoice'), 'orange'],
    'po' => ['PO', 'yellow'],
    'payment' => [__('Payment'), 'green'],
    default => [__('Project'), 'slate'],
};
@endphp

<x-status-badge :color="$color">{{ $label }}</x-status-badge>
