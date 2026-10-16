<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Kelola Lead:') }} {{ $lead->customer->name ?? 'N/A' }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Isi solusi, progress follow-up, catatan internal, dan assign ke Sales') }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('manage-sales.index') }}" title="Kembali" />
    </div>

    <form action="{{ route('manage-sales.update', $lead) }}" method="POST" data-loading-text="Saving…"
          class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
        @csrf
        @method('PUT')

        {{-- Info Lead --}}
        <section class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-slate-50 dark:bg-slate-700 rounded-xl p-4">
            <div>
                <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('Lead dari PT') }}</div>
                @if($lead->pt_group)
                    <span class="inline-flex px-2 py-0.5 rounded {{ \App\Models\Lead::PT_COLORS[$lead->pt_group] ?? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' }} text-xs font-semibold mt-1">{{ $lead->pt_group }}</span>
                @else
                    <span class="text-slate-400">-</span>
                @endif
            </div>
            <div>
                <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">PIC</div>
                <div class="text-sm text-slate-700 mt-1">{{ $lead->customer->contact_person ?? '-' }}</div>
            </div>
            <div>
                <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('Tanggal Masuk') }}</div>
                <div class="text-sm text-slate-700 mt-1">{{ $lead->incoming_date?->format('d M Y') ?? '-' }}</div>
            </div>
        </section>

        {{-- Detail --}}
        <section class="border-t border-slate-200">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                <div class="md:col-span-2">
                    <label for="kebutuhan" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                        {{ __('Kebutuhan') }}
                    </label>
                    <textarea name="kebutuhan" id="kebutuhan" rows="2"
                              class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('kebutuhan', $lead->kebutuhan) }}</textarea>
                </div>
                <div>
                    <label for="solusi" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                        {{ __('Solusi') }}
                        <x-info-tip tip="{{ __('Solusi yang ditawarkan untuk memenuhi kebutuhan customer.') }}" />
                    </label>
                    <textarea name="solusi" id="solusi" rows="3"
                              placeholder="{{ __('cth: Rekomendasi Cisco Webex Board 55S untuk ruang meeting') }}"
                              class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('solusi', $lead->solusi) }}</textarea>
                </div>
                <div>
                    <label for="progress_notes" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                        {{ __('Progress FollowUp / Keterangan') }}
                        <x-info-tip tip="{{ __('Catatan progress follow-up: sudah dihubungi, jadwal meeting, status negosiasi, dll.') }}" />
                    </label>
                    <textarea name="progress_notes" id="progress_notes" rows="3"
                              placeholder="{{ __('cth: Sudah telepon, menunggu balasan. Follow-up lagi Senin depan.') }}"
                              class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('progress_notes', $lead->progress_notes) }}</textarea>
                </div>
                <div class="md:col-span-2">
                    <label for="notes" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                        {{ __('Catatan Internal') }}
                        <x-info-tip tip="{{ __('Catatan khusus tim, tidak ditampilkan ke customer.') }}" />
                    </label>
                    <textarea name="notes" id="notes" rows="3"
                              placeholder="{{ __('Catatan internal...') }}"
                              class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ old('notes', $lead->notes) }}</textarea>
                </div>
            </div>
        </section>

        {{-- Penugasan --}}
        <section class="border-t border-slate-200">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                <div>
                    <label for="pt_group" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                        {{ __('Lead dari PT') }}
                        <x-info-tip tip="{{ __('Jenama penyedia yang menangani lead ini. Bisa diubah jikalau management menggantinya.') }}" />
                    </label>
                    <x-glide-select name="pt_group" id="pt_group" :label="__('Lead dari PT')" empty-label="— Pilih PT —"
                        :options="collect(\App\Models\Lead::PT_GROUPS)->map(fn ($g) => ['value' => $g, 'label' => $g])->all()"
                        :value="old('pt_group', $lead->pt_group)" :error="$errors->first('pt_group')" />
                </div>
                <div>
                    <label for="assigned_to" class="flex items-center gap-1.5 text-sm font-medium text-slate-700 mb-1">
                        {{ __('Assign / Direct ke Sales') }}
                        <x-info-tip tip="{{ __('Sales yang bertanggung jawab follow-up lead ini.') }}" />
                    </label>
                    <x-glide-select name="assigned_to" id="assigned_to" :label="__('Assign / Direct ke Sales')"
                        :placeholder="__('— Belum di-assign (NEW) —')"
                        :options="$salesUsers->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all()"
                        :value="old('assigned_to', $lead->assigned_to)" :error="$errors->first('assigned_to')" />
                    @if($lead->assignee)
                        <p class="mt-1 text-xs text-slate-500">
                            {{ __('Di-assign ke') }} {{ $lead->assignee->name }}
                            @if($lead->assigned_at) {{ __('pada') }} {{ $lead->assigned_at->format('d M Y H:i') }} @endif
                        </p>
                    @endif
                </div>
            </div>
        </section>

        {{-- Aksi --}}
        <div class="flex justify-end gap-3 border-t border-slate-200">
            <a href="{{ route('manage-sales.index') }}"
               class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                {{ __('Batal') }}
                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
            </a>
            <button type="submit"
                    class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                {{ __('Simpan') }}
                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
            </button>
        </div>
    </form>
</x-app-layout>