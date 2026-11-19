<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\LeadDocument;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Proposal;
use App\Models\ProposalAttachment;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPurchaseOrder;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Batas akses Document Center per role/PT. Fokus: metadata sensitif tidak
 * muncul di HTML, dan endpoint file langsung tetap menolak role yang salah.
 */
class DocumentCenterAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('private');
    }

    private function user(string ...$roles): User
    {
        $user = User::factory()->create();
        $user->assignRole($roles);

        return $user;
    }

    private function customer(string $name, ?string $pt = null): Customer
    {
        return Customer::create(['name' => $name, 'pt_group' => $pt]);
    }

    private function project(Customer $customer): Project
    {
        $workType = WorkType::firstOrCreate(['name' => 'Instalasi']);

        return Project::withoutEvents(fn () => Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project '.$customer->name,
        ]));
    }

    private function fileDocument(Customer $customer, string $name, array $attributes = []): ProjectDocument
    {
        Storage::disk('private')->put('documents/'.$name, 'isi file');

        return ProjectDocument::create(array_merge([
            'customer_id' => $customer->id,
            'file_name' => $name,
            'file_path' => 'documents/'.$name,
            'file_size' => 100,
            'mime_type' => 'application/pdf',
            'uploaded_by' => User::first()?->id,
        ], $attributes));
    }

    private function paymentDocument(Customer $customer): ProjectDocument
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-PAY-1',
            'customer_id' => $customer->id,
            'amount' => 100000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        Storage::disk('private')->put('payments/proof.jpg', 'proof');
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
            'proof_file' => 'payments/proof.jpg',
        ]);

        return ProjectDocument::create([
            'customer_id' => $customer->id,
            'doc_type' => 'payment',
            'documentable_type' => Payment::class,
            'documentable_id' => $payment->id,
            'file_name' => 'bukti-transfer-rahasia.jpg',
            'file_path' => 'payments/proof.jpg',
            'file_size' => 10,
            'mime_type' => 'image/jpeg',
            'uploaded_by' => User::first()?->id,
        ]);
    }

    private function supplierInvoice(): SupplierInvoice
    {
        $supplier = Supplier::create(['name' => 'Supplier Rahasia']);
        $order = SupplierPurchaseOrder::create([
            'po_number' => 'SPO-ACCESS-1',
            'supplier_id' => $supplier->id,
            'issue_date' => now()->toDateString(),
            'status' => 'ordered',
        ]);
        Storage::disk('private')->put('supplier-invoices/secret.pdf', 'secret');

        return SupplierInvoice::create([
            'internal_ref_number' => 'SI-ACCESS-1',
            'supplier_invoice_number' => 'SUP-INV-ACCESS-1',
            'supplier_id' => $supplier->id,
            'supplier_purchase_order_id' => $order->id,
            'invoice_type' => 'full',
            'amount' => 100000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'invoice_file_path' => 'supplier-invoices/secret.pdf',
        ]);
    }

    private function quotation(Customer $customer): Proposal
    {
        $owner = User::factory()->create();
        $lead = Lead::create(['customer_id' => $customer->id, 'status' => 'cool']);
        $proposal = Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => 'QUO-ACCESS-1',
            'created_by' => $owner->id,
            'proposal_date' => now()->toDateString(),
            'customer' => $customer->name,
            'status' => 'draft',
        ]);
        ProposalAttachment::create([
            'proposal_id' => $proposal->id,
            'file_path' => 'quotations/access.pdf',
            'original_name' => 'quotation-rahasia.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
            'uploaded_by' => $owner->id,
        ]);

        return $proposal;
    }

    public function test_technician_cannot_see_finance_purchasing_or_quotation_documents(): void
    {
        $technician = $this->user('technician');
        $customer = $this->customer('PT Teknisi');
        $project = $this->project($customer);

        $this->fileDocument($customer, 'dokumen-proyek.pdf', ['project_id' => $project->id]);
        $this->paymentDocument($customer);
        $this->supplierInvoice();
        $this->quotation($customer);

        $this->actingAs($technician)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertSee('dokumen-proyek.pdf', false)
            ->assertDontSee('bukti-transfer-rahasia.jpg', false)
            ->assertDontSee('SUP-INV-ACCESS-1', false)
            ->assertDontSee('quotation-rahasia.pdf', false);
    }

    public function test_sales_only_sees_documents_in_own_pt(): void
    {
        $sales = $this->user('sales', 'sales-mgk');
        $own = $this->customer('PT MGK Sendiri', 'MGK');
        $other = $this->customer('PT NTI Lain', 'NTI');

        $this->fileDocument($own, 'milik-mgk.pdf');
        $this->fileDocument($other, 'milik-nti.pdf');

        $this->actingAs($sales)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertSee('milik-mgk.pdf', false)
            ->assertDontSee('milik-nti.pdf', false);
    }

    public function test_sales_cannot_see_or_download_payment_proof(): void
    {
        $sales = $this->user('sales');
        $customer = $this->customer('PT Sales');
        $document = $this->paymentDocument($customer);

        $this->actingAs($sales)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertDontSee('bukti-transfer-rahasia.jpg', false);

        $this->actingAs($sales)
            ->get(route('project-documents.download', $document))
            ->assertForbidden();
    }

    public function test_purchasing_sees_supplier_invoice_but_not_customer_payment_proof(): void
    {
        $purchasing = $this->user('purchasing');
        $customer = $this->customer('PT Purchasing');
        $this->paymentDocument($customer);
        $this->supplierInvoice();

        $this->actingAs($purchasing)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertSee('SUP-INV-ACCESS-1', false)
            ->assertDontSee('bukti-transfer-rahasia.jpg', false);
    }

    public function test_finance_sees_payment_proof_but_not_quotation(): void
    {
        $finance = $this->user('finance');
        $customer = $this->customer('PT Finance');
        $this->paymentDocument($customer);
        $this->quotation($customer);

        $this->actingAs($finance)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertSee('bukti-transfer-rahasia.jpg', false)
            ->assertDontSee('quotation-rahasia.pdf', false);
    }

    public function test_marketing_only_sees_lead_documents(): void
    {
        $marketing = $this->user('marketing');
        $customer = $this->customer('PT Marketing');
        $project = $this->project($customer);
        $this->fileDocument($customer, 'dokumen-proyek-rahasia.pdf', ['project_id' => $project->id]);

        $lead = Lead::create(['customer_id' => $customer->id, 'status' => 'warm']);
        Storage::disk('private')->put('leads/1/pitch.pdf', 'pitch');
        LeadDocument::create([
            'lead_id' => $lead->id,
            'file_name' => 'lampiran-lead.pdf',
            'file_path' => 'leads/1/pitch.pdf',
            'mime_type' => 'application/pdf',
            'category' => 'proposal',
        ]);

        $this->actingAs($marketing)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertSee('lampiran-lead.pdf', false)
            ->assertDontSee('dokumen-proyek-rahasia.pdf', false);
    }

    public function test_npwp_is_only_visible_to_finance_capable_roles(): void
    {
        $customer = $this->customer('PT NPWP');
        Storage::disk('private')->put('customers/1/npwp/npwp.pdf', 'npwp');
        $customer->forceFill([
            'npwp_document_path' => 'customers/1/npwp/npwp.pdf',
            'npwp_document_name' => 'npwp-rahasia.pdf',
        ])->save();

        $this->actingAs($this->user('admin'))
            ->get(route('documents.index'))
            ->assertOk()
            ->assertSee('npwp-rahasia.pdf', false);

        $this->actingAs($this->user('technician'))
            ->get(route('documents.index'))
            ->assertOk()
            ->assertDontSee('npwp-rahasia.pdf', false);
    }

    public function test_direct_endpoints_still_reject_unauthorized_roles(): void
    {
        $customer = $this->customer('PT Direct');
        $supplierInvoice = $this->supplierInvoice();
        $paymentDocument = $this->paymentDocument($customer);
        $project = $this->project($customer);
        $projectDocument = $this->fileDocument($customer, 'proyek.pdf', ['project_id' => $project->id]);

        $this->actingAs($this->user('sales'))
            ->get(route('admin.supplier-invoices.file', $supplierInvoice))
            ->assertForbidden();

        $this->actingAs($this->user('purchasing'))
            ->get(route('admin.payments.proof', $paymentDocument->documentable))
            ->assertForbidden();

        $this->actingAs($this->user('marketing'))
            ->get(route('project-documents.download', $projectDocument))
            ->assertForbidden();
    }
}
