<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Edit User:') }} {{ $user->name }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Update user information and role') }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('admin-panel.index') }}" title="{{ __('Back') }}" />
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-6">
        <form method="POST" action="{{ route('admin-panel.users.update', $user) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            
            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Name') }}</label>
                <input type="text" id="name" name="name" required value="{{ $user->name }}"
                       class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent">
            </div>

            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Email') }}</label>
                <input type="email" id="email" name="email" required value="{{ $user->email }}"
                       class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent">
            </div>

            <div class="mb-4">
                <label for="phone" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Phone / WhatsApp') }}</label>
                <input type="tel" id="phone" name="phone" maxlength="30" value="{{ old('phone', $user->phone) }}" placeholder="08xxxxxxxxxx"
                       class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent">
                @error('phone') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label for="signature" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Digital Signature (PNG, transparent background)') }}</label>
                <p class="mb-2 text-xs text-slate-500">{{ __('Used automatically in the signature block of the official quotation PDF.') }}</p>
                @if($user->signature_path)
                    <div class="mb-3 flex flex-wrap items-center gap-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <img src="{{ asset('storage/'.$user->signature_path) }}" alt="{{ __('Digital Signature') }}" class="h-20 w-auto object-contain">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="remove_signature" value="1" class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                            {{ __('Remove current signature') }}
                        </label>
                    </div>
                @endif
                <input type="file" id="signature" name="signature" accept="image/png,image/webp"
                       class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-accent-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-accent-700">
                @error('signature') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-slate-700 mb-1">{{ __('New Password (leave blank if unchanged)') }}</label>
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
                <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Confirm New Password') }}</label>
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
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ __('Role(s)') }}</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($roles as $role)
                        <label class="flex items-center gap-3 px-4 py-3 rounded-xl border border-slate-200 cursor-pointer hover:border-accent-400 hover:bg-accent-50 dark:hover:bg-accent-500/10 transition">
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                                   {{ $user->hasRole($role->name) ? 'checked' : '' }}
                                   class="w-4 h-4 rounded border-slate-300 text-accent-600 focus:ring-accent-500">
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium text-slate-800 capitalize">{{ __(\Illuminate\Support\Str::headline($role->name)) }}</span>
                                <span class="block text-xs text-slate-500">{{ $role->permissions->count() }} {{ __('permission') }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-3">
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