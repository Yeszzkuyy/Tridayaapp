<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Add Task') }} — {{ $project->project_name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                <form method="POST" action="{{ route('project-tasks.store', $project) }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700">{{ __('Judul Task') }}</label>
                        <input type="text" name="title" id="title" value="{{ old('title') }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent-500 focus:ring-accent-500">
                        @error('title')
                            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">{{ __('Description') }}</label>
                        <textarea name="description" id="description" rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent-500 focus:ring-accent-500">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="assigned_to" class="block text-sm font-medium text-gray-700">{{ __('Assigned To') }}</label>
                        <select name="assigned_to" id="assigned_to"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent-500 focus:ring-accent-500">
                            <option value="">{{ __('-- Belum Ditentukan --') }}</option>
                            @foreach($engineers as $engineer)
                                <option value="{{ $engineer->id }}" @selected(old('assigned_to') == $engineer->id)>
                                    {{ $engineer->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('assigned_to')
                            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                        @if($engineers->isEmpty())
                            <p class="text-xs text-amber-600 mt-1">{{ __('Belum ada user dengan role engineer/teknisi.') }}</p>
                        @endif
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">{{ __('Status') }}</label>
                        <select name="status" id="status"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent-500 focus:ring-accent-500">
                            <option value="">{{ __('-- Pilih Status --') }}</option>
                            <option value="Pending" @selected(old('status') === 'Pending')>{{ __('Pending') }}</option>
                            <option value="In Progress" @selected(old('status') === 'In Progress')>{{ __('In Progress') }}</option>
                            <option value="Done" @selected(old('status') === 'Done')>{{ __('Done') }}</option>
                            <option value="Cancelled" @selected(old('status') === 'Cancelled')>{{ __('Cancelled') }}</option>
                        </select>
                        @error('status')
                            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700">{{ __('Tanggal Mulai') }}</label>
                            <x-datepicker name="start_date" id="start_date" value="{{ old('start_date') }}"></x-datepicker>
                            @error('start_date')
                                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="due_date" class="block text-sm font-medium text-gray-700">{{ __('Tanggal Selesai (Target)') }}</label>
                            <x-datepicker name="due_date" id="due_date" value="{{ old('due_date') }}"></x-datepicker>
                            @error('due_date')
                                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit"
                            class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                            {{ __('Add Task') }}
                            <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                        </button>
                        <a href="{{ route('projects.show', $project) }}"
                           class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                            {{ __('Cancel') }}
                            <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                        </a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>