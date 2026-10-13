<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesScheduleCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeVisit(User $creator, array $overrides = []): FollowUp
    {
        $customer = Customer::create(['name' => 'PT Strip '.uniqid()]);

        return FollowUp::create(array_merge([
            'customer_id' => $customer->id,
            'description' => 'Presentasi BOQ',
            'type' => 'meeting',
            'follow_up_date' => '2026-10-28',
            'activity_time' => '10:00',
            'location' => 'Kantor customer',
            'created_by' => $creator->id,
        ], $overrides));
    }

    public function test_api_returns_only_requested_day_and_own_schedules(): void
    {
        $sales = $this->userWithRole('sales');
        $other = $this->userWithRole('sales');

        $mine = $this->makeVisit($sales);
        $this->makeVisit($sales, ['follow_up_date' => '2026-10-29', 'description' => 'Hari lain']);
        $this->makeVisit($other, ['description' => 'Punya orang lain']);
        $this->makeVisit($sales, ['type' => 'call', 'description' => 'Bukan kunjungan']);

        $res = $this->actingAs($sales)->getJson(route('sales.schedules.api', ['date' => '2026-10-28']));
        $res->assertOk();

        $ids = collect($res->json())->pluck('id')->all();
        $this->assertEquals([$mine->id], $ids);

        $event = $res->json()[0];
        $this->assertSame('Presentasi BOQ', $event['description']);
        $this->assertSame('10:00', $event['activity_time']);
        $this->assertSame('Kantor customer', $event['location']);
        $this->assertStringContainsString('PT Strip', $event['customer']);
        $this->assertNotEmpty($event['url']);
    }

    public function test_api_orders_by_time_and_rejects_bad_date(): void
    {
        $sales = $this->userWithRole('sales');
        $this->makeVisit($sales, ['activity_time' => '14:00', 'description' => 'Siang']);
        $this->makeVisit($sales, ['activity_time' => '09:00', 'description' => 'Pagi']);

        $res = $this->actingAs($sales)->getJson(route('sales.schedules.api', ['date' => '2026-10-28']));
        $res->assertOk();
        $this->assertSame(['Pagi', 'Siang'], collect($res->json())->pluck('description')->all());

        $this->actingAs($sales)->getJson(route('sales.schedules.api', ['date' => 'bukan-tanggal']))
            ->assertStatus(422);
    }

    public function test_management_sees_all_schedules(): void
    {
        $sales = $this->userWithRole('sales');
        $mgmt = $this->userWithRole('management');

        $fu = $this->makeVisit($sales);

        $res = $this->actingAs($mgmt)->getJson(route('sales.schedules.api', ['date' => '2026-10-28']));
        $res->assertOk();
        $this->assertContains($fu->id, collect($res->json())->pluck('id')->all());
    }

    public function test_index_renders_strip_and_modal(): void
    {
        $sales = $this->userWithRole('sales');

        $res = $this->actingAs($sales)->get(route('sales.schedules.index'));
        $res->assertOk();
        $res->assertSee('id="sales-strip"', false);
        $res->assertSee(route('sales.schedules.api'), false);
        $res->assertSee(route('sales.follow-ups.store'), false);
    }

    public function test_modal_store_redirects_back_to_schedule_date(): void
    {
        $sales = $this->userWithRole('sales');
        $customer = Customer::create(['name' => 'PT Balik '.uniqid()]);

        $res = $this->actingAs($sales)->post(route('sales.follow-ups.store'), [
            'customer_id' => $customer->id,
            'description' => 'Kunjungan balik',
            'type' => 'meeting',
            'follow_up_date' => '2026-10-28',
            'activity_time' => '10:00',
            'location' => 'Kantor',
            'return' => 'schedule',
            'date' => '2026-10-28',
        ]);

        $res->assertRedirect(route('sales.schedules.index', ['date' => '2026-10-28']));
        $this->assertDatabaseHas('follow_ups', ['description' => 'Kunjungan balik']);
    }
}
