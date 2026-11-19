<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PriceRequest;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstantSaveResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole('admin');
    }

    private function ajax(): array
    {
        return [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }

    public function test_ajax_product_save_returns_json_and_consumes_flash(): void
    {
        $response = $this->actingAs($this->admin())
            ->withHeaders($this->ajax())
            ->post(route('products.store'), [
                'name' => 'Produk Simpan Cepat',
                'category' => 'barang',
                'unit' => 'pcs',
                'default_price' => '1.000.000',
                'cost_price' => '600.000',
                'is_active' => '1',
            ]);

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'variant' => 'success',
                'message' => __('Product added successfully.'),
            ])
            ->assertJsonStructure(['redirect']);

        $this->assertStringContainsString(route('products.index'), $response->json('redirect'));
        $response->assertSessionMissing('success');

        $this->assertDatabaseHas('products', ['name' => 'Produk Simpan Cepat']);
    }

    public function test_ajax_product_save_returns_validation_errors_as_json(): void
    {
        $this->actingAs($this->admin())
            ->withHeaders($this->ajax())
            ->post(route('products.store'), [
                'name' => '',
                'category' => 'barang',
                'unit' => 'pcs',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_ajax_sales_price_request_cancel_returns_json(): void
    {
        $sales = User::factory()->create()->assignRole('sales');
        $customer = Customer::create(['name' => 'PT Simpan Cepat Sales']);
        $request = PriceRequest::create([
            'customer_id' => $customer->id,
            'requested_by' => $sales->id,
            'quotation_path' => 'price-requests/qa/quotation.pdf',
            'status' => 'waiting_lead',
        ]);

        $response = $this->actingAs($sales)
            ->withHeaders($this->ajax())
            ->post(route('sales.price-requests.cancel', $request));

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'message' => __('Price request canceled.'),
            ])
            ->assertJsonStructure(['redirect']);

        $response->assertSessionMissing('success');
        $this->assertSame('cancelled', $request->fresh()->status);
    }

    public function test_non_ajax_product_save_still_redirects_with_flash(): void
    {
        $this->actingAs($this->admin())
            ->post(route('products.store'), [
                'name' => 'Produk Jalur Lama',
                'category' => 'barang',
                'unit' => 'pcs',
                'is_active' => '1',
            ])
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('success', __('Product added successfully.'));

        $this->assertSame(1, Product::where('name', 'Produk Jalur Lama')->count());
    }
}
