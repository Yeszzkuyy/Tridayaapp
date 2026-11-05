<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\ProposalAttachment;
use App\Models\ProposalItem;
use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Models\SupplierPurchaseOrderItem;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminHandoffPoVerificationTest extends TestCase
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

    private function makeProposal(User $sales, array $attributes = []): Proposal
    {
        $customer = Customer::create(['name' => 'PT Pelanggan Handal']);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'assigned_to' => $sales->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'hot',
        ]);

        $proposal = Proposal::create(array_merge([
            'lead_id' => $lead->id,
            'proposal_number' => 'Q/TEST/' . uniqid(),
            'created_by' => $sales->id,
            'proposal_date' => now()->toDateString(),
            'customer' => $customer->name,
            'status' => 'accepted',
            'po_number' => 'PO-CUST-100',
            'po_date' => now()->toDateString(),
            'po_document_path' => 'proposals/test/po.pdf',
            'payment_scheme' => 'full',
            'subtotal' => 10000000,
            'discount' => 0,
            'tax' => 0,
            'grand_total' => 10000000,
        ], $attributes));

        ProposalItem::create([
            'proposal_id' => $proposal->id,
            'description' => 'Server Unit Rackmount',
            'quantity' => 2,
            'unit' => 'unit',
            'unit_price' => 5000000,
            'unit_cost' => 4000000,
            'discount' => 0,
            'subtotal' => 10000000,
        ]);

        return $proposal->fresh(['lead.customer', 'items']);
    }

    public function test_admin_can_verify_customer_po(): void
    {
        $sales = $this->userWithRole('sales');
        $admin = $this->userWithRole('admin');
        $proposal = $this->makeProposal($sales);

        $this->assertFalse($proposal->isPoVerified());

        // Admin verifies PO
        $response = $this->actingAs($admin)
            ->post(route('admin.purchasing.verify-po', $proposal));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $proposal->refresh();
        $this->assertTrue($proposal->isPoVerified());
        $this->assertNotNull($proposal->po_verified_at);
        $this->assertSame($admin->id, $proposal->po_verified_by);
        $this->assertSame('PO Verified', $proposal->purchasingStatusLabel());

        // Regular sales without admin permission cannot verify PO
        $otherSales = $this->userWithRole('sales');
        $this->actingAs($otherSales)
            ->post(route('admin.purchasing.verify-po', $proposal))
            ->assertForbidden();
    }

    public function test_admin_can_return_customer_po_with_revision_reason(): void
    {
        $sales = $this->userWithRole('sales');
        $admin = $this->userWithRole('admin');
        $proposal = $this->makeProposal($sales);

        // Admin returns PO with reason
        $response = $this->actingAs($admin)
            ->post(route('admin.purchasing.return-po', $proposal), [
                'reason' => 'Nomor PO tidak terbaca, mohon scan ulang dengan jelas.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $proposal->refresh();
        $this->assertTrue($proposal->isPoReturned());
        $this->assertNotNull($proposal->po_returned_at);
        $this->assertSame($admin->id, $proposal->po_returned_by);
        $this->assertSame('Nomor PO tidak terbaca, mohon scan ulang dengan jelas.', $proposal->po_return_reason);
        $this->assertSame('PO Returned (Revision Needed)', $proposal->purchasingStatusLabel());

        // Sales sees alert banner when viewing proposal
        $this->actingAs($sales)
            ->get(route('sales.proposals.show', $proposal))
            ->assertOk()
            ->assertSee('Customer PO Returned by Admin')
            ->assertSee('Nomor PO tidak terbaca, mohon scan ulang dengan jelas.');
    }

    public function test_sales_reuploading_po_resets_po_return_status(): void
    {
        Storage::fake('private');
        $sales = $this->userWithRole('sales');
        $admin = $this->userWithRole('admin');
        $proposal = $this->makeProposal($sales, [
            'po_returned_at' => now(),
            'po_returned_by' => $admin->id,
            'po_return_reason' => 'Revisi nilai nominal',
        ]);

        $this->assertTrue($proposal->isPoReturned());

        // Sales re-uploads new PO document
        $this->actingAs($sales)
            ->post(route('sales.proposals.upload-po', $proposal), [
                'po_file' => UploadedFile::fake()->create('revised_po.pdf', 150, 'application/pdf'),
                'po_number' => 'PO-REV-200',
                'po_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $proposal->refresh();
        $this->assertFalse($proposal->isPoReturned());
        $this->assertNull($proposal->po_returned_at);
        $this->assertNull($proposal->po_returned_by);
        $this->assertNull($proposal->po_return_reason);
        $this->assertSame('PO-REV-200', $proposal->po_number);
    }

    public function test_admin_can_download_original_quotation_attachments_from_handoff_view(): void
    {
        Storage::fake('private');
        $sales = $this->userWithRole('sales');
        $admin = $this->userWithRole('admin');
        $proposal = $this->makeProposal($sales);

        $fakeFilePath = 'proposals/' . $proposal->id . '/files/signed_quotation.pdf';
        Storage::disk('private')->put($fakeFilePath, 'Fake PDF Content');

        ProposalAttachment::create([
            'proposal_id' => $proposal->id,
            'file_path' => $fakeFilePath,
            'original_name' => 'signed_quotation.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
            'uploaded_by' => $sales->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.purchasing.show', $proposal))
            ->assertOk()
            ->assertSee('Original Quotation Files')
            ->assertSee('signed_quotation.pdf')
            ->assertSee('Download Quotation');
    }

    public function test_admin_or_management_can_convert_proposal_to_project_as_fallback(): void
    {
        $sales = $this->userWithRole('sales');
        $admin = $this->userWithRole('admin');
        $proposal = $this->makeProposal($sales);
        $workType = WorkType::firstOrCreate(['name' => 'Pengadaan Server']);

        // Admin performs fallback conversion
        $response = $this->actingAs($admin)
            ->post(route('sales.proposals.convert', $proposal), [
                'work_type_id' => $workType->id,
                'requires_delivery' => '1',
                'requires_installation' => '0',
            ]);

        $response->assertRedirect();

        $project = $proposal->fresh()->project;
        $this->assertNotNull($project);
        $this->assertTrue((bool) $project->requires_delivery);
        $this->assertFalse((bool) $project->requires_installation);
    }

    public function test_pure_service_project_without_delivery_can_close_commercially(): void
    {
        $customer = Customer::create(['name' => 'PT Layanan Jasa']);
        $workType = WorkType::firstOrCreate(['name' => 'Konsultasi IT']);

        $project = Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Jasa Audit Keamanan IT',
            'progress' => 100,
            'requires_delivery' => false,
            'requires_installation' => false,
            'bast_number' => 'BAST/2026/001',
        ]);

        $invoice = Invoice::create([
            'project_id' => $project->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV/TEST/001',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 25000000,
            'discount' => 0,
            'tax' => 0,
            'amount' => 25000000,
            'paid_amount' => 25000000,
            'status' => 'paid',
        ]);

        $this->assertNull($project->commercial_closed_at);

        $closed = $project->evaluateCommercialClosing();
        $this->assertTrue($closed);

        $project->refresh();
        $this->assertNotNull($project->commercial_closed_at);
    }

    public function test_partial_sourcing_keeps_quotation_in_pending_supplier_po_queue(): void
    {
        $sales = $this->userWithRole('sales');
        $proposal = $this->makeProposal($sales);

        // Tambah item kedua
        $item1 = $proposal->items()->first(); // quantity = 2
        $item2 = ProposalItem::create([
            'proposal_id' => $proposal->id,
            'description' => 'Switch Gigabit 24 Port',
            'quantity' => 5,
            'unit' => 'unit',
            'unit_price' => 2000000,
            'unit_cost' => 1500000,
            'discount' => 0,
            'subtotal' => 10000000,
        ]);

        $supplier = Supplier::create([
            'name' => 'PT Distributor Serverindo',
            'is_active' => true,
        ]);

        // Proposal harus ada di antrean pendingSupplierPo
        $this->assertTrue(Proposal::pendingSupplierPo()->where('id', $proposal->id)->exists());
        $this->assertFalse($proposal->isFullySourced());

        // Buat Supplier PO pertama: hanya memesan item 1 tuntas (qty 2)
        $spo1 = SupplierPurchaseOrder::create([
            'po_number' => 'SPO/TEST/001',
            'supplier_id' => $supplier->id,
            'proposal_id' => $proposal->id,
            'issue_date' => now()->toDateString(),
            'status' => 'ordered',
        ]);

        SupplierPurchaseOrderItem::create([
            'supplier_purchase_order_id' => $spo1->id,
            'proposal_item_id' => $item1->id,
            'item_name' => $item1->description,
            'quantity_ordered' => 2,
            'quantity_received' => 0,
            'unit_price' => 4000000,
            'subtotal' => 8000000,
        ]);

        // Karena item 2 belum dipesan (masih 0 dari 5), proposal HARUS tetap berada di antrean!
        $this->assertTrue(Proposal::pendingSupplierPo()->where('id', $proposal->id)->exists());
        $this->assertFalse($proposal->isFullySourced());

        // Buat Supplier PO kedua: memesan item 2 (qty 5)
        $spo2 = SupplierPurchaseOrder::create([
            'po_number' => 'SPO/TEST/002',
            'supplier_id' => $supplier->id,
            'proposal_id' => $proposal->id,
            'issue_date' => now()->toDateString(),
            'status' => 'ordered',
        ]);

        SupplierPurchaseOrderItem::create([
            'supplier_purchase_order_id' => $spo2->id,
            'proposal_item_id' => $item2->id,
            'item_name' => $item2->description,
            'quantity_ordered' => 5,
            'quantity_received' => 0,
            'unit_price' => 1500000,
            'subtotal' => 7500000,
        ]);

        // Sekarang seluruh item sudah tuntas dipesan!
        $this->assertFalse(Proposal::pendingSupplierPo()->where('id', $proposal->id)->exists());
        $this->assertTrue($proposal->isFullySourced());
    }
}
