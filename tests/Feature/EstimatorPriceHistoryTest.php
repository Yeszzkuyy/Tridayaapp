<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductPriceLog;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_estimator_updates_cost_without_touching_selling_price(): void
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

    public function test_product_picker_returns_cost_history_with_stock_and_warranty(): void
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
}
