<?php

namespace Tests\Feature;

use App\Ai\Tools\GetProductKnowledge;
use App\Jobs\ImportProductSource;
use App\Models\Product;
use App\Models\ProductSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Kunci dummy: HTTP difake, yang penting lolos cek konfigurasi.
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

    private function scrapeFake(&$markdown, array $links = []): void
    {
        Http::fake([
            'api.firecrawl.dev/v2/scrape' => function () use (&$markdown, $links) {
                // Padding > listing_threshold agar jalur halaman-produk, bukan crawl.
                $body = $markdown."\n\n".str_repeat('Konten resmi produk. ', 40);
                return Http::response([
                    'success' => true,
                    'data' => ['markdown' => $body, 'metadata' => [], 'links' => $links],
                ]);
            },
            'openrouter.ai/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'brand' => 'Yeastar', 'name' => 'P-Series', 'model' => 'P560',
                        'sku' => null, 'category' => 'IP PBX',
                        'description' => 'IP PBX untuk usaha.',
                        'specifications' => 'Up to 100 users.',
                    ])],
                ]],
            ]),
        ]);
    }

    private function importUrls(array $urls, bool $sync = true): array
    {
        $sources = [];
        foreach ($urls as $url) {
            $source = ProductSource::create([
                'url' => $url,
                'source_type' => ProductSource::TYPE_PRODUCT_PAGE,
                'status' => ProductSource::STATUS_QUEUED,
                'batch_id' => 'batch-1',
            ]);
            if ($sync) {
                // Gagal sync = status FAILED sudah tercatat job; lanjut URL berikut.
                try {
                    ImportProductSource::dispatchSync($source->id);
                } catch (\Throwable $e) {
                    // ditelan sengaja
                }
            }
            $sources[] = $source->fresh();
        }

        return $sources;
    }

    public function test_admin_gate(): void
    {
        Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        $user = User::factory()->create();
        $user->assignRole('technician');

        $this->actingAs($user)->get(route('product-knowledge.index'))->assertForbidden();
        $this->actingAs($user)->post(route('product-knowledge.import'), ['urls' => 'https://x.com/a'])
            ->assertForbidden();
    }

    public function test_import_single_url_success(): void
    {
        $this->actingAs($this->admin());
        $md = "# Yeastar P560\nIP PBX untuk usaha. Up to 100 users.";
        $this->scrapeFake($md);

        [$source] = $this->importUrls(['https://vendor.com/product/1']);

        $this->assertEquals(ProductSource::STATUS_PUBLISHED, $source->status);
        $product = $source->fresh()->product;
        $this->assertNotNull($product);
        $this->assertEquals('Yeastar', $product->brand);
        $this->assertEquals('P560', $product->model);
        $this->assertEquals(1, $product->documents()->count());
        $this->assertGreaterThanOrEqual(1, $product->documents()->first()->chunks()->count());
    }

    public function test_batch_continues_when_one_url_fails(): void
    {
        $this->actingAs($this->admin());
        Http::fake([
            'api.firecrawl.dev/v2/scrape' => function ($request) {
                $url = $request->data()['url'] ?? '';
                if (str_contains($url, '/gagal')) {
                    return Http::response(['error' => 'boom'], 500);
                }

                return Http::response(['success' => true, 'data' => ['markdown' => 'ok '.str_repeat('konten resmi. ', 60), 'metadata' => [], 'links' => []]]);
            },
            'openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
                'brand' => 'B', 'name' => 'N', 'model' => 'M', 'sku' => null,
                'category' => null, 'description' => null, 'specifications' => null,
            ])]]]]),
        ]);

        [$ok, $fail] = $this->importUrls(['https://vendor.com/ok', 'https://vendor.com/gagal']);

        $this->assertEquals(ProductSource::STATUS_FAILED, $fail->status);
        $this->assertNotNull($fail->error);
        $this->assertEquals(ProductSource::STATUS_PUBLISHED, $ok->status);
    }

    public function test_import_dispatches_one_job_per_url(): void
    {
        $this->actingAs($this->admin());
        Bus::fake();

        $this->post(route('product-knowledge.import'), [
            'urls' => "https://a.com/1\nhttps://a.com/2\nhttps://a.com/1\n",
        ])->assertRedirect();

        Bus::assertDispatchedTimes(ImportProductSource::class, 2);
        $this->assertEquals(2, ProductSource::count());
    }

    public function test_double_paste_same_batch_processed_once(): void
    {
        $this->actingAs($this->admin());
        $md = 'konten ganda';
        $this->scrapeFake($md);

        // Antrean sync: job langsung jalan saat POST.
        $this->post(route('product-knowledge.import'), [
            'urls' => "https://vendor.com/ganda\nhttps://vendor.com/ganda\n",
        ])->assertRedirect();

        // Satu baris, diproses normal (bukan duplicate).
        $this->assertEquals(1, ProductSource::count());
        $source = ProductSource::first();
        $this->assertNotEquals(ProductSource::STATUS_DUPLICATE, $source->status);
        $this->assertNotNull($source->product_id);
    }

    public function test_reimport_processed_url_shows_duplicate_without_touching(): void
    {
        $this->actingAs($this->admin());
        $md = 'konten';
        $this->scrapeFake($md);

        $this->importUrls(['https://vendor.com/dup']);
        $originalStatus = ProductSource::first()->status;
        $this->assertEquals(1, ProductSource::count());

        $this->post(route('product-knowledge.import'), ['urls' => 'https://vendor.com/dup'])
            ->assertRedirect()
            ->assertSessionHas('duplicateUrls', ['https://vendor.com/dup']);

        // Baris asli tak tersentuh.
        $this->assertEquals(1, ProductSource::count());
        $this->assertEquals($originalStatus, ProductSource::first()->status);
    }

    public function test_reimport_failed_url_retries(): void
    {
        $this->actingAs($this->admin());
        $failed = ProductSource::create([
            'url' => 'https://vendor.com/gagal-dulu',
            'status' => ProductSource::STATUS_FAILED,
            'error' => 'boom',
            'batch_id' => 'lama',
        ]);

        $md = 'konten pulih';
        $this->scrapeFake($md);

        $this->post(route('product-knowledge.import'), ['urls' => 'https://vendor.com/gagal-dulu'])
            ->assertRedirect();

        // Baris sama dipakai ulang + diproses (sync), bukan duplicate.
        $this->assertEquals(1, ProductSource::where('url', 'https://vendor.com/gagal-dulu')->count());
        $this->assertNotEquals(ProductSource::STATUS_DUPLICATE, $failed->fresh()->status);
        $this->assertNotNull($failed->fresh()->product_id);
    }

    public function test_same_product_different_source_appends(): void
    {
        $this->actingAs($this->admin());
        $md = 'konten';
        $this->scrapeFake($md);

        $this->importUrls(['https://vendor.com/product/1']);
        $this->importUrls(['https://vendor.com/datasheet/1.pdf']);

        $this->assertEquals(1, Product::count());
        $this->assertEquals(2, Product::first()->sources()->count());
    }

    public function test_missing_metadata_needs_review(): void
    {
        $this->actingAs($this->admin());
        Http::fake([
            'api.firecrawl.dev/v2/scrape' => Http::response([
                'success' => true, 'data' => ['markdown' => 'halo dunia '.str_repeat('konten resmi. ', 60), 'metadata' => [], 'links' => []],
            ]),
            'openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
                'brand' => null, 'name' => null, 'model' => null, 'sku' => null,
                'category' => null, 'description' => null, 'specifications' => null,
            ])]]]]),
        ]);

        [$source] = $this->importUrls(['https://vendor.com/samar']);

        $this->assertEquals(ProductSource::STATUS_NEEDS_REVIEW, $source->status);
    }

    public function test_valid_auto_publishes_and_retrieval(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $md = 'konten';
        $this->scrapeFake($md);

        [$source] = $this->importUrls(['https://vendor.com/product/9']);
        $product = $source->fresh()->product;

        // Tanpa klik apa pun: produk + dokumen + source langsung PUBLISHED.
        $this->assertEquals(Product::STATUS_PUBLISHED, $product->status);
        $this->assertEquals('published', $product->documents()->first()->status);

        $tool = new GetProductKnowledge($admin);
        $found = (string) $tool->handle(new \Laravel\Ai\Tools\Request(['query' => 'P560']));
        $this->assertStringContainsString('P560', $found);
        $this->assertStringContainsString('https:\/\/vendor.com\/product\/9', $found);
    }

    public function test_needs_review_fix_then_publish(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        Http::fake([
            'api.firecrawl.dev/v2/scrape' => Http::response([
                'success' => true,
                'data' => ['markdown' => 'samar '.str_repeat('konten resmi. ', 60), 'metadata' => [], 'links' => []],
            ]),
            'openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
                'brand' => null, 'name' => null, 'model' => null, 'sku' => null,
                'category' => null, 'description' => null, 'specifications' => null,
            ])]]]]),
        ]);

        [$source] = $this->importUrls(['https://vendor.com/samar-2']);
        $product = $source->fresh()->product;

        // Alasan tercatat, belum publish.
        $this->assertEquals(ProductSource::STATUS_NEEDS_REVIEW, $source->fresh()->status);
        $this->assertStringContainsString('Perlu review', (string) $source->fresh()->error);
        $this->assertNotEquals(Product::STATUS_PUBLISHED, $product->status);

        // Admin betulkan data lalu publish manual.
        $this->patch(route('product-knowledge.update', $product), [
            'brand' => 'Yeastar', 'name' => 'P-Series', 'model' => 'P520',
        ])->assertRedirect();
        $this->patch(route('product-knowledge.publish', $product))->assertRedirect();
        $this->assertEquals(Product::STATUS_PUBLISHED, $product->fresh()->status);

        $tool = new GetProductKnowledge($admin);
        $found = (string) $tool->handle(new \Laravel\Ai\Tools\Request(['query' => 'P520']));
        $this->assertStringContainsString('P520', $found);
    }

    public function test_natural_question_finds_published_product(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $md = 'konten Yeastar P560';
        $this->scrapeFake($md);
        $this->importUrls(['https://vendor.com/product/9']);

        $tool = new GetProductKnowledge($admin);
        $found = (string) $tool->handle(new \Laravel\Ai\Tools\Request(
            ['query' => 'Berapa spesifikasi PBX Yeastar P560 untuk kantor?']
        ));
        $this->assertStringContainsString('P560', $found);
        $this->assertStringContainsString('vendor.com', $found);
    }

    public function test_invalid_url_rejected_by_validation(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('product-knowledge.import'), ['urls' => 'bukan-url'])
            ->assertSessionHasErrors('urls');
        $this->assertEquals(0, ProductSource::count());
    }

    public function test_sales_cannot_manage(): void
    {
        Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        $sales = User::factory()->create();
        $sales->assignRole('sales');

        $this->actingAs($sales)->get(route('product-knowledge.index'))->assertForbidden();
        $this->actingAs($sales)->post(route('product-knowledge.import'), ['urls' => 'https://x.com/a'])
            ->assertForbidden();
    }

    public function test_rejected_excluded_from_retrieval(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $md = 'konten';
        $this->scrapeFake($md);

        [$source] = $this->importUrls(['https://vendor.com/product/8']);
        $product = $source->fresh()->product;

        $this->assertEquals(Product::STATUS_PUBLISHED, $product->fresh()->status);
        $this->patch(route('product-knowledge.reject', $product))->assertRedirect();

        $tool = new GetProductKnowledge($admin);
        $this->assertStringContainsString('Tidak ada', (string) $tool->handle(new \Laravel\Ai\Tools\Request(['query' => 'P560'])));
    }

    public function test_refetch_unchanged_no_new_version(): void
    {
        $this->actingAs($this->admin());
        $md = 'konten sama';
        $this->scrapeFake($md);

        [$source] = $this->importUrls(['https://vendor.com/product/7']);
        $this->assertEquals(1, $source->fresh()->product->documents()->count());

        $productsBefore = Product::count();

        // Fetch ulang isi sama -> tanpa dokumen baru, tanpa produk yatim.
        ImportProductSource::dispatchSync($source->fresh()->id);
        $this->assertEquals($productsBefore, Product::count());
        $this->assertEquals(1, $source->fresh()->product->documents()->count());
        $this->assertEquals(ProductSource::STATUS_PUBLISHED, $source->fresh()->status);
    }

    public function test_refetch_changed_creates_new_version(): void
    {
        $this->actingAs($this->admin());
        $md = 'konten v1';
        $this->scrapeFake($md);

        [$source] = $this->importUrls(['https://vendor.com/product/6']);
        $product = $source->fresh()->product;

        $md = 'konten v1 plus tambahan spesifikasi baru';
        $this->scrapeFake($md);
        $this->post(route('product-knowledge.refetch', $source))->assertRedirect();
        ImportProductSource::dispatchSync($source->fresh()->id);

        $docs = $product->documents()->orderBy('version')->get();
        $this->assertEquals(2, $docs->count());
        $this->assertEquals([1, 2], $docs->pluck('version')->all());
        // Versi lama tetap tercatat.
        $this->assertTrue($docs->first()->chunks()->exists());
    }

    public function test_scrape_uses_quality_params(): void
    {
        $this->actingAs($this->admin());
        $md = 'konten param';
        $this->scrapeFake($md);
        $this->importUrls(['https://vendor.com/param']);

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            $payload = $request->data();
            return ($request->url() === 'https://api.firecrawl.dev/v2/scrape')
                && ($payload['waitFor'] ?? 0) > 0
                && ($payload['blockAds'] ?? false) === true
                && ($payload['onlyCleanContent'] ?? false) === true
                && is_array($payload['actions'] ?? null);
        });
    }

    public function test_pdf_datasheet_auto_attached(): void
    {
        $this->actingAs($this->admin());
        Http::fake([
            'api.firecrawl.dev/v2/scrape' => function ($request) {
                $url = $request->data()['url'] ?? '';
                if ($url === 'https://vendor.com/printer') {
                    return Http::response(['success' => true, 'data' => [
                        'markdown' => 'Printer X. '.str_repeat('konten resmi. ', 60),
                        'metadata' => [],
                        'links' => [
                            'https://vendor.com/datasheet/x.pdf',
                            'https://vendor.com/datasheet/y.pdf',
                            'https://luar.com/z.pdf',
                            'https://vendor.com/datasheet/kelebihan.pdf',
                        ],
                    ]]);
                }

                return Http::response(['success' => true, 'data' => [
                    'markdown' => 'MODEL:PX100 datasheet. '.str_repeat('konten resmi. ', 60),
                    'metadata' => [], 'links' => [],
                ]]);
            },
            'openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
                'brand' => 'Vendor', 'name' => 'Printer', 'model' => 'PX100', 'sku' => null,
                'category' => 'Printer', 'description' => 'Printer.', 'specifications' => 'A4.',
            ])]]]]),
        ]);

        $this->importUrls(['https://vendor.com/printer']);

        // 2 PDF se-host (maks 2), luar host dibuang.
        $pdfs = ProductSource::where('source_type', 'datasheet')->get();
        $this->assertEquals(2, $pdfs->count());
        $this->assertDatabaseMissing('product_sources', ['url' => 'https://luar.com/z.pdf']);
        // PDF menempel ke produk yang sama.
        $this->assertEquals(1, Product::count());
    }

    public function test_cookie_wall_lines_cleaned(): void
    {
        $pipeline = app(\App\Services\Knowledge\ProductKnowledgePipeline::class);

        $clean = $pipeline->cleanContent(implode("\n", [
            '# Yeastar P560',
            'We value your privacy',
            'We use cookies to personalize your use of our site.',
            'IP PBX untuk 100 user.',
        ]));

        $this->assertStringContainsString('Yeastar P560', $clean);
        $this->assertStringContainsString('100 user', $clean);
        $this->assertStringNotContainsString('privacy', strtolower($clean));
        $this->assertStringNotContainsString('cookies to personalize', strtolower($clean));
    }


    public function test_extraction_retries_once_on_empty_result(): void
    {
        $this->actingAs($this->admin());
        $calls = 0;
        Http::fake([
            'api.firecrawl.dev/v2/scrape' => Http::response(['success' => true, 'data' => [
                'markdown' => 'konten retry '.str_repeat('konten resmi. ', 60),
                'metadata' => [], 'links' => [],
            ]]),
            'openrouter.ai/*' => function () use (&$calls) {
                $calls++;
                $empty = $calls === 1;

                return Http::response(['choices' => [['message' => ['content' => json_encode([
                    'brand' => $empty ? null : 'B', 'name' => $empty ? null : 'N',
                    'model' => $empty ? null : 'M', 'sku' => null,
                    'category' => null, 'description' => null, 'specifications' => null,
                ])]]]]);
            },
        ]);

        [$source] = $this->importUrls(['https://vendor.com/retry']);

        // Voting: dicoba ulang sampai dapat yang lengkap -> PUBLISHED.
        $this->assertGreaterThanOrEqual(2, $calls);
        $this->assertEquals(ProductSource::STATUS_PUBLISHED, $source->status);
    }

    public function test_extraction_sends_title_hint(): void
    {
        config()->set('ai.providers.openrouter.key', 'test-key');
        $payload = ['brand' => 'Synology', 'name' => 'DS425+', 'model' => 'DS425+'];
        $sent = null;
        Http::fake([
            'openrouter.ai/*' => function ($request) use (&$sent, $payload) {
                $sent = $request->data();
                return Http::response(['choices' => [['message' => ['content' => json_encode($payload)]]]]);
            },
        ]);

        $extractor = app(\App\Services\Knowledge\ProductExtractor::class);
        $result = $extractor->extract('Fitur NAS cepat.', [
            'title' => 'DiskStation DS425+ | Synology Inc.',
            'url' => 'https://www.synology.com/en-id/products/DS425+',
        ]);

        $this->assertEquals('Synology', $result['brand']);

        $user = end($sent['messages'])['content'] ?? '';
        $this->assertStringContainsString('Page title: DiskStation DS425+', $user);
    }

    public function test_extraction_upstream_error_throws(): void
    {
        config()->set('ai.providers.openrouter.key', 'test-key');
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'id' => 'gen-1',
                'error' => ['message' => 'Upstream error from Nvidia: ResourceExhausted'],
            ], 200),
        ]);

        $this->expectException(\RuntimeException::class);
        app(\App\Services\Knowledge\ProductExtractor::class)->extract('konten');
    }

    public function test_bulk_delete_products_cascades(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $md = 'konten hapus';
        $this->scrapeFake($md);
        [$s1] = $this->importUrls(['https://vendor.com/hapus-1']);
        [$s2] = $this->importUrls(['https://vendor.com/hapus-2']);
        $p1 = $s1->fresh()->product;
        $p2 = $s2->fresh()->product;
        $docId = $p1->documents()->first()->id;

        $this->delete(route('product-knowledge.bulk-destroy'), ['ids' => [$p1->id, $p2->id]])
            ->assertRedirect();
        $this->assertDatabaseMissing('products', ['id' => $p1->id]);
        $this->assertDatabaseMissing('products', ['id' => $p2->id]);
        $this->assertDatabaseMissing('product_sources', ['id' => $s1->id]);
        $this->assertDatabaseMissing('knowledge_documents', ['id' => $docId]);
        $this->assertEquals(0, \App\Models\KnowledgeChunk::where('document_id', $docId)->count());
    }

    public function test_bulk_delete_validates_and_forbids(): void
    {
        $this->actingAs($this->admin());
        $this->delete(route('product-knowledge.bulk-destroy'), ['ids' => []])
            ->assertSessionHasErrors('ids');
        $this->delete(route('product-knowledge.bulk-destroy'), ['ids' => [999999]])
            ->assertSessionHasErrors('ids.0');

        Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        $sales = User::factory()->create();
        $sales->assignRole('sales');
        $product = Product::create(['name' => 'X']);
        $this->actingAs($sales)->delete(route('product-knowledge.bulk-destroy'), ['ids' => [$product->id]])
            ->assertForbidden();
        $this->assertNotNull(Product::find($product->id));
    }

    public function test_delete_source_keeps_product(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $md = 'konten source';
        $this->scrapeFake($md);
        [$source] = $this->importUrls(['https://vendor.com/source-hapus']);
        $product = $source->fresh()->product;

        $this->delete(route('product-knowledge.sources.destroy', $source))->assertRedirect();
        $this->assertDatabaseMissing('product_sources', ['id' => $source->id]);
        $this->assertNotNull(Product::find($product->id));
    }


    public function test_description_auto_filled_and_manual_kept(): void
    {
        $this->actingAs($this->admin());
        Http::fake([
            'api.firecrawl.dev/v2/scrape' => Http::response(['success' => true, 'data' => [
                'markdown' => 'Printer bagus. '.str_repeat('konten resmi. ', 60),
                'metadata' => [], 'links' => [],
            ]]),
            'openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
                'brand' => 'Vendor', 'name' => 'Printer', 'model' => 'PX200', 'sku' => null,
                'category' => 'Printer', 'description' => null, 'specifications' => 'A4, 20ppm.',
            ])]]]]),
        ]);

        [$source] = $this->importUrls(['https://vendor.com/desc']);
        $product = $source->fresh()->product;

        // Deskripsi tersusun otomatis dari fakta.
        $this->assertStringContainsString('PX200', (string) $product->description);
        $this->assertStringContainsString('Vendor', (string) $product->description);

        // Deskripsi manual tidak ditimpa fetch ulang.
        $product->forceFill(['description' => 'Manual'])->save();
        ImportProductSource::dispatchSync($source->fresh()->id);
        $this->assertEquals('Manual', $product->fresh()->description);
    }
}
