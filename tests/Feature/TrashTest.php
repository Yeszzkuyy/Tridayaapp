<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrashTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        return $user;
    }

    private function projectWith(String $name): Project
    {
        $customer = Customer::create(['name' => 'PT ABC Indonesia']);
        $workType = WorkType::create(['name' => 'Instalasi']);

        return Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_status_id' => ProjectStatus::create(['name' => 'Open'])->id,
            'pic_engineer' => 'Naufal',
            'project_name' => $name,
        ]);
    }

    public function test_trash_menampilkan_customer_dan_project_terhapus(): void
    {
        $this->actingAs($this->adminUser());

        $project = $this->projectWith('Project Lama');
        $customer = Customer::create(['name' => 'PT Baru Terhapus']);
        $project->deleted_at = now()->subDays(3);
        $project->save();
        $project->delete();
        $customer->delete();

        $this->get(route('trash.index'))
            ->assertOk()
            ->assertSee(__('Customer Terhapus'))
            ->assertSee(__('Project Terhapus'))
            ->assertSeeInOrder(['PT Baru Terhapus', 'Project Lama']);
    }

    public function test_hapus_permanen_menghilangkan_data_dari_trash(): void
    {
        $this->actingAs($this->adminUser());

        $customer = Customer::create(['name' => 'PT Akan Dihapus']);
        $customer->delete();

        $this->delete(route('trash.destroy-customer', $customer->id))
            ->assertRedirect(route('trash.index'));

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->get(route('trash.index'))->assertSee(__('Tidak ada customer yang terhapus.'));
    }

    public function test_hapus_permanen_project_menghilangkan_dari_trash(): void
    {
        $this->actingAs($this->adminUser());

        $project = $this->projectWith('Project Akan Dihapus');
        $project->delete();

        $this->delete(route('trash.destroy-project', $project->id))
            ->assertRedirect(route('trash.index'));

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->get(route('trash.index'))->assertSee(__('Tidak ada project yang terhapus.'));
    }

    public function test_hapus_semua_menghapus_history_sekaligus(): void
    {
        $this->actingAs($this->adminUser());

        $customer = Customer::create(['name' => 'PT Satu']);
        $project = $this->projectWith('Project Dua');
        $customer->delete();
        $project->delete();

        $this->delete(route('trash.clear'))
            ->assertRedirect(route('trash.index'));

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->get(route('trash.index'))->assertSee(__('Tidak ada customer yang terhapus.'));
    }

    public function test_trash_membutuhkan_permission_view_trash(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('trash.index'))->assertForbidden();
    }
}
