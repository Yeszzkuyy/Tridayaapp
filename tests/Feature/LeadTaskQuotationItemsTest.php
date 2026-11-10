<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadTask;
use App\Models\LeadTaskAttachment;
use App\Models\LeadTaskItem;
use App\Models\Proposal;
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
            ->assertSee('Cisco IP Phone 780');
    }
}
