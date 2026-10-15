<?php

namespace App\Ai\Tools;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetProductKnowledge implements Tool
{
    public function __construct(public User $user) {}

    public function description(): Stringable|string
    {
        return 'Mencari spesifikasi/informasi produk di Product Knowledge Base (hanya data PUBLISHED dari sumber resmi). '
            .'Parameter query = KATA KUNCI (brand, model, SKU, fitur), BUKAN kalimat tanya. '
            .'Contoh: "Yeastar P560", "DS425+ spesifikasi". '
            .'Parameter brand/model opsional sebagai filter. Read-only.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Kata kunci produk/spesifikasi'),
            'brand' => $schema->string()->nullable()->description('Filter brand'),
            'model' => $schema->string()->nullable()->description('Filter model'),
        ];
    }

    /**
     * Kata kunci alfanumerik tanpa stopwords (ID/EN). Aman untuk to_tsquery.
     */
    protected static function keywords(string $text): array
    {
        preg_match_all('/[a-z0-9]+/', mb_strtolower($text), $m);
        $stop = ['apa', 'yang', 'dan', 'atau', 'untuk', 'dengan', 'dari', 'ini', 'itu',
            'ada', 'adalah', 'bagaimana', 'berapa', 'dimana', 'kapan', 'siapa', 'tidak',
            'saya', 'kami', 'tolong', 'mohon', 'bisa', 'apakah', 'the', 'and', 'for',
            'with', 'what', 'how', 'please'];
        $words = [];
        foreach ($m[0] as $w) {
            if (mb_strlen($w) >= 2 && !in_array($w, $stop, true) && !in_array($w, $words, true)) {
                $words[] = $w;
            }
        }

        return array_slice($words, 0, 10);
    }

    public function handle(Request $request): Stringable|string
    {
        $query = trim((string) ($request['query'] ?? ''));
        if ($query === '') {
            return 'Query kosong.';
        }

        $brand = trim((string) ($request['brand'] ?? ''));
        $model = trim((string) ($request['model'] ?? ''));

        $chunkQuery = KnowledgeChunk::query()
            ->join('knowledge_documents', 'knowledge_documents.id', '=', 'knowledge_chunks.document_id')
            ->join('products', 'products.id', '=', 'knowledge_documents.product_id')
            ->where('knowledge_documents.status', KnowledgeDocument::STATUS_PUBLISHED)
            ->where('products.status', Product::STATUS_PUBLISHED);
        // Pecah pertanyaan menjadi kata kunci: cocok SATU kata saja sudah cukup
        // (AND membuat pertanyaan bahasa alami tak pernah cocok).
        $words = self::keywords($query);
        if ($words === []) {
            return 'Query terlalu umum, sebutkan brand atau model produk.';
        }

        $isPgsql = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql';
        $tsQuery = implode(' | ', $words);

        $chunkQuery->where(fn ($w) => $w
            ->where(function ($ww) use ($words) {
                foreach ($words as $word) {
                    $like = '%' . $word . '%';
                    $ww->orWhere('knowledge_chunks.content', 'like', $like)
                        ->orWhere('products.brand', 'like', $like)
                        ->orWhere('products.name', 'like', $like)
                        ->orWhere('products.model', 'like', $like)
                        ->orWhere('products.sku', 'like', $like);
                }
            })
            ->when($isPgsql && $tsQuery !== '', fn ($q) => $q->orWhereRaw(
                "to_tsvector('simple', knowledge_chunks.content) @@ to_tsquery('simple', ?)",
                [$tsQuery]
            )));

        if ($brand !== '') {
            $chunkQuery->whereRaw('LOWER(products.brand) = ?', [mb_strtolower($brand)]);
        }
        if ($model !== '') {
            $chunkQuery->whereRaw('LOWER(products.model) = ?', [mb_strtolower($model)]);
        }

        if ($isPgsql) {
            $chunkQuery->orderByRaw("ts_rank(to_tsvector('simple', knowledge_chunks.content), to_tsquery('simple', ?)) DESC", [$tsQuery]);
        } else {
            $chunkQuery->latest('knowledge_chunks.id');
        }

        $chunks = $chunkQuery
            ->limit(6)
            ->get([
                'knowledge_chunks.content',
                'products.brand', 'products.name as product_name', 'products.model',
                'products.sku', 'products.category',
                'knowledge_documents.source_url', 'knowledge_documents.version',
            ]);

        if ($chunks->isEmpty()) {
            return 'Tidak ada knowledge produk PUBLISHED yang cocok. Katakan data tidak tersedia, jangan mengarang spesifikasi.';
        }

        return json_encode([
            'results' => $chunks->map(fn ($c) => [
                'brand' => $c->brand,
                'product' => $c->product_name,
                'model' => $c->model,
                'sku' => $c->sku,
                'category' => $c->category,
                'snippet' => mb_substr((string) $c->content, 0, 1200),
                'source_url' => $c->source_url,
                'version' => $c->version,
            ])->all(),
        ], JSON_UNESCAPED_UNICODE);
    }
}
