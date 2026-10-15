<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use App\Notifications\NewLeadNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class NotificationChannelTest extends TestCase
{
    use RefreshDatabase;

    private function notification(): NewLeadNotification
    {
        return new NewLeadNotification(new Lead());
    }

    public function test_default_channels_are_database_only(): void
    {
        $user = User::factory()->create();

        $this->assertSame(['database', 'mail', WebPushChannel::class], $this->notification()->via($user));
    }

    public function test_email_channel_is_used_when_enabled(): void
    {
        $user = User::factory()->create();
        $user->preferences = ['notify_system' => false, 'notify_email' => true];
        $user->save();

        $this->assertSame(['mail', WebPushChannel::class], $this->notification()->via($user));
    }

    public function test_no_channels_when_all_disabled(): void
    {
        $user = User::factory()->create();
        $user->preferences = ['notify_system' => false, 'notify_email' => false, 'notify_push' => false];
        $user->save();

        $this->assertSame([], $this->notification()->via($user));
    }

    public function test_email_disabled_keeps_database_and_push(): void
    {
        $user = User::factory()->create();
        $user->preferences = ['notify_system' => true, 'notify_email' => false, 'notify_push' => true];
        $user->save();

        // Email mati hanya mematikan mail — sistem & push tetap aktif.
        $this->assertSame(['database', WebPushChannel::class], $this->notification()->via($user));
    }

    public function test_all_notifications_include_mail_by_default(): void
    {
        $user = User::factory()->create();
        $schedule = new \App\Models\TechnicianSchedule([
            'title' => 'Tes',
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(2),
            'status' => 'scheduled',
        ]);

        $notifications = [
            new \App\Notifications\WhatsappInboundNotification(1, 'WA', '6281', 'halo'),
            new \App\Notifications\WhatsappHandoffNotification(1, 'WA', '6281', 'waiting'),
            new \App\Notifications\TechnicianScheduleAssignedNotification($schedule),
            new \App\Notifications\TechnicianScheduleChangedNotification($schedule, 'updated'),
            new \App\Notifications\TechnicianScheduleReminderNotification($schedule),
        ];

        foreach ($notifications as $notification) {
            $this->assertContains('mail', $notification->via($user), get_class($notification));
            $this->assertInstanceOf(
                \Illuminate\Notifications\Messages\MailMessage::class,
                $notification->toMail($user),
                get_class($notification)
            );
        }
    }

    public function test_mail_excluded_when_email_disabled(): void
    {
        $user = User::factory()->create();
        $user->preferences = ['notify_email' => false];
        $user->save();

        $schedule = new \App\Models\TechnicianSchedule([
            'title' => 'Tes',
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(2),
            'status' => 'scheduled',
        ]);

        $this->assertNotContains('mail', (new \App\Notifications\WhatsappInboundNotification(1, 'WA', '6281', 'halo'))->via($user));
        $this->assertContains('database', (new \App\Notifications\WhatsappInboundNotification(1, 'WA', '6281', 'halo'))->via($user));
        $this->assertNotContains('mail', (new \App\Notifications\TechnicianScheduleReminderNotification($schedule))->via($user));
        $this->assertContains('database', (new \App\Notifications\TechnicianScheduleReminderNotification($schedule))->via($user));
    }
}