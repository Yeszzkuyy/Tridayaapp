<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Tambah User Baru') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Buat akun user dan assign role') }}</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-6">
        <form action="{{ route('admin-panel.users.store') }}" method="POST">
            @csrf
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Nama') }}</label>
                <input type="text" name="name" value="{{ old('name') }}"
                       class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent"
                       required>
                @error('name')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email') }}"
                       class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent"
                       required>
                @error('email')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="user-password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <div class="relative">
                    <input type="password" id="user-password" name="password"
                           class="w-full px-4 py-2 pr-11 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent"
                           required minlength="8">
                    <button type="button" data-toggle-password="user-password"
                            title="{{ __('Show password') }}" aria-label="{{ __('Show password') }}"
                            class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-400 transition hover:text-accent-600">
                        <x-icon name="eye" class="h-5 w-5" />
                    </button>
                </div>
                @error('password')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="user-password-confirmation" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Konfirmasi Password') }}</label>
                <div class="relative">
                    <input type="password" id="user-password-confirmation" name="password_confirmation"
                           class="w-full px-4 py-2 pr-11 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent"
                           required>
                    <button type="button" data-toggle-password="user-password-confirmation"
                            title="{{ __('Show password') }}" aria-label="{{ __('Show password') }}"
                            class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-400 transition hover:text-accent-600">
                        <x-icon name="eye" class="h-5 w-5" />
                    </button>
                </div>
                @error('password_confirmation')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-700 mb-2">Role</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($roles as $role)
                        <label class="flex items-center gap-3 px-4 py-3 rounded-xl border border-slate-200 cursor-pointer hover:border-accent-400 hover:bg-accent-50 dark:hover:bg-accent-500/10 transition">
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                                   @checked(in_array($role->name, old('roles', [])))
                                   class="w-4 h-4 rounded border-slate-300 text-accent-600 focus:ring-accent-500">
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium text-slate-800 capitalize">{{ $role->name }}</span>
                                <span class="block text-xs text-slate-500">{{ $role->permissions->count() }} permission</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                <a href="{{ route('admin-panel.index') }}"
                   class="group relative overflow-hidden px-4 py-2 rounded-xl bg-accent-500 hover:bg-accent-600 text-white transition-all duration-300 hover:scale-105 hover:shadow-lg active:scale-95">
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    {{ __('Batal') }}
                </a>
                <button type="submit"
                        class="group relative overflow-hidden px-4 py-2 bg-accent-600 text-white rounded-xl hover:bg-accent-500 transition-all duration-300 hover:scale-105 hover:shadow-lg hover:shadow-accent-500/40 hover:brightness-110 active:scale-95">
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    {{ __('Simpan User') }}
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