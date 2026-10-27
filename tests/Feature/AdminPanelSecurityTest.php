<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S16 — pastikan Admin Panel dan alat developer (dev-worklog)
 * hanya bisa diakses Super Admin (manage-monitoring).
 */
class AdminPanelSecurityTest extends TestCase
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

    public function test_dev_worklog_rejects_non_super_admin_roles(): void
    {
        foreach (['sales', 'technician', 'admin', 'management', 'ceo', 'lead-marketing', 'inside-sales'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get('/dev-worklog')->assertForbidden();
            $this->actingAs($user)->post('/dev-worklog/generate')->assertForbidden();
        }
    }

    public function test_dev_worklog_rejects_unauthenticated(): void
    {
        $this->get('/dev-worklog')->assertRedirect();
        $this->post('/dev-worklog/generate')->assertRedirect();
    }

    public function test_dev_worklog_allows_super_admin(): void
    {
        $admin = $this->userWithRole('super-admin');

        $this->actingAs($admin)->get('/dev-worklog')->assertOk();
    }

    public function test_admin_panel_mutations_reject_non_super_admin_roles(): void
    {
        foreach (['sales', 'technician', 'admin', 'management', 'ceo'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->post(route('admin-panel.users.store'), [
                'name' => 'X', 'email' => 'x@example.com', 'password' => 'secret-password',
            ])->assertForbidden();

            $this->actingAs($user)->post(route('admin-panel.roles.store'), ['name' => 'role-x'])
                ->assertForbidden();
        }
    }

    public function test_admin_panel_index_rejects_non_super_admin_roles(): void
    {
        foreach (['sales', 'technician', 'admin', 'management', 'ceo'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin-panel.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin-panel.audit-log'))->assertForbidden();
        }
    }
}
