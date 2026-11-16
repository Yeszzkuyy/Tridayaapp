<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormValidationStandardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_layout_injects_validation_templates_and_server_errors(): void
    {
        $admin = User::factory()->create()->assignRole('admin');

        $this->actingAs($admin)
            ->followingRedirects()
            ->from(route('products.create'))
            ->post(route('products.store'), [])
            ->assertOk()
            ->assertSee('name="validation-required"', false)
            ->assertSee('name="validation-summary"', false)
            ->assertSee('window.__formErrors =', false)
            ->assertSee('"name"', false)
            ->assertSee('data-server-error-summary', false);
    }

    public function test_guest_login_page_has_validation_templates(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="validation-required"', false)
            ->assertSee('window.__formErrors', false);
    }
}
