<x-app-layout>
<div class="px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Add Project') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Create a new project for:') }} <span class="font-semibold text-slate-700">{{ $customer->name }}</span></p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('customers.show', $customer) }}" title="{{ __('Back') }}" />
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 w-full">
        <form action="{{ route('projects.store') }}" method="POST">
            @csrf
            <input type="hidden" name="customer_id" value="{{ $customer->id }}">

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Project Name') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Jenis Pekerjaan') }} <span class="text-red-500">*</span></label>
                    <select name="work_type_id" required class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        <option value="">{{ __('Select Work Type') }}</option>
                        @foreach($workTypes as $workType)
                            <option value="{{ $workType->id }}" {{ old('work_type_id') == $workType->id ? 'selected' : '' }}>{{ $workType->name }}</option>
                        @endforeach
                    </select>
                    @error('work_type_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Account Manager') }}</label>
                    <select name="account_manager_id" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        <option value="">{{ __('Select Account Manager') }}</option>
                        @foreach($accountManagers as $am)
                            <option value="{{ $am->id }}" {{ old('account_manager_id') == $am->id ? 'selected' : '' }}>{{ $am->name }}</option>
                        @endforeach
                    </select>
                    @error('account_manager_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- PIC Engineer --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        {{ __('PIC Engineer') }} <span class="text-red-500">*</span>
                    </label>
                    <select name="pic_engineer" required
                            class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        <option value="">{{ __('- Select Technician -') }}</option>
                        @foreach($technicians as $tech)
                            <option value="{{ $tech->name }}" {{ old('pic_engineer') === $tech->name ? 'selected' : '' }}>{{ $tech->name }}</option>
                        @endforeach
                    </select>
                    @error('pic_engineer') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Support Technicians --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        {{ __('Support Technicians') }} <span class="text-slate-400 text-xs">{{ __('(Optional)') }}</span>
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        @foreach($technicians as $tech)
                            <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 cursor-pointer">
                                <input type="checkbox" name="support_technicians[]" value="{{ $tech->name }}"
                                       class="rounded border-slate-300 text-accent-600 focus:ring-accent-500"
                                       {{ in_array($tech->name, (array) old('support_technicians', [])) ? 'checked' : '' }}>
                                {{ $tech->name }}
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-slate-400 mt-1">{{ __('Check the supporting technicians (more than one allowed).') }}</p>
                    @error('support_technicians') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Description') }}</label>
                    <textarea name="description" rows="3" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('description') }}</textarea>
                    @error('description') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit"
                        class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                    {{ __('Save Project') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </button>
                <a href="{{ route('customers.show', $customer) }}"
                   class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                    {{ __('Cancel') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </a>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
