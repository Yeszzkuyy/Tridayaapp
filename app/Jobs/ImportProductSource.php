<?php

namespace App\Jobs;

use App\Models\KnowledgeDocument;
use App\Models\Product;
use App\Models\ProductSource;
use App\Services\Knowledge\FirecrawlService;
use App\Services\Knowledge\ProductExtractor;
use App\Services\Knowledge\ProductKnowledgePipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Satu URL = satu job. Gagal di satu URL tidak menghentikan batch.
 */
class ImportProductSource implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $sourceId,
        public int $depth = 0,
        public bool $fresh = false,
    ) {}

    /**
     * Status akhir: jelas -> PUBLISHED otomatis, kecuali produk pernah
     * di-reject admin (jangan hidupkan lagi diam-diam).
     */
    protected function resolveStatus(ProductSource $source, Product $product, ProductKnowledgePipeline $pipeline, array $extracted): void
    {
        if ($product->status === Product::STATUS_REJECTED) {
            $source->forceFill([
                'status' => ProductSource::STATUS_NEEDS_REVIEW,
                'error' => 'Produk terkait pernah ditolak admin.',
            ])->save();

            return;
        }

        if ($product->hasClearIdentity()) {
            $this->publishProduct($source, $product);

            return;
        }

        $issues = $pipeline->identityIssues($extracted);
        $source->forceFill([
            'status' => ProductSource::STATUS_NEEDS_REVIEW,
            'error' => 'Perlu review: '.implode(', ', $issues).' tidak jelas dari halaman.',
        ])->save();
    }

    /**
     * Identitas jelas -> PUBLISHED otomatis (produk + dokumen terbaru + source).
     */
    protected function publishProduct(ProductSource $source, Product $product): void
    {
        $product->forceFill(['status' => Product::STATUS_PUBLISHED])->save();

        KnowledgeDocument::where('source_id', $source->id)
            ->latest('version')->limit(1)
            ->update(['status' => KnowledgeDocument::STATUS_PUBLISHED]);

        $source->forceFill(['status' => ProductSource::STATUS_PUBLISHED])->save();
    }

    /**
     * Katalog: tanpa Product record (jangan gabung banyak model),
     * sebar satu source per halaman produk yang ditemukan.
     */
    protected function importAsCatalog(ProductSource $source, ProductKnowledgePipeline $pipeline, array $links): void
    {
        foreach ($links as $url) {
            if (ProductSource::where('url', $url)->exists()) {
                continue;
            }

            $child = ProductSource::create([
                'url' => $url,
                'source_type' => $pipeline->detectSourceType($url),
                'status' => ProductSource::STATUS_QUEUED,
                'batch_id' => $source->batch_id,
            ]);
            ImportProductSource::dispatch($child->id, 1);
        }

        $source->forceFill([
            'source_type' => ProductSource::TYPE_CATALOG,
            'status' => ProductSource::STATUS_SUCCESS,
            'last_fetched_at' => now(),
        ])->save();
    }

    public function handle(
        FirecrawlService $firecrawl,
        ProductExtractor $extractor,
        ProductKnowledgePipeline $pipeline,
    ): void {
        $source = ProductSource::find($this->sourceId);
        if (!$source) {
            return;
        }

        $source->forceFill(['status' => ProductSource::STATUS_PROCESSING, 'error' => null])->save();

        try {
            $scraped = $firecrawl->scrape($source->url, $this->fresh);
            $markdown = $pipeline->cleanContent($scraped['markdown']);

            // 1 URL bisa = banyak produk: temukan link produk se-host dulu.
            if ($this->depth === 0) {
                $links = $pipeline->discoverProductLinks($source->url, $scraped['links']);

                if (count($links) >= 2) {
                    $this->importAsCatalog($source, $pipeline, $links);

                    return;
                }

                // Konten tak berubah: tanpa produk/dokumen baru (hindari yatim).
                if ($source->content_hash === $pipeline->contentHash($markdown) && $source->product_id) {
                    $source->forceFill(['last_fetched_at' => now()]);
                    $linked = $source->product;
                    if ($linked && $linked->status === Product::STATUS_PUBLISHED) {
                        $source->forceFill(['status' => ProductSource::STATUS_PUBLISHED]);
                    } elseif ($linked && $linked->status === Product::STATUS_REJECTED) {
                        $source->forceFill([
                            'status' => ProductSource::STATUS_NEEDS_REVIEW,
                            'error' => 'Produk terkait pernah ditolak admin.',
                        ]);
                    } else {
                        $source->forceFill(['status' => ProductSource::STATUS_NEEDS_REVIEW]);
                        if (!$source->error) {
                            $source->forceFill(['error' => 'Perlu review: identitas produk belum jelas dari halaman.']);
                        }
                    }
                    $source->save();

                    return;
                }

                // Tak ada link produk tapi konten tipis -> mungkin listing JS -> crawl.
                // Satu link = tetap anggap halaman produk (1 URL = 1 produk).
                if ($links === [] && mb_strlen($markdown) < config('knowledge.firecrawl.listing_threshold')) {
                    $crawlId = $firecrawl->crawlStart($source->url);
                    PollProductCrawl::dispatch($crawlId, (string) $source->batch_id, $source->id)->delay(now()->addMinutes(2));

                    return;
                }
            }

            // Lampirkan datasheet PDF se-host (maks 2) sebagai source tambahan.
            foreach ($pipeline->discoverPdfLinks($source->url, $scraped['links']) as $pdfUrl) {
                if (ProductSource::where('url', $pdfUrl)->exists()) {
                    continue;
                }

                $pdf = ProductSource::create([
                    'url' => $pdfUrl,
                    'source_type' => ProductSource::TYPE_DATASHEET,
                    'status' => ProductSource::STATUS_QUEUED,
                    'batch_id' => $source->batch_id,
                ]);
                self::dispatch($pdf->id, 1);
            }

            $extracted = $extractor->extract($markdown, [
                'title' => $scraped['metadata']['title'] ?? null,
                'url' => $source->url,
            ]);
            [$product, ] = $pipeline->matchOrCreateProduct($extracted);
            $pipeline->ensureDescription($product, $extracted);

            if ($pipeline->storeDocument($source, $product, $extracted, $markdown)) {
                // Konten sama: tanpa versi baru.
                $this->resolveStatus($source, $product, $pipeline, $extracted);

                return;
            }

            $source->forceFill([
                'source_type' => $pipeline->detectSourceType($source->url),
            ])->save();

            $this->resolveStatus($source, $product, $pipeline, $extracted);
        } catch (Throwable $e) {
            Log::warning('Import URL produk gagal', ['source_id' => $source->id, 'error' => $e->getMessage()]);
            $source->forceFill([
                'status' => ProductSource::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 500),
            ])->save();

            if ($this->attempts() < $this->tries) {
                throw $e;
            }
        }
    }
}
