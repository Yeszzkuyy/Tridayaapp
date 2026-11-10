<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\LeadTaskItem;
use App\Models\Proposal;
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

    public function test_sales_cannot_store_cost_price_on_task_item(): void
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
        ])->assertRedirect();

        $this->assertNull($task->items()->firstOrFail()->unit_cost);
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
