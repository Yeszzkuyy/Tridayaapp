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

    private function makeFollowUp(User $creator, array $overrides = []): FollowUp
    {
        $customer = Customer::create(['name' => 'PT Kalender '.uniqid()]);

        return FollowUp::create(array_merge([
            'customer_id' => $customer->id,
            'description' => 'Presentasi BOQ',
            'type' => 'meeting',
            'follow_up_date' => '2026-10-25',
            'activity_time' => '10:00',
            'location' => 'Kantor customer',
            'created_by' => $creator->id,
        ], $overrides));
    }

    public function test_sales_sees_only_own_events(): void
    {
        $sales = $this->userWithRole('sales');
        $other = $this->userWithRole('sales');

        $mine = $this->makeFollowUp($sales);
        $theirs = $this->makeFollowUp($other);

        $res = $this->actingAs($sales)->getJson(route('sales.schedules.events'));
        $res->assertOk();

        $ids = collect($res->json())->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);

        $event = collect($res->json())->firstWhere('id', $mine->id);
        $this->assertSame('2026-10-25T10:00:00', $event['start']);
        $this->assertStringContainsString('Presentasi BOQ', $event['description']);
        $this->assertNotEmpty($event['url']);
    }

    public function test_management_sees_all_events(): void
    {
        $sales = $this->userWithRole('sales');
        $mgmt = $this->userWithRole('management');

        $fu = $this->makeFollowUp($sales);

        $res = $this->actingAs($mgmt)->getJson(route('sales.schedules.events'));
        $res->assertOk();
        $this->assertContains($fu->id, collect($res->json())->pluck('id')->all());
    }

    public function test_index_renders_calendar_and_modal_data(): void
    {
        $sales = $this->userWithRole('sales');

        $res = $this->actingAs($sales)->get(route('sales.schedules.index'));
        $res->assertOk();
        $res->assertSee('id="sales-calendar"', false);
        $res->assertSee(route('sales.schedules.events'), false);
        $res->assertSee(route('sales.follow-ups.store'), false);
    }
}
