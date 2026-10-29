<?php

namespace App\Notifications;

use App\Models\LeadTask;
use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dikirim saat inside sales task dibuat/di-assign atau statusnya berubah. */
class LeadTaskNotification extends Notification implements ShouldQueue
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
                'quotation' => 'Quotation baru',
                'accepted' => 'Quotation diterima',
                default => 'Status task',
            },
            'lead_id' => $this->task->lead_id,
            'lead_task_id' => $this->task->id,
            'customer' => $this->task->lead?->customer?->name ?? 'Lead task',
            'preview' => $this->task->title,
            'url' => $this->databaseUrl($notifiable),
        ];
    }

    /**
     * URL relatif (tanpa domain) supaya klik notifikasi tidak berpindah host
     * dan kehilangan session. Sales/management diarahkan ke menu Request →
     * Inside Sales dengan diskusi terbuka; inside-sales murni ke task miliknya.
     */
    private function databaseUrl(object $notifiable): string
    {
        $useRequestHub = $notifiable instanceof User
            && ($notifiable->can('view-sales')
                || $notifiable->can('manage-sales')
                || $notifiable->can('manage-sales-leads'));

        return $useRequestHub
            ? route('sales.requests.index', ['tab' => 'inside-sales', 'task' => $this->task->id], false)
            : route('lead-tasks.show', $this->task->id, false);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(match ($this->event) {
                'created' => 'Inside Sales Task Baru',
                'comment' => 'Komentar Baru di Task',
                'quotation' => 'File Quotation Baru',
                'accepted' => 'Quotation Diterima',
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
                'quotation' => 'File quotation baru',
                'accepted' => 'Quotation diterima',
                default => 'Status task berubah',
            },
            'body' => $this->task->title,
            'url' => route('lead-tasks.show', $this->task->id),
        ];
    }
}
