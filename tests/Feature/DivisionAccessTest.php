<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DivisionAccessTest extends TestCase
{
    use RefreshDatabase;

    private function loginAs(string $role): User
    {
        Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    /**
     * Matriks: tiap divisi melihat Dashboard + divisinya + Customer + Trash.
     * Monitoring hanya manager & super-admin.
     */
    public function test_teknisi_menu_and_access()
    {
        $u = $this->loginAs('technician');
        $res = $this->actingAs($u)->get('/teknisi/dashboard')->assertOk();
        $html = $res->getContent();

        foreach (['/teknisi/jadwal', '/customers', '/trash'] as $link) {
            $this->assertStringContainsString($link, $html, "$link should be visible for teknisi");
        }
        foreach (['/leads"', '/admin/invoices', '/sales/follow-ups', '/monitoring'] as $link) {
            $this->assertStringNotContainsString($link, $html, "$link should be hidden for teknisi");
        }

        $this->actingAs($u)->get('/dashboard')->assertOk();
        $this->actingAs($u)->get('/customers')->assertOk();
        $this->actingAs($u)->get('/trash')->assertOk();
        $this->actingAs($u)->get('/leads')->assertForbidden();
        $this->actingAs($u)->get('/admin/invoices')->assertForbidden();
        $this->actingAs($u)->get('/sales/meetings')->assertNotFound();
        $this->actingAs($u)->get('/monitoring')->assertForbidden();
    }

    public function test_sales_menu_and_access()
    {
        $u = $this->loginAs('sales');
        $res = $this->actingAs($u)->get('/sales/follow-ups')->assertOk();
        $html = $res->getContent();

        foreach (['/sales/follow-ups', '/customers', '/trash', '/projects', '/admin/invoices'] as $link) {
            $this->assertStringContainsString($link, $html, "$link should be visible for sales");
        }
        foreach (['/leads"'] as $link) {
            $this->assertStringNotContainsString($link, $html, "$link should be hidden for sales");
        }

        $this->actingAs($u)->get('/dashboard')->assertOk();
        $this->actingAs($u)->get('/customers')->assertOk();
        $this->actingAs($u)->get('/trash')->assertOk();

        // Sales read-only pada Project: boleh lihat, dilarang mengubah
        $customer = \App\Models\Customer::create(['name' => 'Cust Sales']);
        $workType = \App\Models\WorkType::create(['name' => 'Instalasi']);
        $project = \App\Models\Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Proj Sales',
        ]);
        $this->actingAs($u)->get('/projects')->assertOk();
        $this->actingAs($u)->get('/projects/'.$project->id)->assertOk();
        $this->actingAs($u)->post('/projects', [])->assertForbidden();
        $this->actingAs($u)->put('/projects/'.$project->id, [])->assertForbidden();
        $this->actingAs($u)->delete('/projects/'.$project->id)->assertForbidden();
        $this->assertNotNull($project->fresh());

        $this->actingAs($u)->get('/teknisi/dashboard')->assertForbidden();
        // Sales read-only pada Admin: lihat boleh, mutasi 403 ( tested di RoleMatrixTest )
        $this->actingAs($u)->get('/admin/invoices')->assertOk();
        $this->actingAs($u)->get('/monitoring')->assertForbidden();
    }

    public function test_admin_menu_and_access()
    {
        $u = $this->loginAs('admin');
        $res = $this->actingAs($u)->get('/admin/invoices')->assertOk();
        $html = $res->getContent();

        foreach (['/admin/invoices', '/trash', '/customers', '/sales/my-leads'] as $link) {
            $this->assertStringContainsString($link, $html, "$link should be visible for admin");
        }
        foreach (['/leads"', '/monitoring'] as $link) {
            $this->assertStringNotContainsString($link, $html, "$link should be hidden for admin");
        }

        $this->actingAs($u)->get('/dashboard')->assertOk();
        $this->actingAs($u)->get('/customers')->assertOk();
        $this->actingAs($u)->get('/trash')->assertOk();
        // Admin read-only pada Sales & Project ( tested di RoleMatrixTest )
        $this->actingAs($u)->get('/projects')->assertOk();
        $this->actingAs($u)->get('/leads')->assertForbidden();
        $this->actingAs($u)->get('/monitoring')->assertForbidden();
    }

    public function test_marketing_menu_and_access()
    {
        $u = $this->loginAs('marketing');
        $res = $this->actingAs($u)->get('/leads')->assertOk();
        $html = $res->getContent();

        foreach (['/partners', '/customers', '/trash'] as $link) {
            $this->assertStringContainsString($link, $html, "$link should be visible for marketing");
        }
        foreach (['/admin/invoices', '/projects"', '/monitoring'] as $link) {
            $this->assertStringNotContainsString($link, $html, "$link should be hidden for marketing");
        }

        $this->actingAs($u)->get('/dashboard')->assertOk();
        $this->actingAs($u)->get('/customers')->assertOk();
        $this->actingAs($u)->get('/trash')->assertOk();
        $this->actingAs($u)->get('/admin/invoices')->assertForbidden();
        $this->actingAs($u)->get('/projects')->assertForbidden();
        $this->actingAs($u)->get('/monitoring')->assertForbidden();
    }

    public function test_ceo_sees_all_including_monitoring()
    {
        $u = $this->loginAs('ceo');
        $this->actingAs($u)->get('/projects')->assertOk();
        $this->actingAs($u)->get('/leads')->assertOk();
        $this->actingAs($u)->get('/customers')->assertOk();
        $this->actingAs($u)->get('/monitoring')->assertOk();
    }

    public function test_super_admin_sees_all()
    {
        $u = $this->loginAs('super-admin');
        $this->actingAs($u)->get('/projects')->assertOk();
        $this->actingAs($u)->get('/admin-panel')->assertOk();
        $this->actingAs($u)->get('/trash')->assertOk();
        $this->actingAs($u)->get('/monitoring')->assertOk();
    }

    public function test_super_admin_bypasses_all_gates()
    {
        $u = $this->loginAs('super-admin');
        foreach (['view-technician', 'view-sales', 'view-admin', 'view-marketing', 'manage-monitoring', 'apa-aja-yang-tidak-ada'] as $p) {
            $this->assertTrue($u->can($p), $p);
        }
    }
}
