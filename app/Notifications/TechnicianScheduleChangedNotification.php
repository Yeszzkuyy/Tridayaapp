<?php

namespace App\Notifications;

use App\Models\TechnicianSchedule;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dikirim saat jadwal diubah atau dibatalkan. */
class TechnicianScheduleChangedNotification extends Notification
{
    use Queueable, SendsWebPush;

    /**
     * @param 'updated'|'cancelled'|'deleted' $change
     */
    public function __construct(
        public TechnicianSchedule $schedule,
        public string $change = 'updated',
    ) {}

    public function via(object $notifiable): array
    {
        if ($this->muted($notifiable)) {
            return [];
        }

        return $this->withWebPush($notifiable, $this->withMail($notifiable, ['database']));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cancelled = $this->change === 'cancelled' || $this->change === 'deleted';

        return (new MailMessage)
            ->subject($cancelled ? 'Jadwal engineer dibatalkan' : 'Jadwal engineer berubah')
            ->line($this->schedule->title . ' — ' . $this->describe())
            ->action('Lihat Jadwal', url(route('teknisi.jadwal')))
            ->line('Terima kasih.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'schedule',
            'schedule_id' => $this->schedule->id,
            'customer' => $this->schedule->title,
            'preview' => $this->describe(),
            'url' => route('teknisi.jadwal'),
        ];
    }

    protected function pushContent(): array
    {
        return [
            'title' => $this->change === 'cancelled' || $this->change === 'deleted'
                ? 'Jadwal engineer dibatalkan'
                : 'Jadwal engineer berubah',
            'body' => $this->schedule->title . ' — ' . $this->describe(),
            'url' => route('teknisi.jadwal'),
        ];
    }

    private function describe(): string
    {
        return match ($this->change) {
            'cancelled', 'deleted' => 'jadwal dibatalkan',
            default => 'jadwal diperbarui: ' . $this->schedule->start_at->format('d M Y H:i'),
        };
    }
}
