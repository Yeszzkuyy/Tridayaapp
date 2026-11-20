<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Menu Follow Up adalah satu pintu jadwal follow-up Sales setelah menu
 * DAR terpisah dihapus (riwayat aktivitas ada di My Leads).
 */
class SalesFollowUpMenuTest extends TestCase
{
    use RefreshDatabase;

    private function loginAs(string $role): User
    {
        Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_follow_up_menu_is_visible_for_sales(): void
    {
        $sales = $this->loginAs('sales');

        $html = $this->actingAs($sales)->get(route('sales.my-leads'))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('sales.follow-ups.index').'"', $html);
        $this->assertStringContainsString(__('Follow Up'), $html);
    }

    public function test_follow_up_pages_are_reachable_for_sales(): void
    {
        $sales = $this->loginAs('sales');

        $this->actingAs($sales)->get(route('sales.follow-ups.index'))->assertOk();
        $this->actingAs($sales)->get(route('sales.follow-ups.create'))->assertOk();
    }

    public function test_follow_up_menu_is_hidden_for_marketing(): void
    {
        $marketing = $this->loginAs('marketing');

        $html = $this->actingAs($marketing)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('href="'.route('sales.follow-ups.index').'"', $html);
    }

    public function test_management_sees_follow_ups_of_every_sales(): void
    {
        $management = $this->loginAs('management');
        $first = User::factory()->create();
        $first->assignRole('sales');
        $second = User::factory()->create();
        $second->assignRole('sales');

        foreach ([[$first, 'PT Follow Pertama'], [$second, 'PT Follow Kedua']] as [$sales, $name]) {
            $customer = \App\Models\Customer::create(['name' => $name, 'pt_group' => 'NTI']);
            $lead = \App\Models\Lead::create([
                'customer_id' => $customer->id,
                'pt_group' => 'NTI',
                'segment' => 'vendor',
                'status' => 'warm',
                'assigned_to' => $sales->id,
            ]);
            \App\Models\FollowUp::create([
                'customer_id' => $customer->id,
                'lead_id' => $lead->id,
                'type' => 'call',
                'description' => 'Follow up '.$name,
                'follow_up_date' => today()->toDateString(),
                'created_by' => $sales->id,
            ]);
        }

        $response = $this->actingAs($management)->get(route('sales.follow-ups.index'))->assertOk();

        $response->assertSee('PT Follow Pertama');
        $response->assertSee('PT Follow Kedua');
    }

    public function test_dar_menu_stays_hidden(): void
    {
        $sales = $this->loginAs('sales');

        $html = $this->actingAs($sales)->get(route('sales.my-leads'))->assertOk()->getContent();

        // DAR tidak lagi jadi menu terpisah: riwayat aktivitas ada di My Leads.
        $this->assertStringNotContainsString('href="'.route('sales.dar.index').'"', $html);
    }

    public function test_add_follow_up_button_opens_full_page_form(): void
    {
        $sales = $this->loginAs('sales');

        $html = $this->actingAs($sales)->get(route('sales.follow-ups.index'))
            ->assertOk()->getContent();

        // Tombol utama keluar dari tab workspace: klik selalu pindah halaman.
        $expected = route('sales.follow-ups.create');
        $this->assertMatchesRegularExpression(
            '/<a href="'.preg_quote($expected, '/').'"[^>]*target="_top"/',
            $html
        );
    }
}
