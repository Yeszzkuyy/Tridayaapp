<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tahap 2 — Master Data Barang & Supplier Purchasing.
 *
 * Membuktikan master produk/pemasok, pemisahan Customer PO vs Supplier PO,
 * pengambilan & pemecahan item quotation, snapshot, idempotensi, dan
 * kompatibilitas Paket 1–3.
 */
class SupplierPurchasingTest extends TestCase
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

    private function makeSupplier(string $name = 'PT Pemasok'): Supplier
    {
        return Supplier::create([
            'code' => 'SUP-'.Str::upper(Str::random(6)),
            'name' => $name,
            'contact_person' => 'Budi',
            'phone' => '0211234567',
            'email' => 'sales@supplier.test',
            'is_active' => true,
        ]);
    }

    private function makeProduct(array $attrs = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Cisco Router C1111',
            'sku' => 'RTR-'.Str::upper(Str::random(5)),
            'category' => 'barang',
            'unit' => 'unit',
            'default_price' => 15000000,
            'cost_price' => 12000000,
            'part_number' => 'C1111-8P',
            'has_serial_number' => true,
            'description' => 'Router cabang',
            'status' => Product::STATUS_APPROVED,
            'is_active' => true,
        ], $attrs));
    }

    /** Quotation accepted + customer PO terunggah = memenuhi syarat handoff. */
    private function makeEligibleProposal(?User $sales = null, array $itemAttrs = []): Proposal
    {
        $customer = Customer::create(['name' => 'PT Customer '.Str::random(4)]);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'hot',
        ]);

        $proposal = Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => 'Q/SPO/'.Str::upper(Str::random(6)),
            'created_by' => $sales?->id,
            'proposal_date' => now()->toDateString(),
            'customer' => $customer->name,
            'po_number' => 'PO-CUST-'.Str::upper(Str::random(4)),
            'po_date' => now()->toDateString(),
            'po_document_path' => 'customer-po/po-'.Str::random(6).'.pdf',
            'delivery_deadline' => now()->addDays(14)->toDateString(),
            'status' => 'accepted',
        ]);

        ProposalItem::create(array_merge([
            'proposal_id' => $proposal->id,
            'description' => 'Cisco Router C1111',
            'quantity' => 5,
            'unit' => 'unit',
            'unit_price' => 15000000,
            'unit_cost' => 12000000,
            'discount' => 0,
            'subtotal' => 75000000,
        ], $itemAttrs));

        $proposal->recalculate();

        return $proposal->fresh();
    }

    private function poPayload(Proposal $proposal, Supplier $supplier, array $items, array $extra = []): array
    {
        return array_merge([
            'idempotency_key' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'proposal_id' => $proposal->id,
            'issue_date' => now()->toDateString(),
            'expected_delivery_date' => now()->addDays(7)->toDateString(),
            'discount' => 0,
            'tax' => 0,
            'items' => $items,
        ], $extra);
    }

    private function itemRow(array $attrs = []): array
    {
        return array_merge([
            'item_name' => 'Cisco Router C1111',
            'part_number' => 'C1111-8P',
            'unit' => 'unit',
            'quantity_ordered' => 1,
            'unit_price' => 12000000,
        ], $attrs);
    }

    // ============================================================
    // 1–3. MASTER PRODUK & PEMASOK
    // ============================================================

    public function test_product_master_stores_goods_and_service_data_with_cost_price(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post(route('products.store'), [
            'name' => 'Cisco Router C1111',
            'sku' => 'RTR-C1111',
            'part_number' => 'C1111-8P',
            'description' => 'Router cabang dengan 8 port',
            'category' => 'barang',
            'unit' => 'unit',
            'default_price' => 15000000,
            'cost_price' => 12000000,
            'has_serial_number' => 1,
            'is_active' => 1,
        ])->assertRedirect(route('products.index'));

        $product = Product::where('sku', 'RTR-C1111')->firstOrFail();

        $this->assertSame('C1111-8P', $product->part_number);
        $this->assertSame('Router cabang dengan 8 port', $product->description);
        $this->assertTrue($product->has_serial_number);
        $this->assertEqualsWithDelta(12000000.0, (float) $product->cost_price, 0.01);

        // Harga modal memakai cost_price — TIDAK ada kolom duplikat buy_price.
        $this->assertFalse(Schema::hasColumn('products', 'buy_price'));
        $this->assertTrue(Schema::hasColumn('products', 'cost_price'));

        // Barang jasa juga bisa disimpan tanpa nomor seri.
        $this->actingAs($admin)->post(route('products.store'), [
            'name' => 'Jasa Instalasi',
            'sku' => 'SRV-INSTALL',
            'category' => 'jasa',
            'unit' => 'paket',
            'default_price' => 2500000,
            'cost_price' => 0,
        ])->assertRedirect();

        $this->assertFalse(Product::where('sku', 'SRV-INSTALL')->firstOrFail()->has_serial_number);
    }

    public function test_product_supports_multiple_suppliers_and_primary_is_only_preference(): void
    {
        $admin = $this->userWithRole('admin');
        $product = $this->makeProduct();
        $primary = $this->makeSupplier('PT Distributor Utama');
        $secondary = $this->makeSupplier('PT Alternatif');

        $this->actingAs($admin)->put(route('products.update', $product), [
            'name' => $product->name,
            'sku' => $product->sku,
            'part_number' => $product->part_number,
            'category' => $product->category,
            'unit' => $product->unit,
            'default_price' => $product->default_price,
            'cost_price' => $product->cost_price,
            'supplier_ids' => [$primary->id, $secondary->id],
            'primary_supplier_id' => $primary->id,
            'is_active' => 1,
        ])->assertRedirect(route('products.index'));

        $product = $product->fresh();

        $this->assertCount(2, $product->suppliers);
        $this->assertSame($primary->id, $product->primarySupplier()->id);
        $this->assertTrue((bool) $product->suppliers->firstWhere('id', $primary->id)->pivot->is_primary);
        $this->assertFalse((bool) $product->suppliers->firstWhere('id', $secondary->id)->pivot->is_primary);
    }

    public function test_non_admin_cannot_change_product_suppliers(): void
    {
        $sales = $this->userWithRole('sales');
        $product = $this->makeProduct();
        $supplier = $this->makeSupplier();

        $this->actingAs($sales)->put(route('products.update', $product), [
            'name' => $product->name,
            'sku' => $product->sku,
            'category' => $product->category,
            'unit' => $product->unit,
            'default_price' => $product->default_price,
            'supplier_ids' => [$supplier->id],
            'primary_supplier_id' => $supplier->id,
        ])->assertForbidden();

        $this->assertCount(0, $product->fresh()->suppliers);
    }

    // ============================================================
    // 4. CUSTOMER PO ≠ SUPPLIER PO
    // ============================================================

    public function test_customer_po_and_supplier_po_are_kept_separate(): void
    {
        $admin = $this->userWithRole('admin');
        $proposal = $this->makeEligibleProposal();

        // Customer PO sudah tercatat di proposal (dokumen pelanggan).
        $this->assertSame($proposal->po_number, $proposal->fresh()->po_number);

        $this->actingAs($admin)->post(route('admin.supplier-pos.store'), $this->poPayload(
            $proposal,
            $this->makeSupplier(),
            [$this->itemRow(['proposal_item_id' => $proposal->items->first()->id])]
        ))->assertRedirect();

        $this->assertDatabaseCount('supplier_purchase_orders', 1);
        // Tidak ada penulisan ke tabel customer PO legacy.
        $this->assertDatabaseCount('purchase_orders', 0);
        // Data customer PO pada quotation tidak berubah.
        $this->assertSame('PO-CUST', substr($proposal->fresh()->po_number, 0, 7));
    }

    // ============================================================
    // 5–7. QUOTATION → SUPPLIER PO, SPLIT, MULTI-SUPPLIER
    // ============================================================

    public function test_quotation_item_can_be_used_as_supplier_po_source_with_snapshot(): void
    {
        $admin = $this->userWithRole('admin');
        $product = $this->makeProduct();
        $proposal = $this->makeEligibleProposal(null, [
            'product_id' => $product->id,
            'description' => 'Cisco Router C1111',
        ]);
        $proposalItem = $proposal->items->first();

        $this->actingAs($admin)->post(route('admin.supplier-pos.store'), $this->poPayload(
            $proposal,
            $this->makeSupplier(),
            [[
                'proposal_item_id' => $proposalItem->id,
                'product_id' => $product->id,
                'item_name' => '',
                'part_number' => '',
                'unit' => '',
                'quantity_ordered' => 2,
                'unit_price' => 11000000,
            ]]
        ))->assertRedirect();

        $order = SupplierPurchaseOrder::with('items')->firstOrFail();
        $item = $order->items->first();

        $this->assertSame($proposalItem->id, $item->proposal_item_id);
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('Cisco Router C1111', $item->item_name);
        $this->assertSame('C1111-8P', $item->part_number);
        $this->assertSame('unit', $item->unit);
        $this->assertEqualsWithDelta(2.0, $item->quantity_ordered, 0.01);
        $this->assertEqualsWithDelta(11000000.0, $item->unit_price, 0.01);
        $this->assertEqualsWithDelta(22000000.0, $item->subtotal, 0.01);
        $this->assertEqualsWithDelta(0.0, $item->quantity_received, 0.01);

        // Quotation asli tidak berubah.
        $this->assertSame(5.0, (float) $proposalItem->fresh()->quantity);
        $this->assertSame(1, $proposal->items()->count());
    }

    public function test_one_quotation_item_can_be_split_into_multiple_procurement_components(): void
    {
        $admin = $this->userWithRole('admin');
        $proposal = $this->makeEligibleProposal();
        $proposalItem = $proposal->items->first();
        $supplier = $this->makeSupplier();

        $this->actingAs($admin)->post(route('admin.supplier-pos.store'), $this->poPayload($proposal, $supplier, [
            $this->itemRow([
                'proposal_item_id' => $proposalItem->id,
                'item_name' => 'Perangkat utama',
                'quantity_ordered' => 1,
                'unit_price' => 10000000,
            ]),
            $this->itemRow([
                'proposal_item_id' => $proposalItem->id,
                'item_name' => 'Lisensi software',
                'part_number' => 'LIC-1Y',
                'quantity_ordered' => 1,
                'unit_price' => 1500000,
            ]),
            $this->itemRow([
                'proposal_item_id' => $proposalItem->id,
                'item_name' => 'Aksesori kabel',
                'part_number' => 'CAB-ACC',
                'quantity_ordered' => 1,
                'unit_price' => 500000,
            ]),
        ]))->assertRedirect();

        $order = SupplierPurchaseOrder::with('items')->firstOrFail();

        $this->assertCount(3, $order->items);
        $this->assertCount(3, $order->items->where('proposal_item_id', $proposalItem->id));
        $this->assertEqualsWithDelta(12000000.0, $order->subtotal, 0.01);

        // Pemecahan komponen tidak mengubah quotation asli.
        $this->assertSame(1, $proposal->items()->count());
        $this->assertSame(5.0, (float) $proposalItem->fresh()->quantity);
    }

    public function test_components_can_be_ordered_from_different_suppliers_via_separate_pos(): void
    {
        $admin = $this->userWithRole('admin');
        $proposal = $this->makeEligibleProposal();
        $proposalItem = $proposal->items->first();
        $supplierA = $this->makeSupplier('PT Pemasok A');
        $supplierB = $this->makeSupplier('PT Pemasok B');

        $this->actingAs($admin)->post(route('admin.supplier-pos.store'), $this->poPayload($proposal, $supplierA, [
            $this->itemRow(['proposal_item_id' => $proposalItem->id, 'item_name' => 'Perangkat utama']),
        ]))->assertRedirect();

        $this->actingAs($admin)->post(route('admin.supplier-pos.store'), $this->poPayload($proposal, $supplierB, [
            $this->itemRow(['proposal_item_id' => $proposalItem->id, 'item_name' => 'Lisensi', 'unit_price' => 1500000]),
        ]))->assertRedirect();

        $orders = SupplierPurchaseOrder::with(['supplier', 'items'])->get();

        $this->assertCount(2, $orders);
        $this->assertEqualsCanonicalizing([$supplierA->id, $supplierB->id], $orders->pluck('supplier_id')->all());
        $this->assertTrue($orders->every(fn ($order) => $order->proposal_id === $proposal->id));
        $this->assertTrue($orders->every(fn ($order) => $order->items->first()->proposal_item_id === $proposalItem->id));
    }

    // ============================================================
    // 8–9. SNAPSHOT & PERUBAHAN MASTER
    // ============================================================

    public function test_po_snapshot_does_not_change_when_product_master_changes(): void
    {
        $admin = $this->userWithRole('admin');
        $product = $this->makeProduct();
        $proposal = $this->makeEligibleProposal(null, ['product_id' => $product->id]);

        $this->actingAs($admin)->post(route('admin.supplier-pos.store'), $this->poPayload(
            $proposal,
            $this->makeSupplier(),
            [$this->itemRow([
                'proposal_item_id' => $proposal->items->first()->id,
                'product_id' => $product->id,
                'item_name' => 'Nama saat PO dibuat',
                'part_number' => 'PN-SAAT-PO',
                'quantity_ordered' => 3,
                'unit_price' => 9000000,
            ])]
        ))->assertRedirect();

        $item = SupplierPurchaseOrder::firstOrFail()->items()->firstOrFail();

        // Master berubah setelah transaksi.
        $product->update([
            'name' => 'Nama Produk Baru',
            'part_number' => 'PN-BARU',
            'cost_price' => 99000000,
            'unit' => 'set',
        ]);

        $item->refresh();

        $this->assertSame('Nama saat PO dibuat', $item->item_name);
        $this->assertSame('PN-SAAT-PO', $item->part_number);
        $this->assertSame('unit', $item->unit);
        $this->assertEqualsWithDelta(9000000.0, $item->unit_price, 0.01);
        $this->assertEqualsWithDelta(27000000.0, $item->subtotal, 0.01);
    }

    public function test_po_totals_are_computed_and_receipt_fields_ready_for_partial_receipt(): void
    {
        $admin = $this->userWithRole('admin');
        $proposal = $this->makeEligibleProposal();

        $this->actingAs($admin)->post(route('admin.supplier-pos.store'), $this->poPayload(
            $proposal,
            $this->makeSupplier(),
            [$this->itemRow(['quantity_ordered' => 2, 'unit_price' => 1000000])],
            ['discount' => 250000, 'tax' => 100000]
        ))->assertRedirect();

        $order = SupplierPurchaseOrder::firstOrFail();

        $this->assertEqualsWithDelta(2000000.0, (float) $order->subtotal, 0.01);
        $this->assertEqualsWithDelta(250000.0, (float) $order->discount, 0.01);
        $this->assertEqualsWithDelta(100000.0, (float) $order->tax, 0.01);
        $this->assertEqualsWithDelta(1850000.0, (float) $order->grand_total, 0.01);
        $this->assertSame('draft', $order->status);

        $summary = $order->quantitySummary();
        $this->assertEqualsWithDelta(2.0, $summary['ordered'], 0.01);
        $this->assertEqualsWithDelta(0.0, $summary['received'], 0.01);
        $this->assertEqualsWithDelta(2.0, $order->items->first()->quantityRemaining(), 0.01);
    }

    // ============================================================
    // 10. IDEMPOTENSI
    // ============================================================

    public function test_repeated_submission_does_not_create_duplicate_po(): void
    {
        $admin = $this->userWithRole('admin');
        $proposal = $this->makeEligibleProposal();
        $payload = $this->poPayload(
            $proposal,
            $this->makeSupplier(),
            [$this->itemRow(['proposal_item_id' => $proposal->items->first()->id])]
        );

        $this->actingAs($admin)->post(route('admin.supplier-pos.store'), $payload)->assertRedirect();
        $first = SupplierPurchaseOrder::firstOrFail();

        // Request ulang (double click / retry) dengan kunci yang sama.
        $this->actingAs($admin)->post(route('admin.supplier-pos.store'), $payload)
            ->assertRedirect(route('admin.supplier-pos.show', $first));

        $this->assertDatabaseCount('supplier_purchase_orders', 1);
        $this->assertSame(1, $first->items()->count());
    }

    // ============================================================
    // VALIDASI & OTORISASI
    // ============================================================

    public function test_supplier_po_requires_approved_quotation_with_customer_po(): void
    {
        $admin = $this->userWithRole('admin');
        $supplier = $this->makeSupplier();

        $draft = $this->makeEligibleProposal();
        $draft->update(['status' => 'draft']);

        $this->actingAs($admin)->post(route('admin.supplier-pos.store'), $this->poPayload($draft, $supplier, [$this->itemRow()]))
            ->assertStatus(422);

        $withoutPo = $this->makeEligibleProposal();
        $withoutPo->update(['po_document_path' => null]);

        $this->actingAs($admin)->post(route('admin.supplier-pos.store'), $this->poPayload($withoutPo, $supplier, [$this->itemRow()]))
            ->assertStatus(422);

        $this->assertDatabaseCount('supplier_purchase_orders', 0);
    }

    public function test_quotation_item_from_another_quotation_is_rejected(): void
    {
        $admin = $this->userWithRole('admin');
        $proposal = $this->makeEligibleProposal();
        $other = $this->makeEligibleProposal();

        $this->actingAs($admin)->post(route('admin.supplier-pos.store'), $this->poPayload(
            $proposal,
            $this->makeSupplier(),
            [$this->itemRow(['proposal_item_id' => $other->items->first()->id])]
        ))->assertStatus(422);

        $this->assertDatabaseCount('supplier_purchase_orders', 0);
    }

    public function test_supplier_po_writes_are_restricted_to_admin_purchasing(): void
    {
        $sales = $this->userWithRole('sales');
        $management = $this->userWithRole('management');
        $proposal = $this->makeEligibleProposal();
        $supplier = $this->makeSupplier();

        // Sales tidak memiliki permission Supplier PO.
        $this->actingAs($sales)->get(route('admin.supplier-pos.index'))->assertForbidden();
        $this->actingAs($sales)->post(route('admin.supplier-pos.store'), $this->poPayload(
            $proposal,
            $supplier,
            [$this->itemRow()]
        ))->assertForbidden();

        // Management read-only boleh melihat, tapi tidak boleh menulis.
        $this->actingAs($management)->get(route('admin.supplier-pos.index'))->assertOk();
        $this->actingAs($management)->post(route('admin.suppliers.store'), ['name' => 'PT Baru'])->assertForbidden();

        $this->assertDatabaseCount('supplier_purchase_orders', 0);
        $this->assertDatabaseCount('suppliers', 1);
    }

    // ============================================================
    // 11–12. INTEGRASI HANDOFF & KELENGKAPAN ALUR
    // ============================================================

    public function test_handoff_queue_links_to_supplier_po_without_creating_one(): void
    {
        $admin = $this->userWithRole('admin');
        $proposal = $this->makeEligibleProposal();

        // Membuka antrean tidak membuat transaksi pembelian.
        $this->actingAs($admin)->get(route('admin.purchasing.show', $proposal))
            ->assertOk()
            ->assertSee(route('admin.supplier-pos.create', ['proposal_id' => $proposal->id]), false);

        $this->assertDatabaseCount('supplier_purchase_orders', 0);

        // Setelah PO dibuat, antrean menautkannya kembali ke quotation.
        $this->actingAs($admin)->post(route('admin.supplier-pos.store'), $this->poPayload(
            $proposal,
            $this->makeSupplier(),
            [$this->itemRow(['proposal_item_id' => $proposal->items->first()->id])]
        ))->assertRedirect();

        $order = SupplierPurchaseOrder::firstOrFail();

        $this->actingAs($admin)->get(route('admin.purchasing.show', $proposal))
            ->assertOk()
            ->assertSee($order->po_number);
    }

    public function test_supplier_po_can_be_issued_and_cancelled_without_deleting_history(): void
    {
        $admin = $this->userWithRole('admin');
        $proposal = $this->makeEligibleProposal();

        $this->actingAs($admin)->post(route('admin.supplier-pos.store'), $this->poPayload(
            $proposal,
            $this->makeSupplier(),
            [$this->itemRow()]
        ))->assertRedirect();

        $order = SupplierPurchaseOrder::firstOrFail();

        $this->actingAs($admin)->post(route('admin.supplier-pos.issue', $order))->assertRedirect();
        $this->assertSame('ordered', $order->fresh()->status);

        $this->actingAs($admin)->post(route('admin.supplier-pos.cancel', $order))->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status);

        // Riwayat tidak dihapus.
        $this->assertDatabaseHas('supplier_purchase_orders', ['id' => $order->id]);
        $this->assertDatabaseCount('supplier_purchase_order_items', 1);
    }

    public function test_supplier_purchasing_pages_render_for_admin(): void
    {
        $admin = $this->userWithRole('admin');
        $product = $this->makeProduct();
        $supplier = $this->makeSupplier();
        $proposal = $this->makeEligibleProposal(null, ['product_id' => $product->id]);

        $this->actingAs($admin)->get(route('products.create'))->assertOk();
        $this->actingAs($admin)->get(route('products.edit', $product))->assertOk();

        $this->actingAs($admin)->get(route('admin.suppliers.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.suppliers.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.suppliers.edit', $supplier))->assertOk();

        $this->actingAs($admin)->get(route('admin.supplier-pos.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.supplier-pos.create', ['proposal_id' => $proposal->id]))
            ->assertOk()
            ->assertSee($proposal->proposal_number);

        $this->actingAs($admin)->post(route('admin.supplier-pos.store'), $this->poPayload(
            $proposal,
            $supplier,
            [$this->itemRow(['proposal_item_id' => $proposal->items->first()->id])]
        ))->assertRedirect();

        $order = SupplierPurchaseOrder::firstOrFail();

        $this->actingAs($admin)->get(route('admin.supplier-pos.show', $order))
            ->assertOk()
            ->assertSee($order->po_number)
            ->assertSee($supplier->name);

        $this->actingAs($admin)->get(route('admin.purchasing.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.purchasing.show', $proposal))->assertOk();
    }

    public function test_supplier_master_crud_keeps_history_by_deactivating(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post(route('admin.suppliers.store'), [
            'name' => 'PT Pemasok Baru',
            'code' => 'SUP-NEW',
            'contact_person' => 'Sari',
            'phone' => '021999',
            'email' => 'sari@supplier.test',
            'is_active' => 1,
        ])->assertRedirect(route('admin.suppliers.index'));

        $supplier = Supplier::where('code', 'SUP-NEW')->firstOrFail();
        $this->assertTrue($supplier->is_active);

        $this->actingAs($admin)->put(route('admin.suppliers.update', $supplier), [
            'name' => 'PT Pemasok Baru (Revisi)',
            'code' => 'SUP-NEW',
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame('PT Pemasok Baru (Revisi)', $supplier->fresh()->name);

        $this->actingAs($admin)->delete(route('admin.suppliers.destroy', $supplier))->assertRedirect();

        $this->assertFalse($supplier->fresh()->is_active);
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }
}
