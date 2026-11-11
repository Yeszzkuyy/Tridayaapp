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
        // lalu saring berdasarkan target pengingat masing-masing:
        // - angka  : start_at - X menit
        // - 0      : pagi hari H pukul 08:10 WIB
        $candidates = TechnicianSchedule::with(['technicians', 'technician', 'owner'])
            ->whereNull('reminder_sent_at')
            ->whereNotNull('reminder_minutes')
            ->whereIn('status', ['scheduled', 'on_progress'])
            ->where('start_at', '>', $now)
            ->where('start_at', '<=', $now->copy()->addDay())
            ->get()
            ->filter(function (TechnicianSchedule $schedule) use ($now) {
                $reminderAt = $schedule->reminderAt();

                return $reminderAt !== null && $reminderAt->lte($now);
            });

        $sent = 0;

        foreach ($candidates as $schedule) {
            // Pengingat dikirim ke SEMUA engineer yang bertugas (utama +
            // support), bukan hanya ke engineer utama.
            $targets = $schedule->technicianList();

            if ($targets->isEmpty() && $schedule->owner) {
                $targets = collect([$schedule->owner]);
            }

            $delivered = 0;

            foreach ($targets->unique('id') as $target) {
                try {
                    $target->notify(new TechnicianScheduleReminderNotification($schedule));
                    $delivered++;
                } catch (\Throwable $e) {
                    Log::warning('Reminder jadwal teknisi gagal: '.$e->getMessage());
                }
            }

            if ($delivered > 0) {
                $schedule->forceFill(['reminder_sent_at' => $now])->saveQuietly();
                $sent += $delivered;
            }
        }

        $this->info("Reminder terkirim: {$sent}.");

        return self::SUCCESS;
    }
}
