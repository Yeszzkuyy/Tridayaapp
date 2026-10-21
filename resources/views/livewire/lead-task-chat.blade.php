<div x-data="{
        scrollToBottom(instant = false) {
            this.$nextTick(() => {
                const box = this.$refs.chatBox;
                if (!box) return;
                if (instant) {
                    box.scrollTop = box.scrollHeight;
                } else {
                    box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
                }
            });
        },
        init() {
            // Immediate scroll down to bottom on page load
            this.scrollToBottom(true);
            setTimeout(() => this.scrollToBottom(true), 60);
            setTimeout(() => this.scrollToBottom(true), 250);

            // Auto-scroll down whenever any new messages arrive (MutationObserver)
            if (this.$refs.chatBox) {
                const observer = new MutationObserver(() => {
                    this.scrollToBottom();
                });
                observer.observe(this.$refs.chatBox, { childList: true, subtree: true });
            }
        }
     }"
     @chat-updated.window="scrollToBottom()"
     wire:poll.5s
     class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">{{ __('Discussion') }} ({{ $comments->count() }})</h3>
        <span class="text-xs text-slate-400 flex items-center gap-1.5">
            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
            {{ __('Live Discussion') }}
        </span>
    </div>

    {{-- Chat Messages Container: WhatsApp flow (oldest at top, newest at bottom, auto-scrolled down) --}}
    <div x-ref="chatBox" class="flex flex-col space-y-3 mb-4 max-h-[460px] overflow-y-auto p-4 rounded-2xl bg-slate-100/70 dark:bg-slate-900/50 border border-slate-200/60 dark:border-slate-700/60">
        @forelse($comments as $comment)
            @php($mine = (int) $comment->user_id === (int) auth()->id())
            <div class="flex {{ $mine ? 'justify-end' : 'justify-start items-end gap-2' }}">
                @unless($mine)
                    <div class="shrink-0 mb-1">
                        <x-user-avatar :user="$comment->user" size="w-7 h-7" text="text-[10px]" :clickable="false" />
                    </div>
                @endunless

                <div class="max-w-[80%] sm:max-w-[70%] rounded-2xl px-4 py-2.5 shadow-sm {{ $mine
                    ? 'bg-emerald-600 dark:bg-emerald-700 text-white rounded-tr-xs'
                    : 'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 rounded-tl-xs border border-slate-200/80 dark:border-slate-700' }}">
                    @unless($mine)
                        <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-400 mb-0.5">
                            {{ $comment->user?->name ?? __('System') }}
                        </p>
                    @endunless

                    <p class="text-sm leading-relaxed whitespace-pre-wrap break-words">{{ $comment->body }}</p>

                    <div class="mt-1 flex items-center justify-end gap-1 text-[10px] {{ $mine ? 'text-emerald-100' : 'text-slate-400 dark:text-slate-500' }}">
                        <span>{{ $comment->created_at?->setTimezone('Asia/Jakarta')->format('H:i') ?? '-' }}</span>
                        @if($mine)
                            <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor">
                                <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/>
                                <path d="M10.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 1 1 .708-.708l.146.147 6.646-6.647a.5.5 0 0 1 .708 0z"/>
                            </svg>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="py-12 text-center text-slate-400">
                <p class="text-sm">{{ __('No messages yet. Start the quotation discussion here.') }}</p>
            </div>
        @endforelse
    </div>

    {{-- Chat Input Form --}}
    <form wire:submit="send" @submit="$nextTick(() => scrollToBottom())" class="flex items-center gap-2 pt-2">
        <div class="relative flex-1">
            <input type="text" wire:model="body" maxlength="2000"
                   placeholder="{{ __('Type a message...') }}"
                   class="w-full rounded-2xl border border-slate-300 bg-slate-50/50 px-4 py-2.5 text-sm placeholder-slate-400 transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-slate-600 dark:bg-slate-700/50 dark:text-slate-100 dark:focus:bg-slate-800">
        </div>
        <button type="submit"
                class="group relative inline-flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-500/20 transition-all duration-200 hover:bg-emerald-700 hover:shadow-md hover:scale-105 active:scale-95">
            <svg class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
            </svg>
        </button>
    </form>
    @error('body') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
</div>
