@props(['name', 'class' => 'w-5 h-5'])

@php
$icons = [
    'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" />',

    'home' => '<path d="M3 10.5L12 3l9 7.5" /><path d="M5 9.5V20a1 1 0 001 1h4v-6a1 1 0 011-1h2a1 1 0 011 1v6h4a1 1 0 001-1V9.5" />',

    'users' => '<circle cx="9" cy="8" r="3.25" /><path d="M3.25 20c0-3.6 2.6-6.25 5.75-6.25S14.75 16.4 14.75 20" /><circle cx="17" cy="9" r="2.4" /><path d="M15.6 13.3c2.3.35 4.15 2.5 4.15 6.2" />',

    'folder' => '<path d="M3 7.5a2 2 0 012-2h3.6l1.6 1.8h7.2a2 2 0 012 2V17a2 2 0 01-2 2H5a2 2 0 01-2-2V7.5z" />',

    'search' => '<circle cx="10.8" cy="10.8" r="6.3" /><path d="m16 16 4.5 4.5" />',

    'eye' => '<path d="M2.5 12s3.3-5.5 9.5-5.5 9.5 5.5 9.5 5.5-3.3 5.5-9.5 5.5S2.5 12 2.5 12Z" /><circle cx="12" cy="12" r="2.5" />',

    'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2" /><path d="M3.5 10h17" /><path d="M8 3v4" /><path d="M16 3v4" />',

    'activity' => '<polyline points="3,12.5 7.5,12.5 9.5,6.5 14,18.5 16.2,12.5 21,12.5" />',

    'book' => '<path d="M12 6.2C12 4.98 10.98 4 9.75 4H4.5v14h5.25c1.23 0 2.25.98 2.25 2.2" /><path d="M12 6.2C12 4.98 13.02 4 14.25 4h5.25v14h-5.25c-1.23 0-2.25.98-2.25 2.2" />',

    'tools' => '<path d="M14.5 6.2a3.6 3.6 0 00-4.9 4.9L4 16.7l3.3 3.3 5.6-5.6a3.6 3.6 0 004.9-4.9l-2.35 2.35-1.8-1.8 2.35-2.35z" />',

    'edit' => '<path d="M4 20h4.2L18.5 9.7a2.1 2.1 0 000-3l-1.2-1.2a2.1 2.1 0 00-3 0L4 15.8V20z" /><path d="M13.5 6.5l4 4" />',

    'trash' => '<path d="M5 7h14" /><path d="M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2" /><path d="M6.5 7l.8 12a2 2 0 002 1.9h5.4a2 2 0 002-1.9l.8-12" /><path d="M10 11v6" /><path d="M14 11v6" />',

    'restore' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8" /><path d="M3 3v5h5" />',

    'chevron-right' => '<path d="M9 5l7 7-7 7" />',
    'chevron-down' => '<path d="M6 9l6 6 6-6" />',

    'logout' => '<path d="M15 4H8a2 2 0 00-2 2v12a2 2 0 002 2h7" /><path d="M19 12H9" /><path d="M15 8l4 4-4 4" />',

    // ============ TAMBAHKAN INI ============
    'chart-bar' => '<path d="M4 20h16M6 16l4-8 4 4 4-6" />',

    // Papan kolom (pipeline/kanban), Heroicons outline view-columns
    'view-columns' => '<path d="M9 4.5v15m6-15v15m-10.5 0h15a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25H4.5a2.25 2.25 0 0 0-2.25 2.25v10.5a2.25 2.25 0 0 0 2.25 2.25Z" />',

    'settings' => '<path d="M12.5 3h-1a1.5 1.5 0 00-1.5 1.5v.5a1.5 1.5 0 01-2.2 1.3l-.4-.2a1.5 1.5 0 00-2.1.6l-.5.9a1.5 1.5 0 00.6 2.1l.4.2a1.5 1.5 0 010 2.6l-.4.2a1.5 1.5 0 00-.6 2.1l.5.9a1.5 1.5 0 002.1.6l.4-.2a1.5 1.5 0 012.2 1.3v.5a1.5 1.5 0 001.5 1.5h1a1.5 1.5 0 001.5-1.5v-.5a1.5 1.5 0 012.2-1.3l.4.2a1.5 1.5 0 002.1-.6l.5-.9a1.5 1.5 0 00-.6-2.1l-.4-.2a1.5 1.5 0 010-2.6l.4-.2a1.5 1.5 0 00.6-2.1l-.5-.9a1.5 1.5 0 00-2.1-.6l-.4.2a1.5 1.5 0 01-2.2-1.3v-.5A1.5 1.5 0 0012.5 3z" /><circle cx="12" cy="12" r="2.5" />',

    'sun' => '<circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />',

    'moon' => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" />',

    'camera' => '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z" /><circle cx="12" cy="13" r="3" />',

    'briefcase' => '<rect x="3" y="7.5" width="18" height="12" rx="2" /><path d="M9 7.5V6a2 2 0 012-2h2a2 2 0 012 2v1.5" /><path d="M3 12.5h18" />',

    // Ikon field customer & statistik (detail customer)
    'building' => '<path d="M4 21V5a2 2 0 012-2h7a2 2 0 012 2v16" /><path d="M15 9.5h3a2 2 0 012 2V21" /><path d="M8 7h3M8 11h3M8 15h3M17 13h1M17 17h1" /><path d="M2.5 21h19" />',
    'user' => '<circle cx="12" cy="8" r="3.5" /><path d="M5 20c.8-3.2 3.5-5 7-5s6.2 1.8 7 5" />',
    'map-pin' => '<path d="M12 21s-6.5-5.2-6.5-10.2a6.5 6.5 0 0 1 13 0C18.5 15.8 12 21 12 21z" /><circle cx="12" cy="10.5" r="2.3" />',
    'phone' => '<path d="M4.5 4.5h3.6l1.6 4-2.2 1.7a13.5 13.5 0 0 0 6.2 6.2l1.7-2.2 4 1.6v3.6a1.5 1.5 0 0 1-1.6 1.5C11 20.4 3.6 13 3 6.1a1.5 1.5 0 0 1 1.5-1.6z" />',
    'chat' => '<path d="M4 6a2.5 2.5 0 0 1 2.5-2.5h11A2.5 2.5 0 0 1 20 6v7a2.5 2.5 0 0 1-2.5 2.5H9L4.5 19.5V6z" /><path d="M8 9.5h8M8 12.5h5" />',
    'mail' => '<rect x="3.5" y="5.5" width="17" height="13" rx="2" /><path d="m4.5 7.5 7.5 6 7.5-6" />',
    'check-circle' => '<circle cx="12" cy="12" r="8.5" /><path d="m8.5 12.3 2.4 2.4 4.8-5" />',
    'bolt' => '<path d="M13 3 5.5 13.5H11L10 21l7.5-10.5H12L13 3z" />',
    'message' => '<path d="M21 12a8.5 8.5 0 01-8.5 8.5H5.5l-2 2V12a8.5 8.5 0 018.5-8.5A8.5 8.5 0 0121 12z" /><path d="M8.5 10.5h7" /><path d="M8.5 13.5h4" />',

    'handshake' => '<path d="m11 17 2 2a1 1 0 1 0 3-3" /><path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4" /><path d="m21 3 1 11h-2" /><path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3" /><path d="M3 4h8" />',

    'receipt' => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z" /><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8" /><path d="M12 17.5v-11" />',

    'file-text' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" /><path d="M14 2v4a2 2 0 0 0 2 2h4" /><path d="M10 9H8" /><path d="M16 13H8" /><path d="M16 17H8" />',

    'credit-card' => '<rect x="2" y="5" width="20" height="14" rx="2" /><path d="M2 10h20" />',
    'dots' => '<circle cx="5" cy="12" r="1.6" fill="currentColor" stroke="none" /><circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none" /><circle cx="19" cy="12" r="1.6" fill="currentColor" stroke="none" />',
    // ========================================
];
@endphp

<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" {{ $attributes->merge(['class' => $class]) }}>
    {!! $icons[$name] ?? '' !!}
</svg>
