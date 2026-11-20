<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductPriceLog;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBulkPriceUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function product(string $name, array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => $name,
            'category' => 'barang',
            'unit' => 'pcs',
            'default_price' => 1_000_000,
            'cost_price' => 100_000,
            'is_active' => true,
            'status' => Product::STATUS_APPROVED,
        ], $attributes));
    }

    private function costLog(Product $product, Supplier $supplier, array $attributes = []): ProductPriceLog
    {
        return ProductPriceLog::create(array_merge([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'price' => $product->cost_price,
            'price_type' => 'cost',
            'currency' => 'IDR',
            'stock_status' => 'ready',
            'stock_eta' => null,
            'warranty_info' => '2 year distributor',
            'valid_until' => '2026-12-01',
            'updated_by' => User::factory()->create()->id,
        ], $attributes));
    }

    public function test_estimator_can_open_bulk_update_with_only_products_that_have_cost_history(): void
    {
        $estimator = User::factory()->create()->assignRole('estimator');
        $supplier = Supplier::create(['name' => 'Supplier Bulk']);
        $withHistory = $this->product('Switch With History');
        $latestLog = $this->costLog($withHistory, $supplier);
        $this->product('Switch Without History');

        $this->assertSame(100_000.0, (float) $latestLog->price);

        $this->actingAs($estimator)
            ->get(route('products.bulk-price'))
            ->assertOk()
            ->assertSee(__('Bulk Price Update'))
            ->assertSee(__('Previous Cost'))
            ->assertSee(__('New Cost'))
            ->assertSee(__('Review Changes'))
            ->assertSee('Switch With History')
            ->assertSee('value="100.000"', false)
            ->assertDontSee('Switch Without History')
            ->assertDontSee(__('Selling Price'))
            ->assertDontSee(__('Margin Value'));
    }

    public function test_estimator_bulk_update_uses_current_vat_rules_and_preserves_stock_and_warranty(): void
    {
        $estimator = User::factory()->create()->assignRole('estimator');
        $oldSupplier = Supplier::create(['name' => 'Old Bulk Supplier']);
        $newSupplier = Supplier::create(['name' => 'New Bulk Supplier']);
        $product = $this->product('Switch Bulk VAT');
        $this->costLog($product, $oldSupplier, [
            'price' => 100_000,
            'stock_status' => 'indent',
            'stock_eta' => '2 weeks',
            'warranty_info' => '3 year manufacturer',
        ]);
        config(['pricing.vat_rate' => 12]);

        $response = $this->actingAs($estimator)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('products.bulk-price.update'), [
                'items' => [
                    $product->id => [
                        'product_id' => $product->id,
                        'supplier_id' => $newSupplier->id,
                        'price' => '224.000',
                        'price_includes_tax' => '1',
                        'valid_until' => '2027-01-15',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'variant' => 'success',
                'message' => __(':count product prices updated.', ['count' => 1]),
            ])
            ->assertJsonStructure(['redirect']);
        $response->assertSessionMissing('success');

        $product->refresh();
        $latest = $product->latestCostLog()->firstOrFail();

        $this->assertSame(200_000.0, (float) $product->cost_price);
        $this->assertSame(200_000.0, (float) $latest->price);
        $this->assertSame($newSupplier->id, $latest->supplier_id);
        $this->assertSame('indent', $latest->stock_status);
        $this->assertSame('2 weeks', $latest->stock_eta);
        $this->assertSame('3 year manufacturer', $latest->warranty_info);
        $this->assertSame('2027-01-15', $latest->valid_until?->format('Y-m-d'));
        $this->assertSame(2, ProductPriceLog::where('product_id', $product->id)->count());
    }

    public function test_bulk_update_rejects_a_batch_with_invalid_supplier_without_partial_changes(): void
    {
        $estimator = User::factory()->create()->assignRole('estimator');
        $supplier = Supplier::create(['name' => 'Supplier Valid']);
        $first = $this->product('First Bulk Product');
        $second = $this->product('Second Bulk Product');
        $this->costLog($first, $supplier);
        $this->costLog($second, $supplier);

        $this->actingAs($estimator)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('products.bulk-price.update'), [
                'items' => [
                    $first->id => [
                        'product_id' => $first->id,
                        'supplier_id' => $supplier->id,
                        'price' => '150.000',
                        'valid_until' => '2027-01-01',
                    ],
                    $second->id => [
                        'product_id' => $second->id,
                        'supplier_id' => 999999,
                        'price' => '250.000',
                        'valid_until' => '2027-01-01',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(["items.{$second->id}.supplier_id"]);

        $this->assertSame(100_000.0, (float) $first->fresh()->cost_price);
        $this->assertSame(100_000.0, (float) $second->fresh()->cost_price);
        $this->assertSame(1, ProductPriceLog::where('product_id', $first->id)->count());
        $this->assertSame(1, ProductPriceLog::where('product_id', $second->id)->count());
    }

    public function test_bulk_update_rolls_back_if_a_product_lost_its_price_history(): void
    {
        $estimator = User::factory()->create()->assignRole('estimator');
        $supplier = Supplier::create(['name' => 'Supplier Bulk Rollback']);
        $first = $this->product('First Product With History');
        $second = $this->product('Second Product Without History');
        $this->costLog($first, $supplier);

        $this->actingAs($estimator)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('products.bulk-price.update'), [
                'items' => [
                    $first->id => [
                        'product_id' => $first->id,
                        'supplier_id' => $supplier->id,
                        'price' => '150.000',
                        'valid_until' => '2027-01-01',
                    ],
                    $second->id => [
                        'product_id' => $second->id,
                        'supplier_id' => $supplier->id,
                        'price' => '250.000',
                        'valid_until' => '2027-01-01',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(["items.{$second->id}.product_id"]);

        $this->assertSame(100_000.0, (float) $first->fresh()->cost_price);
        $this->assertSame(1, ProductPriceLog::where('product_id', $first->id)->count());
        $this->assertSame(0, ProductPriceLog::where('product_id', $second->id)->count());
    }

    public function test_bulk_validation_redirect_preserves_the_entered_price(): void
    {
        $estimator = User::factory()->create()->assignRole('estimator');
        $supplier = Supplier::create(['name' => 'Supplier Validation']);
        $product = $this->product('Bulk Validation Product');
        $this->costLog($product, $supplier);

        $response = $this->actingAs($estimator)
            ->from(route('products.bulk-price'))
            ->followingRedirects()
            ->post(route('products.bulk-price.update'), [
                'items' => [
                    $product->id => [
                        'product_id' => $product->id,
                        'supplier_id' => $supplier->id,
                        'price' => '0',
                        'valid_until' => '2027-01-01',
                    ],
                ],
            ]);

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/<input\b(?=[^>]*\bname="items\['.$product->id.'\]\[price\]")(?=[^>]*\bvalue="0")[^>]*>/',
            $response->getContent(),
        );
    }

    public function test_bulk_validation_preserves_other_price_edits_when_valid_until_is_invalid(): void
    {
        $estimator = User::factory()->create()->assignRole('estimator');
        $supplier = Supplier::create(['name' => 'Supplier Validation']);
        $product = $this->product('Bulk Validation Product');
        $this->costLog($product, $supplier);

        $response = $this->actingAs($estimator)
            ->from(route('products.bulk-price'))
            ->followingRedirects()
            ->post(route('products.bulk-price.update'), [
                'items' => [
                    $product->id => [
                        'product_id' => $product->id,
                        'supplier_id' => $supplier->id,
                        'price' => '125000',
                        'valid_until' => 'not-a-date',
                    ],
                ],
            ]);

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/<input\b(?=[^>]*\bname="items\['.$product->id.'\]\[price\]")(?=[^>]*\bvalue="125\.000")[^>]*>/',
            $response->getContent(),
        );
    }

    public function test_sales_cannot_access_bulk_cost_update_routes(): void
    {
        $sales = User::factory()->create()->assignRole('sales');

        $this->actingAs($sales)
            ->get(route('products.bulk-price'))
            ->assertForbidden();

        $this->actingAs($sales)
            ->post(route('products.bulk-price.update'), [])
            ->assertForbidden();
    }

    public function test_bulk_update_skips_unchanged_prices_without_adding_a_log(): void
    {
        $estimator = User::factory()->create()->assignRole('estimator');
        $supplier = Supplier::create(['name' => 'Supplier Unchanged']);
        $product = $this->product('Switch Unchanged');
        $this->costLog($product, $supplier, [
            'price' => 100_000,
            'valid_until' => '2027-01-01',
        ]);

        $this->actingAs($estimator)
            ->post(route('products.bulk-price.update'), [
                'items' => [
                    $product->id => [
                        'product_id' => $product->id,
                        'supplier_id' => $supplier->id,
                        'price' => '100.000',
                        'valid_until' => '2027-01-01',
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('warning', __('No price changes were saved.'));

        $this->assertSame(1, ProductPriceLog::where('product_id', $product->id)->count());
        $this->assertSame(100_000.0, (float) $product->fresh()->cost_price);
    }
}
