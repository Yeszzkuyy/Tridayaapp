<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Edit User: {{ $user->name }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Perbarui informasi user dan role') }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('admin-panel.index') }}" title="Back" />
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-6">
        <form method="POST" action="{{ route('admin-panel.users.update', $user) }}">
            @csrf @method('PUT')
            
            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Nama') }}</label>
                <input type="text" id="name" name="name" required value="{{ $user->name }}"
                       class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent">
            </div>

            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input type="email" id="email" name="email" required value="{{ $user->email }}"
                       class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent">
            </div>

            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Password Baru (kosongkan jika tidak diubah)') }}</label>
                <div class="relative">
                    <input type="password" id="password" name="password" minlength="8"
                           class="w-full px-4 py-2 pr-11 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent">
                    <button type="button" data-toggle-password="password"
                            title="{{ __('Show password') }}" aria-label="{{ __('Show password') }}"
                            class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-400 transition hover:text-accent-600">
                        <x-icon name="eye" class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <div class="mb-4">
                <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Konfirmasi Password Baru') }}</label>
                <div class="relative">
                    <input type="password" id="password_confirmation" name="password_confirmation"
                           class="w-full px-4 py-2 pr-11 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent">
                    <button type="button" data-toggle-password="password_confirmation"
                            title="{{ __('Show password') }}" aria-label="{{ __('Show password') }}"
                            class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-400 transition hover:text-accent-600">
                        <x-icon name="eye" class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-700 mb-2">Role(s)</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($roles as $role)
                        <label class="flex items-center gap-3 px-4 py-3 rounded-xl border border-slate-200 cursor-pointer hover:border-accent-400 hover:bg-accent-50 dark:hover:bg-accent-500/10 transition">
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                                   {{ $user->hasRole($role->name) ? 'checked' : '' }}
                                   class="w-4 h-4 rounded border-slate-300 text-accent-600 focus:ring-accent-500">
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium text-slate-800 capitalize">{{ $role->name }}</span>
                                <span class="block text-xs text-slate-500">{{ $role->permissions->count() }} permission</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin-panel.index') }}"
                   class="group relative overflow-hidden px-4 py-2 rounded-xl bg-accent-500 hover:bg-accent-600 text-white transition-all duration-300 hover:scale-105 hover:shadow-lg active:scale-95">
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    {{ __('Batal') }}
                </a>
                <button type="submit"
                        class="group relative overflow-hidden px-4 py-2 bg-accent-600 text-white rounded-xl hover:bg-accent-500 transition-all duration-300 hover:scale-105 hover:shadow-lg hover:shadow-accent-500/40 hover:brightness-110 active:scale-95">
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    {{ __('Simpan Perubahan') }}
                </button>
            </div>
        </form>
    </div>

    <script>
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.togglePassword);
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
        });
    });
    </script>
</x-app-layout>