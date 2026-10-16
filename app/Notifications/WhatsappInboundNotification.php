<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WhatsappInboundNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(
        public int $accountId,
        public string $accountName,
        public string $sender,
        public string $preview,
    ) {
    }

    /**
     * @return array<int, string>
     */
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
            ->subject('Pesan WhatsApp baru')
            ->line('Pesan masuk baru membutuhkan penanganan:')
            ->line(trim($this->accountName . ' • ' . $this->sender, ' •') . ': ' . $this->preview)
            ->action('Buka WhatsApp Center', url(route('whatsapp-center.index')))
            ->line('Terima kasih.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'whatsapp',
            'account_id' => $this->accountId,
            'sender' => $this->sender,
            'customer' => trim($this->accountName.' • '.$this->sender, ' •'),
            'preview' => $this->preview,
            'url' => route('whatsapp-center.index'),
        ];
    }

    protected function pushContent(): array
    {
        return [
            'title' => __('Pesan WhatsApp baru'),
            'body' => trim($this->accountName . ' • ' . $this->sender, ' •') . ': ' . $this->preview,
            'url' => route('whatsapp-center.index'),
        ];
    }
}
