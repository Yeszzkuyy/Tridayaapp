<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowUpTypeNextTest extends TestCase
{
    use RefreshDatabase;

    private function salesUser(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('sales');

        return $user;
    }

    public function test_store_persists_type_and_next_date(): void
    {
        $sales = $this->salesUser();
        $customer = Customer::create(['name' => 'PT Tipe']);

        $this->actingAs($sales)->postJson(route('sales.follow-ups.store'), [
            'customer_id' => $customer->id,
            'description' => 'Customer sudah menerima proposal.',
            'type' => 'whatsapp',
            'follow_up_date' => '2026-09-29',
            'next_follow_up_date' => '2026-10-02',
        ])->assertOk();

        $fu = FollowUp::where('customer_id', $customer->id)->first();
        $this->assertNotNull($fu);
        $this->assertSame('whatsapp', $fu->type);
        $this->assertSame('2026-10-02', $fu->next_follow_up_date->toDateString());

        // tampil di detail dan tidak hilang setelah refresh
        $res = $this->actingAs($sales)->get(route('sales.follow-ups.show', $fu));
        $res->assertOk();
        $res->assertSee('WhatsApp');
        $res->assertSee('02 Oct 2026');
        $this->actingAs($sales)->get(route('sales.follow-ups.show', $fu))->assertOk();
    }

    public function test_invalid_type_rejected(): void
    {
        $sales = $this->salesUser();
        $customer = Customer::create(['name' => 'PT Tolak']);

        $this->actingAs($sales)->postJson(route('sales.follow-ups.store'), [
            'customer_id' => $customer->id,
            'description' => 'x',
            'type' => 'telepati',
        ])->assertStatus(422)->assertJsonValidationErrors(['type']);
    }

    public function test_due_filters_today_upcoming_overdue(): void
    {
        $sales = $this->salesUser();
        $customer = Customer::create(['name' => 'PT Due']);
        $mk = fn ($date) => FollowUp::create([
            'customer_id' => $customer->id,
            'description' => 'fu '.$date,
            'follow_up_date' => $date,
            'created_by' => $sales->id,
        ]);
        $today = today()->toDateString();
        $mk($today);
        $mk(today()->addDay()->toDateString());
        $mk(today()->subDay()->toDateString());

        $ridden = fn ($param) => $this->actingAs($sales)
            ->getJson(route('sales.follow-ups.index', $param ? ['overdue' => $param] : []))
            ->json('html');

        $this->assertStringContainsString('fu '.$today, $ridden('today'));
        $this->assertStringNotContainsString('fu '.today()->addDay()->toDateString(), $ridden('today'));

        $this->assertStringContainsString('fu '.today()->addDay()->toDateString(), $ridden('upcoming'));
        $this->assertStringNotContainsString('fu '.$today, $ridden('upcoming'));

        // kompatibel mundur: overdue=1 sekarang berarti tab Due (termasuk yang lewat).
        $this->assertStringContainsString('fu '.today()->subDay()->toDateString(), $ridden('1'));
        $this->assertStringContainsString('fu '.$today, $ridden('1'));
        $this->assertStringNotContainsString('fu '.today()->addDay()->toDateString(), $ridden('1'));
    }

    public function test_null_fields_fall_back_gracefully(): void
    {
        $sales = $this->salesUser();
        $customer = Customer::create(['name' => 'PT Null']);
        $fu = FollowUp::create(['customer_id' => $customer->id, 'description' => 'tanpa tanggal']);

        $res = $this->actingAs($sales)->get(route('sales.follow-ups.show', $fu));
        $res->assertOk();
        $res->assertSee('-');
    }
}
