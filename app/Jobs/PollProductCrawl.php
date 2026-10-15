<?php

namespace App\Jobs;

use App\Models\ProductSource;
use App\Services\Knowledge\FirecrawlService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Polling crawl katalog: selesai -> sebar URL anak; jalan -> tunda ulang.
 */
class PollProductCrawl implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $crawlId,
        public string $batchId,
        public int $originSourceId,
        public int $tries = 0,
    ) {}

    public function handle(FirecrawlService $firecrawl): void
    {
        try {
            $state = $firecrawl->crawlStatus($this->crawlId);
        } catch (Throwable $e) {
            Log::warning('Poll crawl gagal', ['crawl_id' => $this->crawlId, 'error' => $e->getMessage()]);
            $this->failOrigin('Crawl gagal: '.mb_substr($e->getMessage(), 0, 300));

            return;
        }

        $status = strtolower((string) ($state['status'] ?? ''));

        if (in_array($status, ['scraping', 'crawling', 'running', 'pending'], true)) {
            if ($this->tries >= 20) {
                $this->failOrigin('Crawl terlalu lama, coba lagi nanti.');

                return;
            }
            self::dispatch($this->crawlId, $this->batchId, $this->originSourceId, $this->tries + 1)
                ->delay(now()->addMinutes(2));

            return;
        }

        if ($status !== 'completed') {
            $this->failOrigin('Crawl berstatus: '.($status ?: 'unknown'));

            return;
        }

        $host = strtolower((string) parse_url((string) ProductSource::find($this->originSourceId)?->url, PHP_URL_HOST));
        $added = 0;

        foreach ((array) ($state['data'] ?? []) as $page) {
            // Satu halaman = objek scrape {markdown, metadata:{sourceURL}}.
            $url = is_array($page)
                ? ($page['metadata']['sourceURL'] ?? $page['url'] ?? null)
                : null;
            if (!is_string($url) || !str_starts_with($url, 'http')) {
                continue;
            }
            if ($host && strtolower((string) parse_url($url, PHP_URL_HOST)) !== $host) {
                continue; // tetap dalam scope host yang sama
            }
            if (ProductSource::where('url', $url)->exists()) {
                continue;
            }

            $child = ProductSource::create([
                'url' => $url,
                'source_type' => ProductSource::TYPE_PRODUCT_PAGE,
                'status' => ProductSource::STATUS_QUEUED,
                'batch_id' => $this->batchId,
            ]);
            ImportProductSource::dispatch($child->id, 1);
            $added++;
        }

        ProductSource::whereKey($this->originSourceId)->update([
            'source_type' => ProductSource::TYPE_CATALOG,
            'status' => $added > 0 ? ProductSource::STATUS_PROCESSING : ProductSource::STATUS_NEEDS_REVIEW,
            'error' => $added > 0 ? null : 'Crawl selesai tanpa halaman produk yang jelas.',
        ]);
    }

    protected function failOrigin(string $error): void
    {
        ProductSource::whereKey($this->originSourceId)->update([
            'status' => ProductSource::STATUS_FAILED,
            'error' => $error,
        ]);
    }
}
