<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">Product Knowledge Base</h1>
                <p class="text-slate-500 mt-1">{{ __('Import URL produk resmi, review, lalu publish untuk AI.') }}</p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('knowledge-base.index') }}" title="Back" />
        </div>

        {{-- Import form --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700">
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-200">{{ __('Official Product URLs') }}</h2>
            </div>
            <form action="{{ route('product-knowledge.import') }}" method="POST" class="p-6 space-y-4">
                @csrf
                <textarea name="urls" rows="4" required
                          placeholder="https://vendor.com/product/1&#10;https://vendor.com/product/2"
                          class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent bg-white dark:bg-slate-700 dark:border-slate-600 dark:text-slate-100 font-mono text-sm">{{ old('urls') }}</textarea>
                @error('urls')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                <div>
                    <button type="submit"
                            class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl bg-accent-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition-all duration-300 hover:scale-105 hover:bg-accent-500 hover:shadow-lg active:scale-95">
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                        {{ __('Import URLs') }}
                    </button>
                </div>
            </form>
        </div>

        {{-- Batch results --}}
        @if($batchSources->isNotEmpty() || $duplicateRows->isNotEmpty())
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700">
                    <h2 class="text-lg font-bold text-slate-800 dark:text-slate-200">{{ __('Import Results') }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-slate-50 dark:bg-slate-700">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">URL</th>
                                <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Product') }}</th>
                                <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">Brand</th>
                                <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">Model</th>
                                <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                            @foreach($batchSources as $source)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                    <td class="px-6 py-4 text-xs font-mono text-slate-600 dark:text-slate-300 break-all max-w-md">{{ $source->url }}</td>
                                    <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-100">
                                        @if($source->product)
                                            <a href="{{ route('product-knowledge.show', $source->product) }}" class="text-accent-600 hover:text-accent-700">{{ $source->product->displayName() }}</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $source->product?->brand ?? '-' }}</td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $source->product?->model ?? '-' }}</td>
                                    <td class="px-6 py-4"><x-status-badge :color="match($source->status) { 'published' => 'green', 'success' => 'green', 'failed' => 'red', 'needs_review' => 'orange', 'duplicate' => 'slate', default => 'blue' }">{{ ucfirst(str_replace('_', ' ', $source->status)) }}</x-status-badge></td>
                                </tr>
                            @endforeach
                            @foreach($duplicateRows as $source)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                    <td class="px-6 py-4 text-xs font-mono text-slate-600 dark:text-slate-300 break-all max-w-md">{{ $source->url }}</td>
                                    <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-100">
                                        @if($source->product)
                                            <a href="{{ route('product-knowledge.show', $source->product) }}" class="text-accent-600 hover:text-accent-700">{{ $source->product->displayName() }}</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $source->product?->brand ?? '-' }}</td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $source->product?->model ?? '-' }}</td>
                                    <td class="px-6 py-4"><x-status-badge color="slate">Duplicate</x-status-badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Product list --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden"
             x-data="{ selected: [], pageIds: {{ $products->pluck('id')->toJson() }} }"
             x-init="$watch('selected', () => { const el = $refs.selectAll; if (el) el.indeterminate = selected.length > 0 && selected.length < pageIds.length; })">
            <form id="bulk-destroy-form" action="{{ route('product-knowledge.bulk-destroy') }}" method="POST">
                @csrf
                @method('DELETE')
            </form>
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex flex-wrap items-center gap-3">
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-200 mr-auto">{{ __('Products') }}</h2>
                <form method="GET" action="{{ route('product-knowledge.index') }}" class="flex flex-wrap items-center gap-2">
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search brand / model / SKU"
                           class="px-4 py-2 border border-slate-300 rounded-xl text-sm bg-white dark:bg-slate-700 dark:border-slate-600 dark:text-slate-100" />
                    <select name="status" class="rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-3 py-2 text-sm text-slate-700 dark:text-slate-200">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach(['review', 'approved', 'published', 'rejected'] as $st)
                            <option value="{{ $st }}" {{ ($filters['status'] ?? '') === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                        @endforeach
                    </select>
                    <x-icon-button icon="filter" type="submit" title="Filter" />
                    @if(!empty($filters['q']) || !empty($filters['status']) || !empty($filters['category']))
                        <x-icon-button as="a" icon="reset" href="{{ route('product-knowledge.index') }}" title="Reset" />
                    @endif
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50 dark:bg-slate-700">
                        <tr>
                            <th class="w-12 px-4 py-4">
                                <label class="inline-flex cursor-pointer items-center justify-center p-2" title="Select all" aria-label="Select all">
                                    <input type="checkbox" x-ref="selectAll" form="bulk-destroy-form" disabled
                                           :checked="selected.length === pageIds.length && pageIds.length > 0"
                                           @click="selected = ($event.target.checked ? [...pageIds] : [])"
                                           class="h-5 w-5 rounded accent-accent-600" />
                                </label>
                            </th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Product') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">Brand</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Category') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Sources') }}</th>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-600">
                        @forelse($products as $product)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition" :class="selected.includes({{ $product->id }}) && 'bg-accent-50 dark:bg-accent-500/10'">
                                <td class="w-12 px-4 py-4">
                                    <label class="inline-flex cursor-pointer items-center justify-center p-2" title="{{ __('Select') }} {{ $product->displayName() }}" aria-label="{{ __('Select') }} {{ $product->displayName() }}">
                                        <input type="checkbox" name="ids[]" value="{{ $product->id }}" form="bulk-destroy-form" x-model.number="selected"
                                               class="h-5 w-5 rounded accent-accent-600" />
                                    </label>
                                </td>
                                <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-100">
                                    <a href="{{ route('product-knowledge.show', $product) }}" class="text-accent-600 hover:text-accent-700">{{ $product->displayName() }}</a>
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $product->brand ?? '-' }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $product->category ?? '-' }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $product->sources_count }}</td>
                                <td class="px-6 py-4"><x-status-badge :color="match($product->status) { 'published' => 'green', 'approved' => 'blue', 'rejected' => 'red', default => 'orange' }">{{ ucfirst($product->status) }}</x-status-badge></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-16 text-center text-slate-400">{{ __('Belum ada produk. Paste URL resmi di atas.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">{{ $products->links() }}</div>
        </div>

        {{-- Bulk action bar --}}
        <div x-show="selected.length > 0" x-transition
             class="fixed bottom-6 left-1/2 z-40 -translate-x-1/2 pb-[env(safe-area-inset-bottom)]"
             role="toolbar" aria-label="Bulk actions">
            <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-3 shadow-xl dark:border-slate-600 dark:bg-slate-800">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                    <span x-text="selected.length"></span> {{ __('selected') }}
                </span>
                <x-icon-button icon="delete" label="{{ __('Delete') }}" title="Delete selected"
                               x-data="" @click="$dispatch('open-modal', 'confirm-bulk-destroy')" />
                <button type="button" @click="selected = []" title="Clear selection" aria-label="Clear selection"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-300 text-slate-500 hover:bg-white dark:border-slate-600 dark:text-slate-300 transition-all duration-300 hover:scale-105 active:scale-95">
                    <span class="text-lg leading-none">&times;</span>
                </button>
            </div>
        </div>

        <x-modal name="confirm-bulk-destroy" maxWidth="md">
            <div class="p-6">
                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Delete selected products?') }}</h3>
                <p class="text-sm text-slate-500 mt-1">
                    <span x-text="selected.length"></span> {{ __('produk beserta source, dokumen, dan knowledgenya akan dihapus selamanya.') }}
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="$dispatch('close')"
                            class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-white dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700 text-sm font-medium transition-colors duration-200">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" form="bulk-destroy-form"
                            class="group relative overflow-hidden rounded-lg bg-red-50 px-6 py-2.5 text-sm font-medium text-red-600 transition-all duration-300 hover:scale-105 hover:bg-red-100 hover:shadow-md active:scale-95 dark:bg-red-500/15 dark:text-red-400 dark:hover:bg-red-500/25">
                        <x-icon name="trash" class="w-4 h-4" />
                        {{ __('Yes, delete permanently') }}
                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        </x-modal>
    </div>
</x-app-layout>
