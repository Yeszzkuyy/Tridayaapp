<div x-data="{
        observer: null,
        scrollToBottom(instant = false) {
            this.$nextTick(() => {
                const box = this.$refs.chatBox;
                if (!box) return;
                if (instant) box.scrollTop = box.scrollHeight;
                else box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
            });
        },
        init() {
            this.scrollToBottom(true);
            setTimeout(() => this.scrollToBottom(true), 80);

            const box = this.$refs.chatBox;
            if (box) {
                this.observer = new MutationObserver(() => this.scrollToBottom());
                this.observer.observe(box, { childList: true, subtree: true });
            }
        },
        destroy() { this.observer?.disconnect(); },
    }"
    @price-request-chat-updated.window="scrollToBottom()"
    wire:poll.5s.visible
    class="flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-600 dark:bg-slate-800">
    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-4 dark:border-slate-600 sm:px-5">
        <div>
            <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Discussion') }}</h2>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Sales and Pricing Engineer') }}</p>
        </div>
        <span class="inline-flex items-center gap-2 rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800 dark:bg-green-900/50 dark:text-green-300">
            <span class="h-2 w-2 rounded-full bg-green-600 motion-safe:animate-pulse motion-reduce:animate-none" aria-hidden="true"></span>
            {{ __('Live Discussion') }}
        </span>
    </div>

    <div x-ref="chatBox" role="log" aria-live="polite" aria-relevant="additions"
         aria-label="{{ __('Price Request discussion') }}"
         class="flex h-[420px] max-h-[55vh] flex-col gap-3 overflow-y-auto bg-slate-50 px-3 py-4 dark:bg-slate-900/60 sm:px-4">
        @forelse($comments as $comment)
            @if($comment->type === \App\Models\PriceRequestComment::TYPE_SYSTEM)
                <div class="flex justify-center py-1">
                    <p class="max-w-[90%] rounded-full border border-slate-200 bg-white px-3 py-1.5 text-center text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                        {{ $comment->body }}
                        <time class="ml-1 tabular-nums" datetime="{{ $comment->created_at?->toIso8601String() }}">{{ $comment->created_at?->timezone('Asia/Jakarta')->format('H:i') }}</time>
                    </p>
                </div>
            @else
                @php
                    $mine = (int) $comment->user_id === (int) auth()->id();
                    $commentRoles = $comment->user?->roles?->pluck('name') ?? collect();
                    $roleLabel = match (true) {
                        (int) $comment->user_id === (int) $priceRequest->requested_by => $commentRoles->contains('technician')
                            ? __('Technician')
                            : ($commentRoles->contains('inside-sales') ? __('Inside Sales') : __('Sales')),
                        (int) $comment->user_id === (int) $priceRequest->assigned_to => __('Pricing Engineer'),
                        $commentRoles->contains('management') => __('Management'),
                        $commentRoles->contains('lead-technician') => __('Lead Technician'),
                        default => __('User'),
                    };
                @endphp
                <div class="flex items-end gap-2 {{ $mine ? 'justify-end' : 'justify-start' }}">
                    @unless($mine)
                        <x-user-avatar :user="$comment->user" size="w-8 h-8" text="text-[11px]" :clickable="false" />
                    @endunless

                    <article class="max-w-[88%] rounded-2xl px-3.5 py-2.5 shadow-sm sm:max-w-[78%] {{ $mine
                        ? 'rounded-br-md bg-accent-600 text-white dark:bg-accent-700'
                        : 'rounded-bl-md border border-slate-200 bg-white text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100' }}">
                        <div class="mb-1 flex flex-wrap items-center gap-x-2 gap-y-1 {{ $mine ? 'text-accent-100' : 'text-slate-500 dark:text-slate-400' }}">
                            @if($mine)
                                <span class="text-xs font-semibold">{{ $comment->user?->name ?? __('You') }}</span>
                            @else
                                <span class="text-xs font-semibold">{{ $comment->user?->name ?? __('System') }}</span>
                            @endif
                            <span class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold {{ $mine ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }}">{{ $roleLabel }}</span>
                        </div>
                        <p class="whitespace-pre-wrap break-words text-sm leading-5">{{ $comment->body }}</p>
                        <time class="mt-1 block text-right text-[10px] tabular-nums {{ $mine ? 'text-accent-100' : 'text-slate-400 dark:text-slate-500' }}" datetime="{{ $comment->created_at?->toIso8601String() }}">
                            {{ $comment->created_at?->timezone('Asia/Jakarta')->format('d M, H:i') ?? '-' }}
                        </time>
                    </article>

                    @if($mine)
                        <x-user-avatar :user="$comment->user" size="w-8 h-8" text="text-[11px]" :clickable="false" />
                    @endif
                </div>
            @endif
        @empty
            <div class="m-auto max-w-xs py-8 text-center">
                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-accent-100 text-accent-700 dark:bg-accent-900/70 dark:text-accent-300" aria-hidden="true">
                    <x-icon name="chat" class="h-5 w-5" />
                </span>
                <p class="mt-3 text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('No discussion messages yet.') }}</p>
                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ __('Use this space to clarify pricing, specifications, or revisions.') }}</p>
            </div>
        @endforelse
    </div>

    <form wire:submit="send" class="border-t border-slate-200 bg-white p-3 dark:border-slate-600 dark:bg-slate-800 sm:p-4">
        <label for="price-request-chat-body" class="sr-only">{{ __('Message') }}</label>
        <div class="flex items-end gap-2">
            <textarea id="price-request-chat-body" wire:model="body" rows="1" maxlength="2000"
                      aria-label="{{ __('Message') }}"
                      placeholder="{{ __('Type a message...') }}"
                      class="max-h-28 min-h-10 flex-1 resize-y rounded-xl border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-800 placeholder-slate-400 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"></textarea>
            <x-icon-button type="submit" icon="mail" title="{{ __('Send message') }}" wire:loading.attr="disabled" wire:target="send" />
        </div>
        @error('body')
            <p class="mt-2 text-xs text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
        @enderror
    </form>
</div>
