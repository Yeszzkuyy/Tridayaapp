<?php

namespace Tests\Feature;

use App\Livewire\LeadTaskChat;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class LeadTaskChatTest extends TestCase
{
    use RefreshDatabase;

    private function makeTask(): array
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $sales = User::factory()->create();
        $sales->assignRole('sales');
        $inside = User::factory()->create();
        $inside->assignRole('inside-sales');

        $customer = Customer::create(['name' => 'PT Diskusi']);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'hot',
            'incoming_date' => now()->toDateString(),
            'assigned_to' => $sales->id,
        ]);
        $task = LeadTask::create([
            'lead_id' => $lead->id,
            'title' => 'Buatkan penawaran',
            'assigned_to' => $inside->id,
            'created_by' => $sales->id,
            'status' => 'todo',
        ]);

        return [$sales, $inside, $task];
    }

    public function test_sales_can_send_and_sees_bubble_with_polling(): void
    {
        [$sales, $inside, $task] = $this->makeTask();

        Livewire::actingAs($sales)
            ->test(LeadTaskChat::class, ['task' => $task])
            ->assertSee('wire:poll.5s', false)
            ->set('body', 'Tolong buatkan penawaran 10 unit ya.')
            ->call('send')
            ->assertSee('Tolong buatkan penawaran 10 unit ya.');

        $this->assertDatabaseHas('lead_task_comments', [
            'lead_task_id' => $task->id,
            'user_id' => $sales->id,
            'body' => 'Tolong buatkan penawaran 10 unit ya.',
        ]);
    }

    public function test_other_side_sees_new_message_on_refresh_and_is_notified(): void
    {
        Notification::fake();
        [$sales, $inside, $task] = $this->makeTask();

        $chat = Livewire::actingAs($sales)
            ->test(LeadTaskChat::class, ['task' => $task])
            ->assertDontSee('Siap, penawaran dikirim hari ini.');

        $task->comments()->create(['user_id' => $inside->id, 'body' => 'Siap, penawaran dikirim hari ini.']);

        $chat->call('$refresh')->assertSee('Siap, penawaran dikirim hari ini.');

        // Pengirim pesan diberi tahu lawan bicaranya.
        Livewire::actingAs($inside)
            ->test(LeadTaskChat::class, ['task' => $task])
            ->set('body', 'Ada revisi harga dari prinsipal.')
            ->call('send');

        Notification::assertSentTo($sales, \App\Notifications\LeadTaskNotification::class);
    }

    public function test_notification_links_sales_to_request_hub_with_relative_url(): void
    {
        Notification::fake();
        [$sales, $inside, $task] = $this->makeTask();

        Livewire::actingAs($inside)
            ->test(LeadTaskChat::class, ['task' => $task])
            ->set('body', 'Penawaran sudah saya siapkan.')
            ->call('send');

        Notification::assertSentTo($sales, \App\Notifications\LeadTaskNotification::class, function ($notification) use ($sales, $task) {
            $data = $notification->toDatabase($sales);

            return $data['lead_task_id'] === $task->id
                && $data['url'] === route('sales.requests.index', [
                    'tab' => 'inside-sales',
                    'task' => $task->id,
                ], false);
        });
    }

    public function test_notification_links_inside_sales_only_to_own_task_with_relative_url(): void
    {
        Notification::fake();
        [$sales, $inside, $task] = $this->makeTask();

        Livewire::actingAs($sales)
            ->test(LeadTaskChat::class, ['task' => $task])
            ->set('body', 'Tolong cek harga prinsipal.')
            ->call('send');

        Notification::assertSentTo($inside, \App\Notifications\LeadTaskNotification::class, function ($notification) use ($inside, $task) {
            $data = $notification->toDatabase($inside);

            return $data['url'] === route('lead-tasks.show', $task->id, false);
        });
    }

    public function test_stranger_cannot_open_chat(): void
    {
        [, , $task] = $this->makeTask();
        $stranger = User::factory()->create();
        $stranger->assignRole('sales');

        Livewire::actingAs($stranger)
            ->test(LeadTaskChat::class, ['task' => $task])
            ->assertForbidden();
    }

    public function test_repeated_replies_keep_one_unread_notification_per_task(): void
    {
        [$sales, $inside, $task] = $this->makeTask();

        foreach (['Pesan pertama', 'Pesan kedua', 'Pesan ketiga'] as $body) {
            Livewire::actingAs($inside)
                ->test(LeadTaskChat::class, ['task' => $task])
                ->set('body', $body)
                ->call('send');
        }

        // Chat beruntun tidak menumpuk: cukup satu notifikasi belum dibaca.
        $unread = $sales->unreadNotifications()->get();
        $this->assertCount(1, $unread);

        $data = $unread->first()->data;
        $this->assertSame('comment', $data['event']);
        $this->assertSame('Balasan baru di task: '.$task->title, $data['title']);
        $this->assertStringContainsString($inside->name, $data['preview']);
        $this->assertStringContainsString('Pesan pertama', $data['preview']);
    }

    public function test_new_reply_notifies_again_after_previous_one_is_read(): void
    {
        [$sales, $inside, $task] = $this->makeTask();

        Livewire::actingAs($inside)
            ->test(LeadTaskChat::class, ['task' => $task])
            ->set('body', 'Balasan pertama')
            ->call('send');

        $sales->unreadNotifications()->update(['read_at' => now()]);

        Livewire::actingAs($inside)
            ->test(LeadTaskChat::class, ['task' => $task])
            ->set('body', 'Balasan kedua')
            ->call('send');

        $this->assertCount(1, $sales->unreadNotifications()->get());
        $this->assertSame(2, $sales->notifications()->count());
        $this->assertStringContainsString(
            'Balasan kedua',
            $sales->unreadNotifications()->first()->data['preview']
        );
    }

    public function test_notification_center_collapses_stacked_task_reply_notifications(): void
    {
        [$sales, $inside, $task] = $this->makeTask();

        foreach (['Paling lama', 'Tengah', 'Paling baru'] as $index => $preview) {
            $sales->notifications()->create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => \App\Notifications\LeadTaskNotification::class,
                'created_at' => now()->subMinutes(3 - $index),
                'data' => [
                    'type' => 'task',
                    'event' => 'comment',
                    'title' => 'Komentar baru',
                    'lead_task_id' => $task->id,
                    'preview' => $preview,
                    'url' => '/lead-tasks/'.$task->id,
                ],
            ]);
        }

        $this->actingAs($sales)->get(route('notifications.status'))->assertOk();

        $remaining = $sales->unreadNotifications()->get()->filter(
            fn ($notification) => (int) ($notification->data['lead_task_id'] ?? 0) === $task->id
        );

        $this->assertCount(1, $remaining);
        $this->assertSame('Paling baru', $remaining->first()->data['preview']);
        $this->assertNotNull($inside);
    }

    public function test_messages_render_in_chronological_order_oldest_first(): void
    {
        [$sales, $inside, $task] = $this->makeTask();

        $task->comments()->create(['user_id' => $sales->id, 'body' => 'Pesan pertama dari Adi']);
        $task->comments()->create(['user_id' => $inside->id, 'body' => 'Balasan kedua dari Yeski']);

        $html = Livewire::actingAs($sales)
            ->test(LeadTaskChat::class, ['task' => $task])
            ->html();

        $posFirst = strpos($html, 'Pesan pertama dari Adi');
        $posSecond = strpos($html, 'Balasan kedua dari Yeski');

        $this->assertNotFalse($posFirst);
        $this->assertNotFalse($posSecond);
        $this->assertTrue($posFirst < $posSecond, 'Pesan pertama harus muncul sebelum balasan kedua dalam HTML.');
    }
}
