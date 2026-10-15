<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CustomerPtTest extends TestCase
{
    use RefreshDatabase;

    private function sales(string $name, array $roles): User
    {
        $u = User::factory()->create(['name' => $name]);
        foreach ($roles as $role) {
            $u->assignRole($role);
        }

        return $u;
    }

    private function setUpUsers(): array
    {
        Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);

        $hendri = $this->sales('Hendri', ['sales-mgk']);
        $multi = $this->sales('Multi', ['sales-mgk', 'sales-tps']);
        $plain = $this->sales('Polos', ['sales']);
        $sa = User::factory()->create(['name' => 'Super']);
        $sa->assignRole('super-admin');

        return [$hendri, $multi, $plain, $sa];
    }

    private function customer(string $name, ?string $pt): Customer
    {
        return Customer::create(['name' => $name, 'pt_group' => $pt]);
    }

    public function test_same_pt_can_edit()
    {
        [$hendri] = $this->setUpUsers();
        $c = $this->customer('PT MGK 1', 'MGK');

        $this->actingAs($hendri)->get('/customers/'.$c->id.'/edit')->assertOk();
        $this->actingAs($hendri)->put('/customers/'.$c->id, [
            'name' => 'PT MGK 1 Baru',
            'pt_group' => 'MGK',
        ])->assertRedirect();
        $this->assertEquals('PT MGK 1 Baru', $c->fresh()->name);

        $this->actingAs($hendri)->delete('/customers/'.$c->id)->assertRedirect();
        $this->assertSoftDeleted('customers', ['id' => $c->id]);
    }

    public function test_other_pt_read_only()
    {
        [$hendri] = $this->setUpUsers();
        $c = $this->customer('PT NTI 1', 'NTI');

        // Boleh lihat...
        $this->actingAs($hendri)->get('/customers/'.$c->id)->assertOk();
        $html = $this->actingAs($hendri)->get('/customers')->assertOk()->getContent();
        $this->assertStringContainsString('PT NTI 1', $html);
        // ...tapi tombol edit tidak tampil dan aksi ditolak.
        $this->assertStringNotContainsString('/customers/'.$c->id.'/edit', $html);
        $this->actingAs($hendri)->get('/customers/'.$c->id.'/edit')->assertForbidden();
        $this->actingAs($hendri)->put('/customers/'.$c->id, ['name' => 'X', 'pt_group' => 'NTI'])->assertForbidden();
        $this->actingAs($hendri)->delete('/customers/'.$c->id)->assertForbidden();
        $this->assertEquals('PT NTI 1', $c->fresh()->name);
    }

    public function test_multi_pt_user()
    {
        [, $multi] = $this->setUpUsers();
        $mgk = $this->customer('PT MGK 2', 'MGK');
        $tps = $this->customer('PT TPS 2', 'TPS');
        $nti = $this->customer('PT NTI 2', 'NTI');

        $this->actingAs($multi)->put('/customers/'.$mgk->id, ['name' => 'OK', 'pt_group' => 'MGK'])->assertRedirect();
        $this->actingAs($multi)->put('/customers/'.$tps->id, ['name' => 'OK', 'pt_group' => 'TPS'])->assertRedirect();
        $this->actingAs($multi)->put('/customers/'.$nti->id, ['name' => 'X', 'pt_group' => 'NTI'])->assertForbidden();
    }

    public function test_plain_sales_and_null_pt_stay_open()
    {
        [, , $plain, $sa] = $this->setUpUsers();
        $nti = $this->customer('PT NTI 3', 'NTI');
        $legacy = $this->customer('PT Lama', null);

        // Sales polos (tanpa role PT) tetap seperti dulu.
        $this->actingAs($plain)->put('/customers/'.$nti->id, ['name' => 'OK', 'pt_group' => 'NTI'])->assertRedirect();
        // PT kosong tidak dikunci untuk siapa pun.
        $this->actingAs($plain)->put('/customers/'.$legacy->id, ['name' => 'OK2', 'pt_group' => 'NTI'])->assertRedirect();

        // Super-admin bebas semua.
        $this->actingAs($sa)->put('/customers/'.$nti->id, ['name' => 'SA', 'pt_group' => 'NTI'])->assertRedirect();
    }

    public function test_trash_restore_respects_pt()
    {
        [$hendri] = $this->setUpUsers();

        // Budi owner (via lead) tapi pegang PT NTI -> tidak boleh restore MGK.
        $budi = $this->sales('Budi', ['sales-nti']);
        $customer = $this->customer('PT MGK Trash', 'MGK');
        Lead::create(['customer_id' => $customer->id, 'assigned_to' => $budi->id]);
        $this->actingAs($hendri)->delete('/customers/'.$customer->id);
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);

        $this->actingAs($budi)->patch('/trash/customers/'.$customer->id.'/restore')->assertForbidden();
        $this->assertNotNull(Customer::onlyTrashed()->find($customer->id));
    }

    public function test_empty_my_trash_respects_pt()
    {
        [$hendri] = $this->setUpUsers();

        // Hendri (MGK) jadi owner dua-duanya via lead, tapi satu NTI.
        $mgk = $this->customer('PT MGK Trash', 'MGK');
        $nti = $this->customer('PT NTI Trash', 'NTI');
        Lead::create(['customer_id' => $mgk->id, 'assigned_to' => $hendri->id]);
        Lead::create(['customer_id' => $nti->id, 'assigned_to' => $hendri->id]);

        // Dihapus super-admin agar deleted_by bukan Hendri (tetap milik Hendri).
        $sa = User::factory()->create();
        $sa->assignRole('super-admin');
        $this->actingAs($sa)->delete('/customers/'.$mgk->id);
        $this->actingAs($sa)->delete('/customers/'.$nti->id);

        $this->actingAs($hendri)->delete('/trash/clear')->assertRedirect();

        $this->assertDatabaseMissing('customers', ['id' => $mgk->id]);
        $this->assertNotNull(Customer::onlyTrashed()->find($nti->id));
    }
}
