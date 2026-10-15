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

    public function test_stranger_cannot_open_chat(): void
    {
        [, , $task] = $this->makeTask();
        $stranger = User::factory()->create();
        $stranger->assignRole('sales');

        Livewire::actingAs($stranger)
            ->test(LeadTaskChat::class, ['task' => $task])
            ->assertForbidden();
    }
}
