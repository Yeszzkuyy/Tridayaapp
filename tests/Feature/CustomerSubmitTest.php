<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CustomerSubmitTest extends TestCase
{
    use RefreshDatabase;

    private function user()
    {
        Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        $u = \App\Models\User::factory()->create();
        $u->givePermissionTo('manage-sales');
        return $u;
    }

    public function test_store_redirects_with_success_card_flag(): void
    {
        $this->actingAs($this->user())
            ->post(route('customers.store'), [
                'name' => 'PT Maju Jaya',
                'pt_group' => 'NTI',
            ])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('success_card', true);

        $this->assertSame(1, Customer::where('name', 'PT Maju Jaya')->count());
    }

    public function test_store_saves_billing_shipping_and_finance_pic(): void
    {
        $this->actingAs($this->user())
            ->post(route('customers.store'), [
                'name' => 'PT Maju Jaya',
                'pt_group' => 'NTI',
                'address' => 'Jl. Utama 1',
                'billing_address' => 'Jl. Tagih 2',
                'shipping_address' => 'Jl. Kirim 3',
                'finance_pic_name' => 'Ibu Sari',
                'finance_pic_phone' => '08123456789',
                'finance_pic_email' => 'sari@majujaya.test',
            ])
            ->assertRedirect(route('customers.index'));

        $customer = Customer::where('name', 'PT Maju Jaya')->firstOrFail();

        $this->assertSame('Jl. Tagih 2', $customer->billing_address);
        $this->assertSame('Jl. Kirim 3', $customer->shipping_address);
        $this->assertSame('Ibu Sari', $customer->finance_pic_name);
        $this->assertSame('08123456789', $customer->finance_pic_phone);
        $this->assertSame('sari@majujaya.test', $customer->finance_pic_email);
        $this->assertSame('Jl. Tagih 2', $customer->billingAddress());
        $this->assertSame('Jl. Kirim 3', $customer->shippingAddress());
    }

    public function test_billing_and_shipping_address_fall_back_to_main_address(): void
    {
        $customer = Customer::create([
            'name' => 'PT Fallback',
            'pt_group' => 'NTI',
            'address' => 'Jl. Utama 9',
        ]);

        $this->assertSame('Jl. Utama 9', $customer->billingAddress());
        $this->assertSame('Jl. Utama 9', $customer->shippingAddress());
        $this->assertNull($customer->financeContact());
    }

    public function test_update_redirects_with_success_card_flag(): void
    {
        $customer = Customer::create(['name' => 'PT Lama', 'pt_group' => 'NTI']);

        $this->actingAs($this->user())
            ->put(route('customers.update', $customer), [
                'name' => 'PT Baru',
                'pt_group' => 'MGK',
            ])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('success_card', true);

        $this->assertSame('MGK', $customer->fresh()->pt_group);
    }
}
