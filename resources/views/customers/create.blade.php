<x-app-layout>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
        <div class="min-w-0">
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-800">
                {{ __('Add Customer') }}
            </h1>
            <p class="text-slate-500 mt-1">
                {{ __('Record a new company / customer into Tridaya App.') }}
            </p>
        </div>
        <div class="sm:self-start">
            <x-icon-button as="a" icon="back" size="lg" href="{{ route('customers.index') }}" title="{{ __('Back') }}" />
        </div>
    </div>

    <form action="{{ route('customers.store') }}" method="POST" enctype="multipart/form-data"
          data-loading-text="Saving…"
          class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-4 w-full">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
                {{ __('Company Name') }} <span class="text-red-500">*</span>
            </label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required
                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
            @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="pt_group" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
                {{ __('Customers from Company') }} <span class="text-red-500">*</span>
            </label>
            @if(count($ptGroups) === 1)
                {{-- Sales terkunci ke company-nya: PT tidak bisa dipindah --}}
                <input type="hidden" name="pt_group" value="{{ old('pt_group', $ptGroups[0]) }}">
                <div class="flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-200">
                    <x-icon name="building" class="h-4 w-4 text-slate-400" />
                    {{ $ptGroups[0] }}
                </div>
                @error('pt_group') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            @else
                <x-glide-select name="pt_group" :label="__('Customers from Company')" required
                    :options="collect($ptGroups)->all()"
                    :value="old('pt_group', '')"
                    :placeholder="__('Pilih Company')" :error="$errors->first('pt_group')" />
            @endif
        </div>

        <div>
            <label for="address" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
                {{ __('Address') }}
            </label>
            <textarea id="address" name="address" rows="3"
                      class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('address') }}</textarea>
            @error('address') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="billing_address" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
                    {{ __('Billing Address') }}
                </label>
                <textarea id="billing_address" name="billing_address" rows="3"
                          placeholder="{{ __('Leave empty to use the main address') }}"
                          class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('billing_address') }}</textarea>
                @error('billing_address') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="shipping_address" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
                    {{ __('Shipping Address') }}
                </label>
                <textarea id="shipping_address" name="shipping_address" rows="3"
                          placeholder="{{ __('Leave empty to use the main address') }}"
                          class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('shipping_address') }}</textarea>
                @error('shipping_address') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 dark:border-slate-600 p-4 space-y-3">
            <div>
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('PIC') }}</p>
                <p class="text-xs text-slate-500">{{ __('Primary') }}</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="pic_name" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Name') }}</label>
                    <input type="text" id="pic_name" name="pic_name" value="{{ old('pic_name') }}"
                           placeholder="{{ __('Person / PIC name') }}"
                           class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    @error('pic_name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="pic_position" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Position') }}</label>
                    <input type="text" id="pic_position" name="pic_position" value="{{ old('pic_position') }}"
                           class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    @error('pic_position') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="pic_phone" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Phone') }}</label>
                    <input type="text" id="pic_phone" name="pic_phone" value="{{ old('pic_phone') }}"
                           class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    @error('pic_phone') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="pic_whatsapp" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('WhatsApp') }}</label>
                    <input type="text" id="pic_whatsapp" name="pic_whatsapp" value="{{ old('pic_whatsapp') }}"
                           class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    @error('pic_whatsapp') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="pic_email" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Email') }}</label>
                    <input type="email" id="pic_email" name="pic_email" value="{{ old('pic_email') }}"
                           class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    @error('pic_email') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 dark:border-slate-600 p-4 space-y-3">
            <div>
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('Finance PIC') }}</p>
                <p class="text-xs text-slate-500">{{ __('Contact person for invoices and billing follow-up.') }}</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="finance_pic_name" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Name') }}</label>
                    <input type="text" id="finance_pic_name" name="finance_pic_name" value="{{ old('finance_pic_name') }}"
                           class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    @error('finance_pic_name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="finance_pic_phone" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Phone / WhatsApp') }}</label>
                    <input type="text" id="finance_pic_phone" name="finance_pic_phone" value="{{ old('finance_pic_phone') }}"
                           class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    @error('finance_pic_phone') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="finance_pic_email" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Email') }}</label>
                    <input type="email" id="finance_pic_email" name="finance_pic_email" value="{{ old('finance_pic_email') }}"
                           class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    @error('finance_pic_email') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div>
            <label for="phone" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
                {{ __('Company Phone No.') }}
            </label>
            <input type="text" id="phone" name="phone" value="{{ old('phone') }}"
                   placeholder="{{ __('e.g. 021-1234-5678') }}"
                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
            @error('phone') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="whatsapp" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
                {{ __('Company WhatsApp No.') }}
            </label>
            <input type="text" id="whatsapp" name="whatsapp" value="{{ old('whatsapp') }}"
                   placeholder="{{ __('e.g. 0812-3456-7890') }}"
                   inputmode="tel"
                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
            @error('whatsapp') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
                {{ __('Email') }}
            </label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
            @error('email') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="npwp" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
                    {{ __('NPWP') }}
                </label>
                <input type="text" id="npwp" name="npwp" value="{{ old('npwp') }}" placeholder="{{ __('e.g. 01.234.567.8-901.234') }}"
                       class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                @error('npwp') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="npwp_document" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
                    {{ __('NPWP Document') }}
                </label>
                <input type="file" id="npwp_document" name="npwp_document" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                       class="w-full text-sm text-slate-500 file:mr-2 file:py-2 file:px-3 file:rounded-xl file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                @error('npwp_document') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="notes" class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
                {{ __('Notes') }}
            </label>
            <textarea id="notes" name="notes" rows="3"
                      class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('notes') }}</textarea>
            @error('notes') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 border-t border-slate-200 dark:border-slate-600 pt-5">
            <a href="{{ route('customers.index') }}"
               class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                {{ __('Cancel') }}
                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
            </a>
            <button type="submit"
                    class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                {{ __('Save') }}
                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
            </button>
        </div>
    </form>
</x-app-layout>
