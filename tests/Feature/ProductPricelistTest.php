<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductPriceLog;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPricelistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Produk '.uniqid(),
            'category' => 'barang',
            'unit' => 'pcs',
            'default_price' => 1000,
            'cost_price' => null,
            'is_active' => true,
        ], $attributes));
    }

    private function addCostLog(
        Product $product,
        User $user,
        float $price,
        ?string $validUntil = null,
        ?Supplier $supplier = null,
    ): ProductPriceLog {
        return ProductPriceLog::create([
            'product_id' => $product->id,
            'supplier_id' => $supplier?->id,
            'price' => $price,
            'price_type' => 'cost',
            'currency' => 'IDR',
            'stock_status' => 'ready',
            'warranty_info' => '2 years',
            'valid_until' => $validUntil,
            'updated_by' => $user->id,
        ]);
    }

    public function test_margin_accessors(): void
    {
        $healthy = $this->makeProduct(['default_price' => 1000, 'cost_price' => 600]);
        $low = $this->makeProduct(['default_price' => 1000, 'cost_price' => 950]);
        $loss = $this->makeProduct(['default_price' => 1000, 'cost_price' => 1100]);
        $zeroSelling = $this->makeProduct(['default_price' => 0, 'cost_price' => 100]);
        $noCost = $this->makeProduct(['default_price' => 1000, 'cost_price' => null]);

        $this->assertSame(400.0, $healthy->margin_amount);
        $this->assertEqualsWithDelta(40.0, $healthy->margin_percentage, 0.001);
        $this->assertSame('healthy', $healthy->margin_status);

        $this->assertEqualsWithDelta(5.0, $low->margin_percentage, 0.001);
        $this->assertSame('low', $low->margin_status);

        $this->assertSame(-100.0, $loss->margin_amount);
        $this->assertEqualsWithDelta(-10.0, $loss->margin_percentage, 0.001);
        $this->assertSame('loss', $loss->margin_status);

        $this->assertNull($zeroSelling->margin_percentage);
        $this->assertNull($zeroSelling->margin_status);
        $this->assertNull($noCost->margin_percentage);
        $this->assertNull($noCost->margin_status);
    }

    public function test_price_trend_compares_latest_two_cost_logs(): void
    {
        $user = $this->userWithRole('admin');
        $product = $this->makeProduct(['default_price' => 1000, 'cost_price' => 600]);

        $this->addCostLog($product, $user, 500);
        $this->addCostLog($product, $user, 600);

        $product->refresh()->load('costLogs');
        $trend = $product->price_trend;

        $this->assertNotNull($trend);
        $this->assertSame('up', $trend['direction']);
        $this->assertEqualsWithDelta(20.0, $trend['percentage'], 0.001);

        $single = $this->makeProduct();
        $this->addCostLog($single, $user, 500);
        $single->refresh()->load('costLogs');
        $this->assertNull($single->price_trend);
    }

    public function test_quick_filter_counts_and_results(): void
    {
        $admin = $this->userWithRole('admin');
        $supplier = Supplier::create(['name' => 'Supplier Uji', 'is_active' => true]);

        $active = $this->makeProduct(['name' => 'Aktif', 'default_price' => 1000, 'cost_price' => 600]);
        $this->addCostLog($active, $admin, 600, now()->addDays(10)->toDateString(), $supplier);

        $negative = $this->makeProduct(['name' => 'Rugi', 'default_price' => 1000, 'cost_price' => 1100]);
        $this->addCostLog($negative, $admin, 1100, now()->addDays(10)->toDateString(), $supplier);

        $expiring = $this->makeProduct(['name' => 'Segera', 'default_price' => 1000, 'cost_price' => 700]);
        $this->addCostLog($expiring, $admin, 700, now()->addDays(3)->toDateString(), $supplier);

        $expired = $this->makeProduct(['name' => 'Kedaluwarsa', 'default_price' => 1000, 'cost_price' => 600]);
        $this->addCostLog($expired, $admin, 600, now()->subDay()->toDateString(), $supplier);

        $zero = $this->makeProduct(['name' => 'Margin Nol', 'default_price' => 1000, 'cost_price' => 1000]);
        $this->addCostLog($zero, $admin, 1000, now()->addDays(10)->toDateString(), $supplier);

        $noLog = $this->makeProduct(['name' => 'Tanpa Riwayat', 'default_price' => 1000, 'cost_price' => 700]);

        $this->actingAs($admin)->get(route('products.index'))
            ->assertOk()
            ->assertViewHas('counts', function (array $counts) {
                return $counts['all'] === 6
                    && $counts['active'] === 3
                    && $counts['needs_recheck'] === 3
                    && $counts['negative_margin'] === 1
                    && $counts['expiring_soon'] === 1;
            });

        $this->actingAs($admin)->get(route('products.index', ['quick' => 'needs_recheck']))
            ->assertOk()
            ->assertSee('Kedaluwarsa')
            ->assertSee('Margin Nol')
            ->assertSee('Tanpa Riwayat')
            ->assertDontSee('Aktif')
            ->assertDontSee('Rugi');

        $this->actingAs($admin)->get(route('products.index', ['quick' => 'negative_margin']))
            ->assertOk()
            ->assertSee('Rugi')
            ->assertDontSee('Aktif');

        $this->actingAs($admin)->get(route('products.index', ['quick' => 'expiring_soon']))
            ->assertOk()
            ->assertSee('Segera')
            ->assertDontSee('Aktif');

        $this->actingAs($admin)->get(route('products.index', ['price_review' => 1]))
            ->assertOk()
            ->assertViewHas('quick', 'needs_recheck');
    }

    public function test_estimator_sees_selling_price_and_margin(): void
    {
        $estimator = $this->userWithRole('estimator');
        $supplier = Supplier::create(['name' => 'Supplier Uji', 'is_active' => true]);
        $product = $this->makeProduct(['name' => 'Produk Estimator', 'default_price' => 1000, 'cost_price' => 600]);
        $this->addCostLog($product, $estimator, 600, now()->addDays(10)->toDateString(), $supplier);

        $this->actingAs($estimator)->get(route('products.index'))
            ->assertOk()
            ->assertSee('Selling Price')
            ->assertSee('Rp 1.000')
            ->assertSee('Margin')
            ->assertSee('Review Prices');
    }

    public function test_bulk_update_creates_one_log_and_updates_cost(): void
    {
        $admin = $this->userWithRole('admin');
        $supplier = Supplier::create(['name' => 'Supplier Bulk', 'is_active' => true]);
        $product = $this->makeProduct(['name' => 'Produk Bulk', 'default_price' => 1000, 'cost_price' => 600]);
        $first = $this->addCostLog($product, $admin, 600, now()->addDays(10)->toDateString(), $supplier);

        $validUntil = now()->addDays(20)->toDateString();

        $this->actingAs($admin)->post(route('products.bulk-price-update'), [
            'prices' => [
                $product->id => [
                    'cost' => '750.000',
                    'supplier_id' => $supplier->id,
                    'valid_until' => $validUntil,
                ],
            ],
            'bulk' => 1,
            'quick' => 'needs_recheck',
        ])->assertRedirect();

        $product->refresh();
        $this->assertSame(750000.0, (float) $product->cost_price);
        $this->assertSame(2, $product->costLogs()->count());

        $latest = $product->costLogs()->first();
        $this->assertSame(750000.0, (float) $latest->price);
        $this->assertSame($supplier->id, $latest->supplier_id);
        $this->assertSame($validUntil, $latest->valid_until?->toDateString());
        $this->assertSame('ready', $latest->stock_status);
        $this->assertSame('2 years', $latest->warranty_info);
        $this->assertSame($admin->id, $latest->updated_by);

        // Simpan nilai yang sama lagi tidak boleh menambah log.
        $this->actingAs($admin)->post(route('products.bulk-price-update'), [
            'prices' => [
                $product->id => [
                    'cost' => '750.000',
                    'supplier_id' => $supplier->id,
                    'valid_until' => $validUntil,
                ],
            ],
            'bulk' => 1,
        ])->assertRedirect()->assertSessionHas('warning');

        $this->assertSame(2, $product->costLogs()->count());
    }

    public function test_bulk_update_rejects_product_without_history_and_invalid_supplier(): void
    {
        $admin = $this->userWithRole('admin');
        $supplier = Supplier::create(['name' => 'Supplier Bulk', 'is_active' => true]);
        $withoutHistory = $this->makeProduct(['name' => 'Tanpa Riwayat Bulk', 'cost_price' => 700]);

        $this->actingAs($admin)->post(route('products.bulk-price-update'), [
            'prices' => [
                $withoutHistory->id => [
                    'cost' => '800.000',
                    'supplier_id' => $supplier->id,
                    'valid_until' => now()->addDays(10)->toDateString(),
                ],
            ],
            'bulk' => 1,
        ])->assertSessionHasErrors('prices');

        $this->assertSame(0, $withoutHistory->costLogs()->count());
        $this->assertSame(700.0, (float) $withoutHistory->fresh()->cost_price);

        $withHistory = $this->makeProduct(['name' => 'Riwayat Bulk', 'cost_price' => 600]);
        $this->addCostLog($withHistory, $admin, 600, now()->addDays(10)->toDateString(), $supplier);

        $this->actingAs($admin)->post(route('products.bulk-price-update'), [
            'prices' => [
                $withHistory->id => [
                    'cost' => '650.000',
                    'supplier_id' => 999999,
                    'valid_until' => now()->addDays(10)->toDateString(),
                ],
            ],
            'bulk' => 1,
        ])->assertSessionHasErrors("prices.{$withHistory->id}.supplier_id");

        $this->assertSame(1, $withHistory->costLogs()->count());
        $this->assertSame(600.0, (float) $withHistory->fresh()->cost_price);
    }

    public function test_bulk_update_requires_cost_permission(): void
    {
        $sales = $this->userWithRole('sales');
        $supplier = Supplier::create(['name' => 'Supplier Bulk', 'is_active' => true]);
        $product = $this->makeProduct(['cost_price' => 600]);
        $this->addCostLog($product, $sales, 600, now()->addDays(10)->toDateString(), $supplier);

        $this->actingAs($sales)->post(route('products.bulk-price-update'), [
            'prices' => [
                $product->id => [
                    'cost' => '700.000',
                    'supplier_id' => $supplier->id,
                    'valid_until' => now()->addDays(10)->toDateString(),
                ],
            ],
            'bulk' => 1,
        ])->assertForbidden();

        $this->assertSame(1, $product->costLogs()->count());
    }
}
