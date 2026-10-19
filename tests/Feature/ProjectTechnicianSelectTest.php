<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTechnicianSelectTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo('manage-technician');

        return $user;
    }

    public function test_store_and_edit_use_technician_selection(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager);

        $a = User::factory()->create(['name' => 'Teknisi Satu']);
        $a->assignRole('technician');
        $b = User::factory()->create(['name' => 'Teknisi Dua']);
        $b->assignRole('technician');

        // PIC & support dipilih via select (nilai = nama user), support berupa array
        $this->post(route('projects.store'), [
            'customer_id' => \App\Models\Customer::create(['name' => 'PT X'])->id,
            'name' => 'Project Uji Pilih Teknisi',
            'work_type_id' => \App\Models\WorkType::create(['name' => 'Instalasi'])->id,
            'pic_engineer' => 'Teknisi Satu',
            'support_technicians' => ['Teknisi Dua'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $project = \App\Models\Project::where('project_name', 'Project Uji Pilih Teknisi')->firstOrFail();
        $this->assertSame('Teknisi Satu', $project->pic_engineer);
        $this->assertSame('Teknisi Dua', $project->support_technicians);

        // Form edit menampilkan dropdown berisi daftar teknisi
        $this->get(route('projects.edit', $project))
            ->assertOk()
            ->assertSee(__('- Pilih Teknisi -'))
            ->assertSee('Teknisi Satu')
            ->assertSee('Teknisi Dua');

        // Update dengan beberapa support sekaligus + ganti status project
        $done = \App\Models\ProjectStatus::create(['name' => 'Done', 'color' => 'green', 'sort_order' => 2]);

        $this->put(route('projects.update', $project), [
            'project_name' => 'Project Uji Pilih Teknisi',
            'customer_id' => $project->customer_id,
            'work_type_id' => $project->work_type_id,
            'pic_engineer' => 'Teknisi Dua',
            'support_technicians' => ['Teknisi Satu', 'Teknisi Dua'],
            'project_status_id' => $done->id,
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame('Teknisi Satu, Teknisi Dua', $project->support_technicians);
        $this->assertSame($done->id, $project->project_status_id, 'Status project harus ikut tersimpan saat update.');

        // Project berstatus non-aktif tidak boleh hilang dari daftar
        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Project Uji Pilih Teknisi');
    }
}
