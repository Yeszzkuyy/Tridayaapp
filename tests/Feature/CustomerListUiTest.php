<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penjaga tampilan menu Customer: header ringkas (tanpa filler),
 * CTA solid, chip filter, dan banner dekoratif yang sudah dihapus.
 */
class CustomerListUiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        return $user;
    }

    public function test_customer_list_is_clean_with_solid_cta_and_quick_filters(): void
    {
        $customer = Customer::create(['name' => 'PT Nusantara Jaya', 'pt_group' => 'NTI']);

        $response = $this->actingAs($this->admin())->get(route('customers.index'));

        $response->assertOk()
            ->assertSee('Customer List')
            ->assertDontSee('Relationship Data')
            ->assertSee('aria-label="Add Customer"', false)
            ->assertSee('bg-accent-600', false)
            ->assertSee('aria-label="Customer filters"', false);

        $this->assertNotNull($customer);
    }

    public function test_customer_list_quick_filter_narrows_results(): void
    {
        $withPic = Customer::create(['name' => 'PT Punya PIC', 'pt_group' => 'NTI', 'contact_person' => 'Budi']);
        $withoutPic = Customer::create(['name' => 'PT Tanpa PIC', 'pt_group' => 'NTI']);

        $this->actingAs($this->admin())
            ->get(route('customers.index', ['pic' => 'has']))
            ->assertOk()
            ->assertSee($withPic->name)
            ->assertDontSee($withoutPic->name);

        $this->actingAs($this->admin())
            ->get(route('customers.index', ['pic' => 'none']))
            ->assertOk()
            ->assertSee($withoutPic->name)
            ->assertDontSee($withPic->name);
    }

    public function test_customer_detail_uses_open_header_without_decorative_banner(): void
    {
        $customer = Customer::create(['name' => 'PT Detail Bersih', 'pt_group' => 'NTI', 'address' => 'Jakarta']);

        $response = $this->actingAs($this->admin())->get(route('customers.show', $customer));

        $response->assertOk()
            ->assertSee('PT Detail Bersih')
            ->assertDontSee('Customer Detail')
            ->assertDontSee('from-white via-indigo-50')
            ->assertSee('sticky top-16 z-20', false)
            ->assertSee('aria-label="Edit Customer"', false);
    }
}
