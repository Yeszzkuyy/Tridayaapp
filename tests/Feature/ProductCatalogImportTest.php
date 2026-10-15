<?php

namespace Tests\Feature;

use App\Jobs\ImportProductSource;
use App\Jobs\PollProductCrawl;
use App\Models\Product;
use App\Models\ProductSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductCatalogImportTest extends TestCase
{
    use RefreshDatabase;

    private string $listingMarkdown = '';
    /** @var array<string,string> url => markdown */
    private array $pages = [];
    /** @var array halaman crawl */
    private array $crawlPages = [];
    private bool $crawlEmpty = false;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('knowledge.firecrawl.key', 'test-key');
        config()->set('ai.providers.openrouter.key', 'test-key');
    }

    private function admin(): User
    {
        Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        $u = User::factory()->create();
        $u->assignRole('super-admin');

        return $u;
    }

    private function fakeAll(): void
    {
        Http::fake([
            'api.firecrawl.dev/v2/scrape' => function ($request) {
                $url = $request->data()['url'] ?? '';
                $md = $this->pages[$url] ?? $this->listingMarkdown;
                $links = $url === 'https://www.yeastar.com/ip-pbx/' || $url === 'https://vendor.com/kosong'
                    ? array_values(array_filter(array_keys($this->pages), fn ($u) => str_starts_with($u, 'https://www.yeastar.com/')))
                    : [];
                if ($url === 'https://vendor.com/kosong') {
                    $links = [];
                    $md = 'tipis';
                }

                return Http::response(['success' => true, 'data' => ['markdown' => $md, 'metadata' => [], 'links' => $links]]);
            },
            'api.firecrawl.dev/v2/crawl' => Http::response(['id' => 'crawl-1']),
            'api.firecrawl.dev/v2/crawl/*' => Http::response([
                'status' => 'completed',
                'data' => $this->crawlEmpty ? [] : array_map(
                    fn ($u) => ['metadata' => ['sourceURL' => $u]],
                    $this->crawlPages
                ),
            ]),
            'openrouter.ai/*' => function ($request) {
                $content = json_encode($request->data()['messages'] ?? '');
                preg_match('/MODEL:([A-Z0-9]+)/', $content, $m);
                $model = $m[1] ?? null;

                return Http::response(['choices' => [['message' => ['content' => json_encode([
                    'brand' => $model ? 'Yeastar' : null,
                    'name' => $model ? 'P-Series' : null,
                    'model' => $model,
                    'sku' => null,
                    'category' => $model ? 'IP PBX' : null,
                    'description' => $model ? 'IP PBX.' : null,
                    'specifications' => $model ? 'Spec '.$model : null,
                ])]]]]);
            },
        ]);
    }

    private function runSource(string $url): ProductSource
    {
        $source = ProductSource::create([
            'url' => $url, 'status' => ProductSource::STATUS_QUEUED, 'batch_id' => 'batch-kb',
        ]);
        try {
            ImportProductSource::dispatchSync($source->id);
        } catch (\Throwable $e) {
            // status FAILED sudah tercatat job
        }

        return $source->fresh();
    }

    public function test_catalog_creates_one_source_per_product(): void
    {
        $this->actingAs($this->admin());
        $this->pages = [
            'https://www.yeastar.com/ip-pbx/p520/' => 'MODEL:P520 IP PBX kecil. '.str_repeat('konten resmi. ', 60),
            'https://www.yeastar.com/ip-pbx/p550/' => 'MODEL:P550 IP PBX menengah. '.str_repeat('konten resmi. ', 60),
            'https://www.yeastar.com/ip-pbx/p560/' => 'MODEL:P560 IP PBX besar. '.str_repeat('konten resmi. ', 60),
            'https://www.yeastar.com/ip-pbx/p570/' => 'MODEL:P570 IP PBX enterprise. '.str_repeat('konten resmi. ', 60),
        ];
        $this->listingMarkdown = 'Yeastar IP PBX lineup.';
        $this->fakeAll();

        $parent = $this->runSource('https://www.yeastar.com/ip-pbx/');

        // Induk = katalog, tanpa Product multi-model.
        $this->assertEquals(ProductSource::TYPE_CATALOG, $parent->source_type);
        $this->assertEquals(ProductSource::STATUS_SUCCESS, $parent->status);
        $this->assertNull($parent->product_id);

        $children = ProductSource::where('id', '!=', $parent->id)->get();
        $this->assertEquals(4, $children->count());

        // Antrean sync: anak langsung diproses -> 4 produk, masing-masing 1 model.
        // Tanpa Product gabungan multi-model.
        $this->assertEquals(4, Product::count());
        // Anak valid langsung PUBLISHED otomatis.
        $this->assertEquals(4, Product::where('status', 'published')->count());
        $this->assertEquals(
            ['P520', 'P550', 'P560', 'P570'],
            Product::orderBy('model')->pluck('model')->all()
        );
    }

    public function test_catalog_skips_already_imported_source(): void
    {
        $this->actingAs($this->admin());
        $this->pages = [
            'https://www.yeastar.com/ip-pbx/p520/' => 'MODEL:P520 kecil. '.str_repeat('konten resmi. ', 60),
            'https://www.yeastar.com/ip-pbx/p550/' => 'MODEL:P550 menengah. '.str_repeat('konten resmi. ', 60),
        ];
        $this->listingMarkdown = 'Lineup.';
        $this->fakeAll();

        // P520 sudah diimport lebih dulu.
        ProductSource::create(['url' => 'https://www.yeastar.com/ip-pbx/p520/', 'status' => ProductSource::STATUS_SUCCESS, 'batch_id' => 'old']);

        $parent = $this->runSource('https://www.yeastar.com/ip-pbx/');

        $this->assertEquals(ProductSource::STATUS_SUCCESS, $parent->status);
        // Hanya P550 yang dibuat baru (P520 + induk sudah ada).
        $this->assertEquals(3, ProductSource::count());
    }

    public function test_thin_page_falls_back_to_crawl(): void
    {
        $this->actingAs($this->admin());
        $this->pages = [];
        $this->listingMarkdown = 'tipis';
        $this->crawlPages = [
            'https://vendor.com/kosong/p1',
            'https://vendor.com/kosong/p2',
            'https://other.com/luar',
        ];
        $this->fakeAll();

        $origin = ProductSource::create([
            'url' => 'https://vendor.com/kosong', 'status' => ProductSource::STATUS_QUEUED, 'batch_id' => 'b',
        ]);
        ImportProductSource::dispatchSync($origin->id);
        PollProductCrawl::dispatchSync('crawl-1', 'b', $origin->id);

        // Se-host dibuat, luar host dibuang.
        $this->assertEquals(2, ProductSource::where('batch_id', 'b')->where('id', '!=', $origin->id)->count());
        $this->assertDatabaseMissing('product_sources', ['url' => 'https://other.com/luar']);
    }

    public function test_crawl_without_pages_needs_review(): void
    {
        $this->actingAs($this->admin());
        $this->pages = [];
        $this->listingMarkdown = 'tipis';
        $this->crawlEmpty = true;
        $this->fakeAll();

        $origin = ProductSource::create([
            'url' => 'https://vendor.com/kosong', 'status' => ProductSource::STATUS_QUEUED, 'batch_id' => 'b',
        ]);
        ImportProductSource::dispatchSync($origin->id);
        PollProductCrawl::dispatchSync('crawl-1', 'b', $origin->id);

        $this->assertEquals(ProductSource::STATUS_NEEDS_REVIEW, $origin->fresh()->status);
    }
}
