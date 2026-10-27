<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Paket 2 — Tahap 1: perhitungan modal/laba/margin quotation + perlindungan
 * informasi finansial internal.
 */
class ProposalCostMarginTest extends TestCase
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

    private function makeLead(?int $assignedTo = null): Lead
    {
        $customer = Customer::create(['name' => 'PT Cost '.uniqid()]);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'hot',
        ]);
        if ($assignedTo) {
            $lead->update(['assigned_to' => $assignedTo]);
        }

        return $lead;
    }

    /** Buat proposal + item langsung (model) untuk menguji rumus. */
    private function makeProposalWithItems(array $attrs, array $items): Proposal
    {
        $proposal = Proposal::create(array_merge([
            'lead_id' => $this->makeLead()->id,
            'proposal_number' => 'Q/COST/'.uniqid(),
            'proposal_date' => now()->toDateString(),
            'customer' => 'PT Cost',
            'status' => 'draft',
        ], $attrs));

        foreach ($items as $item) {
            ProposalItem::create(array_merge(['proposal_id' => $proposal->id], $item));
        }
        $proposal->recalculate();

        return $proposal->fresh();
    }

    // ============================================================
    // PERHITUNGAN (rumus yang disepakati)
    // ============================================================

    public function test_total_cost_sums_all_items_and_margin_uses_net_sales(): void
    {
        // item1: 2 × 1000 − 0 = 2000; item2: 1 × 500 − 100 = 400 → subtotal 2400
        // diskon proposal 200, pajak 100 → penjualan bersih 2200 (PAJAK TIDAK ikut)
        // modal: 2 × 100 + 1 × 300 = 500 → laba 1700 → margin 77,27%
        $p = $this->makeProposalWithItems(
            ['discount' => 200, 'tax' => 100],
            [
                ['description' => 'A', 'quantity' => 2, 'unit' => 'pcs', 'unit_price' => 1000, 'unit_cost' => 100, 'discount' => 0, 'subtotal' => 2000],
                ['description' => 'B', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => 500, 'unit_cost' => 300, 'discount' => 100, 'subtotal' => 400],
            ]
        );

        $this->assertEqualsWithDelta(2200.0, $p->netSales(), 0.01);
        $this->assertEqualsWithDelta(500.0, (float) $p->total_cost, 0.01);
        $this->assertEqualsWithDelta(1700.0, (float) $p->gross_profit, 0.01);
        $this->assertEqualsWithDelta(77.27, (float) $p->gross_margin, 0.01);
        // Pajak tetap masuk grand_total, tapi bukan ke margin.
        $this->assertEqualsWithDelta(2300.0, (float) $p->grand_total, 0.01);
    }

    public function test_tax_does_not_change_margin_percentage(): void
    {
        $items = [
            ['description' => 'A', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => 1000, 'unit_cost' => 600, 'discount' => 0, 'subtotal' => 1000],
        ];
        $tanpaPajak = $this->makeProposalWithItems(['tax' => 0], $items);
        $denganPajak = $this->makeProposalWithItems(['tax' => 500], $items);

        $this->assertSame(
            (float) $tanpaPajak->gross_margin,
            (float) $denganPajak->gross_margin
        );
        $this->assertEqualsWithDelta(40.0, (float) $tanpaPajak->gross_margin, 0.01);
    }

    public function test_negative_margin_is_not_clamped_and_zero_net_sales_gives_null_margin(): void
    {
        // Modal > penjualan → margin negatif, tidak dipaksa 0.
        $rugi = $this->makeProposalWithItems(
            ['discount' => 0, 'tax' => 0],
            [['description' => 'A', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => 100, 'unit_cost' => 200, 'discount' => 0, 'subtotal' => 100]]
        );
        $this->assertEqualsWithDelta(-100.0, (float) $rugi->gross_profit, 0.01);
        $this->assertEqualsWithDelta(-100.0, (float) $rugi->gross_margin, 0.01);

        // Penjualan bersih 0 → margin NULL (tanpa pembagian nol).
        $nol = $this->makeProposalWithItems(
            ['discount' => 0, 'tax' => 0],
            [['description' => 'A', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => 0, 'unit_cost' => 100, 'discount' => 0, 'subtotal' => 0]]
        );
        $this->assertEqualsWithDelta(-100.0, (float) $nol->gross_profit, 0.01);
        $this->assertNull($nol->gross_margin);
    }

    public function test_incomplete_cost_data_yields_no_fake_margin(): void
    {
        // Satu item tanpa harga modal = perhitungan TIDAK dianggap 0.
        $p = $this->makeProposalWithItems(
            ['discount' => 0, 'tax' => 0],
            [
                ['description' => 'Punya modal', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => 1000, 'unit_cost' => 400, 'discount' => 0, 'subtotal' => 1000],
                ['description' => 'Tanpa modal', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => 500, 'unit_cost' => null, 'discount' => 0, 'subtotal' => 500],
            ]
        );

        $this->assertFalse($p->hasCompleteCostData());
        $this->assertNull($p->total_cost);
        $this->assertNull($p->gross_profit);
        $this->assertNull($p->gross_margin);
        $this->assertSame(1, $p->missingCostItemsCount());
    }

    public function test_product_cost_change_does_not_rewrite_history_snapshot(): void
    {
        $product = Product::create(['name' => 'Kamera', 'cost_price' => 500, 'default_price' => 1000, 'status' => Product::STATUS_APPROVED]);
        $p = $this->makeProposalWithItems(
            ['discount' => 0, 'tax' => 0],
            [['product_id' => $product->id, 'description' => 'Kamera', 'quantity' => 2, 'unit' => 'pcs', 'unit_price' => 1000, 'unit_cost' => 500, 'discount' => 0, 'subtotal' => 2000]]
        );
        $this->assertEqualsWithDelta(1000.0, (float) $p->total_cost, 0.01);

        // Harga modal produk berubah → snapshot quotation TIDAK berubah.
        $product->update(['cost_price' => 999]);
        $p->recalculate();

        $this->assertEqualsWithDelta(500.0, (float) $p->items()->first()->unit_cost, 0.01);
        $this->assertEqualsWithDelta(1000.0, (float) $p->fresh()->total_cost, 0.01);
    }

    // ============================================================
    // HAK AKSES (backend, bukan sekadar UI)
    // ============================================================

    public function test_role_matrix_for_cost_access(): void
    {
        $p = $this->makeProposalWithItems([], [['description' => 'A', 'quantity' => 1, 'unit_price' => 100, 'unit_cost' => 50, 'discount' => 0, 'subtotal' => 100]]);

        // Paket 3: CEO mendapat akses BACA sesuai matriks (semua view-*, read-only).
        foreach (['super-admin', 'admin', 'purchasing', 'sales', 'inside-sales', 'management', 'ceo'] as $role) {
            $this->assertTrue($this->userWithRole($role)->can('viewCost', Proposal::class), "viewCost harusnya TRUE utk {$role}");
        }
        foreach (['marketing', 'technician', 'prakerin-technician', 'prakerin-admin'] as $role) {
            $this->assertFalse($this->userWithRole($role)->can('viewCost', Proposal::class), "viewCost harusnya FALSE utk {$role}");
        }

        $this->assertTrue($this->userWithRole('admin')->can('updateCost', Proposal::class));
        $this->assertTrue($this->userWithRole('super-admin')->can('updateCost', Proposal::class));
        $this->assertFalse($this->userWithRole('sales')->can('updateCost', Proposal::class));
        $this->assertFalse($this->userWithRole('management')->can('updateCost', Proposal::class));
        $this->assertTrue($this->userWithRole('purchasing')->can('updateCost', Proposal::class));
        // Read-only: CEO boleh baca, TIDAK boleh ubah.
        $this->assertFalse($this->userWithRole('ceo')->can('updateCost', Proposal::class));
    }

    public function test_sales_cannot_submit_unit_cost_and_snapshot_is_preserved(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);
        $product = Product::create(['name' => 'Switch', 'cost_price' => 700, 'default_price' => 1200, 'status' => Product::STATUS_APPROVED]);

        // Sales membuat quotation: harga modal = snapshot produk.
        $this->actingAs($sales)->post(route('sales.proposals.store'), [
            'lead_id' => $lead->id,
            'proposal_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'description' => 'Switch', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => 1200, 'discount' => 0]],
        ])->assertRedirect();
        $proposal = Proposal::where('lead_id', $lead->id)->firstOrFail();
        $this->assertEqualsWithDelta(700.0, (float) $proposal->items()->first()->unit_cost, 0.01);

        // Sales mencoba mengirim unit_cost = anti-tamper backend menolak (403).
        $this->actingAs($sales)->put(route('sales.proposals.update', $proposal), [
            'proposal_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'description' => 'Switch', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => 1200, 'unit_cost' => 1, 'discount' => 0]],
        ])->assertForbidden();

        // Edit sah oleh sales: snapshot harga modal TETAP 700 walau harga modal
        // produk sudah berubah (historis tidak diganti angka baru).
        $product->update(['cost_price' => 999]);
        $this->actingAs($sales)->put(route('sales.proposals.update', $proposal), [
            'proposal_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'description' => 'Switch (revisi)', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => 1200, 'discount' => 0]],
        ])->assertRedirect();

        $item = $proposal->fresh()->items()->first();
        $this->assertEqualsWithDelta(700.0, (float) $item->unit_cost, 0.01);
        $this->assertSame('Switch (revisi)', $item->description);
    }

    public function test_product_cost_price_restricted_to_authorized(): void
    {
        $sales = $this->userWithRole('sales');
        $admin = $this->userWithRole('admin');

        // Admin (manage-admin) boleh mengubah harga modal produk.
        $this->actingAs($admin)->post(route('products.store'), [
            'name' => 'Router', 'category' => 'barang', 'default_price' => 2000, 'cost_price' => 1500,
        ])->assertRedirect();
        $product = Product::where('name', 'Router')->firstOrFail();
        $this->assertEqualsWithDelta(1500.0, (float) $product->cost_price, 0.01);

        // Sales mengirim cost_price = backend menolak (403), bukan disembunyikan.
        $this->actingAs($sales)->put(route('products.update', $product), [
            'name' => 'Router', 'category' => 'barang', 'default_price' => 2000, 'cost_price' => 1,
        ])->assertForbidden();

        // Sales tetap boleh mengubah data non-harga.
        $this->actingAs($sales)->put(route('products.update', $product), [
            'name' => 'Router v2', 'category' => 'barang', 'default_price' => 2100,
        ])->assertRedirect();
        $this->assertSame('Router v2', $product->fresh()->name);
        $this->assertEqualsWithDelta(1500.0, (float) $product->fresh()->cost_price, 0.01);
    }

    // ============================================================
    // KERAHASIAAN: preview pelanggan & pemisahan notes
    // ============================================================

    public function test_customer_preview_has_no_internal_financials(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);
        $p = $this->makeProposalWithItems(
            ['lead_id' => $lead->id, 'created_by' => $sales->id, 'notes' => 'Harga sudah termasuk pemasangan.', 'internal_notes' => 'Diskon khusus, jangan dibocorkan.', 'discount' => 100, 'tax' => 50],
            [['description' => 'A', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => 1000, 'unit_cost' => 400, 'discount' => 0, 'subtotal' => 1000]]
        );

        $html = $this->actingAs($sales)->get(route('sales.proposals.preview', $p))->assertOk()->getContent();

        // Informasi finansial internal tidak boleh tercetak.
        $this->assertStringNotContainsString('Total Cost', $html);
        $this->assertStringNotContainsString('Gross Margin', $html);
        $this->assertStringNotContainsString('400', $html);
        // Catatan internal tidak bocor; catatan pelanggan tetap ada.
        $this->assertStringNotContainsString('jangan dibocorkan', $html);
        $this->assertStringContainsString('Harga sudah termasuk pemasangan.', $html);
    }

    public function test_internal_notes_only_visible_in_show_not_preview(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);
        $p = $this->makeProposalWithItems(
            ['lead_id' => $lead->id, 'created_by' => $sales->id, 'internal_notes' => 'Catatan rahasia internal.'],
            [['description' => 'A', 'quantity' => 1, 'unit_price' => 100, 'unit_cost' => null, 'discount' => 0, 'subtotal' => 100]]
        );

        $show = $this->actingAs($sales)->get(route('sales.proposals.show', $p))->assertOk()->getContent();
        $preview = $this->actingAs($sales)->get(route('sales.proposals.preview', $p))->assertOk()->getContent();

        $this->assertStringContainsString('Catatan rahasia internal.', $show);
        $this->assertStringNotContainsString('Catatan rahasia internal.', $preview);
    }

    public function test_cost_section_renders_for_authorized_with_incomplete_flag(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);
        $p = $this->makeProposalWithItems(
            ['lead_id' => $lead->id, 'created_by' => $sales->id],
            [['description' => 'A', 'quantity' => 1, 'unit_price' => 100, 'unit_cost' => null, 'discount' => 0, 'subtotal' => 100]]
        );

        $html = $this->actingAs($sales)->get(route('sales.proposals.show', $p))->assertOk()->getContent();
        $this->assertStringContainsString(__('Profit calculation incomplete'), $html);
    }

    // ============================================================
    // KOMPATIBILITAS: quotation lama & alur status
    // ============================================================

    public function test_old_quotation_without_cost_keeps_status_flow_working(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);
        // Quotation "lama": item tanpa harga modal.
        $p = $this->makeProposalWithItems(
            ['lead_id' => $lead->id, 'created_by' => $sales->id, 'status' => 'draft'],
            [['description' => 'Lama', 'quantity' => 1, 'unit_price' => 100, 'unit_cost' => null, 'discount' => 0, 'subtotal' => 100]]
        );

        $this->assertFalse($p->hasCompleteCostData());

        // Alur status lama tetap jalan.
        $this->actingAs($sales)->post(route('sales.proposals.ready', $p))->assertRedirect();
        $this->assertSame('ready', $p->fresh()->status);
        $this->actingAs($sales)->post(route('sales.proposals.send', $p))->assertRedirect();
        $this->assertSame('sent', $p->fresh()->status);
    }
}
