<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DocumentActivity;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Survey;
use App\Models\User;
use App\Models\WorkType;
use App\Services\DocumentRegistry;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Matriks akses Trash dokumen: teknisi/lead, management (read-only),
 * sales (per PT), plus alur arsip/restore dokumen modul.
 */
class DocumentTrashAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('private');
    }

    private function userWithRole(string $role, ?string $extraRole = null): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        if ($extraRole) {
            $user->assignRole($extraRole);
        }

        return $user;
    }

    private function makeProject(string $pt = 'NTI', array $extra = []): Project
    {
        $customer = Customer::create([
            'name' => 'PT Akses ' . uniqid(),
            'pt_group' => $pt,
        ]);
        $workType = WorkType::create(['name' => 'Instalasi ' . uniqid()]);

        return Project::create(array_merge([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Akses',
        ], $extra));
    }

    private function makeDocument(Project $project, array $extra = []): ProjectDocument
    {
        $path = $extra['file_path'] ?? 'documents/project-'.$project->id.'/'.uniqid().'.pdf';
        Storage::disk('private')->put($path, 'isi-dokumen');

        return ProjectDocument::create(array_merge([
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'file_name' => 'dokumen.pdf',
            'file_path' => $path,
            'file_size' => 12,
            'uploaded_by' => auth()->id(),
        ], $extra));
    }

    private function makeModuleDocument(Project $project): array
    {
        $path = 'surveys/'.uniqid().'/reports/laporan.pdf';
        Storage::disk('private')->put($path, 'isi-laporan');

        $survey = Survey::create([
            'title' => 'Survey Akses',
            'project_id' => $project->id,
            'report_file' => $path,
            'report_file_name' => 'laporan.pdf',
            'status' => 'completed',
        ]);

        $document = $this->makeDocument($project, [
            'doc_type' => 'survey',
            'documentable_type' => Survey::class,
            'documentable_id' => $survey->id,
            'file_name' => 'laporan.pdf',
            'file_path' => $path,
        ]);

        return [$survey, $document];
    }

    public function test_lead_technician_bisa_arsip_dan_restore_dokumen_modul(): void
    {
        $lead = $this->userWithRole('lead-technician');
        $this->actingAs($lead);

        $project = $this->makeProject();
        [$survey, $document] = $this->makeModuleDocument($project);

        $this->post(route('project-documents.archive', $document))->assertRedirect();

        $this->assertSoftDeleted($document);
        $this->assertNull($survey->fresh()->report_file);
        $this->assertSame(
            $lead->id,
            ProjectDocument::withTrashed()->findOrFail($document->id)->deleted_by
        );

        $this->assertDatabaseHas('document_activities', [
            'document_id' => $document->id,
            'user_id' => $lead->id,
            'action' => 'archived',
        ]);

        $this->patch(route('trash.restore-document', $document->id))->assertRedirect();

        $this->assertNotSoftDeleted($document);
        $this->assertSame($survey->fresh()->report_file, $document->file_path);
        $this->assertSame(1, ProjectDocument::count(), 'Restore tidak boleh menduplikasi dokumen');

        $this->assertDatabaseHas('document_activities', [
            'document_id' => $document->id,
            'user_id' => $lead->id,
            'action' => 'restored',
        ]);
    }

    public function test_management_hanya_bisa_melihat_tidak_bisa_hapus_restore_atau_kosongkan(): void
    {
        $admin = $this->userWithRole('super-admin');
        $this->actingAs($admin);

        $project = $this->makeProject();
        $document = $this->makeDocument($project, ['file_name' => 'terlihat-management.pdf']);
        $active = $this->makeDocument($project, ['file_name' => 'masih-aktif.pdf']);

        $this->post(route('project-documents.archive', $document))->assertRedirect();
        $documentId = $document->id;

        $management = $this->userWithRole('management');

        $this->actingAs($management)
            ->get(route('trash.index'))
            ->assertOk()
            ->assertSee('terlihat-management.pdf');

        $this->actingAs($management)
            ->post(route('project-documents.archive', $active))
            ->assertForbidden();

        $this->actingAs($management)
            ->patch(route('trash.restore-document', $documentId))
            ->assertForbidden();

        $this->actingAs($management)
            ->delete(route('trash.destroy-document', $documentId))
            ->assertForbidden();

        // Empty Trash tidak boleh menghapus dokumen yang tidak boleh dikelola.
        $this->actingAs($management)
            ->delete(route('trash.clear'))
            ->assertRedirect();

        $this->assertNotNull(ProjectDocument::withTrashed()->find($documentId));
    }

    public function test_sales_hanya_bisa_arsip_dokumen_pt_sendiri_dan_tidak_bisa_dokumen_modul(): void
    {
        $salesNti = $this->userWithRole('sales', 'sales-nti');
        $salesMgk = $this->userWithRole('sales', 'sales-mgk');

        $this->actingAs($salesNti);
        $project = $this->makeProject('NTI');

        $regular = $this->makeDocument($project, ['file_name' => 'nib-nti.pdf']);
        $module = $this->makeModuleDocument($project)[1];

        $this->actingAs($salesNti)
            ->post(route('project-documents.archive', $regular))
            ->assertRedirect();
        $this->assertSoftDeleted($regular);

        $this->actingAs($salesNti)
            ->post(route('project-documents.archive', $module))
            ->assertForbidden();

        $other = $this->makeDocument($project, ['file_name' => 'milik-nti.pdf']);
        $this->actingAs($salesMgk)
            ->post(route('project-documents.archive', $other))
            ->assertForbidden();

        $this->assertNotSoftDeleted($other);
    }

    public function test_restore_modul_ditolak_bila_modul_sudah_punya_file_lain(): void
    {
        $lead = $this->userWithRole('lead-technician');
        $this->actingAs($lead);

        $project = $this->makeProject();
        [$survey, $document] = $this->makeModuleDocument($project);

        $this->post(route('project-documents.archive', $document))->assertRedirect();
        $this->assertSoftDeleted($document);

        // Modul sudah dipakai file lain sebelum dokumen lama dipulihkan.
        $survey->forceFill(['report_file' => 'surveys/baru/reports/baru.pdf'])->save();

        $this->patch(route('trash.restore-document', $document->id))
            ->assertRedirect()
            ->assertSessionHasErrors('document');

        $this->assertSoftDeleted($document);
        $this->assertSame('surveys/baru/reports/baru.pdf', $survey->fresh()->report_file);
    }

    public function test_sync_tidak_membangkitkan_dokumen_yang_sudah_di_trash(): void
    {
        $lead = $this->userWithRole('lead-technician');
        $this->actingAs($lead);

        $project = $this->makeProject();
        [$survey, $document] = $this->makeModuleDocument($project);

        $this->post(route('project-documents.archive', $document))->assertRedirect();
        $this->assertSoftDeleted($document);

        DocumentRegistry::sync($survey->fresh());

        $this->assertSoftDeleted($document);
        $this->assertSame(0, ProjectDocument::count(), 'Sync tidak boleh membangkitkan dokumen di Trash');
        $this->assertSame(1, ProjectDocument::withTrashed()->count());
    }

    public function test_purge_mencatat_aktivitas_dan_tidak_membangkitkan_dokumen_modul(): void
    {
        $lead = $this->userWithRole('lead-technician');
        $this->actingAs($lead);

        $project = $this->makeProject();
        [$survey, $document] = $this->makeModuleDocument($project);

        $this->post(route('project-documents.archive', $document))->assertRedirect();

        DB::table('project_documents')->where('id', $document->id)->update([
            'deleted_at' => now()->subDays(15),
        ]);

        $this->artisan('documents:purge-trash')->assertSuccessful();

        $this->assertNull(ProjectDocument::withTrashed()->find($document->id));
        $this->assertNull($survey->fresh()->report_file);

        $this->assertDatabaseHas('document_activities', [
            'document_id' => $document->id,
            'action' => 'purged',
            'file_name' => 'laporan.pdf',
        ]);
    }

    public function test_teknisi_melihat_semua_dokumen_teknis_tapi_bukan_dokumen_transaksi(): void
    {
        $admin = $this->userWithRole('super-admin');
        $this->actingAs($admin);

        $project = $this->makeProject();
        $technical = $this->makeDocument($project, ['file_name' => 'teknis.pdf']);
        $transaction = $this->makeDocument($project, [
            'file_name' => 'invoice.pdf',
            'doc_type' => 'invoice',
        ]);

        $this->post(route('project-documents.archive', $technical))->assertRedirect();
        $this->post(route('project-documents.archive', $transaction))->assertRedirect();

        $technician = $this->userWithRole('technician');

        $this->actingAs($technician)
            ->get(route('trash.index'))
            ->assertOk()
            ->assertSee('teknis.pdf')
            ->assertDontSee('invoice.pdf');
    }

    public function test_arsip_dokumen_menampilkan_dokumen_di_trash_dan_tombol_sesuai_role(): void
    {
        $admin = $this->userWithRole('super-admin');
        $this->actingAs($admin);

        $project = $this->makeProject();
        $document = $this->makeDocument($project, ['file_name' => 'arsip-publik.pdf']);
        $this->post(route('project-documents.archive', $document))->assertRedirect();

        $management = $this->userWithRole('management');
        $this->actingAs($management)
            ->get(route('customer-documents.archive', $project->customer_id))
            ->assertOk()
            ->assertSee('arsip-publik.pdf')
            ->assertDontSee('data-doc-id="' . $document->id . '"', false);

        // File di Trash tetap bisa diunduh (read-only) oleh management.
        $this->actingAs($management)
            ->get(route('project-documents.download', $document))
            ->assertOk();

        $lead = $this->userWithRole('lead-technician');
        $this->actingAs($lead)
            ->get(route('customer-documents.archive', $project->customer_id))
            ->assertOk()
            ->assertSee('arsip-publik.pdf')
            ->assertSee('data-doc-id="' . $document->id . '"', false);
    }

    public function test_aksi_hapus_dan_restore_tercatat_dengan_aktor_dan_waktu(): void
    {
        $admin = $this->userWithRole('super-admin');
        $this->actingAs($admin);

        $project = $this->makeProject();
        $document = $this->makeDocument($project);

        $this->post(route('project-documents.archive', $document))->assertRedirect();
        $this->patch(route('trash.restore-document', $document->id))->assertRedirect();

        $activities = DocumentActivity::where('document_id', $document->id)->orderBy('id')->get();

        $this->assertSame(['archived', 'restored'], $activities->pluck('action')->all());
        $this->assertSame([$admin->id, $admin->id], $activities->pluck('user_id')->all());
        $this->assertNotNull($activities->first()->created_at);
    }
}
