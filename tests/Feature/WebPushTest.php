<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\TechnicianSchedule;
use App\Models\User;
use App\Notifications\LeadAssignedNotification;
use App\Notifications\NewLeadNotification;
use App\Notifications\TechnicianScheduleReminderNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class WebPushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_push_subscription_can_be_stored_and_deleted(): void
    {
        $user = User::factory()->create();

        $payload = [
            'endpoint' => 'https://fcm.example.test/sub-123',
            'keys' => ['p256dh' => 'key-abc', 'auth' => 'token-xyz'],
        ];

        $this->actingAs($user)
            ->postJson(route('push-subscriptions.store'), $payload)
            ->assertOk();

        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint' => 'https://fcm.example.test/sub-123',
            'subscribable_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => 'https://fcm.example.test/sub-123'])
            ->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint' => 'https://fcm.example.test/sub-123',
        ]);
    }

    public function test_assign_lead_notifies_only_the_assignee(): void
    {
        Notification::fake();

        $manager = User::factory()->create();
        $manager->assignRole('management');
        $sales = User::factory()->create();
        $sales->assignRole('sales');

        $lead = Lead::create([
            'customer_id' => Customer::create(['name' => 'PT Uji'])->id,
            'status' => 'cool',
        ]);

        $this->actingAs($manager)
            ->post(route('manage-sales.assign', $lead), ['assigned_to' => $sales->id])
            ->assertRedirect();

        Notification::assertSentTo($sales, LeadAssignedNotification::class);
        Notification::assertNotSentTo($manager, LeadAssignedNotification::class);
    }

    public function test_schedule_reminder_sent_once_to_technician(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $owner->assignRole('management');
        $tech = User::factory()->create();
        $tech->assignRole('technician');

        TechnicianSchedule::create([
            'user_id' => $owner->id,
            'technician_user_id' => $tech->id,
            'title' => 'Instalasi AC',
            'start_at' => now()->addMinutes(30),
            'end_at' => now()->addHours(2),
            'status' => 'scheduled',
            'reminder_minutes' => 60,
        ]);

        $this->artisan('schedules:remind')->assertSuccessful();
        $this->artisan('schedules:remind')->assertSuccessful();

        Notification::assertSentToTimes($tech, TechnicianScheduleReminderNotification::class, 1);
        Notification::assertNotSentTo($owner, TechnicianScheduleReminderNotification::class);
    }

    public function test_webpush_channel_follows_notify_push_preference(): void
    {
        $user = User::factory()->create();
        $lead = Lead::create([
            'customer_id' => Customer::create(['name' => 'PT Dorong'])->id,
            'status' => 'cool',
        ]);

        $channels = (new NewLeadNotification($lead))->via($user);
        $this->assertContains(WebPushChannel::class, $channels);

        $user->preferences = array_merge($user->preferences ?? [], ['notify_push' => false]);
        $channels = (new NewLeadNotification($lead->fresh()))->via($user);
        $this->assertNotContains(WebPushChannel::class, $channels);
    }
}
