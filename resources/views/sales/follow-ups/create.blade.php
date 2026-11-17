<x-app-layout>
    @php($backUrl = ($returnTo ?? null) === 'my-leads' ? route('sales.my-leads') : route('sales.follow-ups.index'))
    <div>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Add Follow Up') }}</h1>
            <x-back-button :href="$backUrl" :label="__('Back')" />
        </div>

        <div class="bg-[var(--card-bg)] rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            @include('sales.follow-ups._form', [
                'action' => route('sales.follow-ups.store'),
                'submitLabel' => __('Save'),
            ])
        </div>
    </div>
</x-app-layout>
