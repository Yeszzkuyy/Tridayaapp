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
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProcurementQueuesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_hub_shows_receipt_delivery_and_ready_to_close_queues(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $customer = Customer::create(['name' => 'PT Queue', 'pt_group' => 'NTI']);
        $workType = WorkType::firstOrCreate(['name' => 'Queue Work']);

        $project = Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Queue Procurement',
            'requires_installation' => false,
            'progress' => 100,
        ]);

        $supplier = Supplier::create(['name' => 'PT Queue Supplier', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Queue Switch',
            'sku' => 'QUEUE-'.uniqid(),
            'category' => 'barang',
            'unit' => 'unit',
            'status' => Product::STATUS_APPROVED,
            'is_active' => true,
        ]);

        $order = SupplierPurchaseOrder::create([
            'po_number' => 'SPO-QUEUE-'.uniqid(),
            'supplier_id' => $supplier->id,
            'project_id' => $project->id,
            'issue_date' => now()->toDateString(),
            'status' => 'ordered',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'item_name' => $product->name,
            'unit' => 'unit',
            'quantity_ordered' => 5,
            'quantity_received' => 5,
            'quantity_delivered' => 2,
            'unit_price' => 100,
            'subtotal' => 500,
        ]);

        GoodsReceipt::create([
            'receipt_number' => 'GR-QUEUE-'.uniqid(),
            'supplier_purchase_order_id' => $order->id,
            'supplier_id' => $supplier->id,
            'project_id' => $project->id,
            'received_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        // Proyek lain yang seluruh syarat closing-nya sudah terpenuhi.
        $ready = Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Queue Ready Close',
            'requires_installation' => false,
            'progress' => 100,
        ]);
        GoodsDelivery::create([
            'delivery_number' => 'DO-QUEUE-'.uniqid(),
            'project_id' => $ready->id,
            'customer_id' => $customer->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'delivered',
        ]);
        Invoice::create([
            'invoice_number' => 'INV-QUEUE-'.uniqid(),
            'customer_id' => $customer->id,
            'project_id' => $ready->id,
            'amount' => 100,
            'paid_amount' => 100,
            'status' => 'paid',
            'issue_date' => now()->toDateString(),
        ]);

        // Invoice partial untuk label Finance.
        Invoice::create([
            'invoice_number' => 'INV-PARTIAL-'.uniqid(),
            'customer_id' => $customer->id,
            'amount' => 100,
            'paid_amount' => 40,
            'status' => 'partial',
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($admin)->get(route('admin.hub'))
            ->assertOk()
            ->assertSee(__('Receipts Waiting Verification'))
            ->assertSee(__('Received Goods Not Fully Delivered'))
            ->assertSee(__('Projects Ready to Close'))
            ->assertSee(__('Partial'))
            ->assertSee(__('Lewat '));
    }
}
