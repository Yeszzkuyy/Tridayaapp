<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelUserTest extends TestCase
{
    use RefreshDatabase;

    private function loginAsSuperAdmin(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        return $user;
    }

    public function test_store_user_syncs_legacy_role_column(): void
    {
        $this->actingAs($this->loginAsSuperAdmin())
            ->post(route('admin-panel.users.store'), [
                'name' => 'Sales Baru',
                'email' => 'salesbaru@tridayaapp.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'roles' => ['sales'],
            ])
            ->assertRedirect(route('admin-panel.index'));

        $user = User::where('email', 'salesbaru@tridayaapp.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('sales'));
        $this->assertSame('sales', $user->role);
    }

    public function test_store_user_rejects_unknown_role(): void
    {
        $this->actingAs($this->loginAsSuperAdmin())
            ->post(route('admin-panel.users.store'), [
                'name' => 'X',
                'email' => 'x@tridayaapp.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'roles' => ['role-tidak-ada'],
            ])
            ->assertSessionHasErrors('roles.0');

        $this->assertNull(User::where('email', 'x@tridayaapp.com')->first());
    }

    public function test_update_user_syncs_legacy_role_column(): void
    {
        $admin = $this->loginAsSuperAdmin();
        $user = User::factory()->create(['role' => 'guest']);

        $this->actingAs($admin)
            ->put(route('admin-panel.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => ['technician'],
            ])
            ->assertRedirect(route('admin-panel.index'));

        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasRole('technician'));
        $this->assertSame('technician', $fresh->role);
    }

    public function test_super_admin_can_grant_super_admin(): void
    {
        $this->actingAs($this->loginAsSuperAdmin())
            ->post(route('admin-panel.users.store'), [
                'name' => 'Super Baru',
                'email' => 'superbaru@tridayaapp.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'roles' => ['super-admin'],
            ])
            ->assertRedirect(route('admin-panel.index'));

        $this->assertTrue(User::where('email', 'superbaru@tridayaapp.com')->first()->hasRole('super-admin'));
    }

    public function test_super_admin_cannot_demote_self(): void
    {
        $admin = $this->loginAsSuperAdmin();

        $this->actingAs($admin)
            ->put(route('admin-panel.users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'roles' => ['technician'],
            ])
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->hasRole('super-admin'));
    }

    public function test_single_super_admin_cannot_remove_last_super_admin(): void
    {
        // Satu-satunya super-admin mencoba demote dirinya sendiri:
        // ditolak (self-demote + last-admin, sistem tidak boleh terkunci).
        $admin = $this->loginAsSuperAdmin();

        $this->actingAs($admin)
            ->put(route('admin-panel.users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'roles' => ['technician'],
            ])
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->hasRole('super-admin'));
        $this->assertSame(1, User::role('super-admin')->count());
    }

    public function test_can_demote_second_super_admin(): void
    {
        $admin = $this->loginAsSuperAdmin();
        $second = User::factory()->create();
        $second->assignRole('super-admin');

        $this->actingAs($admin)
            ->put(route('admin-panel.users.update', $second), [
                'name' => $second->name,
                'email' => $second->email,
                'roles' => ['technician'],
            ])
            ->assertRedirect(route('admin-panel.index'));

        $this->assertFalse($second->fresh()->hasRole('super-admin'));
        $this->assertTrue($admin->fresh()->hasRole('super-admin'));
    }
}
