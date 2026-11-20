<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPricingSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    public function test_admin_can_update_tax_and_default_margin_settings(): void
    {
        $admin = $this->userWithRole('super-admin');

        $this->actingAs($admin)
            ->put(route('admin-panel.pricing.update'), [
                'vat_rate' => 11,
                'default_margin_percent' => 25,
            ])
            ->assertRedirect(route('admin-panel.pricing.edit'));

        $this->assertSame(11.0, AppSetting::vatRate());
        $this->assertSame(25.0, AppSetting::defaultMarginPercent());
    }

    public function test_admin_can_open_pricing_settings_page(): void
    {
        $admin = $this->userWithRole('super-admin');

        $this->actingAs($admin)
            ->get(route('admin-panel.pricing.edit'))
            ->assertOk()
            ->assertSee('VAT Rate (%)')
            ->assertSee('Default Margin (%)');
    }
}
