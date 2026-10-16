<x-app-layout>
    @php
        $initialItems = $requestHarga->items ?? [];
        if (!is_array($initialItems) || count($initialItems) === 0) {
            $initialItems = [[
                'device' => '',
                'quantity' => 1,
                'specification' => '',
            ]];
        }
        $oldItems = old('items');
    @endphp

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Edit Request Harga') }}</h1>
                <p class="text-slate-500 dark:text-slate-400 mt-1">{{ __('Perbarui daftar perangkat dan status') }}</p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('teknisi.request-hargas.show', $requestHarga) }}" title="{{ __('Kembali') }}" />
        </div>

        <form action="{{ route('teknisi.request-hargas.update', $requestHarga) }}" method="POST"
              x-data="{
                  items: @js($oldItems ?? $initialItems),
                  addItem() {
                      this.items.push({ device: '', quantity: 1, specification: '' });
                  },
                  removeItem(index) {
                      if (this.items.length > 1) this.items.splice(index, 1);
                  },
              }"
              class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            @csrf
            @method('PUT')

            {{-- Project --}}
            <div>
                <label for="project_id" class="block text-sm font-medium text-slate-700 mb-1 dark:text-slate-300">{{ __('Project') }}</label>
                <select id="project_id" name="project_id" required
                        class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent bg-white dark:bg-slate-700 dark:border-slate-600 dark:text-slate-100">
                    <option value="">{{ __('Pilih Project') }}</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}"
                                {{ (string) old('project_id', $requestHarga->project_id) === (string) $project->id ? 'selected' : '' }}>
                            {{ $project->project_name }} — {{ $project->customer?->name ?? __('Tanpa customer') }}
                        </option>
                    @endforeach
                </select>
                @error('project_id')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Items --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Items') }}</label>
                    <x-icon-button icon="add" label="{{ __('Tambah Item') }}" title="{{ __('Tambah Item') }}" @click="addItem()" />
                </div>

                <div class="space-y-3">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                            <div class="sm:col-span-5">
                                <label class="block text-xs font-medium text-slate-500 mb-1" x-text="'{{ __('Device #') }}' + (index + 1)"></label>
                                <input type="text" x-model="item.device" name="items[][device]"
                                       placeholder="{{ __('Nama perangkat') }}"
                                       class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent bg-white dark:bg-slate-700 dark:border-slate-600 dark:text-slate-100">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Qty') }}</label>
                                <input type="number" min="1" x-model.number="item.quantity" name="items[][quantity]"
                                       class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent bg-white dark:bg-slate-700 dark:border-slate-600 dark:text-slate-100">
                            </div>
                            <div class="sm:col-span-4">
                                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Spesifikasi') }}</label>
                                <input type="text" x-model="item.specification" name="items[][specification]"
                                       placeholder="{{ __('Opsional') }}"
                                       class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent bg-white dark:bg-slate-700 dark:border-slate-600 dark:text-slate-100">
                            </div>
                            <div class="sm:col-span-1 flex items-end justify-end">
                                <x-icon-button type="button" variant="delete" @click="removeItem(index)" :disabled="items.length <= 1"
                                               class="disabled:opacity-40 disabled:cursor-not-allowed" title="{{ __('Remove') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </x-icon-button>
                            </div>
                        </div>
                    </template>
                </div>
                @error('items')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Notes --}}
            <div>
                <label for="notes" class="block text-sm font-medium text-slate-700 mb-1 dark:text-slate-300">{{ __('Catatan') }}</label>
                <textarea id="notes" name="notes" rows="3"
                          placeholder="{{ __('Catatan tambahan (opsional)') }}"
                          class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent bg-white dark:bg-slate-700 dark:border-slate-600 dark:text-slate-100">{{ old('notes', $requestHarga->notes) }}</textarea>
                @error('notes')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Status --}}
            <div>
                <label for="status" class="block text-sm font-medium text-slate-700 mb-1 dark:text-slate-300">{{ __('Status') }}</label>
                <select id="status" name="status" required
                        class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent bg-white dark:bg-slate-700 dark:border-slate-600 dark:text-slate-100">
                    @foreach(['draft', 'submitted', 'approved', 'rejected', 'completed'] as $status)
                        <option value="{{ $status }}" {{ old('status', $requestHarga->status) === $status ? 'selected' : '' }}>
                            {{ __(ucfirst($status)) }}
                        </option>
                    @endforeach
                </select>
                @error('status')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                <a href="{{ route('teknisi.request-hargas.show', $requestHarga) }}"
                   class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                    {{ __('Batal') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </a>
                <button type="submit"
                        class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                    {{ __('Simpan Perubahan') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </button>
            </div>
        </form>
    </div>
</x-app-layout>