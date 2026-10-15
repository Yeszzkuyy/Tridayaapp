<?php

namespace App\Notifications\Concerns;

use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Channel database/push/email mengikuti preferensi pengguna
 * (Profil > Setting > Notifikasi). Setiap channel independen:
 * notify_system → database, notify_email → mail, notify_push → push.
 *
 * notify_email BUKAN master switch — mematikan email tidak boleh
 * mematikan notifikasi dalam aplikasi/push (masing-masing punya
 * toggle sendiri). Kelas memakai trait ini cukup mengimplementasikan
 * pushContent() ['title', 'body', 'url'].
 */
trait SendsWebPush
{
    /**
     * Tidak ada master-mute: bila semua channel dimatikan via(),
     * sudah menghasilkan [] tanpa perlu pintasan ini.
     */
    protected function muted(object $notifiable): bool
    {
        return false;
    }

    protected function withWebPush(object $notifiable, array $channels): array
    {
        if ($notifiable->preference('notify_push', true)) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    protected function withMail(object $notifiable, array $channels): array
    {
        if ($notifiable->preference('notify_email', true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** @return array{title: string, body: string, url: string} */
    abstract protected function pushContent(): array;

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        $content = $this->pushContent();

        return (new WebPushMessage)
            ->title($content['title'])
            ->body($content['body'])
            ->icon(url('/favicon.png'))
            ->badge(url('/favicon.png'))
            ->data(['url' => $content['url']])
            ->action('Lihat', 'open');
    }
}
