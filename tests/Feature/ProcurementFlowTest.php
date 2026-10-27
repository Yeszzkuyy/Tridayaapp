<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GoodsDelivery;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPurchaseOrder;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tahap 3–6 — alur pengadaan end-to-end:
 * terima barang parsial + serial, surat jalan, invoice/pembayaran supplier,
 * laporan, BAST, invoice pelanggan partial, closing komersial, dan hub.
 */
class ProcurementFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('private');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeSupplier(): Supplier
    {
        return Supplier::create([
            'code' => 'SUP-'.Str::upper(Str::random(5)),
            'name' => 'PT Pemasok '.Str::random(3),
            'is_active' => true,
        ]);
    }

    private function makeProduct(bool $serial = true): Product
    {
        return Product::create([
            'name' => 'Cisco Router '.Str::random(4),
            'sku' => 'RTR-'.Str::upper(Str::random(5)),
            'category' => 'barang',
            'unit' => 'unit',
            'default_price' => 15000000,
            'cost_price' => 12000000,
            'part_number' => 'C1111-8P',
            'has_serial_number' => $serial,
            'status' => Product::STATUS_APPROVED,
            'is_active' => true,
        ]);
    }

    private function makeProject(?User $sales = null): Project
    {
        $customer = Customer::create([
            'name' => 'PT Customer '.Str::random(4),
            'pt_group' => 'NTI',
        ]);

        $workType = WorkType::firstOrCreate(['name' => 'Instalasi & Aktivasi']);

        // Observer activity butuh user login; test ini tidak menguji observer.
        return Project::withoutEvents(fn () => Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project '.Str::random(5),
            'project_code' => 'PRJ-'.Str::upper(Str::random(6)),
            'progress' => 100,
            'requires_installation' => true,
        ]));
    }

    private function makeOrder(Project $project, Supplier $supplier, Product $product, float $qty = 10): SupplierPurchaseOrder
    {
        $order = SupplierPurchaseOrder::create([
            'po_number' => 'SPO-'.Str::upper(Str::random(8)),
            'supplier_id' => $supplier->id,
            'project_id' => $project->id,
            'issue_date' => now()->toDateString(),
            'status' => 'ordered',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'item_name' => $product->name,
            'part_number' => $product->part_number,
            'unit' => 'unit',
            'quantity_ordered' => $qty,
            'quantity_received' => 0,
            'unit_price' => 1000000,
            'subtotal' => $qty * 1000000,
        ]);

        $order->recalculate();

        return $order->fresh('items');
    }

    private function receiptPayload(SupplierPurchaseOrder $order, float $qty, string $serials = ''): array
    {
        return [
            'supplier_purchase_order_id' => $order->id,
            'received_date' => now()->toDateString(),
            'supplier_delivery_note_no' => 'SJ-'.Str::upper(Str::random(5)),
            'items' => [
                [
                    'supplier_purchase_order_item_id' => $order->items->first()->id,
                    'quantity_received' => $qty,
                    'serial_numbers_text' => $serials,
                    'condition_notes' => 'Good',
                ],
            ],
        ];
    }

    // ============================================================
    // TAHAP 3 — RECEIPT, SERIAL, PARTIAL, OVER-RECEIPT
    // ============================================================

    public function test_partial_receipt_updates_po_only_after_verification_and_tracks_serials(): void
    {
        $admin = $this->userWithRole('admin');
        $order = $this->makeOrder($this->makeProject(), $this->makeSupplier(), $this->makeProduct(), 10);

        $this->actingAs($admin)->post(route('admin.goods-receipts.store'), $this->receiptPayload($order, 6, 'SN-1,SN-2,SN-3,SN-4,SN-5,SN-6'))
            ->assertRedirect();

        $receipt = GoodsReceipt::firstOrFail();
        $this->assertSame('draft', $receipt->status);
        $this->assertSame(0.0, (float) $order->items->first()->fresh()->quantity_received);
        $this->assertSame(['SN-1', 'SN-2', 'SN-3', 'SN-4', 'SN-5', 'SN-6'], $receipt->items->first()->serial_numbers);

        $this->actingAs($admin)->post(route('admin.goods-receipts.verify', $receipt))->assertRedirect();

        $this->assertSame('verified', $receipt->fresh()->status);
        $this->assertSame(6.0, (float) $order->items->first()->fresh()->quantity_received);
        $this->assertSame('partially_received', $order->fresh()->status);
        $this->assertSame(4.0, $order->items->first()->fresh()->quantityRemaining());

        // Sisa 4 diterima pada surat jalan kedua.
        $this->actingAs($admin)->post(route('admin.goods-receipts.store'), $this->receiptPayload($order->fresh('items'), 4, 'SN-7,SN-8,SN-9,SN-10'))
            ->assertRedirect();
        $this->actingAs($admin)->post(route('admin.goods-receipts.verify', GoodsReceipt::latest('id')->firstOrFail()))
            ->assertRedirect();

        $this->assertSame(10.0, (float) $order->items->first()->fresh()->quantity_received);
        $this->assertSame('received', $order->fresh()->status);
    }

    public function test_over_receipt_and_duplicate_serial_are_rejected(): void
    {
        $admin = $this->userWithRole('admin');
        $order = $this->makeOrder($this->makeProject(), $this->makeSupplier(), $this->makeProduct(), 10);
        $item = $order->items->first();

        // Terima 7 dari 10, verifikasi.
        $this->actingAs($admin)->post(route('admin.goods-receipts.store'), $this->receiptPayload($order, 7, 'A1,A2,A3,A4,A5,A6,A7'))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.goods-receipts.verify', GoodsReceipt::firstOrFail()))->assertRedirect();

        // Sisa 3, minta 4 -> store ditolak.
        $this->actingAs($admin)->post(route('admin.goods-receipts.store'), $this->receiptPayload($order->fresh('items'), 4, 'B1,B2,B3,B4'))
            ->assertSessionHasErrors('items.0.quantity_received');
        $this->assertSame(1, GoodsReceipt::count());

        // Serial yang sudah pernah diterima juga ditolak.
        $this->actingAs($admin)->post(route('admin.goods-receipts.store'), $this->receiptPayload($order->fresh('items'), 3, 'A1,B2,B3'))
            ->assertSessionHasErrors('items.0.serial_numbers_text');

        // Jumlah serial tidak cocok dengan kuantitas juga ditolak.
        $this->actingAs($admin)->post(route('admin.goods-receipts.store'), $this->receiptPayload($order->fresh('items'), 3, 'C1,C2'))
            ->assertSessionHasErrors('items.0.serial_numbers_text');
    }

    public function test_delivery_note_and_closing_flow_requires_delivery_bast_and_paid_invoice(): void
    {
        $admin = $this->userWithRole('admin');
        $project = $this->makeProject();

        // 1. Buat surat jalan (belum terkirim).
        $this->actingAs($admin)->post(route('admin.goods-deliveries.store'), [
            'project_id' => $project->id,
            'delivery_date' => now()->toDateString(),
            'recipient_name' => 'Pak Penerima',
            'items' => [
                ['item_name' => 'Cisco Router', 'quantity' => 1, 'unit' => 'unit', 'serial_numbers_text' => 'SN-DLV-1'],
            ],
        ])->assertRedirect();

        $delivery = GoodsDelivery::firstOrFail();
        $this->assertSame('prepared', $delivery->status);
        $this->assertNull($project->fresh()->commercial_closed_at);

        // 2. BAST diisi admin.
        $this->actingAs($admin)->put(route('admin.bast.update', $project), [
            'bast_number' => 'BAST-001',
            'bast_date' => now()->toDateString(),
            'requires_installation' => 1,
        ])->assertRedirect();

        $this->assertSame('BAST-001', $project->fresh()->bast_number);
        $this->assertNull($project->fresh()->commercial_closed_at);

        // 3. Invoice pelanggan terbit, belum dibayar.
        $this->actingAs($admin)->post(route('admin.invoices.store'), [
            'customer_id' => $project->customer_id,
            'project_id' => $project->id,
            'amount' => 5000000,
            'invoice_type' => 'full',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ])->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertSame('full', $invoice->invoice_type);
        $this->assertNull($project->fresh()->commercial_closed_at);

        // 4. Barang terkirim.
        $this->actingAs($admin)->post(route('admin.goods-deliveries.status', $delivery), ['status' => 'delivered'])
            ->assertRedirect();
        $this->assertNull($project->fresh()->commercial_closed_at);

        // 5. Invoice dibayar penuh -> closing otomatis.
        $this->actingAs($admin)->post(route('admin.payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 5000000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
            'proof_file' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(5000000.0, (float) $invoice->fresh()->paid_amount);
        $this->assertNotNull($project->fresh()->commercial_closed_at);
    }

    // ============================================================
    // TAHAP 4 — SUPPLIER INVOICE & PAYMENT
    // ============================================================

    public function test_supplier_invoice_partial_payment_and_reports(): void
    {
        $admin = $this->userWithRole('admin');
        $order = $this->makeOrder($this->makeProject(), $this->makeSupplier(), $this->makeProduct(), 10);

        // Verifikasi receipt agar muncul di laporan unbilled.
        $this->actingAs($admin)->post(route('admin.goods-receipts.store'), $this->receiptPayload($order, 6, 'R1,R2,R3,R4,R5,R6'))->assertRedirect();
        $receipt = GoodsReceipt::firstOrFail();
        $this->actingAs($admin)->post(route('admin.goods-receipts.verify', $receipt))->assertRedirect();

        $this->actingAs($admin)->get(route('admin.purchasing.reports'))
            ->assertOk()
            ->assertSee($receipt->receipt_number, false)
            ->assertSee($order->po_number, false);

        // Catat invoice supplier.
        $this->actingAs($admin)->post(route('admin.supplier-invoices.store'), [
            'supplier_purchase_order_id' => $order->id,
            'goods_receipt_id' => $receipt->id,
            'supplier_invoice_number' => 'INV-SUP-001',
            'invoice_type' => 'partial',
            'amount' => 6000000,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
        ])->assertRedirect();

        $supplierInvoice = SupplierInvoice::firstOrFail();
        $this->assertSame('unpaid', $supplierInvoice->status);
        $this->assertSame(0.0, (float) $supplierInvoice->paid_amount);

        // Duplikat nomor invoice supplier ditolak.
        $this->actingAs($admin)->post(route('admin.supplier-invoices.store'), [
            'supplier_purchase_order_id' => $order->id,
            'supplier_invoice_number' => 'INV-SUP-001',
            'invoice_type' => 'full',
            'amount' => 1000000,
            'issue_date' => now()->toDateString(),
        ])->assertSessionHasErrors('supplier_invoice_number');

        // Pembayaran sebagian.
        $this->actingAs($admin)->post(route('admin.supplier-invoices.payments.store', $supplierInvoice), [
            'amount' => 2000000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
            'proof_file' => UploadedFile::fake()->create('transfer.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertSame('partial', $supplierInvoice->fresh()->status);
        $this->assertSame(2000000.0, (float) $supplierInvoice->fresh()->paid_amount);
        $this->assertSame(4000000.0, $supplierInvoice->fresh()->remainingAmount());

        // Pelunasan.
        $this->actingAs($admin)->post(route('admin.supplier-invoices.payments.store', $supplierInvoice), [
            'amount' => 4000000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
        ])->assertRedirect();

        $this->assertSame('paid', $supplierInvoice->fresh()->status);
        $this->assertSame(0.0, $supplierInvoice->fresh()->remainingAmount());

        // Invoice yang sudah ada pembayaran tidak boleh dicancel.
        $this->actingAs($admin)->post(route('admin.supplier-invoices.cancel', $supplierInvoice))
            ->assertSessionHasErrors('status');
    }

    public function test_payment_cannot_exceed_supplier_invoice_remaining(): void
    {
        $admin = $this->userWithRole('admin');
        $order = $this->makeOrder($this->makeProject(), $this->makeSupplier(), $this->makeProduct(false), 1);
        $invoice = SupplierInvoice::create([
            'internal_ref_number' => 'SINV-TEST-1',
            'supplier_invoice_number' => 'SUP-X-1',
            'supplier_id' => $order->supplier_id,
            'supplier_purchase_order_id' => $order->id,
            'invoice_type' => 'full',
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('admin.supplier-invoices.payments.store', $invoice), [
            'amount' => 1500000,
            'payment_date' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_supplier_invoice_is_rejected_for_cancelled_purchase_order(): void
    {
        $admin = $this->userWithRole('admin');
        $order = $this->makeOrder($this->makeProject(), $this->makeSupplier(), $this->makeProduct(false), 1);
        $order->update(['status' => 'cancelled']);

        $this->actingAs($admin)->post(route('admin.supplier-invoices.store'), [
            'supplier_purchase_order_id' => $order->id,
            'supplier_invoice_number' => 'SUP-CANCELLED-1',
            'invoice_type' => 'full',
            'amount' => 1000000,
            'issue_date' => now()->toDateString(),
        ])->assertStatus(422);

        $this->assertSame(0, SupplierInvoice::count());
    }

    // ============================================================
    // TAHAP 5 — INVOICE PULL & PARTIAL AR
    // ============================================================

    public function test_customer_invoice_prefill_from_project_and_partial_payment_status(): void
    {
        $admin = $this->userWithRole('admin');
        $project = $this->makeProject();
        $proposal = $this->makeProposal($project, 5000000);
        $project->update(['proposal_id' => $proposal->id]);

        // Form invoice project otomatis membawa sisa nilai quotation.
        $this->actingAs($admin)->get(route('admin.invoices.create', ['project_id' => $project->id]))
            ->assertOk()
            ->assertSee('value="5000000"', false);

        // Invoice 2 juta dibayar 1 juta -> partial (bukan unpaid).
        $this->actingAs($admin)->post(route('admin.invoices.store'), [
            'customer_id' => $project->customer_id,
            'project_id' => $project->id,
            'proposal_id' => $proposal->id,
            'amount' => 2000000,
            'invoice_type' => 'dp',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ])->assertRedirect();

        // Sisa quotation yang belum terinvoice menjadi nominal awal berikutnya.
        $this->actingAs($admin)->get(route('admin.invoices.create', ['project_id' => $project->id]))
            ->assertOk()
            ->assertSee('value="3000000"', false);

        // Invoice pelunasan 3 juta membuat quotation terinvoice penuh; form
        // berikutnya tidak boleh menawarkan nominal quotation lagi (anti double-bill).
        $this->actingAs($admin)->post(route('admin.invoices.store'), [
            'customer_id' => $project->customer_id,
            'project_id' => $project->id,
            'proposal_id' => $proposal->id,
            'amount' => 3000000,
            'invoice_type' => 'pelunasan',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($admin)->get(route('admin.invoices.create', ['project_id' => $project->id]))
            ->assertOk()
            ->assertSee('value="0"', false);

        $invoice = Invoice::firstOrFail();
        $this->actingAs($admin)->post(route('admin.payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 1000000,
            'payment_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertSame(1000000.0, (float) $invoice->fresh()->paid_amount);
        $this->assertSame($proposal->id, $invoice->proposal_id);

        // Halaman index/show tetap menampilkan label Partial.
        $this->actingAs($admin)->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertSee(__('Partial'), false);
    }

    private function makeProposal(Project $project, float $grandTotal): Proposal
    {
        $lead = Lead::create([
            'customer_id' => $project->customer_id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'hot',
        ]);

        $proposal = Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => 'Q/PRC/'.Str::upper(Str::random(5)),
            'proposal_date' => now()->toDateString(),
            'customer' => $project->customer->name,
            'status' => 'accepted',
        ]);

        $proposal->items()->create([
            'description' => 'Paket perangkat',
            'quantity' => 1,
            'unit' => 'paket',
            'unit_price' => $grandTotal,
            'unit_cost' => 0,
            'discount' => 0,
            'subtotal' => $grandTotal,
        ]);
        $proposal->recalculate();

        return $proposal->fresh();
    }

    // ============================================================
    // TAHAP 6 — HUB & AKSES
    // ============================================================

    public function test_admin_hub_shows_procurement_tab_and_queues(): void
    {
        $admin = $this->userWithRole('admin');
        $order = $this->makeOrder($this->makeProject(), $this->makeSupplier(), $this->makeProduct(false), 5);

        $this->actingAs($admin)->get(route('admin.hub'))
            ->assertOk()
            ->assertSee('4. Procurement', false)
            ->assertSee($order->po_number, false);
    }

    public function test_hub_shows_delivery_deadline_risk_for_undelivered_project(): void
    {
        $admin = $this->userWithRole('admin');
        $project = $this->makeProject();
        $project->update(['delivery_deadline' => now()->addDays(3)->toDateString()]);

        $this->actingAs($admin)->get(route('admin.hub'))
            ->assertOk()
            ->assertSee(__('Delivery Deadline Risk'))
            ->assertSee($project->project_name);
    }

    public function test_hub_shows_quotation_deal_ready_for_supplier_po(): void
    {
        $admin = $this->userWithRole('admin');
        $project = $this->makeProject();
        $proposal = $this->makeProposal($project, 2500000);
        $proposal->update([
            'po_document_path' => 'customer-po/test.pdf',
            'po_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)->get(route('admin.hub'))
            ->assertOk()
            ->assertSee(__('Quotation Deals Ready for Supplier PO'))
            ->assertSee($proposal->proposal_number);
    }

    public function test_procurement_pages_smoke_and_sales_is_read_only(): void
    {
        $admin = $this->userWithRole('admin');
        $sales = $this->userWithRole('sales');
        $marketing = $this->userWithRole('marketing');
        $order = $this->makeOrder($this->makeProject(), $this->makeSupplier(), $this->makeProduct(false), 5);

        foreach ([
            route('admin.goods-receipts.index'),
            route('admin.goods-receipts.create', ['supplier_purchase_order_id' => $order->id]),
            route('admin.goods-deliveries.index'),
            route('admin.goods-deliveries.create'),
            route('admin.supplier-invoices.index'),
            route('admin.supplier-invoices.create', ['supplier_purchase_order_id' => $order->id]),
            route('admin.purchasing.reports'),
            route('admin.bast.index'),
            route('admin.bast.edit', $order->project_id),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        // Sales punya view-admin (read-only): boleh lihat, tidak boleh tulis.
        $this->actingAs($sales)->get(route('admin.goods-receipts.index'))->assertOk();
        $this->actingAs($sales)->get(route('admin.supplier-invoices.index'))->assertOk();
        $this->actingAs($sales)->post(route('admin.goods-receipts.store'), $this->receiptPayload($order, 1, 'X1'))->assertForbidden();

        // Marketing tidak punya view-admin/manage-admin: tertutup total.
        $this->actingAs($marketing)->get(route('admin.goods-receipts.index'))->assertForbidden();
        $this->actingAs($marketing)->get(route('admin.supplier-invoices.index'))->assertForbidden();
    }
}
