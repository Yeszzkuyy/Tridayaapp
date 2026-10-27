<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectStatus;
use App\Models\ProjectTask;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProjectCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['name' => 'Admin Kartu']);
        $user->assignRole('super-admin');

        return $user;
    }

    public function test_kartu_project_menampilkan_pic_engineer_dan_ringkasan_progres(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin); // Project::create mencatat activity butuh user login
        $customer = Customer::create(['name' => 'PT Kartu Project']);
        $workType = WorkType::create(['name' => 'Instalasi Jaringan']);
        $status = ProjectStatus::create(['name' => 'On Progress', 'color' => 'yellow']);

        $project = Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_status_id' => $status->id,
            'project_name' => 'Pemasangan Jaringan Gudang',
            'pic_engineer' => 'Budi Santoso',
            'description' => 'Instalasi kabel dan akses point di gudang utama.',
            'progress' => 65,
        ]);

        // 1 dari 2 task selesai -> ringkasan progres kerja.
        ProjectTask::forceCreate([
            'project_id' => $project->id,
            'assigned_to' => $admin->id,
            'title' => 'Tarik kabel',
            'status' => 'Done',
        ]);
        ProjectTask::forceCreate([
            'project_id' => $project->id,
            'assigned_to' => $admin->id,
            'title' => 'Pasang akses point',
            'status' => 'Open',
        ]);

        ProjectDocument::create([
            'project_id' => $project->id,
            'customer_id' => $customer->id,
            'file_name' => 'boq.pdf',
            'file_path' => 'private/boq.pdf',
            'file_size' => 1024,
            'uploaded_by' => $admin->id,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->getContent();

        // Grid kartu (3 kolom di layar lebar), bukan tabel lagi.
        $this->assertStringContainsString('xl:grid-cols-3', $html);

        // Identitas project.
        $this->assertStringContainsString('Pemasangan Jaringan Gudang', $html);
        $this->assertStringContainsString('Instalasi Jaringan', $html);
        $this->assertStringContainsString('On Progress', $html);

        // Permintaan utama: PIC Engineer tampil di kartu.
        $this->assertStringContainsString('Budi Santoso', $html);
        $this->assertStringContainsString('PIC Engineer', $html);

        // Deskripsi + ringkasan progres kerja.
        $this->assertStringContainsString('Instalasi kabel dan akses point di gudang utama.', $html);
        $this->assertStringContainsString('1/2 Tasks', $html);
        $this->assertStringContainsString('1 Documents', $html);

        // Aksi tetap lengkap, sekarang icon-only (bukan link teks lama).
        $this->assertStringContainsString(route('projects.show', $project), $html);
        $this->assertStringContainsString(route('projects.edit', $project), $html);
        $this->assertStringContainsString(route('projects.destroy', $project), $html);
        // Link teks lama "Detail" sudah diganti tombol ikon.
        $this->assertStringNotContainsString('>Detail</a>', $html);
    }

    public function test_kartu_project_tanpa_pic_dan_relasi_tetap_tampil(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $customer = Customer::create(['name' => 'PT Tanpa PIC']);
        $workType = WorkType::create(['name' => 'Service']);

        Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Baru',
        ]);

        $response = $this->actingAs($admin)->get(route('customers.show', $customer));

        $response->assertOk();
        $html = $response->getContent();

        // Status kosong jatuh ke "Not Started", PIC kosong tetap ada barisnya.
        $this->assertStringContainsString('Project Baru', $html);
        $this->assertStringContainsString('Not Started', $html);
        $this->assertStringContainsString('PIC Engineer', $html);
        $this->assertStringContainsString('0/0 Tasks', $html);
        $this->assertStringContainsString('0 Documents', $html);
    }
}
