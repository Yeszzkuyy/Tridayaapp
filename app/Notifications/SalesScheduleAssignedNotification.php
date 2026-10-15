<?php

namespace App\Notifications;

use App\Models\SalesSchedule;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dikirim ke PIC saat schedule dibuat/diubah oleh orang lain. */
class SalesScheduleAssignedNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public SalesSchedule $schedule) {}

    public function via(object $notifiable): array
    {
        if ($this->muted($notifiable)) {
            return [];
        }

        $channels = [];

        if ($notifiable->preference('notify_system', true)) {
            $channels[] = 'database';
        }

        $channels = $this->withMail($notifiable, $channels);

        return $this->withWebPush($notifiable, $channels);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'sales_schedule_assigned',
            'sales_schedule_id' => $this->schedule->id,
            'lead_id' => $this->schedule->lead_id,
            'title' => $this->schedule->title,
            'start_at' => $this->schedule->start_at?->toISOString(),
            'url' => route('sales.schedules.show', $this->schedule->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Jadwal Sales Baru Untuk Anda')
            ->line('Jadwal: ' . $this->schedule->title)
            ->line('Waktu: ' . ($this->schedule->start_at?->format('d M Y H:i') ?? '-'))
            ->action('Lihat Jadwal', url(route('sales.schedules.show', $this->schedule->id)))
            ->line('Terima kasih.');
    }

    protected function pushContent(): array
    {
        return [
            'title' => 'Jadwal sales baru',
            'body' => $this->schedule->title,
            'url' => route('sales.schedules.show', $this->schedule->id),
        ];
    }
}
