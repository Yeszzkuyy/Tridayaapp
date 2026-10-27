<?php

namespace App\Notifications;

use App\Models\Project;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dikirim ke Lead Teknisi saat Sales meng-convert proposal menjadi project. */
class ProjectConvertedNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public Project $project) {}

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
            'type' => 'project_converted',
            'project_id' => $this->project->id,
            'customer_id' => $this->project->customer_id,
            'title' => $this->project->project_name,
            // Relatif: klik di aplikasi tetap di domain tempat user login.
            'url' => route('projects.show', $this->project->id, false),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Project Baru dari Sales')
            ->line('Customer: '.($this->project->customer?->name ?? '-'))
            ->line('Project: '.$this->project->project_name)
            ->action('Lihat Project', url(route('projects.show', $this->project->id)))
            ->line('Terima kasih.');
    }

    protected function pushUrl(object $notifiable): string
    {
        return route('projects.show', $this->project->id);
    }

    protected function pushContent(): array
    {
        return [
            'title' => __('New Project from Sales'),
            'body' => $this->project->project_name,
            'url' => route('projects.show', $this->project->id),
        ];
    }
}
