<x-app-layout>
    <div class="max-w-2xl">
        <div class="flex items-center justify-between gap-3 mb-6">
            <h1 class="text-3xl font-bold text-slate-800 dark:text-white">{{ __('Edit Supplier') }}</h1>
            <x-back-button href="{{ route('admin.suppliers.index') }}" />
        </div>
        <form action="{{ route('admin.suppliers.update', $supplier) }}" method="POST" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-4">
            @csrf @method('PUT')
            @include('admin.suppliers._form')
            <div class="flex items-center gap-3 pt-2">
                <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
                <x-secondary-button as="a" href="{{ route('admin.suppliers.index') }}">{{ __('Cancel') }}</x-secondary-button>
            </div>
        </form>
    </div>
</x-app-layout>
