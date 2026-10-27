@can('manage-marketing')
<div class="wa-settings-section">
    <div class="p-4">
        <p class="text-sm font-semibold" style="color:var(--wa-ink)">{{ __('Bot Settings') }}</p>
        <p class="mt-1 text-xs leading-relaxed" style="color:var(--wa-muted)">{{ __('Greeting & requirement format the bot must dig into every time it replies to a chat.') }}</p>
    </div>
    @foreach($accounts as $account)
    <form method="POST" action="{{ route('whatsapp-center.bot-settings', $account) }}" class="border-t p-4" style="border-color:var(--wa-line)">
        @csrf
        @method('PUT')
        <div class="mb-2 flex items-center justify-between">
            <span class="text-xs font-semibold" style="color:var(--wa-ink)">{{ $account->name }}</span>
            <span class="text-[11px]" style="color:var(--wa-muted)">{{ $account->bot_enabled ? __('Bot active') : __('Bot inactive') }}</span>
        </div>
        <textarea name="bot_instructions" rows="3" maxlength="2000" class="wa-field wa-textarea" placeholder="{{ __('Example: Greet the customer warmly, explore their needs + quantity + location before giving more information.') }}">{{ $account->bot_instructions }}</textarea>
        <div class="mt-2 flex justify-end">
            <button type="submit" class="wa-primary">{{ __('Save') }}</button>
        </div>
    </form>
    @endforeach
</div>
@endcan
