<?php

namespace App\Events;

use App\Models\DatabaseNotification as DatabaseNotificationModel;
use App\Support\NotificationPresenter;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Satu notifikasi database baru siap ditampilkan — broadcast privat ke
 * channel user penerima.
 *
 * `ShouldBroadcastNow`: server ini memproses queue lewat cron per menit,
 * jadi lewat queue notifikasi bisa telat ~60 detik. `ShouldRescue`
 * memastikan Reverb mati / error broadcast tidak pernah menggagalkan
 * request pembuat notifikasi (hanya dicatat di log).
 */
class NotificationCreated implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, SerializesModels;

    public function __construct(public DatabaseNotificationModel $notification)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('notifications.'.$this->notification->notifiable_id);
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * Bentuk item seragam dari presenter + angka unread terbaru, supaya
     * frontend tidak perlu fetch ulang saat kartu baru masuk.
     */
    public function broadcastWith(): array
    {
        $user = $this->notification->notifiable;

        return [
            'item' => NotificationPresenter::present($this->notification),
            'unread' => $user ? $user->unreadNotifications()->count() : 0,
        ];
    }
}
