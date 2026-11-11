<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\Payment;
use App\Models\Poc;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Proposal;
use App\Models\User;
use App\Models\WorkType;
use App\Services\SalesKpiService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesAreaPrivacyTest extends TestCase
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

    private function customer(string $name, string $pt = 'NTI', ?User $creator = null): Customer
    {
        return Customer::create([
            'name' => $name,
            'company' => $name,
            'pt_group' => $pt,
            'status' => 'deal',
            'created_by' => $creator?->id,
        ]);
    }

    private function lead(Customer $customer, User $sales): Lead
    {
        return Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => $customer->pt_group,
            'segment' => 'vendor',
            'status' => 'warm',
            'assigned_to' => $sales->id,
            'incoming_date' => today()->toDateString(),
        ]);
    }

    private function project(Customer $customer, User $sales, string $name = 'Project Uji'): Project
    {
        $workType = WorkType::firstOrCreate(['name' => 'Instalasi']);
        $status = ProjectStatus::firstOrCreate(['name' => 'Pending']);

        return Project::create([
            'customer_id' => $customer->id,
            'sales_user_id' => $sales->id,
            'work_type_id' => $workType->id,
            'project_status_id' => $status->id,
            'project_name' => $name,
            'progress' => 0,
        ]);
    }

    private function paidInvoice(User $sales, Customer $customer, float $amount): Payment
    {
        $lead = $this->lead($customer, $sales);

        $proposal = Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => 'PRIV-'.uniqid(),
            'created_by' => $sales->id,
            'proposal_date' => today()->toDateString(),
            'customer' => $customer->name,
            'status' => 'accepted',
            'subtotal' => $amount,
            'grand_total' => $amount,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-'.uniqid(),
            'customer_id' => $customer->id,
            'proposal_id' => $proposal->id,
            'amount' => $amount,
            'issue_date' => today()->toDateString(),
            'status' => 'unpaid',
            'created_by' => $sales->id,
        ]);

        return Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'payment_date' => today()->toDateString(),
            'created_by' => $sales->id,
        ]);
    }

    public function test_sales_only_sees_own_customers(): void
    {
        $sales = $this->userWithRole('sales');
        $other = $this->userWithRole('sales');

        $own = $this->customer('PT Milik Sendiri');
        $this->lead($own, $sales);

        $foreign = $this->customer('PT Milik Sales Lain');
        $this->lead($foreign, $other);

        $this->actingAs($sales)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee($own->name)
            ->assertDontSee($foreign->name);

        $this->actingAs($sales)
            ->get(route('customers.show', $foreign))
            ->assertForbidden();

        $this->actingAs($sales)
            ->get(route('customers.show', $own))
            ->assertOk();
    }

    public function test_sales_can_see_customer_they_created_without_lead(): void
    {
        $sales = $this->userWithRole('sales');
        $customer = $this->customer('PT Buatan Sendiri', 'MGK', $sales);

        $this->actingAs($sales)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee($customer->name);

        $this->actingAs($sales)
            ->get(route('customers.show', $customer))
            ->assertOk();
    }

    public function test_management_still_sees_all_customers(): void
    {
        $sales = $this->userWithRole('sales');
        $management = $this->userWithRole('management');

        $own = $this->customer('PT Satu');
        $this->lead($own, $sales);

        $foreign = $this->customer('PT Dua');
        $this->lead($foreign, $sales);

        $this->actingAs($management)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee($own->name)
            ->assertSee($foreign->name);
    }

    public function test_inside_sales_sees_customer_from_assigned_task_only(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $other = $this->userWithRole('sales');

        $assignedCustomer = $this->customer('PT Task Inside');
        $assignedLead = $this->lead($assignedCustomer, $sales);

        LeadTask::create([
            'lead_id' => $assignedLead->id,
            'title' => 'Buat quotation',
            'assigned_to' => $inside->id,
            'created_by' => $sales->id,
            'status' => 'todo',
        ]);

        $foreignCustomer = $this->customer('PT Bukan Task');
        $this->lead($foreignCustomer, $other);

        $this->actingAs($inside)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee($assignedCustomer->name)
            ->assertDontSee($foreignCustomer->name);

        $this->actingAs($inside)
            ->get(route('customers.show', $foreignCustomer))
            ->assertForbidden();
    }

    public function test_sales_cannot_open_other_sales_project_detail(): void
    {
        $sales = $this->userWithRole('sales');
        $other = $this->userWithRole('sales');
        $management = $this->userWithRole('management');

        $ownCustomer = $this->customer('PT Project Sendiri');
        $this->lead($ownCustomer, $sales);
        $ownProject = $this->project($ownCustomer, $sales, 'Project Sendiri');

        $foreignCustomer = $this->customer('PT Project Lain');
        $this->lead($foreignCustomer, $other);
        $foreignProject = $this->project($foreignCustomer, $other, 'Project Lain');

        $this->actingAs($sales)
            ->get(route('projects.show', $ownProject))
            ->assertOk();

        $this->actingAs($sales)
            ->get(route('projects.show', $foreignProject))
            ->assertForbidden();

        $this->actingAs($management)
            ->get(route('projects.show', $foreignProject))
            ->assertOk();
    }

    public function test_follow_up_and_meeting_cannot_use_other_sales_customer(): void
    {
        $sales = $this->userWithRole('sales');
        $other = $this->userWithRole('sales');

        $own = $this->customer('PT Follow Up Sendiri');
        $this->lead($own, $sales);

        $foreign = $this->customer('PT Follow Up Lain');
        $this->lead($foreign, $other);

        $this->actingAs($sales)
            ->get(route('sales.follow-ups.create'))
            ->assertOk()
            ->assertSee($own->name)
            ->assertDontSee($foreign->name);

        $this->actingAs($sales)
            ->post(route('sales.follow-ups.store'), [
                'customer_id' => $foreign->id,
                'description' => 'Coba tulis follow up sales lain',
                'type' => 'call',
            ])
            ->assertForbidden();

        $this->actingAs($sales)
            ->post(route('sales.meetings.store'), [
                'customer_id' => $foreign->id,
                'follow_up_date' => today()->toDateString(),
                'meeting_mode' => 'online',
                'description' => 'Coba meeting customer sales lain',
            ])
            ->assertForbidden();
    }

    public function test_sales_cannot_open_other_sales_poc(): void
    {
        $sales = $this->userWithRole('sales');
        $other = $this->userWithRole('sales');

        $foreignCustomer = $this->customer('PT POC Lain');
        $foreignLead = $this->lead($foreignCustomer, $other);

        $poc = Poc::create([
            'customer_id' => $foreignCustomer->id,
            'lead_id' => $foreignLead->id,
            'type' => 'poc',
            'scheduled_date' => today()->toDateString(),
            'status' => 'scheduled',
            'created_by' => $other->id,
        ]);

        $this->actingAs($sales)
            ->get(route('sales.pocs.show', $poc))
            ->assertForbidden();
    }

    public function test_sales_dashboard_income_is_personal(): void
    {
        $sales = $this->userWithRole('sales');
        $other = $this->userWithRole('sales');

        $ownCustomer = $this->customer('PT Income Sendiri', 'NTI');
        $this->paidInvoice($sales, $ownCustomer, 1250000);

        $foreignCustomer = $this->customer('PT Income Lain', 'MGK');
        $this->paidInvoice($other, $foreignCustomer, 9000000);

        $revenue = app(SalesKpiService::class)->getPaidRevenueByPt($sales, now()->month, now()->year);

        $this->assertSame(1250000.0, $revenue['NTI']);
        $this->assertSame(0.0, $revenue['MGK']);

        $this->actingAs($sales)
            ->get(route('sales.dashboard'))
            ->assertOk()
            ->assertViewHas('incomeTotal', 1250000.0)
            ->assertViewHas('incomeMonth', fn ($rows) => (float) $rows['NTI'] === 1250000.0
                && (float) $rows['MGK'] === 0.0);
    }
}
