<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\ProjectDocument;
use App\Models\User;
use App\Services\AdminService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransactionDocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function admin(string $name = 'Admin'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole('admin');
        return $user;
    }

    private function sales(string $name = 'Sales'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole('sales');
        return $user;
    }

    private function technician(string $name = 'Teknisi'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole('technician');
        return $user;
    }

    private function superAdmin(string $name = 'Super Admin'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole('super-admin');
        return $user;
    }

    private function customer(string $name = 'PT Test'): Customer
    {
        return Customer::create(['name' => $name]);
    }

    // ============================================================
    // INVOICE OWNERSHIP
    // ============================================================

    public function test_admin_can_update_invoice_created_by_other_admin(): void
    {
        Storage::fake('private');
        $owner = $this->admin('Admin A');
        $other = $this->admin('Admin B');
        $customer = $this->customer();

        $this->actingAs($owner);
        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        $this->actingAs($other)
            ->put(route('admin.invoices.update', $invoice), [
                'customer_id' => $customer->id,
                'amount' => 999999,
                'status' => 'paid',
                'issue_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'amount' => 999999,
        ]);
    }

    public function test_admin_can_delete_invoice_created_by_other_admin(): void
    {
        Storage::fake('private');
        $owner = $this->admin('Admin A');
        $other = $this->admin('Admin B');
        $customer = $this->customer();

        $this->actingAs($owner);
        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        $this->actingAs($other)
            ->delete(route('admin.invoices.destroy', $invoice))
            ->assertRedirect();

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    public function test_admin_can_update_own_invoice(): void
    {
        Storage::fake('private');
        $owner = $this->admin('Admin A');
        $customer = $this->customer();

        $this->actingAs($owner);
        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        $this->actingAs($owner)
            ->put(route('admin.invoices.update', $invoice), [
                'customer_id' => $customer->id,
                'amount' => 2000000,
                'status' => 'paid',
                'issue_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'amount' => 2000000,
        ]);
    }

    public function test_super_admin_can_update_any_invoice(): void
    {
        Storage::fake('private');
        $owner = $this->admin('Admin A');
        $super = $this->superAdmin();
        $customer = $this->customer();

        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        $this->actingAs($super)
            ->put(route('admin.invoices.update', $invoice), [
                'customer_id' => $customer->id,
                'amount' => 5000000,
                'status' => 'cancelled',
                'issue_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'amount' => 5000000,
            'status' => 'cancelled',
        ]);
    }

    // ============================================================
    // PURCHASE ORDER OWNERSHIP
    // ============================================================

    public function test_admin_can_update_purchase_order_created_by_other_admin(): void
    {
        Storage::fake('private');
        $owner = $this->admin('Admin A');
        $other = $this->admin('Admin B');
        $customer = $this->customer();

        $this->actingAs($owner);
        $po = app(AdminService::class)->createPurchaseOrder([
            'customer_id' => $customer->id,
            'items' => 'Instalasi jaringan',
            'amount' => 2500000,
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
        ]);

        $this->actingAs($other)
            ->put(route('admin.pos.update', $po), [
                'customer_id' => $customer->id,
                'items' => 'Direvisi oleh Admin B',
                'amount' => 9999999,
                'status' => 'selesai',
                'issue_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'amount' => 9999999,
        ]);
    }

    public function test_admin_can_delete_purchase_order_created_by_other_admin(): void
    {
        Storage::fake('private');
        $owner = $this->admin('Admin A');
        $other = $this->admin('Admin B');
        $customer = $this->customer();

        $this->actingAs($owner);
        $po = app(AdminService::class)->createPurchaseOrder([
            'customer_id' => $customer->id,
            'items' => 'Instalasi jaringan',
            'amount' => 2500000,
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
        ]);

        $this->actingAs($other)
            ->delete(route('admin.pos.destroy', $po))
            ->assertRedirect();

        $this->assertSoftDeleted('purchase_orders', ['id' => $po->id]);
    }

    public function test_admin_can_update_own_purchase_order(): void
    {
        Storage::fake('private');
        $owner = $this->admin('Admin A');
        $customer = $this->customer();

        $this->actingAs($owner);
        $po = app(AdminService::class)->createPurchaseOrder([
            'customer_id' => $customer->id,
            'items' => 'Instalasi jaringan',
            'amount' => 2500000,
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
        ]);

        $this->actingAs($owner)
            ->put(route('admin.pos.update', $po), [
                'customer_id' => $customer->id,
                'items' => 'Instalasi jaringan direvisi',
                'amount' => 3000000,
                'status' => 'diproses',
                'issue_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'amount' => 3000000,
        ]);
    }

    // ============================================================
    // PAYMENT OWNERSHIP
    // ============================================================

    public function test_admin_can_delete_payment_created_by_other_admin(): void
    {
        Storage::fake('private');
        $owner = $this->admin('Admin A');
        $other = $this->admin('Admin B');
        $customer = $this->customer();

        $this->actingAs($owner);
        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        $payment = app(AdminService::class)->createPayment([
            'invoice_id' => $invoice->id,
            'amount' => 500000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
        ]);

        $this->actingAs($other)
            ->delete(route('admin.payments.destroy', $payment))
            ->assertRedirect();

        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
    }

    public function test_admin_can_delete_own_payment(): void
    {
        Storage::fake('private');
        $owner = $this->admin('Admin A');
        $customer = $this->customer();

        $this->actingAs($owner);
        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        $payment = app(AdminService::class)->createPayment([
            'invoice_id' => $invoice->id,
            'amount' => 500000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
        ]);

        $this->actingAs($owner)
            ->delete(route('admin.payments.destroy', $payment))
            ->assertRedirect();

        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
    }

    public function test_super_admin_can_delete_any_payment(): void
    {
        Storage::fake('private');
        $owner = $this->admin('Admin A');
        $super = $this->superAdmin();
        $customer = $this->customer();

        $this->actingAs($owner);
        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        $payment = app(AdminService::class)->createPayment([
            'invoice_id' => $invoice->id,
            'amount' => 500000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
        ]);

        $this->actingAs($super)
            ->delete(route('admin.payments.destroy', $payment))
            ->assertRedirect();

        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
    }

    // ============================================================
    // PAYMENT PROOF AUTHORIZATION
    // ============================================================

    public function test_admin_can_download_payment_proof(): void
    {
        Storage::fake('private');
        $admin = $this->admin('Admin A');
        $customer = $this->customer();
        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        $payment = app(AdminService::class)->createPayment([
            'invoice_id' => $invoice->id,
            'amount' => 500000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
            'proof_file' => 'documents/customer-' . $customer->id . '/proof.pdf',
        ]);

        Storage::disk('private')->put(
            'documents/customer-' . $customer->id . '/proof.pdf',
            'fake proof content'
        );

        $this->actingAs($admin)
            ->get(route('admin.payments.proof', $payment))
            ->assertOk();
    }

    public function test_sales_can_download_payment_proof(): void
    {
        Storage::fake('private');
        $sales = $this->sales('Sales A');
        $customer = $this->customer();
        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        $payment = app(AdminService::class)->createPayment([
            'invoice_id' => $invoice->id,
            'amount' => 500000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
            'proof_file' => 'documents/customer-' . $customer->id . '/proof.pdf',
        ]);

        Storage::disk('private')->put(
            'documents/customer-' . $customer->id . '/proof.pdf',
            'fake proof content'
        );

        $this->actingAs($sales)
            ->get(route('admin.payments.proof', $payment))
            ->assertOk();
    }

    public function test_unauthenticated_cannot_access_payment_proof(): void
    {
        $customer = $this->customer();
        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        $payment = app(AdminService::class)->createPayment([
            'invoice_id' => $invoice->id,
            'amount' => 500000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
            'proof_file' => 'documents/customer-' . $customer->id . '/proof.pdf',
        ]);

        $this->get(route('admin.payments.proof', $payment))
            ->assertRedirect();
    }

    public function test_technician_cannot_access_payment_proof(): void
    {
        Storage::fake('private');
        $tech = $this->technician();
        $customer = $this->customer();
        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        $payment = app(AdminService::class)->createPayment([
            'invoice_id' => $invoice->id,
            'amount' => 500000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
            'proof_file' => 'documents/customer-' . $customer->id . '/proof.pdf',
        ]);

        Storage::disk('private')->put(
            'documents/customer-' . $customer->id . '/proof.pdf',
            'fake proof content'
        );

        $this->actingAs($tech)
            ->get(route('admin.payments.proof', $payment))
            ->assertForbidden();
    }

    // ============================================================
    // SALES & TEKNISI TETAP DITOLAK
    // ============================================================

    public function test_sales_cannot_update_invoice(): void
    {
        $sales = $this->sales('Sales A');
        $customer = $this->customer();

        $this->actingAs($sales);
        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        $this->actingAs($sales)
            ->put(route('admin.invoices.update', $invoice), [
                'customer_id' => $customer->id,
                'amount' => 999999,
                'status' => 'paid',
                'issue_date' => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_technician_cannot_delete_invoice(): void
    {
        $tech = $this->technician();
        $customer = $this->customer();

        $this->actingAs($tech);
        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        $this->actingAs($tech)
            ->delete(route('admin.invoices.destroy', $invoice))
            ->assertForbidden();
    }

    // ============================================================
    // PROJECT DOCUMENT - TRANSACTION DOCUMENT LOCK
    // ============================================================

    public function test_technician_cannot_update_transaction_document_metadata(): void
    {
        Storage::fake('private');
        $tech = $this->technician();
        $customer = $this->customer();
        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ], UploadedFile::fake()->create('faktur.pdf', 100, 'application/pdf'));

        $document = ProjectDocument::where('documentable_type', Invoice::class)
            ->where('documentable_id', $invoice->id)
            ->first();

        $this->assertNotNull($document);

        $this->actingAs($tech)
            ->patch(route('project-documents.update', $document), [
                'name' => 'Hacked name',
            ])
            ->assertForbidden();
    }

    public function test_technician_cannot_archive_transaction_document(): void
    {
        Storage::fake('private');
        $tech = $this->technician();
        $customer = $this->customer();
        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ], UploadedFile::fake()->create('faktur.pdf', 100, 'application/pdf'));

        $document = ProjectDocument::where('documentable_type', Invoice::class)
            ->where('documentable_id', $invoice->id)
            ->first();

        $this->assertNotNull($document);

        $this->actingAs($tech)
            ->post(route('project-documents.archive', $document))
            ->assertForbidden();
    }

    public function test_technician_cannot_download_transaction_document(): void
    {
        Storage::fake('private');
        $tech = $this->technician();
        $customer = $this->customer();
        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ], UploadedFile::fake()->create('faktur.pdf', 100, 'application/pdf'));

        $document = ProjectDocument::where('documentable_type', Invoice::class)
            ->where('documentable_id', $invoice->id)
            ->first();

        $this->assertNotNull($document);

        $this->actingAs($tech)
            ->get(route('project-documents.download', $document))
            ->assertForbidden();
    }

    // ============================================================
    // ADMIN PAYMENT DELETE DELETES LINKED DOCUMENT
    // ============================================================

    public function test_delete_payment_preserves_proof_file_in_storage(): void
    {
        Storage::fake('private');
        $owner = $this->admin('Admin A');
        $customer = $this->customer();

        $this->actingAs($owner);
        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        $payment = app(AdminService::class)->createPayment([
            'invoice_id' => $invoice->id,
            'amount' => 500000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
        ], UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'));

        $document = ProjectDocument::where('documentable_type', Payment::class)
            ->where('documentable_id', $payment->id)
            ->first();

        $this->assertNotNull($document);
        $filePath = $document->file_path;
        Storage::disk('private')->assertExists($filePath);

        $this->actingAs($owner)
            ->delete(route('admin.payments.destroy', $payment))
            ->assertRedirect();

        // Bukti pembayaran soft-deleted (bukan terhapus permanen).
        $this->assertSoftDeleted('project_documents', ['id' => $document->id]);

        // File storage TETAP ADA (bisa dipulihkan dari Trash).
        Storage::disk('private')->assertExists($filePath);
    }

    public function test_delete_payment_soft_deletes_payment_record(): void
    {
        Storage::fake('private');
        $owner = $this->admin('Admin A');
        $customer = $this->customer();

        $this->actingAs($owner);
        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        $payment = app(AdminService::class)->createPayment([
            'invoice_id' => $invoice->id,
            'amount' => 500000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
        ], UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'));

        $this->actingAs($owner)
            ->delete(route('admin.payments.destroy', $payment))
            ->assertRedirect();

        // Payment soft-deleted (bukan terhapus permanen).
        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
    }
}
