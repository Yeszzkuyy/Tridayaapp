<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">{{ __('Jadwalkan POC/Demo') }}</h1>
                <p class="text-slate-500 mt-1">{{ __('Catat jadwal proof of concept atau demo produk.') }}</p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('sales.pocs.index') }}" title="{{ __('Kembali') }}" />
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <form action="{{ route('sales.pocs.store') }}" method="POST" data-ajax class="space-y-6">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Customer') }} <span class="text-red-500">*</span></label>
                    <x-searchable-select
                        name="customer_id"
                        :options="$customers->mapWithKeys(fn ($c) => [$c->id => $c->name])->all()"
                        :selected="old('customer_id', $preselectedCustomerId)"
                        placeholder="{{ __('Ketik nama customer untuk mencari...') }}"
                    />
                    @error('customer_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Terkait Lead (opsional)') }}</label>
                    <select name="lead_id"
                            class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        <option value="">{{ __('-- Tidak terkait lead tertentu --') }}</option>
                        @foreach($leads ?? [] as $lead)
                            <option value="{{ $lead->id }}" @selected(old('lead_id', $preselectedLeadId ?? null) == $lead->id)>
                                {{ $lead->customer->name ?? 'Lead #'.$lead->id }} — {{ ucfirst($lead->status) }}
                            </option>
                        @endforeach
                    </select>
                    @error('lead_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tipe') }} <span class="text-red-500">*</span></label>
                    <select name="type" required
                            class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        <option value="">{{ __('-- Pilih tipe --') }}</option>
                        @foreach(\App\Models\Poc::TYPES as $type)
                            <option value="{{ $type }}" @selected(old('type') === $type)>
                                {{ \App\Models\Poc::typeLabel($type) }}
                            </option>
                        @endforeach
                    </select>
                    @error('type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tanggal Jadwal') }}</label>
                    <x-datepicker name="scheduled_date" value="{{ old('scheduled_date', date('Y-m-d')) }}"></x-datepicker>
                    @error('scheduled_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lokasi') }}</label>
                    <input type="text" name="location" value="{{ old('location') }}"
                           placeholder="{{ __('cth: Kantor customer / online via Zoom...') }}"
                           class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    @error('location') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Catatan Hasil') }}</label>
                    <textarea name="result_notes" rows="3"
                              placeholder="{{ __('Hasil POC/Demo, diisi setelah pelaksanaan...') }}"
                              class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('result_notes') }}</textarea>
                    @error('result_notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit"
                            class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                        {{ __('Simpan') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                    <a href="{{ route('sales.pocs.index') }}"
                       class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                        {{ __('Batal') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
