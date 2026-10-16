<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div><h1 class="text-3xl font-bold text-slate-800">{{ __('Buat Purchase Order') }}</h1><p class="text-slate-500 mt-1">{{ __('Buat PO baru untuk customer.') }}</p></div>
            <x-icon-button as="a" icon="back" href="{{ route('admin.pos.index') }}" title="{{ __('Kembali') }}" />
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <form action="{{ route('admin.pos.store') }}" method="POST" class="space-y-6">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Customer') }} <span class="text-red-500">*</span></label>
                    <x-searchable-select
                        name="customer_id"
                        :options="$customers->mapWithKeys(fn ($c) => [$c->id => $c->name])->all()"
                        :selected="old('customer_id')"
                        placeholder="{{ __('Cari & pilih customer...') }}"
                    />
                    @error('customer_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Proyek Terkait') }}</label>
                    <select name="project_id" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500"
                            x-data="{ customerId: '' }" @searchable-select:changed.window="customerId = $event.detail.id; $el.value = ''">
                        <option value="">{{ __('-- Tidak terkait proyek --') }}</option>
                        @foreach($projects as $p) <option value="{{ $p->id }}" data-customer="{{ $p->customer_id }}"
                                x-show="!customerId || customerId == '{{ $p->customer_id }}'"
                                {{ old('project_id') == $p->id ? 'selected' : '' }}>{{ $p->project_name }}</option> @endforeach
                    </select>
                    @error('project_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Item') }} <span class="text-red-500">*</span></label>
                    <textarea name="items" rows="4" required placeholder="{{ __('Deskripsi barang/jasa yang dipesan...') }}" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('items') }}</textarea>
                    @error('items') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Nominal (Rp)') }} <span class="text-red-500">*</span></label>
                        <input type="number" name="amount" value="{{ old('amount') }}" required step="0.01" min="0" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @error('amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Status') }}</label>
                        <select name="status" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                            @foreach($statuses as $val => $label) <option value="{{ $val }}" {{ old('status', 'draft') == $val ? 'selected' : '' }}>{{ $label }}</option> @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tanggal Terbit') }} <span class="text-red-500">*</span></label>
                    <x-datepicker name="issue_date" required value="{{ old('issue_date', date('Y-m-d')) }}"></x-datepicker>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Catatan') }}</label>
                    <textarea name="notes" rows="2" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('notes') }}</textarea>
                </div>
                <div class="flex gap-3">
                    <button type="submit"
                            class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Simpan') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                    <a href="{{ route('admin.pos.index') }}"
                       class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                        {{ __('Batal') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
