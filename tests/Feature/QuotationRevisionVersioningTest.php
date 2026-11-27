<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\LeadTaskItem;
use App\Models\Proposal;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuotationRevisionVersioningTest extends TestCase
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

    private function makeLead(User $sales, string $pt = 'MGK'): Lead
    {
        $customer = Customer::create(['name' => 'PT Revision', 'pt_group' => $pt]);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => $pt,
            'segment' => 'vendor',
            'status' => 'hot',
            'assigned_to' => $sales->id,
        ]);

        FollowUp::create([
            'customer_id' => $customer->id,
            'lead_id' => $lead->id,
            'description' => 'Follow up awal.',
            'type' => 'note',
            'created_by' => $sales->id,
        ]);

        return $lead;
    }

    private function storePayload(Lead $lead): array
    {
        return [
            'lead_id' => $lead->id,
            'proposal_date' => now()->toDateString(),
            'items' => [[
                'description' => 'CCTV 8 channel',
                'quantity' => 2,
                'unit' => 'unit',
                'unit_price' => 5000000,
                'discount' => 0,
            ]],
        ];
    }

    private function createQuotation(User $sales, User $management): Proposal
    {
        $sales->update(['name' => 'Adi Santosa', 'initials' => 'AS']);
        $lead = $this->makeLead($sales);

        $this->actingAs($management)
            ->post(route('sales.proposals.store'), $this->storePayload($lead))
            ->assertRedirect();

        return Proposal::query()->latest('id')->firstOrFail();
    }

    public function test_initial_number_uses_sequence_company_and_initials(): void
    {
        $sales = $this->userWithRole('sales');
        $proposal = $this->createQuotation($sales, $this->userWithRole('management'));

        $this->assertSame('001/MGK-AS/'.now()->year, $proposal->proposal_number);
        $this->assertSame(1, $proposal->sequence_number);
        $this->assertSame(0, $proposal->revision_number);
        $this->assertSame('MGK', $proposal->company_code);
        $this->assertSame('AS', $proposal->user_initials);
        $this->assertTrue($proposal->is_latest);
        $this->assertSame('R0', $proposal->revisionLabel());
    }

    public function test_new_revision_creates_new_row_and_keeps_old_version_intact(): void
    {
        $sales = $this->userWithRole('sales');
        $proposal = $this->createQuotation($sales, $this->userWithRole('management'));
        $oldItems = $proposal->items()->get()->toArray();

        $this->actingAs($sales)
            ->post(route('sales.proposals.new-revision', $proposal))
            ->assertRedirect();

        $proposal->refresh();
        $this->assertFalse($proposal->is_latest);
        $this->assertSame('001/MGK-AS/'.now()->year, $proposal->proposal_number);
        $this->assertSame($oldItems, $proposal->items()->get()->toArray());

        $revision = Proposal::query()->where('parent_quotation_id', $proposal->id)->firstOrFail();
        $this->assertSame('001R1/MGK-AS/'.now()->year, $revision->proposal_number);
        $this->assertSame(1, $revision->revision_number);
        $this->assertSame(1, $revision->sequence_number);
        $this->assertSame('MGK', $revision->company_code);
        $this->assertSame('AS', $revision->user_initials);
        $this->assertSame($proposal->lead_id, $revision->lead_id);
        $this->assertSame('draft', $revision->status);
        $this->assertTrue($revision->is_latest);
        $this->assertSame('R1', $revision->revisionLabel());
        $this->assertCount(1, $revision->items()->get());
        $this->assertSame(10000000.0, (float) $revision->grand_total);
    }

    public function test_second_revision_uses_r2_and_single_latest_version(): void
    {
        $sales = $this->userWithRole('sales');
        $proposal = $this->createQuotation($sales, $this->userWithRole('management'));

        $this->actingAs($sales)->post(route('sales.proposals.new-revision', $proposal));
        $first = Proposal::query()->where('parent_quotation_id', $proposal->id)->firstOrFail();

        $this->actingAs($sales)->post(route('sales.proposals.new-revision', $first));

        $second = Proposal::query()->where('revision_number', 2)->firstOrFail();
        $this->assertSame('001R2/MGK-AS/'.now()->year, $second->proposal_number);
        $this->assertSame($proposal->id, $second->parent_quotation_id);
        $this->assertSame(1, Proposal::query()->where('is_latest', true)->count());
        $this->assertSame(3, Proposal::query()->count());
    }

    public function test_quotation_index_only_lists_latest_version(): void
    {
        $sales = $this->userWithRole('sales');
        $proposal = $this->createQuotation($sales, $this->userWithRole('management'));
        $oldNumber = $proposal->proposal_number;

        $this->actingAs($sales)->post(route('sales.proposals.new-revision', $proposal));
        $revision = Proposal::query()->where('revision_number', 1)->firstOrFail();

        $this->actingAs($sales)
            ->get(route('sales.proposals.index'))
            ->assertOk()
            ->assertSee($revision->proposal_number)
            ->assertDontSee($oldNumber);
    }

    public function test_accepted_quotation_cannot_be_revised(): void
    {
        $sales = $this->userWithRole('sales');
        $proposal = $this->createQuotation($sales, $this->userWithRole('management'));
        $proposal->update(['status' => 'accepted']);

        $this->actingAs($sales)
            ->post(route('sales.proposals.new-revision', $proposal))
            ->assertStatus(422);

        $this->assertSame(1, Proposal::query()->count());
    }

    public function test_inside_sales_assignee_can_revise_own_task_quotation(): void
    {
        Storage::fake('private');
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $lead = $this->makeLead($sales, 'WANI');

        $task = LeadTask::create([
            'lead_id' => $lead->id,
            'title' => 'Buatkan quotation revisi',
            'assigned_to' => $inside->id,
            'created_by' => $sales->id,
            'status' => 'in_progress',
        ]);

        LeadTaskItem::create([
            'lead_task_id' => $task->id,
            'description' => 'Raised floor',
            'quantity' => 2,
            'unit' => 'm2',
            'unit_price' => 1000000,
            'unit_cost' => 700000,
            'discount' => 0,
            'subtotal' => 2000000,
        ]);

        $this->actingAs($inside)
            ->post(route('inside-sales.quotations.store', $task), [
                'proposal_date' => now()->toDateString(),
                'customer' => $lead->customer->name,
                'customer_address' => 'Jl. Test No. 1, Jakarta',
                'contact_name' => 'Ibu Rezki',
                'contact_email' => 'rezki@example.test',
                'contact_phone' => '+62 899 1599 319',
                'project_description' => 'Raised floor untuk data center.',
                'payment_terms_text' => 'TT in Advance',
                'delivery_terms' => 'Ready stock',
                'warranty_terms' => '1 year',
                'letterhead_pt' => 'WANI',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $original = Proposal::query()->firstOrFail();

        $this->actingAs($inside)
            ->post(route('inside-sales.quotations.revise', $task))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $revision = Proposal::query()->where('revision_number', 1)->firstOrFail();
        $this->assertSame('001R1/WANI-'.$sales->quotationInitials().'/'.now()->year, $revision->proposal_number);
        $this->assertTrue($revision->is_latest);
        $this->assertFalse($original->fresh()->is_latest);

        // Inside Sales boleh membuka form edit revisi (hak sempit, bukan kirim/terima).
        $this->actingAs($inside)
            ->get(route('sales.proposals.edit', $revision))
            ->assertOk();
    }
}
