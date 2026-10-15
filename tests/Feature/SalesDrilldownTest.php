<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SalesDrilldownTest extends TestCase
{
    use RefreshDatabase;

    private function loginAsSales(): User
    {
        Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        $user = User::factory()->create();
        $user->assignRole('sales');
        return $user;
    }

    private function makeLead(User $assignee, string $status, string $customerName): Lead
    {
        $customer = Customer::create(['name' => $customerName]);
        return Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => $status,
            'incoming_date' => now()->toDateString(),
            'assigned_to' => $assignee->id,
        ]);
    }

    public function test_dashboard_links_drill_down_to_filtered_tables(): void
    {
        $response = $this->actingAs($this->loginAsSales())
            ->get(route('sales.dashboard'));

        $response->assertOk()
            ->assertSee(route('sales.my-leads', ['active' => 1]), false)
            ->assertSee(route('sales.my-leads', ['won_month' => 1]), false);
    }

    public function test_my_leads_sort_oldest_and_customer(): void
    {
        $sales = $this->loginAsSales();
        $this->makeLead($sales, 'cool', 'PT Zulu');
        $this->makeLead($sales, 'cool', 'PT Alpha');

        $oldest = $this->actingAs($sales)
            ->get(route('sales.my-leads', ['sort' => 'oldest']))
            ->assertOk();
        $this->assertTrue(
            strpos($oldest->getContent(), 'PT Zulu') < strpos($oldest->getContent(), 'PT Alpha')
        );

        $byCustomer = $this->actingAs($sales)
            ->get(route('sales.my-leads', ['sort' => 'customer']))
            ->assertOk();
        $this->assertTrue(
            strpos($byCustomer->getContent(), 'PT Alpha') < strpos($byCustomer->getContent(), 'PT Zulu')
        );
    }
    public function test_my_leads_won_month_filter_matches_kpi(): void
    {
        $sales = $this->loginAsSales();
        $this->makeLead($sales, 'won', 'PT Menang Bulan Ini');
        $wonLastMonth = $this->makeLead($sales, 'won', 'PT Menang Bulan Lalu');
        $wonLastMonth->forceFill(['updated_at' => now()->subMonth()])->saveQuietly();

        $response = $this->actingAs($sales)
            ->get(route('sales.my-leads', ['won_month' => 1]));

        $response->assertOk()
            ->assertSee('PT Menang Bulan Ini')
            ->assertDontSee('PT Menang Bulan Lalu');
    }

    public function test_my_leads_active_filter_hides_won_and_lost(): void
    {
        $sales = $this->loginAsSales();
        $this->makeLead($sales, 'cool', 'PT Aktif');
        $this->makeLead($sales, 'won', 'PT Menang');

        $response = $this->actingAs($sales)
            ->get(route('sales.my-leads', ['active' => 1]));

        $response->assertOk()
            ->assertSee('PT Aktif')
            ->assertDontSee('PT Menang');
    }

    public function test_export_my_leads_returns_csv_with_own_leads_only(): void
    {
        $sales = $this->loginAsSales();
        $other = User::factory()->create();
        $other->assignRole('sales');
        $this->makeLead($sales, 'cool', 'PT Milik Saya');
        $this->makeLead($other, 'cool', 'PT Orang Lain');

        $response = $this->actingAs($sales)
            ->get(route('sales.my-leads.export'));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('PT Milik Saya', $response->streamedContent());
        $this->assertStringNotContainsString('PT Orang Lain', $response->streamedContent());
    }
}
