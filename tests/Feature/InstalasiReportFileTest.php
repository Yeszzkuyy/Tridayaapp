<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Instalasi;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * File laporan instalasi (PDF/DOC maks 5 MB) di form Tambah/Edit Instalasi —
 * mengikuti pola laporan survey: disk privat, nama asli disimpan, bisa
 * dilihat/diunduh/diganti. Field Catatan (notes) dihapus dari UI.
 */
class InstalasiReportFileTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeInstalasi(array $overrides = []): Instalasi
    {
        // ProjectObserver menulis ProjectActivity dengan auth()->id(); sediakan
        // aktor bila belum ada agar fixture tidak melanggar NOT NULL.
        if (! auth()->check()) {
            $this->actingAs(User::factory()->create());
        }

        $customer = Customer::create(['name' => 'PT Laporan Uji']);
        $workType = WorkType::create(['name' => 'Instalasi']);
        $project = Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Laporan Uji',
        ]);

        return Instalasi::create(array_merge([
            'project_id' => $project->id,
            'location' => 'Jakarta',
            'status' => 'scheduled',
        ], $overrides));
    }

    private function validPayload(Instalasi $instalasi): array
    {
        return [
            'project_id' => $instalasi->project_id,
            'location' => 'Jakarta',
            'status' => 'scheduled',
        ];
    }

    private function pdfFile(string $name = 'laporan-instalasi.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, '%PDF-1.4 laporan instalasi');
    }

    public function test_technician_can_upload_pdf_report_on_create(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $instalasi = $this->makeInstalasi();

        $this->actingAs($technician)->post(route('teknisi.instalasis.store'), array_merge(
            $this->validPayload($instalasi),
            ['report_file' => $this->pdfFile()]
        ))->assertRedirect(route('teknisi.instalasis.index'));

        $created = Instalasi::latest('id')->first();
        $this->assertTrue($created->hasReport());
        $this->assertSame('laporan-instalasi.pdf', $created->report_file_name);
        Storage::disk('private')->assertExists($created->report_file);
    }

    public function test_technician_can_upload_doc_report(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $instalasi = $this->makeInstalasi();

        $this->actingAs($technician)->post(route('teknisi.instalasis.store'), array_merge(
            $this->validPayload($instalasi),
            ['report_file' => UploadedFile::fake()->create('laporan.doc', 10, 'application/msword')]
        ))->assertRedirect(route('teknisi.instalasis.index'));

        $created = Instalasi::latest('id')->first();
        $this->assertTrue($created->hasReport());
        $this->assertStringEndsWith('.doc', $created->report_file);
        Storage::disk('private')->assertExists($created->report_file);
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $instalasi = $this->makeInstalasi();

        $this->actingAs($technician)->from(route('teknisi.instalasis.create'))->post(
            route('teknisi.instalasis.store'),
            array_merge($this->validPayload($instalasi), [
                'report_file' => UploadedFile::fake()->create('salah.txt', 5, 'text/plain'),
            ])
        )->assertSessionHasErrors('report_file');

        // Fixture tetap satu-satunya instalasi — tidak ada record baru yang dibuat.
        $this->assertSame(1, Instalasi::count());
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_report_larger_than_5mb_is_rejected(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $instalasi = $this->makeInstalasi();

        $this->actingAs($technician)->post(
            route('teknisi.instalasis.store'),
            array_merge($this->validPayload($instalasi), [
                'report_file' => UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf'),
            ])
        )->assertSessionHasErrors('report_file');

        $this->assertSame(1, Instalasi::count());
    }

    public function test_update_replaces_report_and_deletes_old_file(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $instalasi = $this->makeInstalasi();

        $this->actingAs($technician)->post(route('teknisi.instalasis.store'), array_merge(
            $this->validPayload($instalasi),
            ['report_file' => $this->pdfFile('awal.pdf')]
        ));

        $created = Instalasi::latest('id')->first();
        $oldPath = $created->report_file;
        Storage::disk('private')->assertExists($oldPath);

        $this->actingAs($technician)->put(route('teknisi.instalasis.update', $created), array_merge(
            $this->validPayload($created),
            ['report_file' => $this->pdfFile('pengganti.pdf')]
        ))->assertRedirect(route('teknisi.instalasis.show', $created));

        $created->refresh();
        $this->assertSame('pengganti.pdf', $created->report_file_name);
        $this->assertNotSame($oldPath, $created->report_file);
        Storage::disk('private')->assertExists($created->report_file);
        Storage::disk('private')->assertMissing($oldPath);
    }

    public function test_view_and_download_report(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $instalasi = $this->makeInstalasi([
            'report_file' => 'instalasis/reports/uji.pdf',
            'report_file_name' => 'laporan uji.pdf',
        ]);
        Storage::disk('private')->put('instalasis/reports/uji.pdf', '%PDF-1.4 isi laporan');

        $view = $this->actingAs($technician)->get(route('teknisi.instalasis.report.view', $instalasi));
        $view->assertOk();
        $this->assertStringContainsString('inline', (string) $view->headers->get('content-disposition'));

        $this->actingAs($technician)
            ->get(route('teknisi.instalasis.report.download', $instalasi))
            ->assertDownload('laporan uji.pdf');
    }

    public function test_report_routes_404_when_no_file(): void
    {
        $technician = $this->userWithRole('technician');
        $instalasi = $this->makeInstalasi();

        $this->actingAs($technician)->get(route('teknisi.instalasis.report.view', $instalasi))->assertNotFound();
        $this->actingAs($technician)->get(route('teknisi.instalasis.report.download', $instalasi))->assertNotFound();
    }

    public function test_destroy_deletes_report_file(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $instalasi = $this->makeInstalasi([
            'report_file' => 'instalasis/reports/hapus.pdf',
            'report_file_name' => 'hapus.pdf',
        ]);
        Storage::disk('private')->put('instalasis/reports/hapus.pdf', '%PDF-1.4');

        $this->actingAs($technician)->delete(route('teknisi.instalasis.destroy', $instalasi))
            ->assertRedirect(route('teknisi.instalasis.index'));

        Storage::disk('private')->assertMissing('instalasis/reports/hapus.pdf');
    }

    public function test_notes_and_text_report_fields_removed_from_ui(): void
    {
        $technician = $this->userWithRole('technician');
        // Data lama (text) tetap aman di database, tapi tidak lagi tampil di UI.
        $instalasi = $this->makeInstalasi([
            'installation_report' => 'Teks laporan lama',
            'notes' => 'Catatan lama',
        ]);

        $create = $this->actingAs($technician)->get(route('teknisi.instalasis.create'))->assertOk()->getContent();
        $this->assertStringNotContainsString('name="notes"', $create);
        $this->assertStringNotContainsString('name="installation_report"', $create);
        $this->assertStringContainsString('name="report_file"', $create);

        $edit = $this->actingAs($technician)->get(route('teknisi.instalasis.edit', $instalasi))->assertOk()->getContent();
        $this->assertStringNotContainsString('name="notes"', $edit);
        $this->assertStringNotContainsString('name="installation_report"', $edit);
        $this->assertStringContainsString('name="report_file"', $edit);

        $show = $this->actingAs($technician)->get(route('teknisi.instalasis.show', $instalasi))->assertOk()->getContent();
        $this->assertStringNotContainsString('Catatan', $show);
        $this->assertStringContainsString('Teks laporan lama', $show);
    }

    public function test_show_displays_report_actions_with_file_name(): void
    {
        Storage::fake('private');
        $technician = $this->userWithRole('technician');
        $instalasi = $this->makeInstalasi([
            'report_file' => 'instalasis/reports/tampil.pdf',
            'report_file_name' => 'laporan-final.pdf',
        ]);
        Storage::disk('private')->put('instalasis/reports/tampil.pdf', '%PDF-1.4');

        $show = $this->actingAs($technician)->get(route('teknisi.instalasis.show', $instalasi))->assertOk()->getContent();

        $this->assertStringContainsString(route('teknisi.instalasis.report.view', $instalasi), $show);
        $this->assertStringContainsString(route('teknisi.instalasis.report.download', $instalasi), $show);
        $this->assertStringContainsString('laporan-final.pdf', $show);
    }
}
