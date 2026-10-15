{{-- Floating 3DY Assistant: maskot + greeting + chat window (Alpine, tanpa dependency baru).
     UI layer di atas POST ai.assistant.send yang sudah ada. Tanpa backend baru. --}}
@auth
<div x-data="aiAssistant(@js(auth()->id()), @js(route('ai.assistant.send')))"
     x-init="init()"
     class="ai-assistant-root"
     @keydown.escape.window="closeChat()">
    {{-- Trigger maskot (draggable) --}}
    <button type="button" x-ref="trigger"
            x-show="!open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-90"
            x-transition:enter-end="opacity-100 scale-100"
            @pointerdown="dragStart($event)"
            @pointermove="dragMove($event)"
            @pointerup="dragEnd($event)"
            @pointercancel="dragCancel()"
            @click.capture.stop="guardClick($event)"
            :style="triggerStyle()"
            :aria-expanded="open"
            aria-label="{{ __('Buka 3DY Assistant') }}"
            class="ai-mascot-btn fixed z-[1000] flex h-14 w-14 touch-none items-center justify-center rounded-full bg-gradient-to-br from-accent-400 via-accent-600 to-accent-700 text-white shadow-xl shadow-accent-600/40 ring-4 ring-accent-500/20 transition-transform duration-200 hover:scale-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-400 focus-visible:ring-offset-2 active:scale-95 motion-reduce:transform-none">
        <svg viewBox="0 0 48 48" class="ai-mascot h-9 w-9" aria-hidden="true">
            <line x1="24" y1="3" x2="24" y2="9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
            <circle cx="24" cy="4" r="2.4" fill="#fbbf24" class="ai-antenna" />
            <rect x="10" y="9" width="28" height="30" rx="10" fill="currentColor" opacity="0.25" />
            <rect x="13.5" y="16" width="21" height="15" rx="7.5" fill="#0f172a" opacity="0.85" />
            <g class="ai-eyes">
                <circle cx="20" cy="22" r="2.2" fill="#fff" />
                <circle cx="28" cy="22" r="2.2" fill="#fff" />
                <circle cx="20.7" cy="21.3" r="0.8" fill="#38bdf8" />
                <circle cx="28.7" cy="21.3" r="0.8" fill="#38bdf8" />
            </g>
            <path d="M20 33.5c1.2 1.4 2.5 2 4 2s2.8-.6 4-2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </svg>
        <span x-show="greeting" class="absolute -right-0.5 -top-0.5 flex h-3.5 w-3.5" aria-hidden="true">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75 motion-reduce:animate-none"></span>
            <span class="relative inline-flex h-3.5 w-3.5 rounded-full border-2 border-white bg-amber-400 dark:border-slate-900"></span>
        </span>
    </button>

    {{-- Greeting bubble --}}
    <div x-show="greeting && !open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         :style="greetingStyle()"
         class="fixed z-[1000] w-[240px] rounded-2xl rounded-br-md border border-slate-200 bg-white px-3.5 py-3 text-sm text-slate-700 shadow-xl dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
         role="status">
        <div class="flex items-center gap-2.5">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-accent-400 to-accent-700 text-white" aria-hidden="true">
                <svg viewBox="0 0 48 48" class="h-5 w-5">
                    <rect x="13.5" y="16" width="21" height="15" rx="7.5" fill="#0f172a" opacity="0.9" />
                    <circle cx="20" cy="22" r="2.4" fill="#fff" />
                    <circle cx="28" cy="22" r="2.4" fill="#fff" />
                    <path d="M20 33.5c1.2 1.4 2.5 2 4 2s2.8-.6 4-2" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                </svg>
            </span>
            <p class="flex-1">{{ __('Hai! Butuh bantuan?') }}</p>
            <button type="button" @click="dismissGreeting()" aria-label="{{ __('Tutup sapaan') }}"
                    class="shrink-0 rounded-md p-0.5 text-slate-400 transition hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-400 dark:hover:text-slate-200">
                <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
            </button>
        </div>
        <button type="button" @click.stop="openChat()"
                class="mt-2.5 w-full rounded-xl bg-accent-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-400">
            {{ __('Mulai chat') }}
        </button>
    </div>

    {{-- Chat window (klik di luar = minimize, tanpa perlu klik logo) --}}
    <div x-show="open"
         @click.outside="minimize()"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         :style="chatStyle()"
         class="fixed z-[1000] flex w-[380px] max-w-[calc(100vw-24px)] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-800"
         role="dialog" aria-modal="false" aria-label="3DY Assistant">
        {{-- Header --}}
        <div class="flex items-center gap-2.5 border-b border-slate-200 bg-gradient-to-r from-accent-700 via-accent-600 to-accent-500 px-4 py-3 text-white dark:border-slate-700">
            <span class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/20" aria-hidden="true">
                <svg viewBox="0 0 48 48" class="h-6 w-6">
                    <rect x="13.5" y="16" width="21" height="15" rx="7.5" fill="#0f172a" opacity="0.9" />
                    <circle cx="20" cy="22" r="2.6" fill="#fff" />
                    <circle cx="28" cy="22" r="2.6" fill="#fff" />
                    <path d="M20 33.5c1.2 1.4 2.5 2 4 2s2.8-.6 4-2" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                </svg>
                <span class="absolute -bottom-0.5 -right-0.5 flex h-3 w-3" aria-hidden="true">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75 motion-reduce:animate-none"></span>
                    <span class="relative inline-flex h-3 w-3 rounded-full border-2 border-accent-600 bg-green-400"></span>
                </span>
            </span>
            <span class="min-w-0 flex-1 leading-tight">
                <span class="block truncate text-sm font-bold">3DY Assistant</span>
                <span class="block text-xs text-white/80">Online • AI Assistant</span>
            </span>
            <button type="button" @click="minimize()" aria-label="{{ __('Minimize chat') }}"
                    class="rounded-lg p-1.5 transition hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M5 12h14" /></svg>
            </button>
            <button type="button" @click="closeChat()" aria-label="{{ __('Tutup chat') }}"
                    class="rounded-lg p-1.5 transition hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
            </button>
        </div>

        {{-- Messages --}}
        <div x-ref="messages" @scroll="onScroll()"
             class="ai-chat-scroll min-h-0 flex-1 space-y-3 overflow-y-auto bg-slate-50 px-4 py-4 dark:bg-slate-900/60"
             aria-live="polite" aria-label="{{ __('Riwayat percakapan') }}">
            <template x-if="!messages.length && !busy">
                <div class="py-6 text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-accent-400 to-accent-700 text-white shadow-lg shadow-accent-600/30" aria-hidden="true">
                        <svg viewBox="0 0 48 48" class="ai-mascot h-7 w-7">
                            <rect x="13.5" y="16" width="21" height="15" rx="7.5" fill="#0f172a" opacity="0.9" />
                            <g class="ai-eyes">
                                <circle cx="20" cy="22" r="2.4" fill="#fff" />
                                <circle cx="28" cy="22" r="2.4" fill="#fff" />
                            </g>
                            <path d="M20 33.5c1.2 1.4 2.5 2 4 2s2.8-.6 4-2" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                        </svg>
                    </span>
                    <p class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('Halo! Saya 3DY Assistant.') }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Ada yang bisa saya bantu?') }}</p>
                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        <template x-for="qa in quickActions" :key="qa">
                            <button type="button" @click="sendQuick(qa)" :disabled="busy"
                                    class="rounded-full border border-accent-500/40 bg-accent-50 px-3 py-1.5 text-xs font-semibold text-accent-700 transition hover:-translate-y-0.5 hover:bg-accent-100 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-400 disabled:opacity-50 dark:bg-accent-500/10 dark:text-accent-300 dark:hover:bg-accent-500/20"
                                    x-text="qa"></button>
                        </template>
                    </div>
                </div>
            </template>
            <template x-for="m in messages" :key="m.id">
                <div :class="m.role === 'user' ? 'flex justify-end' : 'flex items-end justify-start gap-2'"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    <template x-if="m.role !== 'user'">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-accent-400 to-accent-700 text-white" aria-hidden="true">
                            <svg viewBox="0 0 48 48" class="h-[18px] w-[18px]">
                                <rect x="13.5" y="16" width="21" height="15" rx="7.5" fill="#0f172a" opacity="0.9" />
                                <circle cx="20" cy="22" r="2.6" fill="#fff" />
                                <circle cx="28" cy="22" r="2.6" fill="#fff" />
                                <path d="M20 33.5c1.2 1.4 2.5 2 4 2s2.8-.6 4-2" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                            </svg>
                        </span>
                    </template>
                    <div :class="m.role === 'user'
                            ? 'max-w-[80%] rounded-2xl rounded-br-md bg-accent-600 px-3.5 py-2.5 text-sm text-white'
                            : 'max-w-[85%] rounded-2xl rounded-bl-md border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200'">
                        <template x-if="m.role === 'user'">
                            <p class="whitespace-pre-wrap break-words" x-text="m.content"></p>
                        </template>
                        <template x-if="m.role !== 'user'">
                            <div class="ai-md break-words" x-html="renderMarkdown(m.content)"></div>
                        </template>
                        <p class="mt-1 text-right text-[10px] leading-none opacity-60" x-text="m.time"></p>
                    </div>
                </div>
            </template>
            <template x-if="busy">
                <div class="flex items-end justify-start gap-2">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-accent-400 to-accent-700 text-white" aria-hidden="true">
                        <svg viewBox="0 0 48 48" class="h-[18px] w-[18px]">
                            <rect x="13.5" y="16" width="21" height="15" rx="7.5" fill="#0f172a" opacity="0.9" />
                            <circle cx="20" cy="22" r="2.6" fill="#fff" />
                            <circle cx="28" cy="22" r="2.6" fill="#fff" />
                        </svg>
                    </span>
                    <div>
                        <div class="flex items-center gap-1.5 rounded-2xl rounded-bl-md border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-800" aria-label="{{ __('AI sedang mengetik') }}">
                            <span class="ai-dot"></span><span class="ai-dot"></span><span class="ai-dot"></span>
                        </div>
                        <p class="mt-1 text-[10px] text-slate-400 dark:text-slate-500">{{ __('3DY sedang mengetik…') }}</p>
                    </div>
                </div>
            </template>
            <template x-if="error">
                <div class="rounded-xl border border-red-300 bg-red-50 px-3.5 py-2.5 text-xs text-red-700 dark:border-red-800 dark:bg-red-500/10 dark:text-red-300" role="alert">
                    <p x-text="error"></p>
                </div>
            </template>
        </div>

        {{-- Input --}}
        <div class="border-t border-slate-200 bg-white px-3.5 py-3 dark:border-slate-700 dark:bg-slate-800">
            <div class="flex items-end gap-2">
                <textarea x-ref="input" x-model="draft" rows="1" maxlength="2000"
                          @input="resize()" @keydown.enter="onEnter($event)"
                          :placeholder="busy ? '{{ __('Menunggu jawaban...') }}' : '{{ __('Tulis pesan... (Enter kirim, Shift+Enter baris baru)') }}'"
                          aria-label="{{ __('Tulis pesan untuk 3DY Assistant') }}"
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
.ai-mascot { animation: ai-float 4s ease-in-out infinite; transform-origin: center; }
.ai-mascot-btn { animation: ai-glow 3.5s ease-in-out infinite; }
.ai-mascot-btn:hover, .ai-mascot-btn:hover .ai-mascot { animation-play-state: paused; }
.ai-mascot-btn:hover { rotate: 4deg; }
.ai-antenna { animation: ai-antenna-blink 2.8s infinite; }
@keyframes ai-float { 0%,100% { translate: 0 0; scale: 1; } 50% { translate: 0 -3px; scale: 1.03; } }
@keyframes ai-glow { 0%,100% { box-shadow: 0 10px 25px -5px rgb(var(--accent-600) / .35); } 50% { box-shadow: 0 10px 34px -5px rgb(var(--accent-600) / .6); } }
@keyframes ai-antenna-blink { 0%,88%,100% { opacity: 1; } 92%,96% { opacity: 0.25; } }
.ai-eyes { animation: ai-blink 5s infinite; transform-origin: center; transform-box: fill-box; }
@keyframes ai-blink { 0%,93%,100% { transform: scaleY(1); } 95.5% { transform: scaleY(0.08); } }
.ai-dot { width: 7px; height: 7px; border-radius: 9999px; background: rgb(var(--accent-500)); animation: ai-typing 1.2s infinite ease-in-out; }
.ai-dot:nth-child(2) { animation-delay: .15s; } .ai-dot:nth-child(3) { animation-delay: .3s; }
@keyframes ai-typing { 0%,60%,100% { transform: translateY(0); opacity: .5; } 30% { transform: translateY(-4px); opacity: 1; } }
.ai-md { font-size: .875rem; line-height: 1.5; }
.ai-md p { margin: .25rem 0; } .ai-md p:first-child { margin-top: 0; } .ai-md p:last-child { margin-bottom: 0; }
.ai-md ul, .ai-md ol { margin: .25rem 0; padding-left: 1.1rem; } .ai-md ul { list-style: disc; } .ai-md ol { list-style: decimal; }
.ai-md code { font-size: .8em; background: rgb(148 163 184 / .18); border-radius: .3rem; padding: .05rem .3rem; }
.ai-md pre { overflow-x: auto; background: rgb(15 23 42 / .9); color: #e2e8f0; border-radius: .6rem; padding: .6rem .75rem; margin: .4rem 0; font-size: .78rem; }
.ai-md pre code { background: transparent; padding: 0; }
.ai-md a { color: rgb(var(--accent-600)); text-decoration: underline; }
.ai-paused .ai-mascot, .ai-paused .ai-eyes, .ai-paused .ai-dot, .ai-paused .ai-mascot-btn, .ai-paused .ai-antenna { animation-play-state: paused; }
@media (prefers-reduced-motion: reduce) {
    .ai-mascot, .ai-eyes, .ai-dot, .ai-mascot-btn, .ai-antenna { animation: none !important; }
    .ai-mascot-btn:hover { rotate: 0deg; }
}
</style>

<script>
function aiAssistant(uid, sendUrl) {
    const SIZE = 56, MARGIN = 24, CHAT_W = 380, CHAT_H = 560;
    const GREET_EVERY = 5 * 60 * 1000, GREET_FIRST_DELAY = 4000, GREET_TICK = 30000;
    return {
        uid, sendUrl, open: false, busy: false, error: '',
        messages: [], draft: '', greeting: false,
        pos: null, dragging: false, moved: false, suppressUntil: 0, sx: 0, sy: 0, ox: 0, oy: 0,
        quickActions: @js([__('Ringkasan pekerjaan saya'), __('Cari customer'), __('Cek tugas saya')]),
        convKey() { return '3dy.ai.conv.' + this.uid; },
        posKey() { return '3dy.ai.pos.' + this.uid; },
        greetKey() { return '3dy.ai.greetAt.' + this.uid; },
        lastGreet() { try { return +(localStorage.getItem(this.greetKey()) || 0); } catch (e) { return 0; } },
        stampGreet() { try { localStorage.setItem(this.greetKey(), String(Date.now())); } catch (e) {} },
        showGreeting() { if (this.open) return; this.greeting = true; this.stampGreet(); },
        init() {
            try {
                const saved = JSON.parse(sessionStorage.getItem(this.posKey()) || 'null');
                if (saved && typeof saved.left === 'number') this.pos = saved;
            } catch (e) {}
            try { this.convId = localStorage.getItem(this.convKey()) || null; } catch (e) { this.convId = null; }
            // Sapaan pertama setelah jeda singkat; berikutnya tiap 5 menit (X = snooze 5 menit).
            // Guard ganda (__greetT + cek ulang lastGreet): dismiss/open/close di tengah
            // jeda tidak boleh dihidupkan lagi oleh timer yang sudah dipersenjatai.
            clearTimeout(this.__greetT);
            if (Date.now() - this.lastGreet() >= GREET_EVERY) {
                this.__greetT = setTimeout(() => {
                    if (Date.now() - this.lastGreet() >= GREET_EVERY) this.showGreeting();
                }, GREET_FIRST_DELAY);
            }
            // Dedupe global: init bisa jalan ulang habis morph/navigasi Livewire —
            // timer & listener lama dimatikan dulu agar tak menumpuk.
            if (window.__aiGreetTimer) clearInterval(window.__aiGreetTimer);
            window.__aiGreetTimer = setInterval(() => {
                if (document.hidden) return;
                if (Date.now() - this.lastGreet() >= GREET_EVERY) this.showGreeting();
            }, GREET_TICK);
            if (window.__aiVisHandler) document.removeEventListener('visibilitychange', window.__aiVisHandler);
            window.__aiVisHandler = () => {
                document.querySelector('.ai-assistant-root')?.classList.toggle('ai-paused', document.hidden);
            };
            document.addEventListener('visibilitychange', window.__aiVisHandler);
        },
        convId: null,
        triggerPos() {
            const vw = window.innerWidth, vh = window.innerHeight;
            if (this.pos) {
                return {
                    left: Math.min(Math.max(this.pos.left, 8), vw - SIZE - 8),
                    top: Math.min(Math.max(this.pos.top, 8), vh - SIZE - 8),
                };
            }
            return { left: vw - MARGIN - SIZE, top: vh - MARGIN - SIZE };
        },
        triggerStyle() {
            const p = this.triggerPos();
            return `left:${p.left}px;top:${p.top}px;`;
        },
        greetingStyle() {
            const vw = window.innerWidth, GAP = 12, W = 240;
            const p = this.triggerPos();
            if (vw - p.left < W + GAP + 8) {
                return `left:${Math.max(p.left - W - GAP, 8)}px;top:${Math.max(p.top - 64, 8)}px;`;
            }
            return `left:${Math.min(p.left + SIZE + 8, vw - W - 8)}px;top:${Math.max(p.top - 12, 8)}px;`;
        },
        chatStyle() {
            const vw = window.innerWidth, vh = window.innerHeight;
            if (vw < 640) return `left:12px;right:12px;top:64px;bottom:12px;width:auto;`;
            const p = this.triggerPos();
            const w = Math.min(CHAT_W, vw - 24), h = Math.min(CHAT_H, vh - 24);
            const left = (p.left + SIZE + 12 + w <= vw) ? p.left + SIZE + 12
                : Math.max(p.left - 12 - w, 12);
            let top = p.top + SIZE - h;
            top = Math.min(Math.max(top, 12), vh - h - 12);
            return `left:${left}px;top:${top}px;width:${w}px;height:${h}px;`;
        },
        openChat() {
            clearTimeout(this.__greetT);
            this.open = true; this.greeting = false; this.error = '';
            this.$nextTick(() => { this.scrollBottom(false); this.$refs.input?.focus({ preventScroll: true }); });
        },
        minimize() { this.open = false; },
        closeChat() { clearTimeout(this.__greetT); this.open = false; this.greeting = false; this.stampGreet(); },
        dismissGreeting() { clearTimeout(this.__greetT); this.greeting = false; this.stampGreet(); },
        dragStart(e) {
            this.dragging = true; this.moved = false;
            this.sx = e.clientX; this.sy = e.clientY;
            const p = this.triggerPos(); this.ox = p.left; this.oy = p.top;
            e.currentTarget.setPointerCapture?.(e.pointerId);
        },
        dragMove(e) {
            if (!this.dragging) {
                // Pengaman terbukti-runtime: pointermove tiba dengan tombol utama
                // ditekan di atas trigger tetapi dragStart() terlewat (pointerdown
                // tak ter-dispatch ke tombol) — mulai drag dari titik ini agar
                // geser tetap memindah logo dan klik susulannya tetap tertelan.
                // Hover biasa (buttons 0) tetap diabaikan seperti sebelumnya.
                if ((e.buttons & 1) && e.target?.closest?.('.ai-mascot-btn')) this.dragStart(e);
                return;
            }
            if (Math.hypot(e.clientX - this.sx, e.clientY - this.sy) > 10) this.moved = true;
            if (!this.moved) return;
            this.pos = { left: this.ox + e.clientX - this.sx, top: this.oy + e.clientY - this.sy };
        },
        dragEnd(e) {
            if (!this.dragging) return;
            this.dragging = false;
            // Satu-satunya pembuka chat adalah guardClick(). pointerup TIDAK pernah
            // membuka — geser > 10px = drag murni, jangan pernah buka chat.
            // Cek jarak saat rilis agar tetap aman walau pointermove hilang (touch).
            const dx = (e.clientX ?? this.sx) - this.sx, dy = (e.clientY ?? this.sy) - this.sy;
            const dragged = this.moved || Math.hypot(dx, dy) > 10;
            if (!dragged) return;
            // Cap waktu anti klik-sintetis susulan — tanpa timer, jadi tanpa race:
            // klik drag yang datang cepat ATAU lambat (>350ms di HP/WebView) tetap tertelan.
            // moved dibiarkan menempel sampai gesture berikutnya (di-reset di dragStart).
            this.suppressUntil = Date.now() + 600;
            try { sessionStorage.setItem(this.posKey(), JSON.stringify(this.triggerPos())); } catch (err) {}
        },
        dragCancel() { this.dragging = false; },
        guardClick(e) {
            // Keyboard Enter/Space (detail 0, tanpa pointer) selalu boleh membuka —
            // flag basi dari drag sebelumnya tidak boleh memblokirnya.
            if (e && e.detail === 0) { this.openChat(); return; }
            // Telan klik mouse/sentuh apa pun yang tiba setelah drag.
            if (this.dragging || this.moved || Date.now() < this.suppressUntil) return;
            this.openChat();
        },
        onScroll() {},
        scrollBottom() {
            this.$nextTick(() => { const el = this.$refs.messages; if (el) el.scrollTop = el.scrollHeight; });
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
        canSend() { return this.draft.trim().length > 0 && !this.busy; },
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
        sendQuick(text) { this.draft = text; this.send(); },
        headers() {
            return {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            };
        },
        async send() {
            const text = this.draft.trim();
            if (!text || this.busy) return;
            this.draft = ''; this.error = '';
            this.$nextTick(() => this.resize());
            this.messages.push({ id: Date.now(), role: 'user', content: text, time: this.nowTime() });
            this.scrollBottom();
            this.busy = true;
            try {
                const body = new FormData();
                body.append('message', text);
                if (this.convId) body.append('conversation_id', this.convId);
                const res = await fetch(this.sendUrl, { method: 'POST', body, headers: this.headers() });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || ('HTTP ' + res.status));
                if (data.conversation_id) {
                    this.convId = data.conversation_id;
                    try { localStorage.setItem(this.convKey(), this.convId); } catch (e) {}
                }
                this.messages.push({ id: Date.now() + 1, role: 'assistant', content: data.message ?? '', time: this.nowTime() });
            } catch (e) {
                this.error = e?.message || @js(__('Gagal menghubungi AI. Coba lagi.'));
            } finally {
                this.busy = false;
                this.scrollBottom();
            }
        },
    };
}
</script>
@endauth
