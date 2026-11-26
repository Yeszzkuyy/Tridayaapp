<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use App\Models\WorkType;
use App\Services\CustomerArchiveService;
use App\Support\CustomerAccess;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    private function customer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'PT Contoh',
            'company' => 'PT Contoh',
            'status' => 'deal',
            'pt_group' => 'NTI',
        ], $attributes));
    }

    private function projectFor(Customer $customer): Project
    {
        return Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => WorkType::create(['name' => 'Instalasi'])->id,
            'project_status_id' => ProjectStatus::create(['name' => 'Open'])->id,
            'pic_engineer' => 'Naufal',
            'project_name' => 'Project '.Str::random(6),
        ]);
    }

    private function invoiceFor(Customer $customer, ?User $creator = null): Invoice
    {
        return Invoice::create([
            'invoice_number' => 'INV-'.Str::uuid(),
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'created_by' => $creator?->id,
        ]);
    }

    public function test_archive_menyimpan_status_tanpa_soft_delete_atau_cascade(): void
    {
        $sales = $this->user('sales-nti');
        $customer = $this->customer(['created_by' => $sales->id]);
        $project = $this->projectFor($customer);

        $this->actingAs($sales)
            ->patch(route('customers.archive', $customer))
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('success');

        $customer->refresh();

        $this->assertNotNull($customer->archived_at);
        $this->assertSame($sales->id, $customer->archived_by);
        $this->assertNull($customer->deleted_at);
        $this->assertNull($project->fresh()->deleted_at);
    }

    public function test_unarchive_mengosongkan_status_arsip(): void
    {
        $sales = $this->user('sales-nti');
        $customer = $this->customer([
            'created_by' => $sales->id,
            'archived_at' => now(),
            'archived_by' => $sales->id,
        ]);

        $this->actingAs($sales)
            ->patch(route('customers.unarchive', $customer))
            ->assertRedirect(route('customers.show', $customer))
            ->assertSessionHas('success');

        $customer->refresh();

        $this->assertNull($customer->archived_at);
        $this->assertNull($customer->archived_by);
    }

    public function test_customer_arsip_hilang_dari_daftar_aktif_tetapi_tetap_bisa_dibuka(): void
    {
        $sales = $this->user('sales-nti');
        $active = $this->customer([
            'name' => 'PT Aktif Terlihat',
            'created_by' => $sales->id,
        ]);
        $archived = $this->customer([
            'name' => 'PT Arsip Disembunyikan',
            'created_by' => $sales->id,
            'archived_at' => now(),
            'archived_by' => $sales->id,
        ]);

        $this->actingAs($sales)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee($active->name)
            ->assertDontSee($archived->name);

        $this->actingAs($sales)
            ->get(route('customers.index', ['status' => 'archived']))
            ->assertOk()
            ->assertSee($archived->name)
            ->assertDontSee($active->name);

        $this->actingAs($sales)
            ->get(route('customers.show', $archived))
            ->assertOk()
            ->assertSee($archived->name);

        $this->assertTrue(
            CustomerAccess::queryFor($sales)->whereKey($archived->id)->exists(),
            'Customer arsip harus tetap tersedia untuk laporan historis.'
        );
        $this->assertFalse(
            CustomerAccess::activeQueryFor($sales)->whereKey($archived->id)->exists(),
            'Customer arsip tidak boleh muncul di selector transaksi baru.'
        );
    }

    public function test_customer_arsip_bersifat_read_only(): void
    {
        $sales = $this->user('sales-nti');
        $archived = $this->customer([
            'created_by' => $sales->id,
            'archived_at' => now(),
            'archived_by' => $sales->id,
        ]);

        $this->actingAs($sales)->get(route('customers.edit', $archived))->assertForbidden();

        $this->actingAs($sales)
            ->put(route('customers.update', $archived), [
                'name' => 'Nama Baru',
                'pt_group' => 'NTI',
            ])
            ->assertForbidden();

        $this->actingAs($sales)
            ->post(route('customer-contacts.store', $archived), [
                'name' => 'PIC Baru',
            ])
            ->assertForbidden();
    }

    public function test_super_admin_tetap_tidak_bisa_mengubah_customer_arsip(): void
    {
        $superAdmin = $this->user('super-admin');
        $archived = $this->customer([
            'created_by' => $superAdmin->id,
            'archived_at' => now(),
            'archived_by' => $superAdmin->id,
        ]);

        $this->actingAs($superAdmin)->get(route('customers.edit', $archived))->assertForbidden();

        $this->actingAs($superAdmin)
            ->put(route('customers.update', $archived), [
                'name' => 'Nama Baru',
                'pt_group' => 'NTI',
            ])
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->post(route('customer-contacts.store', $archived), [
                'name' => 'PIC Baru',
            ])
            ->assertForbidden();
    }

    public function test_upload_dokumen_ke_customer_arsip_ditolak(): void
    {
        Storage::fake('private');

        $admin = $this->user('admin');
        $archived = $this->customer([
            'created_by' => $admin->id,
            'archived_at' => now(),
            'archived_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('customer-documents.store', $archived), [
                'file' => UploadedFile::fake()->create('dokumen.pdf', 10, 'application/pdf'),
            ])
            ->assertForbidden();
    }

    public function test_selector_transaksi_baru_tidak_menampilkan_customer_arsip(): void
    {
        $sales = $this->user('sales-nti');
        $active = $this->customer([
            'name' => 'PT Aktif Selector',
            'created_by' => $sales->id,
        ]);
        $archived = $this->customer([
            'name' => 'PT Arsip Selector',
            'created_by' => $sales->id,
            'archived_at' => now(),
            'archived_by' => $sales->id,
        ]);

        foreach ([
            route('sales.follow-ups.create'),
            route('sales.meetings.create'),
            route('sales.pocs.create'),
            route('sales.price-requests.create'),
        ] as $url) {
            $this->actingAs($sales)
                ->get($url)
                ->assertOk()
                ->assertSee($active->name)
                ->assertDontSee($archived->name);
        }

        $admin = $this->user('admin');

        $this->actingAs($admin)
            ->get(route('admin.invoices.create'))
            ->assertOk()
            ->assertSee($active->name)
            ->assertDontSee($archived->name);
    }

    public function test_project_baru_untuk_customer_arsip_ditolak(): void
    {
        $management = $this->user('management');
        $active = $this->customer(['name' => 'PT Aktif Project']);
        $archived = $this->customer([
            'name' => 'PT Arsip Project',
            'archived_at' => now(),
            'archived_by' => $management->id,
        ]);

        $this->actingAs($management)
            ->get(route('projects.create', ['customer_id' => $active->id]))
            ->assertOk();

        $this->actingAs($management)
            ->get(route('projects.create', ['customer_id' => $archived->id]))
            ->assertForbidden();

        $workType = WorkType::create(['name' => 'Instalasi Baru']);

        $this->actingAs($management)
            ->post(route('projects.store'), [
                'customer_id' => $archived->id,
                'name' => 'Project Baru',
                'work_type_id' => $workType->id,
            ])
            ->assertSessionHasErrors('customer_id');

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_archive_dan_unarchive_menghormati_ownership_dan_pt(): void
    {
        $owner = $this->user('sales-nti');
        $otherSales = $this->user('sales-nti');
        $otherPt = $this->user('sales-mgk');
        $technician = $this->user('technician');

        $customer = $this->customer(['created_by' => $owner->id]);

        $this->actingAs($otherSales)
            ->patch(route('customers.archive', $customer))
            ->assertForbidden();

        $this->actingAs($technician)
            ->patch(route('customers.archive', $customer))
            ->assertForbidden();

        $wrongPt = $this->customer([
            'created_by' => $otherPt->id,
            'pt_group' => 'NTI',
        ]);

        $this->actingAs($otherPt)
            ->patch(route('customers.archive', $wrongPt))
            ->assertForbidden();

        $archived = $this->customer([
            'created_by' => $owner->id,
            'archived_at' => now(),
            'archived_by' => $owner->id,
        ]);

        $this->actingAs($otherSales)
            ->patch(route('customers.unarchive', $archived))
            ->assertForbidden();
    }

    public function test_customer_aktif_dengan_riwayat_transaksi_tidak_bisa_dihapus(): void
    {
        $sales = $this->user('sales-nti');
        $customer = $this->customer(['created_by' => $sales->id]);
        $this->invoiceFor($customer, $sales);

        $this->actingAs($sales)
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('error');

        $this->assertNull($customer->fresh()->deleted_at);
    }

    public function test_customer_tanpa_riwayat_transaksi_tetap_bisa_dihapus(): void
    {
        $sales = $this->user('sales-nti');
        $customer = $this->customer(['created_by' => $sales->id]);

        $this->actingAs($sales)
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_force_delete_customer_dengan_riwayat_transaksi_ditolak(): void
    {
        $sales = $this->user('sales-nti');
        $superAdmin = $this->user('super-admin');
        $customer = $this->customer(['created_by' => $sales->id]);
        $this->invoiceFor($customer, $sales);
        Customer::withoutEvents(fn () => $customer->delete());

        $this->actingAs($superAdmin)
            ->delete(route('trash.destroy-customer', $customer->id))
            ->assertRedirect(route('trash.index'))
            ->assertSessionHas('error');

        $this->assertNotNull(Customer::onlyTrashed()->find($customer->id));
    }

    public function test_force_delete_customer_tanpa_riwayat_transaksi_berhasil(): void
    {
        $sales = $this->user('sales-nti');
        $superAdmin = $this->user('super-admin');
        $customer = $this->customer(['created_by' => $sales->id]);
        $customer->delete();

        $this->actingAs($superAdmin)
            ->delete(route('trash.destroy-customer', $customer->id))
            ->assertRedirect(route('trash.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_bulk_clear_mempertahankan_customer_dengan_riwayat_transaksi(): void
    {
        $sales = $this->user('sales-nti');
        $superAdmin = $this->user('super-admin');
        $unprotected = $this->customer([
            'name' => 'PT Tanpa Riwayat',
            'created_by' => $sales->id,
        ]);
        $protected = $this->customer([
            'name' => 'PT Dengan Riwayat',
            'created_by' => $sales->id,
        ]);
        $this->invoiceFor($protected, $sales);

        $unprotected->delete();
        Customer::withoutEvents(fn () => $protected->delete());

        $this->actingAs($superAdmin)
            ->delete(route('trash.clear'))
            ->assertRedirect(route('trash.index'))
            ->assertSessionHas('warning');

        $this->assertDatabaseMissing('customers', ['id' => $unprotected->id]);
        $this->assertNotNull(Customer::onlyTrashed()->find($protected->id));
    }

    public function test_customer_arsip_tidak_bisa_dihapus_sebelum_di_unarchive(): void
    {
        $sales = $this->user('sales-nti');
        $superAdmin = $this->user('super-admin');
        $archived = $this->customer([
            'created_by' => $sales->id,
            'archived_at' => now(),
            'archived_by' => $sales->id,
        ]);

        $this->assertTrue($archived->isArchived());
        $this->assertFalse($sales->can('delete', $archived));

        $this->actingAs($sales)
            ->delete(route('customers.destroy', $archived))
            ->assertForbidden();

        $this->assertNull($archived->fresh()->deleted_at);

        // Super-admin melewati Gate::before, jadi controller tetap harus
        // menolak delete customer arsip.
        $this->actingAs($superAdmin)
            ->delete(route('customers.destroy', $archived))
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('error');

        $this->assertNull($archived->fresh()->deleted_at);

        Customer::withoutEvents(fn () => $archived->delete());

        $this->actingAs($superAdmin)
            ->delete(route('trash.destroy-customer', $archived->id))
            ->assertRedirect(route('trash.index'))
            ->assertSessionHas('error');

        $this->assertNotNull(Customer::onlyTrashed()->find($archived->id));
    }

    public function test_model_delete_langsung_dengan_riwayat_tetap_ditolak(): void
    {
        $customer = $this->customer();
        $this->invoiceFor($customer);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $customer->delete();
    }

    public function test_model_force_delete_langsung_dengan_riwayat_tetap_ditolak(): void
    {
        $customer = $this->customer();
        $this->invoiceFor($customer);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $customer->forceDelete();
    }

    public function test_daftar_relasi_proteksi_mencakup_semua_riwayat_bisnis(): void
    {
        $reflection = new \ReflectionClass(CustomerArchiveService::class);
        $constant = $reflection->getReflectionConstant('HISTORY_RELATIONS');
        $relations = array_keys($constant->getValue());

        $this->assertEqualsCanonicalizing([
            'invoices',
            'projects',
            'leads',
            'purchaseOrders',
            'goodsDeliveries',
            'estimatorRequests',
            'followUps',
            'pocs',
            'priceRequests',
            'documents',
            'surveys',
            'instalasis',
            'technicianSchedules',
        ], $relations);
    }
}
