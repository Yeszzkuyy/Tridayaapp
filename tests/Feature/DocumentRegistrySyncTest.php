<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Instalasi;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\SizingProject;
use App\Models\Survey;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Satu file = satu dokumen: file yang diunggah lewat menu modul (Survey,
 * Sizing, Instalasi) harus ikut tampil di SEMUA menu dokumen dan tidak boleh
 * menduplikasi dirinya sendiri.
 */
class DocumentRegistrySyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeProject(): Project
    {
        if (! auth()->check()) {
            $this->actingAs(User::factory()->create());
        }

        $customer = Customer::create(['name' => 'PT Sinkron ' . uniqid()]);
        $workType = WorkType::create(['name' => 'Instalasi']);

        return Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Sinkron',
        ]);
    }

    public function test_laporan_survey_jadi_dokumen_di_semua_menu(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $project = $this->makeProject();
        $survey = Survey::create([
            'title' => 'Survey Sinkron',
            'project_id' => $project->id,
            'status' => 'scheduled',
        ]);

        $this->actingAs($technician)->post(route('teknisi.surveys.report.upload', $survey), [
            'report' => UploadedFile::fake()->create('laporan-survey.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $document = ProjectDocument::where('doc_type', 'survey')->first();
        $this->assertNotNull($document, 'Laporan survey harus tercatat sebagai dokumen');
        $this->assertSame($survey->id, (int) $document->documentable_id);
        $this->assertSame($project->id, (int) $document->project_id);
        $this->assertSame($project->customer_id, (int) $document->customer_id);

        $html = $this->actingAs($technician)->get(route('customers.show', $project->customer_id))
            ->assertOk()->getContent();
        $this->assertStringContainsString('laporan-survey.pdf', $html, 'Tidak tampil di Customer > Documents');

        $html = $this->get(route('project-documents.index', $project))->assertOk()->getContent();
        $this->assertStringContainsString('laporan-survey.pdf', $html, 'Tidak tampil di Project Documents');

        $html = $this->get(route('teknisi.documents.index'))->assertOk()->getContent();
        $this->assertStringContainsString('laporan-survey.pdf', $html, 'Tidak tampil di Document Repository');
    }

    public function test_ganti_laporan_tidak_menduplikasi_dokumen(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $project = $this->makeProject();
        $survey = Survey::create([
            'title' => 'Survey Ganti Laporan',
            'project_id' => $project->id,
            'status' => 'scheduled',
        ]);

        $this->actingAs($technician);
        foreach (['versi-1.pdf', 'versi-2.pdf'] as $name) {
            $this->post(route('teknisi.surveys.report.upload', $survey), [
                'report' => UploadedFile::fake()->create($name, 100, 'application/pdf'),
            ])->assertRedirect();
        }

        $this->assertSame(1, ProjectDocument::count(), 'Ganti laporan tidak boleh menambah dokumen');
        $this->assertSame('versi-2.pdf', ProjectDocument::first()->file_name);
    }

    public function test_hapus_survey_menghapus_dokumennya(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $project = $this->makeProject();
        $survey = Survey::create([
            'title' => 'Survey Dihapus',
            'project_id' => $project->id,
            'status' => 'scheduled',
        ]);

        $this->actingAs($technician)->post(route('teknisi.surveys.report.upload', $survey), [
            'report' => UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertSame(1, ProjectDocument::count());

        $this->delete(route('teknisi.surveys.destroy', $survey))->assertRedirect();

        $this->assertSame(0, ProjectDocument::count(), 'Dokumen survey harus ikut terhapus');
    }

    public function test_laporan_instalasi_jadi_dokumen(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $project = $this->makeProject();

        $this->actingAs($technician)->post(route('teknisi.instalasis.store'), [
            'project_id' => $project->id,
            'status' => 'completed',
            'report_file' => UploadedFile::fake()->create('bast.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $document = ProjectDocument::where('doc_type', 'instalasi')->first();
        $this->assertNotNull($document, 'Laporan instalasi harus tercatat sebagai dokumen');
        $this->assertSame('bast.pdf', $document->file_name);

        $html = $this->get(route('customers.show', $project->customer_id))->assertOk()->getContent();
        $this->assertStringContainsString('bast.pdf', $html);
    }

    public function test_lampiran_sizing_jadi_dokumen(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $project = $this->makeProject();

        $this->actingAs($technician)->post(route('teknisi.sizing-projects.store'), [
            'project_id' => $project->id,
            'status' => 'completed',
            'attachments' => [
                UploadedFile::fake()->create('hasil-sizing.pdf', 100, 'application/pdf'),
            ],
        ])->assertRedirect();

        $document = ProjectDocument::where('doc_type', 'sizing')->first();
        $this->assertNotNull($document, 'Lampiran sizing harus tercatat sebagai dokumen');
        $this->assertSame('hasil-sizing.pdf', $document->file_name);

        $html = $this->get(route('customers.show', $project->customer_id))->assertOk()->getContent();
        $this->assertStringContainsString('hasil-sizing.pdf', $html);
    }

    public function test_perintah_sync_mendaftarkan_file_lama_tanpa_duplikat(): void
    {
        Storage::fake('private');
        $project = $this->makeProject();

        // File lama: laporan survey sudah ada di disk + tabel survey,
        // tapi belum punya baris dokumen.
        Storage::disk('private')->put('surveys/9/reports/lama.pdf', 'isi-laporan');
        $survey = Survey::create([
            'title' => 'Survey Lama',
            'project_id' => $project->id,
            'report_file' => 'surveys/9/reports/lama.pdf',
            'report_file_name' => 'laporan-lama.pdf',
            'status' => 'completed',
        ]);

        // Dokumen yang sama kebetulan sudah diunggah manual lewat menu
        // Documents (nama + ukuran sama) — harus digabung, bukan dobel.
        ProjectDocument::create([
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'file_name' => 'laporan-lama.pdf',
            'file_path' => 'documents/1/manual.pdf',
            'file_size' => strlen('isi-laporan'),
            'uploaded_by' => null,
        ]);

        $this->artisan('documents:sync-modules')->assertSuccessful();

        $this->assertSame(1, ProjectDocument::count(), 'File yang sama tidak boleh jadi dua dokumen');
        $document = ProjectDocument::first();
        $this->assertSame($survey->id, (int) $document->documentable_id);
        $this->assertSame('surveys/9/reports/lama.pdf', $document->file_path);
    }

    public function test_dokumen_modul_hanya_bisa_dikelola_dari_menu_modulnya(): void
    {
        Storage::fake('private');
        $admin = $this->userWithRole('super-admin');
        $project = $this->makeProject();

        $document = ProjectDocument::create([
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'doc_type' => 'survey',
            'documentable_type' => Survey::class,
            'documentable_id' => 1,
            'file_name' => 'laporan.pdf',
            'file_path' => 'surveys/1/reports/laporan.pdf',
            'file_size' => 10,
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin);

        $this->patch(route('project-documents.rename', $document), ['name' => 'Ubah Nama'])
            ->assertForbidden();
        $this->post(route('project-documents.archive', $document))->assertForbidden();
        $this->delete(route('project-documents.destroy', $document))->assertForbidden();

        $this->assertNotNull($document->fresh());
        $this->assertSame('laporan.pdf', $document->fresh()->file_name);
    }

    public function test_dokumen_transaksi_tidak_ikut_masuk_daftar_dokumen_proyek(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $project = $this->makeProject();

        ProjectDocument::create([
            'customer_id' => $project->customer_id,
            'doc_type' => 'invoice',
            'file_name' => 'faktur.pdf',
            'file_path' => 'documents/customer-' . $project->customer_id . '/faktur.pdf',
            'file_size' => 10,
            'uploaded_by' => $technician->id,
        ]);

        $this->actingAs($technician);

        $html = $this->get(route('project-documents.index', $project))->assertOk()->getContent();
        $this->assertStringNotContainsString('faktur.pdf', $html, 'Dokumen transaksi bukan dokumen proyek');

        $html = $this->get(route('teknisi.documents.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('faktur.pdf', $html, 'Repository teknis tidak menampilkan dokumen admin');
    }
}
