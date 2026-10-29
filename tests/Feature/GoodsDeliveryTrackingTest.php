<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GoodsDelivery;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Models\User;
use App\Models\WorkType;
use App\Services\AdminService;
use App\Services\ProcurementService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoodsDeliveryTrackingTest extends TestCase
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

    private function makeProject(bool $requiresInstallation = false): Project
    {
        $customer = Customer::create([
            'name' => 'PT Delivery Tracking '.uniqid(),
            'pt_group' => 'NTI',
        ]);

        $workType = WorkType::firstOrCreate(['name' => 'Instalasi & Aktivasi']);

        return Project::withoutEvents(fn () => Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Delivery '.uniqid(),
            'project_code' => 'PRJ-'.uniqid(),
            'progress' => 100,
            'requires_installation' => $requiresInstallation,
        ]));
    }

    private function makeOrder(Project $project, float $quantity = 10): SupplierPurchaseOrder
    {
        $supplier = Supplier::create([
            'code' => 'SUP-'.uniqid(),
            'name' => 'PT Supplier '.uniqid(),
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Switch '.uniqid(),
            'sku' => 'SW-'.uniqid(),
            'category' => 'barang',
            'unit' => 'unit',
            'default_price' => 1000,
            'cost_price' => 800,
            'has_serial_number' => false,
            'status' => Product::STATUS_APPROVED,
            'is_active' => true,
        ]);

        $order = SupplierPurchaseOrder::create([
            'po_number' => 'SPO-'.uniqid(),
            'supplier_id' => $supplier->id,
            'project_id' => $project->id,
            'issue_date' => now()->toDateString(),
            'status' => 'ordered',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'item_name' => $product->name,
            'unit' => 'unit',
            'quantity_ordered' => $quantity,
            'quantity_received' => 0,
            'quantity_delivered' => 0,
            'unit_price' => 800,
            'subtotal' => $quantity * 800,
        ]);

        $order->recalculate();

        return $order->fresh(['items']);
    }

    private function receive(SupplierPurchaseOrder $order, float $quantity): void
    {
        $receipt = GoodsReceipt::create([
            'receipt_number' => 'GR-'.uniqid(),
            'supplier_purchase_order_id' => $order->id,
            'supplier_id' => $order->supplier_id,
            'project_id' => $order->project_id,
            'received_date' => now()->toDateString(),
            'received_by' => User::first()?->id,
            'status' => 'verified',
        ]);

        $receipt->items()->create([
            'supplier_purchase_order_item_id' => $order->items->first()->id,
            'product_id' => $order->items->first()->product_id,
            'quantity_received' => $quantity,
        ]);

        app(ProcurementService::class)->recalculatePurchaseOrderReceipts($order->fresh('items'));
    }

    public function test_delivery_cannot_exceed_received_goods_and_tracks_delivered_quantity(): void
    {
        $admin = $this->userWithRole('admin');
        $project = $this->makeProject();
        $order = $this->makeOrder($project, 10);
        $item = $order->items->first();

        $this->receive($order, 6);
        $order->refresh()->load('items');
        $item = $order->items->first();

        $this->actingAs($admin)
            ->get(route('admin.goods-deliveries.create', ['project_id' => $project->id]))
            ->assertOk()
            ->assertSee(__('Available'));

        $this->actingAs($admin)->post(route('admin.goods-deliveries.store'), [
            'project_id' => $project->id,
            'delivery_date' => now()->toDateString(),
            'items' => [[
                'supplier_purchase_order_item_id' => $item->id,
                'quantity' => 7,
            ]],
        ])->assertSessionHasErrors('items.0.quantity');

        $this->assertSame(0, GoodsDelivery::count());

        $this->actingAs($admin)->post(route('admin.goods-deliveries.store'), [
            'project_id' => $project->id,
            'delivery_date' => now()->toDateString(),
            'items' => [[
                'supplier_purchase_order_item_id' => $item->id,
                'quantity' => 6,
            ]],
        ])->assertRedirect();

        $this->assertSame(6.0, (float) $item->fresh()->quantity_delivered);
        $this->assertSame(0.0, (float) $item->fresh()->quantityAvailable());

        // Barang berikutnya belum diterima: tidak boleh dikirim.
        $this->actingAs($admin)->post(route('admin.goods-deliveries.store'), [
            'project_id' => $project->id,
            'delivery_date' => now()->toDateString(),
            'items' => [[
                'supplier_purchase_order_item_id' => $item->id,
                'quantity' => 1,
            ]],
        ])->assertSessionHasErrors('items.0.quantity');

        // Terima sisa 4, lalu kirim 4.
        $this->receive($order->fresh('items'), 4);
        $this->actingAs($admin)->post(route('admin.goods-deliveries.store'), [
            'project_id' => $project->id,
            'delivery_date' => now()->toDateString(),
            'items' => [[
                'supplier_purchase_order_item_id' => $item->id,
                'quantity' => 4,
            ]],
        ])->assertRedirect();

        $this->assertSame(10.0, (float) $item->fresh()->quantity_delivered);
        $this->assertSame(0.0, (float) $item->fresh()->quantityAvailable());
    }

    public function test_project_cannot_close_while_delivery_obligation_remains(): void
    {
        $admin = $this->userWithRole('admin');
        $project = $this->makeProject(false);
        $order = $this->makeOrder($project, 10);
        $item = $order->items->first();

        $this->receive($order, 10);
        $order->refresh()->load('items');
        $item = $order->items->first();

        $invoice = Invoice::create([
            'invoice_number' => 'INV-'.uniqid(),
            'customer_id' => $project->customer_id,
            'project_id' => $project->id,
            'amount' => 1000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin);
        app(AdminService::class)->createPayment([
            'invoice_id' => $invoice->id,
            'amount' => 1000,
            'payment_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)->post(route('admin.goods-deliveries.store'), [
            'project_id' => $project->id,
            'delivery_date' => now()->toDateString(),
            'items' => [[
                'supplier_purchase_order_item_id' => $item->id,
                'quantity' => 3,
            ]],
        ])->assertRedirect();

        $first = GoodsDelivery::firstOrFail();
        $this->actingAs($admin)
            ->post(route('admin.goods-deliveries.status', $first), ['status' => 'delivered'])
            ->assertRedirect();

        $this->assertNull($project->fresh()->commercial_closed_at);

        $this->actingAs($admin)->post(route('admin.goods-deliveries.store'), [
            'project_id' => $project->id,
            'delivery_date' => now()->toDateString(),
            'items' => [[
                'supplier_purchase_order_item_id' => $item->id,
                'quantity' => 7,
            ]],
        ])->assertRedirect();

        $second = GoodsDelivery::latest('id')->firstOrFail();
        $this->actingAs($admin)
            ->post(route('admin.goods-deliveries.status', $second), ['status' => 'delivered'])
            ->assertRedirect();

        $this->assertNotNull($project->fresh()->commercial_closed_at);
    }

    public function test_legacy_manual_delivery_without_supplier_po_is_still_allowed(): void
    {
        $admin = $this->userWithRole('admin');
        $project = $this->makeProject(false);

        $this->actingAs($admin)->post(route('admin.goods-deliveries.store'), [
            'project_id' => $project->id,
            'delivery_date' => now()->toDateString(),
            'items' => [[
                'item_name' => 'Legacy item',
                'quantity' => 1,
                'unit' => 'unit',
            ]],
        ])->assertRedirect();

        $this->assertSame(1, GoodsDelivery::count());
    }
}
