<?php

namespace App\Notifications;

use App\Models\TechnicalRequest;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dikirim ke Sales (requester) saat pekerjaan teknis selesai + hasil tersedia. */
class TechnicalWorkCompletedNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public TechnicalRequest $request) {}

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
            'type' => 'technical_work_completed',
            'technical_request_id' => $this->request->id,
            'lead_id' => $this->request->lead_id,
            'title' => $this->request->title,
            'url' => route('sales.technical-requests.show', $this->request->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Hasil Teknis Sudah Tersedia')
            ->line('Pekerjaan teknis untuk "' . $this->request->title . '" telah selesai.')
            ->line('Silakan lihat hasilnya dan lanjutkan ke penawaran bila sesuai.')
            ->action('Lihat Hasil', url(route('sales.technical-requests.show', $this->request->id)))
            ->line('Terima kasih.');
    }

    protected function pushContent(): array
    {
        return [
            'title' => 'Hasil teknis tersedia',
            'body' => $this->request->title,
            'url' => route('sales.technical-requests.show', $this->request->id),
        ];
    }
}
