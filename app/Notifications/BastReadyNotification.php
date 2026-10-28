<?php

namespace App\Notifications;

use App\Models\Project;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke Finance saat BAST sudah lengkap: dokumen tersimpan dan
 * ditandatangani 3DY + pelanggan. Finance bisa langsung membuka
 * dokumen untuk proses penagihan/pelunasan berikutnya.
 */
class BastReadyNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public Project $project) {}

    private function showUrl(): string
    {
        return route('admin.bast.index', ['q' => $this->project->project_name]);
    }

    private function relativeUrl(): string
    {
        return route('admin.bast.index', ['q' => $this->project->project_name], false);
    }

    protected function pushUrl(object $notifiable): string
    {
        return $this->showUrl();
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
            'type' => 'bast_ready',
            'project_id' => $this->project->id,
            'title' => $this->project->project_name,
            'url' => $this->relativeUrl(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('BAST is complete and ready'))
            ->line(__('The BAST document has been uploaded and signed by both parties.'))
            ->line(__('Project: :project', ['project' => $this->project->project_name]))
            ->action(__('Open BAST list'), url($this->showUrl()))
            ->line(__('You can continue with the next billing process.'));
    }

    protected function pushContent(): array
    {
        return [
            'title' => __('BAST is complete and ready'),
            'body' => $this->project->project_name,
            'url' => $this->showUrl(),
        ];
    }
}
