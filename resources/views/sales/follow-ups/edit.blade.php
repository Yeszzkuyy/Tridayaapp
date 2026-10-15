<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">{{ __('Edit Follow Up') }}</h1>
                <p class="text-slate-500 mt-1">{{ __('Update a customer follow-up.') }}</p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('sales.follow-ups.index') }}" title="Back" />
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            @include('sales.follow-ups._form', [
                'action' => route('sales.follow-ups.update', $followUp),
                'method' => 'PUT',
                'submitLabel' => __('Save'),
            ])
        </div>
    </div>
</x-app-layout>
