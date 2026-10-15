<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use App\Models\WorkType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrashPerUserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Budi & Andi = sales biasa TANPA manage-admin (skenario nyata).
     * @return array{0:User,1:User,2:User} [Budi, Andi, super-admin]
     */
    private function setUpUsers(): array
    {
        Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);

        $budi = User::factory()->create(['name' => 'Budi']);
        $andi = User::factory()->create(['name' => 'Andi']);
        $sa = User::factory()->create(['name' => 'Super']);

        $budi->assignRole('sales');
        $andi->assignRole('sales');
        $sa->assignRole('super-admin');

        return [$budi, $andi, $sa];
    }

    private function ownedCustomer(User $owner, string $name): Customer
    {
        $customer = Customer::create(['name' => $name]);
        Lead::create(['customer_id' => $customer->id, 'assigned_to' => $owner->id]);

        return $customer;
    }

    private function ownedProject(User $owner, Customer $customer, string $name): Project
    {
        return Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => WorkType::create(['name' => 'Instalasi'])->id,
            'project_status_id' => ProjectStatus::create(['name' => 'Open'])->id,
            'pic_engineer' => 'Teknisi',
            'project_name' => $name,
        ]);
    }

    private function deleteCustomer(User $by, Customer $customer): void
    {
        $this->actingAs($by)->delete('/customers/'.$customer->id);
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_owner_sees_only_own_trash()
    {
        [$budi, $andi] = $this->setUpUsers();

        $this->deleteCustomer($budi, $this->ownedCustomer($budi, 'Milik Budi'));
        $this->deleteCustomer($andi, $this->ownedCustomer($andi, 'Milik Andi'));

        $htmlBudi = $this->actingAs($budi)->get('/trash')->assertOk()->getContent();
        $this->assertStringContainsString('My Trash', $htmlBudi);
        $this->assertStringNotContainsString('All Trash', $htmlBudi);
        $this->assertStringContainsString('Milik Budi', $htmlBudi);
        $this->assertStringNotContainsString('Milik Andi', $htmlBudi);

        $htmlAndi = $this->actingAs($andi)->get('/trash')->assertOk()->getContent();
        $this->assertStringContainsString('Milik Andi', $htmlAndi);
        $this->assertStringNotContainsString('Milik Budi', $htmlAndi);
    }

    public function test_deleter_is_not_owner()
    {
        [$budi, $andi] = $this->setUpUsers();

        $customer = $this->ownedCustomer($budi, 'Punya Budi');
        // Andi yang menghapus, Budi yang punya.
        $this->deleteCustomer($andi, $customer);
        $this->assertEquals($andi->id, $customer->fresh()->deleted_by);

        // Budi melihat, Andi tidak.
        $this->assertStringContainsString('Punya Budi', $this->actingAs($budi)->get('/trash')->getContent());
        $this->assertStringNotContainsString('Punya Budi', $this->actingAs($andi)->get('/trash')->getContent());

        // Andi tidak bisa restore / hapus permanen -> 404, data utuh.
        $this->actingAs($andi)->patch('/trash/customers/'.$customer->id.'/restore')->assertNotFound();
        $this->actingAs($andi)->delete('/trash/customers/'.$customer->id.'/delete')->assertNotFound();
        $this->assertNotNull(Customer::onlyTrashed()->find($customer->id));
    }

    public function test_owner_can_restore_and_delete_own()
    {
        [$budi] = $this->setUpUsers();

        $customer = $this->ownedCustomer($budi, 'Milik Budi');
        $this->deleteCustomer($budi, $customer);

        $this->actingAs($budi)->patch('/trash/customers/'.$customer->id.'/restore')->assertRedirect();
        $this->assertNull($customer->fresh()->deleted_at);

        $this->deleteCustomer($budi, $customer->fresh());
        $this->actingAs($budi)->delete('/trash/customers/'.$customer->id.'/delete')->assertRedirect();
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_empty_my_trash_only_deletes_owned()
    {
        [$budi, $andi] = $this->setUpUsers();

        $b1 = $this->ownedCustomer($budi, 'Budi Hapus Sendiri');
        $b2 = $this->ownedCustomer($budi, 'Budi Dihapus Andi');
        $a1 = $this->ownedCustomer($andi, 'Andi Dihapus Budi');
        $this->deleteCustomer($budi, $b1);
        $this->deleteCustomer($andi, $b2);
        $this->deleteCustomer($budi, $a1);

        $this->actingAs($budi)->delete('/trash/clear')->assertRedirect();

        $this->assertDatabaseMissing('customers', ['id' => $b1->id]);
        $this->assertDatabaseMissing('customers', ['id' => $b2->id]);
        // Milik Andi tetap ada di trash.
        $this->assertNotNull(Customer::onlyTrashed()->find($a1->id));
    }

    public function test_project_follows_customer_owner()
    {
        [$budi] = $this->setUpUsers();
        // Teknisi boleh hapus project (manage-technician) tapi bukan owner.
        $tek = User::factory()->create(['name' => 'Tek']);
        $tek->assignRole('technician');

        $customer = $this->ownedCustomer($budi, 'PT Budi');
        // ProjectObserver mencatat pembuat -> harus ada user login.
        $this->actingAs($budi);
        $project = $this->ownedProject($budi, $customer, 'Proyek Budi');
        $this->actingAs($tek)->delete('/projects/'.$project->id);
        $this->assertSoftDeleted('projects', ['id' => $project->id]);

        $this->assertStringContainsString('Proyek Budi', $this->actingAs($budi)->get('/trash')->getContent());
        $this->assertStringNotContainsString('Proyek Budi', $this->actingAs($tek)->get('/trash')->getContent());
        $this->actingAs($tek)->patch('/trash/projects/'.$project->id.'/restore')->assertNotFound();

        $this->actingAs($budi)->patch('/trash/projects/'.$project->id.'/restore')->assertRedirect();
        $this->assertNull($project->fresh()->deleted_at);
    }

    public function test_legacy_trash_is_super_admin_only()
    {
        [$budi, $andi, $sa] = $this->setUpUsers();

        $legacy = Customer::create(['name' => 'Legacy Lama']);
        $this->actingAs($andi)->delete('/customers/'.$legacy->id);
        // Simulasi data lama: pencatat penghapus kosong + tanpa lead.
        DB::table('customers')->where('id', $legacy->id)->update(['deleted_by' => null]);

        $this->assertStringNotContainsString('Legacy Lama', $this->actingAs($budi)->get('/trash')->getContent());

        $htmlSA = $this->actingAs($sa)->get('/trash')->assertOk()->getContent();
        $this->assertStringContainsString('All Trash', $htmlSA);
        $this->assertStringContainsString('Legacy Lama', $htmlSA);

        $this->actingAs($sa)->patch('/trash/customers/'.$legacy->id.'/restore')->assertRedirect();
        $this->assertNull($legacy->fresh()->deleted_at);
    }

    public function test_super_admin_sees_all_with_filter()
    {
        [$budi, $andi, $sa] = $this->setUpUsers();

        $this->deleteCustomer($budi, $this->ownedCustomer($budi, 'Milik Budi'));
        $this->deleteCustomer($andi, $this->ownedCustomer($andi, 'Milik Andi'));

        $html = $this->actingAs($sa)->get('/trash')->assertOk()->getContent();
        $this->assertStringContainsString('All Trash', $html);
        $this->assertStringContainsString(__('Owner'), $html);
        $this->assertStringContainsString(__('Dihapus Oleh'), $html);
        $this->assertStringContainsString('Milik Budi', $html);
        $this->assertStringContainsString('Milik Andi', $html);

        $filtered = $this->actingAs($sa)->get('/trash?user='.$budi->id)->assertOk()->getContent();
        $this->assertStringContainsString('Milik Budi', $filtered);
        $this->assertStringNotContainsString('Milik Andi', $filtered);
    }

    public function test_without_view_trash_is_forbidden()
    {
        Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        $guest = User::factory()->create();

        $this->actingAs($guest)->get('/trash')->assertForbidden();
        $this->actingAs($guest)->patch('/trash/customers/1/restore')->assertForbidden();
        $this->actingAs($guest)->delete('/trash/clear')->assertForbidden();
    }
}
