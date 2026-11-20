<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EstimatorRequest;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadTask;
use App\Models\LeadTaskAttachment;
use App\Models\LeadTaskItem;
use App\Models\Product;
use App\Models\ProductPriceLog;
use App\Models\Proposal;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeadTaskQuotationItemsTest extends TestCase
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

    private function makeLead(?User $assignee = null): Lead
    {
        $customer = Customer::create(['name' => 'PT Item Quotation '.uniqid()]);
        LeadActivity::query()->delete();

        return Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'warm',
            'assigned_to' => $assignee?->id,
            'incoming_date' => today()->toDateString(),
        ]);
    }

    private function makeTask(Lead $lead, User $sales, User $inside): LeadTask
    {
        return LeadTask::create([
            'lead_id' => $lead->id,
            'title' => 'Buatkan quotation',
            'assigned_to' => $inside->id,
            'created_by' => $sales->id,
        ]);
    }

    private function addAttachment(LeadTask $task, User $inside): LeadTaskAttachment
    {
        Storage::fake('private');

        $this->actingAs($inside)->post(route('lead-tasks.attachments.store', $task), [
            'attachment' => UploadedFile::fake()->create('quotation.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        return $task->attachments()->firstOrFail();
    }

    private function makeItem(LeadTask $task, array $overrides = []): LeadTaskItem
    {
        $data = array_merge([
            'description' => 'Cisco IP Phone 780',
            'quantity' => 1,
            'unit' => 'pcs',
            'unit_price' => 100000,
            'discount' => 0,
        ], $overrides);

        return LeadTaskItem::create([
            ...$data,
            'lead_task_id' => $task->id,
            'subtotal' => LeadTaskItem::subtotalFor(
                (float) $data['quantity'],
                (float) $data['unit_price'],
                (float) $data['discount'],
            ),
        ]);
    }

    private function product(string $name = 'Produk Uji', array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => $name,
            'category' => 'barang',
            'unit' => 'pcs',
            'default_price' => 100000,
            'is_active' => true,
            'status' => Product::STATUS_APPROVED,
        ], $overrides));
    }

    public function test_inside_sales_can_add_quotation_item(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $lead = $this->makeLead($sales);
        $task = $this->makeTask($lead, $sales, $inside);

        $this->actingAs($inside)->post(route('lead-tasks.items.store', $task), [
            'description' => 'Cisco IP Phone 780',
            'quantity' => 2,
            'unit' => 'pcs',
            'unit_price' => 100000,
            'discount' => 5000,
        ])->assertRedirect();

        $item = $task->items()->firstOrFail();
        $this->assertSame('Cisco IP Phone 780', $item->description);
        $this->assertSame(195000.0, (float) $item->subtotal);
    }

    public function test_formatted_rupiah_input_is_stored_as_number(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $lead = $this->makeLead($sales);
        $task = $this->makeTask($lead, $sales, $inside);

        $this->actingAs($inside)->post(route('lead-tasks.items.store', $task), [
            'description' => 'Cisco IP Phone 780',
            'quantity' => 1,
            'unit' => 'pcs',
            'unit_price' => '22.000.000',
            'unit_cost' => '20.000.000',
            'discount' => '1.000.000',
        ])->assertRedirect();

        $item = $task->items()->firstOrFail();
        $this->assertSame(22000000.0, (float) $item->unit_price);
        $this->assertSame(20000000.0, (float) $item->unit_cost);
        $this->assertSame(1000000.0, (float) $item->discount);
        $this->assertSame(21000000.0, (float) $item->subtotal);
    }

    public function test_item_below_cost_is_rejected(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $lead = $this->makeLead($sales);
        $task = $this->makeTask($lead, $sales, $inside);

        $this->actingAs($inside)->post(route('lead-tasks.items.store', $task), [
            'description' => 'Cisco IP Phone 780',
            'quantity' => 1,
            'unit' => 'pcs',
            'unit_price' => '18.000.000',
            'unit_cost' => '20.000.000',
            'discount' => 0,
        ])->assertSessionHasErrors('unit_price');

        $this->assertSame(0, $task->items()->count());
    }

    public function test_task_page_shows_estimator_items_to_use(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $lead = $this->makeLead($sales);
        $task = $this->makeTask($lead, $sales, $inside);
        $product = $this->product('Switch Estimator');
        $supplier = Supplier::create(['name' => 'Supplier Estimator']);

        $estimator = EstimatorRequest::create([
            'customer_id' => $lead->customer_id,
            'lead_id' => $lead->id,
            'requested_by' => $sales->id,
            'status' => 'answered',
        ]);
        $item = $estimator->items()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'description' => 'Switch Estimator',
            'quantity' => 2,
            'unit' => 'pcs',
            'price' => 8000000,
            'price_before_tax' => 8000000,
            'status' => 'answered',
        ]);
        ProductPriceLog::create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'price' => 8000000,
            'price_type' => 'cost',
            'currency' => 'IDR',
            'stock_status' => 'ready',
            'warranty_info' => '1 year',
            'estimator_request_item_id' => $item->id,
            'updated_by' => $sales->id,
        ]);

        $this->actingAs($inside)
            ->get(route('lead-tasks.show', $task))
            ->assertOk()
            ->assertSee('Estimator Items')
            ->assertSee('Switch Estimator')
            ->assertSee('data-use-estimator-item', false)
            ->assertSee('data-estimator-item-row', false)
            ->assertSee('Use All');
    }

    public function test_quotation_create_warns_when_quantity_differs_from_estimator(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $lead = $this->makeLead($sales);
        $task = $this->makeTask($lead, $sales, $inside);
        $product = $this->product('Switch Quantity');

        $this->makeItem($task, [
            'product_id' => $product->id,
            'description' => 'Switch Quantity',
            'quantity' => 1,
            'unit_price' => 10000000,
            'unit_cost' => 8000000,
            'target_margin_percent' => 20,
        ]);

        $estimator = EstimatorRequest::create([
            'customer_id' => $lead->customer_id,
            'lead_id' => $lead->id,
            'requested_by' => $sales->id,
            'status' => 'answered',
        ]);
        $estimator->items()->create([
            'product_id' => $product->id,
            'description' => 'Switch Quantity',
            'quantity' => 2,
            'unit' => 'pcs',
            'price' => 8000000,
            'price_before_tax' => 8000000,
            'status' => 'answered',
        ]);

        $this->actingAs($inside)
            ->get(route('inside-sales.quotations.create', $task))
            ->assertOk()
            ->assertSee('Check item quantities')
            ->assertSee('quantity differs', false);
    }

    public function test_batch_saves_items_and_deletes_removed_rows(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $lead = $this->makeLead($sales);
        $task = $this->makeTask($lead, $sales, $inside);

        $keep = $this->makeItem($task, ['description' => 'Item Lama', 'quantity' => 1, 'unit_price' => 100000]);
        $remove = $this->makeItem($task, ['description' => 'Item Hapus', 'quantity' => 1, 'unit_price' => 200000]);

        $this->actingAs($inside)->put(route('lead-tasks.items.batch', $task), [
            'items' => [
                [
                    'id' => $keep->id,
                    'description' => 'Item Diperbarui',
                    'quantity' => 2,
                    'unit' => 'pcs',
                    'unit_price' => '10.000.000',
                    'discount' => '0',
                    'target_margin_percent' => 20,
                ],
                [
                    'description' => 'Item Tambahan',
                    'quantity' => 1,
                    'unit' => 'unit',
                    'unit_price' => '5.000.000',
                    'discount' => '0',
                    'target_margin_percent' => 20,
                ],
            ],
        ])->assertRedirect();

        $this->assertSame(2, $task->items()->count());
        $this->assertDatabaseHas('lead_task_items', [
            'id' => $keep->id,
            'description' => 'Item Diperbarui',
            'unit_price' => 10000000,
            'subtotal' => 20000000,
        ]);
        $this->assertDatabaseHas('lead_task_items', [
            'lead_task_id' => $task->id,
            'description' => 'Item Tambahan',
        ]);
        $this->assertDatabaseMissing('lead_task_items', ['id' => $remove->id]);
    }

    public function test_batch_rejects_item_below_cost(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $lead = $this->makeLead($sales);
        $task = $this->makeTask($lead, $sales, $inside);

        $this->actingAs($inside)->put(route('lead-tasks.items.batch', $task), [
            'items' => [[
                'description' => 'Item Rugi',
                'quantity' => 1,
                'unit' => 'pcs',
                'unit_price' => '18.000.000',
                'unit_cost' => '20.000.000',
                'discount' => 0,
            ]],
        ])->assertSessionHasErrors('items.0.unit_price');

        $this->assertSame(0, $task->items()->count());
    }

    public function test_batch_uses_product_cost_log_and_rejects_tampered_cost(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $lead = $this->makeLead($sales);
        $task = $this->makeTask($lead, $sales, $inside);
        $product = $this->product('Router Uji');
        $supplier = Supplier::create(['name' => 'Supplier Router Uji']);

        $log = ProductPriceLog::create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'price' => 20000000,
            'price_type' => 'cost',
            'currency' => 'IDR',
            'stock_status' => 'ready',
            'warranty_info' => '1 year',
            'updated_by' => $sales->id,
        ]);

        $this->actingAs($inside)->put(route('lead-tasks.items.batch', $task), [
            'items' => [[
                'product_id' => $product->id,
                'product_price_log_id' => $log->id,
                'description' => 'Router Uji',
                'quantity' => 2,
                'unit' => 'pcs',
                'unit_price' => '50.000.000',
                'unit_cost' => '1',
                'discount' => 0,
            ]],
        ])->assertRedirect();

        $item = $task->items()->firstOrFail();
        $this->assertSame(20000000.0, (float) $item->unit_cost);
        $this->assertSame($log->id, $item->product_price_log_id);
        $this->assertSame(100000000.0, (float) $item->subtotal);

        $this->actingAs($inside)->put(route('lead-tasks.items.batch', $task), [
            'items' => [[
                'id' => $item->id,
                'product_id' => $product->id,
                'product_price_log_id' => $log->id,
                'description' => 'Router Uji',
                'quantity' => 1,
                'unit' => 'pcs',
                'unit_price' => '15.000.000',
                'discount' => 0,
            ]],
        ])->assertSessionHasErrors('items.0.unit_price');

        $this->assertSame(100000000.0, (float) $item->fresh()->subtotal);
    }

    public function test_task_show_renders_hidden_cost_as_whole_rupiah(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $lead = $this->makeLead($sales);
        $task = $this->makeTask($lead, $sales, $inside);

        $this->makeItem($task, [
            'description' => 'Item dengan modal',
            'unit_cost' => 20000000.00,
            'unit_price' => 25000000,
        ]);

        $this->actingAs($inside)->get(route('lead-tasks.show', $task))
            ->assertOk()
            ->assertSee('name="items[0][unit_cost]" value="20000000"', false);
    }

    public function test_accepting_file_creates_quotation_with_items(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $lead = $this->makeLead($sales);
        $task = $this->makeTask($lead, $sales, $inside);

        $this->makeItem($task, ['description' => 'Cisco IP Phone 780', 'quantity' => 2, 'unit_price' => 100000, 'discount' => 5000]);
        $this->makeItem($task, ['description' => 'Switch PoE 8 port', 'quantity' => 1, 'unit_price' => 150000]);

        $attachment = $this->addAttachment($task, $inside);

        $this->actingAs($sales)
            ->post(route('lead-tasks.attachments.accept', [$task, $attachment]))
            ->assertRedirect();

        $attachment->refresh();
        $this->assertNotNull($attachment->accepted_at);

        $proposal = Proposal::findOrFail($attachment->proposal_id);
        $this->assertSame($task->id, $proposal->lead_task_id);
        $this->assertSame($sales->id, $proposal->created_by);
        $this->assertSame('draft', $proposal->status);
        $this->assertCount(2, $proposal->items()->get());
        $this->assertSame(345000.0, (float) $proposal->grand_total);
        $this->assertCount(1, $proposal->attachments()->get());
        $this->assertSame(1, $proposal->versions()->count());
        $this->assertSame($sales->id, (int) $proposal->versions()->first()->changed_by);
    }

    public function test_accepting_file_requires_at_least_one_item(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $lead = $this->makeLead($sales);
        $task = $this->makeTask($lead, $sales, $inside);

        $attachment = $this->addAttachment($task, $inside);

        $this->actingAs($sales)
            ->post(route('lead-tasks.attachments.accept', [$task, $attachment]))
            ->assertStatus(422);

        $attachment->refresh();
        $this->assertNull($attachment->accepted_at);
        $this->assertSame(0, Proposal::count());
    }

    public function test_task_show_displays_quotation_items(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $lead = $this->makeLead($sales);
        $task = $this->makeTask($lead, $sales, $inside);

        $this->makeItem($task, ['description' => 'Cisco IP Phone 780']);

        $this->actingAs($inside)->get(route('lead-tasks.show', $task))
            ->assertOk()
            ->assertSee('Quotation Items')
            ->assertSee('Cisco IP Phone 780')
            ->assertSee('Save Items')
            ->assertSee(route('lead-tasks.items.batch', $task), false)
            ->assertSee('Add Item');
    }
}
