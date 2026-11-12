<?php

namespace Tests\Feature;

use App\Http\Controllers\NotificationController;
use App\Models\Customer;
use App\Models\EstimatorRequest;
use App\Models\Product;
use App\Models\ProductPriceLog;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\EstimatorRequestAnsweredNotification;
use App\Notifications\EstimatorRequestCreatedNotification;
use App\Notifications\EstimatorRequestRevisionNotification;
use App\Notifications\PurchasingHandoffNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class EstimatorRequestTest extends TestCase
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

    private function customer(string $name = 'PT Estimator Test'): Customer
    {
        return Customer::create(['name' => $name]);
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Produk Estimator '.uniqid(),
            'category' => 'barang',
            'unit' => 'pcs',
            'default_price' => 1000000,
            'cost_price' => 600000,
            'is_active' => true,
            'status' => Product::STATUS_APPROVED,
        ], $attributes));
    }

    private function makeRequest(User $sales, array $overrides = []): EstimatorRequest
    {
        return EstimatorRequest::create(array_merge([
            'customer_id' => $this->customer()->id,
            'requested_by' => $sales->id,
            'status' => 'submitted',
        ], $overrides));
    }

    public function test_sales_can_open_create_form(): void
    {
        $sales = $this->userWithRole('sales');

        $this->actingAs($sales)
            ->get(route('estimator.requests.create'))
            ->assertOk()
            ->assertSee('New Product Price Request')
            ->assertSee('Requested Items');
    }

    public function test_request_detail_page_renders_for_estimator_and_owner(): void
    {
        $sales = $this->userWithRole('sales');
        $estimator = $this->userWithRole('estimator');
        $otherSales = $this->userWithRole('sales');
        $request = $this->makeRequest($sales, [
            'customer_id' => $this->customer('PT Detail Estimator')->id,
        ]);
        $request->items()->create([
            'description' => 'Kamera Detail',
            'quantity' => 2,
            'unit' => 'pcs',
        ]);

        $this->actingAs($estimator)
            ->get(route('estimator.requests.show', $request))
            ->assertOk()
            ->assertSee('PT Detail Estimator')
            ->assertSee('Kamera Detail')
            ->assertSee(__('Send Prices'));

        $this->actingAs($sales)
            ->get(route('estimator.requests.show', $request))
            ->assertOk()
            ->assertSee('Kamera Detail')
            ->assertDontSee(__('Send Prices'));

        $this->actingAs($otherSales)
            ->get(route('estimator.requests.show', $request))
            ->assertForbidden();
    }

    public function test_estimator_notifications_appear_in_notification_center(): void
    {
        $sales = $this->userWithRole('sales');
        $estimator = $this->userWithRole('estimator');
        $request = $this->makeRequest($sales);

        $estimator->notify(new EstimatorRequestCreatedNotification($request));
        $sales->notify(new EstimatorRequestAnsweredNotification($request));

        $estimatorItems = collect(NotificationController::itemsFor($estimator))->keyBy('type');
        $this->assertArrayHasKey('estimator_request_created', $estimatorItems);
        $this->assertSame(__('Estimator'), $estimatorItems['estimator_request_created']['app']);
        $this->assertSame(__('New product price request'), $estimatorItems['estimator_request_created']['title']);

        $salesItems = collect(NotificationController::itemsFor($sales))->keyBy('type');
        $this->assertArrayHasKey('estimator_request_answered', $salesItems);
        $this->assertSame(__('Product price reply is available'), $salesItems['estimator_request_answered']['title']);
    }

    public function test_sales_creates_product_price_request_with_items(): void
    {
        Notification::fake();
        Storage::fake('private');

        $sales = $this->userWithRole('sales');
        $estimator = $this->userWithRole('estimator');
        $customer = $this->customer('PT Request Produk');
        $product = $this->product(['name' => 'Kamera Request']);

        $this->actingAs($sales)->post(route('estimator.requests.store'), [
            'customer_id' => $customer->id,
            'notes' => 'Mohon harga terbaik.',
            'attachment' => UploadedFile::fake()->create('boq.pdf', 100, 'application/pdf'),
            'items' => [
                ['product_id' => $product->id, 'description' => 'Kamera Request', 'quantity' => 2, 'unit' => 'pcs'],
                ['product_id' => null, 'description' => 'Jasa konfigurasi', 'quantity' => 1, 'unit' => 'lot'],
            ],
        ])->assertRedirect();

        $request = EstimatorRequest::with('items')->firstOrFail();
        $this->assertSame($customer->id, $request->customer_id);
        $this->assertSame($sales->id, $request->requested_by);
        $this->assertSame('submitted', $request->status);
        $this->assertCount(2, $request->items);
        $this->assertNotNull($request->attachment_path);
        Storage::disk('private')->assertExists($request->attachment_path);
        Notification::assertSentTo($estimator, EstimatorRequestCreatedNotification::class);
    }

    public function test_sales_queue_only_shows_own_requests_and_estimator_sees_all(): void
    {
        $salesA = $this->userWithRole('sales');
        $salesB = $this->userWithRole('sales');
        $estimator = $this->userWithRole('estimator');

        $this->makeRequest($salesA, ['customer_id' => $this->customer('PT Punya A')->id]);
        $this->makeRequest($salesB, ['customer_id' => $this->customer('PT Punya B')->id]);

        $this->actingAs($salesA)
            ->get(route('estimator.requests.index'))
            ->assertOk()
            ->assertSee('PT Punya A')
            ->assertDontSee('PT Punya B');

        $this->actingAs($estimator)
            ->get(route('estimator.requests.index'))
            ->assertOk()
            ->assertSee('PT Punya A')
            ->assertSee('PT Punya B');
    }

    public function test_estimator_answers_items_and_updates_product_cost(): void
    {
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $estimator = $this->userWithRole('estimator');
        $supplier = Supplier::create(['name' => 'Vendor Estimator']);
        $product = $this->product(['name' => 'Switch Estimator', 'cost_price' => 600000, 'default_price' => 1000000]);
        $request = $this->makeRequest($sales, [
            'customer_id' => $this->customer('PT Jawab Harga')->id,
            'status' => 'submitted',
        ]);
        $productItem = $request->items()->create([
            'product_id' => $product->id,
            'description' => 'Switch Estimator',
            'quantity' => 1,
            'unit' => 'pcs',
        ]);
        $manualItem = $request->items()->create([
            'product_id' => null,
            'description' => 'Jasa konfigurasi',
            'quantity' => 1,
            'unit' => 'lot',
        ]);

        $this->actingAs($estimator)->post(route('estimator.requests.answer', $request), [
            'items' => [
                [
                    'id' => $productItem->id,
                    'supplier_id' => $supplier->id,
                    'price' => 725000,
                    'stock_status' => 'ready',
                    'warranty_info' => '1 year distributor',
                    'valid_until' => '2026-12-31',
                    'notes' => 'Ready',
                ],
                [
                    'id' => $manualItem->id,
                    'supplier_id' => $supplier->id,
                    'price' => 150000,
                    'stock_status' => 'indent',
                    'stock_eta' => '2 weeks',
                    'warranty_info' => 'N/A',
                    'notes' => 'Jasa',
                ],
            ],
        ])->assertRedirect();

        $request->refresh();
        $this->assertSame('answered', $request->status);
        $this->assertSame($estimator->id, $request->handled_by);

        $productItem->refresh();
        $this->assertSame(725000.0, (float) $productItem->price);
        $this->assertSame($supplier->id, $productItem->supplier_id);
        $this->assertSame('ready', $productItem->stock_status);
        $this->assertSame('1 year distributor', $productItem->warranty_info);
        $this->assertSame('2026-12-31', $productItem->valid_until?->format('Y-m-d'));
        $this->assertSame('answered', $productItem->status);

        $manualItem->refresh();
        $this->assertSame(150000.0, (float) $manualItem->price);
        $this->assertSame('indent', $manualItem->stock_status);
        $this->assertSame('2 weeks', $manualItem->stock_eta);

        $this->assertSame(725000.0, (float) $product->fresh()->cost_price);
        $this->assertSame(1000000.0, (float) $product->fresh()->default_price);
        $this->assertDatabaseHas('product_price_logs', [
            'product_id' => $product->id,
            'estimator_request_item_id' => $productItem->id,
            'price' => 725000,
            'price_type' => 'cost',
            'stock_status' => 'ready',
            'warranty_info' => '1 year distributor',
        ]);
        $this->assertDatabaseMissing('product_price_logs', [
            'estimator_request_item_id' => $manualItem->id,
        ]);

        Notification::assertSentTo($sales, EstimatorRequestAnsweredNotification::class);
    }

    public function test_answer_requires_supplier_stock_and_warranty(): void
    {
        $sales = $this->userWithRole('sales');
        $estimator = $this->userWithRole('estimator');
        $request = $this->makeRequest($sales);
        $item = $request->items()->create([
            'description' => 'Item Validasi',
            'quantity' => 1,
            'unit' => 'pcs',
        ]);

        $this->actingAs($estimator)->post(route('estimator.requests.answer', $request), [
            'items' => [[
                'id' => $item->id,
                'price' => 100000,
            ]],
        ])->assertSessionHasErrors([
            'items.0.supplier_id',
            'items.0.stock_status',
            'items.0.warranty_info',
        ]);

        $this->assertSame('submitted', $request->fresh()->status);
    }

    public function test_indent_item_requires_stock_estimate(): void
    {
        $sales = $this->userWithRole('sales');
        $estimator = $this->userWithRole('estimator');
        $supplier = Supplier::create(['name' => 'Vendor Indent']);
        $request = $this->makeRequest($sales);
        $item = $request->items()->create([
            'description' => 'Item Indent',
            'quantity' => 1,
            'unit' => 'pcs',
        ]);

        $this->actingAs($estimator)->post(route('estimator.requests.answer', $request), [
            'items' => [[
                'id' => $item->id,
                'supplier_id' => $supplier->id,
                'price' => 100000,
                'stock_status' => 'indent',
                'warranty_info' => '1 year',
            ]],
        ])->assertSessionHasErrors('items.0.stock_eta');
    }

    public function test_sales_accepts_answered_prices(): void
    {
        $sales = $this->userWithRole('sales');
        $estimator = $this->userWithRole('estimator');
        $request = $this->makeRequest($sales, ['status' => 'answered']);
        $item = $request->items()->create([
            'description' => 'Item Accept',
            'quantity' => 1,
            'unit' => 'pcs',
            'price' => 100000,
            'status' => 'answered',
        ]);

        $this->actingAs($sales)
            ->post(route('estimator.requests.accept', $request))
            ->assertRedirect();

        $this->assertSame('completed', $request->fresh()->status);
        $this->assertNotNull($request->fresh()->completed_at);
        $this->assertSame('accepted', $item->fresh()->status);
    }

    public function test_sales_requests_revision(): void
    {
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $estimator = $this->userWithRole('estimator');
        $request = $this->makeRequest($sales, ['status' => 'answered']);
        $item = $request->items()->create([
            'description' => 'Item Revisi',
            'quantity' => 1,
            'unit' => 'pcs',
            'price' => 100000,
            'status' => 'answered',
        ]);

        $this->actingAs($sales)->post(route('estimator.requests.revision', $request), [
            'reason' => 'Harga masih di atas target.',
        ])->assertRedirect();

        $request->refresh();
        $this->assertSame('revision', $request->status);
        $this->assertSame('Harga masih di atas target.', $request->revision_reason);
        $this->assertNotNull($request->revision_requested_at);
        $this->assertSame('revision', $item->fresh()->status);
        Notification::assertSentTo($estimator, EstimatorRequestRevisionNotification::class);

        $this->actingAs($estimator)->get(route('estimator.requests.show', $request))
            ->assertOk()
            ->assertSee(__('Revision Required'))
            ->assertSee('Harga masih di atas target.')
            ->assertSee(__('Send Prices'));
    }

    public function test_estimator_queue_separates_new_requests_with_process_action(): void
    {
        $sales = $this->userWithRole('sales');
        $estimator = $this->userWithRole('estimator');
        $newRequest = $this->makeRequest($sales, [
            'customer_id' => $this->customer('PT Antrean Baru')->id,
        ]);
        $newRequest->items()->create([
            'description' => 'Kamera Antrean',
            'quantity' => 1,
            'unit' => 'pcs',
        ]);
        $answered = $this->makeRequest($sales, [
            'customer_id' => $this->customer('PT Sudah Dikerjakan')->id,
            'status' => 'answered',
        ]);
        $answered->items()->create([
            'description' => 'Kamera Selesai',
            'quantity' => 1,
            'unit' => 'pcs',
            'price' => 100000,
            'status' => 'answered',
        ]);

        $this->actingAs($estimator)->get(route('estimator.requests.index'))
            ->assertOk()
            ->assertSee(__('New Requests'))
            ->assertSee('PT Antrean Baru')
            ->assertSee(__('Process Request'))
            ->assertSee('PT Sudah Dikerjakan');
    }

    public function test_estimator_updates_cost_price_from_pricelist_without_touching_selling_price(): void
    {
        $estimator = $this->userWithRole('estimator');
        $sales = $this->userWithRole('sales');
        $supplier = Supplier::create(['name' => 'Vendor Pricelist Baru']);
        $product = $this->product([
            'name' => 'Kamera Pricelist Baru',
            'cost_price' => 600000,
            'default_price' => 1000000,
        ]);

        $this->actingAs($estimator)->get(route('products.price.edit', $product))
            ->assertOk()
            ->assertSee(__('Update Cost Price'));

        $this->actingAs($estimator)->post(route('products.price.update', $product), [
            'supplier_id' => $supplier->id,
            'price' => 555000,
            'stock_status' => 'indent',
            'stock_eta' => '3 weeks',
            'warranty_info' => '1 year distributor',
            'valid_until' => '2027-01-31',
            'notes' => 'Update vendor',
        ])->assertRedirect(route('products.index'));

        $product->refresh();
        $this->assertSame(555000.0, (float) $product->cost_price);
        $this->assertSame(1000000.0, (float) $product->default_price);

        $log = ProductPriceLog::where('product_id', $product->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('cost', $log->price_type);
        $this->assertNull($log->estimator_request_item_id);
        $this->assertSame($supplier->id, $log->supplier_id);
        $this->assertSame('2027-01-31', $log->valid_until?->format('Y-m-d'));
        $this->assertSame($estimator->id, $log->updated_by);

        $this->actingAs($sales)->get(route('products.price.edit', $product))->assertForbidden();
    }

    public function test_answered_request_shows_edit_prices_instead_of_send_button(): void
    {
        $sales = $this->userWithRole('sales');
        $estimator = $this->userWithRole('estimator');
        $request = $this->makeRequest($sales, ['status' => 'answered']);
        $request->items()->create([
            'description' => 'Kamera Edit',
            'quantity' => 1,
            'unit' => 'pcs',
            'price' => 90000,
            'status' => 'answered',
        ]);

        $this->actingAs($estimator)->get(route('estimator.requests.show', $request))
            ->assertOk()
            ->assertSee(__('Edit Prices'))
            ->assertSee('style="display: none"', false);
    }

    public function test_estimator_can_open_supplier_directory_but_sales_cannot(): void
    {
        $sales = $this->userWithRole('sales');
        $estimator = $this->userWithRole('estimator');
        Supplier::create([
            'name' => 'Vendor Kontak',
            'contact_person' => 'Budi',
            'phone' => '08123456789',
            'email' => 'budi@vendor.test',
        ]);

        $this->actingAs($estimator)->get(route('estimator.suppliers'))
            ->assertOk()
            ->assertSee('Vendor Kontak')
            ->assertSee('Budi')
            ->assertSee('budi@vendor.test');

        $this->actingAs($sales)->get(route('estimator.suppliers'))->assertForbidden();
    }

    public function test_customer_po_notifications_are_hidden_from_estimator(): void
    {
        $estimator = $this->userWithRole('estimator');

        $estimator->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => PurchasingHandoffNotification::class,
            'data' => ['type' => 'purchasing_handoff', 'title' => 'PT PO Lama'],
            'read_at' => null,
        ]);

        $this->assertSame([], NotificationController::itemsFor($estimator));
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_estimator_sees_pricelist_title_and_latest_valid_until(): void
    {
        $estimator = $this->userWithRole('estimator');
        $product = $this->product(['name' => 'Kamera Pricelist']);
        $supplier = Supplier::create(['name' => 'Vendor Pricelist']);

        ProductPriceLog::create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'price' => 450000,
            'price_type' => 'cost',
            'currency' => 'IDR',
            'stock_status' => 'ready',
            'warranty_info' => '1 year',
            'valid_until' => '2026-12-31',
            'updated_by' => $estimator->id,
        ]);

        $this->actingAs($estimator)->get(route('products.index'))
            ->assertOk()
            ->assertSee(__('Pricelist'))
            ->assertSee('Vendor Pricelist')
            ->assertSee(__('Valid until :date', ['date' => '31 Dec 2026']));
    }

    public function test_sales_cannot_answer_and_estimator_cannot_accept(): void
    {
        $sales = $this->userWithRole('sales');
        $estimator = $this->userWithRole('estimator');
        $supplier = Supplier::create(['name' => 'Vendor Akses']);
        $request = $this->makeRequest($sales);
        $item = $request->items()->create([
            'description' => 'Item Akses',
            'quantity' => 1,
            'unit' => 'pcs',
        ]);

        $this->actingAs($sales)->post(route('estimator.requests.answer', $request), [
            'items' => [[
                'id' => $item->id,
                'supplier_id' => $supplier->id,
                'price' => 100000,
                'stock_status' => 'ready',
                'warranty_info' => '1 year',
            ]],
        ])->assertForbidden();

        $this->actingAs($estimator)
            ->post(route('estimator.requests.accept', $request))
            ->assertForbidden();
    }

    public function test_estimator_export_and_sales_forbidden(): void
    {
        $sales = $this->userWithRole('sales');
        $estimator = $this->userWithRole('estimator');
        $this->makeRequest($sales, [
            'customer_id' => $this->customer('PT Export Estimator')->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($estimator)
            ->get(route('estimator.requests.export-excel'))
            ->assertOk();

        $this->actingAs($sales)
            ->get(route('estimator.requests.export-excel'))
            ->assertForbidden();
    }

    public function test_price_history_is_visible_to_estimator_only(): void
    {
        $sales = $this->userWithRole('sales');
        $estimator = $this->userWithRole('estimator');
        $product = $this->product(['name' => 'Produk History']);
        $supplier = Supplier::create(['name' => 'Vendor History']);
        $request = $this->makeRequest($sales, ['status' => 'answered']);
        $item = $request->items()->create([
            'product_id' => $product->id,
            'description' => 'Produk History',
            'quantity' => 1,
            'unit' => 'pcs',
            'status' => 'answered',
        ]);
        ProductPriceLog::create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'price' => 700000,
            'price_type' => 'cost',
            'currency' => 'IDR',
            'stock_status' => 'ready',
            'warranty_info' => '1 year',
            'estimator_request_item_id' => $item->id,
            'updated_by' => $estimator->id,
        ]);

        $this->actingAs($estimator)
            ->get(route('estimator.price-history'))
            ->assertOk()
            ->assertSee('Produk History')
            ->assertSee('700.000');

        $this->actingAs($sales)
            ->get(route('estimator.price-history'))
            ->assertForbidden();
    }
}
