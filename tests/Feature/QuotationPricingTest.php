<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\EstimatorRequest;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\LeadTaskItem;
use App\Models\Product;
use App\Models\ProductPriceLog;
use App\Models\Proposal;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuotationPricingTest extends TestCase
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

    private function customer(string $name = 'PT Pricing Test'): Customer
    {
        return Customer::create([
            'name' => $name,
            'pt_group' => 'WANI',
            'address' => 'Jl. Test No. 1, Jakarta',
            'email' => 'customer@example.test',
            'phone' => '+62 812 0000 0000',
        ]);
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Produk Pricing '.uniqid(),
            'category' => 'barang',
            'unit' => 'pcs',
            'default_price' => 1000000,
            'cost_price' => 600000,
            'is_active' => true,
            'status' => Product::STATUS_APPROVED,
        ], $attributes));
    }

    private function task(User $sales, User $inside): LeadTask
    {
        $lead = Lead::create([
            'customer_id' => $this->customer()->id,
            'pt_group' => 'WANI',
            'segment' => 'vendor',
            'status' => 'warm',
            'assigned_to' => $sales->id,
            'incoming_date' => today()->toDateString(),
        ]);

        return LeadTask::create([
            'lead_id' => $lead->id,
            'title' => 'Buatkan quotation pricing',
            'assigned_to' => $inside->id,
            'created_by' => $sales->id,
            'status' => 'in_progress',
        ]);
    }

    private function item(LeadTask $task, array $overrides = []): LeadTaskItem
    {
        $data = array_merge([
            'description' => 'Calcium Sulphate HPL',
            'quantity' => 2,
            'unit' => 'm2',
            'unit_price' => 1000000,
            'unit_cost' => 700000,
            'target_margin_percent' => 25,
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
            'proposal_date' => today()->toDateString(),
            'customer' => $task->lead->customer->name,
            'customer_address' => 'Jl. Test No. 1, Jakarta',
            'contact_name' => 'Ibu Rezki',
            'contact_email' => 'rezki@example.test',
            'contact_phone' => '+62 899 1599 319',
            'project_description' => 'Raised floor untuk data center.',
            'payment_terms_text' => 'TT in Advance',
            'delivery_terms' => 'Ready stock',
            'warranty_terms' => '1 year official distributor',
            'letterhead_pt' => 'WANI',
        ];
    }

    public function test_proposal_edit_page_shows_margin_fields(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = Lead::create([
            'customer_id' => $this->customer()->id,
            'pt_group' => 'WANI',
            'segment' => 'vendor',
            'status' => 'warm',
            'assigned_to' => $sales->id,
            'incoming_date' => today()->toDateString(),
        ]);

        $proposal = Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => 'WANI/001/AS/2026',
            'created_by' => $sales->id,
            'proposal_date' => today()->toDateString(),
            'customer' => $lead->customer->name,
            'letterhead_pt' => 'WANI',
            'status' => 'draft',
            'tax_rate' => 12,
            'default_margin_percent' => 20,
        ]);
        $proposal->items()->create([
            'description' => 'Item margin',
            'quantity' => 1,
            'unit' => 'pcs',
            'unit_price' => 1000000,
            'unit_cost' => 800000,
            'target_margin_percent' => 20,
            'discount' => 0,
            'subtotal' => 1000000,
        ]);

        $this->actingAs($sales)
            ->get(route('sales.proposals.edit', $proposal))
            ->assertOk()
            ->assertSee('Default Margin (%)')
            ->assertSee('Target Margin (%)');
    }

    public function test_estimator_include_vat_is_converted_to_net_cost(): void
    {
        $sales = $this->userWithRole('sales');
        $estimator = $this->userWithRole('estimator');
        $supplier = Supplier::create(['name' => 'Supplier PPN']);
        $product = $this->product();

        $request = EstimatorRequest::create([
            'customer_id' => $this->customer()->id,
            'requested_by' => $sales->id,
            'status' => 'submitted',
        ]);
        $item = $request->items()->create([
            'product_id' => $product->id,
            'description' => 'Kamera PPN',
            'quantity' => 1,
            'unit' => 'pcs',
        ]);

        $this->actingAs($estimator)
            ->post(route('estimator.requests.answer', $request), [
                'items' => [[
                    'id' => $item->id,
                    'supplier_id' => $supplier->id,
                    'price' => '1.120.000',
                    'price_includes_tax' => 1,
                    'stock_status' => 'ready',
                    'warranty_info' => '1 year distributor',
                ]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('estimator_request_items', [
            'id' => $item->id,
            'price_includes_tax' => true,
            'price_before_tax' => 1000000,
        ]);
        $this->assertDatabaseHas('product_price_logs', [
            'product_id' => $product->id,
            'price' => 1000000,
            'price_entered' => 1120000,
            'price_includes_tax' => true,
        ]);
        $this->assertSame(1000000.0, (float) $product->fresh()->cost_price);
    }

    public function test_estimator_answer_form_shows_price_includes_tax_checkbox(): void
    {
        $sales = $this->userWithRole('sales');
        $estimator = $this->userWithRole('estimator');
        $product = $this->product();

        $request = EstimatorRequest::create([
            'customer_id' => $this->customer()->id,
            'requested_by' => $sales->id,
            'status' => 'submitted',
        ]);
        $request->items()->create([
            'product_id' => $product->id,
            'description' => 'Kamera PPN',
            'quantity' => 1,
            'unit' => 'pcs',
        ]);

        $this->actingAs($estimator)
            ->get(route('estimator.requests.show', $request))
            ->assertOk()
            ->assertSee('Price includes VAT');
    }

    public function test_estimator_back_button_stays_in_estimator_area(): void
    {
        $sales = $this->userWithRole('sales');
        $estimator = $this->userWithRole('estimator');
        $lead = Lead::create([
            'customer_id' => $this->customer()->id,
            'pt_group' => 'WANI',
            'segment' => 'vendor',
            'status' => 'warm',
            'assigned_to' => $sales->id,
            'incoming_date' => today()->toDateString(),
        ]);

        $request = EstimatorRequest::create([
            'customer_id' => $lead->customer_id,
            'lead_id' => $lead->id,
            'requested_by' => $sales->id,
            'status' => 'submitted',
        ]);
        $request->items()->create([
            'product_id' => $this->product()->id,
            'description' => 'Kamera Back',
            'quantity' => 1,
            'unit' => 'pcs',
        ]);

        $this->actingAs($estimator)
            ->get(route('estimator.requests.show', $request))
            ->assertOk()
            ->assertSee('href="'.route('estimator.dashboard').'"', false);
    }

    public function test_task_page_shows_price_history_and_target_margin(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->task($sales, $inside);

        $this->actingAs($inside)
            ->get(route('lead-tasks.show', $task))
            ->assertOk()
            ->assertSee('Price History')
            ->assertSee('Target Margin (%)');
    }

    public function test_cost_logs_endpoint_returns_history_for_inside_sales(): void
    {
        $inside = $this->userWithRole('inside-sales');
        $product = $this->product();
        $supplier = Supplier::create(['name' => 'Supplier Endpoint']);

        ProductPriceLog::create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'price' => 800000,
            'price_entered' => 896000,
            'price_includes_tax' => true,
            'tax_rate' => 12,
            'price_type' => 'cost',
            'currency' => 'IDR',
            'stock_status' => 'ready',
            'warranty_info' => '1 year',
            'updated_by' => $inside->id,
        ]);

        $this->actingAs($inside)
            ->getJson(route('products.cost-logs', $product))
            ->assertOk()
            ->assertJsonPath('0.price', 800000)
            ->assertJsonPath('0.price_includes_tax', true)
            ->assertJsonPath('0.supplier', 'Supplier Endpoint');
    }

    public function test_cost_logs_endpoint_rejects_marketing(): void
    {
        $marketing = $this->userWithRole('marketing');
        $product = $this->product();

        $this->actingAs($marketing)
            ->getJson(route('products.cost-logs', $product))
            ->assertForbidden();
    }

    public function test_task_item_uses_selected_price_history_and_target_margin(): void
    {        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->task($sales, $inside);
        $product = $this->product();
        $supplier = Supplier::create(['name' => 'Supplier Riwayat']);

        $older = ProductPriceLog::create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'price' => 800000,
            'price_type' => 'cost',
            'currency' => 'IDR',
            'stock_status' => 'ready',
            'warranty_info' => '1 year',
            'updated_by' => $inside->id,
        ]);
        $older->forceFill(['created_at' => now()->subDays(5)])->save();

        $newer = ProductPriceLog::create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'price' => 900000,
            'price_type' => 'cost',
            'currency' => 'IDR',
            'stock_status' => 'ready',
            'warranty_info' => '1 year',
            'updated_by' => $inside->id,
        ]);
        $newer->forceFill(['created_at' => now()->subDay()])->save();

        $this->actingAs($inside)
            ->post(route('lead-tasks.items.store', $task), [
                'product_id' => $product->id,
                'product_price_log_id' => $older->id,
                'description' => $product->name,
                'quantity' => 1,
                'unit' => 'pcs',
                'unit_price' => 1000000,
                'target_margin_percent' => 25,
                'discount' => 0,
            ])
            ->assertRedirect();

        $item = $task->items()->firstOrFail();
        $this->assertSame($older->id, (int) $item->product_price_log_id);
        $this->assertSame(800000.0, (float) $item->unit_cost);
        $this->assertSame(25.0, (float) $item->target_margin_percent);
    }

    public function test_inside_sales_quotation_snapshots_tax_rate_and_carries_margin(): void
    {
        Storage::fake('private');
        AppSetting::set('vat_rate', 11);
        AppSetting::set('default_margin_percent', 20);

        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->task($sales, $inside);
        $this->item($task);

        $this->actingAs($inside)
            ->post(route('inside-sales.quotations.store', $task), $this->quotationPayload($task))
            ->assertRedirect(route('lead-tasks.show', $task));

        $proposal = Proposal::query()->firstOrFail();
        $this->assertSame(11.0, (float) $proposal->tax_rate);
        $this->assertSame(220000.0, (float) $proposal->tax);
        $this->assertSame(2220000.0, (float) $proposal->grand_total);
        $this->assertSame(25.0, (float) $proposal->items()->firstOrFail()->target_margin_percent);
    }
}
