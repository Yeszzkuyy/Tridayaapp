<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\Product;
use App\Models\ProductSource;
use Illuminate\Support\Facades\DB;

/**
 * Pencocokan produk + versioning dokumen + chunking.
 */
class ProductKnowledgePipeline
{
    /**
     * Cari produk bestaande berdasar SKU, lalu brand+model, lalu brand+name.
     * Tidak cocok -> buat baru (status review).
     *
     * @return array{0:Product,1:bool} [product, isNew]
     */
    public function matchOrCreateProduct(array $extracted): array
    {
        $brand = $extracted['brand'];
        $name = $extracted['name'];
        $model = $extracted['model'];
        $sku = $extracted['sku'];

        $found = null;

        if ($sku) {
            $found = Product::whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)])->first();
        }

        if (!$found && $brand && $model) {
            $found = Product::whereRaw('LOWER(brand) = ?', [mb_strtolower($brand)])
                ->whereRaw('LOWER(model) = ?', [mb_strtolower($model)])
                ->first();
        }

        // Nama saja hanya bila tak ada model: dua model beda = produk beda,
        // walau namanya sama (mis. satu seri, beda tipe).
        if (!$found && $brand && $name && !$model) {
            $found = Product::whereRaw('LOWER(brand) = ?', [mb_strtolower($brand)])
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();
        }

        if ($found) {
            return [$found, false];
        }

        return [Product::create([
            'brand' => $brand,
            'name' => $name ?? 'Produk tanpa nama',
            'model' => $model,
            'sku' => $sku,
            'category' => $extracted['category'],
            'description' => $extracted['description'] ?? $this->buildDescription($extracted, $brand, $name, $model),
            'status' => Product::STATUS_REVIEW,
        ]), true];
    }

    /**
     * Simpan dokumen berversi. Hash sama -> tanpa dokumen baru (true = noop).
     */
    public function storeDocument(ProductSource $source, Product $product, array $extracted, string $markdown): bool
    {
        $hash = $this->contentHash($markdown);

        if ($source->content_hash === $hash) {
            $source->forceFill(['last_fetched_at' => now()])->save();

            return true;
        }

        $content = $this->buildContent($product, $extracted, $markdown);

        DB::transaction(function () use ($source, $product, $extracted, $content, $hash) {
            $version = (int) (KnowledgeDocument::where('source_id', $source->id)->max('version') ?? 0) + 1;

            KnowledgeDocument::where('source_id', $source->id)
                ->whereIn('status', [KnowledgeDocument::STATUS_DRAFT, KnowledgeDocument::STATUS_PUBLISHED])
                ->update(['status' => KnowledgeDocument::STATUS_SUPERSEDED]);

            $doc = KnowledgeDocument::create([
                'source_id' => $source->id,
                'product_id' => $product->id,
                'title' => $product->displayName(),
                'content' => $content,
                'source_url' => $source->url,
                'version' => $version,
                'content_hash' => $hash,
                'status' => KnowledgeDocument::STATUS_DRAFT,
            ]);

            KnowledgeChunk::where('document_id', $doc->id)->delete();
            foreach ($this->chunk($content) as $i => $piece) {
                KnowledgeChunk::create([
                    'document_id' => $doc->id,
                    'chunk_index' => $i,
                    'content' => $piece,
                    'metadata' => [
                        'brand' => $product->brand,
                        'model' => $product->model,
                        'category' => $product->category,
                    ],
                ]);
            }

            $source->forceFill([
                'product_id' => $product->id,
                'content_hash' => $hash,
                'last_fetched_at' => now(),
            ])->save();
        });

        return false;
    }

    /**
     * Susun deskripsi otomatis dari fakta yang ada (bila ekstraksi tak memberi).
     * Murni dari konten: nama/model/brand/kategori + potongan spesifikasi.
     */
    public function buildDescription(array $extracted, ?string $brand, ?string $name, ?string $model): ?string
    {
        $title = trim(collect([$name ?? 'Produk', $model])->filter()->join(' '));
        $head = $title.(filled($brand) ? ' dari '.$brand : '')
            .(filled($extracted['category'] ?? null) ? ' ('.$extracted['category'].')' : '');

        $specs = trim((string) ($extracted['specifications'] ?? ''));
        $body = $specs !== '' ? mb_substr(preg_replace('/\s+/', ' ', $specs) ?? '', 0, 400) : null;

        $text = $body ? $head.'. '.$body : $head.'.';

        return mb_strlen($text) > 20 ? $text : null;
    }

    /**
     * Isi deskripsi yang masih kosong (jangan timpa isi manual/ada).
     */
    public function ensureDescription(Product $product, array $extracted): void
    {
        if (filled($product->description)) {
            return;
        }

        $fallback = $this->buildDescription($extracted, $product->brand, $product->name, $product->model);
        if ($fallback) {
            $product->forceFill(['description' => $fallback])->save();
        }
    }

    /**
     * Temukan link halaman produk dalam satu host: buang diri sendiri,
     * aset, dan halaman non-produk (blog/kontak/bantuan/dll).
     *
     * @return string[]
     */
    public function discoverProductLinks(string $pageUrl, array $links): array
    {
        $host = strtolower((string) parse_url($pageUrl, PHP_URL_HOST));
        $self = rtrim(mb_strtolower($pageUrl), '/');
        $found = [];

        foreach ($links as $link) {
            if (!is_string($link) || !str_starts_with($link, 'http')) {
                continue;
            }
            $linkHost = strtolower((string) parse_url($link, PHP_URL_HOST));
            if ($host === '' || $linkHost !== $host) {
                continue;
            }
            if (rtrim(mb_strtolower($link), '/') === $self) {
                continue;
            }
            $path = mb_strtolower((string) parse_url($link, PHP_URL_PATH));
            if (preg_match('/\.(css|js|png|jpe?g|gif|svg|webp|ico|woff2?|mp4|zip|pdf)(\?|$)/', $path)) {
                continue;
            }
            if (preg_match('#/(blog|news|contact|about|support|help|login|cart|checkout|privacy|terms|sitemap)#', $path)) {
                continue;
            }
            $found[] = strtok($link, '#');
        }

        $found = array_values(array_unique($found));

        return array_slice($found, 0, (int) config('knowledge.firecrawl.crawl_limit'));
    }

    /**
     * Daftar identitas yang kurang jelas (untuk alasan NEEDS_REVIEW).
     */
    public function identityIssues(array $extracted): array
    {
        $issues = [];
        if (!filled($extracted['name'] ?? null)) {
            $issues[] = 'nama produk';
        }
        if (!filled($extracted['model'] ?? null) && !filled($extracted['sku'] ?? null)) {
            $issues[] = 'model/SKU';
        }
        if (!filled($extracted['brand'] ?? null)) {
            $issues[] = 'brand';
        }

        return $issues;
    }

    /**
     * Link PDF se-host (maks 2): datasheet pendamping otomatis.
     *
     * @return string[]
     */
    public function discoverPdfLinks(string $pageUrl, array $links, int $max = 2): array
    {
        $host = strtolower((string) parse_url($pageUrl, PHP_URL_HOST));
        $found = [];

        foreach ($links as $link) {
            if (!is_string($link) || !str_starts_with($link, 'http')) {
                continue;
            }
            if (strtolower((string) parse_url($link, PHP_URL_HOST)) !== $host || $host === '') {
                continue;
            }
            if (!preg_match('/\.pdf([?#]|$)/i', $link)) {
                continue;
            }
            $found[] = strtok($link, '#');
            if (count($found) >= $max) {
                break;
            }
        }

        return array_values(array_unique($found));
    }

    public function detectSourceType(string $url): string
    {
        $lower = mb_strtolower($url);

        if (str_ends_with($lower, '.pdf') || str_contains($lower, 'datasheet')) {
            return ProductSource::TYPE_DATASHEET;
        }

        if (str_contains($lower, 'doc')) {
            return ProductSource::TYPE_DOCUMENTATION;
        }

        return ProductSource::TYPE_PRODUCT_PAGE;
    }

    /**
     * Bersihkan noise navigasi/footer: kosongkan baris tautan/gambar murni.
     */
    public function cleanContent(string $markdown): string
    {
        $lines = preg_split('/\R/', $markdown) ?: [];
        $kept = [];

        // Sisa tembok cookie/privasi yang lolos filter Firecrawl.
        $cookieWall = '/(we value your privacy|we use cookies|cookie policy|privacy statement|accept.*cookies|menyetujui.*cookie)/i';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^(\[.*?\]\(.*?\)\s*)+$/', $line)) {
                continue;
            }
            if (mb_strlen($line) < 200 && preg_match($cookieWall, $line)) {
                continue;
            }
            $kept[] = $line;
        }

        return trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $kept)));
    }

    protected function buildContent(Product $product, array $extracted, string $markdown): string
    {
        $head = collect([
            'Brand: '.($product->brand ?? '-'),
            'Product: '.($product->name ?? '-'),
            'Model: '.($product->model ?? '-'),
            'SKU: '.($product->sku ?? '-'),
            'Category: '.($product->category ?? '-'),
        ])->join("\n");

        $specs = $extracted['specifications'] ? "\nSpecifications:\n".$extracted['specifications'] : '';

        return $head."\n\n".($extracted['description'] ?? '').$specs."\n\n---\n".$markdown;
    }

    protected function normalize(string $markdown): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $markdown)));
    }

    public function contentHash(string $markdown): string
    {
        return hash('sha256', $this->normalize($markdown));
    }

    /**
     * Potong per paragraf mendekati chunk_size + overlap.
     */
    public function chunk(string $content): array
    {
        $size = (int) config('knowledge.import.chunk_size');
        $overlap = (int) config('knowledge.import.chunk_overlap');
        $paras = preg_split('/\n{2,}/', trim($content)) ?: [];
        $chunks = [];
        $current = '';

        foreach ($paras as $para) {
            if (mb_strlen($current) + mb_strlen($para) + 2 > $size && $current !== '') {
                $chunks[] = $current;
                $current = $overlap > 0 ? mb_substr($current, -$overlap)."\n\n" : '';
            }
            $current .= ($current === '' ? '' : "\n\n").$para;
        }

        if (trim($current) !== '') {
            $chunks[] = $current;
        }

        return $chunks ?: [$content];
    }
}
