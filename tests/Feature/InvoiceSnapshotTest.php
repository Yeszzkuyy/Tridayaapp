<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Proposal;
use App\Models\User;
use App\Services\AdminService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_invoice_keeps_billing_address_and_payment_terms_snapshot(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $customer = Customer::create([
            'name' => 'PT Snapshot Invoice',
            'pt_group' => 'NTI',
            'billing_address' => 'Jl. Snapshot Awal No. 1',
        ]);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'hot',
        ]);

        $proposal = Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => 'Q-SNAPSHOT-'.uniqid(),
            'created_by' => $admin->id,
            'proposal_date' => now()->toDateString(),
            'customer' => $customer->name,
            'payment_terms' => 'Termin 30/60 hari',
            'status' => 'accepted',
        ]);

        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'proposal_id' => $proposal->id,
            'amount' => 1000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        $this->assertSame('Jl. Snapshot Awal No. 1', $invoice->billing_address);
        $this->assertSame('Termin 30/60 hari', $invoice->payment_terms);

        // Perubahan data master setelah invoice terbit tidak boleh mengubah
        // dokumen yang sudah diterbitkan.
        $customer->update(['billing_address' => 'Jl. Snapshot Baru No. 9']);
        $proposal->update(['payment_terms' => 'Termin baru 14 hari']);

        $this->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Jl. Snapshot Awal No. 1')
            ->assertSee('Termin 30/60 hari')
            ->assertDontSee('Jl. Snapshot Baru No. 9')
            ->assertDontSee('Termin baru 14 hari');

        $this->assertSame('Jl. Snapshot Awal No. 1', $invoice->fresh()->billing_address);
        $this->assertSame('Termin 30/60 hari', $invoice->fresh()->payment_terms);
    }
}
