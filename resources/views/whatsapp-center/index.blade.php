<x-app-layout>
<div x-data="whatsappCenter()" x-init="init()" x-cloak class="wa-page">
    <style>
        .wa-page {
            --wa-green: #00a884;
            --wa-green-deep: #008069;
            --wa-ink: #111b21;
            --wa-muted: #667781;
            --wa-line: #d9e2e7;
            --wa-sidebar: #f0f2f5;
            --wa-surface: #ffffff;
            --wa-hover: #f5f6f6;
            --wa-chat: #efeae2;
            --wa-out: #d9fdd3;
            height: calc(100dvh - 7rem);
            min-height: 480px;
            margin: -1.25rem -1rem -2rem;
            color: var(--wa-ink);
        }

        .dark .wa-page {
            --wa-ink: #e9edef;
            --wa-muted: #8696a0;
            --wa-line: #303d45;
            --wa-sidebar: #202c33;
            --wa-surface: #111b21;
            --wa-hover: #2a3942;
            --wa-chat: #0b141a;
            --wa-out: #005c4b;
        }

        .wa-frame {
            position: relative;
            display: grid;
            grid-template-columns: minmax(290px, 380px) minmax(0, 1fr);
            height: 100%;
            overflow: hidden;
            background: var(--wa-surface);
            border: 1px solid var(--wa-line);
            box-shadow: 0 8px 24px rgba(11, 20, 26, .08);
        }

        .wa-sidebar, .wa-chat {
            min-width: 0;
            min-height: 0;
        }

        .wa-sidebar {
            display: flex;
            flex-direction: column;
            background: var(--wa-surface);
            border-right: 1px solid var(--wa-line);
        }

        .wa-chat {
            position: relative;
            display: flex;
            flex-direction: column;
            background: var(--wa-chat);
            background-image: radial-gradient(rgba(17, 27, 33, .035) 1px, transparent 1px);
            background-size: 18px 18px;
        }

        .dark .wa-chat { background-image: radial-gradient(rgba(233, 237, 239, .025) 1px, transparent 1px); }
        .wa-sidebar-head, .wa-chat-head { background: var(--wa-sidebar); }
        .wa-chat-head { background: var(--wa-sidebar); border-bottom: 1px solid var(--wa-line); }
        .wa-sidebar-head { border-bottom: 1px solid var(--wa-line); }
        .wa-icon-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 999px;
            color: var(--wa-muted);
        }
        .wa-icon-button:hover { background: rgba(103, 119, 129, .12); color: var(--wa-ink); }
        .wa-icon-button:disabled { opacity: .45; cursor: not-allowed; }
        .wa-avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 48px;
            height: 48px;
            border-radius: 999px;
            background: #dfe5e7;
            color: #54656f;
            font-weight: 600;
            font-size: 1rem;
            text-transform: uppercase;
        }
        .dark .wa-avatar { background: #37474f; color: #d5dde0; }
        .wa-avatar.wa-avatar-small { width: 42px; height: 42px; font-size: .9rem; }
        .wa-avatar.wa-avatar-large { width: 92px; height: 92px; font-size: 2rem; }
        .wa-search-wrap { position: relative; }
        .wa-search-wrap svg { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--wa-muted); }
        .wa-search {
            width: 100%;
            height: 38px;
            padding: 0 14px 0 40px;
            border: 0 !important;
            border-radius: 9px;
            background: var(--wa-sidebar) !important;
            color: var(--wa-ink) !important;
            outline: none;
            font-size: .875rem;
        }
        .wa-search:focus { box-shadow: 0 0 0 2px rgba(0, 168, 132, .25) !important; }
        .wa-account-select {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            width: 100%;
            padding: .6rem .75rem;
            border-bottom: 1px solid var(--wa-line);
            color: var(--wa-ink);
            text-align: left;
        }
        .wa-account-select:hover { background: var(--wa-hover); }
        .wa-status-dot { width: 9px; height: 9px; border-radius: 999px; background: #aebac1; }
        .wa-status-dot.is-online { background: var(--wa-green); box-shadow: 0 0 0 3px rgba(0, 168, 132, .12); }
        .wa-status-dot.is-error { background: #f15c6d; }
        .wa-account-menu, .wa-context-menu {
            position: absolute;
            z-index: 40;
            min-width: 190px;
            padding: .35rem 0;
            overflow: hidden;
            border: 1px solid var(--wa-line);
            border-radius: 8px;
            background: var(--wa-surface);
            box-shadow: 0 8px 24px rgba(11, 20, 26, .16);
        }
        .wa-account-menu { left: .75rem; right: .75rem; top: 7.6rem; }
        .wa-context-menu { width: 190px; }
        .wa-menu-item {
            display: flex;
            align-items: center;
            gap: .7rem;
            width: 100%;
            min-height: 40px;
            padding: .55rem .85rem;
            color: var(--wa-ink);
            text-align: left;
            font-size: .8125rem;
        }
        .wa-menu-item:hover { background: var(--wa-hover); }
        .wa-menu-item:disabled { color: var(--wa-muted); opacity: .55; cursor: not-allowed; }
        .wa-filter {
            display: inline-flex;
            align-items: center;
            height: 30px;
            padding: 0 .7rem;
            border-radius: 999px;
            color: var(--wa-muted);
            font-size: .75rem;
            white-space: nowrap;
        }
        .wa-filter:hover { background: var(--wa-hover); }
        .wa-filter.is-active { color: var(--wa-green-deep); background: rgba(0, 168, 132, .12); font-weight: 600; }
        .dark .wa-filter.is-active { color: #62d9bd; }
        .wa-conversations { min-height: 0; overflow-y: auto; }
        .wa-conversation {
            display: flex;
            gap: .75rem;
            width: 100%;
            padding: .72rem .9rem;
            border-bottom: 1px solid var(--wa-line);
            color: var(--wa-ink);
            text-align: left;
        }
        .wa-conversation:hover, .wa-conversation.is-active { background: var(--wa-hover); }
        .wa-conversation.is-active { box-shadow: inset 3px 0 0 var(--wa-green); }
        .wa-conversation-main { min-width: 0; flex: 1; }
        .wa-conversation-title, .wa-conversation-preview { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .wa-conversation-title { font-size: .875rem; font-weight: 600; }
        .wa-conversation-preview { margin-top: .15rem; color: var(--wa-muted); font-size: .78rem; }
        .wa-unread {
            display: inline-flex;
            min-width: 19px;
            height: 19px;
            align-items: center;
            justify-content: center;
            padding: 0 .28rem;
            border-radius: 999px;
            background: var(--wa-green);
            color: white;
            font-size: .67rem;
            font-weight: 700;
        }
        .wa-chat-empty { display: flex; align-items: center; justify-content: center; height: 100%; color: var(--wa-muted); }
        .wa-chat-empty-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 74px;
            height: 74px;
            margin-bottom: 1rem;
            border-radius: 999px;
            background: rgba(0, 168, 132, .12);
            color: var(--wa-green);
        }
        .wa-chat-head { display: flex; align-items: center; gap: .75rem; min-height: 64px; padding: .65rem 1rem; }
        .wa-chat-head-copy { min-width: 0; flex: 1; }
        .wa-chat-head-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--wa-ink); font-size: .9rem; font-weight: 600; }
        .wa-chat-head-status { color: var(--wa-muted); font-size: .75rem; }
        .wa-messages { min-height: 0; flex: 1; overflow-y: auto; padding: 1.35rem clamp(.8rem, 4vw, 5rem); scroll-behavior: smooth; }
        .wa-date-separator { display: flex; justify-content: center; margin: .35rem 0 1rem; }
        .wa-date-separator span { padding: .34rem .75rem; border-radius: 7px; background: rgba(255, 255, 255, .78); color: var(--wa-muted); font-size: .7rem; box-shadow: 0 1px 1px rgba(11, 20, 26, .05); }
        .dark .wa-date-separator span { background: rgba(32, 44, 51, .9); }
        .wa-message-row { display: flex; margin: .22rem 0; }
        .wa-message-row.is-outbound { justify-content: flex-end; }
        .wa-bubble {
            position: relative;
            max-width: min(72%, 620px);
            padding: .42rem .62rem .3rem;
            border-radius: 8px;
            background: var(--wa-surface);
            color: var(--wa-ink);
            box-shadow: 0 1px 1px rgba(11, 20, 26, .08);
        }
        .wa-message-row:not(.is-outbound) .wa-bubble { border-top-left-radius: 2px; }
        .wa-message-row.is-outbound .wa-bubble { border-top-right-radius: 2px; background: var(--wa-out); }
        .wa-bubble.is-selected { outline: 2px solid var(--wa-green); outline-offset: 2px; }
        .wa-bubble-text { white-space: pre-wrap; overflow-wrap: anywhere; font-size: .875rem; line-height: 1.42; }
        .wa-bubble-meta { display: flex; align-items: center; justify-content: flex-end; gap: .25rem; margin-top: .18rem; color: var(--wa-muted); font-size: .65rem; }
        .wa-message-row.is-outbound .wa-bubble-meta { color: rgba(17, 27, 33, .55); }
        .dark .wa-message-row.is-outbound .wa-bubble-meta { color: rgba(233, 237, 239, .62); }
        .wa-read { color: #53bdeb; font-weight: 700; letter-spacing: -2px; }
        .wa-selection-bar, .wa-search-bar { display: flex; align-items: center; gap: .5rem; min-height: 50px; padding: .5rem 1rem; background: var(--wa-sidebar); border-bottom: 1px solid var(--wa-line); }
        .wa-search-bar input { flex: 1; height: 34px; border: 0 !important; background: transparent !important; color: var(--wa-ink) !important; outline: none; font-size: .85rem; }
        .wa-composer-wrap { position: relative; padding: .65rem 1rem .75rem; background: var(--wa-sidebar); border-top: 1px solid var(--wa-line); }
        .wa-composer { display: flex; align-items: flex-end; gap: .45rem; }
        .wa-composer-input { min-height: 42px; max-height: 120px; flex: 1; resize: none; padding: .68rem .9rem; border: 0 !important; border-radius: 22px; background: var(--wa-surface) !important; color: var(--wa-ink) !important; outline: none; font-size: .875rem; }
        .wa-composer-input:focus { box-shadow: 0 0 0 2px rgba(0, 168, 132, .22) !important; }
        .wa-send { display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 999px; background: var(--wa-green); color: white; }
        .wa-send:hover { background: var(--wa-green-deep); }
        .wa-send:disabled { opacity: .45; cursor: not-allowed; }
        .wa-popover { position: absolute; bottom: 4.2rem; left: 1rem; z-index: 30; width: min(340px, calc(100% - 2rem)); padding: .75rem; border: 1px solid var(--wa-line); border-radius: 10px; background: var(--wa-surface); box-shadow: 0 8px 24px rgba(11, 20, 26, .16); }
        .wa-emoji-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: .25rem; }
        .wa-emoji-grid button { height: 34px; border-radius: 6px; font-size: 1.2rem; }
        .wa-emoji-grid button:hover { background: var(--wa-hover); }
        .wa-info-panel { position: absolute; inset: 0 0 0 auto; z-index: 20; width: min(360px, 100%); overflow-y: auto; background: var(--wa-surface); box-shadow: -8px 0 24px rgba(11, 20, 26, .1); }
        .wa-info-head { display: flex; align-items: center; gap: .75rem; min-height: 64px; padding: .65rem 1rem; background: var(--wa-sidebar); border-bottom: 1px solid var(--wa-line); }
        .wa-info-section { padding: 1rem; border-bottom: 1px solid var(--wa-line); }
        .wa-info-label { color: var(--wa-muted); font-size: .7rem; }
        .wa-info-value { color: var(--wa-ink); font-size: .875rem; }
        .wa-panel { height: 100%; overflow-y: auto; background: var(--wa-surface); }
        .wa-panel-head { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.4rem; background: var(--wa-sidebar); border-bottom: 1px solid var(--wa-line); }
        .wa-panel-title { color: var(--wa-ink); font-size: 1rem; font-weight: 600; }
        .wa-panel-body { max-width: 760px; margin: 0 auto; padding: 1.25rem; }
        .wa-settings-section { margin-bottom: .75rem; overflow: hidden; border: 1px solid var(--wa-line); border-radius: 10px; background: var(--wa-surface); }
        .wa-settings-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .9rem 1rem; border-bottom: 1px solid var(--wa-line); color: var(--wa-ink); font-size: .875rem; }
        .wa-settings-row:last-child { border-bottom: 0; }
        .wa-toggle { position: relative; width: 38px; height: 22px; flex: 0 0 auto; border-radius: 999px; background: #aebac1; }
        .wa-toggle::after { position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 999px; background: white; content: ''; transition: transform .18s ease; }
        .wa-toggle.is-on { background: var(--wa-green); }
        .wa-toggle.is-on::after { transform: translateX(16px); }
        .wa-modal-backdrop { position: fixed; inset: 0; z-index: 80; display: flex; align-items: center; justify-content: center; padding: 1rem; background: rgba(11, 20, 26, .55); }
        .wa-modal { width: min(520px, 100%); max-height: min(720px, calc(100dvh - 2rem)); overflow-y: auto; border-radius: 12px; background: var(--wa-surface); box-shadow: 0 16px 48px rgba(11, 20, 26, .24); }
        .wa-modal-head { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.15rem; border-bottom: 1px solid var(--wa-line); }
        .wa-modal-body { padding: 1.15rem; }
        .wa-field { width: 100%; height: 42px; padding: 0 .75rem; border: 1px solid var(--wa-line) !important; border-radius: 8px; background: var(--wa-surface) !important; color: var(--wa-ink) !important; outline: none; font-size: .875rem; }
        .wa-field:focus { border-color: var(--wa-green) !important; box-shadow: 0 0 0 2px rgba(0, 168, 132, .16) !important; }
        .wa-textarea { height: 84px; padding-top: .65rem; resize: vertical; }
        .wa-primary { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; padding: 0 1rem; border-radius: 7px; background: var(--wa-green); color: white; font-size: .8125rem; font-weight: 600; }
        .wa-primary:hover { background: var(--wa-green-deep); }
        .wa-secondary { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; padding: 0 1rem; border: 1px solid var(--wa-line); border-radius: 7px; color: var(--wa-ink); font-size: .8125rem; font-weight: 600; }
        .wa-secondary:hover { background: var(--wa-hover); }
        .wa-primary:disabled, .wa-secondary:disabled { opacity: .5; cursor: not-allowed; }
        .wa-notice { padding: .65rem .75rem; border-radius: 7px; background: rgba(255, 193, 7, .13); color: #866b11; font-size: .75rem; }
        .dark .wa-notice { color: #e6ca6d; }
        .wa-skeleton { height: 62px; margin: .25rem .7rem; border-radius: 8px; background: linear-gradient(90deg, var(--wa-sidebar), var(--wa-hover), var(--wa-sidebar)); background-size: 200% 100%; animation: wa-shimmer 1.2s infinite; }
        @keyframes wa-shimmer { to { background-position: -200% 0; } }
        @media (prefers-reduced-motion: reduce) { .wa-skeleton { animation: none; } .wa-messages { scroll-behavior: auto; } }
        @media (min-width: 640px) { .wa-page { margin-left: -1.5rem; margin-right: -1.5rem; } }
        @media (min-width: 1024px) { .wa-page { margin-left: -2rem; margin-right: -2rem; } }
        @media (max-width: 767px) {
            .wa-page { height: calc(100dvh - 5.4rem); min-height: 0; margin: -1.25rem -1rem -2rem; }
            .wa-frame { display: block; border-right: 0; border-left: 0; }
            .wa-sidebar, .wa-chat { position: absolute; inset: 0; width: 100%; height: 100%; }
            .wa-chat { display: none; }
            .wa-frame.is-mobile-chat .wa-sidebar { display: none; }
            .wa-frame.is-mobile-chat .wa-chat { display: flex; }
            .wa-bubble { max-width: 88%; }
            .wa-messages { padding: 1rem .7rem; }
            .wa-chat-head { min-height: 58px; padding: .5rem .55rem; }
            .wa-composer-wrap { padding-left: .45rem; padding-right: .45rem; }
            .wa-composer .wa-icon-button { width: 36px; height: 36px; }
            .wa-composer-input { min-height: 38px; }
            .wa-send { width: 38px; height: 38px; }
        }
    </style>

    <div class="wa-frame" :class="mobileChat ? 'is-mobile-chat' : ''">
        <aside class="wa-sidebar">
            <div class="wa-sidebar-head flex items-center justify-between px-3 py-2.5">
                <div class="flex items-center gap-2.5 min-w-0">
                    @if(auth()->user()->avatar)
                        <img src="{{ asset('storage/' . auth()->user()->avatar) }}" alt="{{ auth()->user()->name }}" class="wa-avatar wa-avatar-small object-cover">
                    @else
                        <div class="wa-avatar wa-avatar-small">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
                    @endif
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold" style="color:var(--wa-ink)">{{ auth()->user()->name }}</p>
                        <p class="text-[11px]" style="color:var(--wa-muted)">WhatsApp Center</p>
                    </div>
                </div>
                <div class="flex items-center gap-0.5">
                    <button type="button" class="wa-icon-button" @click="view = 'status'; mobileChat = true" aria-label="Status">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.8" d="M4 12a8 8 0 0116 0M7 12a5 5 0 0110 0M10 12a2 2 0 014 0"/></svg>
                    </button>
                    <button type="button" class="wa-icon-button" @click="view = 'settings'; mobileChat = true" aria-label="{{ __('Pengaturan') }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.8" d="M10.3 3.3l.4-1.1h2.6l.4 1.1a2 2 0 002.5.9l1-.5 1.8 1.8-.5 1a2 2 0 00.9 2.5l1.1.4v2.6l-1.1.4a2 2 0 00-.9 2.5l.5 1-1.8 1.8-1-.5a2 2 0 00-2.5.9l-.4 1.1h-2.6l-.4-1.1a2 2 0 00-2.5-.9l-1 .5L5 15.9l.5-1a2 2 0 00-.9-2.5l-1.1-.4V9.4l1.1-.4a2 2 0 00.9-2.5L5 5.5l1.8-1.8 1 .5a2 2 0 002.5-.9z"/><circle cx="12" cy="10.7" r="2.5" stroke-width="1.8"/></svg>
                    </button>
                    <button type="button" class="wa-icon-button" @click="startNewChat()" aria-label="{{ __('Chat baru') }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.8" d="M12 5v14M5 12h14"/></svg>
                    </button>
                </div>
            </div>

            <div class="relative">
                <button type="button" class="wa-account-select" @click="accountMenu = !accountMenu" :aria-expanded="accountMenu">
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="wa-status-dot" :class="accountStatusClass(activeAccount)" :title="accountStatusTitle(activeAccount)"></span>
                        <span class="min-w-0">
                            <span class="block truncate text-xs font-semibold" style="color:var(--wa-ink)" x-text="activeAccount?.name || '{{ __('Pilih akun WhatsApp') }}'"></span>
                            <span class="block truncate text-[11px]" style="color:var(--wa-muted)" x-text="activeAccount?.phone_number || activeAccount?.account_code || ''"></span>
                        </span>
                    </span>
                    <svg class="h-4 w-4 shrink-0" style="color:var(--wa-muted)" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.8" d="M6 9l6 6 6-6"/></svg>
                </button>
                <div x-show="accountMenu" @click.away="accountMenu = false" class="wa-account-menu" x-transition>
                    <template x-for="account in accounts" :key="account.id">
                        <button type="button" class="wa-menu-item" @click="setAccount(account)">
                            <span class="wa-status-dot" :class="accountStatusClass(account)" :title="accountStatusTitle(account)"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate" x-text="account.name"></span>
                                <span class="block truncate text-[11px]" style="color:var(--wa-muted)" x-text="account.phone_number || account.account_code"></span>
                            </span>
                            <span x-show="account.id === activeAccount?.id" style="color:var(--wa-green)">✓</span>
                        </button>
                    </template>
                    <div x-show="accounts.length === 0" class="px-3 py-3 text-xs" style="color:var(--wa-muted)">{{ __('Tidak ada akun yang dapat diakses.') }}</div>
                </div>
            </div>

            <div class="px-3 py-2.5">
                <div class="wa-search-wrap">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.8" stroke-width="1.8"/><path stroke-linecap="round" stroke-width="1.8" d="M16 16l5 5"/></svg>
                    <input x-model="search" type="search" class="wa-search" placeholder="{{ __('Cari atau mulai chat') }}" aria-label="{{ __('Cari chat') }}">
                </div>
            </div>

            <div class="flex items-center gap-1 overflow-x-auto px-3 pb-2">
                <button type="button" class="wa-filter" :class="filter === 'all' ? 'is-active' : ''" @click="filter = 'all'">{{ __('Semua') }}</button>
                <button type="button" class="wa-filter" :class="filter === 'unread' ? 'is-active' : ''" @click="filter = 'unread'">{{ __('Belum dibaca') }}</button>
                <button type="button" class="wa-filter" :class="filter === 'pinned' ? 'is-active' : ''" @click="filter = 'pinned'">{{ __('Disematkan') }}</button>
                <button type="button" class="wa-filter" :class="filter === 'archived' ? 'is-active' : ''" @click="filter = 'archived'">{{ __('Diarsipkan') }}</button>
            </div>

            <div class="wa-conversations flex-1">
                <template x-if="loadingConversations">
                    <div><div class="wa-skeleton"></div><div class="wa-skeleton"></div><div class="wa-skeleton"></div><div class="wa-skeleton"></div></div>
                </template>
                <template x-if="!loadingConversations && filteredConversations.length === 0">
                    <div class="flex flex-col items-center justify-center px-8 py-16 text-center">
                        <svg class="mb-3 h-9 w-9" style="color:var(--wa-muted)" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.6" d="M20 11.5a7.5 7.5 0 01-8 7.45 8.5 8.5 0 01-3.3-.65L4 20l1.7-4.3A7.4 7.4 0 014.5 11.5 7.5 7.5 0 1120 11.5z"/></svg>
                        <p class="text-sm font-medium" style="color:var(--wa-ink)" x-text="search ? '{{ __('Chat tidak ditemukan') }}' : (filter === 'archived' ? '{{ __('Belum ada chat diarsipkan') }}' : '{{ __('Belum ada percakapan') }}')"></p>
                        <p class="mt-1 text-xs" style="color:var(--wa-muted)" x-text="search ? '{{ __('Coba nama atau nomor lain.') }}' : '{{ __('Mulai percakapan baru dari tombol +.') }}'"></p>
                    </div>
                </template>
                <template x-for="conv in filteredConversations" :key="conv.sender_number">
                    <div class="wa-conversation" :class="activeSender === conv.sender_number ? 'is-active' : ''" @contextmenu.prevent="openContextMenu($event, conv)">
                        <button type="button" class="flex min-w-0 flex-1 gap-3 text-left" @click="openConversation(conv)">
                            <span class="wa-avatar wa-avatar-small" x-text="initials(conversationName(conv))"></span>
                            <span class="wa-conversation-main">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="wa-conversation-title" x-text="conversationName(conv)"></span>
                                    <span class="shrink-0 text-[11px]" :class="conv.unread > 0 ? 'font-semibold' : ''" :style="conv.unread > 0 ? 'color:var(--wa-green)' : 'color:var(--wa-muted)'" x-text="timeLabel(conv.last_at)"></span>
                                </span>
                                <span class="mt-0.5 flex items-center justify-between gap-2">
                                    <span class="wa-conversation-preview" x-text="conv.last_message || conv.sender_number"></span>
                                    <span x-show="conv.unread > 0" class="wa-unread shrink-0" x-text="conv.unread > 99 ? '99+' : conv.unread"></span>
                                </span>
                                <span class="mt-1 flex items-center gap-1.5 text-[10px]" style="color:var(--wa-muted)">
                                    <span x-show="conv.mode === 'human'" class="font-semibold" style="color:var(--wa-green)">{{ __('Ditangani') }}</span>
                                    <span x-show="conv.mode !== 'human'" class="font-semibold" style="color:#0ea5e9">{{ __('Bot') }}</span>
                                    <span x-show="conv.is_pinned" title="{{ __('Disematkan') }}">📌</span>
                                    <span x-show="conv.is_muted" title="{{ __('Notifikasi dibisukan') }}">⌁</span>
                                    <span x-show="conv.lead_id" class="font-semibold" style="color:var(--wa-green)">Lead</span>
                                    <span x-show="!conv.lead_id && conv.customer" class="font-semibold" style="color:#54656f">{{ __('Tersimpan') }}</span>
                                    <span x-show="!conv.lead_id && !conv.customer" class="font-semibold" style="color:#a47719">{{ __('Nomor baru') }}</span>
                                </span>
                            </span>
                        </button>
                        <button type="button" class="wa-icon-button !h-8 !w-8 shrink-0 self-center" @click.stop="openContextMenu($event, conv)" aria-label="{{ __('Menu chat') }}">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/></svg>
                        </button>
                    </div>
                </template>
            </div>
        </aside>

        <section class="wa-chat">
            <template x-if="view === 'chats'">
                <div class="flex h-full min-h-0 flex-col">
                    <template x-if="!activeConv">
                        <div class="wa-chat-empty flex-1">
                            <div class="max-w-sm px-6 text-center">
                                <div class="wa-chat-empty-mark mx-auto">
                                    <svg class="h-9 w-9" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.6" d="M20 11.5a7.5 7.5 0 01-8 7.45 8.5 8.5 0 01-3.3-.65L4 20l1.7-4.3A7.4 7.4 0 014.5 11.5 7.5 7.5 0 1120 11.5z"/></svg>
                                </div>
                                <h2 class="text-lg font-medium" style="color:var(--wa-ink)">{{ __('WhatsApp untuk tim') }}</h2>
                                <p class="mt-2 text-sm leading-relaxed" style="color:var(--wa-muted)">{{ __('Pilih chat di sebelah kiri untuk membaca dan membalas pesan. Percakapan tetap terhubung ke akun WhatsApp yang dipilih.') }}</p>
                                <button type="button" class="wa-primary mt-5" @click="startNewChat()">{{ __('Mulai chat baru') }}</button>
                            </div>
                        </div>
                    </template>

                    <template x-if="activeConv">
                        <div class="flex h-full min-h-0 flex-col">
                            <div class="wa-chat-head">
                                <button type="button" class="wa-icon-button md:hidden" @click="mobileChat = false; activeConv = null" aria-label="{{ __('Kembali ke daftar chat') }}">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.8" d="M15 18l-6-6 6-6"/></svg>
                                </button>
                                <button type="button" class="flex min-w-0 flex-1 items-center gap-3 text-left" @click="contactPanel = true">
                                    <span class="wa-avatar wa-avatar-small" x-text="initials(conversationName(activeConv))"></span>
                                    <span class="wa-chat-head-copy">
                                        <span class="wa-chat-head-name block" x-text="conversationName(activeConv)"></span>
                                        <span class="wa-chat-head-status block" x-text="(activeConv.customer ? (activeConv.customer.whatsapp || activeConv.sender_number) : activeConv.sender_number) + ' · ' + (activeConv.mode === 'human' ? '{{ __('Ditangani') }}' : '{{ __('Bot aktif') }}')"></span>
                                    </span>
                                </button>
                                <div class="flex items-center gap-0.5">
                                    @can('manage-marketing')
                                        <button type="button" x-show="activeConv.mode !== 'human'" class="wa-secondary hidden !min-h-[32px] !px-2 !text-xs sm:inline-flex" @click="takeoverChat()">{{ __('Ambil alih') }}</button>
                                        <button type="button" x-show="activeConv.mode === 'human'" class="wa-secondary hidden !min-h-[32px] !px-2 !text-xs sm:inline-flex" @click="releaseChat()">{{ __('Kembalikan ke bot') }}</button>
                                    @endcan
                                    <button type="button" class="wa-icon-button" @click="messageSearchOpen = !messageSearchOpen; $nextTick(() => messageSearchOpen && $refs.messageSearch?.focus())" aria-label="{{ __('Cari dalam chat') }}">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.8" stroke-width="1.8"/><path stroke-linecap="round" stroke-width="1.8" d="M16 16l5 5"/></svg>
                                    </button>
                                    <button type="button" class="wa-icon-button" @click="contactPanel = true" aria-label="{{ __('Info kontak') }}">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="1.8"/><path stroke-linecap="round" stroke-width="1.8" d="M12 10.5v5M12 7.5h.01"/></svg>
                                    </button>
                                    <button type="button" class="wa-icon-button" @click="chatMenu = !chatMenu" aria-label="{{ __('Menu chat') }}">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/></svg>
                                    </button>
                                    @can('manage-marketing')
                                        <button x-show="!activeConv.lead_id" type="button" class="wa-primary hidden min-h-[32px] px-2 text-xs sm:inline-flex" @click="goToLeadForm()">Lead</button>
                                    @endcan
                                </div>
                            </div>

                            <div x-show="chatMenu" @click.away="chatMenu = false" class="wa-context-menu" style="right:12px;top:55px;left:auto" x-transition>
                                <button type="button" class="wa-menu-item" @click="contactPanel = true; chatMenu = false"><span>ⓘ</span><span>{{ __('Info kontak') }}</span></button>
                                <button type="button" class="wa-menu-item" @click="messageSearchOpen = true; chatMenu = false; $nextTick(() => $refs.messageSearch?.focus())"><span>⌕</span><span>{{ __('Cari') }}</span></button>
                                <button type="button" class="wa-menu-item" @click="togglePreference(activeConv, 'is_muted'); chatMenu = false"><span>⌁</span><span x-text="activeConv.is_muted ? '{{ __('Bunyikan notifikasi') }}' : '{{ __('Bisukan notifikasi') }}'"></span></button>
                                <button type="button" class="wa-menu-item" @click="togglePreference(activeConv, 'is_archived'); chatMenu = false"><span>⌄</span><span x-text="activeConv.is_archived ? '{{ __('Keluarkan dari arsip') }}' : '{{ __('Arsipkan chat') }}'"></span></button>
                                <button type="button" class="wa-menu-item" @click="markConversation(activeConv, activeConv.unread > 0); chatMenu = false"><span>✓</span><span x-text="activeConv.unread > 0 ? __('Tandai dibaca') : __('Tandai belum dibaca')"></span></button>
                                <button type="button" class="wa-menu-item" disabled title="{{ __('Clear messages belum didukung backend') }}">⌫ <span>{{ __('Bersihkan pesan') }}</span></button>
                                <button type="button" class="wa-menu-item" disabled title="{{ __('Export chat belum didukung backend') }}">⇩ <span>{{ __('Ekspor chat') }}</span></button>
                            </div>

                            <div x-show="messageSearchOpen" class="wa-search-bar" x-transition>
                                <svg class="h-4 w-4" style="color:var(--wa-muted)" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.8" stroke-width="1.8"/><path stroke-linecap="round" stroke-width="1.8" d="M16 16l5 5"/></svg>
                                <input x-ref="messageSearch" x-model="messageSearch" @keydown.enter.prevent="searchInChat()" type="search" placeholder="{{ __('Cari pesan dalam chat') }}">
                                <span class="whitespace-nowrap text-[11px]" style="color:var(--wa-muted)" x-text="messageSearchResultText"></span>
                                <button type="button" class="wa-icon-button !h-8 !w-8" @click="clearMessageSearch()" aria-label="{{ __('Tutup pencarian') }}">×</button>
                            </div>

                            <div x-show="selectedMessages.length" class="wa-selection-bar" x-transition>
                                <button type="button" class="wa-icon-button !h-8 !w-8" @click="selectedMessages = []" aria-label="{{ __('Batalkan pilihan') }}">×</button>
                                <span class="flex-1 text-sm" style="color:var(--wa-ink)" x-text="selectedMessages.length + ' pesan dipilih'"></span>
                                <button type="button" class="wa-icon-button !h-8 !w-8" @click="copySelectedMessages()" aria-label="{{ __('Salin pesan terpilih') }}">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect width="11" height="13" x="4" y="3" rx="1.5" stroke-width="1.6"/><path stroke-width="1.6" d="M8 7h9a2 2 0 012 2v10a2 2 0 01-2 2H10a2 2 0 01-2-2V7z"/></svg>
                                </button>
                            </div>

                            <div x-ref="messageArea" class="wa-messages" @scroll="handleMessageScroll($event)">
                                <div x-show="loadingOlder" class="py-2 text-center text-xs" style="color:var(--wa-muted)">{{ __('Memuat pesan sebelumnya...') }}</div>
                                <button x-show="hasMore && !loadingMessages && !messageSearchActive" type="button" class="mx-auto mb-3 block rounded-full px-3 py-1 text-xs" style="background:var(--wa-surface);color:var(--wa-muted)" @click="loadOlder()">{{ __('Muat pesan sebelumnya') }}</button>
                                <template x-if="loadingMessages && messages.length === 0">
                                    <div class="space-y-2 py-4"><div class="mx-auto h-8 w-32 rounded-lg" style="background:var(--wa-surface)"></div><div class="ml-auto h-14 w-48 rounded-lg" style="background:var(--wa-out)"></div><div class="h-12 w-44 rounded-lg" style="background:var(--wa-surface)"></div></div>
                                </template>
                                <template x-if="!loadingMessages && displayMessages.length === 0">
                                    <div class="flex h-full min-h-[260px] items-center justify-center text-center"><div><p class="text-sm font-medium" style="color:var(--wa-ink)" x-text="messageSearchActive ? '{{ __('Pesan tidak ditemukan') }}' : '{{ __('Belum ada pesan') }}' "></p><p class="mt-1 text-xs" style="color:var(--wa-muted)" x-text="messageSearchActive ? '{{ __('Coba kata kunci lain.') }}' : '{{ __('Kirim pesan pertama untuk memulai percakapan.') }}'"></p></div></div>
                                </template>
                                <template x-for="(msg, index) in displayMessages" :key="msg.id">
                                    <div>
                                        <template x-if="showDateSeparator(index)"><div class="wa-date-separator"><span x-text="dateSeparator(msg)"></span></div></template>
                                        <div class="wa-message-row" :class="msg.direction === 'outbound' ? 'is-outbound' : ''" :data-message-id="msg.id">
                                            <button type="button" class="wa-bubble text-left" :class="selectedMessages.includes(msg.id) ? 'is-selected' : ''" @click="selectedMessages.length ? toggleMessageSelection(msg) : null" @contextmenu.prevent="openMessageMenu($event, msg)">
                                                <p class="wa-bubble-text" x-text="msg.message_body"></p>
                                                <span class="wa-bubble-meta">
                                                    <template x-if="msg.is_bot"><span class="font-semibold" style="color:var(--wa-green)">{{ __('Bot') }}</span></template>
                                                    <span x-text="messageTime(msg)"></span>
                                                    <template x-if="msg.direction === 'outbound'"><span :class="msg.status === 'read' ? 'wa-read' : ''" x-text="statusGlyph(msg.status)" :title="statusLabel(msg.status)"></span></template>
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div class="wa-composer-wrap">
                                <div x-show="replyTo" class="mb-2 flex items-center gap-2 rounded-lg px-3 py-2 text-xs" style="background:var(--wa-surface);border-left:3px solid var(--wa-green)" x-transition>
                                    <span class="min-w-0 flex-1"><span class="block font-semibold" style="color:var(--wa-green)" x-text="replyTo?.direction === 'outbound' ? '{{ __('Pesan Anda') }}' : conversationName(activeConv)"></span><span class="block truncate" style="color:var(--wa-muted)" x-text="replyTo?.message_body"></span></span>
                                    <button type="button" class="wa-icon-button !h-7 !w-7" @click="replyTo = null" aria-label="{{ __('Batal membalas') }}">×</button>
                                </div>
                                <div x-show="attachmentName" class="mb-2 flex items-center gap-2 rounded-lg px-3 py-2 text-xs" style="background:var(--wa-notice)" x-transition>
                                    <span class="min-w-0 flex-1 truncate" x-text="attachmentName"></span>
                                    <button type="button" class="font-semibold" @click="clearAttachment()">{{ __('Hapus') }}</button>
                                </div>
                                <div x-show="emojiOpen" class="wa-popover" x-transition>
                                    <div class="mb-2 flex items-center justify-between"><span class="text-xs font-semibold" style="color:var(--wa-ink)">Emoji</span><button type="button" class="text-xs" style="color:var(--wa-muted)" @click="emojiOpen = false">{{ __('Tutup') }}</button></div>
                                    <div class="wa-emoji-grid"><template x-for="emoji in emojis" :key="emoji"><button type="button" @click="reply += emoji"> <span x-text="emoji"></span></button></template></div>
                                </div>
                                <div x-show="attachmentPanel" class="wa-popover" x-transition>
                                    <p class="mb-2 text-xs font-semibold" style="color:var(--wa-ink)">{{ __('Lampiran') }}</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="button" class="wa-secondary" @click="openFilePicker('image/*,video/*')">{{ __('Foto / Video') }}</button>
                                        <button type="button" class="wa-secondary" @click="openFilePicker('.pdf,.doc,.docx,.xls,.xlsx,.txt')">{{ __('Dokumen') }}</button>
                                        <button type="button" class="wa-secondary" disabled>Audio</button>
                                        <button type="button" class="wa-secondary" disabled>{{ __('Kontak') }}</button>
                                    </div>
                                    <p class="mt-2 text-[11px] leading-relaxed" style="color:var(--wa-muted)">{{ __('Gateway aktif saat ini hanya menyediakan pesan teks. Pilihan file disiapkan di UI, tetapi tidak akan dikirim sebelum endpoint media tersedia.') }}</p>
                                </div>
                                <input x-ref="fileInput" type="file" class="hidden" @change="handleAttachment($event)">
                                <div class="wa-composer">
                                    <button type="button" class="wa-icon-button" @click="emojiOpen = !emojiOpen; attachmentPanel = false" aria-label="Emoji"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5" stroke-width="1.7"/><path stroke-linecap="round" stroke-width="1.7" d="M8.5 14.2a4.4 4.4 0 007 0M9 9.5h.01M15 9.5h.01"/></svg></button>
                                    <button type="button" class="wa-icon-button" @click="attachmentPanel = !attachmentPanel; emojiOpen = false" aria-label="{{ __('Lampiran') }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.8" d="M8.5 12.5l5.2-5.2a3 3 0 114.2 4.2l-6.8 6.8a4.5 4.5 0 11-6.4-6.4l7.1-7.1"/></svg></button>
                                    <textarea x-model="reply" class="wa-composer-input" rows="1" placeholder="{{ __('Ketik pesan') }}" @keydown="handleComposerKeydown($event)" @input="resizeComposer($event)"></textarea>
                                    <button type="button" class="wa-icon-button" disabled aria-label="{{ __('Pesan suara') }}" title="{{ __('Pesan suara belum didukung gateway') }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="9" y="3" width="6" height="11" rx="3" stroke-width="1.7"/><path stroke-linecap="round" stroke-width="1.7" d="M6.5 11a5.5 5.5 0 0011 0M12 16.5V21M9.5 21h5"/></svg></button>
                                    <button type="button" class="wa-send" @click="sendReply()" :disabled="!canSend" aria-label="{{ __('Kirim pesan') }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4l16 8-16 8 3-8-3-8zm3 8h13"/></svg></button>
                                </div>
                                <p x-show="!canManage" class="mt-1 text-center text-[11px]" style="color:var(--wa-muted)">{{ __('Mode baca. Anda tidak memiliki izin untuk mengirim pesan.') }}</p>
                                <p x-show="attachmentName" class="mt-1 text-center text-[11px]" style="color:#866b11">{{ __('Lampiran belum dikirim karena gateway saat ini hanya mendukung teks.') }}</p>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="view === 'status'"><div class="wa-panel"><div class="wa-panel-head"><div><h2 class="wa-panel-title">Status</h2><p class="mt-1 text-xs" style="color:var(--wa-muted)">{{ __('Pembaruan status dari kontak WhatsApp.') }}</p></div><button type="button" class="wa-icon-button" @click="view = 'chats'; mobileChat = false" aria-label="{{ __('Tutup status') }}">×</button></div><div class="wa-panel-body"><div class="wa-settings-section"><div class="flex items-center gap-3 p-4"><span class="wa-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><span class="min-w-0 flex-1"><span class="block text-sm font-semibold" style="color:var(--wa-ink)">{{ __('Status saya') }}</span><span class="block text-xs" style="color:var(--wa-muted)">{{ __('Tambahkan pembaruan status') }}</span></span><button type="button" class="wa-icon-button" disabled title="{{ __('Status belum didukung gateway') }}">＋</button></div></div><div class="wa-settings-section p-6 text-center"><svg class="mx-auto mb-3 h-9 w-9" style="color:var(--wa-muted)" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="1.6"/><path stroke-linecap="round" stroke-width="1.6" d="M12 8v4l2.5 2.5"/></svg><p class="text-sm font-medium" style="color:var(--wa-ink)">{{ __('Status belum tersedia') }}</p><p class="mx-auto mt-1 max-w-sm text-xs leading-relaxed" style="color:var(--wa-muted)">{{ __('Gateway WhatsApp yang terhubung saat ini tidak menyediakan API status/story. UI ini tidak menampilkan data contoh.') }}</p></div></div></div></template>

            <template x-if="view === 'settings'"><div class="wa-panel"><div class="wa-panel-head"><div><h2 class="wa-panel-title">{{ __('Pengaturan') }}</h2><p class="mt-1 text-xs" style="color:var(--wa-muted)">{{ __('Preferensi pengalaman WhatsApp Center di perangkat ini.') }}</p></div><button type="button" class="wa-icon-button" @click="view = 'chats'; mobileChat = false" aria-label="{{ __('Tutup pengaturan') }}">×</button></div><div class="wa-panel-body"><div class="mb-4 flex items-center gap-3 rounded-xl p-4" style="background:var(--wa-sidebar)"><span class="wa-avatar wa-avatar-large">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><div><p class="text-lg font-semibold" style="color:var(--wa-ink)">{{ auth()->user()->name }}</p><p class="text-sm" style="color:var(--wa-muted)">{{ auth()->user()->email }}</p><a href="{{ route('profile.edit') }}" class="mt-2 inline-block text-xs font-semibold" style="color:var(--wa-green-deep)">{{ __('Edit profil akun') }}</a></div></div><div class="wa-settings-section"><div class="wa-settings-row"><span><span class="block font-semibold">Chat</span><span class="mt-1 block text-xs" style="color:var(--wa-muted)">{{ __('Pengaturan percakapan') }}</span></span><span style="color:var(--wa-muted)">›</span></div><button type="button" class="wa-settings-row w-full text-left" @click="settings.enterToSend = !settings.enterToSend; persistSettings()"><span><span class="block">{{ __('Enter untuk mengirim') }}</span><span class="mt-1 block text-xs" style="color:var(--wa-muted)">{{ __('Shift + Enter tetap membuat baris baru') }}</span></span><span class="wa-toggle" :class="settings.enterToSend ? 'is-on' : ''"></span></button><button type="button" class="wa-settings-row w-full text-left" @click="settings.mediaVisibility = !settings.mediaVisibility; persistSettings()"><span><span class="block">{{ __('Tampilkan media') }}</span><span class="mt-1 block text-xs" style="color:var(--wa-muted)">{{ __('Preferensi lokal browser') }}</span></span><span class="wa-toggle" :class="settings.mediaVisibility ? 'is-on' : ''"></span></button></div><div class="wa-settings-section"><div class="wa-settings-row"><span><span class="block font-semibold">{{ __('Notifikasi') }}</span><span class="mt-1 block text-xs" style="color:var(--wa-muted)">{{ __('Notifikasi browser untuk pesan baru') }}</span></span><button type="button" class="wa-secondary !min-h-[32px] !px-3 !text-xs" @click="requestNotifications()" x-text="notificationPermission === 'granted' ? '{{ __('Aktif') }}' : '{{ __('Izinkan') }}'"></button></div><button type="button" class="wa-settings-row w-full text-left" @click="settings.notificationSound = !settings.notificationSound; persistSettings()"><span><span class="block">{{ __('Suara notifikasi') }}</span><span class="mt-1 block text-xs" style="color:var(--wa-muted)">{{ __('Mainkan suara saat ada notifikasi baru') }}</span></span><span class="wa-toggle" :class="settings.notificationSound ? 'is-on' : ''"></span></button></div><div class="wa-settings-section"><div class="wa-settings-row"><span><span class="block font-semibold">{{ __('Privasi') }}</span><span class="mt-1 block text-xs" style="color:var(--wa-muted)">{{ __('Pengaturan ini mengikuti akun WhatsApp provider') }}</span></span><span class="text-xs" style="color:var(--wa-muted)">Provider</span></div><div class="wa-settings-row"><span>Read receipts</span><span class="text-xs" style="color:var(--wa-muted)">{{ __('Dikelola gateway') }}</span></div><div class="wa-settings-row"><span>{{ __('Kontak diblokir') }}</span><span class="text-xs" style="color:var(--wa-muted)">{{ __('Belum tersedia') }}</span></div></div><div class="wa-settings-section"><div class="wa-settings-row"><span><span class="block font-semibold">{{ __('Akun WhatsApp') }}</span><span class="mt-1 block text-xs" style="color:var(--wa-muted)">{{ __('Pilih nomor aktif dari sidebar untuk mengganti akun.') }}</span></span><span class="text-xs" style="color:var(--wa-green)" x-text="accounts.length + ' akun'"></span></div></div>@include('whatsapp-center._bot_settings')@if(auth()->user()->hasRole('super-admin') || auth()->user()->hasPermissionTo('manage-sales-leads'))<div class="wa-settings-section"><div class="p-4"><p class="text-sm font-semibold" style="color:var(--wa-ink)">{{ __('Kredensial gateway') }}</p><p class="mt-1 text-xs leading-relaxed" style="color:var(--wa-muted)">{{ __('Pengaturan teknis tetap memakai endpoint yang sama dan hanya terlihat oleh pengelola.') }}</p></div>@foreach($accounts as $account)<form method="POST" action="{{ route('whatsapp-center.credentials', $account) }}" class="border-t p-4" style="border-color:var(--wa-line)">@csrf @method('PUT')<div class="mb-2 flex items-center justify-between"><span class="text-xs font-semibold" style="color:var(--wa-ink)">{{ $account->name }} @if($account->gateway_type === 'meta')<span class="ml-1 rounded px-1.5 py-0.5 text-[10px] font-bold" style="background:#e7f6f0;color:#008069">META</span>@endif</span><span class="text-[11px]" style="color:var(--wa-muted)">{{ $account->gateway_status ?: __('belum dikonfigurasi') }}</span></div><label class="mb-2 flex items-center gap-2 text-xs" style="color:var(--wa-ink)"><input type="checkbox" name="bot_enabled" value="1" @checked($account->bot_enabled) class="rounded border-slate-300"> {{ __('Bot balas otomatis') }}</label><div class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]"><input name="gateway_instance" value="{{ $account->gateway_instance }}" placeholder="{{ $account->gateway_type === 'meta' ? 'Phone Number ID (Meta)' : 'IdInstance' }}" class="wa-field"><input type="password" name="gateway_token" value="" placeholder="{{ $account->gateway_type === 'meta' ? 'System User Access Token (Meta) — kosongkan bila tidak diubah' : 'ApiTokenInstance — kosongkan bila tidak diubah' }}" autocomplete="new-password" class="wa-field"><button type="submit" class="wa-primary">{{ __('Simpan') }}</button></div>@if($account->gateway_type === 'meta')<p class="mt-2 text-[11px] leading-relaxed" style="color:var(--wa-muted)">Webhook Meta: <code>{{ url('/api/whatsapp/webhook') }}</code> · Verify Token: {{ config('whatsapp.meta.verify_token') ? __('sudah diset') : __('BELUM diset di .env (META_WA_VERIFY_TOKEN)') }} · Isi dengan Phone Number ID + System User Access Token, bukan App ID.</p>@endif</form>@endforeach</div>@endif<div class="mt-5 flex items-center gap-2 text-xs" style="color:var(--wa-muted)"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="1.6"/><path stroke-linecap="round" stroke-width="1.6" d="M9.5 9a2.5 2.5 0 015 0c0 1.7-2.5 2-2.5 3.5M12 16h.01"/></svg><span>{{ __('Fitur media, status, reactions, dan panggilan memerlukan dukungan gateway tambahan.') }}</span></div></div></div></template>

            <div x-show="contactPanel && activeConv" class="wa-info-panel" x-transition>
                <div class="wa-info-head"><button type="button" class="wa-icon-button !h-8 !w-8" @click="contactPanel = false" aria-label="{{ __('Tutup info kontak') }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.8" d="M15 18l-6-6 6-6"/></svg></button><span class="text-sm font-semibold" style="color:var(--wa-ink)">{{ __('Info kontak') }}</span></div><div class="flex flex-col items-center px-5 py-7 text-center"><span class="wa-avatar wa-avatar-large" x-text="initials(conversationName(activeConv))"></span><h3 class="mt-3 text-lg font-semibold" style="color:var(--wa-ink)" x-text="conversationName(activeConv)"></h3><p class="mt-1 text-sm" style="color:var(--wa-muted)" x-text="activeConv?.sender_number"></p><div class="mt-4 flex gap-2"><button x-show="canManage" type="button" class="wa-secondary !min-h-[34px] !px-3 !text-xs" @click="openContactModal()" x-text="activeConv?.customer ? '{{ __('Edit kontak') }}' : '{{ __('Simpan kontak') }}'"></button><a x-show="activeConv?.customer" :href="activeConv.customer?.url || '{{ route('customers.index') }}'" class="wa-secondary !min-h-[34px] !px-3 !text-xs">{{ __('Buka customer') }}</a></div></div><div class="wa-info-section"><p class="wa-info-label">{{ __('Nomor WhatsApp') }}</p><p class="wa-info-value mt-1" x-text="activeConv?.sender_number"></p></div><div class="wa-info-section"><p class="wa-info-label">{{ __('Tentang') }}</p><p class="wa-info-value mt-1 whitespace-pre-wrap" x-text="activeConv?.customer?.notes || '{{ __('Belum ada catatan kontak.') }}'"></p></div><div class="wa-info-section"><p class="wa-info-label">Chat</p><div class="mt-3 grid grid-cols-2 gap-2"><button type="button" class="wa-secondary !min-h-[34px] !px-2 !text-xs" @click="togglePreference(activeConv, 'is_muted')" x-text="activeConv?.is_muted ? '{{ __('Bunyikan') }}' : '{{ __('Bisukan') }}'"></button><button type="button" class="wa-secondary !min-h-[34px] !px-2 !text-xs" @click="togglePreference(activeConv, 'is_archived')" x-text="activeConv?.is_archived ? '{{ __('Keluarkan arsip') }}' : '{{ __('Arsipkan') }}'"></button></div></div><div class="wa-info-section"><p class="wa-info-label">{{ __('Media, link, dan dokumen') }}</p><p class="mt-1 text-xs leading-relaxed" style="color:var(--wa-muted)">{{ __('Belum dapat ditampilkan karena gateway hanya mengirim pesan teks.') }}</p></div></div>
        </section>
    </div>

    <div x-show="toast.message" class="fixed bottom-5 right-5 z-[100] max-w-xs rounded-lg px-4 py-3 text-sm shadow-lg" :class="toast.type === 'error' ? 'bg-red-600 text-white' : 'text-white'" :style="toast.type === 'error' ? '' : 'background:var(--wa-green-deep)'" x-transition role="status">
        <span x-text="toast.message"></span>
    </div>

    <div x-show="contextMenu.open" @click.away="contextMenu.open = false" class="wa-context-menu" :style="`left:${contextMenu.x}px;top:${contextMenu.y}px`" x-transition>
        <button type="button" class="wa-menu-item" @click="togglePreference(contextMenu.conversation, 'is_pinned'); contextMenu.open = false"><span>📌</span><span x-text="contextMenu.conversation?.is_pinned ? '{{ __('Lepas sematan') }}' : '{{ __('Sematkan chat') }}'"></span></button>
        <button type="button" class="wa-menu-item" @click="togglePreference(contextMenu.conversation, 'is_muted'); contextMenu.open = false"><span>⌁</span><span x-text="contextMenu.conversation?.is_muted ? '{{ __('Bunyikan notifikasi') }}' : '{{ __('Bisukan notifikasi') }}'"></span></button>
        <button type="button" class="wa-menu-item" @click="togglePreference(contextMenu.conversation, 'is_archived'); contextMenu.open = false"><span>⌄</span><span x-text="contextMenu.conversation?.is_archived ? '{{ __('Keluarkan dari arsip') }}' : '{{ __('Arsipkan chat') }}'"></span></button>
        <button type="button" class="wa-menu-item" @click="markConversation(contextMenu.conversation, contextMenu.conversation?.unread > 0); contextMenu.open = false"><span>✓</span><span x-text="contextMenu.conversation?.unread > 0 ? __('Tandai dibaca') : __('Tandai belum dibaca')"></span></button>
        <button type="button" class="wa-menu-item" @click="openContactPanelFor(contextMenu.conversation); contextMenu.open = false"><span>ⓘ</span><span>{{ __('Info kontak') }}</span></button>
    </div>

    <div x-show="messageMenu.open" @click.away="messageMenu.open = false" class="wa-context-menu" :style="`left:${messageMenu.x}px;top:${messageMenu.y}px`" x-transition>
        <button type="button" class="wa-menu-item" @click="copyMessage(messageMenu.message); messageMenu.open = false"><span>⧉</span><span>{{ __('Salin') }}</span></button>
        <button type="button" class="wa-menu-item" @click="toggleMessageSelection(messageMenu.message); messageMenu.open = false"><span>✓</span><span>{{ __('Pilih') }}</span></button>
        <button type="button" class="wa-menu-item" disabled title="{{ __('Reply terstruktur belum didukung gateway') }}"><span>↩</span><span>{{ __('Balas') }}</span></button>
        <button type="button" class="wa-menu-item" disabled title="{{ __('Forward belum didukung gateway') }}"><span>↗</span><span>{{ __('Teruskan') }}</span></button>
        <button type="button" class="wa-menu-item" disabled title="{{ __('Reaksi belum didukung gateway') }}"><span>☺</span><span>{{ __('Reaksi') }}</span></button>
        <button type="button" class="wa-menu-item" disabled title="{{ __('Penghapusan pesan belum didukung gateway') }}"><span>⌫</span><span>{{ __('Hapus') }}</span></button>
    </div>

    <div x-show="newChatOpen" class="wa-modal-backdrop" @keydown.escape.window="newChatOpen = false" x-transition>
        <div class="wa-modal" @click.stop>
            <div class="wa-modal-head"><div><h2 class="text-base font-semibold" style="color:var(--wa-ink)">{{ __('Chat baru') }}</h2><p class="mt-1 text-xs" style="color:var(--wa-muted)">{{ __('Pilih customer tersimpan atau masukkan nomor WhatsApp.') }}</p></div><button type="button" class="wa-icon-button !h-8 !w-8" @click="newChatOpen = false" aria-label="{{ __('Tutup') }}">×</button></div>
            <div class="wa-modal-body"><div class="wa-search-wrap"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.8" stroke-width="1.8"/><path stroke-linecap="round" stroke-width="1.8" d="M16 16l5 5"/></svg><input x-model="contactSearch" @input.debounce.300ms="loadContacts()" type="search" class="wa-field !pl-10" placeholder="{{ __('Cari nama atau nomor') }}"></div><div class="my-3 flex items-center gap-2"><input x-model="directNumber" type="text" class="wa-field" placeholder="628xxxxxxxxxx"><button type="button" class="wa-primary shrink-0" @click="startNumberChat()" :disabled="!directNumber.trim()">Chat</button></div><div class="mb-2 text-[11px] font-semibold uppercase tracking-wide" style="color:var(--wa-muted)">{{ __('Kontak tersimpan') }}</div><div class="max-h-64 overflow-y-auto rounded-lg" style="border:1px solid var(--wa-line)"><template x-if="loadingContacts"><div class="p-4 text-center text-xs" style="color:var(--wa-muted)">{{ __('Mencari kontak...') }}</div></template><template x-for="contact in contacts" :key="contact.id"><button type="button" class="flex w-full items-center gap-3 border-b px-3 py-2.5 text-left last:border-b-0" style="border-color:var(--wa-line)" @click="chooseContact(contact)"><span class="wa-avatar wa-avatar-small" x-text="initials(contact.name)"></span><span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold" style="color:var(--wa-ink)" x-text="contact.name"></span><span class="block truncate text-xs" style="color:var(--wa-muted)" x-text="contact.whatsapp"></span></span><span style="color:var(--wa-muted)">›</span></button></template><template x-if="!loadingContacts && contacts.length === 0"><p class="p-5 text-center text-xs" style="color:var(--wa-muted)">{{ __('Kontak dengan nomor WhatsApp tidak ditemukan.') }}</p></template></div></div>
        </div>
    </div>

    <div x-show="contactModal" class="wa-modal-backdrop" @keydown.escape.window="contactModal = false" x-transition>
        <div class="wa-modal" @click.stop><div class="wa-modal-head"><div><h2 class="text-base font-semibold" style="color:var(--wa-ink)" x-text="contactForm.customer_id ? '{{ __('Edit kontak') }}' : '{{ __('Simpan nomor') }}'"></h2><p class="mt-1 text-xs" style="color:var(--wa-muted)">{{ __('Data disimpan ke Customer CRM yang sudah ada.') }}</p></div><button type="button" class="wa-icon-button !h-8 !w-8" @click="contactModal = false" aria-label="{{ __('Tutup') }}">×</button></div><form class="wa-modal-body space-y-3" @submit.prevent="saveContact()"><label class="block"><span class="mb-1 block text-xs font-semibold" style="color:var(--wa-ink)">{{ __('Nama kontak') }}</span><input x-model="contactForm.name" required class="wa-field" maxlength="255" placeholder="{{ __('Nama orang / PIC') }}"></label><label class="block"><span class="mb-1 block text-xs font-semibold" style="color:var(--wa-ink)">{{ __('Nama Perusahaan (opsional)') }}</span><input x-model="contactForm.company" class="wa-field" maxlength="255" placeholder="{{ __('Nama perusahaan / PT') }}"></label><label class="block"><span class="mb-1 block text-xs font-semibold" style="color:var(--wa-ink)">{{ __('Nomor WhatsApp') }}</span><input x-model="contactForm.whatsapp" required class="wa-field" placeholder="628xxxxxxxxxx" maxlength="50"></label><label class="block"><span class="mb-1 block text-xs font-semibold" style="color:var(--wa-ink)">{{ __('Alamat (opsional)') }}</span><textarea x-model="contactForm.address" class="wa-field wa-textarea" maxlength="1000" placeholder="{{ __('Alamat perusahaan') }}"></textarea></label><label class="block"><span class="mb-1 block text-xs font-semibold" style="color:var(--wa-ink)">{{ __('Catatan') }}</span><textarea x-model="contactForm.notes" class="wa-field wa-textarea" maxlength="2000" placeholder="{{ __('Catatan internal') }}"></textarea></label><p x-show="contactError" class="text-xs text-red-600" x-text="contactError"></p><div class="flex justify-end gap-2 pt-2"><button type="button" class="wa-secondary" @click="contactModal = false">{{ __('Batal') }}</button><button type="submit" class="wa-primary" :disabled="savingContact" x-text="savingContact ? '{{ __('Menyimpan...') }}' : '{{ __('Simpan kontak') }}'"></button></div></form></div>
    </div>

</div>

<script>
    function whatsappCenter() {
        return {
            accounts: @js($accountTabs),
            canManage: @js(auth()->user()->can('manage-marketing')),
            activeAccount: null,
            activeConv: null,
            activeSender: null,
            conversations: [],
            messages: [],
            search: '',
            filter: 'all',
            view: 'chats',
            mobileChat: false,
            accountMenu: false,
            chatMenu: false,
            contactPanel: false,
            loadingConversations: false,
            loadingMessages: false,
            loadingOlder: false,
            hasMore: false,
            nextBefore: null,
            pollTimer: null,
            reply: '',
            replyTo: null,
            emojiOpen: false,
            attachmentPanel: false,
            attachmentName: '',
            messageSearchOpen: false,
            messageSearch: '',
            messageSearchActive: false,
            messageSearchCount: 0,
            selectedMessages: [],
            contextMenu: { open: false, x: 0, y: 0, conversation: null },
            messageMenu: { open: false, x: 0, y: 0, message: null },
            newChatOpen: false,
            contactSearch: '',
            contacts: [],
            loadingContacts: false,
            chatBlocked: false,
            directNumber: '',
            contactModal: false,
            contactForm: { customer_id: null, name: '', company: '', whatsapp: '', address: '', notes: '' },
            savingContact: false,
            contactError: '',
            settings: { enterToSend: true, mediaVisibility: true, notificationSound: true },
            notificationPermission: 'default',
            toast: { message: '', type: 'notice' },
            toastTimer: null,
            emojis: ['😀', '😂', '😊', '😍', '🤝', '👍', '👏', '🙏', '✅', '🎉', '🤔', '😅', '❤️', '🔥', '👋', '💡', '📌', '🚀', '😄', '😉', '😎', '😢', '😮', '🎯'],

            init() {
                try {
                    const saved = JSON.parse(localStorage.getItem('whatsapp-center-settings') || '{}');
                    this.settings = { ...this.settings, ...saved };
                } catch (e) {}
                this.notificationPermission = 'Notification' in window ? Notification.permission : 'unsupported';
                this.activeAccount = this.accounts[0] || null;
                if (!this.activeAccount) return;
                this.loadConversations();
                this.refreshStatus();
                this.accounts.forEach((account) => this.checkAccountConnection(account));
                this.pollTimer = setInterval(() => {
                    this.refreshStatus();
                    this.loadConversations(true);
                    if (this.activeConv && this.view === 'chats') this.loadMessages(this.activeConv.sender_number, true);
                }, 5000);
                // ponytail: simpan ref handler agar bisa dilepas saat destroy;
                // tanpa ini listener bocor tiap navigasi tanpa refresh.
                this._escHandler = (event) => {
                    if (event.key === 'Escape') {
                        this.contextMenu.open = false;
                        this.messageMenu.open = false;
                        this.chatMenu = false;
                        this.accountMenu = false;
                    }
                };
                window.addEventListener('keydown', this._escHandler);
            },

            // Dipanggil otomatis Alpine saat komponen dihapus dari DOM
            // (mis. navigasi wire:navigate) — hentikan polling.
            destroy() {
                if (this.pollTimer) clearInterval(this.pollTimer);
                if (this.toastTimer) clearTimeout(this.toastTimer);
                if (this._escHandler) window.removeEventListener('keydown', this._escHandler);
                this.pollTimer = null;
            },

            get filteredConversations() {
                const query = this.search.trim().toLowerCase();
                return this.conversations.filter((conv) => {
                    const matchesSearch = !query || this.conversationName(conv).toLowerCase().includes(query) || conv.sender_number.includes(query) || (conv.last_message || '').toLowerCase().includes(query);
                    const matchesFilter = this.filter === 'unread' ? conv.unread > 0 : this.filter === 'pinned' ? conv.is_pinned : this.filter === 'archived' ? conv.is_archived : !conv.is_archived;
                    return matchesSearch && matchesFilter;
                });
            },

            get displayMessages() {
                return this.messageSearchActive
                    ? this.messages.filter((message) => (message.message_body || '').toLowerCase().includes(this.messageSearch.trim().toLowerCase()))
                    : this.messages;
            },

            get messageSearchResultText() {
                return this.messageSearchActive ? `${this.messageSearchCount} {{ __('hasil') }}` : '';
            },

            get canSend() {
                return this.canManage && !!this.activeConv && !this.chatBlocked && !!this.reply.trim() && !this.attachmentName && !this.loadingMessages;
            },

            setAccount(account) {
                this.activeAccount = account;
                this.accountMenu = false;
                this.activeConv = null;
                this.activeSender = null;
                this.chatBlocked = false;
                this.messages = [];
                this.view = 'chats';
                this.mobileChat = false;
                this.loadConversations();
                this.refreshStatus().finally(() => this.checkAccountConnection(account));
            },

            accountStatusClass(account) {
                if (account?.gateway_status === 'authorized') return 'is-online';
                if (account?.gateway_status) return 'is-error';
                return '';
            },

            conversationName(conv) {
                const cust = conv?.customer;
                if (cust?.contact_person && cust?.company && cust.contact_person !== cust.company) return `${cust.contact_person}-${cust.company}`;
                return cust?.name || conv?.sender_name || conv?.sender_number || '{{ __('Kontak WhatsApp') }}';
            },

            initials(value) {
                const words = (value || '?').trim().split(/\s+/).filter(Boolean);
                return (words.length > 1 ? words[0][0] + words[words.length - 1][0] : words[0]?.[0] || '?').toUpperCase();
            },

            async refreshStatus() {
                try {
                    const response = await fetch('{{ route('whatsapp-center.status') }}', { headers: { Accept: 'application/json' } });
                    if (!response.ok) return;
                    const data = await response.json();
                    data.forEach((status) => {
                        const account = this.accounts.find((item) => item.id === status.id);
                        if (!account) return;
                        if (status.has_credentials !== undefined) account.has_credentials = status.has_credentials;
                        if (!account._live) account.gateway_status = status.gateway_status;
                    });
                } catch (error) {}
            },

            // Cek koneksi live SATU kali per akun Meta (bukan polling):
            // route check-status memukul Graph API, dibatasi manual agar hemat rate limit.
            async checkAccountConnection(account) {
                if (!account || account.gateway_type !== 'meta' || !account.has_credentials || account._live) return;
                try {
                    const response = await fetch('{{ route('whatsapp-center.check-status', ['account' => ':id']) }}'.replace(':id', account.id), { headers: { Accept: 'application/json' } });
                    if (!response.ok) return;
                    const data = await response.json();
                    account.gateway_status = data.gateway_status;
                    account.connection_detail = data.detail;
                    account._live = true;
                } catch (error) {}
            },

            accountStatusTitle(account) {
                if (!account?.has_credentials) return '{{ __('Belum dikonfigurasi — isi kredensial gateway') }}';
                if (account.gateway_status === 'authorized') return '{{ __('Terhubung ke Meta') }}';
                if (account.connection_detail) return account.connection_detail;
                if (account.gateway_status) return account.gateway_status;
                return '{{ __('Mengecek koneksi...') }}';
            },

            async loadConversations(silent = false) {
                if (!this.activeAccount) return;
                if (!silent) this.loadingConversations = true;
                try {
                    const response = await fetch('{{ route('whatsapp-center.conversations', ['account' => ':id']) }}'.replace(':id', this.activeAccount.id), { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error('{{ __('Gagal memuat percakapan') }}');
                    const data = await response.json();
                    this.conversations = data;
                    if (this.activeConv) {
                        const updated = data.find((conv) => conv.sender_number === this.activeConv.sender_number);
                        if (updated) this.activeConv = { ...this.activeConv, ...updated };
                    }
                } catch (error) {
                    if (!silent) this.showError('{{ __('Percakapan tidak dapat dimuat.') }}');
                } finally {
                    this.loadingConversations = false;
                }
            },

            async loadMessages(sender, silent = false, params = {}) {
                if (!this.activeAccount || !sender) return;
                if (!silent) this.loadingMessages = true;
                const query = new URLSearchParams({ limit: '50', ...params }).toString();
                try {
                    const url = '{{ route('whatsapp-center.messages', ['account' => ':id', 'sender' => ':sender']) }}'.replace(':id', this.activeAccount.id).replace(':sender', encodeURIComponent(sender)) + '?' + query;
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    if (response.status === 404) {
                        const blockedError = new Error('{{ __('Chat tidak tersedia di akun ini (milik company lain).') }}');
                        blockedError.blocked = true;
                        throw blockedError;
                    }
                    if (!response.ok) throw new Error('{{ __('Gagal memuat pesan') }}');
                    const data = await response.json();
                    const previousLastId = this.messages.length ? this.messages[this.messages.length - 1].id : null;
                    const shouldScroll = !silent || this.isNearBottom();
                    const incomingNew = silent && previousLastId ? data.messages.filter((message) => message.id > previousLastId && message.direction === 'inbound') : [];
                    this.messages = data.messages.map((message) => ({ ...message, created_iso: message.created_iso || new Date().toISOString() }));
                    this.chatBlocked = false;
                    this.hasMore = data.has_more;
                    this.nextBefore = data.next_before;
                    if (this.activeConv && data.customer) this.activeConv.customer = data.customer;
                    if (shouldScroll) this.$nextTick(() => this.scrollToBottom(true));
                    if (incomingNew.length) this.notifyIncoming(incomingNew[incomingNew.length - 1]);
                    if (!silent) this.markConversation(this.activeConv, true);
                } catch (error) {
                    if (!silent) {
                        if (error.blocked) {
                            this.chatBlocked = true;
                            this.activeConv = null;
                            this.activeSender = null;
                            this.messages = [];
                            this.mobileChat = false;
                        }
                        this.showError(error.message || '{{ __('Pesan tidak dapat dimuat.') }}');
                    }
                } finally {
                    this.loadingMessages = false;
                }
            },

            async loadOlder() {
                if (!this.activeConv || !this.hasMore || this.loadingOlder || this.messageSearchActive) return;
                this.loadingOlder = true;
                const area = this.$refs.messageArea;
                const oldHeight = area?.scrollHeight || 0;
                try {
                    const url = '{{ route('whatsapp-center.messages', ['account' => ':id', 'sender' => ':sender']) }}'.replace(':id', this.activeAccount.id).replace(':sender', encodeURIComponent(this.activeConv.sender_number)) + '?' + new URLSearchParams({ limit: '50', before: this.nextBefore }).toString();
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    const data = await response.json();
                    this.messages = [...data.messages, ...this.messages];
                    this.hasMore = data.has_more;
                    this.nextBefore = data.next_before;
                    this.$nextTick(() => { if (area) area.scrollTop = area.scrollHeight - oldHeight; });
                } catch (error) {
                    this.showError('{{ __('Pesan sebelumnya tidak dapat dimuat.') }}');
                } finally {
                    this.loadingOlder = false;
                }
            },

            openConversation(conv) {
                this.view = 'chats';
                this.mobileChat = true;
                this.activeConv = conv;
                this.activeSender = conv.sender_number;
                this.chatBlocked = false;
                this.contactPanel = false;
                this.chatMenu = false;
                this.clearMessageSearch(false);
                this.loadMessages(conv.sender_number);
            },

            async sendReply() {
                if (!this.canSend) return;
                const body = this.reply.trim();
                try {
                    const response = await fetch('{{ route('whatsapp-center.reply', ['account' => ':id', 'sender' => ':sender']) }}'.replace(':id', this.activeAccount.id).replace(':sender', encodeURIComponent(this.activeConv.sender_number)), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify({ message_body: body }),
                    });
                    if (response.status === 404 || response.status === 403) throw new Error('{{ __('Chat milik company lain, tidak bisa dikirim dari akun ini.') }}');
                    if (!response.ok) throw new Error('{{ __('Gagal mengirim pesan') }}');
                    const message = await response.json();
                    message.created_iso = new Date().toISOString();
                    this.messages.push(message);
                    this.reply = '';
                    this.replyTo = null;
                    this.$nextTick(() => { this.resizeComposer({ target: document.querySelector('.wa-composer-input') }); this.scrollToBottom(true); });
                    this.loadConversations(true);
                } catch (error) {
                    this.showError(error.message || '{{ __('Pesan gagal dikirim. Coba lagi.') }}');
                }
            },

            handleComposerKeydown(event) {
                if (event.key === 'Enter' && !event.shiftKey && !event.isComposing && this.settings.enterToSend) {
                    event.preventDefault();
                    this.sendReply();
                }
            },

            resizeComposer(event) {
                if (!event?.target) return;
                event.target.style.height = 'auto';
                event.target.style.height = Math.min(event.target.scrollHeight, 120) + 'px';
            },

            isNearBottom() {
                const area = this.$refs.messageArea;
                return !area || area.scrollHeight - area.scrollTop - area.clientHeight < 120;
            },

            scrollToBottom(force = false) {
                const area = this.$refs.messageArea;
                if (area && (force || this.isNearBottom())) area.scrollTop = area.scrollHeight;
            },

            handleMessageScroll(event) {
                if (event.target.scrollTop < 60 && this.hasMore && !this.messageSearchActive) this.loadOlder();
            },

            timeLabel(iso) {
                if (!iso) return '';
                const date = new Date(iso);
                const now = new Date();
                if (date.toDateString() === now.toDateString()) return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
            },

            messageTime(message) { return this.timeLabel(message.created_iso) || message.created_at || ''; },

            showDateSeparator(index) {
                if (index === 0) return true;
                const current = new Date(this.displayMessages[index].created_iso);
                const previous = new Date(this.displayMessages[index - 1].created_iso);
                return current.toDateString() !== previous.toDateString();
            },

            dateSeparator(message) {
                const date = new Date(message.created_iso);
                const now = new Date();
                const yesterday = new Date();
                yesterday.setDate(now.getDate() - 1);
                if (date.toDateString() === now.toDateString()) return '{{ __('Hari ini') }}';
                if (date.toDateString() === yesterday.toDateString()) return '{{ __('Kemarin') }}';
                return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
            },

            statusGlyph(status) {
                return { queued: '◷', sending: '◷', sent: '✓', delivered: '✓✓', read: '✓✓', failed: '!' }[status] || '◷';
            },

            statusLabel(status) {
                return { queued: '{{ __('Menunggu pengiriman') }}', sending: '{{ __('Mengirim') }}', sent: '{{ __('Terkirim ke gateway') }}', delivered: '{{ __('Terkirim ke perangkat') }}', read: '{{ __('Dibaca') }}', failed: '{{ __('Gagal dikirim') }}' }[status] || '{{ __('Status belum tersedia') }}';
            },

            async markConversation(conv, read) {
                if (!conv || !this.activeAccount) return;
                try {
                    await fetch('{{ route('whatsapp-center.mark-read', ['account' => ':id', 'sender' => ':sender']) }}'.replace(':id', this.activeAccount.id).replace(':sender', encodeURIComponent(conv.sender_number)), { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ read }) });
                    conv.unread = read ? 0 : Math.max(conv.unread || 0, 1);
                    if (this.activeConv?.sender_number === conv.sender_number) this.activeConv.unread = conv.unread;
                    this.refreshStatus();
                } catch (error) {}
            },

            async togglePreference(conv, key) {
                if (!conv || !this.activeAccount) return;
                const value = !conv[key];
                try {
                    const response = await fetch('{{ route('whatsapp-center.preference', ['account' => ':id', 'sender' => ':sender']) }}'.replace(':id', this.activeAccount.id).replace(':sender', encodeURIComponent(conv.sender_number)), { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ [key]: value }) });
                    if (!response.ok) throw new Error('{{ __('Gagal menyimpan preferensi') }}');
                    conv[key] = value;
                    if (this.activeConv?.sender_number === conv.sender_number) this.activeConv[key] = value;
                    if (key === 'is_archived' && value && this.filter !== 'archived') this.activeConv = null;
                    this.conversations = [...this.conversations].sort((a, b) => (b.is_pinned - a.is_pinned) || new Date(b.last_at) - new Date(a.last_at));
                } catch (error) { this.showError('{{ __('Preferensi chat tidak dapat disimpan.') }}'); }
            },

            openContextMenu(event, conv) {
                this.contextMenu = { open: true, x: Math.min(event.clientX, window.innerWidth - 205), y: Math.min(event.clientY, window.innerHeight - 250), conversation: conv };
                this.messageMenu.open = false;
            },

            openContactPanelFor(conv) {
                this.activeConv = conv;
                this.activeSender = conv.sender_number;
                this.view = 'chats';
                this.contactPanel = true;
            },

            openMessageMenu(event, message) {
                this.messageMenu = { open: true, x: Math.min(event.clientX, window.innerWidth - 205), y: Math.min(event.clientY, window.innerHeight - 250), message };
                this.contextMenu.open = false;
            },

            toggleMessageSelection(message) {
                this.selectedMessages = this.selectedMessages.includes(message.id) ? this.selectedMessages.filter((id) => id !== message.id) : [...this.selectedMessages, message.id];
            },

            async copyMessage(message) {
                if (!message) return;
                try { await navigator.clipboard.writeText(message.message_body); this.showNotice('{{ __('Pesan disalin.') }}'); } catch (error) { this.showError('{{ __('Pesan tidak dapat disalin.') }}'); }
            },

            async copySelectedMessages() {
                const selected = this.messages.filter((message) => this.selectedMessages.includes(message.id)).map((message) => message.message_body).join('\n');
                if (selected) await this.copyMessage({ message_body: selected });
                this.selectedMessages = [];
            },

            async searchInChat() {
                if (!this.activeConv || !this.messageSearch.trim()) return;
                this.messageSearchActive = true;
                try {
                    const url = '{{ route('whatsapp-center.messages', ['account' => ':id', 'sender' => ':sender']) }}'.replace(':id', this.activeAccount.id).replace(':sender', encodeURIComponent(this.activeConv.sender_number)) + '?' + new URLSearchParams({ limit: '100', q: this.messageSearch.trim() }).toString();
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    const data = await response.json();
                    this.messages = data.messages;
                    this.messageSearchCount = data.messages.length;
                    this.hasMore = false;
                } catch (error) { this.showError('{{ __('Pencarian pesan gagal.') }}'); }
            },

            clearMessageSearch(reload = true) {
                this.messageSearch = '';
                this.messageSearchActive = false;
                this.messageSearchCount = 0;
                this.messageSearchOpen = false;
                if (reload && this.activeConv) this.loadMessages(this.activeConv.sender_number);
            },

            startNewChat() {
                this.view = 'chats';
                this.newChatOpen = true;
                this.contactSearch = '';
                this.directNumber = '';
                this.loadContacts();
            },

            async loadContacts() {
                if (!this.activeAccount) return;
                this.loadingContacts = true;
                try {
                    const url = '{{ route('whatsapp-center.contacts', ['account' => ':id']) }}'.replace(':id', this.activeAccount.id) + '?' + new URLSearchParams({ q: this.contactSearch }).toString();
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    this.contacts = await response.json();
                } catch (error) { this.contacts = []; } finally { this.loadingContacts = false; }
            },

            chooseContact(contact) {
                if (!contact.whatsapp) return;
                const existing = this.conversations.find((conv) => conv.sender_number === contact.whatsapp);
                this.newChatOpen = false;
                this.openConversation(existing || { sender_number: contact.whatsapp, sender_name: contact.name, customer: contact, last_message: '', unread: 0, is_pinned: false, is_muted: false, is_archived: false });
            },

            startNumberChat() {
                const number = this.directNumber.replace(/\D/g, '');
                if (!number) return;
                const existing = this.conversations.find((conv) => conv.sender_number === number);
                this.newChatOpen = false;
                this.openConversation(existing || { sender_number: number, sender_name: number, last_message: '', unread: 0, is_pinned: false, is_muted: false, is_archived: false });
            },

            openContactModal() {
                const customer = this.activeConv?.customer;
                this.contactForm = { customer_id: customer?.id || null, name: customer?.contact_person || customer?.name || this.activeConv?.sender_name || '', company: customer?.company || '', whatsapp: customer?.whatsapp || this.activeConv?.sender_number || '', address: customer?.address || '', notes: customer?.notes || '' };
                this.contactError = '';
                this.contactModal = true;
            },

            async saveContact() {
                if (!this.activeAccount) return;
                this.savingContact = true;
                this.contactError = '';
                try {
                    const response = await fetch('{{ route('whatsapp-center.contact-save', ['account' => ':id']) }}'.replace(':id', this.activeAccount.id), { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(this.contactForm) });
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || '{{ __('Data kontak tidak valid.') }}');
                    this.activeConv.customer = data;
                    this.activeConv.sender_name = data.contact_person || data.name;
                    this.contactModal = false;
                    this.loadConversations(true);
                } catch (error) { this.contactError = error.message; } finally { this.savingContact = false; }
            },

            goToLeadForm() {
                if (!this.activeConv || !this.activeAccount) return;
                const customer = this.activeConv.customer;
                const params = new URLSearchParams({
                    whatsapp_account_id: this.activeAccount.id,
                    sender: this.activeConv.sender_number,
                    company: customer?.company || '',
                    pic: customer?.contact_person || this.activeConv.sender_name || '',
                });
                window.location.href = '{{ route('leads.create') }}?' + params.toString();
            },

            async takeoverChat() {
                if (!this.activeConv || !this.activeAccount) return;
                try {
                    const response = await fetch('{{ route('whatsapp-center.takeover', ['account' => ':id', 'sender' => ':sender']) }}'.replace(':id', this.activeAccount.id).replace(':sender', encodeURIComponent(this.activeConv.sender_number)), { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.message || '{{ __('Gagal mengambil alih percakapan.') }}');
                    this.activeConv = { ...this.activeConv, mode: data.mode, handled_by: data.handled_by };
                    this.loadConversations(true);
                } catch (error) { this.showError(error.message); }
            },

            async releaseChat() {
                if (!this.activeConv || !this.activeAccount) return;
                try {
                    const response = await fetch('{{ route('whatsapp-center.release', ['account' => ':id', 'sender' => ':sender']) }}'.replace(':id', this.activeAccount.id).replace(':sender', encodeURIComponent(this.activeConv.sender_number)), { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.message || '{{ __('Gagal mengembalikan ke bot.') }}');
                    this.activeConv = { ...this.activeConv, mode: data.mode, handled_by: data.handled_by, bot_turns: data.bot_turns };
                    this.loadConversations(true);
                } catch (error) { this.showError(error.message); }
            },

            openFilePicker(accept) { this.attachmentPanel = false; this.$refs.fileInput.accept = accept; this.$refs.fileInput.click(); },
            handleAttachment(event) { this.attachmentName = event.target.files?.[0]?.name || ''; },
            clearAttachment() { this.attachmentName = ''; if (this.$refs.fileInput) this.$refs.fileInput.value = ''; },

            persistSettings() { localStorage.setItem('whatsapp-center-settings', JSON.stringify(this.settings)); },

            requestNotifications() {
                if (!('Notification' in window)) { this.showError('{{ __('Browser ini tidak mendukung notifikasi.') }}'); return; }
                Notification.requestPermission().then((permission) => { this.notificationPermission = permission; });
            },

            notifyIncoming(message) {
                if (document.visibilityState === 'visible' || this.notificationPermission !== 'granted' || this.activeConv?.sender_number === message.sender_number) return;
                new Notification('{{ __('Pesan WhatsApp baru') }}', { body: message.message_body, tag: `wa-${message.id}` });
                if (this.settings.notificationSound) new Audio('/sounds/wa-notification.wav').play().catch(() => {});
            },

            showNotice(message) {
                this.toast = { message, type: 'notice' };
                clearTimeout(this.toastTimer);
                this.toastTimer = setTimeout(() => { this.toast.message = ''; }, 3000);
            },

            showError(message) {
                this.toast = { message, type: 'error' };
                clearTimeout(this.toastTimer);
                this.toastTimer = setTimeout(() => { this.toast.message = ''; }, 4500);
            },
        };
    }
</script>
</x-app-layout>
