<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">{{ __('Add Follow Up') }}</h1>
                <p class="text-slate-500 mt-1">{{ __('Log a follow-up with a customer.') }}</p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('sales.follow-ups.index') }}" title="Back" />
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            @include('sales.follow-ups._form', [
                'action' => route('sales.follow-ups.store'),
                'submitLabel' => __('Save'),
            ])
        </div>
    </div>
</x-app-layout>
