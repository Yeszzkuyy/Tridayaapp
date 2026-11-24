@props(['status' => 'draft', 'class' => ''])

@php
    // Satu peta status → warna + ikon untuk seluruh halaman Quotation.
    // Warna memakai token status (bukan hex) dan latar memakai tint token.
    $map = [
        'draft' => [
            'color' => 'var(--text-secondary)',
            'icon' => '<path d="M4 20h4L18.5 9.5a2.1 2.1 0 0 0 0-3l-1-1a2.1 2.1 0 0 0-3 0L4 16v4Z"/><path d="M13.5 6.5l4 4"/>',
        ],
        'pending_technical' => [
            'color' => 'var(--warning)',
            'icon' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        ],
        'ready' => [
            'color' => 'var(--info)',
            'icon' => '<circle cx="12" cy="12" r="8.5"/><path d="m8.5 12.3 2.4 2.4 4.8-5"/>',
        ],
        'sent' => [
            'color' => 'var(--info)',
            'icon' => '<path d="M3 11l18-8-8 18-2.5-7.5L3 11Z"/>',
        ],
        'viewed' => [
            'color' => 'var(--info)',
            'icon' => '<path d="M2.5 12S6 6.5 12 6.5 21.5 12 21.5 12 18 17.5 12 17.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.5"/>',
        ],
        'revision' => [
            'color' => 'var(--warning)',
            'icon' => '<path d="M20 12a8 8 0 1 1-2.3-5.6"/><path d="M20 4v5h-5"/>',
        ],
        'accepted' => [
            'color' => 'var(--success)',
            'icon' => '<path d="m5 12 4.5 4.5L19 7"/>',
        ],
        'rejected' => [
            'color' => 'var(--danger)',
            'icon' => '<path d="M6 6l12 12M18 6 6 18"/>',
        ],
        'expired' => [
            'color' => 'var(--danger)',
            'icon' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        ],
        'cancelled' => [
            'color' => 'var(--text-tertiary)',
            'icon' => '<circle cx="12" cy="12" r="8.5"/><path d="m6.5 17.5 11-11"/>',
        ],
    ];

    $item = $map[$status] ?? [
        'color' => 'var(--text-secondary)',
        'icon' => '<circle cx="12" cy="12" r="3"/>',
    ];
@endphp

<span class="inline-flex h-6 min-w-24 items-center justify-center gap-1.5 whitespace-nowrap rounded-full px-2.5 text-xs font-semibold {{ $class }}"
      style="color: {{ $item['color'] }}; background-color: color-mix(in srgb, {{ $item['color'] }} 10%, transparent);">
    <svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        {!! $item['icon'] !!}
    </svg>
    <span>{{ \App\Models\Proposal::statusLabel($status) }}</span>
</span>
