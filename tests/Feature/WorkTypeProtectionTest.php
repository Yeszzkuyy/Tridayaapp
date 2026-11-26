<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkTypeProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function user(string $role = 'super-admin'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function customer(): Customer
    {
        return Customer::create([
            'name' => 'PT Work Type',
            'company' => 'PT Work Type',
            'status' => 'deal',
            'pt_group' => 'NTI',
        ]);
    }

    private function project(WorkType $workType, ?Customer $customer = null): Project
    {
        return Project::create([
            'customer_id' => ($customer ?? $this->customer())->id,
            'work_type_id' => $workType->id,
            'project_status_id' => ProjectStatus::create(['name' => 'Open'])->id,
            'pic_engineer' => 'Naufal',
            'project_name' => 'Project '.$workType->name,
        ]);
    }

    public function test_delete_work_type_tanpa_project_menonaktifkan_bukan_menghapus(): void
    {
        $admin = $this->user();
        $workType = WorkType::create(['name' => 'Instalasi']);

        $this->actingAs($admin)
            ->delete(route('admin-panel.work-types.destroy', $workType))
            ->assertRedirect(route('admin-panel.work-types.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('work_types', [
            'id' => $workType->id,
            'is_active' => false,
        ]);
    }

    public function test_delete_work_type_yang_dipakai_project_ditolak(): void
    {
        $admin = $this->user();
        $workType = WorkType::create(['name' => 'Instalasi']);
        $project = $this->project($workType);

        $this->actingAs($admin)
            ->delete(route('admin-panel.work-types.destroy', $workType))
            ->assertRedirect(route('admin-panel.work-types.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('work_types', [
            'id' => $workType->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'work_type_id' => $workType->id,
        ]);
    }

    public function test_delete_work_type_yang_dipakai_project_terhapus_tetap_ditolak(): void
    {
        $admin = $this->user();
        $workType = WorkType::create(['name' => 'Instalasi Arsip']);
        $project = $this->project($workType);
        $project->delete();

        $this->actingAs($admin)
            ->delete(route('admin-panel.work-types.destroy', $workType))
            ->assertRedirect(route('admin-panel.work-types.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('work_types', [
            'id' => $workType->id,
            'is_active' => true,
        ]);
    }

    public function test_index_menampilkan_status_work_type(): void
    {
        $admin = $this->user();
        $workType = WorkType::create(['name' => 'Instalasi Index']);
        $this->project($workType);

        $this->actingAs($admin)
            ->get(route('admin-panel.work-types.index'))
            ->assertOk()
            ->assertSee($workType->name)
            ->assertSee(__('Active'));
    }

    public function test_deactivate_work_type_terpakai_dan_project_lama_tetap_menampilkannya(): void
    {
        $admin = $this->user();
        $workType = WorkType::create(['name' => 'Instalasi']);
        $project = $this->project($workType);

        $this->actingAs($admin)
            ->patch(route('admin-panel.work-types.toggle-active', $workType))
            ->assertRedirect(route('admin-panel.work-types.index'))
            ->assertSessionHas('success');

        $this->assertFalse($workType->fresh()->isActive());
        $this->assertSame($workType->id, $project->fresh()->work_type_id);

        $this->actingAs($admin)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee($workType->name);
    }

    public function test_activate_work_type(): void
    {
        $admin = $this->user();
        $workType = WorkType::create(['name' => 'Instalasi', 'is_active' => false]);

        $this->actingAs($admin)
            ->patch(route('admin-panel.work-types.toggle-active', $workType))
            ->assertRedirect(route('admin-panel.work-types.index'))
            ->assertSessionHas('success');

        $this->assertTrue($workType->fresh()->isActive());
    }

    public function test_selector_project_baru_hanya_menampilkan_work_type_aktif(): void
    {
        $admin = $this->user();
        $customer = $this->customer();
        $active = WorkType::create(['name' => 'Aktif Selector']);
        $inactive = WorkType::create(['name' => 'Nonaktif Selector', 'is_active' => false]);

        $this->actingAs($admin)
            ->get(route('projects.create', ['customer_id' => $customer->id]))
            ->assertOk()
            ->assertSee($active->name)
            ->assertDontSee($inactive->name);
    }

    public function test_project_store_menolak_work_type_nonaktif(): void
    {
        $admin = $this->user();
        $customer = $this->customer();
        $inactive = WorkType::create(['name' => 'Nonaktif Store', 'is_active' => false]);

        $this->actingAs($admin)
            ->post(route('projects.store'), [
                'customer_id' => $customer->id,
                'name' => 'Project Baru',
                'work_type_id' => $inactive->id,
            ])
            ->assertSessionHasErrors('work_type_id');

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_project_update_tetap_boleh_memakai_work_type_nonaktif_yang_sama(): void
    {
        $admin = $this->user();
        $workType = WorkType::create(['name' => 'Instalasi']);
        $project = $this->project($workType);

        $this->actingAs($admin)
            ->patch(route('admin-panel.work-types.toggle-active', $workType));

        $this->actingAs($admin)
            ->put(route('projects.update', $project), [
                'project_name' => 'Project Diperbarui',
                'work_type_id' => $workType->id,
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertSame($workType->id, $project->fresh()->work_type_id);
    }

    public function test_project_update_menolak_ganti_ke_work_type_nonaktif_lain(): void
    {
        $admin = $this->user();
        $current = WorkType::create(['name' => 'Instalasi']);
        $inactive = WorkType::create(['name' => 'Nonaktif Lain', 'is_active' => false]);
        $project = $this->project($current);

        $this->actingAs($admin)
            ->put(route('projects.update', $project), [
                'project_name' => 'Project Diperbarui',
                'work_type_id' => $inactive->id,
            ])
            ->assertSessionHasErrors('work_type_id');

        $this->assertSame($current->id, $project->fresh()->work_type_id);
    }

    public function test_project_edit_menampilkan_work_type_nonaktif_yang_dipakai(): void
    {
        $admin = $this->user();
        $workType = WorkType::create(['name' => 'Instalasi Lama']);
        $project = $this->project($workType);

        $this->actingAs($admin)
            ->patch(route('admin-panel.work-types.toggle-active', $workType));

        $this->actingAs($admin)
            ->get(route('projects.edit', $project))
            ->assertOk()
            ->assertSee($workType->name)
            ->assertSee(__('Inactive'));
    }

    public function test_convert_lead_menolak_work_type_nonaktif(): void
    {
        $admin = $this->user();
        $customer = $this->customer();
        $inactive = WorkType::create(['name' => 'Nonaktif Lead', 'is_active' => false]);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'status' => 'hot',
        ]);
        $status = ProjectStatus::create(['name' => 'Open Lead']);

        $this->actingAs($admin)
            ->patch(route('leads.convert', $lead), [
                'project_name' => 'Project dari Lead',
                'project_status_id' => $status->id,
                'work_type_id' => $inactive->id,
            ])
            ->assertSessionHasErrors('work_type_id');

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_role_tanpa_manage_monitoring_tidak_bisa_mengelola_work_type(): void
    {
        $sales = $this->user('sales');

        $this->actingAs($sales)
            ->get(route('admin-panel.work-types.index'))
            ->assertForbidden();

        $this->actingAs($sales)
            ->post(route('admin-panel.work-types.store'), ['name' => 'Tidak Boleh'])
            ->assertForbidden();
    }
}
