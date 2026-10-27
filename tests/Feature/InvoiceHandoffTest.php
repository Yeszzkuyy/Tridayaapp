<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceHandoffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole('super-admin');
    }

    private function makeProject(array $proposalOverrides = [], array $customerOverrides = []): array
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $customer = Customer::create(array_merge([
            'name' => 'PT Invoice Handoff',
            'address' => 'Jl. Utama 1',
            'billing_address' => 'Jl. Tagih 2',
            'finance_pic_name' => 'Ibu Sari',
            'finance_pic_phone' => '08123456789',
            'finance_pic_email' => 'sari@example.test',
            'npwp' => '01.234.567.8-901.234',
            'npwp_document_path' => 'customers/1/npwp/test.pdf',
        ], $customerOverrides));

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'won',
        ]);

        $proposal = Proposal::create(array_merge([
            'lead_id' => $lead->id,
            'proposal_number' => 'Q/INV/'.uniqid(),
            'proposal_date' => now()->toDateString(),
            'customer' => $customer->name,
            'subtotal' => 10000000,
            'discount' => 0,
            'tax' => 0,
            'grand_total' => 10000000,
            'status' => 'accepted',
            'po_number' => 'PO-CUST-77',
            'po_document_path' => 'proposals/1/po/test.pdf',
            'payment_scheme' => 'full',
        ], $proposalOverrides));

        $workType = WorkType::create(['name' => 'Instalasi']);

        $project = Project::create([
            'customer_id' => $customer->id,
            'sales_user_id' => $admin->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Invoice Handoff',
            'proposal_id' => $proposal->id,
            'progress' => 0,
        ]);

        return [$project, $proposal, $customer];
    }

    public function test_create_invoice_prefills_dp_amount_and_customer_po_number(): void
    {
        [$project, $proposal, $customer] = $this->makeProject([
            'payment_scheme' => 'dp',
            'dp_percent' => 30,
            'payment_terms' => null,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.invoices.create', ['project_id' => $project->id]))
            ->assertOk();

        $response->assertViewHas('amount', 3000000.0);
        $response->assertViewHas('invoiceType', 'dp');
        $response->assertViewHas('customerPoNumber', 'PO-CUST-77');
    }

    public function test_create_invoice_after_dp_prefills_settlement_remaining_amount(): void
    {
        [$project, $proposal] = $this->makeProject([
            'payment_scheme' => 'dp',
            'dp_percent' => 30,
        ]);

        Invoice::create([
            'invoice_number' => 'INV-TEST-DP',
            'customer_id' => $project->customer_id,
            'project_id' => $project->id,
            'proposal_id' => $proposal->id,
            'invoice_type' => 'dp',
            'amount' => 3000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.invoices.create', ['project_id' => $project->id]))
            ->assertOk();

        $response->assertViewHas('amount', 7000000.0);
        $response->assertViewHas('invoiceType', 'pelunasan');
    }

    public function test_create_invoice_prefills_tempo_due_date_and_full_amount(): void
    {
        [$project] = $this->makeProject([
            'payment_scheme' => 'tempo',
            'term_days' => 30,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.invoices.create', ['project_id' => $project->id]))
            ->assertOk();

        $response->assertViewHas('amount', 10000000.0);
        $response->assertViewHas('invoiceType', 'full');
        $response->assertViewHas('dueDate', now()->addDays(30)->toDateString());
    }

    public function test_admin_can_store_and_see_customer_po_number_and_billing_details(): void
    {
        [$project, $proposal, $customer] = $this->makeProject();

        $this->actingAs($this->admin())->post(route('admin.invoices.store'), [
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'proposal_id' => $proposal->id,
            'customer_po_number' => 'PO-CUST-77',
            'invoice_type' => 'full',
            'amount' => 10000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ])->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertSame('PO-CUST-77', $invoice->customer_po_number);

        $this->actingAs($this->admin())
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('PO-CUST-77')
            ->assertSee('Jl. Tagih 2')
            ->assertSee('Ibu Sari')
            ->assertSee('01.234.567.8-901.234')
            ->assertSee(__('Full payment 100%'));
    }
}
