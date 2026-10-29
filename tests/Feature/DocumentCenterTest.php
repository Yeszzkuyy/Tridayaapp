<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Proposal;
use App\Models\ProposalAttachment;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPurchaseOrder;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pencarian, filter, urutan, pagination, dan penanganan file hilang pada
 * Document Center (menu Dokumen).
 */
class DocumentCenterTest extends TestCase
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

    private function customer(string $name = 'PT Alpha', ?string $pt = null): Customer
    {
        return Customer::create(['name' => $name, 'pt_group' => $pt]);
    }

    private function project(Customer $customer, string $name = 'Project Alpha'): Project
    {
        $workType = WorkType::firstOrCreate(['name' => 'Instalasi']);

        // Observer mencatat ProjectActivity dengan user login; test ini tidak
        // butuh log aktivitas, jadi event dimatikan agar tidak bergantung auth.
        return Project::withoutEvents(fn () => Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => $name,
            'project_code' => strtoupper(substr(md5($name), 0, 6)),
        ]));
    }

    private function document(Customer $customer, ?Project $project, array $attributes = []): ProjectDocument
    {
        $createdAt = $attributes['created_at'] ?? null;
        unset($attributes['created_at']);

        $document = ProjectDocument::create(array_merge([
            'customer_id' => $customer->id,
            'project_id' => $project?->id,
            'file_name' => 'dokumen-umum.pdf',
            'file_path' => 'documents/'.uniqid().'.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'uploaded_by' => User::first()?->id,
        ], $attributes));

        if ($createdAt) {
            $document->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        return $document;
    }

    private function invoiceWithFile(Customer $customer): ProjectDocument
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-SECRET-001',
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        return $this->document($customer, null, [
            'doc_type' => 'invoice',
            'documentable_type' => Invoice::class,
            'documentable_id' => $invoice->id,
            'file_name' => 'faktur-secret.pdf',
            'file_path' => 'documents/inv-secret.pdf',
        ]);
    }

    private function supplierInvoiceWithFile(?Customer $customer = null): SupplierInvoice
    {
        $supplier = Supplier::create(['name' => 'PT Supplier Jaya']);
        $order = SupplierPurchaseOrder::create([
            'po_number' => 'SPO-SECRET-77',
            'supplier_id' => $supplier->id,
            'issue_date' => now()->toDateString(),
            'status' => 'ordered',
        ]);

        return SupplierInvoice::create([
            'internal_ref_number' => 'SI-'.uniqid(),
            'supplier_invoice_number' => 'SUP-INV-SECRET-9',
            'supplier_id' => $supplier->id,
            'supplier_purchase_order_id' => $order->id,
            'project_id' => $customer?->projects()->first()?->id,
            'invoice_type' => 'full',
            'amount' => 500000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'invoice_file_path' => 'supplier-invoices/secret.pdf',
        ]);
    }

    private function proposalWithFile(Customer $customer, string $poNumber = 'CPO-SECRET-5'): Proposal
    {
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'status' => 'cool',
        ]);

        $proposal = Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => 'QUO-SECRET-42',
            'created_by' => User::first()?->id,
            'proposal_date' => now()->toDateString(),
            'customer' => $customer->name,
            'status' => 'accepted',
            'po_number' => $poNumber,
            'po_document_path' => 'quotations/po-secret.pdf',
        ]);

        ProposalAttachment::create([
            'proposal_id' => $proposal->id,
            'file_path' => 'quotations/quotation-secret.pdf',
            'original_name' => 'quotation-secret.pdf',
            'mime' => 'application/pdf',
            'size' => 2048,
            'uploaded_by' => User::first()?->id,
        ]);

        return $proposal;
    }

    public function test_document_center_requires_authentication(): void
    {
        $this->get(route('documents.index'))->assertRedirect(route('login'));
    }

    public function test_document_center_opens_for_authorized_roles(): void
    {
        foreach (['super-admin', 'management', 'ceo', 'admin', 'finance', 'purchasing', 'sales', 'technician', 'marketing', 'inside-sales'] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('documents.index'))
                ->assertOk();
        }
    }

    public function test_documents_menu_is_visible_for_users_with_customer_access(): void
    {
        foreach (['sales', 'technician', 'admin', 'marketing'] as $role) {
            $html = $this->actingAs($this->user($role))->get(route('dashboard'))->assertOk()->getContent();
            $this->assertStringContainsString(route('documents.index'), $html, "Menu Documents tidak tampil untuk {$role}");
        }
    }

    public function test_searches_by_file_name(): void
    {
        $admin = $this->user('super-admin');
        $customer = $this->customer();
        $this->document($customer, null, ['file_name' => 'laporan-unik-xyz.pdf', 'file_path' => 'documents/a.pdf']);

        $this->actingAs($admin)
            ->get(route('documents.index', ['q' => 'laporan-unik-xyz']))
            ->assertOk()
            ->assertSee('laporan-unik-xyz.pdf', false);
    }

    public function test_searches_by_invoice_number(): void
    {
        $admin = $this->user('super-admin');
        $customer = $this->customer();
        $this->invoiceWithFile($customer);

        $this->actingAs($admin)
            ->get(route('documents.index', ['q' => 'INV-SECRET-001']))
            ->assertOk()
            ->assertSee('faktur-secret.pdf', false);
    }

    public function test_searches_by_customer_po_and_quotation_number(): void
    {
        $admin = $this->user('super-admin');
        $customer = $this->customer('PT Quotation Search');
        $this->proposalWithFile($customer, 'CPO-SECRET-5');

        $this->actingAs($admin)
            ->get(route('documents.index', ['q' => 'CPO-SECRET-5']))
            ->assertOk()
            ->assertSee('CPO-SECRET-5', false);

        $this->actingAs($admin)
            ->get(route('documents.index', ['q' => 'QUO-SECRET-42']))
            ->assertOk()
            ->assertSee('quotation-secret.pdf', false);
    }

    public function test_searches_by_supplier_po_number(): void
    {
        $admin = $this->user('super-admin');
        $customer = $this->customer();
        $project = $this->project($customer);
        $this->supplierInvoiceWithFile($customer);

        $this->actingAs($admin)
            ->get(route('documents.index', ['q' => 'SPO-SECRET-77']))
            ->assertOk()
            ->assertSee('SUP-INV-SECRET-9', false);
    }

    public function test_searches_by_customer_and_project_name(): void
    {
        $admin = $this->user('super-admin');
        $customer = $this->customer('PT Nama Unik Sekali');
        $project = $this->project($customer, 'Proyek Pencarian Unik');
        $this->document($customer, $project, [
            'file_name' => 'gambar-teknis.pdf',
            'file_path' => 'documents/teknis.pdf',
        ]);

        $this->actingAs($admin)
            ->get(route('documents.index', ['q' => 'Nama Unik Sekali']))
            ->assertOk()
            ->assertSee('gambar-teknis.pdf', false);

        $this->actingAs($admin)
            ->get(route('documents.index', ['q' => 'Pencarian Unik']))
            ->assertOk()
            ->assertSee('gambar-teknis.pdf', false);
    }

    public function test_category_customer_and_date_filters_can_be_combined(): void
    {
        $admin = $this->user('super-admin');
        $customer = $this->customer();
        $project = $this->project($customer);

        $this->document($customer, $project, [
            'file_name' => 'foto-proyek.pdf',
            'file_path' => 'documents/proyek.pdf',
            'created_at' => now()->subDays(2),
        ]);
        $this->document($customer, null, [
            'file_name' => 'dokumen-umum.pdf',
            'file_path' => 'documents/umum.pdf',
            'created_at' => now()->subDays(10),
        ]);

        $this->actingAs($admin)
            ->get(route('documents.index', [
                'category' => 'project',
                'customer_id' => $customer->id,
                'date_field' => 'upload',
                'date_from' => now()->subDays(3)->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('foto-proyek.pdf', false)
            ->assertDontSee('dokumen-umum.pdf', false);
    }

    public function test_filters_are_preserved_in_pagination_and_category_links(): void
    {
        $admin = $this->user('super-admin');
        $customer = $this->customer();
        $this->document($customer, null, ['file_name' => 'a.pdf', 'file_path' => 'documents/a.pdf']);

        $response = $this->actingAs($admin)->get(route('documents.index', [
            'q' => 'a.pdf',
            'customer_id' => $customer->id,
            'category' => 'customer',
        ]));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('customer_id='.$customer->id, $html);
        $this->assertStringContainsString('q=a.pdf', $html);
    }

    public function test_newest_first_and_missing_file_is_marked_unavailable(): void
    {
        $admin = $this->user('super-admin');
        $customer = $this->customer();

        $old = $this->document($customer, null, [
            'file_name' => 'file-lama.pdf',
            'file_path' => 'documents/old.pdf',
            'created_at' => now()->subDays(3),
        ]);
        $new = $this->document($customer, null, [
            'file_name' => 'file-baru.pdf',
            'file_path' => 'documents/new.pdf',
            'created_at' => now(),
        ]);
        Storage::disk('private')->put('documents/new.pdf', 'isi');

        $html = $this->actingAs($admin)
            ->get(route('documents.index'))
            ->assertOk()
            ->getContent();

        $this->assertLessThan(strpos($html, 'file-lama.pdf'), strpos($html, 'file-baru.pdf'));
        $this->assertStringContainsString(__('Unavailable'), $html);
        // File yang hilang tidak boleh diberi tautan download.
        $this->assertStringNotContainsString(route('project-documents.download', $old), $html);
        $this->assertStringContainsString(route('project-documents.download', $new), $html);
    }

    public function test_one_file_is_listed_only_once(): void
    {
        $admin = $this->user('super-admin');
        $customer = $this->customer();
        $this->document($customer, $this->project($customer), [
            'file_name' => 'sekali-saja.pdf',
            'file_path' => 'documents/once.pdf',
        ]);

        $response = $this->actingAs($admin)->get(route('documents.index'))->assertOk();

        // Satu file = satu baris. Nama file memang muncul beberapa kali di
        // markup (judul tombol, title, payload panel detail), jadi yang
        // diperiksa adalah jumlah baris hasil query.
        $documents = $response->viewData('documents');
        $this->assertSame(1, $documents->total());
        $this->assertSame(1, $documents->getCollection()->where('name', 'sekali-saja.pdf')->count());
    }

    public function test_pagination_is_stable_and_does_not_repeat_rows(): void
    {
        $admin = $this->user('super-admin');
        $customer = $this->customer();

        for ($i = 0; $i < 25; $i++) {
            $this->document($customer, null, [
                'file_name' => "halaman-{$i}.pdf",
                'file_path' => "documents/page-{$i}.pdf",
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $pageOne = $this->actingAs($admin)->get(route('documents.index'))->assertOk();
        $pageTwo = $this->actingAs($admin)
            ->get(route('documents.index', ['page' => 2]))
            ->assertOk();

        $namesOne = $pageOne->viewData('documents')->getCollection()->pluck('name')->all();
        $namesTwo = $pageTwo->viewData('documents')->getCollection()->pluck('name')->all();

        $this->assertSame(20, count($namesOne));
        $this->assertSame(5, count($namesTwo));
        $this->assertSame([], array_intersect($namesOne, $namesTwo));
        $this->assertSame('halaman-0.pdf', $namesOne[0]);
    }

    public function test_query_count_does_not_grow_with_number_of_rows(): void
    {
        $admin = $this->user('super-admin');
        $customer = $this->customer();
        $this->document($customer, null, ['file_name' => 'awal.pdf', 'file_path' => 'documents/1.pdf']);

        $countFor = function () use ($admin) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($admin)->get(route('documents.index'))->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $before = $countFor();

        for ($i = 0; $i < 12; $i++) {
            $this->document($customer, null, [
                'file_name' => "tambahan-{$i}.pdf",
                'file_path' => "documents/extra-{$i}.pdf",
            ]);
        }

        $after = $countFor();

        $this->assertLessThanOrEqual($before + 2, $after, 'Jumlah query naik saat baris bertambah (indikasi N+1).');
    }
}
