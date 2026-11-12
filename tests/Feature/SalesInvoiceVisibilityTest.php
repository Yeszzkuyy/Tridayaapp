<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Proposal;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sales boleh melihat PI/invoice miliknya secara read-only untuk verifikasi,
 * tanpa mendapat akses penuh menu Admin.
 */
class SalesInvoiceVisibilityTest extends TestCase
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

    private function makeInvoice(User $sales, array $overrides = []): Invoice
    {
        $customer = Customer::create([
            'name' => 'PT Invoice Sales '.uniqid(),
            'pt_group' => 'NTI',
            'status' => 'deal',
        ]);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'won',
            'assigned_to' => $sales->id,
        ]);

        $proposal = Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => 'Q/INV/'.uniqid(),
            'created_by' => $sales->id,
            'proposal_date' => now()->toDateString(),
            'customer' => $customer->name,
            'grand_total' => 5000000,
            'status' => 'accepted',
        ]);

        return Invoice::create(array_merge([
            'invoice_number' => 'INV-SALES-'.uniqid(),
            'customer_id' => $customer->id,
            'proposal_id' => $proposal->id,
            'amount' => 5000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'created_by' => $sales->id,
        ], $overrides));
    }

    public function test_sales_can_view_own_invoice_read_only(): void
    {
        $sales = $this->userWithRole('sales');
        $invoice = $this->makeInvoice($sales);

        $this->actingAs($sales)
            ->get(route('sales.invoices.show', $invoice))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('Rp 5.000.000');
    }

    public function test_other_sales_cannot_view_foreign_invoice(): void
    {
        $owner = $this->userWithRole('sales');
        $other = $this->userWithRole('sales');
        $invoice = $this->makeInvoice($owner);

        $this->actingAs($other)
            ->get(route('sales.invoices.show', $invoice))
            ->assertForbidden();
    }

    public function test_sales_still_cannot_open_admin_invoice_page(): void
    {
        $sales = $this->userWithRole('sales');
        $invoice = $this->makeInvoice($sales);

        $this->actingAs($sales)
            ->get(route('admin.invoices.show', $invoice))
            ->assertForbidden();
    }

    public function test_management_can_view_sales_invoice_page(): void
    {
        $sales = $this->userWithRole('sales');
        $management = $this->userWithRole('management');
        $invoice = $this->makeInvoice($sales);

        $this->actingAs($management)
            ->get(route('sales.invoices.show', $invoice))
            ->assertOk()
            ->assertSee($invoice->invoice_number);
    }
}
