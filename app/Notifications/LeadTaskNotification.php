<?php

namespace App\Notifications;

use App\Models\LeadTask;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dikirim saat inside sales task dibuat/di-assign atau statusnya berubah. */
class LeadTaskNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public LeadTask $task, public string $event = 'created') {}

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
            'type' => 'task',
            'title' => match ($this->event) {
                'created' => 'Task baru',
                'comment' => 'Komentar baru',
                default => 'Status task',
            },
            'lead_id' => $this->task->lead_id,
            'customer' => $this->task->lead?->customer?->name ?? 'Lead task',
            'preview' => $this->task->title,
            'url' => route('lead-tasks.show', $this->task->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(match ($this->event) {
                'created' => 'Inside Sales Task Baru',
                'comment' => 'Komentar Baru di Task',
                default => 'Status Task Berubah',
            })
            ->line('Task: ' . $this->task->title)
            ->line('Lead: ' . ($this->task->lead?->customer?->name ?? '-'))
            ->action('Lihat Task', url(route('lead-tasks.show', $this->task->id)))
            ->line('Terima kasih.');
    }

    protected function pushContent(): array
    {
        return [
            'title' => match ($this->event) {
                'created' => 'Inside sales task baru',
                'comment' => 'Komentar baru di task',
                default => 'Status task berubah',
            },
            'body' => $this->task->title,
            'url' => route('lead-tasks.show', $this->task->id),
        ];
    }
}
