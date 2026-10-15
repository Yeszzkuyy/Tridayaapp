<div wire:poll.5s class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
    <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">{{ __('Diskusi') }} ({{ $comments->count() }})</h3>
    <div class="space-y-3 mb-4 max-h-96 overflow-y-auto pr-1">
        @forelse($comments as $comment)
            @php($mine = (int) $comment->user_id === (int) auth()->id())
            <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[80%] rounded-2xl px-4 py-2.5 {{ $mine ? 'bg-accent-600 text-white rounded-br-md' : 'bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-100 rounded-bl-md' }}">
                    @unless($mine)
                        <p class="text-xs font-semibold text-accent-600 dark:text-accent-300">{{ $comment->user?->name ?? 'System' }}</p>
                    @endunless
                    <p class="text-sm whitespace-pre-wrap">{{ $comment->body }}</p>
                    <p class="mt-1 text-[11px] {{ $mine ? 'text-white/70' : 'text-slate-400' }}">
                        {{ $comment->created_at?->setTimezone('Asia/Jakarta')->format('d M Y H:i') ?? '-' }}
                    </p>
                </div>
            </div>
        @empty
            <p class="text-slate-500">{{ __('Belum ada pesan. Mulai diskusi penawaran di sini.') }}</p>
        @endforelse
    </div>
    <form wire:submit="send" class="flex gap-3">
        <input type="text" wire:model="body" maxlength="2000"
               placeholder="{{ __('Tulis pesan...') }}"
               class="flex-1 rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
        <x-icon-button type="submit" title="Send">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.3 2.7a.6.6 0 0 1 .8-.7L21.4 11a.6.6 0 0 1 0 1L4.1 21a.6.6 0 0 1-.8-.7L6 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 12h9" /></svg>
        </x-icon-button>
    </form>
    @error('body') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
</div>
