<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\LeadTaskItem;
use App\Models\EstimatorRequest;
use App\Models\Product;
use App\Models\ProductPriceLog;
use App\Models\Proposal;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\QuotationGeneratedNotification;
use App\Support\NotificationPresenter;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InsideSalesWorkspaceTest extends TestCase
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

    private function makeTask(User $sales, User $inside, array $overrides = []): LeadTask
    {
        $customer = Customer::create([
            'name' => 'PT WANI Test '.uniqid(),
            'pt_group' => 'WANI',
            'address' => 'Jl. Test No. 1, Jakarta',
            'email' => 'customer@example.test',
            'phone' => '+62 812 0000 0000',
        ]);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'WANI',
            'segment' => 'vendor',
            'status' => 'warm',
            'assigned_to' => $sales->id,
            'incoming_date' => today()->toDateString(),
            'kebutuhan' => 'Pengadaan floor panel untuk data center.',
            ...$overrides,
        ]);

        return LeadTask::create([
            'lead_id' => $lead->id,
            'title' => 'Buatkan quotation floor panel',
            'assigned_to' => $inside->id,
            'created_by' => $sales->id,
            'status' => 'in_progress',
        ]);
    }

    private function makeItem(LeadTask $task, array $overrides = []): LeadTaskItem
    {
        $data = array_merge([
            'description' => 'Calcium Sulphate HPL',
            'scope_of_supply' => 'WAN-RS-CSH',
            'lead_time' => 'Ready Stock',
            'section' => 'MATERIAL',
            'quantity' => 2,
            'unit' => 'm2',
            'unit_price' => 1000000,
            'unit_cost' => 700000,
            'discount' => 0,
        ], $overrides);

        return LeadTaskItem::create([
            'lead_task_id' => $task->id,
            ...$data,
            'subtotal' => LeadTaskItem::subtotalFor(
                (float) $data['quantity'],
                (float) $data['unit_price'],
                (float) $data['discount'],
            ),
        ]);
    }

    private function quotationPayload(LeadTask $task): array
    {
        return [
            'proposal_number' => '217R6-FE-WANI-2026',
            'proposal_date' => today()->toDateString(),
            'customer' => $task->lead->customer->name,
            'customer_address' => 'Jl. Test No. 1, Jakarta',
            'contact_name' => 'Ibu Rezki',
            'contact_email' => 'rezki@example.test',
            'contact_phone' => '+62 899 1599 319',
            'project_description' => 'Raised floor untuk data center.',
            'payment_terms_text' => 'TT in Advance',
            'delivery_terms' => 'Ready stock; Indent 2-3 weeks',
            'warranty_terms' => '1 year official distributor',
            'letterhead_pt' => 'WANI',
        ];
    }

    public function test_inside_sales_dashboard_is_accessible(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside);

        $this->actingAs($inside)
            ->get(route('inside-sales.dashboard'))
            ->assertOk()
            ->assertSee('Inside Sales')
            ->assertSee($task->title);
    }

    public function test_sales_without_inside_permission_cannot_open_dashboard(): void
    {
        $sales = $this->userWithRole('sales');

        $this->actingAs($sales)
            ->get(route('inside-sales.dashboard'))
            ->assertForbidden();
    }

    public function test_inside_sales_can_add_item_with_estimator_cost(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside);

        $this->actingAs($inside)->post(route('lead-tasks.items.store', $task), [
            'description' => 'Calcium Sulphate HPL',
            'scope_of_supply' => 'WAN-RS-CSH',
            'lead_time' => 'Ready Stock',
            'section' => 'MATERIAL',
            'quantity' => 2,
            'unit' => 'm2',
            'unit_price' => 1000000,
            'unit_cost' => 700000,
            'discount' => 0,
        ])->assertRedirect();

        $item = $task->items()->firstOrFail();
        $this->assertSame(700000.0, (float) $item->unit_cost);
        $this->assertSame('WAN-RS-CSH', $item->scope_of_supply);
        $this->assertSame('Ready Stock', $item->lead_time);
        $this->assertSame(2000000.0, (float) $item->subtotal);
    }

    public function test_inside_sales_can_open_quotation_form(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside);
        $this->makeItem($task);

        $this->actingAs($inside)
            ->get(route('inside-sales.quotations.create', $task))
            ->assertOk()
            ->assertSee($task->title)
            ->assertSee('WANI');
    }

    public function test_quotation_form_requires_items(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside);

        $this->actingAs($inside)
            ->get(route('inside-sales.quotations.create', $task))
            ->assertStatus(422);
    }

    public function test_sales_cannot_generate_quotation_from_inside_sales(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside);
        $this->makeItem($task);

        $this->actingAs($sales)
            ->post(route('inside-sales.quotations.store', $task), $this->quotationPayload($task))
            ->assertForbidden();
    }

    public function test_sales_creator_cannot_add_item_to_task(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside);

        $this->actingAs($sales)->post(route('lead-tasks.items.store', $task), [
            'description' => 'Item dari Sales',
            'quantity' => 1,
            'unit' => 'pcs',
            'unit_price' => 1000000,
            'unit_cost' => 700000,
        ])->assertForbidden();

        $this->assertSame(0, $task->items()->count());
    }

    public function test_assigned_inside_sales_can_add_item_across_pt(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $inside->assignRole('sales-nti');

        $customer = Customer::create([
            'name' => 'PT MGK Test '.uniqid(),
            'pt_group' => 'MGK',
            'address' => 'Jl. Test No. 2, Jakarta',
            'email' => 'mgk@example.test',
            'phone' => '+62 812 0000 0001',
        ]);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'MGK',
            'segment' => 'vendor',
            'status' => 'warm',
            'assigned_to' => $sales->id,
            'incoming_date' => today()->toDateString(),
            'kebutuhan' => 'Pengadaan perangkat MGK.',
        ]);

        $task = LeadTask::create([
            'lead_id' => $lead->id,
            'title' => 'Buatkan quotation MGK',
            'assigned_to' => $inside->id,
            'created_by' => $sales->id,
            'status' => 'in_progress',
        ]);

        $this->actingAs($inside)
            ->get(route('lead-tasks.show', $task))
            ->assertOk()
            ->assertSee(__('Cost Price (Estimator)'));

        $this->actingAs($inside)->post(route('lead-tasks.items.store', $task), [
            'description' => 'Item MGK',
            'quantity' => 1,
            'unit' => 'pcs',
            'unit_price' => 1000000,
            'unit_cost' => 700000,
        ])->assertRedirect();

        $this->assertSame(1, $task->items()->count());
    }

    public function test_task_creation_requires_inside_sales_assignee(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside);
        $lead = $task->lead;

        $this->actingAs($sales)->post(route('lead-tasks.store'), [
            'lead_id' => $lead->id,
            'title' => 'Tanpa assignee',
        ])->assertSessionHasErrors('assigned_to');

        $this->actingAs($sales)->post(route('lead-tasks.store'), [
            'lead_id' => $lead->id,
            'title' => 'Assignee bukan inside sales',
            'assigned_to' => $sales->id,
        ])->assertStatus(422);

        $this->actingAs($sales)->post(route('lead-tasks.store'), [
            'lead_id' => $lead->id,
            'title' => 'Request valid',
            'assigned_to' => $inside->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('lead_tasks', [
            'title' => 'Request valid',
            'assigned_to' => $inside->id,
        ]);
    }

    public function test_estimator_sees_estimator_menu(): void
    {
        $estimator = $this->userWithRole('estimator');

        $this->actingAs($estimator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Estimator')
            ->assertSee('Queue')
            ->assertSee('Price History');
    }

    public function test_inside_sales_request_hub_does_not_list_assigned_task(): void
    {
        $salesOwner = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $inside->assignRole('sales');
        $task = $this->makeTask($salesOwner, $inside);
        $task->update(['created_by' => $salesOwner->id]);

        $this->actingAs($inside)
            ->get(route('sales.requests.index', ['tab' => 'inside-sales']))
            ->assertOk()
            ->assertDontSee($task->title);
    }

    public function test_super_admin_permission_sync_command(): void
    {
        $role = \Spatie\Permission\Models\Role::findByName('super-admin');
        $role->revokePermissionTo('manage-price-requests');

        $this->assertFalse($role->fresh()->hasPermissionTo('manage-price-requests'));

        $this->artisan('app:sync-super-admin-permissions')->assertSuccessful();

        $this->assertTrue($role->fresh()->hasPermissionTo('manage-price-requests'));
    }

    public function test_task_page_renders_item_form_with_products(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside);
        $this->makeItem($task);

        $this->actingAs($inside)
            ->get(route('lead-tasks.show', $task))
            ->assertOk()
            ->assertSee(__('Quotation Items'))
            ->assertSee($task->items->first()->description);
    }

    public function test_generate_quotation_creates_pdf_and_notifies_sales(): void
    {
        Storage::fake('private');
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside);
        $this->makeItem($task);

        $this->actingAs($inside)
            ->post(route('inside-sales.quotations.store', $task), $this->quotationPayload($task))
            ->assertRedirect(route('lead-tasks.show', $task));

        $proposal = Proposal::query()->firstOrFail();
        $this->assertSame($task->id, $proposal->lead_task_id);
        $this->assertSame($sales->id, $proposal->created_by);
        $this->assertSame('217R6-FE-WANI-2026', $proposal->proposal_number);
        $this->assertSame('WANI', $proposal->letterhead_pt);
        $this->assertSame('draft', $proposal->status);
        $this->assertSame(2240000.0, (float) $proposal->grand_total);
        $this->assertCount(1, $proposal->items()->get());
        $this->assertNotNull($proposal->pdf_path);
        Storage::disk('private')->assertExists($proposal->pdf_path);
        $this->assertSame('done', $task->fresh()->status);
        Notification::assertSentTo($sales, QuotationGeneratedNotification::class);
        $this->assertSame(
            'proposal',
            NotificationPresenter::kind(
                QuotationGeneratedNotification::class,
                (new QuotationGeneratedNotification($proposal))->toDatabase($sales)
            )
        );
    }

    public function test_generate_quotation_copies_stock_and_warranty_snapshot(): void
    {
        Storage::fake('private');
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside);
        $this->makeItem($task, [
            'stock_status' => 'indent',
            'stock_eta' => '2-3 weeks',
            'warranty_info' => '1 year distributor',
        ]);

        $payload = $this->quotationPayload($task);
        $payload['delivery_terms'] = 'Indent 2-3 weeks';
        $payload['warranty_terms'] = '1 year distributor';

        $this->actingAs($inside)
            ->post(route('inside-sales.quotations.store', $task), $payload)
            ->assertRedirect();

        $proposal = Proposal::query()->firstOrFail();
        $this->assertSame('Indent 2-3 weeks', $proposal->delivery_terms);
        $this->assertSame('1 year distributor', $proposal->warranty_terms);

        $item = $proposal->items()->firstOrFail();
        $this->assertSame('indent', $item->stock_status);
        $this->assertSame('2-3 weeks', $item->stock_eta);
        $this->assertSame('1 year distributor', $item->warranty_info);
    }

    public function test_pull_latest_price_updates_task_item_stock_and_warranty(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $estimator = $this->userWithRole('estimator');
        $task = $this->makeTask($sales, $inside);

        $product = Product::create([
            'name' => 'Switch PoE 24 Port',
            'category' => 'barang',
            'unit' => 'pcs',
            'default_price' => 5000000,
            'cost_price' => 4000000,
            'is_active' => true,
            'status' => Product::STATUS_APPROVED,
        ]);
        $supplier = Supplier::create(['name' => 'Vendor Switch']);

        $estimatorRequest = EstimatorRequest::create([
            'customer_id' => $task->lead->customer_id,
            'requested_by' => $sales->id,
            'status' => 'completed',
        ]);
        $requestItem = $estimatorRequest->items()->create([
            'product_id' => $product->id,
            'description' => 'Switch PoE 24 Port',
            'quantity' => 1,
            'unit' => 'pcs',
            'status' => 'answered',
        ]);

        $log = ProductPriceLog::create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'price' => 3850000,
            'price_type' => 'cost',
            'currency' => 'IDR',
            'stock_status' => 'indent',
            'stock_eta' => '3 weeks',
            'warranty_info' => '1 year distributor',
            'estimator_request_item_id' => $requestItem->id,
            'updated_by' => $estimator->id,
        ]);

        $item = $this->makeItem($task, [
            'product_id' => $product->id,
            'unit_cost' => null,
        ]);

        $this->actingAs($inside)
            ->post(route('inside-sales.quotations.pull-prices', $task))
            ->assertRedirect();

        $item->refresh();
        $this->assertSame($log->id, $item->product_price_log_id);
        $this->assertSame(3850000.0, (float) $item->unit_cost);
        $this->assertSame('indent', $item->stock_status);
        $this->assertSame('3 weeks', $item->stock_eta);
        $this->assertSame('1 year distributor', $item->warranty_info);
    }

    public function test_sales_cannot_pull_prices_into_inside_sales_task(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside);

        $this->actingAs($sales)
            ->post(route('inside-sales.quotations.pull-prices', $task))
            ->assertForbidden();
    }

    public function test_generate_quotation_requires_items(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside);

        $this->actingAs($inside)
            ->post(route('inside-sales.quotations.store', $task), $this->quotationPayload($task))
            ->assertStatus(422);
    }

    public function test_quotation_number_must_be_unique(): void
    {
        Storage::fake('private');

        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside);
        $this->makeItem($task);

        Proposal::create([
            'lead_id' => $task->lead_id,
            'proposal_number' => '217R6-FE-WANI-2026',
            'created_by' => $sales->id,
            'proposal_date' => today()->toDateString(),
            'customer' => 'PT Lain',
            'status' => 'draft',
        ]);

        $this->actingAs($inside)
            ->post(route('inside-sales.quotations.store', $task), $this->quotationPayload($task))
            ->assertSessionHasErrors('proposal_number');
    }

    public function test_sales_can_download_generated_pdf(): void
    {
        Storage::fake('private');

        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside);
        $this->makeItem($task);

        $this->actingAs($inside)
            ->post(route('inside-sales.quotations.store', $task), $this->quotationPayload($task))
            ->assertRedirect();

        $proposal = Proposal::query()->firstOrFail();

        $this->actingAs($sales)
            ->get(route('sales.proposals.pdf', $proposal))
            ->assertOk();

        $this->actingAs($sales)
            ->get(route('lead-tasks.show', $task))
            ->assertOk()
            ->assertSee(route('sales.proposals.pdf', $proposal))
            ->assertDontSee(__('Cost Price (Estimator)'));
    }
}
