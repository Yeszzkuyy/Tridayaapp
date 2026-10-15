<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPanelRoleTest extends TestCase
{
    use RefreshDatabase;

    private function loginAsSuperAdmin(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        return $user;
    }

    public function test_create_page_lists_official_roles_as_presets(): void
    {
        $html = $this->actingAs($this->loginAsSuperAdmin())
            ->get(route('admin-panel.roles.create'))
            ->assertOk()
            ->getContent();

        // Batasi ke blok <select preset> (grid permission memang masih
        // memuat nama permission manage-marketing).
        $this->assertMatchesRegularExpression(
            '/<select id="role-preset".*?<\/select>/s',
            $html,
            'preset select harus ada'
        );
        preg_match('/<select id="role-preset".*?<\/select>/s', $html, $m);
        $presetHtml = $m[0] ?? '';

        foreach (['management', 'lead-marketing', 'sales', 'prakerin-admin'] as $role) {
            $this->assertStringContainsString('value="' . $role . '"', $presetHtml);
        }
        $this->assertStringNotContainsString('manage-marketing', $presetHtml);
    }

    public function test_store_role_requires_lowercase_name(): void
    {
        $this->actingAs($this->loginAsSuperAdmin())
            ->post(route('admin-panel.roles.store'), ['name' => 'Finance Baru'])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->loginAsSuperAdmin())
            ->post(route('admin-panel.roles.store'), [
                'name' => 'finance',
                'permissions' => ['view-admin'],
            ])
            ->assertRedirect(route('admin-panel.index'));

        $role = Role::where('name', 'finance')->first();
        $this->assertNotNull($role);
        $this->assertTrue($role->hasPermissionTo('view-admin'));
    }
}
