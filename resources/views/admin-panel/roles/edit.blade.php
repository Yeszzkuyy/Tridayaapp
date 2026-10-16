<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Edit Role:') }} {{ $role->name }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Update role name and permissions') }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('admin-panel.index') }}" title="{{ __('Back') }}" />
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-6">
        <form method="POST" action="{{ route('admin-panel.roles.update', $role) }}">
            @csrf @method('PUT')
            
            <div class="mb-6">
                <label for="name" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Role Name') }}</label>
                <input type="text" id="name" name="name" required value="{{ $role->name }}"
                       class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent">
            </div>

            <div class="mb-6">
                <div class="flex items-center justify-between mb-3">
                    <label class="block text-sm font-medium text-slate-700">{{ __('Permissions') }}</label>
                    <div class="flex gap-2">
                        <x-icon-button id="select-all" icon="leads" label="{{ __('Select All') }}" title="{{ __('Select All') }}" />
                        <x-icon-button id="deselect-all" icon="reset" label="{{ __('Clear Selection') }}" title="{{ __('Clear Selection') }}" />
                    </div>
                </div>
                
                <div class="space-y-4">
                    @foreach($permissions as $group => $perms)
                        <div class="border border-slate-200 rounded-xl p-4">
                            <h4 class="font-medium text-slate-800 mb-3 capitalize">{{ $group }}</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                @foreach($perms as $perm)
                                        <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                                        <input type="checkbox" name="permissions[]" value="{{ $perm->name }}"
                                               {{ $role->hasPermissionTo($perm->name) ? 'checked' : '' }}
                                               class="w-4 h-4 rounded border-slate-300 text-accent-600 focus:ring-accent-500">
                                        <span class="text-sm text-slate-700">{{ $perm->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                <a href="{{ route('admin-panel.index') }}"
                   class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                    {{ __('Cancel') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </a>
                <button type="submit"
                        class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                    {{ __('Save Changes') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </button>
            </div>
        </form>
    </div>
</x-app-layout>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('select-all');
    const deselectAll = document.getElementById('deselect-all');
    const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
    
    selectAll.addEventListener('click', () => {
        checkboxes.forEach(cb => cb.checked = true);
    });
    
    deselectAll.addEventListener('click', () => {
        checkboxes.forEach(cb => cb.checked = false);
    });
});
</script>