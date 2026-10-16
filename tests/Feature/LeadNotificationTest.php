<?php

namespace Tests\Feature;

use App\Http\Controllers\NotificationController;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\LeadAssignedNotification;
use App\Notifications\NewLeadNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeadNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function loginAs(string $role): User
    {
        Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function storeLead(array $overrides = []): void
    {
        $customer = Customer::create(['name' => 'PT Notifikasi']);

        $this->post(route('leads.store'), array_merge([
            'customer_mode' => 'existing',
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'kebutuhan' => 'CCTV',
            'incoming_date' => now()->toDateString(),
        ], $overrides))->assertRedirect(route('leads.index'));
    }

    public function test_store_queues_notification_and_redirects_with_flash(): void
    {
        Queue::fake();
        $this->actingAs($this->loginAs('marketing'));

        $managementUser = User::factory()->create();
        $managementUser->assignRole('management');

        $customer = Customer::create(['name' => 'PT Antre']);

        $this->post(route('leads.store'), [
            'customer_mode' => 'existing',
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'kebutuhan' => 'IP Phone 10 unit.',
            'incoming_date' => now()->toDateString(),
        ])
            ->assertRedirect(route('leads.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('success_card', true);

        $this->assertSame(1, Lead::count());
        // Notifikasi wajib antre (tidak dikirim inline agar submit tetap cepat).
        Queue::assertPushed(SendQueuedNotifications::class);
    }

    public function test_unassigned_new_lead_notifies_management_users_only(): void
    {
        Notification::fake();
        $this->actingAs($this->loginAs('marketing'));

        $managementUser = User::factory()->create();
        $managementUser->assignRole('management');

        $marketingUser = User::factory()->create();
        $marketingUser->assignRole('marketing');

        $this->storeLead();

        Notification::assertSentTo($managementUser, NewLeadNotification::class);
        Notification::assertNotSentTo($marketingUser, NewLeadNotification::class);
    }

    public function test_assigned_new_lead_does_not_notify_management(): void
    {
        Notification::fake();
        $this->actingAs($this->loginAs('marketing'));

        $managementUser = User::factory()->create();
        $managementUser->assignRole('management');

        $sales = User::factory()->create();
        $sales->assignRole('sales');

        $this->storeLead(['assigned_to' => $sales->id]);

        Notification::assertNotSentTo($managementUser, NewLeadNotification::class);
    }

    public function test_assigned_new_lead_notifies_only_the_assignee(): void
    {
        Notification::fake();
        $marketing = $this->loginAs('marketing');
        $this->actingAs($marketing);

        $salesA = User::factory()->create();
        $salesA->assignRole('sales');
        $salesB = User::factory()->create();
        $salesB->assignRole('sales');

        $this->storeLead(['assigned_to' => $salesA->id]);

        Notification::assertSentTo($salesA, LeadAssignedNotification::class);
        Notification::assertNotSentTo($salesB, LeadAssignedNotification::class);
        Notification::assertNotSentTo($salesA, NewLeadNotification::class);

        $lead = Lead::latest()->first();
        $this->assertSame($salesA->id, $lead->assigned_to);
        $this->assertSame($marketing->id, $lead->assigned_by);
        $this->assertNotNull($lead->assigned_at);
    }

    public function test_update_changing_assignee_notifies_only_new_assignee(): void
    {
        Notification::fake();
        $this->actingAs($this->loginAs('marketing'));

        $salesA = User::factory()->create();
        $salesA->assignRole('sales');
        $salesB = User::factory()->create();
        $salesB->assignRole('sales');

        $customer = Customer::create(['name' => 'PT Ganti Sales']);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'assigned_to' => $salesA->id,
            'incoming_date' => now()->toDateString(),
        ]);

        $this->put(route('leads.update', $lead), [
            'customer_mode' => 'existing',
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'kebutuhan' => 'IP Phone 10 unit.',
            'assigned_to' => $salesB->id,
            'incoming_date' => now()->toDateString(),
        ])->assertRedirect(route('leads.index'));

        Notification::assertSentTo($salesB, LeadAssignedNotification::class);
        Notification::assertNotSentTo($salesA, LeadAssignedNotification::class);
    }

    public function test_update_without_assignee_change_sends_nothing(): void
    {
        Notification::fake();
        $this->actingAs($this->loginAs('marketing'));

        $sales = User::factory()->create();
        $sales->assignRole('sales');

        $customer = Customer::create(['name' => 'PT Tetap Sales']);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'assigned_to' => $sales->id,
            'incoming_date' => now()->toDateString(),
        ]);

        $this->put(route('leads.update', $lead), [
            'customer_mode' => 'existing',
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'assigned_to' => $sales->id,
            'kebutuhan' => 'Update kebutuhan',
            'incoming_date' => now()->toDateString(),
        ])->assertRedirect(route('leads.index'));

        Notification::assertNothingSent();
    }

    public function test_management_can_mark_all_notifications_read(): void
    {
        $management = $this->loginAs('management');
        $lead = Lead::create([
            'customer_id' => Customer::create(['name' => 'PT Notif Lagi'])->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'incoming_date' => now()->toDateString(),
        ]);

        $management->notify(new NewLeadNotification($lead));
        $this->assertSame(1, $management->unreadNotifications()->count());

        $this->actingAs($management)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $management->fresh()->unreadNotifications()->count());
    }

    public function test_user_can_mark_one_notification_read_and_delete_it(): void
    {
        $management = $this->loginAs('management');
        $lead = Lead::create([
            'customer_id' => Customer::create(['name' => 'PT Baca Hapus'])->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'incoming_date' => now()->toDateString(),
        ]);
        $management->notify(new NewLeadNotification($lead));
        $notificationId = $management->notifications()->first()->id;

        $this->actingAs($management)
            ->post(route('notifications.read', $notificationId))
            ->assertOk()
            ->assertJson(['unread' => 0]);

        $this->assertSame(1, $management->notifications()->count());
        $this->assertNotNull($management->notifications()->first()->read_at);

        $this->actingAs($management)
            ->delete(route('notifications.destroy', $notificationId))
            ->assertOk()
            ->assertJson(['unread' => 0]);

        $this->assertSame(0, $management->notifications()->count());
    }

    public function test_user_cannot_read_or_delete_another_users_notification(): void
    {
        $owner = $this->loginAs('management');
        $other = $this->loginAs('management');

        $lead = Lead::create([
            'customer_id' => Customer::create(['name' => 'PT Milik Orang'])->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'incoming_date' => now()->toDateString(),
        ]);
        $owner->notify(new NewLeadNotification($lead));
        $notificationId = $owner->notifications()->first()->id;

        $this->actingAs($other)
            ->post(route('notifications.read', $notificationId))
            ->assertNotFound();

        $this->actingAs($other)
            ->delete(route('notifications.destroy', $notificationId))
            ->assertNotFound();

        $this->assertSame(1, $owner->notifications()->count());
    }

    public function test_status_endpoint_returns_unread_and_unassigned(): void
    {
        $management = $this->loginAs('management');
        $lead = Lead::create([
            'customer_id' => Customer::create(['name' => 'PT Notif JSON'])->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'incoming_date' => now()->toDateString(),
        ]);
        $management->notify(new NewLeadNotification($lead));

        $this->actingAs($management)
            ->get(route('notifications.status'))
            ->assertOk()
            ->assertJson(['unread' => 1, 'unassigned' => 1])
            ->assertJsonPath('items.0.body', 'PT Notif JSON')
            ->assertJsonPath('items.0.type', 'lead');
    }

    public function test_assigned_lead_removes_its_notifications(): void
    {
        $management = $this->loginAs('management');
        $sales = User::factory()->create();
        $sales->assignRole('sales');

        $lead = Lead::create([
            'customer_id' => Customer::create(['name' => 'PT Assign Hapus Notif'])->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'incoming_date' => now()->toDateString(),
        ]);
        $management->notify(new NewLeadNotification($lead));

        $this->actingAs($management)
            ->post(route('manage-sales.assign', $lead), ['assigned_to' => $sales->id])
            ->assertRedirect(route('manage-sales.index'));

        $this->assertSame(0, $management->fresh()->unreadNotifications()->count());
        $this->assertSame(0, $management->fresh()->notifications()->count());
    }

    public function test_assigned_notification_links_to_my_leads(): void
    {
        $sales = $this->loginAs('sales');
        $lead = Lead::create([
            'customer_id' => Customer::create(['name' => 'PT Notif My Leads'])->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'assigned_to' => $sales->id,
            'incoming_date' => now()->toDateString(),
        ]);
        $sales->notify(new LeadAssignedNotification($lead));

        $expected = route('sales.my-leads', ['search' => 'PT Notif My Leads']);
        $this->assertSame($expected, $sales->notifications()->first()->data['url']);

        $items = NotificationController::itemsFor($sales);
        $this->assertCount(1, $items);
        $this->assertSame($expected, $items[0]['url']);
    }

    public function test_items_for_repairs_legacy_assigned_url_to_my_leads(): void
    {
        $sales = $this->loginAs('sales');
        $lead = Lead::create([
            'customer_id' => Customer::create(['name' => 'PT Notif Lama'])->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'assigned_to' => $sales->id,
            'incoming_date' => now()->toDateString(),
        ]);
        // Baris lama yang masih menyimpan URL leads.show.
        $sales->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => LeadAssignedNotification::class,
            'data' => [
                'type' => 'assigned',
                'lead_id' => $lead->id,
                'customer' => 'PT Notif Lama',
                'url' => route('leads.show', $lead),
            ],
        ]);

        $items = NotificationController::itemsFor($sales);
        $this->assertCount(1, $items);
        $this->assertSame(route('sales.my-leads', ['search' => 'PT Notif Lama']), $items[0]['url']);
    }

    public function test_reassign_removes_previous_sales_assigned_notification(): void
    {
        $management = $this->loginAs('management');
        $salesA = User::factory()->create();
        $salesA->assignRole('sales');
        $salesB = User::factory()->create();
        $salesB->assignRole('sales');

        $lead = Lead::create([
            'customer_id' => Customer::create(['name' => 'PT Reassign Notif'])->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'assigned_to' => $salesA->id,
            'incoming_date' => now()->toDateString(),
        ]);
        $salesA->notify(new LeadAssignedNotification($lead));
        $this->assertSame(1, $salesA->notifications()->count());

        $this->actingAs($management)
            ->post(route('manage-sales.assign', $lead), ['assigned_to' => $salesB->id])
            ->assertRedirect(route('manage-sales.index'));

        $this->assertSame(0, $salesA->fresh()->notifications()->count());
        $this->assertSame(1, $salesB->fresh()->notifications()
            ->where('type', LeadAssignedNotification::class)->count());
    }

    public function test_items_for_drops_stale_assigned_notification(): void
    {
        $salesA = $this->loginAs('sales');
        $salesB = User::factory()->create();
        $salesB->assignRole('sales');

        $lead = Lead::create([
            'customer_id' => Customer::create(['name' => 'PT Basi Notif'])->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'assigned_to' => $salesA->id,
            'incoming_date' => now()->toDateString(),
        ]);
        $salesA->notify(new LeadAssignedNotification($lead));

        // Lead pindah tangan tanpa lewat controller (simulasi baris basi).
        $lead->forceFill(['assigned_to' => $salesB->id])->saveQuietly();

        $this->assertSame([], NotificationController::itemsFor($salesA));
        $this->assertSame(0, $salesA->fresh()->notifications()->count());
    }

    public function test_items_for_drops_management_notification_for_sales(): void
    {
        $sales = $this->loginAs('sales');
        $lead = Lead::create([
            'customer_id' => Customer::create(['name' => 'PT Nyasar Notif'])->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'incoming_date' => now()->toDateString(),
        ]);
        // Baris NewLead (untuk management) yang nyasar ke sales.
        $sales->notify(new NewLeadNotification($lead));
        $this->assertSame(1, $sales->notifications()->count());

        $this->actingAs($sales)
            ->get(route('notifications.status'))
            ->assertOk()
            ->assertJson(['unread' => 0, 'items' => []]);

        $this->assertSame(0, $sales->fresh()->notifications()->count());
    }

    public function test_new_lead_notification_links_to_manage_sales(): void
    {
        $management = $this->loginAs('management');
        $lead = Lead::create([
            'customer_id' => Customer::create(['name' => 'PT Notif Kelola'])->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'incoming_date' => now()->toDateString(),
        ]);
        $management->notify(new NewLeadNotification($lead));

        $expected = route('manage-sales.index');
        $this->assertSame($expected, $management->notifications()->first()->data['url']);

        $items = NotificationController::itemsFor($management);
        $this->assertCount(1, $items);
        $this->assertSame($expected, $items[0]['url']);
    }

    public function test_items_for_repairs_legacy_new_lead_url_to_manage_sales(): void
    {
        $management = $this->loginAs('management');
        $lead = Lead::create([
            'customer_id' => Customer::create(['name' => 'PT Notif Lama Kelola'])->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'incoming_date' => now()->toDateString(),
        ]);
        // Baris lama: tanpa URL, atau masih menyimpan URL halaman edit.
        $management->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => NewLeadNotification::class,
            'data' => [
                'type' => 'lead',
                'lead_id' => $lead->id,
                'customer' => 'PT Notif Lama Kelola',
                'url' => route('manage-sales.edit', $lead),
            ],
        ]);

        $items = NotificationController::itemsFor($management);
        $this->assertCount(1, $items);
        $this->assertSame(route('manage-sales.index'), $items[0]['url']);
    }

    public function test_management_can_open_lead_page_from_notification_link(): void
    {
        $management = $this->loginAs('management');
        $lead = Lead::create([
            'customer_id' => Customer::create(['name' => 'PT Link Notif'])->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'incoming_date' => now()->toDateString(),
        ]);

        $this->actingAs($management)
            ->get(route('manage-sales.edit', $lead))
            ->assertOk()
            ->assertSee('Manage Sales');
    }
}
