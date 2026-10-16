<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Support Engineer Detail') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Project') }}</dt>
                        <dd class="text-gray-900">{{ $projectSupport->project->project_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Engineer') }}</dt>
                        <dd class="text-gray-900">{{ $projectSupport->engineer->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Added On') }}</dt>
                        <dd class="text-gray-900">{{ $projectSupport->created_at->format('d M Y, H:i') }}</dd>
                    </div>
                </dl>

                <div class="mt-6 flex items-center gap-4">
                    <form method="POST" action="{{ route('project-supports.destroy', $projectSupport) }}"
                        onsubmit="return confirm('{{ __('Remove this support engineer from the project?') }}')">
                        @csrf
                        @method('DELETE')
                        <x-icon-button icon="delete" type="submit" label="{{ __('Delete') }}" title="{{ __('Delete') }}" />
                    </form>
                    <x-icon-button as="a" icon="back" href="{{ route('projects.show', $projectSupport->project) }}" title="{{ __('Back to project') }}" />
                </div>
            </div>

        </div>
    </div>
</x-app-layout>