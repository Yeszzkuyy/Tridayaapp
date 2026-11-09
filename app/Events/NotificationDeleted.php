<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Notifikasi telah dihapus — broadcast privat ke channel user penerima
 * agar item langsung hilang di seluruh tab/perangkat pengguna.
 */
class NotificationDeleted implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<int, string> $ids
     */
    public function __construct(
        public int|string $userId,
        public array $ids,
        public int $unread,
        public string $scope = 'single'
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('notifications.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'notification.deleted';
    }

    /**
     * @return array{ids: array<int, string>, unread: int, scope: string}
     */
    public function broadcastWith(): array
    {
        return [
            'ids' => $this->ids,
            'unread' => $this->unread,
            'scope' => $this->scope,
        ];
    }
}
