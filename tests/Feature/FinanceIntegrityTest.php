<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Proposal;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole('admin');
    }

    private function customer(string $name = 'PT Finance Integrity'): Customer
    {
        return Customer::create(['name' => $name]);
    }

    private function invoice(Customer $customer, float $amount = 1000): Invoice
    {
        return Invoice::create([
            'invoice_number' => 'INV-'.uniqid(),
            'customer_id' => $customer->id,
            'amount' => $amount,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'created_by' => $this->admin()->id,
        ]);
    }

    public function test_invoice_cannot_be_created_as_paid_without_payment(): void
    {
        $this->actingAs($this->admin());
        $customer = $this->customer();

        $this->post(route('admin.invoices.store'), [
            'customer_id' => $customer->id,
            'amount' => 1000,
            'status' => 'paid',
            'issue_date' => now()->toDateString(),
        ])->assertSessionHasErrors('status');

        $this->assertSame(0, Invoice::count());
    }

    public function test_invoice_cannot_be_marked_paid_manually_without_payment(): void
    {
        $this->actingAs($this->admin());
        $customer = $this->customer();
        $invoice = $this->invoice($customer);

        $this->put(route('admin.invoices.update', $invoice), [
            'customer_id' => $customer->id,
            'amount' => 1000,
            'status' => 'paid',
            'issue_date' => now()->toDateString(),
        ])->assertSessionHasErrors('status');

        $this->assertSame('unpaid', $invoice->fresh()->status);
        $this->assertSame(0.0, (float) $invoice->fresh()->paid_amount);
    }

    public function test_invoice_with_payment_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $customer = $this->customer();
        $invoice = $this->invoice($customer);

        $this->post(route('admin.payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 400,
            'payment_date' => now()->toDateString(),
        ])->assertRedirect();

        $payment = Payment::firstOrFail();

        $this->delete(route('admin.invoices.destroy', $invoice))
            ->assertSessionHasErrors('invoice');

        $this->assertNotSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'deleted_at' => null]);
    }

    public function test_invoice_create_is_idempotent_with_same_key(): void
    {
        $this->actingAs($this->admin());
        $customer = $this->customer();

        $payload = [
            'customer_id' => $customer->id,
            'amount' => 1000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'idempotency_key' => 'invoice-key-1',
        ];

        $this->post(route('admin.invoices.store'), $payload)->assertRedirect();
        $this->post(route('admin.invoices.store'), $payload)->assertRedirect();

        $this->assertSame(1, Invoice::count());
    }

    public function test_payment_create_is_idempotent_with_same_key(): void
    {
        $this->actingAs($this->admin());
        $customer = $this->customer();
        $invoice = $this->invoice($customer, 1000);

        $payload = [
            'invoice_id' => $invoice->id,
            'amount' => 400,
            'payment_date' => now()->toDateString(),
            'idempotency_key' => 'payment-key-1',
        ];

        $this->post(route('admin.payments.store'), $payload)->assertRedirect();
        $this->post(route('admin.payments.store'), $payload)->assertRedirect();

        $this->assertSame(1, Payment::count());
        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertSame(400.0, (float) $invoice->fresh()->paid_amount);
    }

    public function test_invoice_proposal_must_belong_to_customer(): void
    {
        $this->actingAs($this->admin());
        $customerA = $this->customer('PT A');
        $customerB = $this->customer('PT B');
        $lead = Lead::create([
            'customer_id' => $customerA->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'hot',
        ]);
        $proposal = Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => 'Q-FIN-'.uniqid(),
            'proposal_date' => now()->toDateString(),
            'customer' => $customerA->name,
            'status' => 'accepted',
        ]);

        $this->post(route('admin.invoices.store'), [
            'customer_id' => $customerB->id,
            'proposal_id' => $proposal->id,
            'amount' => 1000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ])->assertSessionHasErrors('proposal_id');

        $this->assertSame(0, Invoice::count());
    }
}
