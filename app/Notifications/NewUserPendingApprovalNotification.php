<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewUserPendingApprovalNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public User $user) {}

    public function via(object $notifiable): array
    {
        if ($this->muted($notifiable)) {
            return [];
        }

        $channels = [];

        if ($notifiable->preference('notify_system', true)) {
            $channels[] = 'database';
        }

        return $this->withWebPush($notifiable, $channels);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'user_pending_approval',
            'user_id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'title' => __('New User Waiting for Role'),
            'body' => __(':name (:email) baru saja mendaftar via Google dan menunggu persetujuan role.', [
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'url' => route('admin-panel.users.edit', $this->user->id, false),
        ];
    }

    protected function pushUrl(object $notifiable): string
    {
        return route('admin-panel.users.edit', $this->user->id);
    }

    protected function pushContent(): array
    {
        return [
            'title' => __('New User Waiting for Role'),
            'body' => "{$this->user->name} ({$this->user->email})",
            'url' => route('admin-panel.users.edit', $this->user->id),
        ];
    }
}
