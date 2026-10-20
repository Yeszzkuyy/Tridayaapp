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
}
