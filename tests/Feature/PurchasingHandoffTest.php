<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Paket 3 — Tahap 1: handoff Sales → Admin/Purchasing.
 *
 * Customer PO (pesanan pelanggan ke 3DY) dibedakan dari Supplier Purchase
 * Order (purchase_orders). Antrean handoff read-only dan diturunkan dari
 * data yang sudah ada → tidak pernah menggandakan proyek/handoff.
 */
class PurchasingHandoffTest extends TestCase
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

    private function makeLead(?int $assignedTo = null, string $customerName = 'PT Handoff'): Lead
    {
        $customer = Customer::create(['name' => $customerName]);
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

    private function makeProposal(Lead $lead, string $status, ?int $createdBy = null): Proposal
    {
        $proposal = Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => 'Q/HO/'.uniqid(),
            'created_by' => $createdBy,
            'proposal_date' => now()->toDateString(),
            'customer' => $lead->customer?->name ?? 'PT Handoff',
            'subtotal' => 0,
            'discount' => 0,
            'tax' => 0,
            'grand_total' => 0,
            'status' => $status,
        ]);

        ProposalItem::create([
            'proposal_id' => $proposal->id,
            'description' => 'Kamera CCTV 4MP',
            'quantity' => 3,
            'unit' => 'pcs',
            'unit_price' => 1500000,
            'unit_cost' => 1000000,
            'discount' => 0,
            'subtotal' => 4500000,
        ]);
        $proposal->recalculate();

        return $proposal->fresh();
    }

    private function uploadPo(Proposal $proposal, User $sales, array $extra = []): void
    {
        $this->actingAs($sales)->post(route('sales.proposals.upload-po', $proposal), array_merge([
            'po_file' => UploadedFile::fake()->create('customer-po.pdf', 100, 'application/pdf'),
        ], $extra))->assertRedirect();
    }

    // ============================================================
    // SYARAT ANTREAN
    // ============================================================

    public function test_accepted_without_customer_po_is_not_in_ready_queue(): void
    {
        $sales = $this->userWithRole('sales');
        $admin = $this->userWithRole('admin');
        $withoutPo = $this->makeProposal($this->makeLead($sales->id), 'accepted', $sales->id);

        $this->actingAs($admin)->get(route('admin.purchasing.index'))
            ->assertOk()
            ->assertDontSee($withoutPo->proposal_number)
            ->assertSee(__('No quotations are ready for Purchasing yet.'));

        // Detail handoff juga tidak boleh dibuka untuk quotation tanpa PO.
        $this->actingAs($admin)->get(route('admin.purchasing.show', $withoutPo))->assertNotFound();
    }

    public function test_accepted_with_customer_po_appears_in_ready_queue(): void
    {
        Storage::fake('private');
        $sales = $this->userWithRole('sales');
        $admin = $this->userWithRole('admin');
        $proposal = $this->makeProposal($this->makeLead($sales->id), 'accepted', $sales->id);

        $this->uploadPo($proposal, $sales, [
            'po_number' => 'PO-CUST-001',
            'po_date' => now()->toDateString(),
            'delivery_deadline' => now()->addDays(14)->toDateString(),
            'delivery_address' => 'Jl. Gatot Subroto 10, Jakarta',
        ]);

        $proposal->refresh();
        $this->assertSame('PO-CUST-001', $proposal->po_number);
        $this->assertNotNull($proposal->po_document_path);
        $this->assertTrue($proposal->isReadyForPurchasing());

        $this->actingAs($admin)->get(route('admin.purchasing.index'))
            ->assertOk()
            ->assertSee($proposal->proposal_number)
            ->assertSee('PO-CUST-001')
            ->assertSee($proposal->lead->assignee->name);
    }

    public function test_po_info_can_be_updated_without_reuploading_file(): void
    {
        Storage::fake('private');
        $sales = $this->userWithRole('sales');
        $proposal = $this->makeProposal($this->makeLead($sales->id), 'accepted', $sales->id);

        $this->uploadPo($proposal, $sales, ['po_number' => 'PO-A']);
        $firstPath = $proposal->fresh()->po_document_path;

        $this->actingAs($sales)->post(route('sales.proposals.upload-po', $proposal), [
            'po_number' => 'PO-B',
            'delivery_address' => 'Gudang Bandung',
        ])->assertRedirect();

        $proposal->refresh();
        $this->assertSame('PO-B', $proposal->po_number);
        $this->assertSame('Gudang Bandung', $proposal->delivery_address);
        $this->assertSame($firstPath, $proposal->po_document_path);
    }

    // ============================================================
    // DATA DITERUSKAN TANPA INPUT ULANG
    // ============================================================

    public function test_admin_sees_quotation_items_and_owners_without_retyping(): void
    {
        Storage::fake('private');
        $sales = $this->userWithRole('sales');
        $admin = $this->userWithRole('admin');
        $lead = $this->makeLead($sales->id);
        $proposal = $this->makeProposal($lead, 'accepted', $sales->id);
        $this->uploadPo($proposal, $sales, ['po_number' => 'PO-777']);

        $this->actingAs($admin)->get(route('admin.purchasing.show', $proposal))
            ->assertOk()
            ->assertSee('Kamera CCTV 4MP')
            ->assertSee('3')
            ->assertSee('1.500.000')
            ->assertSee('PO-777')
            ->assertSee($sales->name)                 // Sales Owner
            ->assertSee($proposal->creator->name);    // Prepared By (Quotation Maker)
    }

    public function test_handoff_is_idempotent_and_never_creates_duplicates(): void
    {
        Storage::fake('private');
        $sales = $this->userWithRole('sales');
        $admin = $this->userWithRole('admin');
        $proposal = $this->makeProposal($this->makeLead($sales->id), 'accepted', $sales->id);
        $this->uploadPo($proposal, $sales);

        $proposalCount = Proposal::count();
        $projectCount = Project::count();
        $poCount = PurchaseOrder::count();

        // Buka antrean & detail berulang kali.
        foreach (range(1, 3) as $i) {
            $this->actingAs($admin)->get(route('admin.purchasing.index'))->assertOk();
            $this->actingAs($admin)->get(route('admin.purchasing.show', $proposal))->assertOk();
        }

        $this->assertSame($proposalCount, Proposal::count());
        $this->assertSame($projectCount, Project::count());
        $this->assertSame($poCount, PurchaseOrder::count());
        $this->assertFalse($proposal->fresh()->project()->exists());
    }

    // ============================================================
    // HAK AKSES
    // ============================================================

    public function test_unauthorized_roles_cannot_access_handoff_queue(): void
    {
        Storage::fake('private');
        $sales = $this->userWithRole('sales');
        $proposal = $this->makeProposal($this->makeLead($sales->id), 'accepted', $sales->id);
        $this->uploadPo($proposal, $sales);

        foreach (['marketing', 'technician', 'inside-sales', 'prakerin-technician'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('admin.purchasing.index'))
                ->assertForbidden();
        }
    }

    public function test_other_sales_cannot_open_foreign_handoff_detail(): void
    {
        Storage::fake('private');
        $owner = $this->userWithRole('sales');
        $other = $this->userWithRole('sales');
        $proposal = $this->makeProposal($this->makeLead($owner->id), 'accepted', $owner->id);
        $this->uploadPo($proposal, $owner);

        // Antrean boleh dibuka (view-admin read-only) tapi hanya quotation sendiri.
        $this->actingAs($other)->get(route('admin.purchasing.index'))
            ->assertOk()
            ->assertDontSee($proposal->proposal_number);

        $this->actingAs($other)->get(route('admin.purchasing.show', $proposal))->assertForbidden();
    }

    public function test_management_and_ceo_are_read_only(): void
    {
        Storage::fake('private');
        $sales = $this->userWithRole('sales');
        $proposal = $this->makeProposal($this->makeLead($sales->id), 'accepted', $sales->id);
        $this->uploadPo($proposal, $sales);

        foreach (['management', 'ceo', 'purchasing', 'super-admin'] as $role) {
            $user = $this->userWithRole($role);

            // Baca boleh.
            $this->actingAs($user)->get(route('admin.purchasing.index'))->assertOk();
            $this->actingAs($user)->get(route('admin.purchasing.show', $proposal))->assertOk();

            // Tidak ada hak ubah: aksi tulis quotation tetap 403.
            $this->actingAs($user)->post(route('sales.proposals.respond', $proposal), [
                'decision' => 'rejected',
            ])->assertForbidden();
        }

        // Harga modal tidak berubah karena role baca.
        $this->assertFalse($this->userWithRole('management')->can('updateCost', Proposal::class));
        $this->assertFalse($this->userWithRole('ceo')->can('updateCost', Proposal::class));
    }

    public function test_ceo_can_read_proposal_but_not_edit_or_change_cost(): void
    {
        $sales = $this->userWithRole('sales');
        $ceo = $this->userWithRole('ceo');
        $proposal = $this->makeProposal($this->makeLead($sales->id), 'draft', $sales->id);

        $this->actingAs($ceo)->get(route('sales.proposals.show', $proposal))->assertOk();
        $this->actingAs($ceo)->get(route('sales.proposals.preview', $proposal))->assertOk();

        // Read-only: form edit, update, dan convert tertutup.
        $this->actingAs($ceo)->get(route('sales.proposals.edit', $proposal))->assertForbidden();
        $this->actingAs($ceo)->put(route('sales.proposals.update', $proposal), [
            'proposal_date' => now()->toDateString(),
            'items' => [['description' => 'Ubah', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => 1, 'discount' => 0]],
        ])->assertForbidden();

        $this->assertFalse($ceo->can('updateCost', Proposal::class));
        $this->assertTrue($ceo->can('viewCost', Proposal::class));
    }

    public function test_management_cannot_edit_sales_draft_proposal(): void
    {
        $sales = $this->userWithRole('sales');
        $management = $this->userWithRole('management');
        $proposal = $this->makeProposal($this->makeLead($sales->id), 'draft', $sales->id);

        $this->actingAs($management)->get(route('sales.proposals.show', $proposal))->assertOk();

        $this->actingAs($management)->put(route('sales.proposals.update', $proposal), [
            'proposal_date' => now()->toDateString(),
            'items' => [['description' => 'Edit management', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => 1, 'discount' => 0]],
        ])->assertForbidden();

        $this->assertSame('Kamera CCTV 4MP', $proposal->fresh()->items()->first()->description);
    }

    // ============================================================
    // CUSTOMER PO ≠ SUPPLIER PO
    // ============================================================

    public function test_customer_po_does_not_create_supplier_purchase_order(): void
    {
        Storage::fake('private');
        $sales = $this->userWithRole('sales');
        $admin = $this->userWithRole('admin');
        $proposal = $this->makeProposal($this->makeLead($sales->id), 'accepted', $sales->id);

        $this->uploadPo($proposal, $sales, ['po_number' => 'PO-CUST-9']);

        $this->assertSame(0, PurchaseOrder::count());
        $this->actingAs($admin)->get(route('admin.purchasing.show', $proposal))
            ->assertOk()
            ->assertSee('PO-CUST-9')
            ->assertSee(__('Customer PO'));
    }

    public function test_project_conversion_keeps_customer_note_and_excludes_internal_notes(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);
        $proposal = $this->makeProposal($lead, 'accepted', $sales->id);
        $proposal->update([
            'po_document_path' => 'proposals/test/po/test.pdf',
            'po_number' => 'PO-TEST-1',
            'payment_scheme' => 'full',
            'notes' => 'Harga termasuk instalasi.',
            'internal_notes' => 'Jangan disebut ke pelanggan: margin tipis.',
        ]);

        $workType = WorkType::firstOrCreate(['name' => 'Instalasi CCTV']);

        $this->actingAs($sales)->post(route('sales.proposals.convert', $proposal), [
            'work_type_id' => $workType->id,
        ])->assertRedirect();

        $project = $proposal->fresh()->project;
        $this->assertNotNull($project);
        $this->assertStringContainsString('Harga termasuk instalasi.', (string) $project->description);
        $this->assertStringNotContainsString('margin tipis', (string) $project->description);

        // Proyek tetap satu walau konversi diulang.
        $this->actingAs($sales)->post(route('sales.proposals.convert', $proposal), [
            'work_type_id' => $workType->id,
        ])->assertStatus(422);

        $this->assertSame(1, Project::where('proposal_id', $proposal->id)->count());
    }

    public function test_customer_preview_still_hides_internal_financials(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);
        $proposal = $this->makeProposal($lead, 'accepted', $sales->id);
        $proposal->update(['internal_notes' => 'Catatan internal handoff.']);

        $html = $this->actingAs($sales)
            ->get(route('sales.proposals.preview', $proposal))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Total Cost', $html);
        $this->assertStringNotContainsString('Gross Margin', $html);
        $this->assertStringNotContainsString('1.000.000', $html); // harga modal item
        $this->assertStringNotContainsString('Catatan internal handoff.', $html);
    }
}
