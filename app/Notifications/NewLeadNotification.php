<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewLeadNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsWebPush;

    public function __construct(public Lead $lead) {}

    public static function indexUrl(): string
    {
        return route('manage-sales.index');
    }

    /**
     * Channel mengikuti preferensi notifikasi pengguna
     * (Profil > Setting > Notifikasi).
     */
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
            'lead_id' => $this->lead->id,
            'customer' => $this->lead->customer?->name ?? 'Lead baru',
            'url' => self::indexUrl(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Lead Baru Diterima')
            ->line('Lead baru masuk dan membutuhkan penanganan:')
            ->line('Customer: ' . ($this->lead->customer?->name ?? 'Lead baru'))
            ->action('Kelola Lead', url(self::indexUrl()))
            ->line('Terima kasih.');
    }

    protected function pushContent(): array
    {
        return [
            'title' => 'Lead baru diterima',
            'body' => 'Customer: ' . ($this->lead->customer?->name ?? 'Lead baru'),
            'url' => self::indexUrl(),
        ];
    }
}