<?php

namespace App\Console\Commands;

use App\Models\TechnicianSchedule;
use App\Notifications\TechnicianScheduleReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendScheduleReminders extends Command
{
    protected $signature = 'schedules:remind';

    protected $description = 'Kirim pengingat jadwal engineer yang akan segera dimulai.';

    public function handle(): int
    {
        $now = now();

        // Ambil jadwal 24 jam ke depan (batas maks reminder_minutes = 1440),
        // saring batas pastinya di PHP agar agnostik database.
        $candidates = TechnicianSchedule::with(['technician', 'owner'])
            ->whereNull('reminder_sent_at')
            ->whereNotNull('reminder_minutes')
            ->whereIn('status', ['scheduled', 'on_progress'])
            ->where('start_at', '>', $now)
            ->where('start_at', '<=', $now->copy()->addDay())
            ->get()
            ->filter(fn (TechnicianSchedule $s) => $s->start_at->lte($now->copy()->addMinutes($s->reminder_minutes)));

        $sent = 0;

        foreach ($candidates as $schedule) {
            $target = $schedule->technician ?? $schedule->owner;

            if (! $target) {
                continue;
            }

            try {
                $target->notify(new TechnicianScheduleReminderNotification($schedule));
                $schedule->forceFill(['reminder_sent_at' => $now])->saveQuietly();
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('Reminder jadwal teknisi gagal: '.$e->getMessage());
            }
        }

        $this->info("Reminder terkirim: {$sent}.");

        return self::SUCCESS;
    }
}
