<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Standar tombol Back (AGENTS §7): halaman Management wajib memakai
 * <x-back-button>, bukan <x-icon-button icon="back">.
 */
class ManagementBackButtonTest extends TestCase
{
    use RefreshDatabase;

    private function management(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('management');

        return $user;
    }

    private function lead(): Lead
    {
        $customer = \App\Models\Customer::create(['name' => 'PT Back Management']);

        return Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'incoming_date' => now()->toDateString(),
        ]);
    }

    public function test_manage_sales_edit_uses_back_button_component(): void
    {
        $html = $this->actingAs($this->management())
            ->get(route('manage-sales.edit', $this->lead()))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/aria-label="Back".{0,600}group\/back/s', $html);
        $this->assertStringContainsString('bg-[var(--card-bg)]', $html);
    }

    public function test_activity_log_uses_back_button_component(): void
    {
        $html = $this->actingAs($this->management())
            ->get(route('manage-sales.activity-log'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/aria-label="Back".{0,600}group\/back/s', $html);
    }
}
