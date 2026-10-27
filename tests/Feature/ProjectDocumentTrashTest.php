<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProjectDocument;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Alur hapus dokumen: Delete = pindah ke Trash (soft delete), bukan langsung
 * permanen. Hapus permanen dilakukan dari menu Trash dan otomatis setelah
 * 14 hari (dihitung dari deleted_at).
 */
class ProjectDocumentTrashTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('private');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeCustomer(string $name = 'PT Trash Dokumen'): Customer
    {
        return Customer::create(['name' => $name . ' ' . uniqid(), 'pt_group' => 'NTI']);
    }

    private function makeDocument(Customer $customer, string $name = 'dokumen.pdf', array $extra = []): ProjectDocument
    {
        $path = 'documents/customer-'.$customer->id.'/'.$name;
        Storage::disk('private')->put($path, 'isi-dokumen');

        return ProjectDocument::create(array_merge([
            'customer_id' => $customer->id,
            'file_name' => $name,
            'file_path' => $path,
            'file_size' => 12,
            'uploaded_by' => auth()->id(),
        ], $extra));
    }

    private function archiveDocument(ProjectDocument $document): void
    {
        $this->post(route('project-documents.archive', $document))->assertRedirect();
    }

    public function test_dokumen_yang_dihapus_masuk_trash_dan_tampil_di_menu_trash(): void
    {
        $admin = $this->userWithRole('super-admin');
        $this->actingAs($admin);

        $customer = $this->makeCustomer();
        $document = $this->makeDocument($customer, 'nib-perusahaan.pdf');

        $this->archiveDocument($document);

        $this->assertSoftDeleted($document);
        Storage::disk('private')->assertExists($document->file_path);

        $deleted = ProjectDocument::withTrashed()->findOrFail($document->id);
        $this->assertSame($admin->id, $deleted->deleted_by);

        $html = $this->get(route('trash.index'))->assertOk()->getContent();
        $this->assertStringContainsString('nib-perusahaan.pdf', $html);
        $this->assertStringContainsString($admin->name, $html);
        $this->assertStringContainsString(
            $deleted->trashDeadline()->format('d M Y H:i'),
            $html,
            'Batas hapus otomatis (deleted_at + 14 hari) harus tampil'
        );
    }

    public function test_dokumen_di_trash_hanya_terlihat_oleh_sales_di_pt_yang_sama(): void
    {
        $sales = $this->userWithRole('sales');
        $sales->assignRole('sales-nti');
        $otherSales = $this->userWithRole('sales');
        $otherSales->assignRole('sales-mgk');

        $this->actingAs($sales);
        $customer = $this->makeCustomer();
        $document = $this->makeDocument($customer);
        $this->archiveDocument($document);

        $this->get(route('trash.index'))
            ->assertOk()
            ->assertSee($document->file_name);

        $this->actingAs($otherSales)
            ->get(route('trash.index'))
            ->assertOk()
            ->assertDontSee($document->file_name);
    }

    public function test_restore_dokumen_dari_trash(): void
    {
        $admin = $this->userWithRole('super-admin');
        $this->actingAs($admin);

        $customer = $this->makeCustomer();
        $document = $this->makeDocument($customer);
        $this->archiveDocument($document);

        $this->patch(route('trash.restore-document', $document->id))->assertRedirect();

        $this->assertNotSoftDeleted($document);
        $this->assertNull(ProjectDocument::findOrFail($document->id)->deleted_by);
    }

    public function test_hapus_permanen_dokumen_dari_trash_menghapus_file(): void
    {
        $admin = $this->userWithRole('super-admin');
        $this->actingAs($admin);

        $customer = $this->makeCustomer();
        $document = $this->makeDocument($customer);
        $path = $document->file_path;
        $this->archiveDocument($document);

        $this->delete(route('trash.destroy-document', $document->id))->assertRedirect();

        $this->assertNull(ProjectDocument::withTrashed()->find($document->id));
        Storage::disk('private')->assertMissing($path);
    }

    public function test_purge_otomatis_hanya_menghapus_dokumen_lewat_14_hari(): void
    {
        $this->actingAs($this->userWithRole('super-admin'));
        $customer = $this->makeCustomer();

        $expired = $this->makeDocument($customer, 'expired.pdf');
        $fresh = $this->makeDocument($customer, 'fresh.pdf');
        $this->archiveDocument($expired);
        $this->archiveDocument($fresh);

        // deleted_at diatur manual: 14 hari + 1 menit (sudah lewat) dan
        // 13 hari (belum lewat). Batas dihitung dari deleted_at.
        DB::table('project_documents')->where('id', $expired->id)->update([
            'deleted_at' => now()->subDays(14)->subMinute(),
        ]);
        DB::table('project_documents')->where('id', $fresh->id)->update([
            'deleted_at' => now()->subDays(13),
        ]);

        $this->artisan('documents:purge-trash')->assertSuccessful();

        $this->assertNull(ProjectDocument::withTrashed()->find($expired->id));
        Storage::disk('private')->assertMissing($expired->file_path);

        $this->assertNotNull(ProjectDocument::withTrashed()->find($fresh->id));
        Storage::disk('private')->assertExists($fresh->file_path);
    }

    public function test_kosongkan_trash_ikut_menghapus_file_dokumen(): void
    {
        $admin = $this->userWithRole('super-admin');
        $this->actingAs($admin);

        $customer = $this->makeCustomer();
        $document = $this->makeDocument($customer);
        $path = $document->file_path;
        $this->archiveDocument($document);

        $this->delete(route('trash.clear'))->assertRedirect();

        $this->assertNull(ProjectDocument::withTrashed()->find($document->id));
        Storage::disk('private')->assertMissing($path);
    }

    public function test_hapus_customer_dari_trash_ikut_menghapus_file_dokumen(): void
    {
        $admin = $this->userWithRole('super-admin');
        $this->actingAs($admin);

        $customer = $this->makeCustomer();
        $document = $this->makeDocument($customer);
        $path = $document->file_path;

        $customer->delete();

        $this->delete(route('trash.destroy-customer', $customer->id))->assertRedirect();

        $this->assertNull(ProjectDocument::withTrashed()->find($document->id));
        Storage::disk('private')->assertMissing($path);
    }

    public function test_kartu_dokumen_memakai_aksi_arsip_bukan_hapus_permanen(): void
    {
        $admin = $this->userWithRole('super-admin');
        $this->actingAs($admin);

        $customer = $this->makeCustomer();
        $document = $this->makeDocument($customer);

        $html = $this->get(route('customers.show', $customer))->assertOk()->getContent();

        $this->assertStringContainsString(
            'action="' . route('project-documents.archive', $document) . '"',
            $html,
            'Tombol Delete dokumen biasa harus mengarah ke arsip/Trash'
        );
    }

    public function test_dokumen_modul_memakai_aksi_hapus_ke_trash_bukan_hapus_permanen(): void
    {
        $admin = $this->userWithRole('super-admin');
        $this->actingAs($admin);

        $customer = $this->makeCustomer();
        $moduleDocument = $this->makeDocument($customer, 'laporan-survey.pdf', [
            'doc_type' => 'survey',
            'documentable_type' => \App\Models\Survey::class,
            'documentable_id' => 99,
        ]);

        $html = $this->get(route('customers.show', $customer))->assertOk()->getContent();
        $this->assertStringContainsString(
            'action="' . route('project-documents.archive', $moduleDocument) . '"',
            $html,
            'Dokumen modul juga harus lewat Trash'
        );
        $this->assertStringNotContainsString(
            'action="' . route('project-documents.destroy', $moduleDocument) . '"',
            $html,
            'Tidak boleh ada tombol hapus permanen di halaman dokumen'
        );
        $this->assertStringNotContainsString('Delete Permanently', $html);
    }
}
