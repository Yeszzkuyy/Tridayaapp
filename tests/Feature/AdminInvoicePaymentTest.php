<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProjectDocument;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\AdminService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminInvoicePaymentTest extends TestCase
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

    private function customer(string $name = 'PT Panel'): Customer
    {
        return Customer::create(['name' => $name]);
    }

    public function test_admin_membuat_invoice_dengan_nomor_otomatis_dan_file_terintegrasi(): void
    {
        Storage::fake('private');
        $this->actingAs($this->admin());
        $customer = $this->customer('PT Invoice');

        $this->post(route('admin.invoices.store'), [
            'customer_id' => $customer->id,
            'amount' => 1500000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'notes' => 'DP 50%',
            'file' => UploadedFile::fake()->create('faktur.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertSame('INV-'.now()->year.'-0001', $invoice->invoice_number);

        $document = ProjectDocument::where('documentable_type', Invoice::class)
            ->where('documentable_id', $invoice->id)
            ->firstOrFail();

        $this->assertSame('invoice', $document->doc_type);
        $this->assertSame($customer->id, $document->customer_id);
        $this->assertSame('faktur.pdf', $document->file_name);
        Storage::disk('private')->assertExists($document->file_path);

        // Integrasi: file dan data invoice tampil di tab Documents customer.
        $this->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('faktur.pdf', false)
            ->assertSee($invoice->invoice_number, false);
    }

    public function test_admin_membuat_po_dengan_nomor_otomatis_dan_file_terintegrasi(): void
    {
        Storage::fake('private');
        $this->actingAs($this->admin());
        $customer = $this->customer('PT PO');

        $this->post(route('admin.pos.store'), [
            'customer_id' => $customer->id,
            'items' => 'Instalasi jaringan 1 paket',
            'amount' => 2500000,
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'file' => UploadedFile::fake()->create('po.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $po = PurchaseOrder::firstOrFail();
        $this->assertSame('PO-'.now()->year.'-0001', $po->po_number);

        $document = ProjectDocument::where('documentable_type', PurchaseOrder::class)
            ->where('documentable_id', $po->id)
            ->firstOrFail();

        $this->assertSame('po', $document->doc_type);

        $this->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('po.pdf', false)
            ->assertSee($po->po_number, false);
    }

    public function test_payment_boleh_dicicil_dan_status_invoice_mengikuti_total(): void
    {
        Storage::fake('private');
        $this->actingAs($this->admin());
        $customer = $this->customer('PT Cicilan');

        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        // Cicilan pertama: belum lunas.
        $this->post(route('admin.payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 400000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
            'proof_file' => UploadedFile::fake()->create('bukti1.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        // Tahap 5: pembayaran sebagian kini berstatus partial.
        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertSame(400000.0, (float) $invoice->fresh()->paid_amount);

        // Cicilan kedua: total lunas -> paid otomatis.
        $this->post(route('admin.payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 600000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
        ])->assertRedirect();

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(0.0, $invoice->fresh()->remainingAmount());

        $first = Payment::orderBy('id')->firstOrFail();
        $this->assertDatabaseHas('project_documents', [
            'documentable_type' => Payment::class,
            'documentable_id' => $first->id,
            'doc_type' => 'payment',
        ]);

        // Hapus cicilan terakhir: sisa 400rb -> status kembali partial.
        $last = Payment::orderByDesc('id')->firstOrFail();
        $this->delete(route('admin.payments.destroy', $last))->assertRedirect();

        $this->assertSame('partial', $invoice->fresh()->status);
    }

    public function test_hub_kpi_menghitung_sisa_invoice_partial_sebagai_piutang(): void
    {
        $this->actingAs($this->admin());
        $customer = $this->customer('PT Partial KPI');

        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(),
        ]);

        app(AdminService::class)->createPayment([
            'invoice_id' => $invoice->id,
            'amount' => 400000,
            'payment_date' => now()->toDateString(),
        ]);

        $hub = app(AdminService::class)->getHubData();

        $this->assertSame(1, $hub['kpi']['unpaid_invoices_count']);
        $this->assertSame(600000.0, (float) $hub['kpi']['unpaid_invoices_amount']);
        $this->assertSame(1, $hub['kpi']['overdue_invoices_count']);
        $this->assertSame(600000.0, (float) $hub['kpi']['overdue_invoices_amount']);
    }

    public function test_sales_read_only_tidak_bisa_membuat_invoice(): void
    {
        $sales = User::factory()->create()->assignRole('sales');
        $this->actingAs($sales);
        $customer = $this->customer('PT Sales Read Only');

        $this->get(route('admin.invoices.create'))->assertForbidden();
        $this->post(route('admin.invoices.store'), [
            'customer_id' => $customer->id,
            'amount' => 100000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ])->assertForbidden();

        $this->assertSame(0, Invoice::count());
    }

    public function test_invoice_cancelled_tidak_bisa_dibayar(): void
    {
        $this->actingAs($this->admin());
        $customer = $this->customer('PT Batal');

        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 500000,
            'status' => 'cancelled',
            'issue_date' => now()->toDateString(),
        ]);

        $this->post(route('admin.payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 500000,
            'payment_date' => now()->toDateString(),
        ])->assertSessionHasErrors('invoice_id');

        $this->assertSame(0, Payment::count());
    }

    public function test_halaman_admin_invoice_po_payment_bisa_dibuka(): void
    {
        $this->actingAs($this->admin());
        $customer = $this->customer('PT Halaman');

        $invoice = app(AdminService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 750000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        $po = app(AdminService::class)->createPurchaseOrder([
            'customer_id' => $customer->id,
            'items' => 'Instalasi',
            'amount' => 750000,
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
        ]);

        $payment = app(AdminService::class)->createPayment([
            'invoice_id' => $invoice->id,
            'amount' => 250000,
            'payment_date' => now()->toDateString(),
        ]);

        $this->get(route('admin.invoices.index'))->assertOk();
        $this->get(route('admin.invoices.create'))->assertOk()->assertSee('Create Invoice', false);
        $this->get(route('admin.invoices.show', $invoice))->assertOk()->assertSee($invoice->invoice_number, false);
        $this->get(route('admin.invoices.edit', $invoice))->assertOk();

        $this->get(route('admin.pos.index'))->assertOk();
        $this->get(route('admin.pos.create'))->assertOk();
        $this->get(route('admin.pos.show', $po))->assertOk()->assertSee($po->po_number, false);
        $this->get(route('admin.pos.edit', $po))->assertOk();

        $this->get(route('admin.payments.index'))->assertOk();
        $this->get(route('admin.payments.create'))->assertOk();
        $this->get(route('admin.payments.show', $payment))->assertOk()->assertSee($invoice->invoice_number, false);
    }
}
