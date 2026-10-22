<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCreateAccessTest extends TestCase
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

    private function makeCustomer(string $pt = 'NTI'): Customer
    {
        return Customer::create(['name' => 'PT Proyek ' . uniqid(), 'pt_group' => $pt]);
    }

    private function payload(Customer $customer, array $overrides = []): array
    {
        $workType = WorkType::create(['name' => 'Instalasi']);

        return array_merge([
            'customer_id' => $customer->id,
            'name' => 'Project Baru',
            'work_type_id' => $workType->id,
        ], $overrides);
    }

    public function test_sales_tidak_bisa_tambah_project(): void
    {
        $sales = $this->userWithRoles('Sales', ['sales', 'sales-nti']);
        $customer = $this->makeCustomer('NTI');

        $this->actingAs($sales);
        $this->get(route('projects.create', ['customer_id' => $customer->id]))->assertForbidden();
        $this->post(route('projects.store'), $this->payload($customer))->assertForbidden();
    }

    public function test_management_bisa_tambah_project(): void
    {
        $mgmt = $this->userWithRoles('Management', ['management']);
        $customer = $this->makeCustomer('NTI');

        $this->actingAs($mgmt);
        $this->post(route('projects.store'), $this->payload($customer))->assertRedirect();

        $this->assertDatabaseHas('projects', ['customer_id' => $customer->id]);
    }

    public function test_sales_beda_pt_tidak_bisa_tambah_project(): void
    {
        $sales = $this->userWithRoles('Sales MGK', ['sales-mgk']);
        $customer = $this->makeCustomer('NTI');

        $this->actingAs($sales);
        $this->get(route('projects.create', ['customer_id' => $customer->id]))->assertForbidden();
        $this->post(route('projects.store'), $this->payload($customer))->assertForbidden();
    }

    public function test_marketing_tidak_bisa_tambah_project(): void
    {
        $mkt = $this->userWithRoles('Marketing', ['marketing']);
        $customer = $this->makeCustomer('NTI');

        $this->actingAs($mkt);
        $this->get(route('projects.create', ['customer_id' => $customer->id]))->assertForbidden();
        $this->post(route('projects.store'), $this->payload($customer))->assertForbidden();
    }

    public function test_teknisi_tetap_bisa_tambah_project_dengan_pic(): void
    {
        $tech = $this->userWithRoles('Teknisi', ['technician']);
        $customer = $this->makeCustomer('NTI');

        $this->actingAs($tech);
        $this->post(route('projects.store'), $this->payload($customer, ['pic_engineer' => 'Andi']))
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'customer_id' => $customer->id,
            'pic_engineer' => 'Andi',
        ]);
    }

    public function test_tambah_project_tanpa_customer_dialihkan(): void
    {
        $tech = $this->userWithRoles('Teknisi', ['technician']);

        $this->actingAs($tech);
        $this->get(route('projects.create'))->assertRedirect(route('customers.index'));
    }

    public function test_sales_tidak_bisa_edit_atau_hapus_project(): void
    {
        $tech = $this->userWithRoles('Teknisi', ['technician']);
        $sales = $this->userWithRoles('Sales', ['sales', 'sales-nti']);
        $customer = $this->makeCustomer('NTI');

        $this->actingAs($tech);
        $this->post(route('projects.store'), $this->payload($customer))->assertRedirect();
        $project = Project::where('customer_id', $customer->id)->firstOrFail();

        $this->actingAs($sales);
        $this->get(route('projects.edit', $project))->assertForbidden();
        $this->put(route('projects.update', $project), [
            'project_name' => 'Project Diubah',
            'work_type_id' => $project->work_type_id,
        ])->assertForbidden();
        $this->delete(route('projects.destroy', $project))->assertForbidden();
        $this->assertNotNull($project->fresh());
    }

    public function test_sales_beda_pt_tidak_bisa_edit_atau_hapus_project(): void
    {
        $owner = $this->userWithRoles('Pemilik', ['technician']);
        $sales = $this->userWithRoles('Sales MGK', ['sales-mgk']);
        $customer = $this->makeCustomer('NTI');

        $this->actingAs($owner);
        $this->post(route('projects.store'), $this->payload($customer))->assertRedirect();
        $project = Project::where('customer_id', $customer->id)->firstOrFail();

        $this->actingAs($sales);
        $this->get(route('projects.edit', $project))->assertForbidden();
        $this->put(route('projects.update', $project), [
            'project_name' => 'Coba Ubah',
            'work_type_id' => $project->work_type_id,
        ])->assertForbidden();
        $this->delete(route('projects.destroy', $project))->assertForbidden();
        $this->assertNotNull($project->fresh());
    }

    public function test_marketing_tidak_bisa_edit_atau_hapus_project(): void
    {
        $owner = $this->userWithRoles('Pemilik', ['technician']);
        $mkt = $this->userWithRoles('Marketing', ['marketing']);
        $customer = $this->makeCustomer('NTI');

        $this->actingAs($owner);
        $this->post(route('projects.store'), $this->payload($customer))->assertRedirect();
        $project = Project::where('customer_id', $customer->id)->firstOrFail();

        $this->actingAs($mkt);
        $this->get(route('projects.edit', $project))->assertForbidden();
        $this->put(route('projects.update', $project), [
            'project_name' => 'Coba Ubah',
            'work_type_id' => $project->work_type_id,
        ])->assertForbidden();
        $this->delete(route('projects.destroy', $project))->assertForbidden();
    }
}
