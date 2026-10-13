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

    public function test_api_range_returns_flat_list_with_dates(): void
    {
        $sales = $this->userWithRole('sales');
        $this->makeVisit($sales, ['follow_up_date' => '2026-10-01', 'description' => 'Awal']);
        $this->makeVisit($sales, ['follow_up_date' => '2026-10-28', 'description' => 'Akhir']);
        $this->makeVisit($sales, ['follow_up_date' => '2026-11-15', 'description' => 'Luar']);

        $res = $this->actingAs($sales)->getJson(route('sales.schedules.api', ['start' => '2026-09-29', 'end' => '2026-11-09']));
        $res->assertOk();
        $this->assertSame(['Awal', 'Akhir'], collect($res->json())->pluck('description')->all());
        $this->assertSame('2026-10-01', $res->json()[0]['date']);

        $this->actingAs($sales)->getJson(route('sales.schedules.api', ['start' => 'rusak', 'end' => '2026-11-09']))
            ->assertStatus(422);
    }

    public function test_api_includes_completed_flag(): void
    {
        $sales = $this->userWithRole('sales');
        $done = $this->makeVisit($sales, ['description' => 'Sudah']);
        $done->forceFill(['completed_at' => now()])->save();
        $this->makeVisit($sales, ['description' => 'Belum']);

        $res = $this->actingAs($sales)->getJson(route('sales.schedules.api', ['date' => '2026-10-28']));
        $res->assertOk();
        $byDesc = collect($res->json())->mapWithKeys(fn ($e) => [$e['description'] => $e['completed']])->all();
        $this->assertTrue($byDesc['Sudah']);
        $this->assertFalse($byDesc['Belum']);
    }

    public function test_readonly_role_sees_calendar_without_write_ui(): void
    {
        $admin = $this->userWithRole('admin');

        $res = $this->actingAs($admin)->get(route('sales.schedules.index'));
        $res->assertOk();
        $res->assertSee('id="sales-drawer"', false);
        $res->assertDontSee('Tambah Jadwal Kunjungan');
        $res->assertDontSee('sales-add-modal', false);

        $sales = $this->userWithRole('sales');
        $res = $this->actingAs($sales)->get(route('sales.schedules.index'));
        $res->assertOk();
        $res->assertSee('id="sales-add-modal"', false);
    }

    public function test_complete_from_drawer_redirects_back_to_schedule(): void
    {
        $sales = $this->userWithRole('sales');
        $fu = $this->makeVisit($sales);

        $res = $this->actingAs($sales)->post(route('sales.follow-ups.complete', $fu), [
            'return' => 'schedule',
            'date' => '2026-10-28',
        ]);

        $res->assertRedirect(route('sales.schedules.index', ['date' => '2026-10-28']));
        $this->assertNotNull($fu->fresh()->completed_at);
    }

    public function test_index_renders_strip_and_modal(): void
    {
        $sales = $this->userWithRole('sales');

        $res = $this->actingAs($sales)->get(route('sales.schedules.index'));
        $res->assertOk();
        $res->assertSee('id="sales-month-grid"', false);
        $res->assertSee('id="sales-view-switcher"', false);
        $res->assertSee('id="sales-week-list"', false);
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
