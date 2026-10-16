<?php

namespace App\Notifications;

use App\Models\TechnicianSchedule;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Pengingat otomatis sebelum jadwal dimulai. */
class TechnicianScheduleReminderNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public TechnicianSchedule $schedule) {}

    public function via(object $notifiable): array
    {
        if ($this->muted($notifiable)) {
            return [];
        }

        return $this->withWebPush($notifiable, $this->withMail($notifiable, ['database']));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pengingat jadwal teknisi')
            ->line($this->schedule->title . ' dimulai ' . $this->schedule->start_at->format('d M Y H:i'))
            ->action('Lihat Jadwal', url(route('teknisi.jadwal')))
            ->line('Terima kasih.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'schedule',
            'schedule_id' => $this->schedule->id,
            'customer' => $this->schedule->title,
            'preview' => 'Dimulai ' . $this->schedule->start_at->format('d M Y H:i'),
            'url' => route('teknisi.jadwal'),
        ];
    }

    protected function pushContent(): array
    {
        return [
            'title' => __('Technician schedule reminder'),
            'body' => $this->schedule->title . ' dimulai ' . $this->schedule->start_at->format('d M Y H:i'),
            'url' => route('teknisi.jadwal'),
        ];
    }
}
