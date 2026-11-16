<?php

namespace App\Notifications;

use App\Models\LeadTask;
use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Dikirim saat inside sales task dibuat/di-assign atau statusnya berubah. */
class LeadTaskNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsWebPush;

    public function __construct(
        public LeadTask $task,
        public string $event = 'created',
        public ?string $senderName = null,
        public ?string $message = null,
    ) {}

    /**
     * Notifikasi balasan chat (event `comment`), termasuk baris lama yang
     * hanya punya judul "Komentar baru" tanpa `event`.
     *
     * @param  array<string, mixed>  $data
     */
    public static function isReplyData(array $data): bool
    {
        if (($data['event'] ?? null) === 'comment') {
            return true;
        }

        if (($data['event'] ?? null) !== null) {
            return false;
        }

        $title = mb_strtolower((string) ($data['title'] ?? ''));

        return str_contains($title, 'komentar') || str_contains($title, 'comment');
    }

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
            'event' => $this->event,
            'title' => $this->title(),
            'lead_id' => $this->task->lead_id,
            'lead_task_id' => $this->task->id,
            'sender_name' => $this->event === 'comment' ? $this->senderName : null,
            'customer' => $this->task->lead?->customer?->name ?? 'Lead task',
            'preview' => $this->previewText(),
            'url' => $this->databaseUrl($notifiable),
        ];
    }

    /** Judul kartu/push: balasan chat menyebut nama task, bukan "Komentar baru". */
    private function title(): string
    {
        return match ($this->event) {
            'created' => 'Task baru',
            'comment' => 'Balasan baru di task: '.$this->task->title,
            'quotation' => 'Quotation baru',
            'accepted' => 'Quotation diterima',
            default => 'Status task',
        };
    }

    /** Isi kartu: pengirim + potongan pesan untuk balasan chat. */
    private function previewText(): string
    {
        if ($this->event !== 'comment') {
            return $this->task->title;
        }

        $sender = trim((string) $this->senderName);
        $message = Str::limit(trim((string) $this->message), 140);

        if ($sender !== '' && $message !== '') {
            return $sender.': '.$message;
        }

        return $sender !== '' ? $sender : $message;
    }

    /**
     * URL relatif (tanpa domain) supaya klik notifikasi tidak berpindah host
     * dan kehilangan session. Sales/management diarahkan ke menu Request →
     * Inside Sales dengan diskusi terbuka; inside-sales murni ke task miliknya.
     */
    private function databaseUrl(object $notifiable): string
    {
        // Inside Sales (termasuk multi-role sales + inside) selalu diarahkan
        // ke task di workspace Inside Sales, bukan ke hub Request milik Sales.
        $useInsideWorkspace = $notifiable instanceof User
            && $notifiable->can('manage-inside-sales');

        $useRequestHub = ! $useInsideWorkspace
            && $notifiable instanceof User
            && ($notifiable->can('view-sales')
                || $notifiable->can('manage-sales')
                || $notifiable->can('manage-sales-leads'));

        return $useRequestHub
            ? route('sales.requests.index', ['tab' => 'inside-sales', 'task' => $this->task->id], false)
            : route('lead-tasks.show', $this->task->id, false);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->event === 'comment'
                ? 'Balasan baru di task: '.$this->task->title
                : match ($this->event) {
                    'created' => 'Inside Sales Task Baru',
                    'quotation' => 'File Quotation Baru',
                    'accepted' => 'Quotation Diterima',
                    default => 'Status Task Berubah',
                })
            ->line('Task: ' . $this->task->title)
            ->line('Lead: ' . ($this->task->lead?->customer?->name ?? '-'));

        if ($this->event === 'comment' && $this->previewText() !== '') {
            $mail->line($this->previewText());
        }

        return $mail
            ->action('Lihat Task', url(route('lead-tasks.show', $this->task->id)))
            ->line('Terima kasih.');
    }

    protected function pushContent(): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->previewText(),
            'url' => route('lead-tasks.show', $this->task->id),
        ];
    }
}
