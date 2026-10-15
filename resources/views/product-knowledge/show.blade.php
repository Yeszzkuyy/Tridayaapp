<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8 max-w-[1100px] mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">{{ $product->displayName() }}</h1>
                <p class="text-sm text-slate-500 mt-1"><x-status-badge :color="match($product->status) { 'published' => 'green', 'approved' => 'blue', 'rejected' => 'red', default => 'orange' }">{{ ucfirst($product->status) }}</x-status-badge></p>
            </div>
            <div class="flex items-center gap-2">
                @if($product->status !== 'published')
                    @if($product->hasClearIdentity())
                        <form action="{{ route('product-knowledge.publish', $product) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <x-icon-button type="submit" title="Publish">
                                <x-icon name="check-circle" class="h-5 w-5" />
                            </x-icon-button>
                        </form>
                    @endif
                    @if($product->status !== 'rejected')
                        <form action="{{ route('product-knowledge.reject', $product) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <x-icon-button icon="delete" type="submit" title="Reject"
                                           onclick="return confirm('{{ __('Tolak produk ini? Tidak masuk pencarian AI.') }}')" />
                        </form>
                    @endif
                @endif
                <x-icon-button as="a" icon="back" href="{{ route('product-knowledge.index') }}" title="Back" />
            </div>
        </div>

        {{-- Facts --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
                <div><dt class="font-semibold text-slate-500">Brand</dt><dd class="text-slate-800 dark:text-slate-100">{{ $product->brand ?? '-' }}</dd></div>
                <div><dt class="font-semibold text-slate-500">Model</dt><dd class="text-slate-800 dark:text-slate-100">{{ $product->model ?? '-' }}</dd></div>
                <div><dt class="font-semibold text-slate-500">SKU</dt><dd class="text-slate-800 dark:text-slate-100">{{ $product->sku ?? '-' }}</dd></div>
                <div><dt class="font-semibold text-slate-500">{{ __('Category') }}</dt><dd class="text-slate-800 dark:text-slate-100">{{ $product->category ?? '-' }}</dd></div>
            </dl>
            <p class="mt-4 text-sm text-slate-600 dark:text-slate-300 whitespace-pre-wrap">{{ $product->description ?? '-' }}</p>
        </section>

        @if(!in_array($product->status, ['published'], true))
            @php($reasons = $product->sources->where('status', 'needs_review')->pluck('error')->filter()->unique()->values())
            @if($reasons->isNotEmpty())
                <section class="rounded-2xl border border-orange-300 bg-orange-50 p-5 dark:border-orange-700 dark:bg-orange-900/20">
                    <h2 class="text-lg font-bold text-orange-800 dark:text-orange-200">{{ __('Needs Review') }}</h2>
                    <ul class="mt-2 list-disc list-inside text-sm text-orange-700 dark:text-orange-300">
                        @foreach($reasons as $reason)
                            <li>{{ $reason }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100 mb-3">{{ __('Fix data') }}</h2>
                <form action="{{ route('product-knowledge.update', $product) }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @csrf
                    @method('PATCH')
                    @foreach(['brand' => $product->brand, 'name' => $product->name, 'model' => $product->model, 'sku' => $product->sku, 'category' => $product->category] as $field => $value)
                        <label class="block text-sm">
                            <span class="font-semibold text-slate-500">{{ ucfirst($field) }}</span>
                            <input type="text" name="{{ $field }}" value="{{ old($field, $value) }}"
                                   class="mt-1 w-full px-4 py-2 border border-slate-300 rounded-xl bg-white dark:bg-slate-700 dark:border-slate-600 dark:text-slate-100" />
                        </label>
                    @endforeach
                    <label class="block text-sm sm:col-span-2">
                        <span class="font-semibold text-slate-500">{{ __('Description') }}</span>
                        <textarea name="description" rows="3"
                                  class="mt-1 w-full px-4 py-2 border border-slate-300 rounded-xl bg-white dark:bg-slate-700 dark:border-slate-600 dark:text-slate-100">{{ old('description', $product->description) }}</textarea>
                    </label>
                    <div class="sm:col-span-2">
                        <button type="submit"
                                class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl bg-accent-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition-all duration-300 hover:scale-105 hover:bg-accent-500 hover:shadow-lg active:scale-95">
                            <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                            {{ __('Save') }}
                        </button>
                    </div>
                </form>
            </section>
        @endif

        {{-- Sources --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100 mb-3">{{ __('Sources') }}</h2>
            <div class="space-y-3">
                @forelse($product->sources as $source)
                    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-mono text-slate-500 break-all">{{ $source->url }}</p>
                            <p class="text-xs text-slate-400 mt-1">{{ $source->source_type }} · {{ __('fetch') }}: {{ $source->last_fetched_at?->format('d M Y H:i') ?? '-' }} · v{{ $source->latestDocument?->version ?? '-' }}</p>
                            @if($source->error)<p class="text-xs text-red-600 mt-1">{{ $source->error }}</p>@endif
                        </div>
                        <x-status-badge :color="match($source->status) { 'published' => 'green', 'success' => 'green', 'failed' => 'red', 'needs_review' => 'orange', 'duplicate' => 'slate', default => 'blue' }">{{ ucfirst(str_replace('_', ' ', $source->status)) }}</x-status-badge>
                        <form action="{{ route('product-knowledge.sources.destroy', $source) }}" method="POST" onsubmit="return confirm('{{ __('Hapus source ini permanen? Dokumennya ikut terhapus.') }}')">
                            @csrf
                            @method('DELETE')
                            <x-icon-button icon="delete" type="submit" title="Delete source" />
                        </form>
                        <form action="{{ route('product-knowledge.refetch', $source) }}" method="POST">
                            @csrf
                            <x-icon-button type="submit" title="Refetch">
                                <x-icon name="restore" class="h-5 w-5" />
                            </x-icon-button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">-</p>
                @endforelse
            </div>
        </section>

        {{-- Latest document --}}
        @php($doc = $product->documents->first())
        @if($doc)
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100 mb-3">{{ __('Specifications') }} (v{{ $doc->version }} · {{ $doc->status }})</h2>
                <div class="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-wrap max-h-96 overflow-y-auto">{{ $doc->content }}</div>
            </section>
        @endif
    </div>
    </div>
</x-app-layout>
