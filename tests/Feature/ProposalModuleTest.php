<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Product;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\User;
use App\Models\WorkType;
use App\Notifications\ProposalStatusNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProposalModuleTest extends TestCase
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
        $customer = Customer::create(['name' => 'PT Proposal ' . uniqid()]);
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

    private function makeProposal(Lead $lead, string $status = 'draft', ?int $createdBy = null): Proposal
    {
        $proposal = Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => 'Q/2026/10/' . uniqid(),
            'created_by' => $createdBy ?? $lead->assigned_to,
            'proposal_date' => now()->toDateString(),
            'customer' => $lead->customer->name,
            'status' => $status,
        ]);
        ProposalItem::create([
            'proposal_id' => $proposal->id,
            'description' => 'IP Camera',
            'quantity' => 2,
            'unit' => 'unit',
            'unit_price' => 1000000,
            'discount' => 0,
            'subtotal' => 2000000,
        ]);
        $proposal->recalculate();

        return $proposal;
    }

    public function test_products_crud_and_picker(): void
    {
        $sales = $this->userWithRole('sales');

        // Tulis butuh manage-sales: teknisi ditolak.
        $tech = $this->userWithRole('technician');
        $this->actingAs($tech)->post(route('products.store'), [
            'name' => 'Kamera', 'category' => 'barang',
        ])->assertForbidden();

        $this->actingAs($sales)->post(route('products.store'), [
            'sku' => 'CAM-T1',
            'name' => 'Kamera Test',
            'category' => 'barang',
            'unit' => 'unit',
            'default_price' => 500000,
        ])->assertRedirect(route('products.index'));

        $product = Product::where('sku', 'CAM-T1')->firstOrFail();
        $this->assertSame('approved', $product->status); // tidak masuk antrean review KB
        $this->assertTrue($product->is_active);

        // Picker hanya produk aktif.
        $this->actingAs($sales)->getJson(route('products.picker', ['q' => 'Kamera Test']))
            ->assertOk()
            ->assertJsonFragment(['sku' => 'CAM-T1']);

        // Destroy = nonaktifkan, bukan hapus (riwayat BOQ aman).
        $this->actingAs($sales)->delete(route('products.destroy', $product))->assertRedirect();
        $this->assertFalse($product->fresh()->is_active);
        $this->actingAs($sales)->getJson(route('products.picker', ['q' => 'Kamera Test']))
            ->assertOk()
            ->assertJsonMissing(['sku' => 'CAM-T1']);
    }

    public function test_pending_technical_flow_and_edit_lock(): void
    {
        Notification::fake();
        $sales = $this->userWithRole('sales');
        $admin = $this->userWithRole('admin');
        $lead = $this->makeLead($sales->id);
        $proposal = $this->makeProposal($lead, 'draft', $sales->id);

        // Sales serahkan ke teknis.
        $this->actingAs($sales)->post(route('sales.proposals.submit-technical', $proposal))->assertRedirect();
        $this->assertSame('pending_technical', $proposal->fresh()->status);
        $this->assertTrue(LeadActivity::where('lead_id', $lead->id)->where('action', 'proposal_pending_technical')->exists());
        Notification::assertSentTo($admin, ProposalStatusNotification::class);

        // Sales terkunci dari edit selama pending_technical.
        $this->actingAs($sales)->get(route('sales.proposals.edit', $proposal))->assertStatus(422);

        // Admin/teknisi boleh selesaikan → ready.
        $this->actingAs($admin)->post(route('sales.proposals.complete-technical', $proposal))->assertRedirect();
        $this->assertSame('ready', $proposal->fresh()->status);
    }

    public function test_return_technical_back_to_draft(): void
    {
        $sales = $this->userWithRole('sales');
        $tech = $this->userWithRole('technician');
        $lead = $this->makeLead($sales->id);
        $proposal = $this->makeProposal($lead, 'pending_technical', $sales->id);

        $this->actingAs($tech)->post(route('sales.proposals.return-technical', $proposal))->assertRedirect();
        $this->assertSame('draft', $proposal->fresh()->status);
    }

    public function test_accept_syncs_lead_won(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);
        $proposal = $this->makeProposal($lead, 'sent', $sales->id);

        $this->actingAs($sales)->post(route('sales.proposals.respond', $proposal), ['decision' => 'accepted'])
            ->assertRedirect();

        $this->assertSame('accepted', $proposal->fresh()->status);
        $fresh = $lead->fresh();
        $this->assertSame('won', $fresh->status);
        $this->assertNotNull($fresh->closed_at);
        $this->assertTrue(LeadActivity::where('lead_id', $lead->id)->where('action', 'proposal_accepted')->exists());
    }

    public function test_convert_to_project_once_with_mapping(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);
        $proposal = $this->makeProposal($lead, 'accepted', $sales->id);
        $proposal->update([
            'po_document_path' => 'proposals/test/po/test.pdf',
            'po_number' => 'PO-TEST-3',
            'payment_scheme' => 'full',
        ]);
        $workType = WorkType::create(['name' => 'Instalasi']);

        $this->actingAs($sales)->post(route('sales.proposals.convert', $proposal), [
            'work_type_id' => $workType->id,
            'start_date' => now()->toDateString(),
        ])->assertRedirect();

        $project = Project::where('proposal_id', $proposal->id)->firstOrFail();
        $this->assertSame($lead->customer_id, $project->customer_id);
        $this->assertSame($proposal->proposal_number, $project->quotation_number);
        $this->assertSame($sales->id, (int) $project->sales_user_id);
        $this->assertTrue(LeadActivity::where('lead_id', $lead->id)->where('action', 'proposal_converted')->exists());

        // Convert kedua ditolak.
        $this->actingAs($sales)->post(route('sales.proposals.convert', $proposal), [
            'work_type_id' => $workType->id,
        ])->assertStatus(422);
    }

    public function test_payment_terms_are_saved_and_shown_on_customer_preview(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);

        $this->actingAs($sales)->post(route('sales.proposals.store'), [
            'lead_id' => $lead->id,
            'proposal_date' => now()->toDateString(),
            'payment_scheme' => 'dp',
            'dp_percent' => 30,
            'payment_terms' => 'DP 30% setelah PO, pelunasan setelah BAST',
            'delivery_terms' => 'Ready stock 3-5 hari kerja',
            'items' => [[
                'description' => 'Kamera CCTV',
                'quantity' => 1,
                'unit' => 'unit',
                'unit_price' => 1000000,
                'discount' => 0,
            ]],
        ])->assertRedirect();

        $proposal = Proposal::latest('id')->firstOrFail();

        $this->assertSame('dp', $proposal->payment_scheme);
        $this->assertEquals(30, (float) $proposal->dp_percent);
        $this->assertSame('DP 30% setelah PO, pelunasan setelah BAST', $proposal->payment_terms);

        $this->actingAs($sales)
            ->get(route('sales.proposals.preview', $proposal))
            ->assertOk()
            ->assertSee('DP 30% setelah PO, pelunasan setelah BAST')
            ->assertSee('Ready stock 3-5 hari kerja')
            ->assertSee('3DY GROUP')
            ->assertSee(__('Print / Save as PDF'));
    }

    public function test_convert_requires_customer_po_number_and_payment_terms_and_copies_deadline(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);
        $proposal = $this->makeProposal($lead, 'accepted', $sales->id);
        $workType = WorkType::create(['name' => 'Instalasi']);

        // Belum ada file PO.
        $this->actingAs($sales)->post(route('sales.proposals.convert', $proposal), [
            'work_type_id' => $workType->id,
        ])->assertStatus(422);

        // File PO ada, tapi nomor PO belum.
        $proposal->update(['po_document_path' => 'proposals/test/po/test.pdf']);
        $this->actingAs($sales)->post(route('sales.proposals.convert', $proposal), [
            'work_type_id' => $workType->id,
        ])->assertStatus(422);

        // Nomor PO ada, tapi cara bayar belum.
        $proposal->update(['po_number' => 'PO-CUST-1']);
        $this->actingAs($sales)->post(route('sales.proposals.convert', $proposal), [
            'work_type_id' => $workType->id,
        ])->assertStatus(422);

        // Lengkap: convert berhasil dan deadline ikut ke project.
        $deadline = now()->addDays(10)->toDateString();
        $proposal->update([
            'payment_scheme' => 'dp',
            'dp_percent' => 30,
            'delivery_deadline' => $deadline,
        ]);

        $this->actingAs($sales)->post(route('sales.proposals.convert', $proposal), [
            'work_type_id' => $workType->id,
        ])->assertRedirect();

        $project = Project::where('proposal_id', $proposal->id)->firstOrFail();
        $this->assertSame($deadline, $project->delivery_deadline?->toDateString());
    }

    public function test_lead_convert_redirects_to_accepted_proposal(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);
        $proposal = $this->makeProposal($lead, 'accepted', $sales->id);

        $this->actingAs($sales)
            ->patch(route('leads.convert', $lead), [])
            ->assertRedirect(route('sales.proposals.show', $proposal))
            ->assertSessionHas('error');
    }

    public function test_create_without_lead_shows_lead_picker(): void
    {
        $sales = $this->userWithRole('sales');
        $mine = $this->makeLead($sales->id);
        $otherSales = $this->userWithRole('sales');
        $foreign = $this->makeLead($otherSales->id);

        $html = $this->actingAs($sales)
            ->get(route('sales.proposals.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($mine->customer->name, $html);
        $this->assertStringNotContainsString($foreign->customer->name, $html);
        $this->assertStringContainsString(route('sales.proposals.create', ['lead_id' => $mine->id]), $html);
    }

    public function test_create_with_lead_shows_builder_and_rejects_foreign_lead(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);
        $otherSales = $this->userWithRole('sales');
        $foreign = $this->makeLead($otherSales->id);

        $this->actingAs($sales)
            ->get(route('sales.proposals.create', ['lead_id' => $lead->id]))
            ->assertOk()
            ->assertSee(__('Create Quotation'));

        $this->actingAs($sales)
            ->get(route('sales.proposals.create', ['lead_id' => $foreign->id]))
            ->assertForbidden();
    }

    public function test_po_upload_and_download(): void
    {
        Storage::fake('private');
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);
        $proposal = $this->makeProposal($lead, 'accepted', $sales->id);

        // Hanya accepted yang boleh upload.
        $draft = $this->makeProposal($lead, 'draft', $sales->id);
        $this->actingAs($sales)->post(route('sales.proposals.upload-po', $draft), [
            'po_file' => UploadedFile::fake()->create('po.pdf', 100, 'application/pdf'),
        ])->assertStatus(422);

        $this->actingAs($sales)->post(route('sales.proposals.upload-po', $proposal), [
            'po_file' => UploadedFile::fake()->create('po.pdf', 100, 'application/pdf'),
        ])->assertRedirect();
        $this->assertNotNull($proposal->fresh()->po_document_path);

        $this->actingAs($sales)->get(route('sales.proposals.download-po', $proposal))->assertOk();
    }

    public function test_customer_npwp_fields(): void
    {
        Storage::fake('private');
        $sales = $this->userWithRole('sales');
        $admin = $this->userWithRole('admin');
        $customer = Customer::create(['name' => 'PT NPWP']);

        $this->actingAs($sales)->put(route('customers.update', $customer), [
            'name' => 'PT NPWP',
            'pt_group' => 'NTI',
            'npwp' => '01.234.567.8-901.234',
            'npwp_document' => UploadedFile::fake()->create('npwp.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $fresh = $customer->fresh();
        $this->assertSame('01.234.567.8-901.234', $fresh->npwp);
        $this->assertNotNull($fresh->npwp_document_path);

        $this->actingAs($admin)->get(route('customers.npwp', $customer))->assertOk();
    }

    public function test_index_kpi_renders(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);
        $this->makeProposal($lead, 'draft', $sales->id);
        $this->makeProposal($lead, 'sent', $sales->id);

        $res = $this->actingAs($sales)->get(route('sales.proposals.index'));
        $res->assertOk();
        $res->assertSee(__('Pending Technical'));
        $res->assertSee(__('Won This Month'));
    }

    public function test_show_renders_per_status(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);

        // Draft: tombol Ke Teknis.
        $draft = $this->makeProposal($lead, 'draft', $sales->id);
        $this->actingAs($sales)->get(route('sales.proposals.show', $draft))
            ->assertOk()->assertSee(__('Ke Teknis'));

        // Accepted: form PO + modal convert.
        $won = $this->makeProposal($lead, 'accepted', $sales->id);
        $this->actingAs($sales)->get(route('sales.proposals.show', $won))
            ->assertOk()
            ->assertSee(__('Upload PO'))
            ->assertSee(__('Convert to Project'));
    }

    public function test_create_quotation_by_uploading_file_without_items(): void
    {
        Storage::fake('private');

        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);

        $this->actingAs($sales)->get(route('sales.proposals.create', ['lead_id' => $lead->id]))
            ->assertOk()
            ->assertSee('quotation_file', false);

        $response = $this->actingAs($sales)->post(route('sales.proposals.store'), [
            'lead_id' => $lead->id,
            'proposal_date' => now()->toDateString(),
            'valid_until' => now()->addDays(14)->toDateString(),
            'notes' => 'Quotation dari sales',
            'quotation_file' => UploadedFile::fake()->create('quotation-sales.pdf', 140, 'application/pdf'),
        ]);

        $proposal = Proposal::latest('id')->firstOrFail();

        $response->assertRedirect(route('sales.proposals.show', $proposal));
        $this->assertSame('draft', $proposal->status);
        $this->assertCount(0, $proposal->items()->get());

        $attachment = $proposal->attachments()->firstOrFail();
        $this->assertSame('quotation-sales.pdf', $attachment->original_name);
        Storage::disk('private')->assertExists($attachment->file_path);

        $this->actingAs($sales)->get(route('sales.proposals.show', $proposal))
            ->assertOk()
            ->assertSee('quotation-sales.pdf');
    }

    public function test_create_quotation_requires_file_or_item(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);

        $this->actingAs($sales)->post(route('sales.proposals.store'), [
            'lead_id' => $lead->id,
            'proposal_date' => now()->toDateString(),
        ])->assertStatus(422);
    }
}
