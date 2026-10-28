<?php

namespace App\Notifications;

use App\Models\Instalasi;
use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke Admin & Finance saat teknisi menandai instalasi selesai
 * secara teknis. Admin perlu memeriksa kelengkapan lalu membuat BAST;
 * Finance hanya perlu tahu progresnya.
 */
class InstallationCompletedNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public Instalasi $instalasi) {}

    private function projectId(): ?int
    {
        return $this->instalasi->project_id ? (int) $this->instalasi->project_id : null;
    }

    private function showUrl(object $notifiable): string
    {
        if ($notifiable instanceof User && $notifiable->can('manage-admin') && $this->projectId()) {
            return route('admin.bast.edit', $this->projectId());
        }

        return route('admin.bast.index');
    }

    private function relativeUrl(object $notifiable): string
    {
        if ($notifiable instanceof User && $notifiable->can('manage-admin') && $this->projectId()) {
            return route('admin.bast.edit', $this->projectId(), false);
        }

        return route('admin.bast.index', [], false);
    }

    protected function pushUrl(object $notifiable): string
    {
        return $this->showUrl($notifiable);
    }

    public function via(object $notifiable): array
    {
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
            'type' => 'installation_completed',
            'installation_id' => $this->instalasi->id,
            'project_id' => $this->projectId(),
            'title' => $this->instalasi->project?->project_name ?? __('Installation'),
            'url' => $this->relativeUrl($notifiable),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Installation completed — BAST is ready to prepare'))
            ->line(__('Technician has completed the installation and uploaded the report.'))
            ->line(__('Project: :project', ['project' => $this->instalasi->project?->project_name ?? '-']))
            ->action(__('Open BAST'), url($this->showUrl($notifiable)))
            ->line(__('Please check the completeness and prepare the BAST document.'));
    }

    protected function pushContent(): array
    {
        return [
            'title' => __('Installation completed'),
            'body' => $this->instalasi->project?->project_name ?? __('BAST is ready to prepare'),
            'url' => route('admin.bast.index'),
        ];
    }
}
