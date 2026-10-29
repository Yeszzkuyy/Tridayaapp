<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DocumentActivity;
use App\Models\DocumentCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Ubah jenis dokumen (general/invoice/PO) dan label kategori.
 *
 * Jenis dokumen menentukan batas akses: hanya manage-admin, wajib menautkan
 * record transaksi milik customer yang sama, payment & dokumen modul/BAST
 * tidak boleh diubah.
 */
class DocumentReclassifyTest extends TestCase
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

    private function customer(string $name = 'PT Reclassify'): Customer
    {
        return Customer::create(['name' => $name, 'pt_group' => 'NTI']);
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

    private function document(Customer $customer, array $attributes = []): ProjectDocument
    {
        Storage::disk('private')->put('documents/reclassify.pdf', 'isi');

        return ProjectDocument::create(array_merge([
            'customer_id' => $customer->id,
            'file_name' => 'reclassify.pdf',
            'file_path' => 'documents/reclassify.pdf',
            'file_size' => 100,
            'mime_type' => 'application/pdf',
            'uploaded_by' => User::first()?->id,
        ], $attributes));
    }

    private function invoice(Customer $customer, string $number = 'INV-RC-1', string $status = 'unpaid'): Invoice
    {
        return Invoice::create([
            'invoice_number' => $number,
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => $status,
            'issue_date' => now()->toDateString(),
        ]);
    }

    private function purchaseOrder(Customer $customer, string $number = 'PO-RC-1'): PurchaseOrder
    {
        return PurchaseOrder::create([
            'po_number' => $number,
            'customer_id' => $customer->id,
            'items' => '[]',
            'amount' => 500000,
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
        ]);
    }

    private function payment(Customer $customer): Payment
    {
        $invoice = $this->invoice($customer, 'INV-PAY-RC');

        return Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
            'proof_file' => 'payments/rc.jpg',
        ]);
    }

    private function paymentDocument(Customer $customer): ProjectDocument
    {
        $payment = $this->payment($customer);

        return $this->document($customer, [
            'doc_type' => 'payment',
            'documentable_type' => Payment::class,
            'documentable_id' => $payment->id,
            'file_name' => 'bukti-rc.jpg',
        ]);
    }

    // ============================================================
    // UBAH JENIS
    // ============================================================

    public function test_admin_can_classify_general_document_as_invoice(): void
    {
        $admin = $this->user('admin');
        $customer = $this->customer();
        $invoice = $this->invoice($customer);
        $document = $this->document($customer);

        $this->actingAs($admin)
            ->patch(route('documents.update-type', $document), [
                'doc_type' => 'invoice',
                'transaction_id' => $invoice->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame('invoice', $document->doc_type);
        $this->assertSame(Invoice::class, $document->documentable_type);
        $this->assertSame($invoice->id, $document->documentable_id);

        $activity = DocumentActivity::where('document_id', $document->id)
            ->where('action', 'reclassified')
            ->first();
        $this->assertNotNull($activity);
        $this->assertSame('general', $activity->meta['from_type']);
        $this->assertSame('invoice', $activity->meta['to_type']);
    }

    public function test_admin_can_classify_general_document_as_legacy_po(): void
    {
        $admin = $this->user('admin');
        $customer = $this->customer();
        $order = $this->purchaseOrder($customer);
        $document = $this->document($customer);

        $this->actingAs($admin)
            ->patch(route('documents.update-type', $document), [
                'doc_type' => 'po',
                'transaction_id' => $order->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame('po', $document->doc_type);
        $this->assertSame(PurchaseOrder::class, $document->documentable_type);
        $this->assertSame($order->id, $document->documentable_id);
    }

    public function test_classifying_as_transaction_requires_a_linked_record(): void
    {
        $admin = $this->user('admin');
        $customer = $this->customer();
        $document = $this->document($customer);

        $this->actingAs($admin)
            ->patch(route('documents.update-type', $document), ['doc_type' => 'invoice'])
            ->assertSessionHasErrors('transaction_id');

        $this->assertNull($document->fresh()->doc_type);
    }

    public function test_cannot_link_transaction_of_another_customer(): void
    {
        $admin = $this->user('admin');
        $mine = $this->customer('PT Punya');
        $other = $this->customer('PT Lain');
        $invoice = $this->invoice($other, 'INV-OTHER');
        $document = $this->document($mine);

        $this->actingAs($admin)
            ->patch(route('documents.update-type', $document), [
                'doc_type' => 'invoice',
                'transaction_id' => $invoice->id,
            ])
            ->assertSessionHasErrors('transaction_id');

        $this->assertNull($document->fresh()->doc_type);
    }

    public function test_invoice_document_can_be_moved_back_to_general(): void
    {
        $admin = $this->user('admin');
        $customer = $this->customer();
        $invoice = $this->invoice($customer);
        $document = $this->document($customer, [
            'doc_type' => 'invoice',
            'documentable_type' => Invoice::class,
            'documentable_id' => $invoice->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('documents.update-type', $document), ['doc_type' => 'general'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertNull($document->doc_type);
        $this->assertNull($document->documentable_type);
        $this->assertNull($document->documentable_id);
    }

    public function test_admin_can_classify_general_document_as_payment(): void
    {
        $admin = $this->user('admin');
        $customer = $this->customer();
        $payment = $this->payment($customer);
        $document = $this->document($customer);

        $this->actingAs($admin)
            ->patch(route('documents.update-type', $document), [
                'doc_type' => 'payment',
                'transaction_id' => $payment->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame('payment', $document->doc_type);
        $this->assertSame(Payment::class, $document->documentable_type);
        $this->assertSame($payment->id, $document->documentable_id);

        // Bukti transfer utama tidak ikut diubah saat menautkan dokumen tambahan.
        $this->assertSame('payments/rc.jpg', $payment->fresh()->proof_file);
    }

    public function test_primary_payment_proof_cannot_be_moved_out(): void
    {
        $admin = $this->user('admin');
        $customer = $this->customer();
        $payment = $this->payment($customer);

        // File utama payment: payments.proof_file menunjuk path yang sama.
        $document = $this->document($customer, [
            'doc_type' => 'payment',
            'documentable_type' => Payment::class,
            'documentable_id' => $payment->id,
            'file_path' => 'payments/rc.jpg',
            'file_name' => 'bukti-utama.jpg',
        ]);
        $payment->update(['proof_file' => 'payments/rc.jpg']);

        $this->actingAs($admin)
            ->patch(route('documents.update-type', $document), ['doc_type' => 'general'])
            ->assertStatus(422);

        $this->assertSame('payment', $document->fresh()->doc_type);
    }

    public function test_additional_payment_document_can_move_to_general(): void
    {
        $admin = $this->user('admin');
        $customer = $this->customer();
        $document = $this->paymentDocument($customer);

        $this->actingAs($admin)
            ->patch(route('documents.update-type', $document), ['doc_type' => 'general'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertNull($document->doc_type);
        $this->assertNull($document->documentable_type);

        // Bukti transfer utama tetap utuh.
        $this->assertSame('payments/rc.jpg', Payment::first()->proof_file);
    }

    public function test_module_and_bast_documents_cannot_be_reclassified(): void
    {
        $admin = $this->user('admin');
        $customer = $this->customer();

        foreach (['survey', 'sizing', 'instalasi', 'bast'] as $type) {
            $document = $this->document($customer, ['doc_type' => $type, 'file_name' => $type.'.pdf']);

            $this->actingAs($admin)
                ->patch(route('documents.update-type', $document), ['doc_type' => 'general'])
                ->assertStatus(422);

            $this->assertSame($type, $document->fresh()->doc_type);
        }
    }

    public function test_non_admin_roles_cannot_change_document_type(): void
    {
        foreach (['sales', 'technician', 'marketing', 'finance', 'ceo', 'management'] as $role) {
            $customer = $this->customer('PT '.$role);
            $invoice = $this->invoice($customer, 'INV-'.strtoupper($role));
            $document = $this->document($customer);

            $this->actingAs($this->user($role))
                ->patch(route('documents.update-type', $document), [
                    'doc_type' => 'invoice',
                    'transaction_id' => $invoice->id,
                ])
                ->assertForbidden();

            $this->assertNull($document->fresh()->doc_type, "Role {$role} tidak boleh mengubah jenis dokumen.");
        }
    }

    // ============================================================
    // LABEL KATEGORI
    // ============================================================

    public function test_admin_can_change_category_label_of_transaction_document(): void
    {
        $admin = $this->user('admin');
        $customer = $this->customer();
        $invoice = $this->invoice($customer);
        $category = DocumentCategory::firstOrCreate(['name' => 'Dokumen Pendukung']);
        $document = $this->document($customer, [
            'doc_type' => 'invoice',
            'documentable_type' => Invoice::class,
            'documentable_id' => $invoice->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('documents.update-category', $document), [
                'document_category_id' => $category->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($category->id, $document->fresh()->document_category_id);
        $this->assertDatabaseHas('document_activities', [
            'document_id' => $document->id,
            'action' => 'categorized',
        ]);
    }

    public function test_technician_can_change_category_of_general_customer_document(): void
    {
        $technician = $this->user('technician');
        $customer = $this->customer();
        $category = DocumentCategory::firstOrCreate(['name' => 'Dokumentasi']);
        $document = $this->document($customer);

        $this->actingAs($technician)
            ->patch(route('documents.update-category', $document), [
                'document_category_id' => $category->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($category->id, $document->fresh()->document_category_id);
    }

    public function test_technician_cannot_change_category_of_transaction_document(): void
    {
        $technician = $this->user('technician');
        $customer = $this->customer();
        $invoice = $this->invoice($customer);
        $category = DocumentCategory::firstOrCreate(['name' => 'Dokumen Pendukung']);
        $document = $this->document($customer, [
            'doc_type' => 'invoice',
            'documentable_type' => Invoice::class,
            'documentable_id' => $invoice->id,
        ]);

        $this->actingAs($technician)
            ->patch(route('documents.update-category', $document), [
                'document_category_id' => $category->id,
            ])
            ->assertForbidden();
    }

    public function test_marketing_cannot_change_document_category(): void
    {
        $marketing = $this->user('marketing');
        $customer = $this->customer();
        $category = DocumentCategory::firstOrCreate(['name' => 'Dokumentasi']);
        $document = $this->document($customer);

        $this->actingAs($marketing)
            ->patch(route('documents.update-category', $document), [
                'document_category_id' => $category->id,
            ])
            ->assertForbidden();
    }

    // ============================================================
    // UPLOAD LANGSUNG DENGAN TRANSAKSI
    // ============================================================

    public function test_admin_upload_can_link_invoice_immediately(): void
    {
        $admin = $this->user('admin');
        $customer = $this->customer();
        $invoice = $this->invoice($customer, 'INV-UPLOAD-RC');

        $this->actingAs($admin)
            ->post(route('customer-documents.store', $customer), [
                'file' => UploadedFile::fake()->create('faktur.pdf', 100, 'application/pdf'),
                'doc_type' => 'invoice',
                'transaction_id' => $invoice->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $document = ProjectDocument::where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame('invoice', $document->doc_type);
        $this->assertSame(Invoice::class, $document->documentable_type);
        $this->assertSame($invoice->id, $document->documentable_id);
    }

    public function test_upload_with_transaction_of_another_customer_is_rejected(): void
    {
        $admin = $this->user('admin');
        $mine = $this->customer('PT Upload Saya');
        $other = $this->customer('PT Upload Lain');
        $invoice = $this->invoice($other, 'INV-UPLOAD-OTHER');

        $this->actingAs($admin)
            ->post(route('customer-documents.store', $mine), [
                'file' => UploadedFile::fake()->create('faktur.pdf', 100, 'application/pdf'),
                'doc_type' => 'invoice',
                'transaction_id' => $invoice->id,
            ])
            ->assertSessionHasErrors('transaction_id');

        $this->assertSame(0, ProjectDocument::where('customer_id', $mine->id)->count());
    }

    public function test_link_options_only_include_same_customer_and_hide_cancelled(): void
    {
        $admin = $this->user('admin');
        $mine = $this->customer('PT Opsi');
        $other = $this->customer('PT Opsi Lain');

        $this->invoice($mine, 'INV-OPTION-1');
        $this->invoice($mine, 'INV-CANCELLED', 'cancelled');
        $this->purchaseOrder($mine, 'PO-OPTION-1');
        $this->payment($mine);
        $this->invoice($other, 'INV-OTHER-OPTION');

        $this->document($mine);

        $response = $this->actingAs($admin)->get(route('documents.index'))->assertOk();
        $options = $response->viewData('documentLinkOptions')[$mine->id] ?? [];

        $invoiceLabels = array_column($options['invoice'] ?? [], 'label');
        $poLabels = array_column($options['po'] ?? [], 'label');
        $paymentLabels = array_column($options['payment'] ?? [], 'label');

        $this->assertTrue(collect($invoiceLabels)->contains(fn ($label) => str_contains($label, 'INV-OPTION-1')));
        $this->assertFalse(collect($invoiceLabels)->contains(fn ($label) => str_contains($label, 'INV-CANCELLED')));
        $this->assertFalse(collect($invoiceLabels)->contains(fn ($label) => str_contains($label, 'INV-OTHER-OPTION')));
        $this->assertTrue(collect($poLabels)->contains(fn ($label) => str_contains($label, 'PO-OPTION-1')));
        $this->assertNotEmpty($paymentLabels);
    }
}
