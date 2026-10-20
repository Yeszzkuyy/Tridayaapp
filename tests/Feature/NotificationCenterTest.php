<?php

namespace Tests\Feature;

use App\Events\NotificationCreated;
use App\Http\Controllers\NotificationController;
use App\Models\User;
use App\Notifications\FollowUpOverdueNotification;
use App\Notifications\NewLeadNotification;
use App\Notifications\ProposalStatusNotification;
use App\Notifications\TechnicalRequestCreatedNotification;
use App\Notifications\TechnicianScheduleAssignedNotification;
use App\Notifications\WhatsappHandoffNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        // RoleAndPermissionSeeder menghapus & membuat ulang semua role, jadi
        // hanya seed saat tabel role memang masih kosong (sekali per test).
        if (\Spatie\Permission\Models\Role::where('name', $role)->doesntExist()) {
            Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeNotification(User $user, string $class, array $data): string
    {
        $id = (string) Str::uuid();
        $user->notifications()->create(['id' => $id, 'type' => $class, 'data' => $data]);

        return $id;
    }

    public function test_bell_renders_for_every_role(): void
    {
        // Bell Notification Center tidak lagi digate permission: semua role
        // yang bisa menerima notifikasi database harus melihat bell.
        foreach (['technician', 'lead-technician', 'marketing', 'admin', 'inside-sales', 'sales'] as $role) {
            $user = $this->makeUser($role);

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('data-notification-center', false);
        }
    }

    public function test_status_paginates_ten_items_with_cursor(): void
    {
        $user = $this->makeUser('management');

        // 25 baris dibuat dalam detik yang sama — kasus terberat untuk cursor
        // komposit (created_at, id) harus tetap tanpa duplikat/kehilangan.
        for ($i = 0; $i < 25; $i++) {
            $this->makeNotification($user, NewLeadNotification::class, [
                'type' => 'lead',
                'lead_id' => $i + 1,
                'customer' => 'PT Notif '.$i,
                'url' => route('manage-sales.index'),
            ]);
        }

        $page1 = $this->actingAs($user)->get(route('notifications.status'))->assertOk()->json();
        $this->assertCount(10, $page1['items']);
        $this->assertTrue($page1['has_more']);
        $this->assertNotNull($page1['next_cursor']);
        $this->assertSame(25, $page1['unread']);

        $page2 = $this->get(route('notifications.feed', ['cursor' => $page1['next_cursor']]))->assertOk()->json();
        $this->assertCount(10, $page2['items']);
        $this->assertTrue($page2['has_more']);

        $page3 = $this->get(route('notifications.feed', ['cursor' => $page2['next_cursor']]))->assertOk()->json();
        $this->assertCount(5, $page3['items']);
        $this->assertFalse($page3['has_more']);
        $this->assertNull($page3['next_cursor']);

        $ids = array_merge(
            array_column($page1['items'], 'id'),
            array_column($page2['items'], 'id'),
            array_column($page3['items'], 'id'),
        );
        $this->assertCount(25, array_unique($ids));
    }

    public function test_clear_all_removes_read_and_unread(): void
    {
        $user = $this->makeUser('management');

        $this->makeNotification($user, NewLeadNotification::class, ['type' => 'lead', 'customer' => 'PT A', 'url' => '/']);
        $readId = $this->makeNotification($user, NewLeadNotification::class, ['type' => 'lead', 'customer' => 'PT B', 'url' => '/']);
        $user->notifications()->find($readId)->markAsRead();

        $this->actingAs($user)
            ->delete(route('notifications.destroy-all'))
            ->assertOk()
            ->assertJson(['unread' => 0]);

        $this->assertSame(0, $user->fresh()->notifications()->count());
    }

    public function test_read_all_returns_json_without_redirect(): void
    {
        $user = $this->makeUser('sales');
        $this->makeNotification($user, FollowUpOverdueNotification::class, ['type' => 'followup', 'customer' => 'PT A', 'url' => '/']);

        $this->actingAs($user)
            ->post(route('notifications.read-all'), [], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['unread' => 0]);

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_presenter_gives_uniform_payload_per_type(): void
    {
        $user = $this->makeUser('management');

        $this->makeNotification($user, ProposalStatusNotification::class, [
            'type' => 'proposal_status', 'proposal_number' => 'Q/2026/10/0001',
            'event' => 'pending_technical', 'url' => '/proposals/1',
        ]);
        $this->makeNotification($user, TechnicalRequestCreatedNotification::class, [
            'type' => 'technical_request_created', 'title' => 'Pasang CCTV',
            'type_label' => 'Installation', 'url' => '/tech/1',
        ]);
        $this->makeNotification($user, TechnicianScheduleAssignedNotification::class, [
            'type' => 'schedule', 'schedule_id' => 1, 'customer' => 'Survey PT A',
            'preview' => '15 Okt 2026 09:00', 'url' => '/jadwal',
        ]);
        $this->makeNotification($user, WhatsappHandoffNotification::class, [
            'type' => 'whatsapp', 'reason' => 'limit', 'customer' => 'Acc • 08123',
            'preview' => 'Halo, mau tanya', 'url' => '/wa',
        ]);
        $this->makeNotification($user, FollowUpOverdueNotification::class, [
            'type' => 'followup', 'title' => 'Follow up jatuh tempo', 'customer' => 'PT B',
            'preview' => 'Telpon lagi besok', 'url' => '/detail-lama',
        ]);

        $items = collect(NotificationController::itemsFor($user))->keyBy('type');

        // Bentuk seragam untuk semua tipe.
        foreach ($items as $item) {
            foreach (['id', 'type', 'app', 'title', 'body', 'chips', 'url', 'read', 'created_at'] as $key) {
                $this->assertArrayHasKey($key, $item);
            }
        }

        $this->assertSame(__('Update proposal'), $items['proposal']['title']);
        $this->assertSame('Q/2026/10/0001', $items['proposal']['body']);
        $this->assertSame(__('Menunggu review teknis'), $items['proposal']['chips'][0]['label']);
        $this->assertSame('warning', $items['proposal']['chips'][0]['tone']);

        $this->assertSame(__('New technical request'), $items['technical_request_created']['title']);
        $this->assertSame(__('Installation'), $items['technical_request_created']['chips'][0]['label']);

        $this->assertSame(__('Jadwal'), $items['schedule_assigned']['app']);
        $this->assertSame(__('Jadwal teknisi baru'), $items['schedule_assigned']['title']);
        $this->assertSame('15 Okt 2026 09:00', $items['schedule_assigned']['chips'][0]['label']);

        $this->assertSame(__('Butuh handoff WhatsApp'), $items['whatsapp']['title']);
        $this->assertSame(__('Bot mencapai batas balasan'), $items['whatsapp']['chips'][0]['label']);
        $this->assertSame('Acc • 08123: Halo, mau tanya', $items['whatsapp']['body']);

        // URL baris lama dirapikan ke bentuk kanonik (daftar follow-up).
        $this->assertSame(FollowUpOverdueNotification::indexUrl(), $items['followup']['url']);
    }

    public function test_creating_notification_dispatches_broadcast_event(): void
    {
        Event::fake([NotificationCreated::class]);

        $user = $this->makeUser('sales');
        $this->makeNotification($user, FollowUpOverdueNotification::class, ['type' => 'followup', 'customer' => 'PT A', 'url' => '/']);

        Event::assertDispatched(NotificationCreated::class);
    }

    public function test_broadcast_event_carries_presented_item_and_unread(): void
    {
        $user = $this->makeUser('sales');
        $id = $this->makeNotification($user, FollowUpOverdueNotification::class, [
            'type' => 'followup', 'title' => 'Follow up jatuh tempo', 'customer' => 'PT A',
            'preview' => 'Telpon lagi', 'url' => '/detail-lama',
        ]);

        $event = new NotificationCreated($user->notifications()->find($id));

        $this->assertSame('private-notifications.'.$user->id, $event->broadcastOn()->name);
        $this->assertSame('notification.created', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertSame($id, $payload['item']['id']);
        $this->assertSame('followup', $payload['item']['type']);
        $this->assertFalse($payload['item']['read']);
        $this->assertSame(FollowUpOverdueNotification::indexUrl(), $payload['item']['url']);
        $this->assertSame(1, $payload['unread']);
    }
}
