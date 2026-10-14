{{-- 3DY AI Chat V2: floating assistant (pengganti ai-assistant lama).
      Launcher bisa digeser (posisi tersimpan per user); jendela menempel
      di samping launcher dengan offset manual via header (ikut pindah
      saat launcher digeser). Berbicara ke endpoint existing
      POST ai.assistant.send. File lama components/ai-assistant.blade.php
      dipertahankan tapi tidak di-mount. --}}
@auth
@php($v2Convos = auth()->user()->conversations()->latest('updated_at')->take(20)->get(['id', 'title', 'updated_at'])->map(fn ($c) => ['id' => $c->id, 'title' => \App\Services\ConversationTitle::smart($c->title), 'updated_at' => $c->updated_at?->toISOString()])->values()->all())
@persist('ai-chat-v2')
<div x-data="aiChatV2(@js(auth()->id()), @js(route('ai.assistant.send')), @js(url('/ai/assistant/conversations')), @js($v2Convos))"
     x-init="init()"
     class="ai-chat-v2-root"
     @keydown.escape.window="minimize()"
     @resize.window="clampAll()">
    {{-- Launcher ala komik: selalu tampil (tokohnya), jendela = balon
         ucapannya. Klik = toggle buka/tutup. --}}
    <button type="button"
            x-cloak
            @pointerdown="dragStart($event, 'launcher')"
            @pointermove="dragMove($event, 'launcher')"
            @pointerup="dragEnd($event, 'launcher')"
            @pointercancel="dragCancel()"
            @click.capture.stop="guardClick($event)"
            :style="triggerStyle()"
            :aria-expanded="open"
            aria-label="{{ __('Toggle 3DY AI') }}"
            class="aiv2-launcher fixed z-[1000] flex h-14 w-14 touch-none items-center justify-center rounded-full bg-gradient-to-br from-accent-400 via-accent-600 to-accent-700 text-white shadow-xl shadow-accent-600/40 ring-4 ring-accent-500/20 transition-transform duration-200 hover:scale-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-400 focus-visible:ring-offset-2 active:scale-95 motion-reduce:transform-none">
        <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3z" /><path d="M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15z" /></svg>
    </button>

    {{-- Chat window (menempel launcher + offset manual via header) --}}
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         :style="chatStyle()"
          class="fixed z-[1000] flex h-[560px] w-[332px] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-800"
         role="dialog" aria-modal="false" aria-label="3DY AI">
        {{-- Header (gagang geser offset; tombol di dalamnya tetap diklik biasa) --}}
        <div @pointerdown="dragStart($event, 'chat')"
             @pointermove="dragMove($event, 'chat')"
             @pointerup="dragEnd($event, 'chat')"
             @pointercancel="dragCancel()"
             class="flex cursor-grab touch-none select-none items-center gap-2.5 border-b border-slate-200 bg-gradient-to-r from-accent-700 via-accent-600 to-accent-500 px-4 py-3 text-white active:cursor-grabbing dark:border-slate-700">
            <span class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/20" aria-hidden="true">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3z" /></svg>
                <span class="absolute -bottom-0.5 -right-0.5 flex h-3 w-3">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75 motion-reduce:animate-none"></span>
                    <span class="relative inline-flex h-3 w-3 rounded-full border-2 border-accent-600 bg-green-400"></span>
                </span>
            </span>
            <span class="min-w-0 flex-1 leading-tight">
                <span class="block truncate text-sm font-bold">3DY AI</span>
                <span class="block truncate text-xs text-white/80" x-text="activeTitle()">{{ __('Online') }}</span>
            </span>
            <button type="button" @click="toggleList()" aria-label="{{ __('Daftar percakapan') }}" title="{{ __('Daftar percakapan') }}"
                    class="rounded-lg p-1.5 transition hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h10" /></svg>
            </button>
            <button type="button" @click="newChat()" :disabled="busy" aria-label="{{ __('Percakapan baru') }}" title="{{ __('Percakapan baru') }}"
                    class="rounded-lg p-1.5 transition hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60 disabled:opacity-40">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg>
            </button>
            <button type="button" @click="minimize()" aria-label="{{ __('Minimize chat') }}" title="{{ __('Minimize chat') }}"
                    class="rounded-lg p-1.5 transition hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M5 12h14" /></svg>
            </button>
            <button type="button" @click="closeChat()" aria-label="{{ __('Tutup chat') }}" title="{{ __('Tutup dan mulai baru') }}"
                    class="rounded-lg p-1.5 transition hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
            </button>
        </div>

        {{-- Messages --}}
        <div x-show="!showList" x-ref="msgs"
             class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-slate-50 px-4 py-4 dark:bg-slate-900/60"
             aria-live="polite" aria-label="{{ __('Riwayat percakapan') }}">
            <template x-if="!messages.length && !busy">
                <div class="py-6 text-center">
                    <p class="whitespace-pre-line text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __("👋 Halo! Saya 3DY AI.\nAda yang bisa saya bantu?") }}</p>
                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        <template x-for="q in quicks" :key="q">
                            <button type="button" @click="sendQuick(q)" :disabled="busy"
                                    class="rounded-full border border-accent-500/40 bg-accent-50 px-3 py-1.5 text-xs font-semibold text-accent-700 transition hover:-translate-y-0.5 hover:bg-accent-100 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-400 disabled:opacity-50 dark:bg-accent-500/10 dark:text-accent-300 dark:hover:bg-accent-500/20"
                                    x-text="q"></button>
                        </template>
                    </div>
                </div>
            </template>
            <template x-for="m in messages" :key="m.id">
                <div :class="m.role === 'user' ? 'flex justify-end' : 'flex items-end justify-start gap-2'">
                    <template x-if="m.role !== 'user'">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-accent-400 to-accent-700 text-white" aria-hidden="true">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3z" /></svg>
                        </span>
                    </template>
                    <div :class="m.role === 'user'
                            ? 'max-w-[80%] rounded-2xl rounded-br-md border border-accent-300/50 bg-accent-100 px-3.5 py-2.5 text-sm text-slate-800 dark:border-accent-400/35 dark:bg-accent-500/30 dark:text-accent-100'
                            : 'max-w-[85%] rounded-2xl rounded-bl-md border border-slate-200 bg-slate-100 px-3.5 py-2.5 text-sm text-slate-700 dark:border-white/15 dark:bg-zinc-700/70 dark:text-zinc-200'">
                        <template x-if="m.role === 'user'">
                            <p class="whitespace-pre-wrap break-words" x-text="m.content"></p>
                        </template>
                        <template x-if="m.role !== 'user'">
                            <div class="aiv2-md break-words" x-html="renderMarkdown(m.content)"></div>
                        </template>
                        <p class="mt-1 text-right text-[10px] leading-none opacity-60" x-text="m.time"></p>
                    </div>
                </div>
            </template>
            <template x-if="busy">
                <div class="flex items-end justify-start gap-2">
                    <div class="flex items-center gap-1.5 rounded-2xl rounded-bl-md border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-800" role="status" aria-label="{{ __('AI sedang mengetik') }}">
                        <span class="aiv2-dot"></span><span class="aiv2-dot"></span><span class="aiv2-dot"></span>
                    </div>
                </div>
            </template>
            <template x-if="error">
                <div class="rounded-xl border border-red-300 bg-red-50 px-3.5 py-2.5 text-xs text-red-700 dark:border-red-800 dark:bg-red-500/10 dark:text-red-300" role="alert">
                    <p x-text="error"></p>
                    <button type="button" @click="retry()" :disabled="busy || !lastUserText"
                            class="mt-2 rounded-lg bg-red-100 px-3 py-1.5 font-semibold text-red-700 transition hover:bg-red-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-400 disabled:opacity-50 dark:bg-red-500/20 dark:text-red-200">
                        {{ __('Coba lagi') }}
                    </button>
                </div>
            </template>
        </div>

        {{-- Conversation list --}}
        <div x-show="showList"
             class="min-h-0 flex-1 space-y-1.5 overflow-y-auto bg-slate-50 px-3 py-3 dark:bg-slate-900/60"
             aria-label="{{ __('Daftar percakapan') }}">
            <button type="button" @click="newChat()" :disabled="busy || loadingConvo"
                    class="w-full rounded-xl bg-accent-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-400 disabled:opacity-50">
                {{ __('+ Percakapan baru') }}
            </button>
            <template x-if="loadingConvo">
                <div class="flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-800" role="status" aria-label="{{ __('Memuat percakapan...') }}">
                    <span class="aiv2-dot"></span><span class="aiv2-dot"></span><span class="aiv2-dot"></span>
                </div>
            </template>
            <template x-for="c in convos" :key="c.id">
                <button type="button" @click="openConversation(c.id)" :disabled="busy || loadingConvo"
                        :class="c.id === conversationId
                            ? 'border-accent-500 bg-accent-50 dark:bg-accent-500/10'
                            : 'border-slate-200 bg-white hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700/60'"
                        class="w-full truncate rounded-xl border px-3 py-2 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-400 disabled:opacity-50">
                    <span class="block truncate text-sm font-semibold text-slate-700 dark:text-slate-200" x-text="c.title"></span>
                </button>
            </template>
            <template x-if="!convos.length && !loadingConvo">
                <p class="py-4 text-center text-xs text-slate-500 dark:text-slate-400">{{ __('Belum ada percakapan.') }}</p>
            </template>
        </div>

        {{-- Input --}}
        <div x-show="!showList" class="border-t border-slate-200 bg-white px-3.5 py-3 dark:border-slate-700 dark:bg-slate-800">
            <div class="flex items-end gap-2">
                <textarea x-ref="input" x-model="draft" rows="1" maxlength="2000"
                          @input="resize()" @keydown.enter="onEnter($event)"
                          :placeholder="busy ? '{{ __('Menunggu jawaban...') }}' : '{{ __('Tulis pesan...') }}'"
                          aria-label="{{ __('Tulis pesan untuk 3DY AI') }}"
                          class="max-h-[120px] min-h-[40px] flex-1 resize-none rounded-xl border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-800 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"></textarea>
                <button type="button" @click="send()" :disabled="!canSend()"
                        aria-label="{{ __('Kirim pesan') }}"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-accent-600 text-white shadow-sm transition hover:bg-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-400 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13" /><path d="M22 2 15 22l-4-9-9-4 20-7z" /></svg>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.aiv2-dot { width: 7px; height: 7px; border-radius: 9999px; background: rgb(var(--accent-500)); animation: aiv2-typing 1.2s infinite ease-in-out; }
.aiv2-dot:nth-child(2) { animation-delay: .15s; } .aiv2-dot:nth-child(3) { animation-delay: .3s; }
@keyframes aiv2-typing { 0%,60%,100% { transform: translateY(0); opacity: .5; } 30% { transform: translateY(-4px); opacity: 1; } }
.aiv2-md { font-size: .875rem; line-height: 1.5; }
.aiv2-md p { margin: .25rem 0; } .aiv2-md p:first-child { margin-top: 0; } .aiv2-md p:last-child { margin-bottom: 0; }
.aiv2-md ul, .aiv2-md ol { margin: .25rem 0; padding-left: 1.1rem; } .aiv2-md ul { list-style: disc; } .aiv2-md ol { list-style: decimal; }
.aiv2-md code { font-size: .8em; background: rgb(148 163 184 / .18); border-radius: .3rem; padding: .05rem .3rem; }
.aiv2-md pre { overflow-x: auto; background: rgb(15 23 42 / .9); color: #e2e8f0; border-radius: .6rem; padding: .6rem .75rem; margin: .4rem 0; font-size: .78rem; }
.aiv2-md pre code { background: transparent; padding: 0; }
.aiv2-md a { color: rgb(var(--accent-600)); text-decoration: underline; }
.aiv2-md table { display: block; width: fit-content; max-width: 100%; margin: .5rem 0; overflow-x: auto; border-collapse: collapse; font-size: .8rem; white-space: nowrap; }
.aiv2-md th, .aiv2-md td { padding: .35rem .6rem; border: 1px solid rgb(226 232 240); text-align: left; }
.dark .aiv2-md th, .dark .aiv2-md td { border-color: rgb(51 65 85); }
.aiv2-md th { background: rgb(var(--accent-500) / .08); font-weight: 700; }
@media (prefers-reduced-motion: reduce) { .aiv2-dot { animation: none !important; } }
</style>

<script>
function aiChatV2(uid, sendUrl, showUrl, initialConvos) {
    return {
        uid, sendUrl, showUrl, open: false, busy: false, error: '',
        messages: [], draft: '', conversationId: null, lastUserText: '',
        convos: initialConvos || [], showList: false, loadingConvo: false,
        __inited: false,
        // Posisi launcher (tersimpan per user) + offset manual jendela
        // dari jangkarnya (tersimpan per user). Jendela = jangkar + offset.
        // Gandeng dua arah: geser jendela ikut menggeser launcher.
        pos: null, chatOff: null,
        // Ukuran viewport (reaktif): semua geometri (clamp/trigger/anchor)
        // membaca state ini, bukan window.* langsung — binding :style hanya
        // dihitung ulang saat state berubah, jadi tanpa ini posisi/ukuran
        // membeku pada viewport lama (bubble bisa keluar layar setelah putar
        // layar HP; jendela bisa tetap selebar layar setelah maximize).
        // vv* = visual viewport (bagian layar yang benar-benar terlihat —
        // di iOS tingginya mengecil saat keyboard terbuka).
        vw: Math.max(window.innerWidth || 0, 0),
        vh: Math.max(window.innerHeight || 0, 0),
        vvh: window.visualViewport ? window.visualViewport.height : window.innerHeight,
        vvo: window.visualViewport ? (window.visualViewport.offsetTop || 0) : 0,
        dragging: false, dragWhich: null, moved: false, suppressUntil: 0,
        sx: 0, sy: 0, ox: 0, oy: 0, bx: 0, by: 0,
        quicks: @js([__('Jelaskan data server saya'), __('Bantu analisis masalah'), __('Buatkan kode Laravel'), __('Jelaskan konsep jaringan')]),
        key() { return '3dy.ai.v2.conv.' + this.uid; },
        posKey() { return '3dy.ai.v2.pos.' + this.uid; },
        chatOffKey() { return '3dy.ai.v2.chatoff.' + this.uid; },
        init() {
            // Idempoten: aman bila x-init jalan ulang habis morph Livewire.
            if (this.__inited) return;
            this.__inited = true;
            try {
                const saved = JSON.parse(localStorage.getItem(this.posKey()) || 'null');
                if (saved && typeof saved.left === 'number') this.pos = saved;
            } catch (e) {}
            try {
                const savedOff = JSON.parse(localStorage.getItem(this.chatOffKey()) || 'null');
                if (savedOff && typeof savedOff.dx === 'number') this.chatOff = savedOff;
            } catch (e) {}
            // Bersih-bersih key posisi jendela mandiri (era jendela draggable) —
            // kini jendela = jangkar + offset.
            try { localStorage.removeItem('3dy.ai.v2.chatpos.' + this.uid); } catch (e) {}
            try { this.conversationId = localStorage.getItem(this.key()) || null; } catch (e) { this.conversationId = null; }
            // ID simpanan yang sudah tak ada di daftar (mis. dihapus dari halaman AI) dibuang.
            if (this.conversationId && !this.convos.some(c => c.id === this.conversationId)) {
                this.conversationId = null;
                try { localStorage.removeItem(this.key()); } catch (e) {}
            }
            // Keyboard HP & URL bar mengubah visual viewport tanpa selalu
            // memicu resize window — dengarkan keduanya.
            const vv = window.visualViewport;
            if (vv && !this.__vvBound) {
                this.__vvBound = true;
                vv.addEventListener('resize', () => this.clampAll());
                vv.addEventListener('scroll', () => this.clampAll());
            }
        },
        activeTitle() {
            const c = this.convos.find(c => c.id === this.conversationId);
            return c ? c.title : @js(__('Online'));
        },
        toggleList() { this.showList = !this.showList; },
        touchConvo(c) {
            if (!c || !c.id) return;
            this.convos = [{
                id: c.id,
                title: c.title || @js(__('Percakapan baru')),
                updated_at: c.updated_at ?? null,
            }, ...this.convos.filter(x => x.id !== c.id)].slice(0, 20);
        },
        async openConversation(id) {
            if (this.busy || this.loadingConvo || !id) return;
            if (id === this.conversationId && this.messages.length) { this.showList = false; return; }
            this.loadingConvo = true; this.error = '';
            try {
                const res = await fetch(this.showUrl + '/' + encodeURIComponent(id), { headers: this.headers() });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || ('HTTP ' + res.status));
                this.touchConvo(data.conversation);
                this.conversationId = (data.conversation && data.conversation.id) || id;
                try { localStorage.setItem(this.key(), this.conversationId); } catch (e) {}
                this.messages = (data.messages ?? []).map((m, i) => ({
                    id: m.id ?? ('m' + Date.now() + i),
                    role: m.role,
                    content: m.content ?? '',
                    time: '',
                }));
                this.lastUserText = '';
                this.showList = false;
                this.scrollBottom();
            } catch (e) {
                this.error = e?.message || @js(__('Gagal memuat percakapan.'));
                this.showList = false;
            } finally {
                this.loadingConvo = false;
            }
        },
        openChat() {
            this.open = true; this.error = '';
            this.$nextTick(() => { this.scrollBottom(); this.$refs.input?.focus({ preventScroll: true }); });
        },
        minimize() { this.open = false; },
        // × = selesai: tutup + reset jendela penuh (pesan, draf, ID
        // percakapan dilepas) sehingga buka berikutnya mulai baru.
        // Data di server TIDAK dihapus — riwayat tetap ada di daftar
        // percakapan & halaman AI.
        closeChat() {
            this.open = false; this.messages = []; this.lastUserText = '';
            this.conversationId = null; this.showList = false;
            this.draft = ''; this.error = '';
            try { localStorage.removeItem(this.key()); } catch (e) {}
            this.$nextTick(() => this.resize());
        },
        // --- Geser: launcher pindah jangkar, header jendela set offset.
        // Pola: threshold 10px, klik launcher tertelan bila habis drag;
        // keyboard (detail 0) selalu membuka.
        isNarrow() { return window.innerWidth < 640; },
        clamp(p, w, h) {
            const vw = this.vw, vh = this.vh;
            return {
                left: Math.min(Math.max(p.left, 8), Math.max(vw - w - 8, 8)),
                top: Math.min(Math.max(p.top, 8), Math.max(vh - h - 8, 8)),
            };
        },
        clampAll() {
            // Selalu perbarui ukuran viewport dulu (state reaktif) supaya
            // binding :style bubble/jendela dihitung ulang tiap resize.
            // Ukuran degenerat (window di-minimize: innerWidth/Height bisa 0)
            // diabaikan agar posisi tidak nyangkut di (8,8).
            const w = window.innerWidth, h = window.innerHeight;
            if (w < 2 || h < 2) return;
            this.vw = w; this.vh = h;
            const vv = window.visualViewport;
            this.vvh = vv ? vv.height : h;
            this.vvo = vv ? (vv.offsetTop || 0) : 0;
            if (this.pos) this.pos = this.clamp(this.pos, 56, 56);
        },
        triggerPos() {
            if (this.pos) return this.clamp(this.pos, 56, 56);
            return { left: this.vw - 24 - 56, top: this.vh - 24 - 56 };
        },
        triggerStyle() {
            // WAJIB object (bukan string): :style string menimpa seluruh
            // atribut style sehingga display:none milik x-show terhapus —
            // jendela/bubble bisa nongol sendiri habis digeser.
            const p = this.triggerPos();
            return { left: `${p.left}px`, top: `${p.top}px` };
        },
        anchorGeom() {
            const vw = this.vw, vh = this.vh;
            if (vw < 640) return null;
            const w = Math.min(332, vw - 24), h = Math.min(560, Math.max(vh - 24, 160));
            const p = this.triggerPos(), GAP = 16;
            const left = (p.left + 56 + GAP + w <= vw) ? p.left + 56 + GAP : Math.max(p.left - GAP - w, 12);
            const top = Math.min(Math.max(p.top + 56 - h, 12), vh - h - 12);
            return { left, top, w, h };
        },
        chatGeom() {
            const g = this.anchorGeom();
            if (!g) return null;
            const GAP = 16, BS = 56;
            let p = { left: g.left, top: g.top };
            if (this.chatOff) p = { left: g.left + this.chatOff.dx, top: g.top + this.chatOff.dy };
            // Tegakkan jarak: bubble tak boleh tumpuk/terlalu rapat dengan
            // jendela (mis. offset lama) — dorong ke sisi terdekat.
            const b = this.triggerPos();
            const vOverlap = p.top < b.top + BS && b.top < p.top + g.h;
            if (vOverlap) {
                const gapR = p.left - (b.left + BS), gapL = b.left - (p.left + g.w);
                if (gapR < GAP && gapL < GAP) {
                    p = { ...p, left: (p.left + g.w / 2 >= b.left + BS / 2) ? b.left + BS + GAP : b.left - GAP - g.w };
                }
            }
            p = this.clamp(p, g.w, g.h);
            return { ...p, w: g.w, h: g.h };
        },
        chatStyle() {
            // Object form (lihat triggerStyle): aman terhadap x-show.
            // Sisa right/bottom saat pindah desktop↔mobile diabaikan browser
            // (over-constrained LTR) — width/height selalu di-set eksplisit.
            const g = this.chatGeom();
            // Bagian layar yang tertutup keyboard HP: visual viewport lebih
            // pendek dari layout viewport (iOS tidak mengecilkan layout
            // viewport) — angkat dasar jendela setinggi itu supaya baris
            // input tetap terlihat.
            const hidden = Math.max(0, this.vh - (this.vvo + this.vvh));
            if (!g) {
                // Layar sempit: jendela mengisi layar (left/right/top/bottom).
                return { left: '12px', right: '12px', top: '64px', bottom: (12 + hidden) + 'px', width: 'auto', height: 'auto' };
            }
            return { left: `${g.left}px`, top: `${Math.max(12, g.top - hidden)}px`, width: `${g.w}px`, height: `${g.h}px` };
        },
        dragStart(e, which) {
            if (which === 'chat' && (this.isNarrow() || e.target?.closest?.('button'))) return;
            if (e.pointerType === 'mouse' && e.button !== undefined && e.button !== 0) return;
            this.dragging = true; this.dragWhich = which; this.moved = false;
            this.sx = e.clientX; this.sy = e.clientY;
            if (which === 'chat') {
                const g = this.chatGeom();
                if (!g) { this.dragging = false; this.dragWhich = null; return; }
                const p = this.triggerPos();
                this.ox = g.left; this.oy = g.top; this.bx = p.left; this.by = p.top;
            } else {
                const p = this.triggerPos();
                this.ox = p.left; this.oy = p.top;
            }
            e.currentTarget.setPointerCapture?.(e.pointerId);
        },
        dragMove(e, which) {
            if (!this.dragging || this.dragWhich !== which) {
                // Pengaman: pointermove tiba dengan tombol ditekan di atas
                // launcher tetapi dragStart() terlewat — mulai dari sini.
                if (which === 'launcher' && (e.buttons & 1) && e.target?.closest?.('.aiv2-launcher')) this.dragStart(e, which);
                return;
            }
            if (Math.hypot(e.clientX - this.sx, e.clientY - this.sy) > 10) this.moved = true;
            if (!this.moved) return;
            if (which === 'chat') {
                // Gandeng: delta pointer yang sama diterapkan ke launcher —
                // jendela otomatis ikut karena dihitung dari jangkarnya.
                this.pos = this.clamp({ left: this.bx + e.clientX - this.sx, top: this.by + e.clientY - this.sy }, 56, 56);
            } else {
                this.pos = this.clamp({ left: this.ox + e.clientX - this.sx, top: this.oy + e.clientY - this.sy }, 56, 56);
            }
        },
        dragEnd(e, which) {
            if (!this.dragging || this.dragWhich !== which) return;
            this.dragging = false; this.dragWhich = null;
            const dx = (e.clientX ?? this.sx) - this.sx, dy = (e.clientY ?? this.sy) - this.sy;
            if (!this.moved && Math.hypot(dx, dy) <= 10) return;
            // Telan klik sintetis susulan (lambat di HP/WebView) tanpa timer race.
            // Hanya relevan untuk launcher (header tak punya aksi klik).
            if (which === 'launcher') this.suppressUntil = Date.now() + 600;
            try { localStorage.setItem(this.posKey(), JSON.stringify(this.triggerPos())); } catch (err) {}
        },
        dragCancel() { this.dragging = false; this.dragWhich = null; },
        guardClick(e) {
            // Toggle ala komik: klik bubble saat terbuka = minimize.
            // Keyboard Enter/Space (detail 0) selalu toggle; klik habis
            // drag tetap ditelan.
            const toggle = () => { this.open ? this.minimize() : this.openChat(); };
            if (e && e.detail === 0) { toggle(); return; }
            if (this.dragging || this.moved || Date.now() < this.suppressUntil) return;
            toggle();
        },
        newChat() {
            this.messages = []; this.error = ''; this.lastUserText = '';
            this.conversationId = null; this.showList = false;
            try { localStorage.removeItem(this.key()); } catch (e) {}
        },
        scrollBottom() {
            this.$nextTick(() => { const el = this.$refs.msgs; if (el) el.scrollTop = el.scrollHeight; });
        },
        resize() {
            const el = this.$refs.input; if (!el) return;
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 120) + 'px';
        },
        onEnter(e) {
            if (e.isComposing) return;
            if (e.shiftKey) return;
            e.preventDefault(); this.send();
        },
        // Samakan dengan backend cleanMessage(): buang karakter tak
        // terlihat + pangkas spasi unicode di kedua ujung.
        cleanText() {
            return (this.draft || '')
                .replace(/[\u200B-\u200D\u2060\uFEFF\u00AD\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/gu, '')
                .replace(/^[\s\u00A0\u1680\u2000-\u200A\u2028\u2029\u202F\u205F\u3000]+|[\s\u00A0\u1680\u2000-\u200A\u2028\u2029\u202F\u205F\u3000]+$/gu, '')
                .trim();
        },
        canSend() { return this.cleanText().length > 0 && !this.busy && !this.loadingConvo; },
        nowTime() {
            const t = new Date();
            return String(t.getHours()).padStart(2, '0') + ':' + String(t.getMinutes()).padStart(2, '0');
        },
        renderMarkdown(content) {
            try {
                return window.DOMPurify.sanitize(window.marked.parse(content ?? '', { breaks: true }));
            } catch (e) {
                const el = document.createElement('span');
                el.textContent = content ?? '';
                return el.innerHTML;
            }
        },
        sendQuick(text) { if (this.busy || this.loadingConvo) return; this.draft = text; this.send(); },
        retry() { if (this.busy || this.loadingConvo || !this.lastUserText) return; this.draft = this.lastUserText; this.send(); },
        headers() {
            return {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            };
        },
        async send() {
            const text = this.cleanText();
            if (!text || this.busy || this.loadingConvo) return;
            this.draft = ''; this.error = '';
            this.lastUserText = text;
            this.$nextTick(() => this.resize());
            this.messages.push({ id: Date.now(), role: 'user', content: text, time: this.nowTime() });
            this.scrollBottom();
            this.busy = true;
            try {
                const body = new FormData();
                body.append('message', text);
                if (this.conversationId) body.append('conversation_id', this.conversationId);
                const res = await fetch(this.sendUrl, { method: 'POST', body, headers: this.headers() });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || ('HTTP ' + res.status));
                if (data.conversation_id) {
                    this.conversationId = data.conversation_id;
                    try { localStorage.setItem(this.key(), this.conversationId); } catch (e) {}
                }
                if (data.conversation) this.touchConvo(data.conversation);
                this.messages.push({ id: Date.now() + 1, role: 'assistant', content: data.message ?? '', time: this.nowTime() });
            } catch (e) {
                this.error = e?.message || @js(__('Maaf, terjadi masalah saat menghubungi AI. Silakan coba lagi.'));
            } finally {
                this.busy = false;
                this.scrollBottom();
            }
        },
    };
}
</script>
@endpersist
@endauth
