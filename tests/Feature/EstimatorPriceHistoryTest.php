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

    public function test_updating_product_base_price_creates_price_log(): void
    {
        $sales = $this->userWithRole('sales');
        $product = $this->product();

        $this->actingAs($sales)->put(route('products.update', $product), [
            'name' => $product->name,
            'category' => 'barang',
            'unit' => 'pcs',
            'default_price' => 1250000,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('product_price_logs', [
            'product_id' => $product->id,
            'price' => 1250000,
            'updated_by' => $sales->id,
        ]);

        // Harga kedua menambah baris baru, bukan menimpa riwayat lama.
        $this->actingAs($sales)->put(route('products.update', $product), [
            'name' => $product->name,
            'category' => 'barang',
            'unit' => 'pcs',
            'default_price' => 1300000,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame(2, ProductPriceLog::where('product_id', $product->id)->count());
        $this->assertSame(1300000.0, (float) $product->fresh()->default_price);
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
            'currency' => 'IDR',
            'updated_by' => $sales->id,
            'notes' => 'Harga vendor minggu ini',
        ]);

        $response = $this->actingAs($sales)->getJson(route('products.picker', ['q' => 'Switch 24']));

        $response->assertOk();
        $response->assertJsonPath('0.name', 'Switch 24 Port');
        $response->assertJsonPath('0.price_history.0.price', 875000);
        $response->assertJsonPath('0.price_history.0.supplier', 'Supplier Jaringan');
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
                'notes' => 'MOQ 5 unit',
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('product_price_logs', [
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'price_request_id' => $priceRequest->id,
            'price' => 640000,
            'updated_by' => $estimator->id,
        ]);

        $this->assertSame(640000.0, (float) $product->fresh()->default_price);
        $this->assertSame($estimator->id, $priceRequest->fresh()->handled_by);
        $this->assertSame('waiting_sales_review', $priceRequest->fresh()->status);
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
