{{-- Badge jenis aktivitas untuk timeline customer. --}}
@props(['type' => 'project'])

@php
[$label, $color] = match ($type) {
    'followup' => [__('Follow-up'), 'cyan'],
    'meeting' => [__('Meeting'), 'purple'],
    default => [__('Project'), 'slate'],
};
@endphp

<x-status-badge :color="$color">{{ $label }}</x-status-badge>
