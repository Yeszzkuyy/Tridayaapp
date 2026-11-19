<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPriceFormatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_product_price_inputs_accept_rupiah_format(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('products.create'))
            ->assertOk()
            ->assertSee('inputmode="numeric"', false);

        $this->actingAs($admin)->post(route('products.store'), [
            'name' => 'Produk Format Rupiah',
            'category' => 'barang',
            'unit' => 'pcs',
            'default_price' => '1.000.000',
            'cost_price' => '600.000',
            'is_active' => '1',
        ])->assertRedirect(route('products.index'));

        $product = Product::where('name', 'Produk Format Rupiah')->firstOrFail();

        $this->assertSame(1000000.0, (float) $product->default_price);
        $this->assertSame(600000.0, (float) $product->cost_price);

        $this->actingAs($admin)->get(route('products.edit', $product))
            ->assertOk()
            ->assertSee('value="1.000.000"', false)
            ->assertSee('value="600.000"', false);
    }

    public function test_product_can_be_saved_with_empty_cost_price(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('products.store'), [
            'name' => 'Produk Tanpa Harga Modal',
            'category' => 'barang',
            'unit' => 'pcs',
            'default_price' => '1.000.000',
            'cost_price' => '',
            'is_active' => '1',
        ])->assertRedirect(route('products.index'));

        $product = Product::where('name', 'Produk Tanpa Harga Modal')->firstOrFail();

        $this->assertSame(1000000.0, (float) $product->default_price);
        $this->assertNull($product->cost_price);
    }
}
