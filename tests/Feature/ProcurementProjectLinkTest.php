<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPurchaseOrder;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementProjectLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_supplier_po_receipt_and_invoice_are_linked_when_project_is_created(): void
    {
        $sales = User::factory()->create()->assignRole('sales');
        $customer = Customer::create(['name' => 'PT Project Link', 'pt_group' => 'NTI']);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'assigned_to' => $sales->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'won',
        ]);

        $proposal = Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => 'Q-LINK-'.uniqid(),
            'created_by' => $sales->id,
            'proposal_date' => now()->toDateString(),
            'customer' => $customer->name,
            'status' => 'accepted',
            'po_number' => 'PO-CUST-LINK',
            'po_document_path' => 'proposals/link/po.pdf',
            'payment_scheme' => 'full',
        ]);

        $supplier = Supplier::create([
            'code' => 'SUP-LINK',
            'name' => 'PT Supplier Link',
            'is_active' => true,
        ]);

        $order = SupplierPurchaseOrder::create([
            'po_number' => 'SPO-LINK-'.uniqid(),
            'supplier_id' => $supplier->id,
            'proposal_id' => $proposal->id,
            'project_id' => null,
            'issue_date' => now()->toDateString(),
            'status' => 'ordered',
        ]);

        $receipt = GoodsReceipt::create([
            'receipt_number' => 'GR-LINK-'.uniqid(),
            'supplier_purchase_order_id' => $order->id,
            'supplier_id' => $supplier->id,
            'project_id' => null,
            'received_date' => now()->toDateString(),
            'received_by' => $sales->id,
            'status' => 'verified',
        ]);

        $invoice = SupplierInvoice::create([
            'internal_ref_number' => 'SINV-LINK-'.uniqid(),
            'supplier_invoice_number' => 'SUP-LINK-'.uniqid(),
            'supplier_id' => $supplier->id,
            'supplier_purchase_order_id' => $order->id,
            'project_id' => null,
            'invoice_type' => 'full',
            'amount' => 1000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        $workType = WorkType::create(['name' => 'Instalasi Link']);

        $this->actingAs($sales)->post(route('sales.proposals.convert', $proposal), [
            'work_type_id' => $workType->id,
        ])->assertRedirect();

        $project = Project::where('proposal_id', $proposal->id)->firstOrFail();

        $this->assertSame($project->id, $order->fresh()->project_id);
        $this->assertSame($project->id, $receipt->fresh()->project_id);
        $this->assertSame($project->id, $invoice->fresh()->project_id);
    }
}
