{{-- Tombol "Selesai" untuk request Inside Sales: bisa dipakai dari halaman
     detail maupun baris daftar request. Hanya muncul untuk assignee Inside
     Sales atau management, dan hilang saat status sudah Done. --}}

@props(['task'])

@php
    $user = auth()->user();
    $canComplete = $user && $task->status !== 'done' && (
        (int) $task->assigned_to === (int) $user->id
        || $user->can('manage-sales-leads')
    );
    $confirmDetails = json_encode([
        ['label' => __('Request'), 'value' => $task->title],
        ['label' => __('Customer'), 'value' => $task->lead?->customer?->name ?? '-'],
    ], JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE);
@endphp

@if($canComplete)
    <form action="{{ route('lead-tasks.complete', $task) }}" method="POST"
          data-confirm="{{ __('The request will be closed and shown as Done in the Sales and Inside Sales lists.') }}"
          data-confirm-title="{{ __('Mark this request as done?') }}"
          data-confirm-tone="success"
          data-confirm-label="{{ __('Yes, mark as done') }}"
          data-confirm-details="{{ $confirmDetails }}">
        @csrf
        @method('PATCH')
        <x-icon-button type="submit" icon="check-circle" label="{{ __('Mark as Done') }}" title="{{ __('Mark as Done') }}" />
    </form>
@endif
