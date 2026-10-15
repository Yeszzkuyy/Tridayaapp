<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesFollowupDoneTest extends TestCase
{
    use RefreshDatabase;

    private function salesUser(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('sales');

        return $user;
    }

    private function makeFollowUp(array $overrides = []): FollowUp
    {
        $customer = Customer::create(['name' => 'PT Selesai']);
        return FollowUp::create(array_merge([
            'customer_id' => $customer->id,
            'description' => 'Follow up proposal.',
            'follow_up_date' => today()->subDay()->toDateString(),
        ], $overrides));
    }

    public function test_complete_hides_from_overdue_and_marks_done(): void
    {
        $sales = $this->salesUser();
        $fu = $this->makeFollowUp(['created_by' => $sales->id]);

        $this->actingAs($sales)
            ->postJson(route('sales.follow-ups.complete', $fu))
            ->assertOk();

        $this->assertNotNull($fu->fresh()->completed_at);

        $this->actingAs($sales)
            ->get(route('sales.follow-ups.index', ['overdue' => 1]))
            ->assertOk()
            ->assertDontSee('Follow up proposal.');
    }

    public function test_reopen_clears_completed_at(): void
    {
        $sales = $this->salesUser();
        $fu = $this->makeFollowUp(['created_by' => $sales->id, 'completed_at' => now()]);

        $this->actingAs($sales)
            ->postJson(route('sales.follow-ups.reopen', $fu))
            ->assertOk();

        $this->assertNull($fu->fresh()->completed_at);
    }

    public function test_snooze_pushes_date_and_resets_reminder(): void
    {
        $sales = $this->salesUser();
        $fu = $this->makeFollowUp([
            'created_by' => $sales->id,
            'follow_up_date' => today()->subDays(5)->toDateString(),
            'reminder_sent_at' => now()->subDay(),
        ]);

        $this->actingAs($sales)
            ->post(route('sales.follow-ups.snooze', $fu), ['days' => 7])
            ->assertRedirect(route('sales.follow-ups.index'));

        $fresh = $fu->fresh();
        $this->assertSame(today()->addDays(7)->toDateString(), $fresh->follow_up_date->toDateString());
        $this->assertNull($fresh->reminder_sent_at);
    }

    public function test_snooze_rejects_invalid_days(): void
    {
        $sales = $this->salesUser();
        $fu = $this->makeFollowUp(['created_by' => $sales->id]);

        $this->actingAs($sales)
            ->post(route('sales.follow-ups.snooze', $fu), ['days' => 61])
            ->assertSessionHasErrors('days');

        $this->actingAs($sales)
            ->post(route('sales.follow-ups.snooze', $fu), [])
            ->assertSessionHasErrors('days');
    }

    public function test_snooze_accepts_any_days_up_to_60(): void
    {
        $sales = $this->salesUser();
        $fu = $this->makeFollowUp([
            'created_by' => $sales->id,
            'follow_up_date' => today()->subDays(5)->toDateString(),
        ]);

        $this->actingAs($sales)
            ->post(route('sales.follow-ups.snooze', $fu), ['days' => 10])
            ->assertRedirect(route('sales.follow-ups.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            today()->addDays(10)->toDateString(),
            $fu->fresh()->follow_up_date->toDateString()
        );
    }

    public function test_snooze_accepts_absolute_date(): void
    {
        $sales = $this->salesUser();
        $fu = $this->makeFollowUp(['created_by' => $sales->id]);

        $target = today()->addDays(5)->toDateString();

        $this->actingAs($sales)
            ->post(route('sales.follow-ups.snooze', $fu), ['date' => $target])
            ->assertRedirect(route('sales.follow-ups.index'))
            ->assertSessionHasNoErrors();

        $fresh = $fu->fresh();
        $this->assertSame($target, $fresh->follow_up_date->toDateString());
        $this->assertNull($fresh->reminder_sent_at);
    }

    public function test_snooze_rejects_past_date(): void
    {
        $sales = $this->salesUser();
        $fu = $this->makeFollowUp(['created_by' => $sales->id]);

        $this->actingAs($sales)
            ->post(route('sales.follow-ups.snooze', $fu), ['date' => today()->subDay()->toDateString()])
            ->assertSessionHasErrors('date');
    }
}
