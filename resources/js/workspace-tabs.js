/**
 * Multi-Tab Workspace (Keep-Alive) — Shell luar.
 *
 * Setiap tab memuat halaman di dalam `<iframe>` same-origin dengan layout
 * embed (tanpa sidebar/header). Karena iframe hanya disembunyikan saat pindah
 * tab, input form, modal, dan posisi scroll tidak hilang.
 *
 * Nama tab SELALU diambil dari label menu sidebar (bukan nama route), supaya
 * tidak pernah muncul nama teknis seperti "Requests.index".
 */

const STORAGE_KEY_PREFIX = 'workspace-tabs:v1:';

/** Identitas tiap divisi: kode badge, nama tampilan, ikon, dan warna badge. */
const DEPT_META = {
    management: { code: 'MGMT', name: 'Management', icon: 'briefcase', color: 'slate' },
    teknisi: { code: 'ENG', name: 'Engineer', icon: 'tools', color: 'indigo' },
    marketing: { code: 'MKT', name: 'Marketing', icon: 'megaphone', color: 'rose' },
    sales: { code: 'SALES', name: 'Sales', icon: 'calendar', color: 'amber' },
    estimator: { code: 'EST', name: 'Estimator', icon: 'tools', color: 'violet' },
    admin: { code: 'ADM', name: 'Admin', icon: 'receipt', color: 'sky' },
    'admin-panel': { code: 'PANEL', name: 'Admin Panel', icon: 'settings', color: 'teal' },
    customer: { code: 'CUST', name: 'Customer', icon: 'users', color: 'cyan' },
    documents: { code: 'DOC', name: 'Documents', icon: 'folder', color: 'teal' },
    monitoring: { code: 'MON', name: 'Monitoring', icon: 'activity', color: 'emerald' },
    'ai-assistant': { code: 'AI', name: 'AI Assistant', icon: 'message', color: 'fuchsia' },
    trash: { code: 'SYS', name: 'Trash', icon: 'trash', color: 'slate' },
    'knowledge-base': { code: 'KB', name: 'Knowledge Base', icon: 'book', color: 'slate' },
    'product-knowledge': { code: 'KB', name: 'Product KB', icon: 'cube', color: 'slate' },
    system: { code: 'SYS', name: 'System', icon: 'settings', color: 'slate' },
};

/** Kelas Tailwind lengkap agar ikut ter-compile (content mencakup resources/js). */
const DEPT_CLASSES = {
    indigo: 'bg-indigo-500/15 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300',
    violet: 'bg-violet-500/15 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300',
    rose: 'bg-rose-500/15 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
    amber: 'bg-amber-500/15 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
    sky: 'bg-sky-500/15 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
    teal: 'bg-teal-500/15 text-teal-700 dark:bg-teal-500/20 dark:text-teal-300',
    cyan: 'bg-cyan-500/15 text-cyan-700 dark:bg-cyan-500/20 dark:text-cyan-300',
    emerald: 'bg-emerald-500/15 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
    fuchsia: 'bg-fuchsia-500/15 text-fuchsia-700 dark:bg-fuchsia-500/20 dark:text-fuchsia-300',
    slate: 'bg-slate-500/15 text-slate-600 dark:bg-slate-500/20 dark:text-slate-300',
};

/** Ikon kelompok menu (sama dengan ikon sidebar) untuk ditampilkan di tab. */
const ICONS = {
    grid: `<rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" />`,
    users: `<circle cx="9" cy="8" r="3.25" /><path d="M3.25 20c0-3.6 2.6-6.25 5.75-6.25S14.75 16.4 14.75 20" /><circle cx="17" cy="9" r="2.4" /><path d="M15.6 13.3c2.3.35 4.15 2.5 4.15 6.2" />`,
    folder: `<path d="M3 7.5a2 2 0 012-2h3.6l1.6 1.8h7.2a2 2 0 012 2V17a2 2 0 01-2 2H5a2 2 0 01-2-2V7.5z" />`,
    calendar: `<rect x="3.5" y="5" width="17" height="15.5" rx="2" /><path d="M3.5 10h17" /><path d="M8 3v4" /><path d="M16 3v4" />`,
    activity: `<polyline points="3,12.5 7.5,12.5 9.5,6.5 14,18.5 16.2,12.5 21,12.5" />`,
    book: `<path d="M12 6.2C12 4.98 10.98 4 9.75 4H4.5v14h5.25c1.23 0 2.25.98 2.25 2.2" /><path d="M12 6.2C12 4.98 13.02 4 14.25 4h5.25v14h-5.25c-1.23 0-2.25.98-2.25 2.2" />`,
    tools: `<path d="M14.5 6.2a3.6 3.6 0 00-4.9 4.9L4 16.7l3.3 3.3 5.6-5.6a3.6 3.6 0 004.9-4.9l-2.35 2.35-1.8-1.8 2.35-2.35z" />`,
    trash: `<path d="M5 7h14" /><path d="M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2" /><path d="M6.5 7l.8 12a2 2 0 002 1.9h5.4a2 2 0 002-1.9l.8-12" /><path d="M10 11v6" /><path d="M14 11v6" />`,
    settings: `<path d="M12.5 3h-1a1.5 1.5 0 00-1.5 1.5v.5a1.5 1.5 0 01-2.2 1.3l-.4-.2a1.5 1.5 0 00-2.1.6l-.5.9a1.5 1.5 0 00.6 2.1l.4.2a1.5 1.5 0 010 2.6l-.4.2a1.5 1.5 0 00-.6 2.1l.5.9a1.5 1.5 0 002.1.6l.4-.2a1.5 1.5 0 012.2 1.3v.5a1.5 1.5 0 001.5 1.5h1a1.5 1.5 0 001.5-1.5v-.5a1.5 1.5 0 012.2-1.3l.4.2a1.5 1.5 0 002.1-.6l.5-.9a1.5 1.5 0 00-.6-2.1l-.4-.2a1.5 1.5 0 010-2.6l.4-.2a1.5 1.5 0 00.6-2.1l-.5-.9a1.5 1.5 0 00-2.1-.6l-.4.2a1.5 1.5 0 01-2.2-1.3v-.5A1.5 1.5 0 0012.5 3z" /><circle cx="12" cy="12" r="2.5" />`,
    briefcase: `<rect x="3" y="7.5" width="18" height="12" rx="2" /><path d="M9 7.5V6a2 2 0 012-2h2a2 2 0 012 2v1.5" /><path d="M3 12.5h18" />`,
    user: `<circle cx="12" cy="8" r="3.5" /><path d="M5 20c.8-3.2 3.5-5 7-5s6.2 1.8 7 5" />`,
    message: `<path d="M21 12a8.5 8.5 0 01-8.5 8.5H5.5l-2 2V12a8.5 8.5 0 018.5-8.5A8.5 8.5 0 0121 12z" /><path d="M8.5 10.5h7" /><path d="M8.5 13.5h4" />`,
    receipt: `<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z" /><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8" /><path d="M12 17.5v-11" />`,
    megaphone: `<path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46" />`,
    cube: `<path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />`,
};

function iconMarkup(name) {
    const body = ICONS[name] || ICONS.grid;
    return `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">${body}</svg>`;
}

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
    return seg.slice(0, 2).join('/');
}

/** Buang sisa nama teknis dari judul lama yang tersimpan di sessionStorage. */
function cleanStoredTitle(title) {
    let value = String(title || '').trim();
    if (value.includes(' • ')) value = value.split(' • ').pop();
    if (value.includes('/')) value = value.split('/').pop();
    return value.replace(/\.index$/i, '').trim() || 'Page';
}

function labelFromLink(link) {
    if (!link) return '';
    const spans = [...link.querySelectorAll('span:not([aria-hidden])')].filter(
        (span) => span.textContent.trim() !== '',
    );
    const el = spans[0] || link;
    return (el.textContent || '').replace(/\s+/g, ' ').trim();
}

/** Tebak divisi dari path untuk halaman yang tidak ada di sidebar. */
function inferDeptKey(path) {
    const rules = [
        ['/admin-panel', 'admin-panel'],
        ['/admin', 'admin'],
        ['/teknisi', 'teknisi'],
        ['/technician', 'teknisi'],
        ['/projects', 'teknisi'],
        ['/project-documents', 'documents'],
        ['/sales', 'sales'],
        ['/leads', 'sales'],
        ['/lead-tasks', 'sales'],
        ['/marketing', 'marketing'],
        ['/partners', 'marketing'],
        ['/whatsapp-center', 'marketing'],
        ['/estimator', 'estimator'],
        ['/products', 'estimator'],
        ['/manage-sales', 'management'],
        ['/manage-', 'management'],
        ['/customers', 'customer'],
        ['/customer-contacts', 'customer'],
        ['/documents', 'documents'],
        ['/monitoring', 'monitoring'],
        ['/ai-assistant', 'ai-assistant'],
        ['/trash', 'trash'],
        ['/knowledge-base', 'knowledge-base'],
        ['/product-knowledge', 'product-knowledge'],
        ['/dashboard', 'dashboard'],
    ];
    for (const [prefix, key] of rules) {
        if (path === prefix || path.startsWith(prefix + '/')) return key;
    }
    return null;
}

export function registerWorkspaceStore(Alpine) {
    if (!Alpine || Alpine.store('workspace')) return;

    const cfg = window.__workspaceConfig || {};
    const userKey = STORAGE_KEY_PREFIX + String(cfg.userId || 'guest');
    const appName = cfg.appName || 'Tridaya App';

    Alpine.store('workspace', {
        enabled: Boolean(cfg.enabled),
        tabs: [],
        activeId: 'dashboard',
        maxDesktop: Number(cfg.maxDesktop || 8),
        maxMobile: Number(cfg.maxMobile || 6),
        i18n: cfg.i18n || {},
        initialized: false,
        hintTimer: null,

        maxTabs() {
            return window.innerWidth < 1024 ? this.maxMobile : this.maxDesktop;
        },

        iconSvg(name) {
            return iconMarkup(name);
        },

        deptClass(code) {
            if (!code) return DEPT_CLASSES.slate;
            const meta = Object.values(DEPT_META).find((entry) => entry.code === code);
            return DEPT_CLASSES[meta?.color || 'slate'];
        },

        fullTitle(tab) {
            if (!tab) return appName;
            return tab.dept ? `${tab.dept} • ${tab.title}` : tab.title;
        },

        metaFromInitial(initial) {
            const rawLabel = initial.tabLabel || initial.sectionLabel || cleanStoredTitle(initial.tabTitle);
            let title = rawLabel || 'Page';
            if (initial.deptCode && initial.deptCode !== 'DASH' && String(title).toLowerCase() === 'dashboard') {
                title = `Dashboard ${initial.deptName || ''}`.trim();
            }
            return {
                title,
                dept: initial.deptCode || null,
                deptName: initial.deptName || null,
                icon: initial.sectionIcon || 'grid',
            };
        },

        resolveMetaForPath(rawPath) {
            const path = normalizePath(rawPath);
            const links = [...document.querySelectorAll('#sidebar a[href]')];
            let best = null;
            let bestLen = -1;

            links.forEach((link) => {
                const linkPath = normalizePath(link.href);
                if (path !== linkPath && !path.startsWith(linkPath + '/')) return;
                if (linkPath.length > bestLen) {
                    best = link;
                    bestLen = linkPath.length;
                }
            });

            if (!best) return null;

            // Header grup dan sub-item pertama punya URL yang sama; pakai label
            // sub-item supaya tab menampilkan nama menu, bukan nama divisi.
            let labelLink = best;
            if (best.closest('.sb-head')) {
                const sub = links.find(
                    (link) =>
                        link.closest('.sb-sub-item') &&
                        normalizePath(link.href) === normalizePath(best.href),
                );
                if (sub) labelLink = sub;
            }

            const label = labelFromLink(labelLink) || labelFromLink(best);
            const groupKey =
                best.closest('[data-sb-group]')?.getAttribute('data-sb-group') || inferDeptKey(path);
            const dept = DEPT_META[groupKey] || null;

            let title = label || dept?.name || 'Page';
            if (dept && groupKey !== 'dashboard' && title.toLowerCase() === 'dashboard') {
                title = `Dashboard ${dept.name}`;
            }

            return {
                title,
                dept: dept?.code || null,
                deptName: dept?.name || null,
                icon: dept?.icon || 'grid',
            };
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
                dept: null,
                deptName: null,
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
                        .filter(
                            (tab) =>
                                tab &&
                                typeof tab.id === 'string' &&
                                typeof tab.url === 'string' &&
                                tab.id !== 'dashboard',
                        )
                        .slice(0, this.maxDesktop - 1)
                        .map((tab) => {
                            const resolved = this.resolveMetaForPath(tab.url);
                            return {
                                id: tab.id,
                                title: resolved?.title || cleanStoredTitle(tab.title),
                                dept: resolved?.dept || tab.dept || null,
                                deptName: resolved?.deptName || tab.deptName || null,
                                url: cleanUrl(tab.url),
                                path: normalizePath(tab.url),
                                icon: resolved?.icon || String(tab.icon || 'grid'),
                                pinned: false,
                                dirty: false,
                                loaded: false,
                            };
                        });
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
                const resolved = this.resolveMetaForPath(currentPath);
                const fallback = this.metaFromInitial(initial);
                const existing = this.tabs.find((tab) => tab.id === currentId || tab.path === currentPath);

                if (existing) {
                    existing.url = currentUrl;
                    existing.path = currentPath;
                    if (resolved) {
                        existing.title = resolved.title;
                        existing.dept = resolved.dept;
                        existing.deptName = resolved.deptName;
                        existing.icon = resolved.icon;
                    }
                    this.activate(existing.id, { pushState: false });
                } else {
                    if (this.tabs.length >= this.maxTabs()) {
                        this.tabs.splice(1, 1);
                    }
                    const tab = {
                        id: currentId,
                        title: resolved?.title || fallback.title,
                        dept: resolved?.dept || fallback.dept,
                        deptName: resolved?.deptName || fallback.deptName,
                        url: currentUrl,
                        path: currentPath,
                        icon: resolved?.icon || fallback.icon,
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
            this.bindTabsScroll();
        },

        persist() {
            try {
                const slim = this.tabs.map((tab) => ({
                    id: tab.id,
                    title: tab.title,
                    dept: tab.dept,
                    deptName: tab.deptName,
                    url: tab.url,
                    icon: tab.icon,
                    pinned: Boolean(tab.pinned),
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
                frame.setAttribute('title', this.fullTitle(tab) || 'Workspace tab');
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
            const active = this.tabs.find((tab) => tab.id === this.activeId) || this.tabs[0];
            if (!active) return;
            this.ensureFrame(active);
            stage.querySelectorAll('[data-workspace-pane]').forEach((pane) => {
                const on = pane.getAttribute('data-workspace-pane') === active.id;
                pane.classList.toggle('hidden', !on);
            });
        },

        activate(id, options = {}) {
            const tab = this.tabs.find((item) => item.id === id);
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

            document.title = `${this.fullTitle(tab)} — ${appName}`;
            this.syncSidebar(tab.path || normalizePath(tab.url));
            this.persist();
            this.scrollActiveIntoView(tab);
            this.scheduleOverflowHints();
        },

        openByUrl(rawUrl, meta = {}) {
            const url = cleanUrl(rawUrl);
            const path = normalizePath(url);
            const id = tabIdFromPath(path);

            const existing = this.tabs.find((tab) => tab.id === id || tab.path === path);
            if (existing) {
                const resolved = this.resolveMetaForPath(path);
                if (resolved) {
                    existing.title = resolved.title;
                    existing.dept = resolved.dept;
                    existing.deptName = resolved.deptName;
                    existing.icon = resolved.icon;
                }
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

            const resolved = this.resolveMetaForPath(path);
            const tab = {
                id,
                title: resolved?.title || meta.title || 'Page',
                dept: resolved?.dept || meta.dept || null,
                deptName: resolved?.deptName || meta.deptName || null,
                url,
                path,
                icon: resolved?.icon || meta.icon || 'grid',
                pinned: false,
                dirty: false,
                loaded: false,
            };
            this.tabs.push(tab);
            this.activate(tab.id);
            return tab;
        },

        async close(id) {
            const idx = this.tabs.findIndex((tab) => tab.id === id);
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
            this.scheduleOverflowHints();
        },

        async closeOthers() {
            const keep = new Set(['dashboard', this.activeId]);
            const dirtyTargets = this.tabs.filter((tab) => !keep.has(tab.id) && tab.dirty);
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
            this.tabs.forEach((tab) => {
                if (!keep.has(tab.id)) this.destroyFrame(tab.id);
            });
            this.tabs = this.tabs.filter((tab) => keep.has(tab.id));
            this.persist();
            this.scheduleOverflowHints();
        },

        async closeAll() {
            const dirtyTargets = this.tabs.filter((tab) => !tab.pinned && tab.dirty);
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
            this.tabs.forEach((tab) => {
                if (!tab.pinned) this.destroyFrame(tab.id);
            });
            this.tabs = this.tabs.filter((tab) => tab.pinned);
            this.activate('dashboard');
            this.scheduleOverflowHints();
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

            // Livewire tidak boleh menavigasi halaman induk saat link sidebar
            // diklik; jika tidak, seluruh iframe tab ikut hancur.
            document.querySelectorAll('#sidebar a').forEach((link) => {
                [...link.attributes].forEach((attr) => {
                    if (attr.name === 'wire:navigate' || attr.name.startsWith('wire:navigate.')) {
                        link.removeAttribute(attr.name);
                    }
                });
            });

            const stopNavBar = () =>
                document.getElementById('navigate-progress')?.removeAttribute('data-run');

            window.addEventListener(
                'click',
                (event) => {
                    if (!this.enabled) return;
                    if (
                        event.defaultPrevented ||
                        event.button !== 0 ||
                        event.metaKey ||
                        event.ctrlKey ||
                        event.shiftKey ||
                        event.altKey
                    ) {
                        return;
                    }
                    const link = event.target?.closest?.('#sidebar a[href]');
                    if (!link) return;
                    if (
                        link.target ||
                        link.hasAttribute('download') ||
                        link.hasAttribute('data-workspace-bypass')
                    ) {
                        return;
                    }
                    const href = link.getAttribute('href') || '';
                    if (
                        !href ||
                        href.startsWith('#') ||
                        href.startsWith('mailto:') ||
                        href.startsWith('tel:')
                    ) {
                        return;
                    }
                    let url;
                    try {
                        url = new URL(link.href, window.location.origin);
                    } catch (err) {
                        return;
                    }
                    if (url.origin !== window.location.origin) return;
                    if (url.pathname.startsWith('/ai-assistant')) return;

                    event.preventDefault();
                    event.stopPropagation();
                    stopNavBar();

                    this.openByUrl(url.pathname + url.search);

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
                (event) => {
                    if (this.enabled && document.getElementById('workspace-stage')) {
                        event.preventDefault();
                    }
                },
                true,
            );

            window.addEventListener('popstate', () => {
                if (!this.enabled) return;
                const path = normalizePath(window.location.href);
                const id = tabIdFromPath(path);
                const found = this.tabs.find((tab) => tab.id === id || tab.path === path);
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

                const stage = document.getElementById('workspace-stage');
                if (!stage) return;
                let ownerId = null;
                stage.querySelectorAll('iframe[data-workspace-frame]').forEach((frame) => {
                    if (frame.contentWindow === event.source) {
                        ownerId = frame.getAttribute('data-workspace-frame');
                    }
                });
                const tab = ownerId ? this.tabs.find((item) => item.id === ownerId) : null;
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
                    tab.dirty = Boolean(data.dirty);

                    // Judul, badge, dan ikon sengaja TIDAK diambil dari iframe:
                    // identitas tab harus tetap sama dengan menu sidebar.
                    if (this.activeId === tab.id) {
                        try {
                            const target = cleanUrl(tab.url);
                            if (window.location.pathname + window.location.search !== target) {
                                window.history.replaceState(
                                    { workspaceTabId: tab.id },
                                    '',
                                    target,
                                );
                            }
                        } catch (e) {}
                        document.title = `${this.fullTitle(tab)} — ${appName}`;
                        this.syncSidebar(tab.path);
                    }
                    this.persist();
                    this.scheduleOverflowHints();
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
                stage.querySelectorAll('iframe[data-workspace-frame]').forEach((frame) => {
                    try {
                        frame.contentWindow?.postMessage(payload, window.location.origin);
                    } catch (e) {}
                });
            });
        },

        bindTabsScroll() {
            const bar = document.getElementById('workspace-tabbar');
            const list = document.getElementById('workspace-tablist');
            if (!bar || !list) return;

            if (!document.__workspaceScrollBound) {
                document.__workspaceScrollBound = true;

                list.addEventListener('scroll', () => this.updateOverflowHints(), { passive: true });
                window.addEventListener('resize', () => this.updateOverflowHints());

                // Roda mouse vertikal di atas barisan tab = geser horizontal.
                list.addEventListener(
                    'wheel',
                    (event) => {
                        if (event.ctrlKey || event.metaKey) return;
                        const max = list.scrollWidth - list.clientWidth;
                        if (max <= 2) return;
                        const delta =
                            Math.abs(event.deltaY) >= Math.abs(event.deltaX)
                                ? event.deltaY
                                : event.deltaX;
                        if (Math.abs(delta) < 1) return;
                        const next = Math.max(0, Math.min(max, list.scrollLeft + delta));
                        if (next === list.scrollLeft) return;
                        event.preventDefault();
                        list.scrollLeft = next;
                    },
                    { passive: false },
                );

                bar.querySelectorAll('[data-ws-scroll]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const direction = button.getAttribute('data-ws-scroll') === 'left' ? -1 : 1;
                        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                        list.scrollBy({
                            left: direction * Math.max(180, Math.round(list.clientWidth * 0.6)),
                            behavior: reduce ? 'auto' : 'smooth',
                        });
                    });
                });

                if (typeof ResizeObserver !== 'undefined') {
                    new ResizeObserver(() => this.updateOverflowHints()).observe(list);
                }
                if (typeof MutationObserver !== 'undefined') {
                    new MutationObserver(() => this.scheduleOverflowHints()).observe(list, {
                        childList: true,
                        subtree: true,
                    });
                }
            }

            this.updateOverflowHints();
        },

        updateOverflowHints() {
            const bar = document.getElementById('workspace-tabbar');
            const list = document.getElementById('workspace-tablist');
            if (!bar || !list) return;
            const max = list.scrollWidth - list.clientWidth;
            const left = list.scrollLeft > 2;
            const right = max - list.scrollLeft > 2;
            bar.dataset.overflow =
                max <= 2 ? 'none' : left && right ? 'both' : left ? 'left' : right ? 'right' : 'none';
        },

        scheduleOverflowHints() {
            if (this.hintTimer) clearTimeout(this.hintTimer);
            this.hintTimer = setTimeout(() => this.updateOverflowHints(), 60);
        },

        scrollActiveIntoView(tab) {
            const run = () => {
                const el = document.querySelector(
                    `[data-workspace-tab="${CSS.escape(tab.id)}"]`,
                );
                if (!el) return;
                const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                el.scrollIntoView({
                    behavior: reduce ? 'auto' : 'smooth',
                    block: 'nearest',
                    inline: 'center',
                });
                setTimeout(() => this.updateOverflowHints(), 400);
            };
            requestAnimationFrame(() => requestAnimationFrame(run));
            setTimeout(run, 180);
        },

        onTabKeydown(event, currentId) {
            const keys = ['ArrowRight', 'ArrowLeft', 'Home', 'End', 'Delete'];
            if (!keys.includes(event.key)) return;
            const idx = this.tabs.findIndex((tab) => tab.id === currentId);
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
                document
                    .querySelector(`[data-workspace-tab="${CSS.escape(next.id)}"]`)
                    ?.focus();
            });
        },
    });
}
