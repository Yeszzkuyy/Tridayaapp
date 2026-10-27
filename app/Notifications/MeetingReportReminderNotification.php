<?php

namespace App\Notifications;

use App\Models\FollowUp;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Pengingat: meeting sudah lewat tetapi laporan (foto + MOM) belum diisi. */
class MeetingReportReminderNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public FollowUp $meeting) {}

    public function via(object $notifiable): array
    {
        if ($this->muted($notifiable)) {
            return [];
        }

        return $this->withWebPush($notifiable, ['database']);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'meeting_report_reminder',
            'meeting_id' => $this->meeting->id,
            'customer' => $this->meeting->customer?->name ?? __('Meeting'),
            'title' => __('Meeting report pending'),
            // Relatif: tetap di domain tempat user login.
            'url' => route('sales.meetings.report', $this->meeting->id, false),
        ];
    }

    protected function pushUrl(object $notifiable): string
    {
        return route('sales.meetings.report', $this->meeting->id);
    }

    protected function pushContent(): array
    {
        return [
            'title' => __('Meeting report pending'),
            'body' => $this->meeting->customer?->name ?? '',
            'url' => route('sales.meetings.report', $this->meeting->id),
        ];
    }
}
