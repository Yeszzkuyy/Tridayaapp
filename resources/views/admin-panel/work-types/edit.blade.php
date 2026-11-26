<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col gap-3 pt-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-content-primary sm:text-3xl">{{ __('Edit Work Type') }}</h1>
                <p class="mt-1 text-sm text-content-secondary">{{ __('Edit:') }} <span class="font-semibold text-content-secondary">{{ $workType->name }}</span></p>
            </div>
            <x-back-button href="{{ route('admin-panel.work-types.index') }}" />
        </div>

        <div class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-600 dark:bg-slate-800">
            <form action="{{ route('admin-panel.work-types.update', $workType) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-content-secondary mb-1">{{ __('Work Type Name') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $workType->name) }}" required
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex gap-3">
                    <x-primary-button>{{ __('Update') }}</x-primary-button>
                    <x-secondary-button as="a" href="{{ route('admin-panel.work-types.index') }}">{{ __('Cancel') }}</x-secondary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>