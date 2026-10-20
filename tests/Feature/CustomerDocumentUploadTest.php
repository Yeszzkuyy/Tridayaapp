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
