<?php

namespace App\Console\Commands;

use App\Models\FollowUp;
use App\Notifications\MeetingReportReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendMeetingReminders extends Command
{
    protected $signature = 'meetings:remind';

    protected $description = 'Kirim pengingat meeting yang belum diisi laporan (foto + MOM).';

    public function handle(): int
    {
        $candidates = FollowUp::with(['customer', 'creator', 'lead.assignee'])
            ->meetings()
            ->whereNull('completed_at')
            ->whereNull('reminder_sent_at')
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '<=', today())
            ->get();

        $sent = 0;

        foreach ($candidates as $meeting) {
            $target = $meeting->lead?->assignee ?? $meeting->creator;

            if (! $target) {
                continue;
            }

            try {
                $target->notify(new MeetingReportReminderNotification($meeting));
                $meeting->forceFill(['reminder_sent_at' => now()])->saveQuietly();
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('Reminder meeting gagal: '.$e->getMessage());
            }
        }

        $this->info("Reminder meeting terkirim: {$sent}.");

        return self::SUCCESS;
    }
}
