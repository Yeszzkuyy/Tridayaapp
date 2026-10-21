<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function userWithRoles(string $name, array $roles): User
    {
        $user = User::factory()->create(['name' => $name]);
        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function makeProject(string $ptGroup = 'NTI'): Project
    {
        $customer = Customer::create(['name' => 'PT Dokumen ' . uniqid(), 'pt_group' => $ptGroup]);
        $workType = WorkType::create(['name' => 'Instalasi']);

        return Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Dokumen',
        ]);
    }

    private function upload(Project $project)
    {
        Storage::fake('private');

        return $this->post(route('project-documents.store', $project), [
            'file' => UploadedFile::fake()->create('boq.pdf', 100, 'application/pdf'),
        ]);
    }

    public function test_sales_yang_boleh_edit_customer_bisa_upload(): void
    {
        $sales = $this->userWithRoles('Sales NTI', ['sales', 'sales-nti']);
        $this->actingAs($sales);
        $project = $this->makeProject('NTI');

        $this->upload($project)->assertRedirect();

        $this->assertDatabaseHas('project_documents', [
            'project_id' => $project->id,
            'file_name' => 'boq.pdf',
        ]);
    }

    public function test_sales_beda_pt_tidak_bisa_upload(): void
    {
        $sales = $this->userWithRoles('Sales MGK', ['sales-mgk']);
        $this->actingAs($sales);
        $project = $this->makeProject('NTI');

        $this->upload($project)->assertForbidden();
    }

    public function test_teknisi_tetap_bisa_upload(): void
    {
        $tech = $this->userWithRoles('Teknisi', ['technician']);
        $this->actingAs($tech);
        $project = $this->makeProject('NTI');

        $this->upload($project)->assertRedirect();

        $this->assertDatabaseHas('project_documents', ['project_id' => $project->id]);
    }

    public function test_marketing_tidak_bisa_upload(): void
    {
        $mkt = $this->userWithRoles('Marketing', ['marketing']);
        $this->actingAs($mkt);
        $project = $this->makeProject('NTI');

        $this->upload($project)->assertForbidden();
    }

    public function test_sales_tidak_bisa_hapus_dokumen(): void
    {
        $tech = $this->userWithRoles('Teknisi', ['technician']);
        $sales = $this->userWithRoles('Sales NTI', ['sales', 'sales-nti']);
        $this->actingAs($tech);
        $project = $this->makeProject('NTI');

        $this->upload($project)->assertRedirect();
        $doc = $project->documents()->firstOrFail();

        $this->actingAs($sales);
        $this->delete(route('project-documents.destroy', $doc))->assertForbidden();
    }

    public function test_sales_bisa_buka_halaman_dokumen_dan_show_customer(): void
    {
        $sales = $this->userWithRoles('Sales NTI', ['sales', 'sales-nti']);
        $this->actingAs($sales);
        $project = $this->makeProject('NTI');

        $this->get(route('project-documents.index', $project))->assertOk();
        $this->upload($project)->assertRedirect();
        $this->get(route('customers.show', $project->customer_id))
            ->assertOk()
            ->assertSee('data-pdf-url', false)
            ->assertSee('Upload File', false);
    }

    public function test_script_dokumen_dipanggil_di_dalam_layout(): void
    {
        // Regresi: script popup/filter/preview harus dipanggil DI DALAM
        // <x-app-layout>. Dulu @vite ditaruh setelah </x-app-layout> sehingga
        // tag-nya terbuang oleh komponen layout dan semua tombol dokumen
        // (upload/preview/filter/rename) jadi tidak berfungsi.
        $view = file_get_contents(resource_path('views/customers/show.blade.php'));

        $scriptPos = strpos($view, 'resources/js/doc-thumbs.js');
        $layoutEndPos = strpos($view, '</x-app-layout>');

        $this->assertNotFalse($scriptPos, 'View customer harus memanggil script doc-thumbs.js');
        $this->assertNotFalse($layoutEndPos, 'View customer harus memakai <x-app-layout>');
        $this->assertLessThan(
            $layoutEndPos,
            $scriptPos,
            'Script doc-thumbs.js harus dipanggil sebelum </x-app-layout> agar tag-nya ikut dirender'
        );
    }

    public function test_upload_tanpa_project_tersimpan_sebagai_dokumen_umum(): void
    {
        $sales = $this->userWithRoles('Sales NTI', ['sales', 'sales-nti']);
        $this->actingAs($sales);
        $customer = Customer::create(['name' => 'PT Tanpa Proyek', 'pt_group' => 'NTI']);

        Storage::fake('private');
        $this->post(route('customer-documents.store', $customer), [
            'file' => UploadedFile::fake()->create('umum.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertDatabaseHas('project_documents', [
            'customer_id' => $customer->id,
            'project_id' => null,
            'file_name' => 'umum.pdf',
        ]);

        $this->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('umum.pdf', false);
    }

    public function test_upload_ditolak_bila_project_milik_customer_lain(): void
    {
        $sales = $this->userWithRoles('Sales NTI', ['sales', 'sales-nti']);
        $this->actingAs($sales);
        $customer = Customer::create(['name' => 'PT A', 'pt_group' => 'NTI']);
        $otherProject = $this->makeProject('NTI');

        Storage::fake('private');
        $this->post(route('customer-documents.store', $customer), [
            'file' => UploadedFile::fake()->create('x.pdf', 100, 'application/pdf'),
            'project_id' => $otherProject->id,
        ])->assertStatus(422);
    }

    public function test_teknisi_bisa_upload_tanpa_project_dan_marketing_ditolak(): void
    {
        $customer = Customer::create(['name' => 'PT Teknisi', 'pt_group' => 'NTI']);

        $tech = $this->userWithRoles('Teknisi', ['technician']);
        $this->actingAs($tech);
        Storage::fake('private');
        $this->post(route('customer-documents.store', $customer), [
            'file' => UploadedFile::fake()->create('tek.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $mkt = $this->userWithRoles('Marketing', ['marketing']);
        $this->actingAs($mkt);
        $this->post(route('customer-documents.store', $customer), [
            'file' => UploadedFile::fake()->create('mkt.pdf', 100, 'application/pdf'),
        ])->assertForbidden();
    }

    public function test_upload_gagal_membuka_lagi_popup_dengan_error(): void
    {
        $sales = $this->userWithRoles('Sales NTI', ['sales', 'sales-nti']);
        $this->actingAs($sales);
        $customer = Customer::create(['name' => 'PT Gagal', 'pt_group' => 'NTI']);

        Storage::fake('private');
        $this->post(route('customer-documents.store', $customer), [
            'file' => UploadedFile::fake()->create('jahat.exe', 100, 'application/x-msdownload'),
        ])->assertRedirect();

        $this->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('doc-open', false);
    }

    public function test_upload_file_jenis_invoice_po_payment(): void
    {
        $admin = $this->userWithRoles('Super Admin Jenis', ['super-admin']);
        $this->actingAs($admin);
        $customer = Customer::create(['name' => 'PT Jenis', 'pt_group' => 'NTI']);

        Storage::fake('private');
        foreach (['invoice', 'po', 'payment'] as $type) {
            $this->post(route('customer-documents.store', $customer), [
                'file' => UploadedFile::fake()->create($type . '.pdf', 100, 'application/pdf'),
                'doc_type' => $type,
                // project_id sengaja diisi: file jenis ini tetap milik customer.
                'project_id' => $this->makeProject('NTI')->id,
            ])->assertRedirect();

            $doc = \App\Models\ProjectDocument::where('customer_id', $customer->id)
                ->where('doc_type', $type)
                ->firstOrFail();
            $this->assertNull($doc->project_id, "File $type tidak boleh melekat di project.");
        }

        $this->assertSame(3, \App\Models\ProjectDocument::where('customer_id', $customer->id)->whereNotNull('doc_type')->count());

        $html = $this->get(route('customers.show', $customer))->assertOk()->getContent();

        // Tiap bagian menampilkan file-nya + tombol upload, tanpa tombol create lama.
        $this->assertStringContainsString('invoice.pdf', $html);
        $this->assertStringContainsString('po.pdf', $html);
        $this->assertStringContainsString('payment.pdf', $html);
        $this->assertStringNotContainsString(__('Create Invoice'), $html);
        $this->assertStringNotContainsString(__('Create PO'), $html);
        $this->assertStringNotContainsString(__('Record Payment'), $html);

        // Tidak dobel muncul di tab Documents (khusus dokumen umum/project).
        $grid = substr($html, (int) strpos($html, 'id="doc-grid"'), 4000);
        $this->assertStringNotContainsString('invoice.pdf', $grid);
    }

    public function test_doc_type_ngawur_ditolak(): void
    {
        $admin = $this->userWithRoles('Super Admin Jenis2', ['super-admin']);
        $this->actingAs($admin);
        $customer = Customer::create(['name' => 'PT Jenis Ngawur', 'pt_group' => 'NTI']);

        Storage::fake('private');
        $this->post(route('customer-documents.store', $customer), [
            'file' => UploadedFile::fake()->create('jahat.pdf', 100, 'application/pdf'),
            'doc_type' => 'apapun',
        ])->assertSessionHasErrors('doc_type');

        $this->assertSame(0, \App\Models\ProjectDocument::where('customer_id', $customer->id)->count());
    }

    public function test_sales_edit_customer_bisa_upload_file_jenis(): void
    {
        $sales = $this->userWithRoles('Sales Jenis', ['sales', 'sales-nti']);
        $this->actingAs($sales);
        $customer = Customer::create(['name' => 'PT Jenis Sales', 'pt_group' => 'NTI']);

        Storage::fake('private');
        $this->post(route('customer-documents.store', $customer), [
            'file' => UploadedFile::fake()->create('faktur.pdf', 100, 'application/pdf'),
            'doc_type' => 'invoice',
        ])->assertRedirect();

        $this->assertDatabaseHas('project_documents', [
            'customer_id' => $customer->id,
            'doc_type' => 'invoice',
        ]);
    }
    public function test_hapus_customer_ikut_hapus_dokumen_umum(): void
    {
        $sales = $this->userWithRoles('Sales NTI', ['sales', 'sales-nti']);
        $this->actingAs($sales);
        $customer = Customer::create(['name' => 'PT Hapus', 'pt_group' => 'NTI']);

        Storage::fake('private');
        $this->post(route('customer-documents.store', $customer), [
            'file' => UploadedFile::fake()->create('hapus.pdf', 100, 'application/pdf'),
        ])->assertRedirect();
        $doc = \App\Models\ProjectDocument::where('customer_id', $customer->id)->firstOrFail();

        $customer->delete();

        $this->assertSoftDeleted('project_documents', ['id' => $doc->id]);
    }

    public function test_rename_menyimpan_nama_dan_ekstensi_asli(): void
    {
        $tech = $this->userWithRoles('Teknisi', ['technician']);
        $customer = Customer::create(['name' => 'PT Rename', 'pt_group' => 'NTI']);
        $this->actingAs($tech);
        $project = $this->makeProject('NTI');
        $project->update(['customer_id' => $customer->id]);

        Storage::fake('private');
        $this->upload($project)->assertRedirect();
        $doc = \App\Models\ProjectDocument::where('project_id', $project->id)->firstOrFail();

        // Coba ganti ekstensi lewat nama -> ekstensi asli dipertahankan.
        $this->patch(route('project-documents.rename', $doc), ['name' => 'laporan-baru.exe'])
            ->assertRedirect();

        $this->assertSame('laporan-baru.pdf', $doc->fresh()->file_name);
    }

    public function test_rename_ditolak_untuk_yang_tidak_berhak(): void
    {
        $tech = $this->userWithRoles('Teknisi', ['technician']);
        $customer = Customer::create(['name' => 'PT Rename2', 'pt_group' => 'NTI']);
        $this->actingAs($tech);
        $project = $this->makeProject('NTI');
        $project->update(['customer_id' => $customer->id]);

        Storage::fake('private');
        $this->upload($project)->assertRedirect();
        $doc = \App\Models\ProjectDocument::where('project_id', $project->id)->firstOrFail();

        $mkt = $this->userWithRoles('Marketing', ['marketing']);
        $this->actingAs($mkt);
        $this->patch(route('project-documents.rename', $doc), ['name' => ' Jahat '])
            ->assertForbidden();
        $this->assertNotSame('Jahat', $doc->fresh()->file_name);

        $salesLain = $this->userWithRoles('Sales MGK', ['sales-mgk']);
        $customerTanpaProyek = Customer::create(['name' => 'PT Umum', 'pt_group' => 'MGK']);
        $this->actingAs($tech);
        $this->post(route('customer-documents.store', $customerTanpaProyek), [
            'file' => UploadedFile::fake()->create('umum.pdf', 100, 'application/pdf'),
        ])->assertRedirect();
        $docUmum = \App\Models\ProjectDocument::where('customer_id', $customerTanpaProyek->id)->firstOrFail();

        // Sales beda PT tidak boleh rename dokumen umum PT lain... (sales-mgk vs MGK = boleh)
        // jadi pakai sales NTI untuk customer MGK -> ditolak.
        $salesNti = $this->userWithRoles('Sales NTI', ['sales', 'sales-nti']);
        $this->actingAs($salesNti);
        $this->patch(route('project-documents.rename', $docUmum), ['name' => 'Coba'])
            ->assertForbidden();
    }
}
