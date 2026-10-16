<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Tambah Role Baru') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Buat role baru dan assign permission') }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('admin-panel.index') }}" title="{{ __('Back') }}" />
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-6">
        <form method="POST" action="{{ route('admin-panel.roles.store') }}">
            @csrf
            
            <div class="mb-6">
                <label for="name" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Nama Role') }}</label>
                <input type="text" id="name" name="name" required
                       class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent"
                       placeholder="{{ __('Contoh: finance, hr, dll') }}">
                <p class="text-xs text-slate-400 mt-1">{{ __('Huruf kecil tanpa spasi, contoh: finance.') }}</p>
            </div>

            <div class="mb-6">
                <div class="flex flex-wrap items-end justify-between gap-3 mb-3">
                    <div>
                        <label for="role-preset" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Salin dari role yang ada') }}</label>
                        <select id="role-preset"
                                class="px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-accent-500">
                            <option value="">{{ __('Pilih role...') }}</option>
                            @foreach($rolePresets as $preset)
                                <option value="{{ $preset->name }}">{{ $preset->name }} ({{ $preset->permissions->count() }} {{ __('permission') }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <x-icon-button id="select-all" icon="leads" label="{{ __('Pilih Semua') }}" title="{{ __('Pilih Semua') }}" />
                        <x-icon-button id="deselect-all" icon="reset" label="{{ __('Batal Pilih') }}" title="{{ __('Batal Pilih') }}" />
                    </div>
                </div>
                <p class="text-xs text-slate-400 mb-3">{{ __('Acuan matriks resmi: management, lead-marketing, marketing, sales, admin, technician, lead-technician, inside-sales, prakerin-technician, prakerin-admin, ceo.') }}</p>
                
                <div class="space-y-4">
                    @foreach($permissions as $group => $perms)
                        <div class="border border-slate-200 rounded-xl p-4">
                            <h4 class="font-medium text-slate-800 mb-3 capitalize">{{ $group }}</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                @foreach($perms as $perm)
                                        <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                                        <input type="checkbox" name="permissions[]" value="{{ $perm->name }}"
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
                    {{ __('Batal') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </a>
                <button type="submit"
                        class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                    {{ __('Simpan Role') }}
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

    // Salin permission dari role resmi yang sudah ada.
    const preset = document.getElementById('role-preset');
    const presetPermissions = @json($rolePresets->mapWithKeys(fn ($r) => [$r->name => $r->permissions->pluck('name')]));
    preset.addEventListener('change', () => {
        const wanted = new Set(presetPermissions[preset.value] || []);
        checkboxes.forEach(cb => { cb.checked = wanted.has(cb.value); });
    });
});
</script>