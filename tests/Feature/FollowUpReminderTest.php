<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use App\Http\Controllers\NotificationController;
use App\Notifications\FollowUpOverdueNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FollowUpReminderTest extends TestCase
{
    use RefreshDatabase;

    private function salesUser(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('sales');

        return $user;
    }

    public function test_overdue_followup_notifies_assigned_sales(): void
    {
        $sales = $this->salesUser();
        $customer = Customer::create(['name' => 'PT Telat']);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'warm',
            'assigned_to' => $sales->id,
        ]);
        $followUp = FollowUp::create([
            'customer_id' => $customer->id,
            'lead_id' => $lead->id,
            'description' => 'Follow up penawaran',
            'follow_up_date' => now()->subDay()->toDateString(),
            'created_by' => $sales->id,
        ]);

        $this->artisan('followups:remind')->assertSuccessful();

        $this->assertNotNull($followUp->fresh()->reminder_sent_at);
        $this->assertTrue(
            $sales->notifications()->where('type', FollowUpOverdueNotification::class)->exists()
        );

        // Klik reminder harus ke daftar Follow Up (filter jatuh tempo), bukan detail.
        $this->assertSame(
            FollowUpOverdueNotification::indexUrl(),
            $sales->notifications()->first()->data['url']
        );

        // Idempoten: jalan kedua tidak mengirim lagi.
        $this->artisan('followups:remind')->assertSuccessful();
        $this->assertSame(1, $sales->notifications()->where('type', FollowUpOverdueNotification::class)->count());
    }

    public function test_future_followup_gets_no_reminder(): void
    {
        $sales = $this->salesUser();
        $customer = Customer::create(['name' => 'PT Besok']);
        FollowUp::create([
            'customer_id' => $customer->id,
            'description' => 'Follow up besok',
            'follow_up_date' => now()->addDay()->toDateString(),
            'created_by' => $sales->id,
        ]);

        $this->artisan('followups:remind')->assertSuccessful();

        $this->assertSame(0, $sales->notifications()->count());
    }

    public function test_items_for_repairs_legacy_followup_url_to_index(): void
    {
        $sales = $this->salesUser();
        $customer = Customer::create(['name' => 'PT Notif Lama']);
        $followUp = FollowUp::create([
            'customer_id' => $customer->id,
            'description' => 'Follow up lama',
            'follow_up_date' => now()->subDay()->toDateString(),
            'created_by' => $sales->id,
        ]);
        // Baris lama yang masih menyimpan URL halaman detail.
        $sales->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => FollowUpOverdueNotification::class,
            'data' => [
                'type' => 'followup',
                'title' => 'Follow up jatuh tempo',
                'follow_up_id' => $followUp->id,
                'customer' => 'PT Notif Lama',
                'url' => route('sales.follow-ups.show', $followUp),
            ],
        ]);

        $items = NotificationController::itemsFor($sales);
        $this->assertCount(1, $items);
        $this->assertSame(FollowUpOverdueNotification::indexUrl(), $items[0]['url']);
    }
}
