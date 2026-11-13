/**
 * Multi-Tab Workspace (Keep-Alive) — Shell luar.
 *
 * Setiap tab memuat halaman di dalam `<iframe>` same-origin dengan layout
 * embed (tanpa sidebar/header). Karena iframe hanya disembunyikan saat pindah
 * tab, input form, modal, dan posisi scroll tidak hilang.
 */

const STORAGE_KEY_PREFIX = 'workspace-tabs:v1:';

const GROUP_SHORT_MAP = {
    management: 'Mgmt',
    teknisi: 'Eng',
    marketing: 'Mkt',
    sales: 'Sales',
    estimator: 'Est',
    admin: 'Admin',
    'admin-panel': 'Panel',
};

const GROUP_ICON_MAP = {
    management: 'briefcase',
    teknisi: 'tools',
    marketing: 'megaphone',
    sales: 'calendar',
    estimator: 'tools',
    admin: 'receipt',
    'admin-panel': 'settings',
};

function normalizePath(rawUrl) {
    try {
        const u = new URL(rawUrl, window.location.origin);
        return u.pathname.replace(/\/+$/, '') || '/';
    } catch (e) {
        return '/';
    }
}

function cleanUrl(rawUrl) {
    try {
        const u = new URL(rawUrl, window.location.origin);
        u.searchParams.delete('embed');
        return u.pathname + (u.search || '') + (u.hash || '');
    } catch (e) {
        return rawUrl || '/dashboard';
    }
}

function embedUrl(rawUrl) {
    try {
        const u = new URL(rawUrl, window.location.origin);
        u.searchParams.set('embed', '1');
        return u.pathname + '?' + u.searchParams.toString() + (u.hash || '');
    } catch (e) {
        return rawUrl;
    }
}

function tabIdFromPath(path) {
    const p = (path || '/').replace(/\/+$/, '') || '/';
    if (p === '/' || p === '/dashboard') return 'dashboard';
    const seg = p.split('/').filter(Boolean);
    if (seg.length === 0) return 'dashboard';
    // Gabungkan 2 segmen pertama supaya list (/sales/proposals) dan detail
    // (/sales/proposals/12) berada di tab modul yang sama saat diklik dari
    // dalam tab, dan klik ulang dari sidebar mengaktifkan tab itu.
    return seg.slice(0, 2).join('/');
}

function metaFromSidebarLink(link) {
    const labelEl = link.querySelector('span:not([aria-hidden])') || link;
    const rawLabel = (labelEl.textContent || '').replace(/\s+/g, ' ').trim();
    const group = link.closest('.sb-group');
    const groupKey = group?.getAttribute('data-sb-group') || '';
    const dept = GROUP_SHORT_MAP[groupKey] || null;
    const isGroupHead = Boolean(link.closest('.sb-head'));
    const title = dept && !isGroupHead && rawLabel ? `${dept} • ${rawLabel}` : rawLabel || 'Page';
    const icon = GROUP_ICON_MAP[groupKey] || 'grid';
    return { title, icon };
}

export function registerWorkspaceStore(Alpine) {
    if (!Alpine || Alpine.store('workspace')) return;

    const cfg = window.__workspaceConfig || {};
    const userKey = STORAGE_KEY_PREFIX + String(cfg.userId || 'guest');

    Alpine.store('workspace', {
        enabled: Boolean(cfg.enabled),
        tabs: [],
        activeId: 'dashboard',
        maxDesktop: Number(cfg.maxDesktop || 8),
        maxMobile: Number(cfg.maxMobile || 6),
        i18n: cfg.i18n || {},
        initialized: false,

        maxTabs() {
            return window.innerWidth < 1024 ? this.maxMobile : this.maxDesktop;
        },

        init() {
            if (this.initialized || !this.enabled) return;
            if (!document.getElementById('workspace-stage')) return;
            this.initialized = true;

            const initial = cfg.initialPage || {};
            const dashUrl = cfg.dashboardUrl || '/dashboard';
            const dashTab = {
                id: 'dashboard',
                title: this.i18n.dashboard || 'Dashboard',
                url: dashUrl,
                path: normalizePath(dashUrl),
                icon: 'grid',
                pinned: true,
                dirty: false,
                loaded: false,
            };

            let restored = [];
            try {
                const raw = sessionStorage.getItem(userKey);
                const parsed = raw ? JSON.parse(raw) : null;
                if (Array.isArray(parsed?.tabs)) {
                    restored = parsed.tabs
                        .filter((t) => t && typeof t.id === 'string' && typeof t.url === 'string' && t.id !== 'dashboard')
                        .slice(0, this.maxDesktop - 1)
                        .map((t) => ({
                            id: t.id,
                            title: String(t.title || 'Page'),
                            url: cleanUrl(t.url),
                            path: normalizePath(t.url),
                            icon: String(t.icon || 'grid'),
                            pinned: false,
                            dirty: false,
                            loaded: false,
                        }));
                }
            } catch (e) {
                restored = [];
            }

            this.tabs = [dashTab, ...restored];

            const currentUrl = cleanUrl(initial.url || window.location.pathname + window.location.search);
            const currentPath = normalizePath(currentUrl);
            const currentId = tabIdFromPath(currentPath);

            if (currentId === 'dashboard') {
                dashTab.url = currentUrl;
                dashTab.path = currentPath;
                this.activate('dashboard', { pushState: false });
            } else {
                const existing = this.tabs.find((t) => t.id === currentId || t.path === currentPath);
                if (existing) {
                    existing.url = currentUrl;
                    existing.path = currentPath;
                    if (initial.tabTitle) existing.title = initial.tabTitle;
                    this.activate(existing.id, { pushState: false });
                } else {
                    if (this.tabs.length >= this.maxTabs()) {
                        this.tabs.splice(1, 1);
                    }
                    const tab = {
                        id: currentId,
                        title: initial.tabTitle || initial.sectionLabel || 'Page',
                        url: currentUrl,
                        path: currentPath,
                        icon: initial.sectionIcon || 'grid',
                        pinned: false,
                        dirty: false,
                        loaded: false,
                    };
                    this.tabs.push(tab);
                    this.activate(tab.id, { pushState: false });
                }
            }

            this.bindSidebarInterceptor();
            this.bindPopState();
            this.bindEmbedMessages();
            this.bindAppearanceBroadcast();
        },

        persist() {
            try {
                const slim = this.tabs.map((t) => ({
                    id: t.id,
                    title: t.title,
                    url: t.url,
                    icon: t.icon,
                    pinned: Boolean(t.pinned),
                }));
                sessionStorage.setItem(userKey, JSON.stringify({ activeId: this.activeId, tabs: slim }));
            } catch (e) {}
        },

        ensureFrame(tab) {
            const stage = document.getElementById('workspace-stage');
            if (!stage || !tab) return null;
            let wrap = stage.querySelector(`[data-workspace-pane="${CSS.escape(tab.id)}"]`);
            if (!wrap) {
                wrap = document.createElement('div');
                wrap.setAttribute('data-workspace-pane', tab.id);
                wrap.className = 'workspace-pane hidden h-full w-full flex-1';
                const frame = document.createElement('iframe');
                frame.setAttribute('data-workspace-frame', tab.id);
                frame.setAttribute('title', tab.title || 'Workspace tab');
                frame.className = 'w-full h-full border-0 block';
                frame.src = embedUrl(tab.url);
                wrap.appendChild(frame);
                stage.appendChild(wrap);
                tab.loaded = true;
            }
            return wrap;
        },

        showActivePane() {
            const stage = document.getElementById('workspace-stage');
            if (!stage) return;
            const active = this.tabs.find((t) => t.id === this.activeId) || this.tabs[0];
            if (!active) return;
            this.ensureFrame(active);
            stage.querySelectorAll('[data-workspace-pane]').forEach((pane) => {
                const on = pane.getAttribute('data-workspace-pane') === active.id;
                pane.classList.toggle('hidden', !on);
            });
        },

        activate(id, options = {}) {
            const tab = this.tabs.find((t) => t.id === id);
            if (!tab) return;
            this.activeId = tab.id;
            this.showActivePane();

            if (options.pushState !== false) {
                try {
                    const target = cleanUrl(tab.url);
                    if (window.location.pathname + window.location.search !== target) {
                        window.history.pushState({ workspaceTabId: tab.id }, '', target);
                    }
                } catch (e) {}
            }

            document.title = `${tab.title} — ${cfg.appName || 'Tridaya App'}`;
            this.syncSidebar(tab.path || normalizePath(tab.url));
            this.persist();

            requestAnimationFrame(() => {
                const el = document.querySelector(`[data-workspace-tab="${CSS.escape(tab.id)}"]`);
                el?.scrollIntoView?.({ block: 'nearest', inline: 'nearest' });
            });
        },

        openByUrl(rawUrl, meta = {}) {
            const url = cleanUrl(rawUrl);
            const path = normalizePath(url);
            const id = tabIdFromPath(path);

            const existing = this.tabs.find((t) => t.id === id || t.path === path);
            if (existing) {
                // Jika user mengklik menu indeks dari sidebar tetapi tab itu
                // sedang berada di sub-halaman lain, tetap aktifkan tabnya
                // tanpa mereset isi (sesuai aturan keep-alive).
                this.activate(existing.id);
                return existing;
            }

            if (this.tabs.length >= this.maxTabs()) {
                const msg =
                    this.i18n.limitReached ||
                    `Maximum ${this.maxTabs()} tabs open. Please close an unused tab first.`;
                if (typeof window.pushToast === 'function') {
                    window.pushToast({ variant: 'warning', message: msg });
                } else if (typeof window.toast === 'function') {
                    window.toast(msg, false);
                }
                return null;
            }

            const tab = {
                id,
                title: meta.title || 'Page',
                url,
                path,
                icon: meta.icon || 'grid',
                pinned: false,
                dirty: false,
                loaded: false,
            };
            this.tabs.push(tab);
            this.activate(tab.id);
            return tab;
        },

        async close(id) {
            const idx = this.tabs.findIndex((t) => t.id === id);
            if (idx === -1) return;
            const tab = this.tabs[idx];
            if (tab.pinned) return;

            if (tab.dirty && typeof window.askConfirm === 'function') {
                const ok = await window.askConfirm({
                    title: this.i18n.unsavedTitle || 'Close this tab?',
                    message:
                        this.i18n.unsavedMessage ||
                        'This tab has unsaved input. Closing it will discard your changes.',
                    tone: 'warning',
                    confirmLabel: this.i18n.closeAnyway || 'Close tab',
                });
                if (!ok) return;
            }

            this.destroyFrame(tab.id);
            const wasActive = this.activeId === tab.id;
            this.tabs.splice(idx, 1);

            if (wasActive) {
                const next = this.tabs[Math.max(0, idx - 1)] || this.tabs[0];
                if (next) this.activate(next.id);
            } else {
                this.persist();
            }
        },

        async closeOthers() {
            const keep = new Set(['dashboard', this.activeId]);
            const dirtyTargets = this.tabs.filter((t) => !keep.has(t.id) && t.dirty);
            if (dirtyTargets.length > 0 && typeof window.askConfirm === 'function') {
                const ok = await window.askConfirm({
                    title: this.i18n.unsavedTitle || 'Close other tabs?',
                    message:
                        this.i18n.unsavedMessage ||
                        'Some tabs have unsaved input. Closing them will discard your changes.',
                    tone: 'warning',
                    confirmLabel: this.i18n.closeAnyway || 'Close tabs',
                });
                if (!ok) return;
            }
            this.tabs.forEach((t) => {
                if (!keep.has(t.id)) this.destroyFrame(t.id);
            });
            this.tabs = this.tabs.filter((t) => keep.has(t.id));
            this.persist();
        },

        async closeAll() {
            const dirtyTargets = this.tabs.filter((t) => !t.pinned && t.dirty);
            if (dirtyTargets.length > 0 && typeof window.askConfirm === 'function') {
                const ok = await window.askConfirm({
                    title: this.i18n.unsavedTitle || 'Close all tabs?',
                    message:
                        this.i18n.unsavedMessage ||
                        'Some tabs have unsaved input. Closing them will discard your changes.',
                    tone: 'warning',
                    confirmLabel: this.i18n.closeAnyway || 'Close tabs',
                });
                if (!ok) return;
            }
            this.tabs.forEach((t) => {
                if (!t.pinned) this.destroyFrame(t.id);
            });
            this.tabs = this.tabs.filter((t) => t.pinned);
            this.activate('dashboard');
        },

        destroyFrame(id) {
            const stage = document.getElementById('workspace-stage');
            const pane = stage?.querySelector(`[data-workspace-pane="${CSS.escape(id)}"]`);
            if (!pane) return;
            const frame = pane.querySelector('iframe');
            if (frame) {
                try {
                    frame.src = 'about:blank';
                } catch (e) {}
            }
            pane.remove();
        },

        syncSidebar(targetPath) {
            if (typeof window.__syncSidebarPath === 'function') {
                window.__syncSidebarPath(targetPath);
            }
        },

        bindSidebarInterceptor() {
            if (document.__workspaceSidebarBound) return;
            document.__workspaceSidebarBound = true;

            // Hapus atribut wire:navigate pada link sidebar supaya Livewire
            // tidak ikut menavigasi ulang halaman induk (yang akan menghancurkan
            // iframe tab yang sedang hidup).
            document.querySelectorAll('#sidebar a').forEach((a) => {
                [...a.attributes].forEach((attr) => {
                    if (attr.name === 'wire:navigate' || attr.name.startsWith('wire:navigate.')) {
                        a.removeAttribute(attr.name);
                    }
                });
            });

            // Hentikan progress bar palsu milik full-reload ketika link sidebar diklik.
            const stopNavBar = () => document.getElementById('navigate-progress')?.removeAttribute('data-run');

            window.addEventListener(
                'click',
                (e) => {
                    if (!this.enabled) return;
                    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                        return;
                    }
                    const link = e.target?.closest?.('#sidebar a[href]');
                    if (!link) return;
                    if (link.target || link.hasAttribute('download') || link.hasAttribute('data-workspace-bypass')) {
                        return;
                    }
                    const href = link.getAttribute('href') || '';
                    if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) {
                        return;
                    }
                    let url;
                    try {
                        url = new URL(link.href, window.location.origin);
                    } catch (err) {
                        return;
                    }
                    if (url.origin !== window.location.origin) return;
                    // AI Assistant punya halaman layar penuh sendiri.
                    if (url.pathname.startsWith('/ai-assistant')) return;

                    e.preventDefault();
                    e.stopPropagation();
                    stopNavBar();

                    const meta = metaFromSidebarLink(link);
                    this.openByUrl(url.pathname + url.search, meta);

                    // Di layar kecil, tutup sheet sidebar setelah memilih menu.
                    if (window.innerWidth < 1024) {
                        document.getElementById('sidebarOverlay')?.click();
                    }
                },
                true,
            );
        },

        bindPopState() {
            document.addEventListener(
                'livewire:navigating',
                (e) => {
                    if (this.enabled && document.getElementById('workspace-stage')) {
                        e.preventDefault();
                    }
                },
                true,
            );
            window.addEventListener('popstate', () => {
                if (!this.enabled) return;
                const path = normalizePath(window.location.href);
                const id = tabIdFromPath(path);
                const found = this.tabs.find((t) => t.id === id || t.path === path);
                if (found) {
                    this.activate(found.id, { pushState: false });
                } else {
                    this.openByUrl(window.location.pathname + window.location.search, {});
                }
            });
        },

        bindEmbedMessages() {
            window.addEventListener('message', (event) => {
                if (event.origin !== window.location.origin) return;
                const data = event.data || {};
                if (data.source !== '3dy-workspace') return;

                if (data.type === 'login') {
                    window.location.href = '/login';
                    return;
                }

                if (data.type === 'toast') {
                    if (typeof window.pushToast === 'function') {
                        window.pushToast({
                            variant: data.variant || 'success',
                            message: data.message || '',
                            action: data.action || null,
                        });
                    } else if (typeof window.toast === 'function') {
                        window.toast(data.message || '', data.variant !== 'error', data.action || null);
                    }
                    return;
                }

                // Cari tab pemilik iframe pengirim pesan.
                const stage = document.getElementById('workspace-stage');
                if (!stage) return;
                let ownerId = null;
                stage.querySelectorAll('iframe[data-workspace-frame]').forEach((f) => {
                    if (f.contentWindow === event.source) {
                        ownerId = f.getAttribute('data-workspace-frame');
                    }
                });
                const tab = ownerId ? this.tabs.find((t) => t.id === ownerId) : null;
                if (!tab) return;

                if (data.type === 'dirty') {
                    tab.dirty = Boolean(data.dirty);
                    return;
                }

                if (data.type === 'meta') {
                    if (data.url) {
                        tab.url = cleanUrl(data.url);
                        tab.path = normalizePath(tab.url);
                    }
                    if (!tab.pinned && data.title) {
                        tab.title = data.title;
                    }
                    if (!tab.pinned && data.sectionIcon) {
                        tab.icon = data.sectionIcon;
                    }
                    tab.dirty = Boolean(data.dirty);

                    if (this.activeId === tab.id) {
                        try {
                            const target = cleanUrl(tab.url);
                            if (window.location.pathname + window.location.search !== target) {
                                window.history.replaceState({ workspaceTabId: tab.id }, '', target);
                            }
                        } catch (e) {}
                        document.title = `${tab.title} — ${cfg.appName || 'Tridaya App'}`;
                        this.syncSidebar(tab.path);
                    }
                    this.persist();
                }
            });
        },

        bindAppearanceBroadcast() {
            window.addEventListener('appearance:change', () => {
                const stage = document.getElementById('workspace-stage');
                if (!stage) return;
                const payload = {
                    source: '3dy-workspace-host',
                    type: 'appearance',
                    mode: window.__appearanceMode,
                    accent: window.__appearanceAccent,
                };
                stage.querySelectorAll('iframe[data-workspace-frame]').forEach((f) => {
                    try {
                        f.contentWindow?.postMessage(payload, window.location.origin);
                    } catch (e) {}
                });
            });
        },

        onTabKeydown(event, currentId) {
            const keys = ['ArrowRight', 'ArrowLeft', 'Home', 'End', 'Delete'];
            if (!keys.includes(event.key)) return;
            const idx = this.tabs.findIndex((t) => t.id === currentId);
            if (idx === -1) return;

            if (event.key === 'Delete') {
                if (!this.tabs[idx].pinned) {
                    event.preventDefault();
                    this.close(currentId);
                }
                return;
            }

            event.preventDefault();
            let nextIdx = idx;
            if (event.key === 'ArrowRight') nextIdx = (idx + 1) % this.tabs.length;
            else if (event.key === 'ArrowLeft') nextIdx = (idx - 1 + this.tabs.length) % this.tabs.length;
            else if (event.key === 'Home') nextIdx = 0;
            else if (event.key === 'End') nextIdx = this.tabs.length - 1;

            const next = this.tabs[nextIdx];
            if (!next) return;
            this.activate(next.id);
            requestAnimationFrame(() => {
                document.querySelector(`[data-workspace-tab="${CSS.escape(next.id)}"]`)?.focus();
            });
        },
    });
}
