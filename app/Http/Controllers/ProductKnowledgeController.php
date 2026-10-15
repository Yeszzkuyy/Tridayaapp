<?php

namespace App\Http\Controllers;

use App\Jobs\ImportProductSource;
use App\Models\KnowledgeDocument;
use App\Models\Product;
use App\Models\ProductSource;
use App\Rules\PublicUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductKnowledgeController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('manage-admin');

        $products = Product::withCount('sources')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%'.mb_strtolower(str_replace(['%', '_'], '', (string) $request->string('q'))).'%';
                $query->where(fn ($w) => $w
                    ->whereRaw('LOWER(name) LIKE ?', [$q])
                    ->orWhereRaw('LOWER(brand) LIKE ?', [$q])
                    ->orWhereRaw('LOWER(model) LIKE ?', [$q])
                    ->orWhereRaw('LOWER(sku) LIKE ?', [$q]));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $batchSources = $request->filled('batch')
            ? ProductSource::with('product')->where('batch_id', $request->string('batch'))->latest()->get()
            : collect();

        // URL duplikat: tampilkan baris aslinya (read-only, tanpa ubah apa pun).
        $duplicateUrls = session('duplicateUrls', []);
        $duplicateRows = $duplicateUrls
            ? ProductSource::with('product')->whereIn('url', $duplicateUrls)->latest()->get()
            : collect();

        return view('product-knowledge.index', [
            'products' => $products,
            'batchSources' => $batchSources,
            'duplicateRows' => $duplicateRows,
            'batchId' => (string) $request->string('batch'),
            'filters' => $request->only(['q', 'status', 'category']),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorize('manage-admin');

        $validated = $request->validate([
            'urls' => ['required', 'string', 'max:20000'],
        ]);

        $lines = preg_split('/\R/', (string) $validated['urls']) ?: [];
        $urls = [];
        foreach ($lines as $line) {
            $url = trim($line);
            if ($url !== '' && !in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        $max = (int) config('knowledge.import.max_urls');
        if (count($urls) > $max) {
            return back()->withErrors(['urls' => __('Maksimal :max URL per batch.', ['max' => $max])]);
        }

        $rule = new PublicUrl();
        foreach ($urls as $url) {
            $failed = null;
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $failed = __('URL tidak valid: :url', ['url' => mb_substr($url, 0, 80)]);
            } else {
                $rule->validate('urls', $url, function ($message) use (&$failed) {
                    $failed = $message;
                });
            }
            if ($failed) {
                return back()->withErrors(['urls' => $failed]);
            }
        }

        $batchId = (string) Str::uuid();
        $duplicates = [];

        foreach ($urls as $url) {
            $existing = ProductSource::where('url', $url)->first();

            if ($existing) {
                // Masih antre/diproses (mis. paste ganda se-batch): lewati diam-diam.
                if (!$existing->product_id && in_array($existing->status, [
                    ProductSource::STATUS_QUEUED, ProductSource::STATUS_PROCESSING,
                ], true)) {
                    continue;
                }

                // Pernah gagal: coba lagi, bukan duplicate.
                if ($existing->status === ProductSource::STATUS_FAILED) {
                    $existing->forceFill([
                        'status' => ProductSource::STATUS_QUEUED,
                        'error' => null,
                        'batch_id' => $batchId,
                    ])->save();
                    ImportProductSource::dispatch($existing->id);
                    continue;
                }

                // Sudah beres: catat duplicate untuk laporan saja, baris asli tak disentuh.
                $duplicates[] = $url;
                continue;
            }

            $source = ProductSource::create([
                'url' => $url,
                'source_type' => ProductSource::TYPE_PRODUCT_PAGE,
                'status' => ProductSource::STATUS_QUEUED,
                'batch_id' => $batchId,
            ]);
            ImportProductSource::dispatch($source->id, 0, true);
        }

        return redirect()->route('product-knowledge.index', ['batch' => $batchId])
            ->with('success', __(':count URL masuk antrean import.', ['count' => count($urls)]))
            ->with('duplicateUrls', $duplicates);
    }

    public function show(Product $product)
    {
        $this->authorize('manage-admin');

        $product->load(['sources.latestDocument', 'documents' => fn ($q) => $q->latest('version')]);

        return view('product-knowledge.show', compact('product'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('manage-admin');

        $validated = $request->validate([
            'brand' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:8000'],
        ]);

        $product->forceFill($validated)->save();

        return redirect()->route('product-knowledge.show', $product)
            ->with('success', __('Data produk diperbarui.'));
    }

    public function publish(Product $product): RedirectResponse
    {
        $this->authorize('manage-admin');

        if (!$product->hasClearIdentity()) {
            return back()->withErrors(['product' => __('Identitas produk belum jelas, tetap Needs Review.')]);
        }

        $doc = KnowledgeDocument::where('product_id', $product->id)->latest('version')->first();

        if (!$doc) {
            return back()->withErrors(['product' => __('Belum ada dokumen untuk dipublish.')]);
        }

        $product->forceFill(['status' => Product::STATUS_PUBLISHED])->save();
        $doc->forceFill(['status' => KnowledgeDocument::STATUS_PUBLISHED])->save();

        return redirect()->route('product-knowledge.show', $product)
            ->with('success', __('Produk dipublish ke Knowledge Base.'));
    }

    public function reject(Product $product): RedirectResponse
    {
        $this->authorize('manage-admin');

        $product->forceFill(['status' => Product::STATUS_REJECTED])->save();
        KnowledgeDocument::where('product_id', $product->id)
            ->whereIn('status', [KnowledgeDocument::STATUS_DRAFT])
            ->update(['status' => KnowledgeDocument::STATUS_REJECTED]);

        return redirect()->route('product-knowledge.show', $product)
            ->with('success', __('Produk ditolak, tidak masuk pencarian AI.'));
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->authorize('manage-admin');

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'exists:products,id'],
        ]);

        $count = 0;
        foreach (Product::whereIn('id', $validated['ids'])->get() as $product) {
            foreach ($product->sources()->pluck('id') as $sourceId) {
                ProductSource::whereKey($sourceId)->delete();
            }
            $product->delete();
            $count++;
        }

        return redirect()->route('product-knowledge.index')
            ->with('success', __(':count produk dihapus permanen.', ['count' => $count]));
    }

    public function destroySource(ProductSource $source): RedirectResponse
    {
        $this->authorize('manage-admin');

        $source->delete();

        return back()->with('success', __('Source dihapus permanen.'));
    }

    public function refetch(ProductSource $source): RedirectResponse
    {
        $this->authorize('manage-admin');

        $source->forceFill(['status' => ProductSource::STATUS_QUEUED, 'error' => null])->save();
        ImportProductSource::dispatch($source->id);

        return back()->with('success', __('URL masuk antrean fetch ulang.'));
    }
}
