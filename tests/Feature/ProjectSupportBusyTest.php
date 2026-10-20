<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectSupportBusyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function technician(string $name = 'Teknisi'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole('technician');

        return $user;
    }

    private function makeProject(string $pic, Customer $customer): Project
    {
        $workType = WorkType::create(['name' => 'Instalasi']);

        return Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project ' . uniqid(),
            'pic_engineer' => $pic,
        ]);
    }

    public function test_pic_sibuk_tercoret_dan_terkunci_di_form(): void
    {
        $tech = $this->technician('Andi Sibuk');
        $customer = Customer::create(['name' => 'PT Sibuk', 'pt_group' => 'NTI']);
        $this->actingAs($tech);
        $this->makeProject('Andi Sibuk', $customer);

        $html = $this->get(route('projects.create', ['customer_id' => $customer->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('line-through', $html);
        $this->assertStringContainsString('Andi Sibuk', $html);
        // Checkbox Andi terkunci (disabled), tapi nama teknisi bebas tidak.
        $this->assertMatchesRegularExpression(
            '/<input[^>]*value="Andi Sibuk"[^>]*disabled/',
            $html
        );
    }

    public function test_store_menolak_support_yang_sedang_pic_aktif(): void
    {
        $tech = $this->technician('Andi Sibuk');
        $customer = Customer::create(['name' => 'PT Sibuk', 'pt_group' => 'NTI']);
        $this->actingAs($tech);
        $this->makeProject('Andi Sibuk', $customer);

        $workType = WorkType::create(['name' => 'Maintenance']);
        $response = $this->post(route('projects.store'), [
            'customer_id' => $customer->id,
            'name' => 'Project Baru',
            'work_type_id' => $workType->id,
            'support_technicians' => ['Andi Sibuk'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('support_technicians.0');
        $this->assertDatabaseMissing('projects', ['project_name' => 'Project Baru']);
    }

    public function test_support_bebas_tetap_bisa_dipilih(): void
    {
        $tech = $this->technician('Teknisi');
        $customer = Customer::create(['name' => 'PT Bebas', 'pt_group' => 'NTI']);
        $this->actingAs($tech);

        $workType = WorkType::create(['name' => 'Instalasi']);
        $this->post(route('projects.store'), [
            'customer_id' => $customer->id,
            'name' => 'Project Bebas',
            'work_type_id' => $workType->id,
            'support_technicians' => ['Budi Bebas'],
        ])->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'project_name' => 'Project Bebas',
            'support_technicians' => 'Budi Bebas',
        ]);
    }

    public function test_pic_project_sendiri_boleh_jadi_support_saat_edit(): void
    {
        $tech = $this->technician('Teknisi');
        $customer = Customer::create(['name' => 'PT Sendiri', 'pt_group' => 'NTI']);
        $this->actingAs($tech);
        $project = $this->makeProject('Andi Sendiri', $customer);

        $this->get(route('projects.edit', $project))->assertOk();
        $this->put(route('projects.update', $project), [
            'project_name' => $project->project_name,
            'work_type_id' => $project->work_type_id,
            'support_technicians' => ['Andi Sendiri'],
        ])->assertRedirect();

        $this->assertSame('Andi Sendiri', $project->fresh()->support_technicians);
    }
}
