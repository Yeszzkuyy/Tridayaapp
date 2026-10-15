<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SalesAjaxTest extends TestCase
{
    use RefreshDatabase;

    private function loginAsSales(): User
    {
        Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        $user = User::factory()->create();
        $user->assignRole('sales');
        return $user;
    }

    public function test_followup_store_and_destroy_ajax(): void
    {
        $sales = $this->loginAsSales();
        $customer = Customer::create(['name' => 'PT FU']);

        $store = $this->actingAs($sales)->postJson(route('sales.follow-ups.store'), [
            'customer_id' => $customer->id,
            'description' => 'Hubungi PIC',
        ]);

        $store->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('redirect', route('sales.follow-ups.index'));
        $fu = FollowUp::where('customer_id', $customer->id)->first();
        $this->assertNotNull($fu);

        $destroy = $this->actingAs($sales)->deleteJson(route('sales.follow-ups.destroy', $fu));
        $destroy->assertOk()->assertJsonPath('ok', true);
        $this->assertSoftDeleted('follow_ups', ['id' => $fu->id]);
    }

    public function test_followup_filter_ajax_returns_table_html(): void
    {
        $sales = $this->loginAsSales();
        $customer = Customer::create(['name' => 'PT FU Filter']);
        FollowUp::create(['customer_id' => $customer->id, 'description' => 'Sapa ulang', 'created_by' => $sales->id]);

        $res = $this->actingAs($sales)->getJson(route('sales.follow-ups.index', ['search' => 'FU Filter']));

        $res->assertOk()->assertJsonPath('ok', true);
        $this->assertStringContainsString('PT FU Filter', $res->json('html'));
    }

    public function test_non_ajax_requests_still_redirect(): void
    {
        $sales = $this->loginAsSales();
        $customer = Customer::create(['name' => 'PT Klasik']);
        $fu = FollowUp::create([
            'customer_id' => $customer->id,
            'description' => 'Hapus klasik',
            'created_by' => $sales->id,
        ]);

        $this->actingAs($sales)
            ->delete(route('sales.follow-ups.destroy', $fu))
            ->assertRedirect(route('sales.follow-ups.index'));
    }
}
