<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi tampilan Admin Dashboard (AGENTS §7.1): kartu ringkasan gaya Lynk,
 * grafik mini dari data riil, filter PT sebaris, dan tanpa CTA ganda di header.
 */
class AdminDashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_hub_uses_four_lynk_summary_cards_with_sparklines(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.hub'))
            ->assertOk()
            ->getContent();

        $this->assertSame(4, substr_count($html, 'lynk-enter lynk-kpi-card'));
        $this->assertSame(4, substr_count($html, 'class="lynk-spark-line"'));
        $this->assertStringContainsString('data-countup', $html);
        $this->assertStringContainsString('aria-label="'.__('Filter Berdasarkan PT').'"', $html);
    }

    public function test_hub_header_has_no_competing_primary_actions(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.hub'))
            ->assertOk()
            ->getContent();

        // x-flow-button adalah CTA primer; dashboard admin tidak memakainya lagi.
        $this->assertStringNotContainsString('rounded-[100px]', $html);

        // Alur kerja tetap ada sebagai stepper tipis satu baris (5 langkah).
        $this->assertSame(5, substr_count($html, 'min-w-[190px]'));
    }

    public function test_hub_kpi_cards_render_real_amounts_and_trends(): void
    {
        $admin = $this->admin();
        $customer = Customer::create(['name' => 'PT Dashboard Layout']);

        Invoice::create([
            'invoice_number' => 'INV-LAYOUT-001',
            'customer_id' => $customer->id,
            'amount' => 5000000,
            'paid_amount' => 0,
            'status' => 'unpaid',
            'issue_date' => now(),
            'created_by' => $admin->id,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('admin.hub'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Rp 5.000.000', $html);
        $this->assertStringContainsString(__('proyek bulan ini'), $html);
    }
}
