<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ManageMenuTest extends TestCase
{
    use RefreshDatabase;

    private function loginAs(string $role): User
    {
        Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    public function test_management_can_open_manage_placeholders(): void
    {
        $this->actingAs($this->loginAs('management'));

        foreach (['manage.marketing.index', 'manage.technical.index', 'manage.admin.index'] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee(__('Dalam Pengembangan'));
        }
    }

    public function test_non_management_roles_cannot_open_manage_placeholders(): void
    {
        foreach (['marketing', 'sales', 'technician', 'lead-technician', 'ceo'] as $role) {
            $this->actingAs($this->loginAs($role));

            foreach (['manage.marketing.index', 'manage.technical.index', 'manage.admin.index'] as $route) {
                $this->get(route($route))->assertForbidden();
            }
        }
    }

    public function test_hub_roles_can_open_manage_placeholders(): void
    {
        // Hub management kini satu role tunggal.
        foreach (['management'] as $role) {
            $this->actingAs($this->loginAs($role));

            foreach (['manage.marketing.index', 'manage.technical.index', 'manage.admin.index'] as $route) {
                $this->get(route($route))
                    ->assertOk()
                    ->assertSee(__('Dalam Pengembangan'));
            }
        }
    }
}