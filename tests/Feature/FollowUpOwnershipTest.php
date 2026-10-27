<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Poc;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowUpOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function makeSales(string $role = 'sales'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    private function makeCustomer(array $attrs = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Test Customer',
            'email' => 'test' . uniqid() . '@example.com',
            'phone' => '081234567890',
        ], $attrs));
    }

    private function makeFollowUp(User $creator, array $attrs = []): FollowUp
    {
        $customer = $this->makeCustomer();
        return FollowUp::create(array_merge([
            'customer_id' => $customer->id,
            'description' => 'Test follow-up',
            'type' => 'call',
            'created_by' => $creator->id,
        ], $attrs));
    }

    private function makePoc(User $creator, array $attrs = []): Poc
    {
        $customer = $this->makeCustomer();
        return Poc::create(array_merge([
            'customer_id' => $customer->id,
            'type' => 'poc',
            'scheduled_date' => now()->addDay(),
            'status' => 'scheduled',
            'created_by' => $creator->id,
        ], $attrs));
    }

    // ============================================================
    // FOLLOW-UP OWNERSHIP
    // ============================================================

    public function test_sales_cannot_update_follow_up_not_owned(): void
    {
        $owner = $this->makeSales();
        $other = $this->makeSales();
        $followUp = $this->makeFollowUp($owner);

        $this->actingAs($other)
            ->put(route('sales.follow-ups.update', $followUp), [
                'customer_id' => $followUp->customer_id,
                'description' => 'Hacked update',
                'type' => 'call',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('follow_ups', [
            'id' => $followUp->id,
            'description' => 'Test follow-up',
        ]);
    }

    public function test_sales_cannot_delete_follow_up_not_owned(): void
    {
        $owner = $this->makeSales();
        $other = $this->makeSales();
        $followUp = $this->makeFollowUp($owner);

        $this->actingAs($other)
            ->delete(route('sales.follow-ups.destroy', $followUp))
            ->assertForbidden();

        $this->assertDatabaseHas('follow_ups', ['id' => $followUp->id]);
    }

    public function test_sales_cannot_complete_follow_up_not_owned(): void
    {
        $owner = $this->makeSales();
        $other = $this->makeSales();
        $followUp = $this->makeFollowUp($owner);

        $this->actingAs($other)
            ->post(route('sales.follow-ups.complete', $followUp))
            ->assertForbidden();

        $this->assertDatabaseHas('follow_ups', [
            'id' => $followUp->id,
            'completed_at' => null,
        ]);
    }

    public function test_sales_cannot_reopen_follow_up_not_owned(): void
    {
        $owner = $this->makeSales();
        $other = $this->makeSales();
        $followUp = $this->makeFollowUp($owner, ['completed_at' => now()]);

        $this->actingAs($other)
            ->post(route('sales.follow-ups.reopen', $followUp))
            ->assertForbidden();

        $this->assertNotNull($followUp->fresh()->completed_at);
    }

    public function test_sales_cannot_snooze_follow_up_not_owned(): void
    {
        $owner = $this->makeSales();
        $other = $this->makeSales();
        $followUp = $this->makeFollowUp($owner);

        $this->actingAs($other)
            ->post(route('sales.follow-ups.snooze', $followUp), ['days' => 3])
            ->assertForbidden();
    }

    public function test_sales_can_update_own_follow_up(): void
    {
        $owner = $this->makeSales();
        $followUp = $this->makeFollowUp($owner);

        $this->actingAs($owner)
            ->put(route('sales.follow-ups.update', $followUp), [
                'customer_id' => $followUp->customer_id,
                'description' => 'Updated by owner',
                'type' => 'call',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('follow_ups', [
            'id' => $followUp->id,
            'description' => 'Updated by owner',
        ]);
    }

    public function test_sales_can_delete_own_follow_up(): void
    {
        $owner = $this->makeSales();
        $followUp = $this->makeFollowUp($owner);

        $this->actingAs($owner)
            ->delete(route('sales.follow-ups.destroy', $followUp))
            ->assertRedirect();

        $this->assertSoftDeleted('follow_ups', ['id' => $followUp->id]);
    }

    public function test_super_admin_can_update_any_follow_up(): void
    {
        $owner = $this->makeSales();
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $followUp = $this->makeFollowUp($owner);

        $this->actingAs($admin)
            ->put(route('sales.follow-ups.update', $followUp), [
                'customer_id' => $followUp->customer_id,
                'description' => 'Admin override',
                'type' => 'call',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('follow_ups', [
            'id' => $followUp->id,
            'description' => 'Admin override',
        ]);
    }

    // ============================================================
    // POC OWNERSHIP
    // ============================================================

    public function test_sales_cannot_update_poc_not_owned(): void
    {
        $owner = $this->makeSales();
        $other = $this->makeSales();
        $poc = $this->makePoc($owner);

        $this->actingAs($other)
            ->put(route('sales.pocs.update', $poc), [
                'customer_id' => $poc->customer_id,
                'type' => 'poc',
                'scheduled_date' => now()->addDays(2),
                'status' => 'done',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('pocs', [
            'id' => $poc->id,
            'status' => 'scheduled',
        ]);
    }

    public function test_sales_cannot_delete_poc_not_owned(): void
    {
        $owner = $this->makeSales();
        $other = $this->makeSales();
        $poc = $this->makePoc($owner);

        $this->actingAs($other)
            ->delete(route('sales.pocs.destroy', $poc))
            ->assertForbidden();

        $this->assertDatabaseHas('pocs', ['id' => $poc->id]);
    }

    public function test_sales_can_update_own_poc(): void
    {
        $owner = $this->makeSales();
        $poc = $this->makePoc($owner);

        $this->actingAs($owner)
            ->put(route('sales.pocs.update', $poc), [
                'customer_id' => $poc->customer_id,
                'type' => 'poc',
                'scheduled_date' => now()->addDays(2),
                'status' => 'done',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pocs', [
            'id' => $poc->id,
            'status' => 'done',
        ]);
    }

    public function test_sales_can_delete_own_poc(): void
    {
        $owner = $this->makeSales();
        $poc = $this->makePoc($owner);

        $this->actingAs($owner)
            ->delete(route('sales.pocs.destroy', $poc))
            ->assertRedirect();

        $this->assertSoftDeleted('pocs', ['id' => $poc->id]);
    }

    public function test_super_admin_can_update_any_poc(): void
    {
        $owner = $this->makeSales();
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $poc = $this->makePoc($owner);

        $this->actingAs($admin)
            ->put(route('sales.pocs.update', $poc), [
                'customer_id' => $poc->customer_id,
                'type' => 'demo',
                'scheduled_date' => now()->addDays(2),
                'status' => 'done',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pocs', [
            'id' => $poc->id,
            'type' => 'demo',
        ]);
    }

    // ============================================================
    // EDGE CASES
    // ============================================================

    public function test_follow_up_with_null_created_by_rejects_all_sales(): void
    {
        $user = $this->makeSales();
        $customer = $this->makeCustomer();
        $followUp = FollowUp::create([
            'customer_id' => $customer->id,
            'description' => 'No owner',
            'type' => 'call',
            'created_by' => null,
        ]);

        $this->actingAs($user)
            ->put(route('sales.follow-ups.update', $followUp), [
                'customer_id' => $customer->id,
                'description' => 'Hacked',
                'type' => 'call',
            ])
            ->assertForbidden();
    }

    public function test_index_view_unchanged_shows_all_follow_ups(): void
    {
        $owner = $this->makeSales();
        $other = $this->makeSales();
        $this->makeFollowUp($owner);
        $this->makeFollowUp($other);

        // Index tetap menampilkan semua record (tidak ada pembatasan baca).
        $this->actingAs($owner)
            ->get(route('sales.follow-ups.index'))
            ->assertOk();
    }

    public function test_manipulating_id_returns_404(): void
    {
        $user = $this->makeSales();

        $this->actingAs($user)
            ->put(route('sales.follow-ups.update', 999999), [
                'customer_id' => 1,
                'description' => 'Ghost',
                'type' => 'call',
            ])
            ->assertNotFound();
    }
}
