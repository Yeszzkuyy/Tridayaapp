<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PriceRequest;
use App\Models\Product;
use App\Models\ProductPriceLog;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EstimatorPriceHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Kamera CCTV '.uniqid(),
            'category' => 'barang',
            'unit' => 'pcs',
            'default_price' => 1000000,
            'is_active' => true,
            'status' => Product::STATUS_APPROVED,
        ], $attributes));
    }

    private function makePriceRequest(User $sales, array $overrides = []): PriceRequest
    {
        return PriceRequest::create(array_merge([
            'customer_id' => Customer::create(['name' => 'PT Harga '.uniqid()])->id,
            'requested_by' => $sales->id,
            'status' => 'waiting_lead',
        ], $overrides));
    }

    public function test_updating_product_base_price_creates_selling_price_log(): void
    {
        $admin = $this->userWithRole('admin');
        $product = $this->product();

        $this->actingAs($admin)->put(route('products.update', $product), [
            'name' => $product->name,
            'category' => 'barang',
            'unit' => 'pcs',
            'default_price' => 1250000,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('product_price_logs', [
            'product_id' => $product->id,
            'price' => 1250000,
            'price_type' => 'selling',
            'updated_by' => $admin->id,
        ]);

        // Harga kedua menambah baris baru, bukan menimpa riwayat lama.
        $this->actingAs($admin)->put(route('products.update', $product), [
            'name' => $product->name,
            'category' => 'barang',
            'unit' => 'pcs',
            'default_price' => 1300000,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame(2, ProductPriceLog::where('product_id', $product->id)->count());
        $this->assertSame(1300000.0, (float) $product->fresh()->default_price);
    }

    public function test_sales_cannot_create_product_with_selling_price(): void
    {
        $sales = $this->userWithRole('sales');

        $this->actingAs($sales)->post(route('products.store'), [
            'name' => 'Produk Tanpa Harga Jual',
            'category' => 'barang',
            'default_price' => 1500000,
        ])->assertForbidden();

        $this->assertDatabaseMissing('products', ['name' => 'Produk Tanpa Harga Jual']);
    }

    public function test_sales_can_create_product_without_setting_selling_price(): void
    {
        $sales = $this->userWithRole('sales');

        $this->actingAs($sales)->post(route('products.store'), [
            'name' => 'Produk Baru Sales',
            'category' => 'barang',
        ])->assertRedirect();

        $product = Product::where('name', 'Produk Baru Sales')->firstOrFail();
        $this->assertSame(0.0, (float) $product->default_price);
    }

    public function test_estimator_can_update_cost_without_touching_selling_price(): void
    {
        $estimator = $this->userWithRole('estimator');
        $product = $this->product([
            'default_price' => 1000000,
            'cost_price' => 600000,
        ]);

        $this->actingAs($estimator)->put(route('products.update', $product), [
            'name' => $product->name,
            'category' => 'barang',
            'unit' => 'pcs',
            'cost_price' => 750000,
            'is_active' => 1,
        ])->assertRedirect();

        $product->refresh();
        $this->assertSame(750000.0, (float) $product->cost_price);
        $this->assertSame(1000000.0, (float) $product->default_price);
        $this->assertDatabaseHas('product_price_logs', [
            'product_id' => $product->id,
            'price' => 750000,
            'price_type' => 'cost',
            'updated_by' => $estimator->id,
        ]);
    }

    public function test_estimator_cannot_update_selling_price(): void
    {
        $estimator = $this->userWithRole('estimator');
        $product = $this->product();

        $this->actingAs($estimator)->put(route('products.update', $product), [
            'name' => $product->name,
            'category' => 'barang',
            'unit' => 'pcs',
            'default_price' => 9999999,
            'is_active' => 1,
        ])->assertForbidden();

        $this->assertSame(1000000.0, (float) $product->fresh()->default_price);
    }

    public function test_sales_cannot_update_selling_price(): void
    {
        $sales = $this->userWithRole('sales');
        $product = $this->product();

        $this->actingAs($sales)->put(route('products.update', $product), [
            'name' => $product->name,
            'category' => 'barang',
            'unit' => 'pcs',
            'default_price' => 9999999,
            'is_active' => 1,
        ])->assertForbidden();

        $this->assertSame(1000000.0, (float) $product->fresh()->default_price);
    }

    public function test_product_picker_returns_price_history(): void
    {
        $sales = $this->userWithRole('sales');
        $product = $this->product(['name' => 'Switch 24 Port']);
        $supplier = Supplier::create(['name' => 'Supplier Jaringan']);

        ProductPriceLog::create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'price' => 875000,
            'price_type' => 'cost',
            'currency' => 'IDR',
            'stock_status' => 'ready',
            'warranty_info' => '1 year distributor',
            'updated_by' => $sales->id,
            'notes' => 'Harga vendor minggu ini',
        ]);

        $response = $this->actingAs($sales)->getJson(route('products.picker', ['q' => 'Switch 24']));

        $response->assertOk();
        $response->assertJsonPath('0.name', 'Switch 24 Port');
        $response->assertJsonPath('0.price_history.0.price', 875000);
        $response->assertJsonPath('0.price_history.0.supplier', 'Supplier Jaringan');
        $response->assertJsonPath('0.price_history.0.stock_status', 'ready');
        $response->assertJsonPath('0.price_history.0.warranty_info', '1 year distributor');
    }

    public function test_estimator_reply_records_prices_and_updates_product(): void
    {
        Notification::fake();
        Storage::fake('private');

        $estimator = $this->userWithRole('estimator');
        $sales = $this->userWithRole('sales');
        $customer = Customer::create(['name' => 'PT Harga']);
        $product = $this->product(['default_price' => 500000]);
        $supplier = Supplier::create(['name' => 'Vendor Harga']);

        $priceRequest = PriceRequest::create([
            'customer_id' => $customer->id,
            'requested_by' => $sales->id,
            'assigned_to' => $estimator->id,
            'status' => 'in_progress',
        ]);

        $this->actingAs($estimator)->post(route('sales.price-requests.reply.store', $priceRequest), [
            'reply' => UploadedFile::fake()->create('hitungan.pdf', 100, 'application/pdf'),
            'reply_notes' => 'Harga sudah dikonfirmasi vendor.',
            'price_items' => [[
                'product_id' => $product->id,
                'supplier_id' => $supplier->id,
                'price' => 640000,
                'stock_status' => 'ready',
                'warranty_info' => '1 year official distributor',
                'notes' => 'MOQ 5 unit',
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('product_price_logs', [
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'price_request_id' => $priceRequest->id,
            'price' => 640000,
            'price_type' => 'cost',
            'stock_status' => 'ready',
            'warranty_info' => '1 year official distributor',
            'updated_by' => $estimator->id,
        ]);

        $product->refresh();
        $this->assertSame(640000.0, (float) $product->cost_price);
        $this->assertSame(500000.0, (float) $product->default_price);
        $this->assertSame($estimator->id, $priceRequest->fresh()->handled_by);
        $this->assertSame('waiting_sales_review', $priceRequest->fresh()->status);
    }

    public function test_estimator_can_reply_with_price_items_without_a_file(): void
    {
        Notification::fake();
        Storage::fake('private');

        $estimator = $this->userWithRole('estimator');
        $sales = $this->userWithRole('sales');
        $customer = Customer::create(['name' => 'PT Tanpa File']);
        $product = $this->product(['default_price' => 500000]);
        $supplier = Supplier::create(['name' => 'Vendor Tanpa File']);

        $priceRequest = PriceRequest::create([
            'customer_id' => $customer->id,
            'requested_by' => $sales->id,
            'assigned_to' => $estimator->id,
            'status' => 'in_progress',
        ]);

        $this->actingAs($estimator)->post(route('sales.price-requests.reply.store', $priceRequest), [
            'reply_notes' => 'Harga tanpa lampiran.',
            'price_items' => [[
                'product_id' => $product->id,
                'supplier_id' => $supplier->id,
                'price' => 610000,
                'stock_status' => 'indent',
                'stock_eta' => '2-3 weeks',
                'warranty_info' => '6 months distributor',
                'notes' => 'Indent',
            ]],
        ])->assertRedirect();

        $priceRequest->refresh();
        $reply = $priceRequest->replies()->firstOrFail();
        $this->assertNull($reply->file_path);
        $this->assertNull($priceRequest->reply_path);
        $this->assertSame('waiting_sales_review', $priceRequest->status);
        $this->assertDatabaseHas('product_price_logs', [
            'product_id' => $product->id,
            'price_request_id' => $priceRequest->id,
            'price' => 610000,
            'price_type' => 'cost',
            'stock_status' => 'indent',
            'stock_eta' => '2-3 weeks',
            'warranty_info' => '6 months distributor',
        ]);
    }

    public function test_estimator_reply_requires_supplier_stock_and_warranty(): void
    {
        Notification::fake();
        Storage::fake('private');

        $estimator = $this->userWithRole('estimator');
        $sales = $this->userWithRole('sales');
        $customer = Customer::create(['name' => 'PT Validasi Harga']);
        $product = $this->product();

        $priceRequest = PriceRequest::create([
            'customer_id' => $customer->id,
            'requested_by' => $sales->id,
            'assigned_to' => $estimator->id,
            'status' => 'in_progress',
        ]);

        $this->actingAs($estimator)->post(route('sales.price-requests.reply.store', $priceRequest), [
            'reply_notes' => 'Data belum lengkap.',
            'price_items' => [[
                'product_id' => $product->id,
                'price' => 500000,
            ]],
        ])->assertSessionHasErrors([
            'price_items.0.supplier_id',
            'price_items.0.stock_status',
            'price_items.0.warranty_info',
        ]);

        $this->assertSame(0, $priceRequest->replies()->count());
    }

    public function test_indent_item_requires_stock_estimate(): void
    {
        Notification::fake();
        Storage::fake('private');

        $estimator = $this->userWithRole('estimator');
        $sales = $this->userWithRole('sales');
        $customer = Customer::create(['name' => 'PT Indent']);
        $product = $this->product();
        $supplier = Supplier::create(['name' => 'Vendor Indent']);

        $priceRequest = PriceRequest::create([
            'customer_id' => $customer->id,
            'requested_by' => $sales->id,
            'assigned_to' => $estimator->id,
            'status' => 'in_progress',
        ]);

        $this->actingAs($estimator)->post(route('sales.price-requests.reply.store', $priceRequest), [
            'price_items' => [[
                'product_id' => $product->id,
                'supplier_id' => $supplier->id,
                'price' => 500000,
                'stock_status' => 'indent',
                'warranty_info' => '1 year',
            ]],
        ])->assertSessionHasErrors('price_items.0.stock_eta');

        $this->assertSame(0, $priceRequest->replies()->count());
    }

    public function test_sales_queue_page_shows_status_and_last_price_update(): void
    {
        $estimator = $this->userWithRole('estimator');
        $sales = $this->userWithRole('sales');
        $customer = Customer::create(['name' => 'PT Antrean Baru']);

        $priceRequest = PriceRequest::create([
            'customer_id' => $customer->id,
            'requested_by' => $sales->id,
            'status' => 'waiting_sales_review',
        ]);

        ProductPriceLog::create([
            'product_id' => $this->product()->id,
            'supplier_id' => Supplier::create(['name' => 'Vendor Antrean'])->id,
            'price' => 450000,
            'price_type' => 'cost',
            'currency' => 'IDR',
            'stock_status' => 'ready',
            'warranty_info' => '1 year',
            'price_request_id' => $priceRequest->id,
            'updated_by' => $estimator->id,
        ]);

        $html = $this->actingAs($sales)
            ->get(route('sales.price-requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('PT Antrean Baru', $html);
        $this->assertStringContainsString(__('Price Received'), $html);
        $this->assertStringContainsString('Rp 450.000', $html);
    }

    public function test_sales_queue_only_shows_own_requests(): void
    {
        $mine = $this->userWithRole('sales');
        $other = $this->userWithRole('sales');
        $this->makePriceRequest($mine, ['customer_id' => Customer::create(['name' => 'PT Milikku'])->id]);
        $this->makePriceRequest($other, ['customer_id' => Customer::create(['name' => 'PT Oranglain'])->id]);

        $html = $this->actingAs($mine)
            ->get(route('sales.price-requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('PT Milikku', $html);
        $this->assertStringNotContainsString('PT Oranglain', $html);
    }

    public function test_estimator_sees_all_price_requests_in_hub(): void
    {
        $estimator = $this->userWithRole('estimator');
        $sales = $this->userWithRole('sales');
        $customer = Customer::create(['name' => 'PT Antrean Harga']);

        PriceRequest::create([
            'customer_id' => $customer->id,
            'requested_by' => $sales->id,
            'status' => 'waiting_lead',
        ]);

        $this->actingAs($estimator)
            ->get(route('sales.requests.index', ['tab' => 'price']))
            ->assertOk()
            ->assertSee('PT Antrean Harga');
    }

    public function test_product_catalog_shows_latest_cost_for_estimator(): void
    {
        $estimator = $this->userWithRole('estimator');
        $product = $this->product(['name' => 'Produk Biaya Modal']);
        $supplier = Supplier::create(['name' => 'Vendor Biaya']);

        ProductPriceLog::create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'price' => 725000,
            'price_type' => 'cost',
            'currency' => 'IDR',
            'updated_by' => $estimator->id,
        ]);

        $this->actingAs($estimator)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Produk Biaya Modal')
            ->assertSee('725.000')
            ->assertSee('Vendor Biaya');
    }

    public function test_estimator_sees_all_price_requests_on_queue_page(): void
    {
        $estimator = $this->userWithRole('estimator');
        $salesA = $this->userWithRole('sales');
        $salesB = $this->userWithRole('sales');

        $this->makePriceRequest($salesA, ['customer_id' => Customer::create(['name' => 'PT Antrean A'])->id]);
        $this->makePriceRequest($salesB, ['customer_id' => Customer::create(['name' => 'PT Antrean B'])->id]);

        $html = $this->actingAs($estimator)
            ->get(route('sales.price-requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('PT Antrean A', $html);
        $this->assertStringContainsString('PT Antrean B', $html);
    }

    public function test_price_request_detail_shows_stock_and_warranty(): void
    {
        $estimator = $this->userWithRole('estimator');
        $sales = $this->userWithRole('sales');
        $customer = Customer::create(['name' => 'PT Detail Stok']);
        $product = $this->product(['name' => 'Kamera Detail']);
        $supplier = Supplier::create(['name' => 'Vendor Detail']);

        $priceRequest = PriceRequest::create([
            'customer_id' => $customer->id,
            'requested_by' => $sales->id,
            'status' => 'waiting_sales_review',
        ]);

        ProductPriceLog::create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'price' => 725000,
            'price_type' => 'cost',
            'currency' => 'IDR',
            'stock_status' => 'indent',
            'stock_eta' => '2 weeks',
            'warranty_info' => '1 year official',
            'price_request_id' => $priceRequest->id,
            'updated_by' => $estimator->id,
        ]);

        $this->actingAs($sales)
            ->get(route('sales.price-requests.show', $priceRequest))
            ->assertOk()
            ->assertSee('Kamera Detail')
            ->assertSee('Vendor Detail')
            ->assertSee('Indent')
            ->assertSee('2 weeks')
            ->assertSee('1 year official');
    }

    public function test_estimator_can_export_weekly_excel_and_sales_cannot(): void
    {
        $estimator = $this->userWithRole('estimator');
        $sales = $this->userWithRole('sales');
        $customer = Customer::create(['name' => 'PT Export Harga']);

        PriceRequest::create([
            'customer_id' => $customer->id,
            'requested_by' => $sales->id,
            'status' => 'completed',
            'completed_at' => now(),
            'notes' => 'Kebutuhan CCTV 8 titik',
        ]);

        $response = $this->actingAs($estimator)->get(route('sales.price-requests.export-excel'));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('estimator-report-', (string) $response->headers->get('content-disposition'));

        $this->actingAs($sales)
            ->get(route('sales.price-requests.export-excel'))
            ->assertForbidden();
    }
}
